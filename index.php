<?php
session_start();
set_time_limit(300);

// Prefer absolute includes so editor & runtime resolve files reliably
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

// ----------------------
// Load functions.php safely and detect problems
// ----------------------
$functions_path = __DIR__ . '/functions.php';
require __DIR__ . '/vendor/autoload.php';
$functions_ok = false;

if (!file_exists($functions_path)) {
    error_log("functions.php not found at expected path: {$functions_path}");
} else {
    // Optional lint check to catch syntax errors early (best-effort; harmless if exec disabled)
    if (function_exists('exec')) {
        // run php -l to lint the file
        @exec(PHP_BINARY . " -l " . escapeshellarg($functions_path) . " 2>&1", $lintOut, $lintRc);
        if (!empty($lintOut) && $lintRc !== 0) {
            error_log("Syntax error detected in functions.php: " . implode("\n", $lintOut));
        }
    }

    // Try to include; wrap in try/catch to surface fatal errors as logged messages
    try {
        require_once $functions_path;
    } catch (Throwable $e) {
        error_log("Exception while including functions.php: " . $e->getMessage());
    }

    // Verify required functions exist
    $requiredFunctions = ['addCharacter', 'removeCharacter', 'refreshAllCharacters', 'getAllCharacters'];
    $missing = array_filter($requiredFunctions, function ($fn) {
        return !function_exists($fn);
    });

    if (!empty($missing)) {
        error_log('Missing required functions: ' . implode(', ', $missing));
    } else {
        $functions_ok = true;
    }
}

// ----------------------
// Safety: disable display_errors so PHP notices/warnings don't break AJAX JSON responses.
// Errors are still logged.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ----------------------
// Handle AJAX requests (sanitization without deprecated filters)
// ----------------------
// grab raw action value (avoid deprecated FILTER_SANITIZE_STRING)
$action = isset($_POST['action']) && is_scalar($_POST['action']) ? trim((string)$_POST['action']) : null;

