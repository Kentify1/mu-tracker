<?php

/**
 * Error Page Template for MU Tracker
 * Displays user-friendly error pages with optional debugging info
 */

// Get error details
$errorCode = $code ?? 500;
$errorTitle = $title ?? 'An Error Occurred';
$errorMessage = $message ?? 'Something went wrong. Please try again later.';
$showDebugInfo = !($this->isProduction ?? true) && isset($_GET['debug']);

// Generate a unique error ID for tracking
$errorId = 'ERR-' . date('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6);

// Log the error ID for reference
logInfo("Error page displayed with ID: $errorId", [
    'error_code' => $errorCode,
    'error_title' => $errorTitle,
    'error_id' => $errorId
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($errorTitle) ?> - MU Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="https://dragon.mu/assets/dragon/images/favicon.ico" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .error-404 .error-icon {
            color: #ff6b6b;
        }

        .error-403 .error-icon {
            color: #ffa500;
        }

        .error-500 .error-icon {
            color: #ff4757;
        }

        .error-default .error-icon {
            color: #4ecdc4;
        }

        .error-code {
            font-size: 6rem;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .error-title {
            font-size: 2rem;
            margin-bottom: 20px;
            color: #e0e0e0;
        }

        .error-message {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: #b0b0b0;
            line-height: 1.6;
        }

        .error-id {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 30px;
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            min-width: 140px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(78, 205, 196, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: #4ecdc4;
            border: 2px solid #4ecdc4;
        }

        .btn-outline:hover {
            background: #4ecdc4;
            color: #1a1a2e;
        }

        .debug-info {
            text-align: left;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .debug-info h4 {
            color: #ff6b6b;
            margin-bottom: 10px;
        }

        .debug-info pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #e0e0e0;
        }

        .suggestions {
            text-align: left;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #4ecdc4;
        }

        .suggestions h4 {
            color: #4ecdc4;
            margin-bottom: 15px;
        }

        .suggestions ul {
            list-style: none;
            padding: 0;
        }

        .suggestions li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .suggestions li:last-child {
            border-bottom: none;
        }

        .suggestions li i {
            color: #4ecdc4;
            margin-right: 10px;
            width: 20px;
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .error-code {
                font-size: 4rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>

<body>
    <div class="error-container error-<?= $errorCode ?>">
        <div class="error-icon">
            <?php
            switch ($errorCode) {
                case 404:
                    echo '<i class="fas fa-search"></i>';
                    break;
                case 403:
                    echo '<i class="fas fa-lock"></i>';
                    break;
                case 500:
                    echo '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                default:
                    echo '<i class="fas fa-bug"></i>';
            }
            ?>
        </div>

        <div class="error-code"><?= $errorCode ?></div>
        <h1 class="error-title"><?= htmlspecialchars($errorTitle) ?></h1>
        <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>

        <div class="error-id">
            <strong>Error ID:</strong> <?= $errorId ?>
            <br><small>Please reference this ID when contacting support</small>
        </div>

        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
            <a href="index" class="btn btn-primary">
                <i class="fas fa-home"></i> Home
            </a>
            <?php if ($errorCode === 404): ?>
                <a href="dashboard" class="btn btn-outline">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            <?php elseif ($errorCode === 403): ?>
                <a href="login" class="btn btn-outline">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>

        <?php if ($errorCode === 404): ?>
            <div class="suggestions">
                <h4><i class="fas fa-lightbulb"></i> Suggestions</h4>
                <ul>
                    <li><i class="fas fa-check"></i> Check the URL for typos</li>
                    <li><i class="fas fa-check"></i> Use the navigation menu to find what you're looking for</li>
                    <li><i class="fas fa-check"></i> Go back to the <a href="index" style="color: #4ecdc4;">homepage</a></li>
                    <li><i class="fas fa-check"></i> Try searching for content in the <a href="dashboard" style="color: #4ecdc4;">dashboard</a></li>
                </ul>
            </div>
        <?php elseif ($errorCode === 403): ?>
            <div class="suggestions">
                <h4><i class="fas fa-info-circle"></i> Access Information</h4>
                <ul>
                    <li><i class="fas fa-user"></i> You may need to log in to access this page</li>
                    <li><i class="fas fa-crown"></i> This page may require VIP or admin privileges</li>
                    <li><i class="fas fa-key"></i> Check if your account has the necessary permissions</li>
                    <li><i class="fas fa-envelope"></i> Contact an administrator if you believe this is an error</li>
                </ul>
            </div>
        <?php elseif ($errorCode === 500): ?>
            <div class="suggestions">
                <h4><i class="fas fa-tools"></i> What you can do</h4>
                <ul>
                    <li><i class="fas fa-refresh"></i> Refresh the page and try again</li>
                    <li><i class="fas fa-clock"></i> Wait a few minutes and try again</li>
                    <li><i class="fas fa-bug"></i> Report this error using the Error ID above</li>
                    <li><i class="fas fa-home"></i> Return to the homepage and try a different action</li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($showDebugInfo): ?>
            <div class="debug-info">
                <h4><i class="fas fa-code"></i> Debug Information (Development Mode)</h4>
                <pre><?php
                        echo "Error Code: $errorCode\n";
                        echo "Error Title: $errorTitle\n";
                        echo "Error Message: $errorMessage\n";
                        echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
                        echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
                        echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
                        echo "Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A') . "\n";
                        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
                        if (isset($_SESSION['user_id'])) {
                            echo "User ID: " . $_SESSION['user_id'] . "\n";
                        }
                        ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh for 500 errors (in case it's a temporary issue)
        <?php if ($errorCode === 500): ?>
            let refreshCount = 0;
            const maxRefreshes = 3;

            function autoRefresh() {
                if (refreshCount < maxRefreshes) {
                    refreshCount++;
                    setTimeout(() => {
                        window.location.reload();
                    }, 30000); // Refresh after 30 seconds
                }
            }

            // Start auto-refresh timer
            autoRefresh();
        <?php endif; ?>

        // Log client-side error information
        console.log('MU Tracker Error Page', {
            errorCode: <?= $errorCode ?>,
            errorId: '<?= $errorId ?>',
            timestamp: '<?= date('c') ?>',
            userAgent: navigator.userAgent,
            url: window.location.href
        });
    </script>
</body>

</html>