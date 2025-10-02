<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/vip_admin_enhancements.php';

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: login');
    exit;
}

// Initialize analytics tables
initAnalyticsTables();

// Also ensure we have the analytics functions available
if (!function_exists('getVipPredictiveAnalyticsSimple')) {
    require_once __DIR__ . '/vip_admin_enhancements.php';
}

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'get_character_stats':
            $characterId = intval($_POST['character_id']);
            $period = $_POST['period'] ?? 'day'; // day, week, month
            $result = getCharacterStatsByPeriod($characterId, $period);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;

        case 'get_hourly_stats':
            $characterId = intval($_POST['character_id']);
            $date = $_POST['date'] ?? date('Y-m-d');
            $result = getHourlyStats($characterId, $date);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;

        case 'get_leaderboard':
            $period = $_POST['period'] ?? 'week';
            $result = getLeaderboard($period);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;

        case 'refresh_all':
            set_time_limit(300);
            $batchSize = intval($_POST['batchSize'] ?? 5);
            $sleepMs = intval($_POST['sleepMs'] ?? 200);
            $ok = updateAllCharactersInBatches(max(1, $batchSize), max(0, $sleepMs));

            // Log dashboard refresh activity
            $currentUser = $auth->getCurrentUser();
            logActivity($currentUser['username'], 'Dashboard refresh', "Refreshed all characters from dashboard", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            echo json_encode(['success' => (bool)$ok]);
            exit;

        case 'get_all_characters_comparison':
            $period = $_POST['period'] ?? 'day';
            $days = intval($_POST['days'] ?? 30);
            $metric = $_POST['metric'] ?? 'level';
            $result = getAllCharactersComparison($period, $days, $metric);
            echo json_encode(['success' => true, 'data' => $result]);
            exit;

        case 'get_character_rankings':
            $period = $_POST['period'] ?? 'day';
            $metric = $_POST['metric'] ?? 'resets_gained';
            $isVip = $auth->isVipOrAdmin();
            $result = getCharacterRankings($period, $metric, $isVip);
            echo json_encode($result);
            exit;

        case 'get_vip_analytics':
            $characterId = intval($_POST['character_id']);
            $period = $_POST['period'] ?? 'month';
            $result = getVipCharacterAnalytics($characterId, $period);
            echo json_encode($result);
            exit;

        case 'export_character_data':
            $characterId = intval($_POST['character_id']);
            $format = $_POST['format'] ?? 'csv';
            exportCharacterData($characterId, $format);
            exit;

        case 'get_vip_predictive_analytics':
            $characterId = intval($_POST['character_id']);
            $days = intval($_POST['days'] ?? 30);

            // Add debugging
            error_log("[Dashboard] VIP Predictive Analytics requested for character: $characterId, days: $days");

            try {
                // Use test function temporarily to isolate the issue
                $result = getVipPredictiveAnalyticsSimple($characterId, $days);
                error_log("[Dashboard] VIP Analytics result: " . json_encode($result));
                echo json_encode($result);
            } catch (Exception $e) {
                error_log("[Dashboard] VIP Analytics error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
            }
            exit;

        case 'get_vip_heatmap':
            $characterId = intval($_POST['character_id']);
            $result = getVipHeatmapData($characterId);
            echo json_encode($result);
            exit;

        case 'get_system_health':
            $result = getSystemHealthMetrics();
            echo json_encode($result);
            exit;

        case 'get_advanced_user_analytics':
            $result = getAdvancedUserAnalytics();
            echo json_encode($result);
            exit;
    }
}