if ($action !== null && $action !== '') {
    header('Content-Type: application/json; charset=utf-8');

    // If required functions are not loaded, return a clear JSON error for AJAX
    if (!$functions_ok) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server misconfiguration: required functions missing. Check server logs.'
        ]);
        exit;
    }

    try {
        switch ($action) {
            case 'add_character':
                $url = isset($_POST['url']) && is_scalar($_POST['url']) ? trim((string)$_POST['url']) : '';

                if ($url === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Character URL is required.']);
                    exit;
                }

                // Validate URL
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid URL format.']);
                    exit;
                }

                $result = addCharacter($url);
                echo json_encode($result);
                exit;

            case 'remove_character':
                $id = isset($_POST['id']) ? intval($_POST['id']) : null;
                if ($id === null || $id <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid character ID.']);
                    exit;
                }
                $result = removeCharacter($id);
                echo json_encode($result);
                exit;

            case 'refresh_all':
                $result = refreshAllCharacters();
                echo json_encode($result);
                exit;

            case 'refresh_character':
                $id = intval($_POST['id'] ?? 0);
                $result = refreshCharacter($id);
                echo json_encode($result);
                exit;

            case 'get_characters':
                $characters = getAllCharacters();
                echo json_encode(['success' => true, 'data' => $characters]);
                exit;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Unknown action.']);
                exit;
        }
    } catch (Throwable $e) {
        error_log('AJAX handler exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error. Check logs.']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MU Online Character Tracker</title>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="https://dragon.mu/assets/dragon/images/favicon.ico" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome-text {
            color: #4ecdc4;
            font-weight: 500;
        }

        .header h1 {
            margin: 0;
            font-size: 2.5em;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
        }

        .header .subtitle {
            margin: 10px 0 0 0;
            opacity: 0.8;
            font-size: 1.1em;
        }

        .controls {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .controls-title {
            margin: 0 0 15px 0;
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .add-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: center;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            z-index: 1;
        }

        .add-form input[type="text"] {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .add-form input[type="text"]:focus {
            outline: none;
            border-color: #4ecdc4;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 20px rgba(78, 205, 196, 0.3);
        }

        .add-form input[type="text"]:focus+.input-group i {
            color: #4ecdc4;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover:before {
            left: 100%;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn:disabled:before {
            display: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(78, 205, 196, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(255, 107, 107, 0.4);
        }

        .btn-info {
            background: linear-gradient(45deg, #45b7d1, #96deda);
            color: white;
            box-shadow: 0 4px 15px rgba(69, 183, 209, 0.3);
        }

        .btn-info:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(69, 183, 209, 0.4);
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
            min-width: auto;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
            display: block;
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.1em;
            font-weight: 600;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .table-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-title {
            margin: 0;
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4ecdc4;
        }

        td {
            background: rgba(255, 255, 255, 0.02);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.08);
        }

        .character-name {
            font-weight: 600;
            font-size: 1.1em;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-online {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .status-offline {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .status-unknown {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
            border: 1px solid rgba(241, 196, 15, 0.3);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-online .status-dot {
            background: #2ecc71;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.6);
        }

        .status-offline .status-dot {
            background: #e74c3c;
        }

        .status-unknown .status-dot {
            background: #f1c40f;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .level-badge,
        .reset-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-block;
        }

        .reset-badge {
            background: linear-gradient(45deg, #ff9a9e, #fecfef);
            color: #333;
        }

        .location-text {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin: 20px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .character-preloader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .character-preloader.show {
            display: flex;
        }

        .character-preloader-content {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            max-width: 400px;
            width: 90%;
        }

        .character-preloader-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(78, 205, 196, 0.2);
            border-radius: 50%;
            border-top-color: #4ecdc4;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto 20px;
        }

        .character-preloader-title {
            font-size: 1.5em;
            font-weight: 600;
            margin-bottom: 10px;
            color: #4ecdc4;
        }

        .character-preloader-message {
            font-size: 1em;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .character-preloader-steps {
            text-align: left;
            font-size: 0.9em;
            opacity: 0.7;
        }

        .character-preloader-steps li {
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }

        .character-preloader-steps li:before {
            content: '•';
            position: absolute;
            left: 0;
            color: #4ecdc4;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: #4ecdc4;
            animation: spin 1s ease-in-out infinite;
            margin-bottom: 15px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }

        .toast {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(20px);
            color: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            border-left: 4px solid;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: #2ecc71;
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        .toast.warning {
            border-left-color: #f39c12;
        }

        .toast.info {
            border-left-color: #3498db;
        }

        .toast-header {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toast-icon {
            margin-right: 8px;
        }

        .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .toast-close:hover {
            opacity: 1;
        }

        .toast-body {
            font-size: 14px;
            line-height: 1.4;
            opacity: 0.9;
        }

        /* Loading states */
        .btn.loading {
            position: relative;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .btn.loading .btn-text {
            visibility: hidden;
        }

        /* Character table animations */
        .character-row {
            transition: all 0.3s ease;
        }

        .character-row.removing {
            opacity: 0;
            transform: translateX(-100%);
        }

        .character-row.adding {
            animation: slideInFromBottom 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes slideInFromBottom {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Form submission animation */
        .add-form.submitting {
            opacity: 0.7;
            pointer-events: none;
        }

        .add-form input.success-flash {
            animation: successFlash 0.8s ease;
        }

        @keyframes successFlash {

            0%,
            100% {
                border-color: rgba(255, 255, 255, 0.1);
                box-shadow: none;
            }

            50% {
                border-color: #2ecc71;
                box-shadow: 0 0 20px rgba(46, 204, 113, 0.4);
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            opacity: 0.7;
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .add-form {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .add-form button {
                grid-column: span 1;
                justify-self: center;
                width: fit-content;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .add-form {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .add-form button {
                grid-column: span 1;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .toast-container {
                left: 10px;
                right: 10px;
                max-width: none;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            th,
            td {
                padding: 10px 8px;
                font-size: 0.9em;
            }

            .header h1 {
                font-size: 2em;
            }
        }

        @media (max-width: 480px) {
            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1><i class="fas fa-gamepad"></i> MU Online Character Tracker</h1>
                <div class="user-info">
                    <span class="welcome-text">Welcome, <?= htmlspecialchars($auth->getCurrentUser()['username']) ?>!</span>
                    <a href="logout" class="btn btn-danger btn-small">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            <p class="subtitle">Track your characters' status, level, and location in real-time</p>
        </div>

        <div class="controls">
            <h3 class="controls-title">
                <i class="fas fa-plus-circle"></i>
                Add New Character
            </h3>
            <form id="add-character-form" class="add-form">
                <div class="input-group">
                    <i class="fas fa-link"></i>
                    <input type="text" id="character-url" placeholder="Character Profile URL" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <span class="btn-text">
                        <i class="fas fa-plus"></i>
                        Add Character
                    </span>
                </button>
            </form>
        </div>

        <div class="stats">
            <div class="stat-item">
                <i class="fas fa-sync-alt stat-icon"></i>
                <div class="stat-label">Auto Refresh</div>
                <div class="stat-value">Next: <span id="next-refresh-time">--:--</span></div>
            </div>
            <div class="stat-item">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-label">Last Updated</div>
                <div class="stat-value" id="last-updated">Never</div>
            </div>
            <div class="stat-item">
                <button id="refresh-all" class="btn btn-secondary">
                    <span class="btn-text">
                        <i class="fas fa-refresh"></i>
                        Refresh All
                    </span>
                </button>
                <a href="dashboard" class="btn btn-secondary">
                    <span class="btn-text">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </span>
                </a>
                <?php if ($auth->isAdmin()): ?>
                    <a href="admin" class="btn" style="background-color: yellow; color: black; border-color: yellow;">
                        <span class="btn-text">
                            <i class="fas fa-crown"></i>
                            Admin Panel
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="loading" id="loading">
            <div class="loading-spinner"></div>
            <p><i class="fas fa-download"></i> Updating character data...</p>
        </div>


        <div class="table-container">
            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="table-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-users"></i> Your Characters
                </h3>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Name</th>
                    <th><i class="fas fa-circle"></i> Status</th>
                    <th><i class="fas fa-level-up-alt"></i> Level</th>
                    <th><i class="fas fa-redo-alt"></i> Resets</th>
                    <th><i class="fas fa-crown"></i> G. Resets</th>
                    <th><i class="fas fa-chess-knight"></i> Class</th>
                    <th><i class="fas fa-users"></i> Guild</th>
                    <th><i class="fas fa-users-cog"></i> Gens</th>
                    <th><i class="fas fa-map-marker-alt"></i> Location</th>
                    <th><i class="fas fa-clock"></i> Updated</th>
                    <th><i class="fas fa-cog"></i> Actions</th>
                </tr>
            </thead>
            <tbody id="characters-tbody">
                <!-- Character data will be loaded here -->
            </tbody>
        </table>
    </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Character Preloader -->
    <div class="character-preloader" id="character-preloader">
        <div class="character-preloader-content">
            <div class="character-preloader-spinner"></div>
            <div class="character-preloader-title">Fetching Character Data</div>
            <div class="character-preloader-message">Please wait while we retrieve character information from the website...</div>
            <ul class="character-preloader-steps">
                <li>Connecting to character profile</li>
                <li>Extracting character name</li>
                <li>Reading level and stats</li>
                <li>Getting location and status</li>
                <li>Adding to your tracker</li>
            </ul>
        </div>
    </div>

    <script>
        let autoRefreshInterval;
        let nextRefreshTime;
        let fullCharacterList = [];
        let sortLevelDesc = true;
        let sortResetsDesc = true;

        document.addEventListener('DOMContentLoaded', function() {
            loadCharacters();
            setupAutoRefresh();

            document.getElementById('add-character-form').addEventListener('submit', function(e) {
                e.preventDefault();
                addCharacter();
            });

            document.getElementById('refresh-all').addEventListener('click', function() {
                refreshAllCharacters();
            });

            const header = document.querySelector('.table-header');
            if (header) {
                // Create a container for sort buttons, float right
                const sortContainer = document.createElement('div');
                sortContainer.style.cssText = 'display:flex; gap:10px; margin-left:auto;';

                // Sort by Level button
                const sortLevelBtn = document.createElement('button');
                sortLevelBtn.id = 'sort-level';
                sortLevelBtn.className = 'btn btn-secondary';
                sortLevelBtn.style.minWidth = '170px';
                sortLevelBtn.innerHTML = '<i class="fas fa-sort-amount-down"></i> Sort by Level ↓';
                sortContainer.appendChild(sortLevelBtn);

                // Sort by Resets button
                const sortResetsBtn = document.createElement('button');
                sortResetsBtn.id = 'sort-resets';
                sortResetsBtn.className = 'btn btn-secondary';
                sortResetsBtn.style.minWidth = '170px';
                sortResetsBtn.innerHTML = '<i class="fas fa-sort-amount-down"></i> Sort by Resets ↓';
                sortContainer.appendChild(sortResetsBtn);

                header.appendChild(sortContainer);

                sortLevelBtn.addEventListener('click', () => {
                    sortLevelDesc = !sortLevelDesc;
                    sortResetsDesc = true; // reset resets sort
                    fullCharacterList.sort((a, b) => sortLevelDesc ? b.level - a.level : a.level - b.level);
                    updateSortButtons('level');
                    renderFilteredCharacters();
                });

                sortResetsBtn.addEventListener('click', () => {
                    sortResetsDesc = !sortResetsDesc;
                    sortLevelDesc = true; // reset level sort
                    fullCharacterList.sort((a, b) => sortResetsDesc ? b.resets - a.resets : a.resets - b.resets);
                    updateSortButtons('resets');
                    renderFilteredCharacters();
                });
            }
        });

        function updateSortButtons(activeField) {
            const lvlBtn = document.getElementById('sort-level');
            const rstBtn = document.getElementById('sort-resets');

            if (activeField === 'level') {
                lvlBtn.innerHTML = sortLevelDesc ?
                    '<i class="fas fa-sort-amount-down"></i> Sort by Level ↓' :
                    '<i class="fas fa-sort-amount-up"></i> Sort by Level ↑';
                rstBtn.innerHTML = '<i class="fas fa-sort-amount-down"></i> Sort by Resets ↓';
            } else {
                rstBtn.innerHTML = sortResetsDesc ?
                    '<i class="fas fa-sort-amount-down"></i> Sort by Resets ↓' :
                    '<i class="fas fa-sort-amount-up"></i> Sort by Resets ↑';
                lvlBtn.innerHTML = '<i class="fas fa-sort-amount-down"></i> Sort by Level ↓';
            }
        }

        // Toast notification system (unchanged)
        function showToast(message, type = 'info', title = '', duration = 5000) {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const toastId = Date.now();
            toast.setAttribute('data-toast-id', toastId);

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            const icon = icons[type] || icons.info;
            const titleHtml = title ?
                `<div class="toast-header">
                <span><i class="${icon} toast-icon"></i>${title}</span>
                <button class="toast-close" onclick="closeToast(${toastId})">&times;</button>
            </div>` : '';

            toast.innerHTML = `
            ${titleHtml}
            <div class="toast-body">${message}</div>
        `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            setTimeout(() => {
                closeToast(toastId);
            }, duration);
        }

        function closeToast(toastId) {
            const toast = document.querySelector(`[data-toast-id="${toastId}"]`);
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 400);
            }
        }

        function setButtonLoading(button, loading) {
            if (!button) return;
            if (loading) {
                button.classList.add('loading');
                button.disabled = true;
            } else {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }

        // Robust fetch wrapper to safely parse JSON and surface errors
        async function postAction(action, body = '') {
            try {
                const res = await fetch('index', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=${encodeURIComponent(action)}${body ? '&' + body : ''}`
                });
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch {
                    console.error('Server returned non-JSON:', text);
                    return {
                        success: false,
                        message: 'Server error (non-JSON response)'
                    };
                }
            } catch (err) {
                console.error('Network error in postAction:', err);
                return {
                    success: false,
                    message: 'Network error'
                };
            }
        }

        function loadCharacters() {
            postAction('get_characters')
                .then(data => {
                    if (data && data.success) {
                        fullCharacterList = data.data;
                        renderFilteredCharacters();
                        updateLastUpdatedTime();
                    } else {
                        showToast(data.message || 'Failed to load characters', 'error', 'Error');
                    }
                }).catch(err => {
                    console.error('Error loading characters:', err);
                    showToast('Network error while loading characters', 'error', 'Connection Error');
                });
        }

        function addCharacter() {
            const form = document.getElementById('add-character-form');
            const urlInput = document.getElementById('character-url');
            const submitBtn = form.querySelector('button[type="submit"]');

            const url = urlInput.value.trim();

            if (!url) {
                showToast('Please enter a character profile URL', 'warning', 'Validation Error');
                return;
            }

            form.classList.add('submitting');
            setButtonLoading(submitBtn, true);

            // Show preloader
            showCharacterPreloader();

            const body = `url=${encodeURIComponent(url)}`;
            postAction('add_character', body)
                .then(data => {
                    form.classList.remove('submitting');
                    setButtonLoading(submitBtn, false);
                    hideCharacterPreloader();

                    if (data && data.success) {
                        urlInput.classList.add('success-flash');

                        // Clear input after success
                        setTimeout(() => {
                            urlInput.value = '';
                            urlInput.classList.remove('success-flash');
                        }, 400);

                        // Add the new character to fullCharacterList and render immediately
                        const newChar = data.character; // make sure your addCharacter() PHP returns the newly added character
                        if (newChar) {
                            fullCharacterList.push(newChar);
                            renderFilteredCharacters();
                        } else {
                            loadCharacters(); // fallback if not returned
                        }

                        showToast(`Character "${newChar.name}" added successfully!`, 'success', 'Success', 3000);
                    } else {
                        showToast(data.message || 'Unknown error occurred', 'error', 'Error');
                    }
                })
                .catch(error => {
                    form.classList.remove('submitting');
                    setButtonLoading(submitBtn, false);
                    hideCharacterPreloader();
                    console.error('Error adding character:', error);
                    showToast('Network error while adding character', 'error', 'Connection Error');
                });
        }

        function viewCharacter(url, name) {
            if (!url) {
                showToast('Invalid character URL', 'warning', 'Invalid URL');
                return;
            }
            // encodeURI for safety
            window.open(encodeURI(url), '_blank', 'noopener,noreferrer');
            showToast(`Opening ${name}'s profile...`, 'info', 'Opening Profile', 2000);
        }

        function refreshCharacter(id) {
            const row = document.querySelector(`tr[data-character-id="${id}"]`);
            const characterName = row ? row.querySelector('.character-name').textContent : 'character';
            const refreshBtn = row ? row.querySelector('.btn-warning') : null;

            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            postAction('refresh_character', `id=${encodeURIComponent(id)}`)
                .then(data => {
                    if (data && data.success) {
                        showToast(`Refreshed "${characterName}" successfully`, 'success', 'Character Refreshed', 3000);
                        loadCharacters();
                    } else {
                        showToast(data.message || 'Failed to refresh character', 'error', 'Refresh Failed');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing character:', error);
                    showToast('Network error while refreshing character', 'error', 'Connection Error');
                })
                .finally(() => {
                    if (refreshBtn) {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = '<i class="fas fa-sync"></i>';
                    }
                });
        }

        function removeCharacter(id) {
            const row = document.querySelector(`tr[data-character-id="${id}"]`);
            const characterName = row ? row.querySelector('.character-name').textContent : 'character';

            if (!confirm(`Are you sure you want to remove "${characterName}"?`)) {
                return;
            }

            if (row) {
                row.classList.add('character-row', 'removing');
            }

            postAction('remove_character', `id=${encodeURIComponent(id)}`)
                .then(data => {
                    if (data && data.success) {
                        setTimeout(() => {
                            loadCharacters();
                        }, 300);
                        showToast(`Character "${characterName}" removed successfully`, 'success', 'Character Removed', 3000);
                    } else {
                        if (row) {
                            row.classList.remove('removing');
                        }
                        showToast(data.message || 'Failed to remove character', 'error', 'Error');
                    }
                })
                .catch(error => {
                    if (row) {
                        row.classList.remove('removing');
                    }
                    console.error('Error removing character:', error);
                    showToast('Network error while removing character', 'error', 'Connection Error');
                });
        }

        function refreshAllCharacters() {
            const refreshBtn = document.getElementById('refresh-all');
            const loading = document.getElementById('loading');

            // Show Cloudflare warning
            showToast('⚠️ Warning: Avoid refreshing too frequently to prevent Cloudflare verification checks that may cause 0 or unknown values.', 'warning', 'Cloudflare Protection', 6000);

            loading.style.display = 'block';
            setButtonLoading(refreshBtn, true);

            postAction('refresh_all')
                .then(data => {
                    loading.style.display = 'none';
                    setButtonLoading(refreshBtn, false);

                    if (data && data.success) {
                        loadCharacters();
                        resetAutoRefresh();

                        const message = data.updated > 0 ?
                            `Successfully updated ${data.updated} character${data.updated !== 1 ? 's' : ''}${data.errors > 0 ? ` (${data.errors} error${data.errors !== 1 ? 's' : ''})` : ''}` :
                            'Refresh completed (no updates needed)';

                        const type = data.errors > 0 ? 'warning' : 'success';
                        showToast(message, type, 'Refresh Complete', 4000);
                    } else {
                        showToast(data.message || 'Refresh failed', 'error', 'Refresh Error');
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    setButtonLoading(refreshBtn, false);
                    console.error('Error refreshing characters:', error);
                    showToast('Network error while refreshing characters', 'error', 'Connection Error');
                });
        }

        // Render characters after optional filtering & sorting
        function renderFilteredCharacters() {
            // For now no filters; just render all sorted characters
            displayCharacters(fullCharacterList);
        }

        // Safe DOM-based rendering, avoids JS string injection issues
        function displayCharacters(characters) {
            const tbody = document.getElementById('characters-tbody');
            tbody.innerHTML = '';

            if (!characters.length) {
                tbody.innerHTML = `
        <tr>
            <td colspan="11" class="empty-state">
                <i class="fas fa-user-slash empty-icon"></i>
                No characters found. Add one above!
            </td>
        </tr>
    `;
                return;
            }

            characters.forEach(char => {
                const tr = document.createElement('tr');
                tr.dataset.characterId = char.id;

                // Name - removed hyperlink, made prominent
                const tdName = document.createElement('td');
                const nameSpan = document.createElement('span');
                nameSpan.className = 'character-name';
                nameSpan.textContent = char.name;
                tdName.appendChild(nameSpan);
                tr.appendChild(tdName);

                // Status
                const tdStatus = document.createElement('td');
                const spanStatus = document.createElement('span');
                spanStatus.classList.add('status-indicator');
                if (char.status === 'Online') {
                    spanStatus.classList.add('status-online');
                    spanStatus.innerHTML = '<span class="status-dot"></span> Online';
                } else if (char.status === 'Offline') {
                    spanStatus.classList.add('status-offline');
                    spanStatus.innerHTML = '<span class="status-dot"></span> Offline';
                } else {
                    spanStatus.classList.add('status-unknown');
                    spanStatus.innerHTML = '<span class="status-dot"></span> Unknown';
                }
                tdStatus.appendChild(spanStatus);
                tr.appendChild(tdStatus);

                // Level, Resets with badges
                const tdLevel = document.createElement('td');
                tdLevel.innerHTML = `<span class="level-badge">${char.level ?? '-'}</span>`;
                tr.appendChild(tdLevel);

                const tdResets = document.createElement('td');
                tdResets.innerHTML = `<span class="reset-badge">${char.resets ?? '-'}</span>`;
                tr.appendChild(tdResets);

                // Grand Resets - NOW PROPERLY DISPLAYED
                const tdGrandResets = document.createElement('td');
                tdGrandResets.innerHTML = `<span class="reset-badge">${char.grand_resets ?? '-'}</span>`;
                tr.appendChild(tdGrandResets);

                // Other fields
                ['class', 'guild', 'gens', 'location'].forEach(field => {
                    const td = document.createElement('td');
                    td.textContent = char[field] ?? '-';
                    tr.appendChild(td);
                });

                const tdUpdated = document.createElement('td');
                tdUpdated.textContent = formatDateTime(char.last_updated);
                tr.appendChild(tdUpdated);

                // Actions - cleaner button styling
                const tdActions = document.createElement('td');
                const viewBtn = document.createElement('button');
                viewBtn.className = 'btn btn-primary btn-small';
                viewBtn.innerHTML = '<i class="fas fa-eye"></i>';
                viewBtn.onclick = () => viewCharacter(char.character_url, char.name);

                const refreshBtn = document.createElement('button');
                refreshBtn.className = 'btn btn-warning btn-small';
                refreshBtn.innerHTML = '<i class="fas fa-sync"></i>';
                refreshBtn.onclick = () => refreshCharacter(char.id);

                const removeBtn = document.createElement('button');
                removeBtn.className = 'btn btn-danger btn-small';
                removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                removeBtn.onclick = () => removeCharacter(char.id);

                tdActions.appendChild(viewBtn);
                tdActions.appendChild(refreshBtn);
                tdActions.appendChild(removeBtn);
                tr.appendChild(tdActions);

                tbody.appendChild(tr);
            });
        }

        function setupAutoRefresh() {
            autoRefreshInterval = setInterval(refreshAllCharacters, 3600000);
            updateNextRefreshTime();
            setInterval(updateNextRefreshTime, 12 * 60 * 60 * 1000);
        }

        function resetAutoRefresh() {
            clearInterval(autoRefreshInterval);
            setupAutoRefresh();
        }

        function updateNextRefreshTime() {
            // Add 12 hours (12 * 60 * 60 * 1000 = 43,200,000 ms)
            const twelveHoursMs = 12 * 60 * 60 * 1000;
            const nextRefreshTime = new Date(Date.now() + twelveHoursMs);
            document.getElementById('next-refresh-time').textContent = nextRefreshTime.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function updateLastUpdatedTime() {
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
        }

        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return '-';
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleString([], {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Character preloader functions
        function showCharacterPreloader() {
            const preloader = document.getElementById('character-preloader');
            if (preloader) {
                preloader.classList.add('show');
            }
        }

        function hideCharacterPreloader() {
            const preloader = document.getElementById('character-preloader');
            if (preloader) {
                preloader.classList.remove('show');
            }
        }
    </script>
</body>

</html>