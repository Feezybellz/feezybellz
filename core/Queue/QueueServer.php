<?php

/**
 * =============================================================================
 * QueueServer — Pure PHP In-Memory Job Queue (Forking TCP Socket Server)
 * =============================================================================
 *
 * A production-grade, zero-dependency job queue server that:
 *   1. Binds a TCP socket on a configurable host:port.
 *   2. Accepts connections from QueueClient producers.
 *   3. Reads job payloads (callables + arguments) over the wire.
 *   4. Stores them in an \SplQueue (O(1) enqueue/dequeue).
 *   5. Forks a child process for each job via pcntl_fork() for true
 *      concurrency — the parent never blocks on job execution.
 *   6. Reaps zombie child processes via pcntl_waitpid(WNOHANG).
 *   7. Manages memory with gc_collect_cycles() and auto-restart.
 *
 * Wire Protocol (length-prefixed JSON):
 *   ┌──────────────────────┬──────────────────────────────────────────┐
 *   │ 4 bytes (uint32 BE)  │  JSON payload (UTF-8)                   │
 *   │ = payload length     │  {"callable":...,"args":[...]}          │
 *   └──────────────────────┴──────────────────────────────────────────┘
 *
 * @package Framework\Core\Queue
 */

namespace Framework\Core\Queue;

class QueueServer
{
    // ─── Properties ─────────────────────────────────────────────────────

    /**
     * The main server socket resource returned by stream_socket_server().
     * This is the "listener" — it does NOT carry data itself; it only
     * accepts new client connections via stream_socket_accept().
     *
     * @var resource|null
     */
    private $serverSocket = null;

    /**
     * In-memory job queue using PHP's native \SplQueue.
     *
     * WHY \SplQueue INSTEAD OF A PLAIN ARRAY?
     * ────────────────────────────────────────
     * With a plain array, array_shift() is O(N) because PHP must re-index
     * every remaining element after removing the first one. For 10,000
     * queued jobs, every dequeue copies 9,999 elements. This creates a
     * catastrophic O(N²) total cost when draining the queue.
     *
     * \SplQueue is implemented as a doubly-linked list internally. Both
     * enqueue() (append) and dequeue() (shift) are O(1) — they simply
     * update pointers. No re-indexing, no copying, no memory reallocation.
     *
     * Performance comparison (10,000 jobs):
     *   array_shift():  ~50,000,000 element moves total (O(N²))
     *   SplQueue:       ~10,000 pointer updates total   (O(N))
     *
     * Each entry stored is an associative array:
     *   [
     *       'id'       => string,          // Unique job ID for tracking
     *       'callable' => string|array,    // The PHP callable reference
     *       'args'     => array,           // Arguments to pass to the callable
     *       'queued_at'=> float,           // microtime(true) when received
     *   ]
     *
     * @var \SplDoublyLinkedList
     */
    private $jobs;

    /**
     * Array of currently connected client socket resources.
     * We track these so stream_select() can monitor them for incoming data.
     *
     * Key: integer resource ID (cast from the resource)
     * Value: the socket resource itself
     *
     * @var array
     */
    private $clientSockets = [];

    /**
     * Per-client read buffers. TCP may deliver partial data in a single
     * fread() call. We accumulate bytes here until we have a complete
     * length-prefixed message.
     *
     * Key: integer resource ID
     * Value: string of raw bytes received so far
     *
     * @var array
     */
    private $clientBuffers = [];

    /**
     * Array of currently active (running) child process PIDs.
     *
     * WHY TRACK CHILD PIDs?
     * ─────────────────────
     * When we pcntl_fork(), the OS creates a child process. When the child
     * exits, it becomes a "zombie" — its entry stays in the process table
     * until the parent calls waitpid(). If we never reap zombies, they
     * accumulate and eventually exhaust the OS process table limit.
     *
     * We store PIDs here so we know:
     *   1. How many children are currently running (to enforce $maxChildren)
     *   2. Which PIDs to check/reap in reapChildren()
     *
     * Key: int PID
     * Value: array ['job_id' => string, 'started_at' => float]
     *
     * @var array
     */
    private $activeChildren = [];

    /** @var string The IP/hostname the server binds to. */
    private $host;

    /** @var int The TCP port the server listens on. */
    private $port;

    /** @var bool Flag controlling the main event loop. */
    private $running = false;

    /**
     * Maximum number of jobs to FORK per event-loop iteration.
     * Prevents spawning too many children in a single cycle.
     *
     * @var int
     */
    private $batchSize = 10;

    /**
     * Maximum number of concurrent child processes.
     *
     * WHY CAP CHILDREN?
     * ─────────────────
     * Each pcntl_fork() creates a full copy of the process (copy-on-write).
     * Without a cap, a burst of 1,000 jobs would spawn 1,000 processes
     * simultaneously — a "fork bomb" that crashes the server.
     *
     * With $maxChildren = 5, we allow 5 jobs to run in parallel. When all
     * 5 slots are occupied, new jobs wait in the SplQueue until a child
     * exits and frees a slot.
     *
     * @var int
     */
    private $maxChildren = 5;

    /**
     * Maximum jobs to execute before the server auto-stops.
     *
     * WHY AUTO-RESTART?
     * ─────────────────
     * Long-running PHP processes slowly accumulate memory from internal
     * fragmentation, circular references that GC can't fully reclaim, and
     * extension-level leaks. After $maxJobsBeforeRestart executions, the
     * server calls stop() and exits cleanly. A process manager (Supervisor,
     * systemd) then restarts it with a completely fresh memory footprint.
     *
     * Default: 10,000 — generous enough to avoid constant restarts but
     * tight enough to prevent runaway memory growth.
     *
     * @var int
     */
    private $maxJobsBeforeRestart = 10000;

