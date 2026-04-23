<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Tester</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f4f7f6; }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="password"], input[type="email"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        button:hover { background-color: #2980b9; }
        button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; display: none; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .logs-container { margin-top: 20px; display: none; }
        .logs { background-color: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; font-family: "Courier New", Courier, monospace; overflow-x: auto; max-height: 400px; overflow-y: auto; }
        .log-entry { margin-bottom: 5px; border-bottom: 1px solid #34495e; padding-bottom: 5px; font-size: 13px; }
        .log-time { color: #95a5a6; font-size: 0.8em; margin-right: 10px; }
        .row { display: flex; gap: 20px; }
        .row > div { flex: 1; }
        .spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-right: 8px; display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1>SMTP Tester</h1>

    <div id="alert-success" class="alert alert-success">Email sent successfully!</div>
    <div id="alert-error" class="alert alert-error"></div>

    <div class="card">
        <form id="smtp-form">
            <?php echo function_exists('csrf_field') ? csrf_field() : ''; ?>
            <div class="row">
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="host" value="127.0.0.1" required>
                </div>
                <div class="form-group" style="flex: 0 0 100px;">
                    <label>Port</label>
                    <input type="number" name="port" value="2525" required>
                </div>
                <div class="form-group">
                    <label>Encryption</label>
                    <select name="encryption">
                        <option value="">None</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" value="">
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="from_name" value="SMTP Tester">
                </div>
                <div class="form-group">
                    <label>From Address</label>
                    <input type="email" name="from_address" value="tester@localhost">
                </div>
            </div>

            <div class="form-group">
                <label>To Address</label>
                <input type="email" name="to" value="" placeholder="recipient@example.com" required>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" value="SMTP Test Connection">
            </div>

            <div class="form-group">
                <label>Body (HTML)</label>
                <textarea name="body" rows="5"><p>Hello!</p><p>If you are reading this, your SMTP settings are working correctly.</p></textarea>
            </div>

            <button type="submit" id="submit-btn">
                <span id="btn-spinner" class="spinner"></span>
                <span id="btn-text">Test Connection & Send Email</span>
            </button>
        </form>
    </div>

    <div id="logs-container" class="logs-container">
        <h2>Debug Logs</h2>
        <div id="logs-output" class="logs"></div>
    </div>

    <script>
        document.getElementById('smtp-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const spinner = document.getElementById('btn-spinner');
            const successAlert = document.getElementById('alert-success');
            const errorAlert = document.getElementById('alert-error');
            const logsContainer = document.getElementById('logs-container');
            const logsOutput = document.getElementById('logs-output');

            // Reset UI
            successAlert.style.display = 'none';
            errorAlert.style.display = 'none';
            btn.disabled = true;
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Testing...';

            try {
                const formData = new FormData(form);
                const response = await fetch('/smtp-tester', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                // Handle Results
                if (result.success) {
                    successAlert.style.display = 'block';
                } else {
                    errorAlert.textContent = 'Failed to send email: ' + (result.error || 'Unknown error');
                    errorAlert.style.display = 'block';
                }

                // Update Logs
                if (result.logs && result.logs.length > 0) {
                    logsOutput.innerHTML = result.logs.map(log => `
                        <div class="log-entry">
                            <span class="log-time">[${log.timestamp}]</span>
                            <span class="log-msg">${escapeHtml(log.message)}</span>
                        </div>
                    `).join('');
                    logsContainer.style.display = 'block';
                } else {
                    logsContainer.style.display = 'none';
                }

            } catch (error) {
                console.error('Error:', error);
                errorAlert.textContent = 'An error occurred: ' + error.message;
                errorAlert.style.display = 'block';
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
                btnText.textContent = 'Test Connection & Send Email';
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
    </script>
</body>
</html>
