<?php

/**
 * VIP and Admin Enhancements for MU Tracker
 * This file contains advanced features for VIP users and administrators
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Initialize auth object if not already available
if (!isset($auth)) {
    $auth = new UserAuth();
}

/**
 * Simple VIP Analytics function for testing
 */
function getVipPredictiveAnalyticsSimple($characterId, $days = 30)
{
    global $auth;

    // Basic validation
    if (!isset($auth)) {
        return ['success' => false, 'message' => 'Authentication system not available'];
    }

    if (!$auth->isVipOrAdmin()) {
        return ['success' => false, 'message' => 'VIP access required'];
    }

    // Return basic working data
    return [
        'success' => true,
        'data' => [
            'patterns' => [
                'best_days_of_week' => [
                    ['day' => 'Saturday', 'avg_resets' => 12.5, 'total_days' => 8],
                    ['day' => 'Sunday', 'avg_resets' => 10.2, 'total_days' => 8],
                    ['day' => 'Friday', 'avg_resets' => 8.7, 'total_days' => 8]
                ],
                'best_days_of_month' => [],
                'weekly_trends' => [],
                'seasonal_patterns' => []
            ],
            'predictions' => [
                'predicted_resets' => 150,
                'predicted_grand_resets' => 8,
                'confidence' => 78,
                'trend_direction' => 'improving',
                'daily_avg_prediction' => 5.2
            ],
            'efficiency' => [
                'consistency_rate' => 65.5,
                'efficiency_score' => 7.8,
                'longest_streak' => 12,
                'total_active_days' => 18,
                'total_days_analyzed' => 30
            ],
            'recommendations' => [
                [
                    'type' => 'timing',
                    'priority' => 'high',
                    'title' => 'Weekend Focus',
                    'message' => 'You perform best on weekends. Consider scheduling longer gaming sessions on Saturday and Sunday.',
                    'icon' => 'calendar-alt'
                ],
                [
                    'type' => 'consistency',
                    'priority' => 'medium',
                    'title' => 'Improve Consistency',
                    'message' => 'Try to play more regularly during weekdays to improve your overall efficiency.',
                    'icon' => 'chart-line'
                ]
            ],
            'data_points' => 30,
            'analysis_period' => '30 days'
        ]
    ];
}

/**
 * Simple test function for VIP Analytics
 */
function getVipPredictiveAnalyticsTest($characterId, $days = 30)
{
    global $auth;

    if (!isset($auth)) {
        $auth = new UserAuth();
    }

    if (!$auth->isVipOrAdmin()) {
        return ['success' => false, 'message' => 'VIP access required'];
    }

    return [
        'success' => true,
        'data' => [
            'patterns' => ['test' => 'data'],
            'predictions' => [
                'predicted_resets' => 100,
                'predicted_grand_resets' => 5,
                'confidence' => 85,
                'trend_direction' => 'improving',
                'daily_avg_prediction' => 3.5
            ],
            'efficiency' => [
                'consistency_rate' => 75.5,
                'efficiency_score' => 8.2,
                'longest_streak' => 14,
                'total_active_days' => 22,
                'total_days_analyzed' => 30
            ],
            'recommendations' => [
                [
                    'type' => 'timing',
                    'priority' => 'high',
                    'title' => 'Test Recommendation',
                    'message' => 'This is a test recommendation for VIP analytics.',
                    'icon' => 'calendar-alt'
                ]
            ],
            'data_points' => 30,
            'analysis_period' => '30 days'
        ]
    ];
}

/**
 * Advanced VIP Analytics - Predictive Analysis
 */
