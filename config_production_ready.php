<?php
// Production Database configuration for dragonmu-tracker.gamer.gd
$db_host = '185.27.134.205'; // Production database host
$db_name = 'dragonmu-tracker.gamer.gd'; // Production database name  
$db_user = 'if0_40047672'; // Production database username
$db_pass = 'ycezK2Y46sKn'; // Production database password

// Initialize error handling early
require_once __DIR__ . '/error_handler.php';

// Environment Detection - Force production for this domain
$is_production = true; // Always production for this config

// Security Settings
if ($is_production) {
    // Disable error display in production
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    // Secure session settings (only if session not started)
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '0'); // Set to 1 if using HTTPS
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Strict');
    }
} else {
    // Development settings
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Timezone
date_default_timezone_set('UTC');

function initDatabase()
{
    global $db_host, $db_name, $db_user, $db_pass;

    try {
        logDebug("Attempting database connection", [
            'host' => $db_host,
            'database' => $db_name,
            'user' => $db_user
        ]);

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        logInfo("Database connection established successfully");

        // Create characters table if not exists (optional, or migrate separately)
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $pdo->exec($sql);
        logDebug("Characters table created/verified");

        // Migrate existing tables to add missing columns
        migrateDatabase($pdo);

        return $pdo;
    } catch (PDOException $e) {
        logDatabaseError("database_initialization", $e->getMessage(), null);
        return false;
    }
}

function getDatabase()
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = initDatabase();
    }

    return $pdo;
}

function migrateDatabase($pdo)
{
    try {
        logDebug("Starting database migration");

        $migrations = [
            'grand_resets' => "ALTER TABLE characters ADD COLUMN grand_resets INT DEFAULT 0 AFTER resets",
            'class' => "ALTER TABLE characters ADD COLUMN class VARCHAR(50) DEFAULT '' AFTER grand_resets",
            'guild' => "ALTER TABLE characters ADD COLUMN guild VARCHAR(100) DEFAULT '' AFTER class",
            'gens' => "ALTER TABLE characters ADD COLUMN gens VARCHAR(50) DEFAULT '' AFTER guild"
        ];

        foreach ($migrations as $column => $sql) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM characters LIKE '$column'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec($sql);
                    logInfo("Migration: Added column '$column' to characters table");
                } else {
                    logDebug("Migration: Column '$column' already exists");
                }
            } catch (Exception $e) {
                logDatabaseError("migration_$column", $e->getMessage(), $sql);
            }
        }

        // Check if user_role column exists in users table
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_role'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN user_role ENUM('regular', 'vip', 'admin') DEFAULT 'regular' AFTER is_active");
                logInfo("Migration: Added user_role column to users table");
            } else {
                logDebug("Migration: user_role column already exists");
            }
        } catch (Exception $e) {
            logDatabaseError("migration_user_role", $e->getMessage(), "ALTER TABLE users ADD COLUMN user_role...");
        }

        logInfo("Database migration completed successfully");
    } catch (Exception $e) {
        logDatabaseError("database_migration", $e->getMessage(), null);
    }
}

// Enhanced Error Logging System
function logError($message, $context = [], $level = 'ERROR')
{
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

    // Build context string
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = ' | Context: ' . json_encode($context);
    }

    // Build log message
    $logMessage = sprintf(
        "[MU Tracker] %s [%s] %s | IP: %s | Method: %s %s | User-Agent: %s%s",
        $timestamp,
        $level,
        $message,
        $ip,
        $method,
        $requestUri,
        substr($userAgent, 0, 100), // Limit user agent length
        $contextStr
    );

    error_log($logMessage);

    // Also log to custom file in production for easier access
    global $is_production;
    if ($is_production) {
        $logFile = __DIR__ . '/logs/mu_tracker.log';
        $logDir = dirname($logFile);

        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Write to custom log file
        if (is_writable($logDir) || is_writable($logFile)) {
            file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

// Specific logging functions for different types of events
function logInfo($message, $context = [])
{
    logError($message, $context, 'INFO');
}

function logWarning($message, $context = [])
{
    logError($message, $context, 'WARNING');
}

function logDebug($message, $context = [])
{
    global $is_production;
    if (!$is_production) { // Only log debug in development
        logError($message, $context, 'DEBUG');
    }
}

function logDatabaseError($operation, $error, $query = null)
{
    $context = [
        'operation' => $operation,
        'error' => $error,
        'query' => $query ? substr($query, 0, 200) : null // Limit query length
    ];
    logError("Database error during $operation", $context, 'DATABASE_ERROR');
}

function logAuthEvent($username, $event, $success = true, $details = [])
{
    $context = array_merge([
        'username' => $username,
        'success' => $success
    ], $details);

    $level = $success ? 'AUTH_SUCCESS' : 'AUTH_FAILURE';
    logError("Authentication event: $event", $context, $level);
}

function logScrapingEvent($url, $success = true, $details = [])
{
    $context = array_merge([
        'url' => $url,
        'success' => $success
    ], $details);

    $level = $success ? 'SCRAPING_SUCCESS' : 'SCRAPING_ERROR';
    logError("Character scraping event", $context, $level);
}
