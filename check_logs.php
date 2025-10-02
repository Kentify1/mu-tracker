<?php
// Script to check PHP error logs
echo "PHP Error Log Check\n";
echo "==================\n\n";

// Get the error log file path
$error_log = ini_get('error_log');
if (empty($error_log)) {
    $error_log = 'php_errors.log'; // Default fallback
}

echo "Error log file: " . $error_log . "\n\n";

// Check if log file exists
if (file_exists($error_log)) {
    echo "Log file exists. Last 20 lines:\n";
    echo "--------------------------------\n";
    $lines = file($error_log);
    $last_lines = array_slice($lines, -20);
    foreach ($last_lines as $line) {
        echo $line;
    }
} else {
    echo "Log file does not exist. Checking common locations:\n";

    $common_paths = [
        'C:\xampp\apache\logs\error.log',
        'C:\xampp\php\logs\php_error_log',
        'C:\xampp\logs\error.log',
        'php_errors.log',
        'error.log'
    ];

    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            echo "Found log at: $path\n";
            echo "Last 10 lines:\n";
            echo "--------------------------------\n";
            $lines = file($path);
            $last_lines = array_slice($lines, -10);
            foreach ($last_lines as $line) {
                echo $line;
            }
            break;
        }
    }
}

echo "\n\nTo test the login/logout pages:\n";
echo "1. Open your browser and go to: http://localhost/mu-tracker/login.php\n";
echo "2. Try to access: http://localhost/mu-tracker/logout.php\n";
echo "3. Check the error logs above for debug messages\n";
echo "4. Look for lines starting with 'LOGIN DEBUG:', 'LOGOUT DEBUG:', 'AUTH DEBUG:', or 'CONFIG DEBUG:'\n";
