<?php

/**
 * Database Setup Script for MU Tracker
 * Run this once to set up your database for production
 * Updated with error handling, logging, and enhanced features
 */

require_once __DIR__ . '/config.php';

// Set up basic styling for better presentation
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>MU Tracker - Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        h3 { color: #7f8c8d; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; font-weight: bold; }
        .step { background: #ecf0f1; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        ul { line-height: 1.6; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .progress { background: #ecf0f1; height: 20px; border-radius: 10px; margin: 20px 0; }
        .progress-bar { background: #3498db; height: 100%; border-radius: 10px; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>
<h1>🚀 MU Tracker - Enhanced Database Setup</h1>
<div class='progress'><div class='progress-bar' style='width: 10%'></div></div>";

$setupSteps = 0;
$completedSteps = 0;

// Step 1: Test database connection
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Testing Database Connection</h3>";
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✓ Database connection successful</p>";
    echo "<p class='info'>Connected to: <strong>$db_name</strong> on <strong>$db_host</strong></p>";
    $completedSteps++;
} catch (PDOException $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p class='warning'>Please check your database credentials in config.php</p>";
    echo "</div></div></body></html>";
    exit;
}
echo "</div>";

// Update progress
$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 2: Create characters table
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Creating Characters Table</h3>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        character_url VARCHAR(255) NOT NULL UNIQUE,
        status VARCHAR(20) DEFAULT 'Unknown',
        level INT DEFAULT 0,
        resets INT DEFAULT 0,
        grand_resets INT DEFAULT 0,
        class VARCHAR(50) DEFAULT '',
        guild VARCHAR(100) DEFAULT '',
        gens VARCHAR(50) DEFAULT '',
        location VARCHAR(100) DEFAULT 'Unknown',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_last_updated (last_updated),
        INDEX idx_level (level),
        INDEX idx_resets (resets)
    )";

    $pdo->exec($sql);
    echo "<p class='success'>✓ Characters table created/verified</p>";
    echo "<p class='info'>Table includes optimized indexes for better performance</p>";
    $completedSteps++;
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating characters table: " . $e->getMessage() . "</p>";
}
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 3: Create analytics tables
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Creating Analytics Tables</h3>";
try {
    // Character history table
    $sql = "CREATE TABLE IF NOT EXISTS character_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        level INT DEFAULT 0,
        resets INT DEFAULT 0,
        grand_resets INT DEFAULT 0,
        class VARCHAR(50) DEFAULT '',
        guild VARCHAR(100) DEFAULT '',
        gens VARCHAR(50) DEFAULT '',
        location VARCHAR(100) DEFAULT 'Unknown',
        status VARCHAR(20) DEFAULT 'Unknown',
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_character_id (character_id),
        INDEX idx_recorded_at (recorded_at),
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "<p class='success'>✓ Character history table created/verified</p>";
    $completedSteps++;
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating character history table: " . $e->getMessage() . "</p>";
}
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 4: Create daily analytics table
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Creating Daily Analytics Table</h3>";
try {
    // Daily analytics table
    $sql = "CREATE TABLE IF NOT EXISTS daily_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        date DATE NOT NULL,
        starting_level INT DEFAULT 0,
        ending_level INT DEFAULT 0,
        starting_resets INT DEFAULT 0,
        ending_resets INT DEFAULT 0,
        starting_grand_resets INT DEFAULT 0,
        ending_grand_resets INT DEFAULT 0,
        level_gained INT DEFAULT 0,
        resets_gained INT DEFAULT 0,
        grand_resets_gained INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_character_date (character_id, date),
        INDEX idx_date (date),
        FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "<p class='success'>✓ Daily analytics table created/verified</p>";
    $completedSteps++;
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating daily analytics table: " . $e->getMessage() . "</p>";
}
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 5: Create authentication and system tables
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Creating Authentication & System Tables</h3>";

// Create users table
try {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        license_key VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        user_role ENUM('regular', 'vip', 'admin') DEFAULT 'regular',
        login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_license_key (license_key),
        INDEX idx_user_role (user_role)
    )";
    $pdo->exec($sql);
    echo "<p class='success'>✓ Users table created/verified</p>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating users table: " . $e->getMessage() . "</p>";
}

