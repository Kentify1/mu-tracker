<?php

/**
 * Web-Based Auto Refresh Endpoint
 * 
 * This can be called by external cron services like:
 * - cron-job.org (free)
 * - EasyCron.com
 * - Your hosting provider's cron jobs
 * 
 * URL to call: https://yourdomain.com/mu-tracker/auto_refresh_endpoint.php?key=YOUR_SECRET_KEY
 */

// Security key - CHANGE THIS TO A RANDOM STRING
define('CRON_SECRET_KEY', 'your-secret-key-change-this-12345');

// Check if secret key is provided and correct
$providedKey = $_GET['key'] ?? '';
if ($providedKey !== CRON_SECRET_KEY) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access']));
}

// Set content type
header('Content-Type: application/json');

// Prevent timeout
set_time_limit(300); // 5 minutes

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/analytics.php';

try {
    // Initialize database
    $pdo = getDatabase();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Initialize analytics tables
    if (function_exists('initAnalyticsTables')) {
        initAnalyticsTables();
    }

    // Get all characters
    $stmt = $pdo->query("SELECT id, name, character_url, user_id FROM characters ORDER BY user_id, name");
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode([
            'success' => true,
            'message' => 'No characters found to refresh',
            'updated' => 0,
            'errors' => 0,
            'total' => 0
        ]);
        exit;
    }

    $updated = 0;
    $errors = 0;
    $startTime = time();
    $results = [];

    foreach ($characters as $character) {
        $characterResult = [
            'id' => $character['id'],
            'name' => $character['name'],
            'success' => false
        ];

        // Update character data with analytics
        if (updateCharacterDataWithAnalytics($character['id'], $character['character_url'])) {
            $updated++;
            $characterResult['success'] = true;
        } else {
            $errors++;
            $characterResult['error'] = 'Failed to update character data';
        }

        $results[] = $characterResult;

        // Small delay to avoid overwhelming the target server
        usleep(500000); // 0.5 second delay
    }

    $totalTime = time() - $startTime;
    $totalCharacters = count($characters);
    $successRate = $totalCharacters > 0 ? round(($updated / $totalCharacters) * 100, 1) : 0;

    // Log activity
    if (function_exists('logActivity')) {
        $details = "Web cron refresh: $updated/$totalCharacters characters updated ({$successRate}% success rate)";
        logActivity('WEB_CRON', 'Auto refresh completed', $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    // Return results
    echo json_encode([
        'success' => true,
        'message' => "Updated $updated characters" . ($errors > 0 ? " with $errors errors" : ""),
        'updated' => $updated,
        'errors' => $errors,
        'total' => $totalCharacters,
        'success_rate' => $successRate,
        'execution_time' => $totalTime,
        'timestamp' => date('Y-m-d H:i:s'),
        'details' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Log error
    error_log("Auto refresh endpoint error: " . $e->getMessage());
}
