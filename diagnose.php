<?php

/**
 * Production Diagnostic Script
 * Helps identify HTTP 500 errors on production
 */

// Enable error reporting for diagnosis
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html><html><head><title>MU Tracker - Production Diagnostics</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:20px auto;padding:20px;background:#f5f5f5;}";
echo ".step{background:white;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #3498db;}";
echo ".success{color:#27ae60;font-weight:bold;} .error{color:#e74c3c;font-weight:bold;} .warning{color:#f39c12;font-weight:bold;}";
echo "</style></head><body>";

echo "<h1>🔍 MU Tracker Production Diagnostics</h1>";

$step = 1;

// Step 1: Basic PHP functionality
echo "<div class='step'><h3>Step $step: Basic PHP Test</h3>";
echo "<p class='success'>✓ PHP is working - version " . PHP_VERSION . "</p>";
echo "</div>";
$step++;

// Step 2: Check if config.php exists and can be loaded
echo "<div class='step'><h3>Step $step: Configuration File Test</h3>";
try {
    if (file_exists(__DIR__ . '/config.php')) {
        echo "<p class='success'>✓ config.php exists</p>";

        // Try to include it
        ob_start();
        require_once __DIR__ . '/config.php';
        $output = ob_get_clean();

        echo "<p class='success'>✓ config.php loaded successfully</p>";

        // Check if database variables are set
        if (isset($db_host, $db_name, $db_user, $db_pass)) {
            echo "<p class='success'>✓ Database variables are set</p>";
            echo "<p>Host: <strong>$db_host</strong></p>";
            echo "<p>Database: <strong>$db_name</strong></p>";
            echo "<p>User: <strong>$db_user</strong></p>";
        } else {
            echo "<p class='error'>✗ Database variables not found</p>";
        }
    } else {
        echo "<p class='error'>✗ config.php not found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading config.php: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p class='error'>✗ Fatal error in config.php: " . $e->getMessage() . "</p>";
}
echo "</div>";
$step++;

// Step 3: Database connection test
echo "<div class='step'><h3>Step $step: Database Connection Test</h3>";
if (isset($db_host, $db_name, $db_user, $db_pass)) {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p class='success'>✓ Database connection successful</p>";

        // Test a simple query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM characters");
        $result = $stmt->fetch();
        echo "<p class='success'>✓ Database query successful - found {$result['count']} characters</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
        echo "<p class='warning'>Check your database credentials in config.php</p>";
    }
} else {
    echo "<p class='warning'>⚠ Skipping database test - variables not available</p>";
}
echo "</div>";
$step++;

// Step 4: Check required files
echo "<div class='step'><h3>Step $step: Required Files Check</h3>";
$requiredFiles = [
    'functions.php' => 'Core functions',
    'auth.php' => 'Authentication system',
    'index.php' => 'Main page',
    'dashboard.php' => 'Dashboard',
    'error_handler.php' => 'Error handler'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p class='success'>✓ $file found ($description)</p>";

        // Try to check syntax
        $output = shell_exec("php -l $file 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "<p class='success'>  ✓ Syntax OK</p>";
        } else {
            echo "<p class='error'>  ✗ Syntax error: $output</p>";
        }
    } else {
        echo "<p class='error'>✗ $file missing ($description)</p>";
    }
}
echo "</div>";
$step++;

// Step 5: Check directory permissions
echo "<div class='step'><h3>Step $step: Directory Permissions</h3>";
$directories = [
    '.' => 'Root directory',
    './logs' => 'Logs directory'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        echo "<p class='success'>✓ $description exists</p>";
        if (is_writable($dir)) {
            echo "<p class='success'>  ✓ Writable</p>";
        } else {
            echo "<p class='warning'>  ⚠ Not writable</p>";
        }
    } else {
        if ($dir === './logs') {
            echo "<p class='warning'>⚠ $description missing - creating...</p>";
            if (mkdir($dir, 0755, true)) {
                echo "<p class='success'>  ✓ Created successfully</p>";
            } else {
                echo "<p class='error'>  ✗ Failed to create</p>";
            }
        } else {
            echo "<p class='error'>✗ $description missing</p>";
        }
    }
}
echo "</div>";
$step++;

// Step 6: Check .htaccess
echo "<div class='step'><h3>Step $step: .htaccess Check</h3>";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<p class='success'>✓ .htaccess exists</p>";
    $htaccess = file_get_contents(__DIR__ . '/.htaccess');
    if (strpos($htaccess, 'ErrorDocument') !== false) {
        echo "<p class='success'>✓ Error documents configured</p>";
    } else {
        echo "<p class='warning'>⚠ Error documents not configured</p>";
    }
} else {
    echo "<p class='warning'>⚠ .htaccess missing</p>";
}
echo "</div>";
$step++;

// Step 7: Test error handler
echo "<div class='step'><h3>Step $step: Error Handler Test</h3>";
try {
    if (file_exists(__DIR__ . '/error_handler.php')) {
        echo "<p class='success'>✓ Error handler file exists</p>";

        // Try to include it
        ob_start();
        require_once __DIR__ . '/error_handler.php';
        $output = ob_get_clean();

        echo "<p class='success'>✓ Error handler loaded</p>";

        if (class_exists('ErrorHandler')) {
            echo "<p class='success'>✓ ErrorHandler class available</p>";
        } else {
            echo "<p class='error'>✗ ErrorHandler class not found</p>";
        }
    } else {
        echo "<p class='error'>✗ Error handler file missing</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading error handler: " . $e->getMessage() . "</p>";
}
echo "</div>";
$step++;

// Step 8: Check PHP extensions
echo "<div class='step'><h3>Step $step: PHP Extensions Check</h3>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='success'>✓ $ext extension loaded</p>";
    } else {
        echo "<p class='error'>✗ $ext extension missing</p>";
    }
}
echo "</div>";
$step++;

// Step 9: Memory and limits
echo "<div class='step'><h3>Step $step: PHP Configuration</h3>";
echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "s</p>";
echo "<p><strong>Error Reporting:</strong> " . error_reporting() . "</p>";
echo "<p><strong>Display Errors:</strong> " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";
echo "<p><strong>Log Errors:</strong> " . (ini_get('log_errors') ? 'On' : 'Off') . "</p>";
echo "<p><strong>Error Log:</strong> " . ini_get('error_log') . "</p>";
echo "</div>";

// Final recommendations
echo "<div class='step' style='border-left-color:#e74c3c;background:#fdf2f2;'>";
echo "<h3>🔧 Troubleshooting Recommendations:</h3>";
echo "<ol>";
echo "<li><strong>Check Error Logs:</strong> Look at your hosting provider's error logs</li>";
echo "<li><strong>Database Credentials:</strong> Verify your production database settings in config.php</li>";
echo "<li><strong>File Permissions:</strong> Ensure all files are readable (644) and directories executable (755)</li>";
echo "<li><strong>PHP Version:</strong> Make sure your hosting supports PHP 7.4+ with required extensions</li>";
echo "<li><strong>Memory Limits:</strong> Check if your hosting has sufficient memory allocation</li>";
echo "<li><strong>Missing Files:</strong> Upload any missing files shown above</li>";
echo "</ol>";
echo "</div>";

echo "<div style='text-align:center;margin-top:30px;'>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<p>1. Fix any issues shown above</p>";
echo "<p>2. Check your hosting error logs</p>";
echo "<p>3. Try accessing <a href='index.php'>index.php</a> directly</p>";
echo "<p>4. Delete this diagnostic file when done</p>";
echo "</div>";

echo "</body></html>";