    /**
     * Maximum number of concurrent connections
     */
    private $maxClients = 100;

    public function setMaxClients(int $max): void
    {
        $this->maxClients = max(1, $max);
    }

    /** @var int Total jobs received since startup. */
    private $totalJobsReceived = 0;

    /** @var int Total jobs successfully executed. */
    private $totalJobsExecuted = 0;

    /** @var int Total jobs that failed during execution. */
    private $totalJobsFailed = 0;


    /**
     * An optional callback executed inside the child process immediately
     * before the job is run. This is the perfect place to safely
     * disconnect and reconnect framework services (like PDO or Redis)
     * so the child doesn't share the parent's TCP connections.
     *
     * @var callable|null
     */
    private $beforeJobHook = null;


    /** @var resource|null The embedded HTTP server for the UI */
    private $httpSocket = null;
    
    /** @var int|null The port for the web UI */
    private $uiPort = null;


    /**
     * @var bool If true, suppress all console output.
     */
    private $silent = false;

    // ─── Constructor ────────────────────────────────────────────────────

    /**
     * Create a new QueueServer instance.
     *
     * We initialize the \SplQueue here and store configuration.
     * No socket is opened until start() is called.
     *
     * @param string $host  The IP/hostname to bind to (default: 127.0.0.1)
     * @param int    $port  The TCP port to listen on (default: 9090)
     */
    public function __construct($host = '127.0.0.1', $port = 9090, $uiPort = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->uiPort = $uiPort;

        $this->jobs = new \SplDoublyLinkedList();
        $this->jobs->setIteratorMode(\SplDoublyLinkedList::IT_MODE_FIFO | \SplDoublyLinkedList::IT_MODE_KEEP);
    }

    // ─── Configuration ──────────────────────────────────────────────────

    /**
     * Set how many queued jobs are forked per event-loop cycle.
     *
     * @param int $size  Number of jobs to fork per cycle
     * @return void
     */
    public function setBatchSize(int $size): void
    {
        $this->batchSize = max(1, $size);
    }

    /**
     * Set the maximum number of concurrent child processes.
     *
     * @param int $max  Maximum concurrent children (default: 5)
     * @return void
     */
    public function setMaxChildren(int $max): void
    {
        $this->maxChildren = max(1, $max);
    }

    /**
     * Set the maximum number of jobs before auto-restart.
     *
     * @param int $max  Max jobs before stop() is called (default: 10000)
     * @return void
     */
    public function setMaxJobsBeforeRestart(int $max): void
    {
        $this->maxJobsBeforeRestart = max(100, $max);
    }

    /**
     * Register a callback to run inside the child process before job execution.
     *
     * @param callable $hook
     * @return void
     */
    public function setBeforeJobHook(callable $hook): void
    {
        $this->beforeJobHook = $hook;
    }

    // ─── Server Lifecycle ───────────────────────────────────────────────

    /**
     * Start the queue server — bind the socket and enter the event loop.
     *
     * This method blocks indefinitely (it IS the long-running process).
     * It only returns when stop() is called or a fatal error occurs.
     *
     * @return void
     * @throws \RuntimeException if the socket cannot be created
     */
    public function start(): void
    {
        // ── Step 0: Modern Asynchronous Signal Handling ─────────────────
        // In PHP 7.1+, this tells the engine to handle signals in the
        // background automatically, completely removing the need to call
        // pcntl_signal_dispatch() inside our tight while() loop.
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
        // ── Step 1: Create the TCP server socket ────────────────────────
        //
        // stream_socket_server() creates a socket, binds it to the address,
        // and starts listening for connections — all in one call.
        //
        // The returned resource is NOT a data socket — it only accepts new
        // connections. Think of it as the "front door" of the server.
        $address = "tcp://{$this->host}:{$this->port}";

        $this->serverSocket = @stream_socket_server(
            $address,               // The full address string to bind to
            $errno,                 // Populated with the error number on failure
            $errstr,                // Populated with the error message on failure
            STREAM_SERVER_BIND      // Flag: bind the socket to the address
            | STREAM_SERVER_LISTEN  // Flag: start listening for incoming connections
        );

        // ── Step 2: Verify the socket was created successfully ──────────
        if ($this->serverSocket === false) {
            throw new \RuntimeException(
                "Failed to create queue server socket at {$address}: {$errstr} (errno: {$errno})"
            );
        }

        // ── Step 3: Set the server socket to non-blocking mode ──────────
        //
        // In non-blocking mode, stream_socket_accept() returns immediately
        // with false if no client is waiting. We use stream_select() to
        // determine WHEN to accept.
        stream_set_blocking($this->serverSocket, false);

        // ── NEW: Bind the HTTP UI Socket ──
        if ($this->uiPort !== null) {
            $httpAddress = "tcp://{$this->host}:{$this->uiPort}";
            $this->httpSocket = @stream_socket_server($httpAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
            if ($this->httpSocket === false) {
                throw new \RuntimeException("Failed to create UI socket at {$httpAddress}: {$errstr}");
            }
            stream_set_blocking($this->httpSocket, false);
        }

        // ── Step 4: Register OS signal handlers ─────────────────────────
        //
        // SIGINT  (2)  = Ctrl+C — user wants to stop
        // SIGTERM (15) = kill — process manager wants to stop
        // SIGCHLD (17) = child process exited — we need to reap it
        //
        // Without SIGCHLD handling, child exits could interrupt
        // stream_select() with EINTR. We handle that in the loop.
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->log("Received SIGINT (Ctrl+C) — shutting down gracefully...");
                $this->running = false;
            });