// Page load data
$allCharacters = getAllCharacters();
$leaderboard = getLeaderboard('week');
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MU Character Analytics - Enhanced Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="shortcut icon" href="https://dragon.mu/assets/dragon/images/favicon.ico" />

    <style>
        :root {
            --bg: #0d1117;
            --muted: #9aa4b2;
        }

        body {
            background: linear-gradient(135deg, #0d1117, #121418);
            color: #cbd5e1;
        }

        .card {
            background: rgba(16, 20, 26, .7);
            border: 1px solid rgba(80, 90, 100, .12);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 51, 234, 0.1));
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .chart-container {
            position: relative;
            height: 400px;
        }

        .small-muted {
            color: var(--muted);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #60a5fa;
        }

        .metric-label {
            font-size: 0.9rem;
            color: var(--muted);
        }

        /* Toast Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            max-width: 400px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }

        .toast-body {
            padding: 12px 16px;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #666;
        }

        .toast-icon {
            margin-right: 8px;
        }

        .toast.success {
            border-left: 4px solid #28a745;
        }

        .toast.error {
            border-left: 4px solid #dc3545;
        }

        .toast.warning {
            border-left: 4px solid #ffc107;
        }

        .toast.info {
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-chart-line text-primary me-2"></i>MU Character Analytics</h1>
            <div>
                <span class="text-light me-3">Welcome, <?= htmlspecialchars($auth->getCurrentUser()['username']) ?>!</span>
                <a href="index" class="btn btn-outline-light me-2"><i class="fa-solid fa-gamepad"></i> Tracker</a>
                <button id="refreshAllBtn" class="btn btn-outline-light me-2"><i class="fas fa-sync-alt me-1"></i>Refresh All</button>
                <button id="compareAllBtn" class="btn btn-outline-primary me-2"><i class="fas fa-chart-bar me-1"></i>Compare All Characters</button>
                <?php if ($auth->isVipOrAdmin()): ?>
                    <button id="exportDataBtn" class="btn btn-outline-success me-2"><i class="fas fa-download me-1"></i>Export Data (VIP)</button>
                    <button id="vipAnalyticsBtn" class="btn btn-outline-info me-2"><i class="fas fa-chart-line me-1"></i>VIP Analytics</button>
                    <button id="vipPredictiveBtn" class="btn btn-outline-warning me-2"><i class="fas fa-crystal-ball me-1"></i>Predictive Analytics (VIP)</button>
                    <button id="vipHeatmapBtn" class="btn btn-outline-danger me-2"><i class="fas fa-fire me-1"></i>Activity Heatmap (VIP)</button>
                <?php endif; ?>
                <?php if ($auth->isAdmin()): ?>
                    <a href="admin" class="btn btn-outline-warning me-2"><i class="fas fa-crown me-1"></i>Admin Panel</a>
                <?php endif; ?>
                <a href="logout" class="btn btn-outline-danger me-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Character Selection -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card p-3">
                    <h5 class="mb-3">Select Character & Time Period</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Character</label>
                            <select id="characterSelect" class="form-select">
                                <option value="">Select a character...</option>
                                <?php foreach ($allCharacters as $char): ?>
                                    <option value="<?= $char['id'] ?>"><?= htmlspecialchars($char['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time Period</label>
                            <select id="periodSelect" class="form-select">
                                <option value="day">Daily</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                                <?php if ($auth->isVipOrAdmin()): ?>
                                    <option value="quarter">Quarterly (VIP)</option>
                                    <option value="half_year">6 Months (VIP)</option>
                                    <option value="year">Yearly (VIP)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date Range</label>
                            <select id="rangeSelect" class="form-select">
                                <option value="7">Last 7 days</option>
                                <option value="14">Last 14 days</option>
                                <option value="30" selected>Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <?php if ($auth->isVipOrAdmin()): ?>
                                    <option value="180">Last 6 months (VIP)</option>
                                    <option value="365">Last year (VIP)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="row mb-4" id="statsOverview" style="display: none;">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="metric-value" id="totalResets">0</div>
                    <div class="metric-label">Total Resets Gained</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="metric-value" id="totalGrandResets">0</div>
                    <div class="metric-label">Total Grand Resets Gained</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="metric-value" id="avgResetsPerDay">0</div>
                    <div class="metric-label">Avg Resets/Day</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="metric-value" id="activeDays">0</div>
                    <div class="metric-label">Active Days</div>
                </div>
            </div>
        </div>

        <!-- Performance Stats -->
        <div class="row mb-4" id="performanceStats" style="display: none;">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.1)); border: 1px solid rgba(34, 197, 94, 0.3);">
                    <div class="metric-value" style="color: #22c55e;" id="bestDayResets">0</div>
                    <div class="metric-label">Best Day (Resets)</div>
                    <div class="small-muted" id="bestDayDate">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <div class="metric-value" style="color: #ef4444;" id="worstDayResets">0</div>
                    <div class="metric-label">Worst Day (Resets)</div>
                    <div class="small-muted" id="worstDayDate">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3);">
                    <div class="metric-value" style="color: #3b82f6;" id="improvementRate">0%</div>
                    <div class="metric-label">Improvement Rate</div>
                    <div class="small-muted" id="improvementDays">0 improvement days</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(147, 51, 234, 0.1)); border: 1px solid rgba(168, 85, 247, 0.3);">
                    <div class="metric-value" style="color: #a855f7;" id="longestStreak">0</div>
                    <div class="metric-label">Longest Improvement Streak</div>
                    <div class="small-muted">consecutive days</div>
                </div>
            </div>
        </div>

        <!-- Comparison Chart -->
        <div class="row mb-4" id="comparisonSection" style="display: none;">
            <div class="col-12">
                <div class="card p-3">
                    <h5 class="mb-3">All Characters Comparison - <span id="comparisonTitle">Level</span></h5>
                    <div class="chart-container" style="height: 500px;">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Metric</label>
                                <select id="comparisonMetric" class="form-select">
                                    <option value="level">Level</option>
                                    <option value="resets">Resets</option>
                                    <option value="grand_resets">Grand Resets</option>
                                    <option value="resets_gained">Resets Gained</option>
                                    <option value="grand_resets_gained">Grand Resets Gained</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Chart Type</label>
                                <select id="comparisonChartType" class="form-select">
                                    <option value="line">Line Chart</option>
                                    <option value="bar">Bar Chart</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Actions</label>
                                <div>
                                    <button id="downloadComparisonChart" class="btn btn-sm btn-outline-secondary me-2">Download PNG</button>
                                    <button id="hideComparison" class="btn btn-sm btn-outline-danger">Hide Comparison</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Analytics for All Characters -->
        <div class="row mb-4" id="overallAnalytics" style="display: none;">
            <div class="col-12">
                <div class="card p-3">
                    <h5 class="mb-3"><i class="fas fa-chart-pie text-primary me-2"></i>Overall Analytics - All Characters</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3);">
                                <div class="metric-value" style="color: #3b82f6;" id="overallTotalResets">0</div>
                                <div class="metric-label">Total Resets Gained</div>
                                <div class="small-muted" id="overallTotalCharacters">0 characters</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(147, 51, 234, 0.1)); border: 1px solid rgba(168, 85, 247, 0.3);">
                                <div class="metric-value" style="color: #a855f7;" id="overallTotalGrandResets">0</div>
                                <div class="metric-label">Total Grand Resets Gained</div>
                                <div class="small-muted" id="overallTotalActiveDays">0 active days</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(16, 185, 129, 0.1)); border: 1px solid rgba(34, 197, 94, 0.3);">
                                <div class="metric-value" style="color: #22c55e;" id="overallAvgImprovement">0%</div>
                                <div class="metric-label">Average Improvement Rate</div>
                                <div class="small-muted" id="overallAvgStreak">0 avg streak</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1)); border: 1px solid rgba(245, 158, 11, 0.3);">
                                <div class="metric-value" style="color: #f59e0b;" id="overallBestPerformer">-</div>
                                <div class="metric-label">Best Performer</div>
                                <div class="small-muted" id="overallBestResets">0 resets</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Character Rankings -->
        <div class="row mb-4" id="characterRankings" style="display: none;">
            <div class="col-12">
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Character Rankings</h5>
                        <div class="d-flex gap-2">
                            <select id="rankingPeriod" class="form-select form-select-sm" style="width: auto;">
                                <option value="day">Daily</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                            </select>
                            <select id="rankingMetric" class="form-select form-select-sm" style="width: auto;">
                                <option value="resets_gained">Resets Gained</option>
                                <option value="grand_resets_gained">Grand Resets Gained</option>
                                <option value="level">Level</option>
                                <option value="resets">Total Resets</option>
                                <option value="grand_resets">Total Grand Resets</option>
                            </select>
                            <button id="refreshRankings" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card p-3" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2);">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-crown me-2"></i>
                                    <span id="topPerformersTitle">Top Performers</span>
                                </h6>
                                <div id="topPerformersList"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <span id="bestDayTitle">Best Day Performers</span>
                                </h6>
                                <div id="bestDayList"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card p-3" style="background: rgba(168, 85, 247, 0.05); border: 1px solid rgba(168, 85, 247, 0.2);">
                                <h6 class="text-purple mb-3">
                                    <i class="fas fa-star me-2"></i>
                                    <span id="consistentTitle">Most Consistent</span>
                                </h6>
                                <div id="consistentList"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2);">
                                <h6 class="text-warning mb-3">
                                    <i class="fas fa-trending-up me-2"></i>
                                    <span id="improvementTitle">Most Improved</span>
                                </h6>
                                <div id="improvementList"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.2);">
                                <h6 class="text-success mb-3">
                                    <i class="fas fa-bolt me-2"></i>
                                    <span id="efficientTitle">Most Efficient</span>
                                </h6>
                                <div id="efficientList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VIP Analytics Section -->
        <?php if ($auth->isVipOrAdmin()): ?>
            <div class="row mb-4" id="vipAnalyticsSection" style="display: none;">
                <div class="col-12">
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fas fa-star text-warning me-2"></i>VIP Analytics - Extended History</h5>
                            <div class="d-flex gap-2">
                                <select id="vipPeriodSelect" class="form-select form-select-sm" style="width: auto;">
                                    <option value="month">Monthly</option>
                                    <option value="quarter">Quarterly</option>
                                    <option value="half_year">6 Months</option>
                                    <option value="year">Yearly</option>
                                </select>
                                <button id="loadVipAnalytics" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-sync"></i> Load VIP Data
                                </button>
                            </div>
                        </div>

                        <div id="vipAnalyticsContent">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(147, 51, 234, 0.1)); border: 1px solid rgba(168, 85, 247, 0.3);">
                                        <div class="metric-value" style="color: #a855f7;" id="vipTotalResets">0</div>
                                        <div class="metric-label">Total Resets (VIP)</div>
                                        <div class="small-muted" id="vipDaysAnalyzed">0 days analyzed</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1)); border: 1px solid rgba(34, 197, 94, 0.3);">
                                        <div class="metric-value" style="color: #22c55e;" id="vipAvgResets">0</div>
                                        <div class="metric-label">Avg Resets/Day</div>
                                        <div class="small-muted" id="vipActiveDays">0 active days</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3);">
                                        <div class="metric-value" style="color: #3b82f6;" id="vipTrend">Stable</div>
                                        <div class="metric-label">Performance Trend</div>
                                        <div class="small-muted" id="vipImprovementRate">0% improvement</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1)); border: 1px solid rgba(245, 158, 11, 0.3);">
                                        <div class="metric-value" style="color: #f59e0b;" id="vipBestDay">0</div>
                                        <div class="metric-label">Best Day Resets</div>
                                        <div class="small-muted" id="vipBestDayDate">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="card p-3">
                                        <h6 class="mb-3">Extended History Chart (VIP)</h6>
                                        <div class="chart-container">
                                            <canvas id="vipAnalyticsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- VIP Predictive Analytics Section -->
        <?php if ($auth->isVipOrAdmin()): ?>
            <div class="row mb-4" id="vipPredictiveSection" style="display: none;">
                <div class="col-12">
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fas fa-crystal-ball text-warning me-2"></i>VIP Predictive Analytics - AI-Powered Insights</h5>
                            <div class="d-flex gap-2">
                                <select id="predictiveDays" class="form-select form-select-sm" style="width: auto;">
                                    <option value="7">Next 7 days</option>
                                    <option value="14">Next 14 days</option>
                                    <option value="30" selected>Next 30 days</option>
                                    <option value="60">Next 60 days</option>
                                </select>
                                <button id="loadPredictiveAnalytics" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-sync"></i> Analyze
                                </button>
                            </div>
                        </div>

                        <div id="predictiveContent">
                            <!-- Predictions Overview -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1)); border: 1px solid rgba(245, 158, 11, 0.3);">
                                        <div class="metric-value" style="color: #f59e0b;" id="predictedResets">0</div>
                                        <div class="metric-label">Predicted Resets</div>
                                        <div class="small-muted" id="predictionConfidence">0% confidence</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), rgba(147, 51, 234, 0.1)); border: 1px solid rgba(168, 85, 247, 0.3);">
                                        <div class="metric-value" style="color: #a855f7;" id="predictedGrandResets">0</div>
                                        <div class="metric-label">Predicted Grand Resets</div>
                                        <div class="small-muted" id="trendDirection">Stable</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1)); border: 1px solid rgba(34, 197, 94, 0.3);">
                                        <div class="metric-value" style="color: #22c55e;" id="efficiencyScore">0</div>
                                        <div class="metric-label">Efficiency Score</div>
                                        <div class="small-muted" id="consistencyRate">0% consistency</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card stat-card p-3 text-center" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3);">
                                        <div class="metric-value" style="color: #3b82f6;" id="dailyAvgPrediction">0</div>
                                        <div class="metric-label">Daily Avg Prediction</div>
                                        <div class="small-muted">resets per day</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Patterns and Recommendations -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card p-3">
                                        <h6 class="mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i>Optimal Play Patterns</h6>
                                        <div id="bestDaysOfWeek"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card p-3">
                                        <h6 class="mb-3"><i class="fas fa-lightbulb text-warning me-2"></i>AI Recommendations</h6>
                                        <div id="aiRecommendations"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIP Activity Heatmap Section -->
            <div class="row mb-4" id="vipHeatmapSection" style="display: none;">
                <div class="col-12">
                    <div class="card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="fas fa-fire text-danger me-2"></i>Activity Heatmap - When You Play Best</h5>
                            <button id="loadHeatmapData" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-sync"></i> Load Heatmap
                            </button>
                        </div>

                        <div id="heatmapContent">
                            <div class="row">
                                <div class="col-12">
                                    <div class="card p-3">
                                        <h6 class="mb-3">Activity Intensity by Hour and Day</h6>
                                        <div id="activityHeatmap" style="height: 400px; overflow-x: auto;">
                                            <!-- Heatmap will be generated here -->
                                        </div>
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Darker colors indicate higher reset activity. Use this to identify your most productive gaming hours.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Charts -->
        <div class="row mb-4" id="individualCharts">
            <div class="col-lg-8">
                <div class="card p-3">
                    <h5 class="mb-3">Reset Progression</h5>
                    <div class="chart-container">
                        <canvas id="progressionChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card p-3">
                    <h5 class="mb-3">Hourly Reset Activity (Today)</h5>
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card p-3">
                    <h5 class="mb-3">Detailed Statistics</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped" id="statsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Start Resets</th>
                                    <th>End Resets</th>
                                    <th>Resets Gained</th>
                                    <th>Start Grand Resets</th>
                                    <th>End Grand Resets</th>
                                    <th>Grand Resets Gained</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let progressionChart = null;
        let hourlyChart = null;
        let comparisonChart = null;

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

        $(document).ready(function() {
            // Show character rankings section by default
            $('#characterRankings').show();

            // Load character rankings on page load
            loadCharacterRankings();

            $('#characterSelect').change(function() {
                const characterId = $(this).val();
                if (characterId) {
                    loadCharacterStats(characterId);
                } else {
                    hideStats();
                }
            });

            $('#periodSelect, #rangeSelect').change(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    loadCharacterStats(characterId);
                }
            });

            $('#refreshAllBtn').click(function() {
                const btn = $(this);

                // Show Cloudflare warning
                showToast('⚠️ Warning: Avoid refreshing too frequently to prevent Cloudflare verification checks that may cause 0 or unknown values.', 'warning', 'Cloudflare Protection', 6000);

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Refreshing...');

                $.post('', {
                    action: 'refresh_all',
                    batchSize: 5,
                    sleepMs: 200
                }).done(function(data) {
                    if (data.success) {
                        showToast('Characters refreshed successfully', 'success', 'Refresh Complete');
                    }
                }).always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Refresh All');
                    // Reload current character if selected
                    const characterId = $('#characterSelect').val();
                    if (characterId) {
                        loadCharacterStats(characterId);
                    }
                });
            });

            $('#compareAllBtn').click(function() {
                loadAllCharactersComparison();
            });

            $('#hideComparison').click(function() {
                hideComparison();
            });

            $('#comparisonMetric, #comparisonChartType').change(function() {
                console.log('Metric/Chart type changed:', $('#comparisonMetric').val(), $('#comparisonChartType').val());
                if ($('#comparisonSection').is(':visible')) {
                    console.log('Reloading comparison...');
                    loadAllCharactersComparison();
                }
            });

            // VIP-specific event handlers
            $('#exportDataBtn').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    exportCharacterData(characterId);
                } else {
                    showToast('Please select a character first', 'warning', 'Export Data');
                }
            });

            $('#vipAnalyticsBtn').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    $('#vipAnalyticsSection').show();
                    loadVipAnalytics(characterId);
                } else {
                    showToast('Please select a character first', 'warning', 'VIP Analytics');
                }
            });

            $('#loadVipAnalytics').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    loadVipAnalytics(characterId);
                }
            });

            $('#downloadComparisonChart').click(function() {
                downloadComparisonChart();
            });

            // Ranking filters
            $('#rankingPeriod, #rankingMetric').change(function() {
                if ($('#characterRankings').is(':visible')) {
                    loadCharacterRankings();
                }
            });

            $('#refreshRankings').click(function() {
                loadCharacterRankings();
            });

            // VIP Predictive Analytics handlers
            $('#vipPredictiveBtn').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    $('#vipPredictiveSection').show();
                    loadPredictiveAnalytics(characterId);
                } else {
                    showToast('Please select a character first', 'warning', 'Predictive Analytics');
                }
            });

            $('#loadPredictiveAnalytics').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    loadPredictiveAnalytics(characterId);
                }
            });

            // VIP Heatmap handlers
            $('#vipHeatmapBtn').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    $('#vipHeatmapSection').show();
                    loadHeatmapData(characterId);
                } else {
                    showToast('Please select a character first', 'warning', 'Activity Heatmap');
                }
            });

            $('#loadHeatmapData').click(function() {
                const characterId = $('#characterSelect').val();
                if (characterId) {
                    loadHeatmapData(characterId);
                }
            });
        });

        function loadCharacterStats(characterId) {
            const period = $('#periodSelect').val();
            const range = $('#rangeSelect').val();

            // Load main stats
            $.post('', {
                action: 'get_character_stats',
                character_id: characterId,
                period: period,
                range: range
            }, function(response) {
                if (response.success) {
                    displayStats(response.data);
                    updateProgressionChart(response.data);
                    updateStatsTable(response.data);
                }
            }, 'json');

            // Load hourly stats for today
            $.post('', {
                action: 'get_hourly_stats',
                character_id: characterId,
                date: new Date().toISOString().split('T')[0]
            }, function(response) {
                if (response.success) {
                    updateHourlyChart(response.data);
                }
            }, 'json');
        }

        function displayStats(data) {
            // Basic stats
            $('#totalResets').text(data.total_resets_gained || 0);
            $('#totalGrandResets').text(data.total_grand_resets_gained || 0);
            $('#avgResetsPerDay').text((data.avg_resets_per_day || 0).toFixed(1));
            $('#activeDays').text(data.active_days || 0);
            $('#statsOverview').show();

            // Performance stats
            if (data.best_day) {
                $('#bestDayResets').text(data.best_day.resets_gained || 0);
                $('#bestDayDate').text(data.best_day.date || '-');
            } else {
                $('#bestDayResets').text('0');
                $('#bestDayDate').text('-');
            }

            if (data.worst_day) {
                $('#worstDayResets').text(data.worst_day.resets_gained || 0);
                $('#worstDayDate').text(data.worst_day.date || '-');
            } else {
                $('#worstDayResets').text('0');
                $('#worstDayDate').text('-');
            }

            $('#improvementRate').text((data.improvement_rate || 0) + '%');
            $('#improvementDays').text((data.improvement_days || 0) + ' improvement days');
            $('#longestStreak').text(data.longest_improvement_streak || 0);
            $('#performanceStats').show();
        }

        function updateProgressionChart(data) {
            const ctx = document.getElementById('progressionChart').getContext('2d');

            if (progressionChart) {
                progressionChart.destroy();
            }

            const labels = data.timeline.map(item => item.date);
            const resets = data.timeline.map(item => item.ending_resets);
            const grandResets = data.timeline.map(item => item.ending_grand_resets);
            const resetsGained = data.timeline.map(item => item.resets_gained);
            const grandResetsGained = data.timeline.map(item => item.grand_resets_gained);

            progressionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Resets',
                            data: resets,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Grand Resets',
                            data: grandResets,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.4
                        },
                        {
                            label: 'Resets Gained',
                            data: resetsGained,
                            type: 'bar',
                            backgroundColor: 'rgba(245, 158, 11, 0.3)',
                            yAxisID: 'y2'
                        },
                        {
                            label: 'Grand Resets Gained',
                            data: grandResetsGained,
                            type: 'bar',
                            backgroundColor: 'rgba(139, 92, 246, 0.3)',
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Resets'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Grand Resets'
                            },
                            grid: {
                                drawOnChartArea: false,
                            }
                        },
                        y2: {
                            type: 'linear',
                            display: false
                        }
                    }
                }
            });
        }

        function updateHourlyChart(data) {
            const ctx = document.getElementById('hourlyChart').getContext('2d');

            if (hourlyChart) {
                hourlyChart.destroy();
            }

            const labels = data.map(item => item.hour + ':00');
            const resetsGained = data.map(item => item.resets_gained);
            const grandResetsGained = data.map(item => item.grand_resets_gained);

            hourlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Resets Gained',
                            data: resetsGained,
                            backgroundColor: 'rgba(245, 158, 11, 0.6)',
                            borderColor: '#f59e0b',
                            borderWidth: 1
                        },
                        {
                            label: 'Grand Resets Gained',
                            data: grandResetsGained,
                            backgroundColor: 'rgba(139, 92, 246, 0.6)',
                            borderColor: '#8b5cf6',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Resets Gained'
                            }
                        }
                    }
                }
            });
        }

        function updateStatsTable(data) {
            const tbody = $('#statsTable tbody');
            tbody.empty();

            data.timeline.forEach(item => {
                tbody.append(`
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.starting_resets}</td>
                        <td>${item.ending_resets}</td>
                        <td class="text-warning">+${item.resets_gained}</td>
                        <td>${item.starting_grand_resets}</td>
                        <td>${item.ending_grand_resets}</td>
                        <td class="text-info">+${item.grand_resets_gained}</td>
                    </tr>
                `);
            });
        }

        function hideStats() {
            $('#statsOverview').hide();
            $('#performanceStats').hide();
            if (progressionChart) {
                progressionChart.destroy();
                progressionChart = null;
            }
            if (hourlyChart) {
                hourlyChart.destroy();
                hourlyChart = null;
            }
            $('#statsTable tbody').empty();
        }

        function loadAllCharactersComparison() {
            const period = $('#periodSelect').val();
            const range = $('#rangeSelect').val();
            const metric = $('#comparisonMetric').val();
            const chartType = $('#comparisonChartType').val();

            console.log('Loading comparison with:', {
                period,
                range,
                metric,
                chartType
            });

            $('#compareAllBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');

            $.post('', {
                action: 'get_all_characters_comparison',
                period: period,
                days: range,
                metric: metric
            }, function(response) {
                $('#compareAllBtn').prop('disabled', false).html('<i class="fas fa-chart-bar me-1"></i>Compare All Characters');

                if (response.success) {
                    console.log('Received data for metric:', metric, 'Sample data:', response.data.characters[0]?.data.slice(-3));
                    showComparison(response.data, chartType);
                } else {
                    alert('Failed to load comparison data');
                }
            }, 'json').fail(function() {
                $('#compareAllBtn').prop('disabled', false).html('<i class="fas fa-chart-bar me-1"></i>Compare All Characters');
                alert('Request failed');
            });
        }

        function showComparison(data, chartType) {
            $('#comparisonSection').show();
            $('#overallAnalytics').show();
            $('#characterRankings').show();
            $('#individualCharts').hide();
            $('#statsOverview').hide();
            $('#performanceStats').hide();

            // Update the title to show current metric
            const metric = $('#comparisonMetric').val();
            const metricLabel = getMetricLabel(metric);
            $('#comparisonTitle').text(metricLabel);

            // Display overall analytics
            displayOverallAnalytics(data.overall_analytics);

            // Load character rankings with current filters
            loadCharacterRankings();

            const ctx = document.getElementById('comparisonChart').getContext('2d');

            if (comparisonChart) {
                comparisonChart.destroy();
                comparisonChart = null;
            }

            const labels = data.labels || [];
            const datasets = (data.characters || []).map((character, index) => {
                const color = generateColor(index, data.characters.length);
                console.log(`Creating dataset for ${character.name} with data:`, character.data.slice(-3));
                return {
                    label: character.name,
                    data: character.data,
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5
                };
            });

            comparisonChart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 10,
                                padding: 8
                            }
                        },
                        tooltip: {
                            mode: 'nearest',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: getMetricLabel($('#comparisonMetric').val())
                            }
                        }
                    }
                }
            });
        }

        function hideComparison() {
            $('#comparisonSection').hide();
            $('#overallAnalytics').hide();
            $('#characterRankings').hide();
            $('#individualCharts').show();
            $('#statsOverview').show();
            $('#performanceStats').show();

            if (comparisonChart) {
                comparisonChart.destroy();
                comparisonChart = null;
            }

            // Restore individual character view if one was selected
            const characterId = $('#characterSelect').val();
            if (characterId) {
                loadCharacterStats(characterId);
            }
        }

        function generateColor(index, total) {
            const hue = (index * 360 / total) % 360;
            const saturation = 70;
            const lightness = 50;
            return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
        }

        function getMetricLabel(metric) {
            switch (metric) {
                case 'level':
                    return 'Level';
                case 'resets':
                    return 'Resets';
                case 'grand_resets':
                    return 'Grand Resets';
                case 'resets_gained':
                    return 'Resets Gained';
                case 'grand_resets_gained':
                    return 'Grand Resets Gained';
                default:
                    return 'Value';
            }
        }

        function downloadComparisonChart() {
            if (!comparisonChart) {
                alert('No comparison chart to download');
                return;
            }

            const canvas = document.getElementById('comparisonChart');
            const url = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = url;
            a.download = 'characters-comparison.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
        }

        function displayOverallAnalytics(overallAnalytics) {
            if (!overallAnalytics) return;

            $('#overallTotalResets').text(overallAnalytics.total_resets_gained || 0);
            $('#overallTotalCharacters').text((overallAnalytics.total_characters || 0) + ' characters');
            $('#overallTotalGrandResets').text(overallAnalytics.total_grand_resets_gained || 0);
            $('#overallTotalActiveDays').text((overallAnalytics.total_active_days || 0) + ' active days');
            $('#overallAvgImprovement').text((overallAnalytics.avg_improvement_rate || 0) + '%');
            $('#overallAvgStreak').text((overallAnalytics.avg_longest_streak || 0) + ' avg streak');

            if (overallAnalytics.best_performer) {
                $('#overallBestPerformer').text(overallAnalytics.best_performer.character_name || '-');
                $('#overallBestResets').text((overallAnalytics.best_performer.total_resets_gained || 0) + ' resets');
            } else {
                $('#overallBestPerformer').text('-');
                $('#overallBestResets').text('0 resets');
            }
        }

        function loadCharacterRankings() {
            const period = $('#rankingPeriod').val();
            const metric = $('#rankingMetric').val();

            $('#refreshRankings').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');

            $.post('', {
                action: 'get_character_rankings',
                period: period,
                metric: metric
            }, function(response) {
                $('#refreshRankings').prop('disabled', false).html('<i class="fas fa-sync"></i> Refresh');

                if (response.success) {
                    displayCharacterRankings(response.data, period, metric);
                } else {
                    showToast('Failed to load rankings', 'error', 'Error');
                }
            }, 'json').fail(function() {
                $('#refreshRankings').prop('disabled', false).html('<i class="fas fa-sync"></i> Refresh');
                showToast('Request failed', 'error', 'Error');
            });
        }

        function displayCharacterRankings(data, period, metric) {
            if (!data) return;

            // Update titles based on period and metric
            const metricLabel = getMetricLabel(metric);
            const periodLabel = period.charAt(0).toUpperCase() + period.slice(1);

            $('#topPerformersTitle').text(`Top ${periodLabel} Performers`);
            $('#bestDayTitle').text(`Best ${periodLabel} Records`);
            $('#consistentTitle').text(`Most Consistent ${periodLabel}`);
            $('#improvementTitle').text(`Most Improved ${periodLabel}`);
            $('#efficientTitle').text(`Most Efficient ${periodLabel}`);

            // Top Performers (by total)
            const topPerformers = data.top_performers || [];
            const topPerformersHtml = topPerformers.map((char, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(34, 197, 94, 0.1); border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">#${index + 1}</span>
                        <span class="fw-bold">${char.character_name}</span>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success">${char.value || 0}</div>
                        <small class="text-muted">${metricLabel.toLowerCase()}</small>
                    </div>
                </div>
            `).join('');

            // Best Day Records
            const bestDayRecords = data.best_day_records || [];
            const bestDayHtml = bestDayRecords.map((char, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(59, 130, 246, 0.1); border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-2">#${index + 1}</span>
                        <div>
                            <div class="fw-bold">${char.character_name}</div>
                            <small class="text-muted">${char.date || ''}</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-primary">${char.value || 0}</div>
                        <small class="text-muted">${metricLabel.toLowerCase()}</small>
                    </div>
                </div>
            `).join('');

            // Most Consistent
            const mostConsistent = data.most_consistent || [];
            const consistentHtml = mostConsistent.map((char, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(168, 85, 247, 0.1); border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-purple me-2">#${index + 1}</span>
                        <span class="fw-bold">${char.character_name}</span>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-purple">${char.consistency_score || 0}%</div>
                        <small class="text-muted">consistency</small>
                    </div>
                </div>
            `).join('');

            // Most Improved
            const mostImproved = data.most_improved || [];
            const improvementHtml = mostImproved.map((char, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-warning me-2">#${index + 1}</span>
                        <span class="fw-bold">${char.character_name}</span>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-warning">${char.improvement_rate || 0}%</div>
                        <small class="text-muted">improvement</small>
                    </div>
                </div>
            `).join('');

            // Most Efficient
            const mostEfficient = data.most_efficient || [];
            const efficientHtml = mostEfficient.map((char, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(34, 197, 94, 0.1); border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">#${index + 1}</span>
                        <span class="fw-bold">${char.character_name}</span>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success">${char.efficiency_score || 0}</div>
                        <small class="text-muted">efficiency</small>
                    </div>
                </div>
            `).join('');

            $('#topPerformersList').html(topPerformersHtml);
            $('#bestDayList').html(bestDayHtml);
            $('#consistentList').html(consistentHtml);
            $('#improvementList').html(improvementHtml);
            $('#efficientList').html(efficientHtml);
        }

        // VIP-specific functions
        function loadVipAnalytics(characterId) {
            const period = $('#vipPeriodSelect').val();

            $('#loadVipAnalytics').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');

            $.post('', {
                action: 'get_vip_analytics',
                character_id: characterId,
                period: period
            }, function(response) {
                $('#loadVipAnalytics').prop('disabled', false).html('<i class="fas fa-sync"></i> Load VIP Data');

                if (response.success) {
                    displayVipAnalytics(response.data);
                } else {
                    showToast(response.message || 'Failed to load VIP analytics', 'error', 'VIP Analytics');
                }
            }, 'json').fail(function() {
                $('#loadVipAnalytics').prop('disabled', false).html('<i class="fas fa-sync"></i> Load VIP Data');
                showToast('Request failed', 'error', 'Error');
            });
        }

        function displayVipAnalytics(data) {
            if (!data) return;

            // Update VIP metrics
            $('#vipTotalResets').text(data.total_resets_gained || 0);
            $('#vipDaysAnalyzed').text(data.days_analyzed + ' days analyzed');
            $('#vipAvgResets').text(data.avg_resets_per_day || 0);
            $('#vipActiveDays').text(data.active_days + ' active days');

            // Update trend information
            const trends = data.trends || {};
            $('#vipTrend').text(trends.trend || 'Stable');
            $('#vipImprovementRate').text((trends.improvement_rate || 0) + '% improvement');

            // Update best day
            if (data.best_day) {
                $('#vipBestDay').text(data.best_day.resets_gained || 0);
                $('#vipBestDayDate').text(data.best_day.date || '-');
            }

            // Update VIP chart
            updateVipChart(data.daily_progress || []);
        }

        function updateVipChart(dailyProgress) {
            const ctx = document.getElementById('vipAnalyticsChart');
            if (!ctx) return;

            // Destroy existing chart if it exists
            if (window.vipChart) {
                window.vipChart.destroy();
            }

            const labels = dailyProgress.map(item => item.date);
            const resetsGained = dailyProgress.map(item => item.resets_gained);

            window.vipChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Resets Gained (VIP Extended)',
                        data: resetsGained,
                        borderColor: '#a855f7',
                        backgroundColor: 'rgba(168, 85, 247, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#9aa4b2'
                            },
                            grid: {
                                color: 'rgba(156, 163, 175, 0.1)'
                            }
                        },
                        y: {
                            ticks: {
                                color: '#9aa4b2'
                            },
                            grid: {
                                color: 'rgba(156, 163, 175, 0.1)'
                            }
                        }
                    }
                }
            });
        }

        function exportCharacterData(characterId) {
            // Create a form to submit the export request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_character_data';

            const characterInput = document.createElement('input');
            characterInput.type = 'hidden';
            characterInput.name = 'character_id';
            characterInput.value = characterId;

            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = 'csv';

            form.appendChild(actionInput);
            form.appendChild(characterInput);
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // VIP Predictive Analytics Functions
        function loadPredictiveAnalytics(characterId) {
            const days = $('#predictiveDays').val();

            $('#loadPredictiveAnalytics').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Analyzing...');

            $.post('', {
                action: 'get_vip_predictive_analytics',
                character_id: characterId,
                days: days
            }, function(response) {
                $('#loadPredictiveAnalytics').prop('disabled', false).html('<i class="fas fa-sync"></i> Analyze');

                console.log('VIP Analytics Response:', response);

                if (response && response.success) {
                    displayPredictiveAnalytics(response.data);
                } else {
                    const errorMsg = response && response.message ? response.message : 'Failed to load predictive analytics';
                    showToast(errorMsg, 'error', 'Predictive Analytics');
                    console.error('VIP Analytics Error:', response);
                }
            }, 'json').fail(function(xhr, status, error) {
                $('#loadPredictiveAnalytics').prop('disabled', false).html('<i class="fas fa-sync"></i> Analyze');
                console.error('AJAX Request Failed:', {
                    xhr,
                    status,
                    error
                });
                console.error('Response Text:', xhr.responseText);

                let errorMessage = 'Request failed';
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                    }
                }

                showToast(errorMessage, 'error', 'Connection Error');
            });
        }

        function displayPredictiveAnalytics(data) {
            if (!data) return;

            // Update predictions
            $('#predictedResets').text(data.predictions.predicted_resets || 0);
            $('#predictionConfidence').text(Math.round(data.predictions.confidence || 0) + '% confidence');
            $('#predictedGrandResets').text(data.predictions.predicted_grand_resets || 0);
            $('#trendDirection').text(data.predictions.trend_direction || 'Stable');
            $('#efficiencyScore').text(data.efficiency.efficiency_score || 0);
            $('#consistencyRate').text((data.efficiency.consistency_rate || 0) + '% consistency');
            $('#dailyAvgPrediction').text(data.predictions.daily_avg_prediction || 0);

            // Display best days of week
            const bestDaysOfWeek = data.patterns.best_days_of_week || [];
            let bestDaysHtml = '';

            if (bestDaysOfWeek.length > 0) {
                bestDaysHtml = bestDaysOfWeek.slice(0, 5).map((day, index) => `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: rgba(59, 130, 246, 0.1); border-radius: 8px;">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2">#${index + 1}</span>
                            <span class="fw-bold">${day.day}</span>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-primary">${day.avg_resets}</div>
                            <small class="text-muted">avg resets</small>
                        </div>
                    </div>
                `).join('');
            } else {
                bestDaysHtml = '<div class="alert alert-info">Analyzing patterns... More data needed for detailed day-of-week analysis.</div>';
            }
            $('#bestDaysOfWeek').html(bestDaysHtml);

            // Display AI recommendations
            const recommendationsHtml = data.recommendations.map(rec => `
                <div class="alert alert-${rec.priority === 'high' ? 'warning' : rec.priority === 'medium' ? 'info' : 'light'} mb-2">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-${rec.icon} me-2 mt-1"></i>
                        <div>
                            <strong>${rec.title}</strong>
                            <p class="mb-0 small">${rec.message}</p>
                        </div>
                    </div>
                </div>
            `).join('');
            $('#aiRecommendations').html(recommendationsHtml);
        }

        // VIP Heatmap Functions
        function loadHeatmapData(characterId) {
            $('#loadHeatmapData').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Loading...');

            $.post('', {
                action: 'get_vip_heatmap',
                character_id: characterId
            }, function(response) {
                $('#loadHeatmapData').prop('disabled', false).html('<i class="fas fa-sync"></i> Load Heatmap');

                if (response.success) {
                    displayHeatmap(response.data.heatmap);
                } else {
                    showToast(response.message || 'Failed to load heatmap data', 'error', 'Activity Heatmap');
                }
            }, 'json').fail(function() {
                $('#loadHeatmapData').prop('disabled', false).html('<i class="fas fa-sync"></i> Load Heatmap');
                showToast('Request failed', 'error', 'Error');
            });
        }

        function displayHeatmap(heatmapData) {
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const hours = Array.from({
                length: 24
            }, (_, i) => i.toString().padStart(2, '0') + ':00');

            // Find max value for color scaling
            let maxValue = 0;
            for (let hour = 0; hour < 24; hour++) {
                for (let day = 0; day < 7; day++) {
                    maxValue = Math.max(maxValue, heatmapData[hour][day]);
                }
            }

            let heatmapHtml = `
                <div style="display: grid; grid-template-columns: 60px repeat(7, 1fr); gap: 2px; font-size: 12px;">
                    <div></div>
                    ${days.map(day => `<div class="text-center fw-bold">${day}</div>`).join('')}
            `;

            for (let hour = 0; hour < 24; hour++) {
                heatmapHtml += `<div class="text-end pe-2">${hours[hour]}</div>`;
                for (let day = 0; day < 7; day++) {
                    const value = heatmapData[hour][day];
                    const intensity = maxValue > 0 ? value / maxValue : 0;
                    const color = `rgba(239, 68, 68, ${intensity})`;
                    const textColor = intensity > 0.5 ? 'white' : 'black';

                    heatmapHtml += `
                        <div class="text-center p-1" 
                             style="background-color: ${color}; color: ${textColor}; border-radius: 4px; min-height: 25px; display: flex; align-items: center; justify-content: center;"
                             title="${days[day]} ${hours[hour]}: ${value} resets">
                            ${value > 0 ? value : ''}
                        </div>
                    `;
                }
            }

            heatmapHtml += '</div>';
            $('#activityHeatmap').html(heatmapHtml);
        }
    </script>
</body>

</html>