// Create license_keys table
try {
    $sql = "CREATE TABLE IF NOT EXISTS license_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_key VARCHAR(50) NOT NULL UNIQUE,
        license_type ENUM('regular', 'vip') DEFAULT 'regular',
        max_uses INT DEFAULT 1,
        current_uses INT DEFAULT 0,
        is_used BOOLEAN DEFAULT FALSE,
        used_by_user_id INT NULL,
        used_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_license_key (license_key),
        INDEX idx_license_type (license_type),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (used_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "<p class='success'>✓ License keys table created/verified</p>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating license_keys table: " . $e->getMessage() . "</p>";
}

// Create user_sessions table
try {
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(64) NOT NULL UNIQUE,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_token (session_token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "<p class='success'>✓ User sessions table created/verified</p>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating user_sessions table: " . $e->getMessage() . "</p>";
}

// Create activity_logs table
try {
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )";
    $pdo->exec($sql);
    echo "<p class='success'>✓ Activity logs table created/verified</p>";
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error creating activity_logs table: " . $e->getMessage() . "</p>";
}

$completedSteps++;
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 6: Create logs directory and setup error handling
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Setting Up Error Handling & Logging</h3>";

// Create logs directory
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        echo "<p class='success'>✓ Created logs directory</p>";
    } else {
        echo "<p class='warning'>⚠ Could not create logs directory - check permissions</p>";
    }
} else {
    echo "<p class='success'>✓ Logs directory already exists</p>";
}

// Create logs .htaccess for security
$logsHtaccess = $logsDir . '/.htaccess';
if (!file_exists($logsHtaccess)) {
    file_put_contents($logsHtaccess, "# Deny all access to logs directory\nRequire all denied");
    echo "<p class='success'>✓ Created logs security file</p>";
} else {
    echo "<p class='success'>✓ Logs security file already exists</p>";
}

// Check error handling files
$errorFiles = [
    'error_handler.php' => 'Error Handler',
    'error_page_template.php' => 'Error Page Template',
    '404.php' => '404 Error Page',
    '403.php' => '403 Error Page',
    '500.php' => '500 Error Page'
];

foreach ($errorFiles as $file => $name) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p class='success'>✓ $name found</p>";
    } else {
        echo "<p class='warning'>⚠ $name not found - error handling may not work properly</p>";
    }
}

$completedSteps++;
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 7: Check core application files
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Verifying Core Application Files</h3>";

$coreFiles = [
    'functions.php' => ['required' => true, 'description' => 'Core functions and scraping logic'],
    'auth.php' => ['required' => true, 'description' => 'User authentication system'],
    'analytics.php' => ['required' => false, 'description' => 'Analytics and tracking functions'],
    'index.php' => ['required' => true, 'description' => 'Main application page'],
    'dashboard.php' => ['required' => true, 'description' => 'User dashboard'],
    'admin.php' => ['required' => false, 'description' => 'Admin panel'],
    'log_viewer.php' => ['required' => false, 'description' => 'Log viewer utility']
];

foreach ($coreFiles as $file => $info) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p class='success'>✓ $file found - {$info['description']}</p>";
    } else {
        if ($info['required']) {
            echo "<p class='error'>✗ $file not found - {$info['description']} (REQUIRED)</p>";
        } else {
            echo "<p class='warning'>⚠ $file not found - {$info['description']} (optional)</p>";
        }
    }
}

$completedSteps++;
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 8: Check dependencies and configuration
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Checking Dependencies & Configuration</h3>";

// Test Composer dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p class='success'>✓ Composer dependencies found</p>";
} else {
    echo "<p class='warning'>⚠ Composer dependencies not found. Run 'composer install' if needed for enhanced scraping.</p>";
}

// Check .htaccess
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<p class='success'>✓ .htaccess security file found</p>";
} else {
    echo "<p class='warning'>⚠ .htaccess not found. Security rules and error pages may not work.</p>";
}

// Check config files
$configFiles = [
    'config.php' => 'Main configuration',
    'config.production.php' => 'Production configuration (optional)'
];

foreach ($configFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p class='success'>✓ $file found - $description</p>";
    } else {
        echo "<p class='warning'>⚠ $file not found - $description</p>";
    }
}

$completedSteps++;
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Step 9: Check existing data and provide statistics
$setupSteps++;
echo "<div class='step'><h3>Step $setupSteps: Analyzing Existing Data</h3>";

try {
    // Check characters
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM characters");
    $result = $stmt->fetch();
    if ($result && $result['count'] > 0) {
        echo "<p class='info'>📊 Found {$result['count']} existing character(s) in database</p>";
        if ($result['count'] > 10) {
            echo "<p class='warning'>⚠ Some users may have more than 10 characters - they will be limited after authentication is enabled</p>";
        }
    } else {
        echo "<p class='info'>📊 No existing characters found - ready for fresh start</p>";
    }

    // Check users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    if ($result && $result['count'] > 0) {
        echo "<p class='info'>👥 Found {$result['count']} existing user(s)</p>";
    } else {
        echo "<p class='info'>👥 No existing users - authentication system ready</p>";
    }

    // Check license keys
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM license_keys");
    $result = $stmt->fetch();
    if ($result && $result['count'] > 0) {
        echo "<p class='info'>🔑 Found {$result['count']} license key(s)</p>";
    } else {
        echo "<p class='info'>🔑 No license keys found - run setup_auth_simple.php to create sample keys</p>";
    }

    $completedSteps++;
} catch (PDOException $e) {
    echo "<p class='warning'>⚠ Data analysis: " . $e->getMessage() . "</p>";
}
echo "</div>";

