<?php

/**
 * Health Check Script for MU Tracker
 * Use this to verify your installation is working correctly
 */

// Disable error display for clean output
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check PHP version
$php_version = phpversion();
$health['checks']['php_version'] = [
    'status' => version_compare($php_version, '7.4.0', '>=') ? 'ok' : 'error',
    'value' => $php_version,
    'required' => '7.4.0+'
];

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'dom', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    $health['checks']['extension_' . $ext] = [
        'status' => extension_loaded($ext) ? 'ok' : 'error',
        'value' => extension_loaded($ext) ? 'loaded' : 'not loaded'
    ];
}

// Check database connection
try {
    require_once __DIR__ . '/config.php';
    $pdo = getDatabase();

    if ($pdo) {
        $health['checks']['database'] = [
            'status' => 'ok',
            'value' => 'connected'
        ];

        // Check if tables exist
        $tables = ['characters', 'character_history', 'daily_analytics', 'hourly_analytics'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $health['checks']['table_' . $table] = [
                'status' => $stmt->rowCount() > 0 ? 'ok' : 'warning',
                'value' => $stmt->rowCount() > 0 ? 'exists' : 'missing'
            ];
        }
    } else {
        $health['checks']['database'] = [
            'status' => 'error',
            'value' => 'connection failed'
        ];
    }
} catch (Exception $e) {
    $health['checks']['database'] = [
        'status' => 'error',
        'value' => 'error: ' . $e->getMessage()
    ];
}

// Check file permissions
$files_to_check = ['index.php', 'dashboard.php', 'config.php', 'functions.php'];
foreach ($files_to_check as $file) {
    $path = __DIR__ . '/' . $file;
    $health['checks']['file_' . $file] = [
        'status' => file_exists($path) && is_readable($path) ? 'ok' : 'error',
        'value' => file_exists($path) ? (is_readable($path) ? 'readable' : 'not readable') : 'not found'
    ];
}

// Check Composer dependencies
$health['checks']['composer'] = [
    'status' => file_exists(__DIR__ . '/vendor/autoload.php') ? 'ok' : 'warning',
    'value' => file_exists(__DIR__ . '/vendor/autoload.php') ? 'loaded' : 'not found'
];

// Check if .htaccess exists
$health['checks']['htaccess'] = [
    'status' => file_exists(__DIR__ . '/.htaccess') ? 'ok' : 'warning',
    'value' => file_exists(__DIR__ . '/.htaccess') ? 'exists' : 'missing'
];

// Check memory limit
$memory_limit = ini_get('memory_limit');
$health['checks']['memory_limit'] = [
    'status' => 'ok',
    'value' => $memory_limit
];

// Check max execution time
$max_execution_time = ini_get('max_execution_time');
$health['checks']['max_execution_time'] = [
    'status' => 'ok',
    'value' => $max_execution_time . 's'
];

// Check if we're in production mode
$is_production = !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
$health['checks']['environment'] = [
    'status' => 'ok',
    'value' => $is_production ? 'production' : 'development'
];

// Overall status
$has_errors = false;
$has_warnings = false;

foreach ($health['checks'] as $check) {
    if ($check['status'] === 'error') {
        $has_errors = true;
    } elseif ($check['status'] === 'warning') {
        $has_warnings = true;
    }
}

if ($has_errors) {
    $health['status'] = 'error';
} elseif ($has_warnings) {
    $health['status'] = 'warning';
}

// Add summary
$health['summary'] = [
    'total_checks' => count($health['checks']),
    'passed' => count(array_filter($health['checks'], function ($check) {
        return $check['status'] === 'ok';
    })),
    'warnings' => count(array_filter($health['checks'], function ($check) {
        return $check['status'] === 'warning';
    })),
    'errors' => count(array_filter($health['checks'], function ($check) {
        return $check['status'] === 'error';
    }))
];

echo json_encode($health, JSON_PRETTY_PRINT);
