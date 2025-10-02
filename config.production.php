<?php

/**
 * Production Configuration for MU Tracker
 * Copy this to config.php and update with your production settings
 */

// Database Configuration
$db_host = 'sql105.infinityfree.com'; // Production database host
$db_name = 'if0_40047672_mu_tracker'; // Production database name
$db_user = 'if0_40047672'; // Production database username
$db_pass = 'ycezK2Y46sKn'; // Production database password

// Environment Detection
$is_production = !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);


// Performance Settings
if (function_exists('opcache_get_status')) {
    ini_set('opcache.enable', '1');
    ini_set('opcache.memory_consumption', '128');
    ini_set('opcache.max_accelerated_files', '4000');
    ini_set('opcache.validate_timestamps', $is_production ? '0' : '1');
}

// Timezone
date_default_timezone_set('UTC');

// Database Connection Function
function getDatabase()
{
    global $db_host, $db_name, $db_user, $db_pass;

    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return false;
        }
    }

    return $pdo;
}

// Initialize Database
function initDatabase()
{
    global $db_host, $db_name, $db_user, $db_pass;

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
        migrateDatabase($pdo);

        return $pdo;
    } catch (PDOException $e) {
        error_log("Database initialization error: " . $e->getMessage());
        return false;
    }
}

// Database Migration
function migrateDatabase($pdo)
{
    try {
        // Check and add missing columns
        $columns = [
            'grand_resets' => 'INT DEFAULT 0 AFTER resets',
            'class' => 'VARCHAR(50) DEFAULT \'\' AFTER grand_resets',
            'guild' => 'VARCHAR(100) DEFAULT \'\' AFTER class',
            'gens' => 'VARCHAR(50) DEFAULT \'\' AFTER guild'
        ];

        foreach ($columns as $column => $definition) {
            $stmt = $pdo->query("SHOW COLUMNS FROM characters LIKE '$column'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE characters ADD COLUMN $column $definition");
            }
        }

        // Add indexes if they don't exist
        $indexes = [
            'idx_status' => 'status',
            'idx_last_updated' => 'last_updated',
            'idx_level' => 'level',
            'idx_resets' => 'resets'
        ];

        foreach ($indexes as $indexName => $column) {
            $stmt = $pdo->query("SHOW INDEX FROM characters WHERE Key_name = '$indexName'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE characters ADD INDEX $indexName ($column)");
            }
        }
    } catch (Exception $e) {
        error_log("Migration error: " . $e->getMessage());
    }
}

// Error Logging Function
function logError($message)
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[MU Tracker] $timestamp - $message" . PHP_EOL;
    error_log($logMessage);
}

// Input Sanitization
function sanitizeInput($input)
{
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// CSRF Protection
function generateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting
function checkRateLimit($action, $limit = 10, $window = 300)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = "rate_limit_$action";
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Clean old entries
    $_SESSION[$key] = array_filter($_SESSION[$key], function ($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });

    if (count($_SESSION[$key]) >= $limit) {
        return false;
    }

    $_SESSION[$key][] = $now;
    return true;
}

// Initialize database on first load
if (!isset($GLOBALS['db_initialized'])) {
    $GLOBALS['db_initialized'] = true;
    initDatabase();
}
