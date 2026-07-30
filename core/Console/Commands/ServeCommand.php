<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class ServeCommand extends Command
{
    public function execute(): void
    {
        $host = $this->option('host', '127.0.0.1');
        $port = $this->option('port', '8000');

        $base = dirname(dirname(dirname(__DIR__))); // project root

        // Resolve the public docroot. Deployments root at www/ (nginx), but the
        // framework default is public/ — accept either (or an explicit --docroot),
        // requiring an index.php front controller inside it.
        $docroot = null;
        $candidates = array_filter([$this->option('docroot'), 'www', 'public']);
        foreach ($candidates as $cand) {
            $path = (strncmp($cand, '/', 1) === 0) ? $cand : $base . '/' . $cand;
            if (is_dir($path) && is_file($path . '/index.php')) {
                $docroot = rtrim($path, '/');
                break;
            }
        }

        if ($docroot === null) {
            $this->error('Public directory not found. Looked for www/ or public/ (with an index.php) under ' . $base . '.');
            return;
        }

        // Router script: serve an existing static file as-is, otherwise route the
        // request through the front controller. Without this the built-in server
        // 404s any URL that isn't a real file (i.e. every framework route).
        $router = tempnam(sys_get_temp_dir(), 'serve_router_') . '.php';
        file_put_contents(
            $router,
            "<?php\n"
            . '$docroot = ' . var_export($docroot, true) . ";\n"
            . '$uri = urldecode(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");' . "\n"
            . 'if ($uri !== "/" && is_file($docroot . $uri)) { return false; }' . "\n"
            . 'require $docroot . "/index.php";' . "\n"
        );

        $this->info("Starting development server on http://{$host}:{$port}");
        $this->info("Serving: {$docroot}");
        $this->info("Press Ctrl+C to stop the server.\n");

        $command = sprintf(
            'php -S %s:%s -t %s %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docroot),
            escapeshellarg($router)
        );

        // Spawn with the .env-derived variables stripped. The console process
        // baked .env into its own environment at boot (autoload.php -> env());
        // if the child inherited those, its env() "shell wins" guard would
        // keep serving the stale values and .env edits would need a server
        // restart. Real shell exports are untouched and still take priority.
        $env = getenv();
        foreach (dotenv_keys() as $key) {
            unset($env[$key]);
        }

        if ($this->option('silent')) {
            $command .= ' > /dev/null 2>&1 & echo $!';
            $process = proc_open($command, [1 => ['pipe', 'w']], $pipes, null, $env);
            if ($process === false) {
                $this->error('Failed to start development server.');
                @unlink($router);
                return;
            }
            $pid = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            proc_close($process);
            echo "Development server started in background on http://{$host}:{$port}. PID: " . ($pid !== '' ? $pid : 'unknown') . "\n";
            return;
        }

        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, null, $env);
        if ($process === false) {
            $this->error('Failed to start development server.');
            @unlink($router);
            return;
        }
        proc_close($process);
        @unlink($router);
    }
}
