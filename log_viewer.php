<?php

/**
 * Log Viewer Utility for MU Tracker
 * Provides easy access to error logs for troubleshooting
 */

require_once __DIR__ . '/auth.php';

// Check if user is admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied. Admin privileges required.');
}

// Get log type from query parameter
$logType = $_GET['type'] ?? 'php';
$lines = (int)($_GET['lines'] ?? 100);
$lines = max(10, min(1000, $lines)); // Limit between 10 and 1000 lines

$logFiles = [
    'php' => [
        'name' => 'PHP Error Log',
        'path' => ini_get('error_log') ?: '/tmp/php_errors.log'
    ],
    'custom' => [
        'name' => 'MU Tracker Custom Log',
        'path' => __DIR__ . '/logs/mu_tracker.log'
    ],
    'apache' => [
        'name' => 'Apache Error Log',
        'path' => '/var/log/apache2/error.log'
    ]
];

function tailFile($filename, $lines = 100)
{
    if (!file_exists($filename) || !is_readable($filename)) {
        return "Log file not found or not readable: $filename";
    }

    $file = new SplFileObject($filename, 'r');
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();

    $startLine = max(0, $totalLines - $lines);
    $file->seek($startLine);

    $content = '';
    while (!$file->eof()) {
        $content .= $file->fgets();
    }

    return $content;
}

function formatLogLine($line)
{
    // Color code different log levels
    $line = htmlspecialchars($line);

    if (strpos($line, '[ERROR]') !== false) {
        return '<span class="log-error">' . $line . '</span>';
    } elseif (strpos($line, '[WARNING]') !== false) {
        return '<span class="log-warning">' . $line . '</span>';
    } elseif (strpos($line, '[INFO]') !== false) {
        return '<span class="log-info">' . $line . '</span>';
    } elseif (strpos($line, '[DEBUG]') !== false) {
        return '<span class="log-debug">' . $line . '</span>';
    } elseif (strpos($line, '[AUTH_FAILURE]') !== false) {
        return '<span class="log-auth-failure">' . $line . '</span>';
    } elseif (strpos($line, '[DATABASE_ERROR]') !== false) {
        return '<span class="log-database-error">' . $line . '</span>';
    } elseif (strpos($line, '[SCRAPING_ERROR]') !== false) {
        return '<span class="log-scraping-error">' . $line . '</span>';
    }

    return $line;
}