            pcntl_signal(SIGTERM, function () {
                $this->log("Received SIGTERM — shutting down gracefully...");
                $this->running = false;
            });

            // SIGCHLD: fired when any child process exits. We set SIG_DFL
            // (default handling) but the real reaping happens in
            // reapChildren() via pcntl_waitpid(). We just need PHP to
            // not ignore the signal so stream_select() wakes up.
            pcntl_signal(SIGCHLD, SIG_DFL);
        }

        // ── Step 5: Mark the server as running and log the startup ───────
        $this->running = true;
        $this->log("Queue server started on {$address}");
        $this->log("Config: maxChildren={$this->maxChildren}, "
            . "batchSize={$this->batchSize}, "
            . "maxJobsBeforeRestart={$this->maxJobsBeforeRestart}");
        $this->log("Waiting for jobs... (press Ctrl+C to stop)");

        // ── Step 6: Enter the main event loop ───────────────────────────
        $this->loop();

        // ── Step 7: Clean up after the loop exits ───────────────────────
        $this->shutdown();
    }

    /**
     * Signal the server to stop after the current event-loop iteration.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Clean up all resources: reap remaining children, close sockets,
     * and log final statistics.
     *
     * @return void
     */
    private function shutdown(): void
    {
        $this->log("Shutting down queue server...");

        // ── Wait for all active children to finish ──────────────────────
        //
        // During normal operation we use WNOHANG (non-blocking). During
        // shutdown we use a blocking wait (0 flags) to ensure all children
        // complete before we close the server socket. This prevents
        // orphaned child processes.
        if (!empty($this->activeChildren)) {
            $this->log("Waiting for " . count($this->activeChildren) . " active child process(es) to finish...");

            foreach (array_keys($this->activeChildren) as $pid) {
                // Blocking wait — waits until this specific child exits
                pcntl_waitpid($pid, $status, 0);
                $this->log("[REAP] Child PID {$pid} finished during shutdown");
            }

            // Clear the tracking array now that all children are reaped
            $this->activeChildren = [];
        }

        // ── Close every connected client socket ─────────────────────────
        foreach (array_keys($this->clientSockets) as $resourceId) {
            $this->disconnectClient($resourceId);
        }

        // ── Close the main server (listener) socket ─────────────────────
        if ($this->serverSocket && is_resource($this->serverSocket)) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }

        // ── Log final execution statistics ──────────────────────────────
        $this->log("Server stopped.");
        $this->log("Stats: {$this->totalJobsReceived} received, "
            . "{$this->totalJobsExecuted} executed, "
            . "{$this->totalJobsFailed} failed, "
            . $this->jobs->count() . " still queued");
    }

    // ─── Event Loop ─────────────────────────────────────────────────────

    /**
     * The main event loop — the beating heart of the queue server.
     *
     * On each iteration, it does four things:
     *   1. stream_select() — wait for I/O activity
     *   2. Accept connections / read data
     *   3. Reap finished child processes (zombie cleanup)
     *   4. Fork new children for pending jobs
     *
     * @return void
     */
    /**
     * The main event loop — the beating heart of the queue server.
     *
     * On each iteration, it does four things:
     * 1. stream_select() — wait for I/O activity on TCP, HTTP, and client sockets
     * 2. Accept connections / read data
     * 3. Reap finished child processes (zombie cleanup)
     * 4. Fork new children for pending jobs
     *
     * @return void
     */
    private function loop(): void
    {
        while ($this->running) {
            // ── Step 1: Build the array of sockets to monitor ───────────
            $socketsToWatch = [$this->serverSocket];
            
            // Add the HTTP socket to the watch list if it is enabled
            if ($this->httpSocket) {
                $socketsToWatch[] = $this->httpSocket;
            }

            $readSockets = array_merge(
                $socketsToWatch,
                array_values($this->clientSockets)
            );

            $writeSockets = null;
            $exceptSockets = null;

            // ── Step 2: Wait for I/O activity (200ms timeout) ───────────
            // stream_select() blocks until a socket has data OR timeout.
            // 200ms keeps us responsive for signal handling and job
            // processing without burning CPU on idle loops.
            $activity = @stream_select($readSockets, $writeSockets, $exceptSockets, 0, 200000);

            // ── Step 3: Handle stream_select() errors ───────────────────
            // Returns false on error (typically EINTR from a signal like
            // SIGCHLD interrupting the syscall). This is normal and safe.
            if ($activity === false) {
                if (!$this->running) {
                    break;
                }
                // EINTR from SIGCHLD or other signal — fall through to
                // reapChildren() and processJobs() below
            }

            // ── Step 4: Handle I/O on ready sockets ─────────────────────
            if ($activity > 0) {
                // 4a. Check if the TCP SERVER socket is ready (new worker connection)
                if (in_array($this->serverSocket, $readSockets, true)) {
                    $this->acceptNewConnection();
                }

                // 4b. Check if the HTTP UI socket is ready (new browser request)
                if ($this->httpSocket && in_array($this->httpSocket, $readSockets, true)) {
                    $this->handleHttpConnection();
                }

                // 4c. Check each CLIENT socket for incoming data
                foreach ($readSockets as $socket) {
                    // Skip the listener sockets, we only want actual connected clients here
                    if ($socket === $this->serverSocket || $socket === $this->httpSocket) {
                        continue;
                    }
                    $resourceId = (int) $socket;
                    $this->readClientData($resourceId);
                }
            }

            // ── Step 5: Reap finished child processes ───────────────────
            // This MUST run every iteration — even after stream_select()
            // timeouts or EINTR errors — to prevent zombie accumulation.
            $this->reapChildren();

            // ── Step 6: Fork new children for pending jobs ──────────────
            $this->processJobs();
        }
    }

    // ─── Zombie Process Reaping ─────────────────────────────────────────

    /**
     * Reap (clean up) all finished child processes.
     *
     * HOW ZOMBIE PROCESSES WORK:
     * ──────────────────────────
     * When a child process exits, the OS doesn't fully remove it from the
     * process table. Instead, it becomes a "zombie" (state Z) — it retains
     * its PID and exit status so the parent can read them via waitpid().
     *
     * If the parent never calls waitpid(), zombies accumulate. Each zombie
     * consumes a PID and a slot in the kernel's process table. Eventually,
     * the system can't fork new processes at all (fork() returns -1).
     *
     * HOW pcntl_waitpid() WORKS:
     * ──────────────────────────
     * pcntl_waitpid($pid, &$status, $options)
     *
     *   $pid = -1   → wait for ANY child process
     *   $status     → populated with the child's exit information
     *   $options:
     *     0         → BLOCKING: waits until a child exits
     *     WNOHANG   → NON-BLOCKING: returns immediately if no child has
     *                  exited yet (returns 0)
     *
     *   Returns:
     *     > 0       → PID of the reaped child
     *     0         → WNOHANG mode and no child has exited yet
     *     -1        → no children exist at all (or error)
     *
     * We use WNOHANG in the event loop so we never block the server
     * waiting for a child. We loop until it returns 0 or -1 to reap ALL
     * finished children in one pass (multiple children might finish
     * between loop iterations).
     *
     * @return void
     */
    private function reapChildren(): void
    {
        // ── Early exit if no children are active ────────────────────────
        if (empty($this->activeChildren)) {
            return;
        }

        // ── Loop to reap ALL finished children ──────────────────────────
        //
        // We must loop because multiple children could exit between
        // loop iterations. A single waitpid() call only reaps ONE child.
        while (true) {
            // pcntl_waitpid(-1, ...) = wait for ANY child process
            // WNOHANG = don't block if no child has exited yet
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            // ── pid > 0: a child was reaped ─────────────────────────────
            if ($pid > 0) {
                // Retrieve the job info we stored when we forked this child
                $childInfo = $this->activeChildren[$pid] ?? ['job_id' => 'unknown'];
                $jobId = $childInfo['job_id'];

                // ── Determine HOW the child exited ──────────────────────
                //
                // pcntl_wifexited(): did the child exit normally (via exit())?
                // pcntl_wexitstatus(): the exit code (0 = success, >0 = failure)
                // pcntl_wifsignaled(): was the child killed by a signal?
                // pcntl_wtermsig(): which signal killed it?
                if (pcntl_wifexited($status)) {
                    $exitCode = pcntl_wexitstatus($status);

                    if ($exitCode === 0) {
                        // Child exited with code 0 = job succeeded
                        $this->totalJobsExecuted++;
                        $this->log("[REAP] Child PID {$pid} [{$jobId}] completed successfully");
                    } else {
                        // Child exited with non-zero = job failed
                        $this->totalJobsFailed++;
                        $this->log("[REAP] Child PID {$pid} [{$jobId}] exited with code {$exitCode}");
                    }
                } elseif (pcntl_wifsignaled($status)) {
                    // The child was killed by a signal (SIGKILL, SIGSEGV, etc.)
                    $signal = pcntl_wtermsig($status);
                    $this->totalJobsFailed++;
                    $this->log("[REAP] Child PID {$pid} [{$jobId}] killed by signal {$signal}");
                }

                // Remove the child from our tracking array to free its slot
                unset($this->activeChildren[$pid]);

                // ── Check auto-restart threshold ────────────────────────
                //
                // After enough jobs, we stop the server so a process manager
                // can restart it with fresh memory.
                $totalCompleted = $this->totalJobsExecuted + $this->totalJobsFailed;
                if ($totalCompleted >= $this->maxJobsBeforeRestart) {
                    $this->log("Reached max jobs threshold ({$this->maxJobsBeforeRestart}) — initiating graceful restart");
                    $this->stop();
                }

                // Continue the loop to check for more finished children
                continue;
            }

            // ── pid = 0: no more children have exited (WNOHANG) ─────────
            // ── pid = -1: no children exist at all, or error ────────────
            //
            // In both cases, we're done reaping for this iteration.
            break;
        }
    }

    // ─── Connection Handling ────────────────────────────────────────────

    /**
     * Accept a new incoming client connection.
     *
     * stream_socket_accept() completes the TCP handshake and returns a
     * new socket resource for the specific client.
     *
     * @return void
     */
    private function acceptNewConnection(): void
    {
        if (count($this->clientSockets) >= $this->maxClients) {
            $rejected = @stream_socket_accept($this->serverSocket, 0);
            if ($rejected !== false) {
                @fclose($rejected);
            }
            $this->log("Rejected connection: max clients ({$this->maxClients}) reached");
            return;
        }

        // stream_socket_accept() does the TCP three-way handshake.
        // The @ suppresses warnings from race conditions (client
        // disconnects between select() and accept()).
        $clientSocket = @stream_socket_accept($this->serverSocket, 0, $peerName);

        if ($clientSocket === false) {
            $this->log("Failed to accept incoming connection (client may have disconnected)");
            return;
        }

        // Non-blocking so fread() returns immediately when no data is
        // available. stream_select() tells us WHEN data arrives.
        stream_set_blocking($clientSocket, false);

        // Track the client using its resource ID as a unique integer key
        $resourceId = (int) $clientSocket;
        $this->clientSockets[$resourceId] = $clientSocket;
        $this->clientBuffers[$resourceId] = '';

        $this->log("Client connected: {$peerName} (resource #{$resourceId})");
    }

    /**
     * Read data from a connected client and process complete messages.
     *
     * TCP is a stream protocol — a single fread() might return a partial
     * message, a complete message, or multiple messages concatenated. We
     * buffer all bytes and only process when we have a complete frame.
     *
     * @param int $resourceId  The integer resource ID of the client socket
     * @return void
     */
    private function readClientData(int $resourceId): void
    {
        if (!isset($this->clientSockets[$resourceId])) {
            return;
        }

        $socket = $this->clientSockets[$resourceId];

        // fread() reads up to 8192 bytes (typical TCP segment size).
        // In non-blocking mode, returns whatever is available right now.
        $data = @fread($socket, 8192);

        // Check for disconnection: fread() returns false on error,
        // empty string + feof() means the remote end closed.
        if ($data === false || ($data === '' && feof($socket))) {
            $this->log("Client disconnected (resource #{$resourceId})");
            $this->disconnectClient($resourceId);
            return;
        }

        // Empty string but socket still open = no data yet (non-blocking)
        if ($data === '') {
            return;
        }

        // Append to this client's buffer and try to extract messages
        $this->clientBuffers[$resourceId] .= $data;
        $this->processClientBuffer($resourceId);
    }

    /**
     * Extract complete length-prefixed messages from a client's buffer.
     *
     * Format: [4 bytes big-endian uint32 = length][JSON payload]
     *
     * @param int $resourceId  The integer resource ID of the client socket
     * @return void
     */
    /**
     * Extract complete length-prefixed messages from a client's buffer.
     *
     * Format: [4 bytes big-endian uint32 = length][JSON payload]
     *
     * @param int $resourceId  The integer resource ID of the client socket
     * @return void
     */
    private function processClientBuffer(int $resourceId): void
    {
        // Loop because TCP coalescing might deliver multiple messages
        while (true) {
            // ── THE FIX ─────────────────────────────────────────────────────
            // If handleJobPayload() successfully processed a message and 
            // aggressively disconnected the client, the buffer is gone.
            // We must break immediately to prevent undefined key warnings.
            if (!isset($this->clientBuffers[$resourceId])) {
                break;
            }

            $buffer = $this->clientBuffers[$resourceId];

            // Need at least 4 bytes for the length header
            if (strlen($buffer) < 4) {
                break;
            }

            // unpack('N', ...) decodes 4 bytes as big-endian unsigned
            // 32-bit integer (network byte order)
            $header = unpack('N', substr($buffer, 0, 4));
            $payloadLength = $header[1];

            // Reject payloads over 1MB to prevent memory exhaustion
            $maxPayloadSize = 1024 * 1024;
            if ($payloadLength > $maxPayloadSize) {
                $this->log("Rejecting oversized payload ({$payloadLength} bytes) from resource #{$resourceId}");
                $this->sendResponse($resourceId, false, "Payload too large (max {$maxPayloadSize} bytes)");
                $this->disconnectClient($resourceId);
                return;
            }

            // Wait for more data if the full payload hasn't arrived
            $totalMessageLength = 4 + $payloadLength;
            if (strlen($buffer) < $totalMessageLength) {
                break;
            }

            // Extract the JSON payload and advance the buffer
            $jsonPayload = substr($buffer, 4, $payloadLength);
            $this->clientBuffers[$resourceId] = substr($buffer, $totalMessageLength);

            // Parse, validate, and enqueue the job
            $this->handleJobPayload($resourceId, $jsonPayload);
        }
    }

    /**
     * Parse a JSON job payload, validate it, and add it to the SplQueue.
     *
     * STRICT JSON PARSING:
     * ────────────────────
     * We use json_decode() with JSON_THROW_ON_ERROR and a depth limit of
     * 512. JSON_THROW_ON_ERROR makes json_decode() throw a \JsonException
     * instead of silently returning null. The depth limit of 512 prevents
     * stack overflow from deeply nested malicious payloads.
     *
     * AGGRESSIVE CONNECTION MANAGEMENT:
     * ─────────────────────────────────
     * After sending the ACK, we immediately disconnect the client from the
     * server side. We do NOT rely on the client to close the connection.
     * This prevents connection leaks from slow/buggy clients and frees
     * file descriptors faster.
     *
     * @param int    $resourceId  The client that sent this payload
     * @param string $json        The raw JSON string
     * @return void
     */
    private function handleJobPayload($resourceId, $json): void
{
    // ── Step 1: Strict JSON decode ───────────────────────────────────────
    try {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        $this->log("Invalid JSON from resource #{$resourceId}: {$e->getMessage()}");
        $this->sendResponse($resourceId, false, "Invalid JSON: {$e->getMessage()}");
        $this->disconnectClient($resourceId);
        return;
    }

    // ── Step 2: Intercept system commands (stats, etc.) ──────────────────
    if (isset($payload['command']) && $payload['command'] === 'stats') {
        $stats = $this->getStats();
        $queuedJobs = [];
        $this->jobs->rewind();
        while ($this->jobs->valid() && count($queuedJobs) < 50) {
            $queuedJobs[] = $this->jobs->current();
            $this->jobs->next();
        }
        $stats['next_jobs'] = $queuedJobs;
        $this->sendResponse($resourceId, true, "Stats retrieved", $stats);
        $this->disconnectClient($resourceId);
        return;
    }

    // ── Step 3: Validate payload shape ───────────────────────────────────
    $type = isset($payload['type']) ? $payload['type'] : 'callable';
    $args = isset($payload['args']) && is_array($payload['args'])
          ? $payload['args']
          : [];

    if ($type !== 'callable') {
        $this->log("Rejected unsupported payload type '{$type}' from resource #{$resourceId}");
        $this->sendResponse($resourceId, false, 'Only named callables are allowed by security policy');
        $this->disconnectClient($resourceId);
        return;
    }

    $callable = $payload['callable'] ?? null;
    if ($callable === null) {
        $this->log("Missing 'callable' key from resource #{$resourceId}");
        $this->sendResponse($resourceId, false, "Missing required 'callable' key");
        $this->disconnectClient($resourceId);
        return;
    }

    if (!$this->isValidCallablePayload($callable)) {
        $this->log("Rejected invalid callable payload from resource #{$resourceId}");
        $this->sendResponse($resourceId, false, 'Invalid callable format');
        $this->disconnectClient($resourceId);
        return;
    }

    $callableName = is_array($callable) ? implode('::', $callable) : (string) $callable;

    // ── Step 4: Generate a unique job ID ────────────────────────────────
    $this->totalJobsReceived++;
    $jobId = sprintf('job_%d_%x', $this->totalJobsReceived, mt_rand());

    // ── Step 5: Enqueue ─────────────────────────────────────────────────
    $job = [
        'id'        => $jobId,
        'type'      => $type,
        'callable'  => $callable,
        'args'      => $args,
        'queued_at' => microtime(true),
    ];

    $this->jobs->push($job);
    $this->log("Job queued: [{$jobId}] {$callableName}(" . $this->formatArgs($args) . ")");

    // ── Step 6: ACK + close ─────────────────────────────────────────────
    $this->sendResponse($resourceId, true, "Job queued successfully", ['job_id' => $jobId]);
    $this->disconnectClient($resourceId);
}

    private function isValidCallablePayload($callable): bool
    {
        if (is_string($callable)) {
            if (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $callable)) {
                return false;
            }

            $blocked = ['system', 'exec', 'shell_exec', 'passthru', 'assert', 'eval', 'unserialize'];
            return !in_array(strtolower($callable), $blocked, true);
        }

        if (is_array($callable) && count($callable) === 2 && is_string($callable[0]) && is_string($callable[1])) {
            return preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $callable[0])
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $callable[1]);
        }

        return false;
    }


    // ─── Job Execution (Forking) ────────────────────────────────────────

    /**
     * Dequeue and fork child processes for pending jobs.
     *
     * This method is called every event-loop iteration. It dequeues up
     * to $batchSize jobs from the SplQueue and forks a child process for
     * each one, as long as we haven't hit the $maxChildren cap.
     *
     * After processing, it calls gc_collect_cycles() to reclaim memory
     * from circular references that PHP's refcount GC can't handle.
     *
     * @return void
     */
    private function processJobs(): void
    {
        // ── Early exit if the queue is empty ─────────────────────────────
        if ($this->jobs->isEmpty()) {
            return;
        }

        $forked = 0;

        while ($forked < $this->batchSize && !$this->jobs->isEmpty()) {
            // ── Check if we've hit the concurrent children cap ───────────
            //
            // If all slots are occupied, we stop forking and wait for
            // children to finish. The jobs stay in the SplQueue.
            if (count($this->activeChildren) >= $this->maxChildren) {
                $this->log("Max children ({$this->maxChildren}) reached — deferring "
                    . $this->jobs->count() . " remaining job(s)");
                break;
            }

            // ── Dequeue the oldest job ──────────────────────────────────
            //
            // SplQueue::dequeue() is O(1) — it detaches the head node of
            // the doubly-linked list by updating pointers. No re-indexing
            // of remaining elements occurs (unlike array_shift which is
            // O(N) because it must renumber all integer keys).
            $job = $this->jobs->shift();

            // ── Fork a child process ────────────────────────────────────
            $this->executeJob($job);

            $forked++;
        }

        // ── Log remaining queue depth ───────────────────────────────────
        if (!$this->jobs->isEmpty()) {
            $this->log("Queue depth: " . $this->jobs->count() . " job(s) remaining");
        }

        // ── Force garbage collection ────────────────────────────────────
        //
        // PHP's default GC uses reference counting, which can't handle
        // circular references (A → B → A). gc_collect_cycles() runs the
        // cycle-detecting collector to reclaim that memory.
        //
        // We call it after each batch to prevent gradual memory bloat
        // in the long-running parent process. The cost is minimal
        // (typically < 1ms) compared to the I/O we're doing.
        gc_collect_cycles();
    }

    /**
     * Fork a child process to execute a single job.
     *
     * HOW pcntl_fork() WORKS:
     * ───────────────────────
     * pcntl_fork() creates a nearly identical COPY of the current process.
     * After the call, there are TWO processes running the SAME code:
     *
     * - PARENT PROCESS: pcntl_fork() returns the child's PID (> 0)
     * - CHILD PROCESS:  pcntl_fork() returns 0
     * - ON ERROR:       pcntl_fork() returns -1
     *
     * The child inherits EVERYTHING from the parent: variables, open file
     * descriptors (including sockets and DB connections!), memory. But changes
     * in the child do NOT affect the parent (copy-on-write).
     *
     * @param array $job  The job record from the queue
     * @return void
     */
    private function executeJob(array $job): void
    {
        $jobId        = $job['id'];
        $callable     = $job['callable'];
        $args         = $job['args'];

        // Format the callable name for logging
        if (is_array($callable)) {
            $callableName = implode('::', $callable);
        } else {
            $callableName = (string) $callable;
        }

        $waitTime = round((microtime(true) - $job['queued_at']) * 1000, 2);

        // ── Fork: create a child process ────────────────────────────────
        $pid = pcntl_fork();

        // ── FORK FAILED ─────────────────────────────────────────────────
        if ($pid === -1) {
            $this->totalJobsFailed++;
            $this->log("[FORK] FAILED to fork for [{$jobId}] {$callableName} — re-enqueuing");
            $this->jobs->unshift($job);
            return;
        }

        // ── CHILD PROCESS ───────────────────────────────────────────────
        if ($pid === 0) {
            // Close all inherited sockets so the child can't interfere
            // with the parent's network connections
            if ($this->serverSocket && is_resource($this->serverSocket)) {
                fclose($this->serverSocket);
            }
            foreach ($this->clientSockets as $clientSocket) {
                if (is_resource($clientSocket)) {
                    fclose($clientSocket);
                }
            }

            // Re-seed RNG so siblings don't share the same random sequence
            mt_srand();

            // Run the framework hook (reconnect DB/Redis, etc.)
            if (is_callable($this->beforeJobHook)) {
                try {
                    call_user_func($this->beforeJobHook);
                } catch (\Throwable $e) {
                    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] [CHILD] Hook Failed [{$jobId}]: {$e->getMessage()}\n");
                    exit(1);
                }
            }

            if (!is_callable($callable)) {
                fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] [CHILD] NOT CALLABLE [{$jobId}] {$callableName}\n");
                exit(1);
            }

            // ── Execute ──────────────────────────────────────────────────
            try {
                $result    = call_user_func_array($callable, $args);
                $resultStr = $this->truncate(var_export($result, true), 200);
                fwrite(STDOUT, "[" . date('Y-m-d H:i:s') . "] [CHILD] SUCCESS [{$jobId}] {$callableName} => {$resultStr}\n");
                exit(0);
            } catch (\Throwable $e) {
                fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] [CHILD] FAILED [{$jobId}] {$callableName} — " . get_class($e) . ": {$e->getMessage()}\n");
                exit(1);
            }
        }

        // ── PARENT PROCESS ───────────────────────────────────────────────
        $this->activeChildren[$pid] = [
            'job_id'     => $jobId,
            'started_at' => microtime(true),
        ];

        $this->log("[FORK] Child PID {$pid} spawned for [{$jobId}] {$callableName}({$this->formatArgs($args)}) — waited {$waitTime}ms");
    }

    // ─── Client Communication ───────────────────────────────────────────

    /**
     * Send a length-prefixed JSON response back to a client.
     *
     * @param int    $resourceId  Client socket resource ID
     * @param bool   $success     Whether the operation succeeded
     * @param string $message     Human-readable status message
     * @param array  $data        Optional extra data (e.g., job_id)
     * @return void
     */
    private function sendResponse($resourceId, $success, $message, array $data = []): void
    {
        if (!isset($this->clientSockets[$resourceId])) {
            return;
        }

        // Build the response payload
        $response = ['success' => $success, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }

        $json = json_encode($response);

        // pack('N', ...) = 4-byte big-endian unsigned int (network byte order)
        $header = pack('N', strlen($json));
        $message = $header . $json;

        // Write with a loop to handle partial writes
        $totalWritten = 0;
        $messageLength = strlen($message);

        while ($totalWritten < $messageLength) {
            $written = @fwrite(
                $this->clientSockets[$resourceId],
                substr($message, $totalWritten)
            );

            if ($written === false || $written === 0) {
                $this->log("Failed to write response to client #{$resourceId}");
                $this->disconnectClient($resourceId);
                return;
            }

            $totalWritten += $written;
        }
    }

    /**
     * Disconnect a client: close the socket and clean up tracking arrays.
     *
     * @param int $resourceId  The integer resource ID of the client socket
     * @return void
     */
    private function disconnectClient(int $resourceId): void
    {
        if (isset($this->clientSockets[$resourceId]) && is_resource($this->clientSockets[$resourceId])) {
            fclose($this->clientSockets[$resourceId]);
        }

        // Free memory by removing from all tracking arrays
        unset($this->clientSockets[$resourceId]);
        unset($this->clientBuffers[$resourceId]);
    }

    // ─── Utilities ──────────────────────────────────────────────────────

    /**
     * Format an arguments array into a human-readable log string.
     *
     * @param array $args  The arguments array
     * @return string
     */
    private function formatArgs(array $args): string
    {
        if (empty($args)) {
            return '';
        }

        return implode(', ', array_map(function ($arg) {
            if (is_string($arg))  return "'" . $this->truncate($arg, 50) . "'";
            if (is_array($arg))   return '[array(' . count($arg) . ')]';
            if (is_bool($arg))    return $arg ? 'true' : 'false';
            if (is_null($arg))    return 'null';
            return (string) $arg;
        }, $args));
    }

    /**
     * Truncate a string, appending '...' if truncated.
     *
     * @param string $str     The string to truncate
     * @param int    $maxLen  Maximum allowed length
     * @return string
     */
    private function truncate(string $str, int $maxLen): string
    {
        if (strlen($str) <= $maxLen) {
            return $str;
        }
        return substr($str, 0, $maxLen - 3) . '...';
    }

    /**
     * @param bool $silent
     * @return void
     */
    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
    }

    /**
     * Write a timestamped log line to STDOUT.
     *
     * @param string $message  The message to log
     * @return void
     */
    private function log(string $message): void
    {
        if ($this->silent) return;
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    }

    // ─── Public Accessors ───────────────────────────────────────────────

    /**
     * Get the current number of jobs waiting in the queue.
     *
     * @return int
     */
    public function getQueueDepth(): int
    {
        return $this->jobs->count();
    }

    /**
     * Get server statistics as an associative array.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'received'        => $this->totalJobsReceived,
            'executed'        => $this->totalJobsExecuted,
            'failed'          => $this->totalJobsFailed,
            'queue_depth'     => $this->jobs->count(),
            'connections'     => count($this->clientSockets),
            'active_children' => count($this->activeChildren),
        ];
    }


    /**
     * Handle incoming HTTP requests for the Web UI.
     */
    private function handleHttpConnection(): void
    {
        $client = @stream_socket_accept($this->httpSocket, 0);
        if ($client === false) return;

        // Read the HTTP request (we only need the first chunk to get the route)
        $request = @fread($client, 2048);
        if (!$request) {
            fclose($client);
            return;
        }

        // Extremely basic HTTP routing
        if (strpos($request, 'GET /api/stats') === 0) {
            $this->serveHttpStats($client);
        } else {
            $this->serveHtmlDashboard($client);
        }

        fclose($client);
    }

    private function serveHttpStats($client): void
    {
        $stats = $this->getStats();
        
        // Add "Up Next" jobs to stats for the UI
        $queuedJobs = [];
        $this->jobs->rewind();
        while ($this->jobs->valid() && count($queuedJobs) < 5) {
            $queuedJobs[] = $this->jobs->current();
            $this->jobs->next();
        }
        $stats['next_jobs'] = $queuedJobs;

        $json = json_encode(['success' => true, 'data' => $stats]);
        
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: application/json\r\n";
        $response .= "Access-Control-Allow-Origin: *\r\n";
        $response .= "Connection: close\r\n";
        $response .= "Content-Length: " . strlen($json) . "\r\n\r\n";
        $response .= $json;

        @fwrite($client, $response);
    }

    private function serveHtmlDashboard($client): void
    {
        $html = $this->getDashboardHtml();
        
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/html; charset=UTF-8\r\n";
        $response .= "Connection: close\r\n";
        $response .= "Content-Length: " . strlen($html) . "\r\n\r\n";
        $response .= $html;

        @fwrite($client, $response);
    }

    private function getDashboardHtml(): string
    {
        // Notice the fetch('/api/stats') inside the script block!
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standalone Queue UI</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #111827; color: #f9fafb; padding: 2rem; }
        .dashboard { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #374151; padding-bottom: 1rem;}
        .status-badge { padding: 0.5rem 1rem; border-radius: 9999px; font-weight: bold; font-size: 0.875rem; }
        .status-online { background: #065f46; color: #a7f3d0; }
        .status-offline { background: #991b1b; color: #fecaca; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .card { background: #1f2937; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #374151; }
        .card h3 { margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #9ca3af; text-transform: uppercase; }
        .card .value { font-size: 2rem; font-weight: bold; margin: 0; }
        .job-list { background: #1f2937; border-radius: 0.5rem; padding: 1rem; border: 1px solid #374151; }
        .job-item { padding: 0.5rem 0; border-bottom: 1px solid #374151; font-family: monospace; font-size: 0.9rem; color: #d1d5db;}
        .job-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
<div class="dashboard">
    <div class="header">
        <h1>Queue Daemon: tcp://{$this->host}:{$this->port}</h1>
        <div id="statusBadge" class="status-badge status-offline">Connecting...</div>
    </div>
    <div class="grid">
        <div class="card"><h3>Active Workers</h3><p id="val-active-children" class="value text-blue">0</p></div>
        <div class="card"><h3>Queue Depth</h3><p id="val-queue-depth" class="value">0</p></div>
        <div class="card"><h3>TCP Connections</h3><p id="val-connections" class="value">0</p></div>
    </div>
    <div class="grid">
        <div class="card"><h3>Total Received</h3><p id="val-received" class="value">0</p></div>
        <div class="card"><h3>Total Executed</h3><p id="val-executed" class="value" style="color: #34d399;">0</p></div>
        <div class="card"><h3>Total Failed</h3><p id="val-failed" class="value" style="color: #f87171;">0</p></div>
    </div>
    <div class="job-list">
        <h3 style="margin-top:0; color: #9ca3af;">Up Next</h3>
        <div id="next-jobs">Waiting for jobs...</div>
    </div>
</div>
<script>
    async function fetchStats() {
        try {
            const res = await fetch('/api/stats');
            const result = await res.json();
            if (result.success) {
                document.getElementById('statusBadge').textContent = 'ONLINE';
                document.getElementById('statusBadge').className = 'status-badge status-online';
                
                const s = result.data;
                document.getElementById('val-active-children').textContent = s.active_children;
                document.getElementById('val-queue-depth').textContent = s.queue_depth;
                document.getElementById('val-connections').textContent = s.connections;
                document.getElementById('val-received').textContent = s.received;
                document.getElementById('val-executed').textContent = s.executed;
                document.getElementById('val-failed').textContent = s.failed;

                const jobsContainer = document.getElementById('next-jobs');
                if (s.next_jobs && s.next_jobs.length > 0) {
                    jobsContainer.innerHTML = s.next_jobs.map(j => {
                        const call = Array.isArray(j.callable) ? j.callable.join('::') : j.callable;
                        return `<div class="job-item">[\${j.id}] \${call}</div>`;
                    }).join('');
                } else {
                    jobsContainer.innerHTML = '<div class="job-item">Queue is empty.</div>';
                }
            }
        } catch (e) {
            document.getElementById('statusBadge').textContent = 'OFFLINE';
            document.getElementById('statusBadge').className = 'status-badge status-offline';
        }
    }
    fetchStats();
    setInterval(fetchStats, 1000);
</script>
</body>
</html>
HTML;
    }
}