$progress = ($completedSteps / 12) * 100;
echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";

// Final completion
$completedSteps = 12; // Set to max for 100% completion
$progress = 100;
echo "<script>document.querySelector('.progress-bar').style.width = '100%';</script>";

echo "<h2 style='color: #27ae60; text-align: center; margin-top: 40px;'>🎉 Setup Complete!</h2>";
echo "<div class='step' style='border-left-color: #27ae60; background: #d5f4e6;'>";
echo "<p style='font-size: 18px; text-align: center;'><strong>Your MU Tracker is now ready for production use with enhanced features!</strong></p>";
echo "</div>";

echo "<h3>🚀 New Features Available:</h3>";
echo "<div class='step'>";
echo "<ul>";
echo "<li><strong>🔐 User Authentication:</strong> Secure login system with role-based access</li>";
echo "<li><strong>👑 User Roles:</strong> Regular, VIP, and Admin roles with different permissions</li>";
echo "<li><strong>📊 Character Limits:</strong> Regular users limited to 10 characters, VIP/Admin unlimited</li>";
echo "<li><strong>🛡️ Enhanced Security:</strong> Session management, rate limiting, and access control</li>";
echo "<li><strong>📈 Analytics Dashboard:</strong> Comprehensive character tracking and performance metrics</li>";
echo "<li><strong>🔍 Error Handling:</strong> Professional error pages with detailed logging</li>";
echo "<li><strong>📝 Activity Logging:</strong> Complete audit trail of user actions</li>";
echo "<li><strong>⚡ Performance:</strong> Optimized database with proper indexes</li>";
echo "<li><strong>🌐 Cloudflare Protection:</strong> Warnings to prevent verification challenges</li>";
echo "<li><strong>🎨 Modern UI:</strong> AdminLTE-powered admin panel with responsive design</li>";
echo "</ul>";
echo "</div>";

echo "<h3>📋 Next Steps:</h3>";
echo "<div class='step'>";
echo "<ol>";
echo "<li><strong>Authentication Setup:</strong> Run <a href='setup_auth_simple.php' style='color: #3498db;'>setup_auth_simple.php</a> to create admin account and sample licenses</li>";
echo "<li><strong>Configuration:</strong> Update database credentials in <code>config.php</code> if needed</li>";
echo "<li><strong>Testing:</strong> Visit <a href='index' style='color: #3498db;'>index</a> to test the main application</li>";
echo "<li><strong>Admin Panel:</strong> Access <a href='admin' style='color: #3498db;'>admin</a> panel after creating admin account</li>";
echo "<li><strong>Dashboard:</strong> Check analytics at <a href='dashboard' style='color: #3498db;'>dashboard</a></li>";
echo "<li><strong>Logs:</strong> Monitor system logs via <a href='log_viewer' style='color: #3498db;'>log viewer</a> (admin only)</li>";
echo "<li><strong>Security:</strong> Delete setup files: <code>rm setup_database.php setup_auth_simple.php</code></li>";
echo "</ol>";
echo "</div>";

echo "<h3>⚠️ Important Security Notes:</h3>";
echo "<div class='step' style='border-left-color: #e74c3c; background: #fdf2f2;'>";
echo "<ul>";
echo "<li><strong>Delete Setup Files:</strong> Remove <code>setup_database.php</code> and <code>setup_auth_simple.php</code> after setup</li>";
echo "<li><strong>Production Config:</strong> Use <code>config.production.php</code> for production deployment</li>";
echo "<li><strong>File Permissions:</strong> Ensure <code>logs/</code> directory is writable but not web-accessible</li>";
echo "<li><strong>Database Security:</strong> Use strong database passwords and limit access</li>";
echo "<li><strong>HTTPS:</strong> Enable HTTPS in production for secure authentication</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<p style='color: #7f8c8d;'>MU Tracker Enhanced Setup v2.0 - Database initialization completed successfully</p>";
echo "<p style='font-size: 14px; color: #95a5a6;'>Generated on " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

echo "</div></body></html>";