// Handle AJAX requests for real-time log updates
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    $selectedLog = $logFiles[$logType] ?? $logFiles['php'];
    $content = tailFile($selectedLog['path'], $lines);

    echo json_encode([
        'success' => true,
        'content' => $content,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$selectedLog = $logFiles[$logType] ?? $logFiles['php'];
$logContent = tailFile($selectedLog['path'], $lines);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MU Tracker - Log Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #e0e0e0;
            line-height: 1.4;
        }

        .header {
            background: #2d2d2d;
            padding: 1rem;
            border-bottom: 2px solid #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            color: #4ecdc4;
            font-size: 1.5rem;
        }

        .controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .controls select,
        .controls input,
        .controls button {
            padding: 0.5rem;
            background: #3d3d3d;
            color: #e0e0e0;
            border: 1px solid #555;
            border-radius: 4px;
        }

        .controls button {
            background: #4ecdc4;
            color: #1a1a1a;
            cursor: pointer;
            font-weight: bold;
        }

        .controls button:hover {
            background: #45b7d1;
        }

        .log-container {
            height: calc(100vh - 120px);
            overflow-y: auto;
            padding: 1rem;
            background: #0d1117;
        }

        .log-content {
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .log-error {
            color: #ff6b6b;
            font-weight: bold;
        }

        .log-warning {
            color: #ffa500;
        }

        .log-info {
            color: #4ecdc4;
        }

        .log-debug {
            color: #888;
        }

        .log-auth-failure {
            color: #ff4757;
            background: rgba(255, 71, 87, 0.1);
        }

        .log-database-error {
            color: #ff3838;
            background: rgba(255, 56, 56, 0.1);
        }

        .log-scraping-error {
            color: #ff6348;
            background: rgba(255, 99, 72, 0.1);
        }

        .status {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #2d2d2d;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: 1px solid #555;
            font-size: 0.8rem;
        }

        .auto-refresh {
            color: #4ecdc4;
        }

        .back-link {
            color: #4ecdc4;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid #4ecdc4;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #4ecdc4;
            color: #1a1a1a;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .controls {
                justify-content: center;
            }

            .log-container {
                height: calc(100vh - 160px);
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🔍 MU Tracker - Log Viewer</h1>
        <div class="controls">
            <select id="logType" onchange="changeLogType()">
                <?php foreach ($logFiles as $key => $log): ?>
                    <option value="<?= $key ?>" <?= $key === $logType ? 'selected' : '' ?>>
                        <?= htmlspecialchars($log['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="number" id="lines" value="<?= $lines ?>" min="10" max="1000" placeholder="Lines">

            <button onclick="refreshLogs()">🔄 Refresh</button>
            <button onclick="toggleAutoRefresh()" id="autoRefreshBtn">▶️ Auto Refresh</button>
            <button onclick="clearLogs()">🗑️ Clear View</button>

            <a href="admin" class="back-link">← Back to Admin</a>
        </div>
    </div>

    <div class="status" id="status">
        Last updated: <?= date('Y-m-d H:i:s') ?>
    </div>

    <div class="log-container">
        <div class="log-content" id="logContent">
            <?php
            $lines = explode("\n", $logContent);
            foreach ($lines as $line) {
                if (trim($line)) {
                    echo formatLogLine($line) . "\n";
                }
            }
            ?>
        </div>
    </div>

    <script>
        let autoRefreshInterval = null;
        let isAutoRefreshing = false;

        function changeLogType() {
            const logType = document.getElementById('logType').value;
            const lines = document.getElementById('lines').value;
            window.location.href = `?type=${logType}&lines=${lines}`;
        }

        function refreshLogs() {
            const logType = document.getElementById('logType').value;
            const lines = document.getElementById('lines').value;

            fetch(`?type=${logType}&lines=${lines}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const logContent = document.getElementById('logContent');
                        const lines = data.content.split('\n');
                        let formattedContent = '';

                        lines.forEach(line => {
                            if (line.trim()) {
                                formattedContent += formatLogLine(line) + '\n';
                            }
                        });

                        logContent.innerHTML = formattedContent;
                        document.getElementById('status').textContent = `Last updated: ${data.timestamp}`;

                        // Auto-scroll to bottom
                        const container = document.querySelector('.log-container');
                        container.scrollTop = container.scrollHeight;
                    }
                })
                .catch(error => {
                    console.error('Error refreshing logs:', error);
                    document.getElementById('status').textContent = 'Error refreshing logs';
                });
        }

        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');

            if (isAutoRefreshing) {
                clearInterval(autoRefreshInterval);
                isAutoRefreshing = false;
                btn.textContent = '▶️ Auto Refresh';
                btn.style.background = '#4ecdc4';
            } else {
                autoRefreshInterval = setInterval(refreshLogs, 5000); // Refresh every 5 seconds
                isAutoRefreshing = true;
                btn.textContent = '⏸️ Stop Auto';
                btn.style.background = '#ff6b6b';
            }
        }

        function clearLogs() {
            document.getElementById('logContent').innerHTML = '<span style="color: #888;">Log view cleared. Click refresh to reload.</span>';
        }

        function formatLogLine(line) {
            // Escape HTML
            line = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            // Color code different log levels
            if (line.includes('[ERROR]')) {
                return `<span class="log-error">${line}</span>`;
            } else if (line.includes('[WARNING]')) {
                return `<span class="log-warning">${line}</span>`;
            } else if (line.includes('[INFO]')) {
                return `<span class="log-info">${line}</span>`;
            } else if (line.includes('[DEBUG]')) {
                return `<span class="log-debug">${line}</span>`;
            } else if (line.includes('[AUTH_FAILURE]')) {
                return `<span class="log-auth-failure">${line}</span>`;
            } else if (line.includes('[DATABASE_ERROR]')) {
                return `<span class="log-database-error">${line}</span>`;
            } else if (line.includes('[SCRAPING_ERROR]')) {
                return `<span class="log-scraping-error">${line}</span>`;
            }

            return line;
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshLogs();
                        break;
                    case ' ':
                        e.preventDefault();
                        toggleAutoRefresh();
                        break;
                }
            }
        });
    </script>
</body>

</html>