function getVipPredictiveAnalytics($characterId, $days = 30)
{
    global $auth;

    // Debug logging
    error_log("[VIP Analytics] Starting predictive analytics for character ID: $characterId, days: $days");

    if (!isset($auth)) {
        error_log("[VIP Analytics] Auth object not found, initializing...");
        $auth = new UserAuth();
    }

    if (!$auth->isVipOrAdmin()) {
        error_log("[VIP Analytics] User is not VIP or Admin");
        return ['success' => false, 'message' => 'VIP access required'];
    }

    $pdo = getDatabase();
    if (!$pdo) {
        error_log("[VIP Analytics] Database connection failed");
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Check if daily_progress table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'daily_progress'");
        if ($stmt->rowCount() == 0) {
            error_log("[VIP Analytics] daily_progress table does not exist, trying to initialize...");

            // Try to initialize analytics tables
            if (function_exists('initAnalyticsTables')) {
                $initResult = initAnalyticsTables();
                if (!$initResult) {
                    return ['success' => false, 'message' => 'Analytics tables could not be initialized. Please contact administrator.'];
                }
                error_log("[VIP Analytics] Analytics tables initialized successfully");
            } else {
                return ['success' => false, 'message' => 'Analytics system not available. Please contact administrator.'];
            }
        }

        error_log("[VIP Analytics] daily_progress table exists");

        // Check if character exists
        $stmt = $pdo->prepare("SELECT id, name FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$character) {
            error_log("[VIP Analytics] Character not found: $characterId");
            return ['success' => false, 'message' => 'Character not found'];
        }

        error_log("[VIP Analytics] Character found: " . $character['name']);
        // Get extended historical data (6 months for VIP)
        $stmt = $pdo->prepare("
            SELECT date, resets_gained, grand_resets_gained,
                   DAY(date) as day_of_month,
                   DAYOFWEEK(date) as day_of_week,
                   WEEK(date) as week_of_year
            FROM daily_progress 
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("[VIP Analytics] Retrieved " . count($data) . " data points for character $characterId");

        if (empty($data)) {
            error_log("[VIP Analytics] No data found for character $characterId");
            // Return a basic analysis with sample data for demonstration
            return [
                'success' => true,
                'data' => [
                    'patterns' => [
                        'best_days_of_week' => [],
                        'best_days_of_month' => [],
                        'weekly_trends' => [],
                        'seasonal_patterns' => []
                    ],
                    'predictions' => [
                        'predicted_resets' => 0,
                        'predicted_grand_resets' => 0,
                        'confidence' => 0,
                        'trend_direction' => 'stable',
                        'daily_avg_prediction' => 0
                    ],
                    'efficiency' => [
                        'consistency_rate' => 0,
                        'efficiency_score' => 0,
                        'longest_streak' => 0,
                        'total_active_days' => 0,
                        'total_days_analyzed' => 0
                    ],
                    'recommendations' => [
                        [
                            'type' => 'data',
                            'priority' => 'high',
                            'title' => 'Start Tracking Progress',
                            'message' => 'No historical data found. Start playing and refreshing your character to build analytics data.',
                            'icon' => 'chart-line'
                        ]
                    ],
                    'data_points' => 0,
                    'analysis_period' => '180 days'
                ]
            ];
        }

        // Calculate patterns and predictions
        error_log("[VIP Analytics] Analyzing patterns...");
        $patterns = analyzePatterns($data);

        error_log("[VIP Analytics] Generating predictions...");
        $predictions = generatePredictions($data, $days);

        error_log("[VIP Analytics] Calculating efficiency metrics...");
        $efficiency = calculateEfficiencyMetrics($data);

        error_log("[VIP Analytics] Generating recommendations...");
        $recommendations = generateRecommendations($patterns, $efficiency);

        return [
            'success' => true,
            'data' => [
                'patterns' => $patterns,
                'predictions' => $predictions,
                'efficiency' => $efficiency,
                'recommendations' => $recommendations,
                'data_points' => count($data),
                'analysis_period' => '180 days'
            ]
        ];
    } catch (Exception $e) {
        error_log("[VIP Analytics] Exception: " . $e->getMessage());
        error_log("[VIP Analytics] Stack trace: " . $e->getTraceAsString());
        logError("Error in VIP predictive analytics: " . $e->getMessage());
        return ['success' => false, 'message' => 'Analysis failed: ' . $e->getMessage()];
    }
}

/**
 * Analyze patterns in character progress
 */
function analyzePatterns($data)
{
    $patterns = [
        'best_days_of_week' => [],
        'best_days_of_month' => [],
        'weekly_trends' => [],
        'seasonal_patterns' => []
    ];

    if (empty($data)) {
        return $patterns;
    }

    // Group by day of week
    $dayOfWeekData = [];
    foreach ($data as $row) {
        $dow = $row['day_of_week'];
        if (!isset($dayOfWeekData[$dow])) {
            $dayOfWeekData[$dow] = [];
        }
        $dayOfWeekData[$dow][] = $row['resets_gained'];
    }

    // Calculate averages for each day of week
    $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($dayOfWeekData as $dow => $resets) {
        $avg = array_sum($resets) / count($resets);
        $patterns['best_days_of_week'][] = [
            'day' => $dayNames[$dow],
            'day_number' => $dow,
            'avg_resets' => round($avg, 2),
            'total_days' => count($resets)
        ];
    }

    // Sort by average resets
    usort($patterns['best_days_of_week'], function ($a, $b) {
        return $b['avg_resets'] <=> $a['avg_resets'];
    });

    // Group by day of month
    $dayOfMonthData = [];
    foreach ($data as $row) {
        $dom = $row['day_of_month'];
        if (!isset($dayOfMonthData[$dom])) {
            $dayOfMonthData[$dom] = [];
        }
        $dayOfMonthData[$dom][] = $row['resets_gained'];
    }

    // Calculate averages for each day of month
    foreach ($dayOfMonthData as $dom => $resets) {
        if (count($resets) >= 3) { // Only include days with sufficient data
            $avg = array_sum($resets) / count($resets);
            $patterns['best_days_of_month'][] = [
                'day' => $dom,
                'avg_resets' => round($avg, 2),
                'total_occurrences' => count($resets)
            ];
        }
    }

    // Sort by average resets
    usort($patterns['best_days_of_month'], function ($a, $b) {
        return $b['avg_resets'] <=> $a['avg_resets'];
    });

    // Weekly trends
    $weeklyData = [];
    foreach ($data as $row) {
        $week = $row['week_of_year'];
        if (!isset($weeklyData[$week])) {
            $weeklyData[$week] = [];
        }
        $weeklyData[$week][] = $row['resets_gained'];
    }

    foreach ($weeklyData as $week => $resets) {
        $patterns['weekly_trends'][] = [
            'week' => $week,
            'total_resets' => array_sum($resets),
            'avg_resets' => round(array_sum($resets) / count($resets), 2),
            'days_active' => count($resets)
        ];
    }

    return $patterns;
}

/**
 * Generate predictions based on historical data
 */
function generatePredictions($data, $days)
{
    if (empty($data)) {
        return [
            'predicted_resets' => 0,
            'predicted_grand_resets' => 0,
            'confidence' => 0,
            'trend_direction' => 'stable',
            'daily_avg_prediction' => 0
        ];
    }

    $recentData = array_slice($data, -30); // Last 30 days
    $avgResets = array_sum(array_column($recentData, 'resets_gained')) / count($recentData);
    $avgGrandResets = array_sum(array_column($recentData, 'grand_resets_gained')) / count($recentData);

    // Calculate trend
    $firstHalf = array_slice($recentData, 0, 15);
    $secondHalf = array_slice($recentData, 15);

    $firstHalfAvg = array_sum(array_column($firstHalf, 'resets_gained')) / count($firstHalf);
    $secondHalfAvg = array_sum(array_column($secondHalf, 'resets_gained')) / count($secondHalf);

    $trendMultiplier = $firstHalfAvg > 0 ? $secondHalfAvg / $firstHalfAvg : 1;

    return [
        'predicted_resets' => round($avgResets * $days * $trendMultiplier),
        'predicted_grand_resets' => round($avgGrandResets * $days * $trendMultiplier),
        'confidence' => min(95, count($data) * 0.5), // Higher confidence with more data
        'trend_direction' => $trendMultiplier > 1.1 ? 'improving' : ($trendMultiplier < 0.9 ? 'declining' : 'stable'),
        'daily_avg_prediction' => round($avgResets * $trendMultiplier, 2)
    ];
}

/**
 * Calculate efficiency metrics
 */
function calculateEfficiencyMetrics($data)
{
    if (empty($data)) {
        return [
            'consistency_rate' => 0,
            'efficiency_score' => 0,
            'longest_streak' => 0,
            'total_active_days' => 0,
            'total_days_analyzed' => 0
        ];
    }

    $activeDays = array_filter($data, function ($row) {
        return $row['resets_gained'] > 0;
    });

    $totalResets = array_sum(array_column($data, 'resets_gained'));
    $totalActiveDays = count($activeDays);
    $totalDays = count($data);

    $consistency = $totalDays > 0 ? ($totalActiveDays / $totalDays) * 100 : 0;
    $efficiency = $totalActiveDays > 0 ? $totalResets / $totalActiveDays : 0;

    // Calculate streaks
    $longestStreak = 0;
    $currentStreak = 0;
    foreach ($data as $row) {
        if ($row['resets_gained'] > 0) {
            $currentStreak++;
            $longestStreak = max($longestStreak, $currentStreak);
        } else {
            $currentStreak = 0;
        }
    }

    return [
        'consistency_rate' => round($consistency, 1),
        'efficiency_score' => round($efficiency, 2),
        'longest_streak' => $longestStreak,
        'total_active_days' => $totalActiveDays,
        'total_days_analyzed' => $totalDays
    ];
}

/**
 * Generate personalized recommendations
 */
function generateRecommendations($patterns, $efficiency)
{
    $recommendations = [];

    if (empty($patterns) || empty($efficiency)) {
        return [[
            'type' => 'data',
            'priority' => 'medium',
            'title' => 'Insufficient Data',
            'message' => 'More gameplay data needed to generate personalized recommendations.',
            'icon' => 'info-circle'
        ]];
    }

    // Day of week recommendations
    if (!empty($patterns['best_days_of_week'])) {
        $bestDay = $patterns['best_days_of_week'][0];
        $recommendations[] = [
            'type' => 'timing',
            'priority' => 'high',
            'title' => 'Optimal Play Days',
            'message' => "You perform best on {$bestDay['day']}s with an average of {$bestDay['avg_resets']} resets. Consider focusing your main grinding sessions on this day.",
            'icon' => 'calendar-alt'
        ];
    }

    // Consistency recommendations
    if ($efficiency['consistency_rate'] < 50) {
        $recommendations[] = [
            'type' => 'consistency',
            'priority' => 'medium',
            'title' => 'Improve Consistency',
            'message' => "Your consistency rate is {$efficiency['consistency_rate']}%. Try to play more regularly, even if for shorter sessions.",
            'icon' => 'chart-line'
        ];
    }

    // Efficiency recommendations
    if ($efficiency['efficiency_score'] < 5) {
        $recommendations[] = [
            'type' => 'efficiency',
            'priority' => 'high',
            'title' => 'Boost Efficiency',
            'message' => "Your efficiency score is {$efficiency['efficiency_score']}. Consider optimizing your grinding strategy or locations.",
            'icon' => 'tachometer-alt'
        ];
    }

    // Streak recommendations
    if ($efficiency['longest_streak'] < 7) {
        $recommendations[] = [
            'type' => 'streak',
            'priority' => 'low',
            'title' => 'Build Longer Streaks',
            'message' => "Your longest streak is {$efficiency['longest_streak']} days. Try to maintain consistent daily progress to build momentum.",
            'icon' => 'fire'
        ];
    }

    return $recommendations;
}

/**
 * Advanced Admin System Monitoring
 */
function getSystemHealthMetrics()
{
    global $auth;

    if (!$auth->isAdmin()) {
        return ['success' => false, 'message' => 'Admin access required'];
    }

    $pdo = getDatabase();
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed'];

    try {
        $metrics = [];

        // Database health
        $stmt = $pdo->query("SHOW TABLE STATUS");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSize = 0;
        $totalRows = 0;
        foreach ($tables as $table) {
            $totalSize += $table['Data_length'] + $table['Index_length'];
            $totalRows += $table['Rows'];
        }

        $metrics['database'] = [
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'total_rows' => $totalRows,
            'table_count' => count($tables)
        ];

        // User activity metrics
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as active_24h,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_7d,
                SUM(CASE WHEN user_role = 'vip' THEN 1 ELSE 0 END) as vip_users,
                SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as admin_users
            FROM users
        ");
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['users'] = $userStats;

        // Character activity
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_characters,
                SUM(CASE WHEN last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as updated_24h,
                AVG(level) as avg_level,
                AVG(resets) as avg_resets
            FROM characters
        ");
        $charStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['characters'] = $charStats;

        // System performance indicators
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_logs,
                SUM(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as logs_1h
            FROM activity_logs
        ");
        $logStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['activity'] = $logStats;

        // Error rate analysis
        $errorLogFile = __DIR__ . '/logs/mu_tracker.log';
        $errorCount = 0;
        if (file_exists($errorLogFile)) {
            $logContent = file_get_contents($errorLogFile);
            $errorCount = substr_count($logContent, '[ERROR]') + substr_count($logContent, '[DATABASE_ERROR]');
        }

        $metrics['errors'] = [
            'recent_errors' => $errorCount,
            'log_file_size_kb' => file_exists($errorLogFile) ? round(filesize($errorLogFile) / 1024, 2) : 0
        ];

        return ['success' => true, 'data' => $metrics];
    } catch (Exception $e) {
        logError("Error getting system health metrics: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to get system metrics'];
    }
}

/**
 * Advanced User Management for Admins
 */
function getAdvancedUserAnalytics()
{
    global $auth;

    if (!$auth->isAdmin()) {
        return ['success' => false, 'message' => 'Admin access required'];
    }

    $pdo = getDatabase();
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed'];

    try {
        // User registration trends
        $stmt = $pdo->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as registrations,
                SUM(CASE WHEN user_role = 'vip' THEN 1 ELSE 0 END) as vip_registrations
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $registrationTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // User engagement analysis
        $stmt = $pdo->query("
            SELECT 
                u.id, u.username, u.user_role, u.created_at, u.last_login,
                COUNT(c.id) as character_count,
                COALESCE(SUM(dp.resets_gained), 0) as total_resets_gained,
                COUNT(DISTINCT dp.date) as active_days,
                DATEDIFF(NOW(), u.created_at) as days_since_registration
            FROM users u
            LEFT JOIN characters c ON u.id = c.user_id
            LEFT JOIN daily_progress dp ON c.id = dp.character_id AND dp.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id
            ORDER BY total_resets_gained DESC
        ");
        $userEngagement = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // License usage statistics
        $stmt = $pdo->query("
            SELECT 
                license_type,
                COUNT(*) as total_licenses,
                SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used_licenses,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired_licenses
            FROM license_keys
            GROUP BY license_type
        ");
        $licenseStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'data' => [
                'registration_trends' => $registrationTrends,
                'user_engagement' => $userEngagement,
                'license_statistics' => $licenseStats
            ]
        ];
    } catch (Exception $e) {
        logError("Error getting advanced user analytics: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to get user analytics'];
    }
}

/**
 * VIP Heatmap Analytics - Shows when users are most active
 */
function getVipHeatmapData($characterId)
{
    global $auth;

    if (!$auth->isVipOrAdmin()) {
        return ['success' => false, 'message' => 'VIP access required'];
    }

    $pdo = getDatabase();
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed'];

    try {
        // Get hourly activity data for the last 30 days
        $stmt = $pdo->prepare("
            SELECT 
                DATE(date) as activity_date,
                hour,
                SUM(resets_gained) as total_resets,
                SUM(status_changes) as activity_level
            FROM hourly_analytics 
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date), hour
            ORDER BY activity_date ASC, hour ASC
        ");
        $stmt->execute([$characterId]);
        $hourlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create heatmap matrix (24 hours x 7 days of week)
        $heatmap = [];
        for ($hour = 0; $hour < 24; $hour++) {
            for ($dow = 0; $dow < 7; $dow++) {
                $heatmap[$hour][$dow] = 0;
            }
        }

        // Populate heatmap with activity data
        foreach ($hourlyData as $data) {
            $dow = date('w', strtotime($data['activity_date'])); // 0 = Sunday
            $hour = (int)$data['hour'];
            $heatmap[$hour][$dow] += (int)$data['total_resets'];
        }

        return [
            'success' => true,
            'data' => [
                'heatmap' => $heatmap,
                'raw_data' => $hourlyData,
                'period' => '30 days'
            ]
        ];
    } catch (Exception $e) {
        logError("Error getting VIP heatmap data: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to get heatmap data'];
    }
}

/**
 * Admin Bulk Operations
 */
function performBulkUserOperation($operation, $userIds, $params = [])
{
    global $auth;

    if (!$auth->isAdmin()) {
        return ['success' => false, 'message' => 'Admin access required'];
    }

    $pdo = getDatabase();
    if (!$pdo) return ['success' => false, 'message' => 'Database connection failed'];

    try {
        $results = [];
        $currentUser = $auth->getCurrentUser();

        foreach ($userIds as $userId) {
            $userId = (int)$userId;

            switch ($operation) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                    $success = $stmt->execute([$userId]);
                    if ($success) {
                        logActivity($currentUser['username'], 'Bulk user activation', "Activated user ID: $userId");
                    }
                    break;

                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $success = $stmt->execute([$userId]);
                    if ($success) {
                        logActivity($currentUser['username'], 'Bulk user deactivation', "Deactivated user ID: $userId");
                    }
                    break;

                case 'change_role':
                    $newRole = $params['role'] ?? 'regular';
                    $stmt = $pdo->prepare("UPDATE users SET user_role = ? WHERE id = ?");
                    $success = $stmt->execute([$newRole, $userId]);
                    if ($success) {
                        logActivity($currentUser['username'], 'Bulk role change', "Changed user ID $userId to role: $newRole");
                    }
                    break;

                case 'reset_login_attempts':
                    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
                    $success = $stmt->execute([$userId]);
                    if ($success) {
                        logActivity($currentUser['username'], 'Bulk login reset', "Reset login attempts for user ID: $userId");
                    }
                    break;

                default:
                    $success = false;
                    break;
            }

            $results[$userId] = $success;
        }

        $successCount = count(array_filter($results));
        $totalCount = count($results);

        return [
            'success' => true,
            'data' => [
                'operation' => $operation,
                'total_processed' => $totalCount,
                'successful' => $successCount,
                'failed' => $totalCount - $successCount,
                'results' => $results
            ]
        ];
    } catch (Exception $e) {
        logError("Error performing bulk user operation: " . $e->getMessage());
        return ['success' => false, 'message' => 'Bulk operation failed'];
    }
}
