<?php
require_once 'config.php';
require_once __DIR__ . '/functions.php';
require __DIR__ . '/vendor/autoload.php';

/**
 * Initialize analytics tables
 */
function initAnalyticsTables()
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        $sql = file_get_contents(__DIR__ . '/analytics-schema.sql');
        $statements = explode(';', $sql);

        foreach ($statements as $statement) {
            $stmt = trim($statement);
            if ($stmt) $pdo->exec($stmt);
        }
        return true;
    } catch (Exception $e) {
        logError("Error initializing analytics tables: " . $e->getMessage());
        return false;
    }
}

/**
 * Record character history
 */
function recordCharacterHistory($characterId, $level, $resets, $grandResets, $location, $status)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Get current user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $now = new DateTime();
        $stmt = $pdo->prepare("
            INSERT INTO character_history 
            (user_id, character_id, level, resets, grand_resets, location, status, day_of_month, day_of_week, status_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $characterId,
            $level,
            $resets,
            $grandResets,
            $location,
            $status,
            (int)$now->format('j'),
            (int)$now->format('N'),
            $now->format('Y-m-d H:i:s')
        ]);

        checkLevelMilestones($characterId, $level, $resets, $grandResets);
        updateDailyProgress($characterId, $level, $resets, $grandResets);
        recordHourlyAnalytics($characterId, $level, $resets, $grandResets);
        updatePeriodAnalytics($characterId);

        return true;
    } catch (Exception $e) {
        logError("Error recording character history: " . $e->getMessage());
        return false;
    }
}

/**
 * Check level, reset, and grand reset milestones
 */
function checkLevelMilestones($characterId, $currentLevel, $currentResets, $currentGrandResets)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Get current user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }
        $stmt = $pdo->prepare("
            SELECT level, resets, grand_resets FROM character_history 
            WHERE character_id = ? 
            ORDER BY status_timestamp DESC 
            LIMIT 2
        ");
        $stmt->execute([$characterId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($history) < 2) return true;

        $previous = $history[1];

        // Level milestone (every 100 levels)
        if (floor($currentLevel / 100) > floor($previous['level'] / 100)) {
            $stmt = $pdo->prepare("
                INSERT INTO level_milestones (user_id, character_id, milestone_type, old_value, new_value)
                VALUES (?, ?, 'level', ?, ?)
            ");
            $stmt->execute([$userId, $characterId, $previous['level'], $currentLevel]);
        }

        // Reset milestone
        if ($currentResets > $previous['resets']) {
            $stmt = $pdo->prepare("
                INSERT INTO level_milestones (user_id, character_id, milestone_type, old_value, new_value)
                VALUES (?, ?, 'reset', ?, ?)
            ");
            $stmt->execute([$userId, $characterId, $previous['resets'], $currentResets]);
        }

        // Grand reset milestone
        if ($currentGrandResets > $previous['grand_resets']) {
            $stmt = $pdo->prepare("
                INSERT INTO level_milestones (user_id, character_id, milestone_type, old_value, new_value)
                VALUES (?, ?, 'grand_reset', ?, ?)
            ");
            $stmt->execute([$userId, $characterId, $previous['grand_resets'], $currentGrandResets]);
        }

        return true;
    } catch (Exception $e) {
        logError("Error checking milestones: " . $e->getMessage());
        return false;
    }
}

/**
 * Record hourly analytics
 */
function recordHourlyAnalytics($characterId, $level, $resets, $grandResets)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Get current user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $now = new DateTime();
        $date = $now->format('Y-m-d');
        $hour = (int)$now->format('G');

        $stmt = $pdo->prepare("
            SELECT * FROM hourly_analytics 
            WHERE character_id = ? AND date = ? AND hour = ?
        ");
        $stmt->execute([$characterId, $date, $hour]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $levelsGained = $level - $existing['level_start'];
            $resetsGained = $resets - $existing['resets_start'];
            $grandResetsGained = $grandResets - $existing['grand_resets_start'];

            $stmt = $pdo->prepare("
                UPDATE hourly_analytics
                SET level_end = ?, resets_end = ?, grand_resets_end = ?,
                    levels_gained = ?, resets_gained = ?, grand_resets_gained = ?,
                    status_changes = status_changes + 1
                WHERE character_id = ? AND date = ? AND hour = ?
            ");
            $stmt->execute([
                $level,
                $resets,
                $grandResets,
                $levelsGained,
                $resetsGained,
                $grandResetsGained,
                $characterId,
                $date,
                $hour
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO hourly_analytics
                (user_id, character_id, date, hour, level_start, level_end, resets_start, resets_end, grand_resets_start, grand_resets_end, levels_gained, resets_gained, grand_resets_gained, status_changes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 1)
            ");
            $stmt->execute([$userId, $characterId, $date, $hour, $level, $level, $resets, $resets, $grandResets, $grandResets]);
        }

        return true;
    } catch (Exception $e) {
        logError("Error recording hourly analytics: " . $e->getMessage());
        return false;
    }
}

/**
 * Update daily progress
 */
function updateDailyProgress($characterId, $level, $resets, $grandResets)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Get current user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $today = date('Y-m-d');

        // Fetch existing row (if any)
        $stmt = $pdo->prepare("SELECT * FROM daily_progress WHERE character_id = ? AND date = ?");
        $stmt->execute([$characterId, $today]);
        $daily = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($daily) {
            // Recalculate using stored starting_level to avoid drift
            $starting_level = (int)$daily['starting_level'];
            $starting_resets = (int)$daily['starting_resets'];
            $starting_grand = (int)$daily['starting_grand_resets'];

            $ending_level = (int)$level;
            $ending_resets = (int)$resets;
            $ending_grand = (int)$grandResets;

            $resets_gained = $ending_resets - $starting_resets;
            $grand_gained = $ending_grand - $starting_grand;

            // Focus on reset tracking - no level calculations needed
            $levels_gained = 0; // Not tracking levels anymore

            // Log reset activity for monitoring
            if ($resets_gained > 0) {
                error_log("[MU Tracker] Character {$characterId} gained {$resets_gained} resets on {$today}");
            }

            $stmt = $pdo->prepare("
                UPDATE daily_progress
                SET user_id = ?, ending_level = ?, ending_resets = ?, ending_grand_resets = ?,
                    levels_gained = ?, resets_gained = ?, grand_resets_gained = ?
                WHERE character_id = ? AND date = ?
            ");
            $stmt->execute([
                $userId,
                $ending_level,
                $ending_resets,
                $ending_grand,
                $levels_gained,
                $resets_gained,
                $grand_gained,
                $characterId,
                $today
            ]);
        } else {
            // Insert new day — starting == ending at insert time (will be updated later in the day)
            $starting_level = (int)$level;
            $starting_resets = (int)$resets;
            $starting_grand = (int)$grandResets;

            $stmt = $pdo->prepare("
                INSERT INTO daily_progress
                (user_id, character_id, date, starting_level, ending_level, starting_resets, ending_resets, starting_grand_resets, ending_grand_resets, levels_gained, resets_gained, grand_resets_gained)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)
                ON DUPLICATE KEY UPDATE
                    ending_level = VALUES(ending_level),
                    ending_resets = VALUES(ending_resets),
                    ending_grand_resets = VALUES(ending_grand_resets)
            ");
            $stmt->execute([
                $userId,
                $characterId,
                $today,
                $starting_level,
                $starting_level,
                $starting_resets,
                $starting_resets,
                $starting_grand,
                $starting_grand
            ]);

            // Log the creation of new daily progress record
            error_log("[MU Tracker] Created new daily progress for character {$characterId} on {$today} with starting values: level={$starting_level}, resets={$starting_resets}");
        }

        return true;
    } catch (Exception $e) {
        logError("Error updating daily progress (hardened): " . $e->getMessage());
        return false;
    }
}

/**
 * Update weekly/monthly analytics
 */
function updatePeriodAnalytics($characterId)
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        // Get current user ID from session
        $userId = null;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $now = new DateTime();
        $year = (int)$now->format('Y');
        $week = (int)$now->format('W');
        $month = (int)$now->format('n');

        // Weekly
        $stmt = $pdo->prepare("
            INSERT INTO period_analytics (user_id, character_id, period_type, year, period_number, levels_gained, resets_gained, grand_resets_gained, active_days)
            SELECT ?, ?, 'week', ?, ?, 
                   COALESCE(SUM(levels_gained),0),
                   COALESCE(SUM(resets_gained),0),
                   COALESCE(SUM(grand_resets_gained),0),
                   COUNT(DISTINCT date)
            FROM daily_progress 
            WHERE character_id = ? AND YEARWEEK(date) = YEARWEEK(NOW())
            ON DUPLICATE KEY UPDATE
                levels_gained = VALUES(levels_gained),
                resets_gained = VALUES(resets_gained),
                grand_resets_gained = VALUES(grand_resets_gained),
                active_days = VALUES(active_days),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$userId, $characterId, $year, $week, $characterId]);

        // Monthly
        $stmt = $pdo->prepare("
            INSERT INTO period_analytics (user_id, character_id, period_type, year, period_number, levels_gained, resets_gained, grand_resets_gained, active_days)
            SELECT ?, ?, 'month', ?, ?, 
                   COALESCE(SUM(levels_gained),0),
                   COALESCE(SUM(resets_gained),0),
                   COALESCE(SUM(grand_resets_gained),0),
                   COUNT(DISTINCT date)
            FROM daily_progress 
            WHERE character_id = ? AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())
            ON DUPLICATE KEY UPDATE
                levels_gained = VALUES(levels_gained),
                resets_gained = VALUES(resets_gained),
                grand_resets_gained = VALUES(grand_resets_gained),
                active_days = VALUES(active_days),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$userId, $characterId, $year, $month, $characterId]);

        return true;
    } catch (Exception $e) {
        logError("Error updating period analytics: " . $e->getMessage());
        return false;
    }
}

/**
 * Update character data and record analytics
 */
function updateCharacterDataWithAnalytics($characterId, $url)
{
    $pdo = getDatabase();
    if (!$pdo) {
        return false;
    }

    $data = scrapeCharacterData($url);
    if (!$data) {
        return false;
    }

    try {
        // Determine status_to_save: only overwrite DB when scraper gives definitive state
        $statusFromScrape = $data['status'] ?? 'Unknown';
        $statusToSave = null;

        if (in_array($statusFromScrape, ['Online', 'Offline', 'Error'], true)) {
            $statusToSave = $statusFromScrape;
        } else {
            // preserve current status in DB when scrape is Unknown
            $stmt = $pdo->prepare("SELECT status FROM characters WHERE id = ?");
            $stmt->execute([$characterId]);
            $existingStatus = $stmt->fetchColumn();
            // if DB has something, keep it; otherwise set Unknown
            $statusToSave = $existingStatus ?: 'Unknown';
        }

        $stmt = $pdo->prepare("
            UPDATE characters SET
                status = :status,
                level = :level,
                resets = :resets,
                grand_resets = :grand_resets,
                class = :class,
                guild = :guild,
                gens = :gens,
                location = :location,
                last_updated = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':status'       => $statusToSave,
            ':level'        => $data['level'],
            ':resets'       => $data['resets'],
            ':grand_resets' => $data['grand_resets'] ?? 0,
            ':class'        => $data['class'] ?? '',
            ':guild'        => $data['guild'] ?? '',
            ':gens'         => $data['gens'] ?? '',
            ':location'     => $data['location'] ?? '',
            ':id'           => $characterId,
        ]);

        // Record analytics — use the status we actually saved to DB
        recordCharacterHistory(
            $characterId,
            $data['level'],
            $data['resets'],
            $data['grand_resets'] ?? 0,
            $data['location'] ?? '',
            $statusToSave
        );

        // Optionally log status changes for debugging
        if (($statusFromScrape !== $statusToSave) && $statusFromScrape === 'Unknown') {
            error_log("[MU Tracker] Status for id={$characterId} preserved as '{$statusToSave}' because scraper returned Unknown for URL {$url}");
        } elseif ($statusFromScrape !== $statusToSave) {
            error_log("[MU Tracker] Status for id={$characterId} updated to '{$statusToSave}' (scraper returned '{$statusFromScrape}') for URL {$url}");
        }

        return true;
    } catch (PDOException $e) {
        logError("Error updating character data: " . $e->getMessage());
        return false;
    }
}

/**
 * Return analytics for a single character (used by AJAX)
 */
/**
 * Return analytics for a single character (used by AJAX)
 * Returns array with keys: character, stats, milestones, timeline
 */
function getCharacterAnalytics(int $characterId, int $days = 30): array
{
    $pdo = getDatabase();
    if (!$pdo) return ['error' => 'DB connection failed'];

    try {
        // Character info
        $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Normalize days
        $days = max(1, (int)$days);

        // Fetch daily progress rows within range (ascending date)
        $stmt = $pdo->prepare("
            SELECT date, starting_level, ending_level, levels_gained,
                   starting_resets, ending_resets, resets_gained,
                   starting_grand_resets, ending_grand_resets, grand_resets_gained
            FROM daily_progress
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId, $days]);
        $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build a date-indexed map for quick lookup
        $byDate = [];
        foreach ($daily as $r) {
            $d = $r['date'];
            $byDate[$d] = [
                'date' => $d,
                'starting_level' => (int)($r['starting_level'] ?? 0),
                'ending_level' => (int)($r['ending_level'] ?? 0),
                'levels_gained' => (int)($r['levels_gained'] ?? 0),
                'starting_resets' => (int)($r['starting_resets'] ?? 0),
                'ending_resets' => (int)($r['ending_resets'] ?? 0),
                'resets_gained' => (int)($r['resets_gained'] ?? 0),
                'starting_grand_resets' => (int)($r['starting_grand_resets'] ?? 0),
                'ending_grand_resets' => (int)($r['ending_grand_resets'] ?? 0),
                'grand_resets_gained' => (int)($r['grand_resets_gained'] ?? 0)
            ];
        }

        // Create full timeline for the requested range (ensure consecutive dates)
        $timeline = [];
        $today = new DateTimeImmutable();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
            if (isset($byDate[$date])) {
                $timeline[] = $byDate[$date];
            } else {
                // If missing, attempt to derive starting/ending values from the nearest previous available day
                // We'll look backwards for the last known ending_level / ending_resets to carry forward
                $starting_level = 0;
                $ending_level = 0;
                $starting_resets = 0;
                $ending_resets = 0;
                $starting_grand_resets = 0;
                $ending_grand_resets = 0;

                // find last known before this date (search daily array)
                $lastKnown = null;
                foreach (array_reverse($daily) as $dd) {
                    if ($dd['date'] < $date) {
                        $lastKnown = $dd;
                        break;
                    }
                }
                if ($lastKnown) {
                    $starting_level = (int)$lastKnown['ending_level'];
                    $ending_level = (int)$lastKnown['ending_level'];
                    $starting_resets = (int)$lastKnown['ending_resets'];
                    $ending_resets = (int)$lastKnown['ending_resets'];
                    $starting_grand_resets = (int)$lastKnown['ending_grand_resets'];
                    $ending_grand_resets = (int)$lastKnown['ending_grand_resets'];
                } else {
                    // fallback to current character values if present
                    $starting_level = $ending_level = (int)($character['level'] ?? 0);
                    $starting_resets = $ending_resets = (int)($character['resets'] ?? 0);
                    $starting_grand_resets = $ending_grand_resets = (int)($character['grand_resets'] ?? 0);
                }

                $timeline[] = [
                    'date' => $date,
                    'starting_level' => $starting_level,
                    'ending_level' => $ending_level,
                    'levels_gained' => 0,
                    'starting_resets' => $starting_resets,
                    'ending_resets' => $ending_resets,
                    'resets_gained' => 0,
                    'starting_grand_resets' => $starting_grand_resets,
                    'ending_grand_resets' => $ending_grand_resets,
                    'grand_resets_gained' => 0
                ];
            }
        }

        // Compute aggregated stats for the range - focus on resets
        $totalResets = array_sum(array_map(fn($r) => (int)$r['resets_gained'], $timeline));
        $totalGrand = array_sum(array_map(fn($r) => (int)$r['grand_resets_gained'], $timeline));
        $activeDays = count(array_filter($timeline, fn($r) => ($r['resets_gained'] > 0 || $r['grand_resets_gained'] > 0)));

        $avgResetsPerDay = $activeDays > 0 ? round($totalResets / $activeDays, 2) : 0;

        // Best day (highest resets_gained) from the timeline
        $bestDay = null;
        $worstDay = null;
        $totalDays = count($timeline);
        $improvementDays = 0;
        $declineDays = 0;
        
        foreach ($timeline as $index => $row) {
            // Best day
            if ($bestDay === null || $row['resets_gained'] > $bestDay['resets_gained']) {
                $bestDay = $row;
            }
            
            // Worst day (lowest resets_gained, but not negative)
            if ($worstDay === null || ($row['resets_gained'] < $worstDay['resets_gained'] && $row['resets_gained'] >= 0)) {
                $worstDay = $row;
            }
            
            // Track improvement/decline trends
            if ($index > 0) {
                $prevRow = $timeline[$index - 1];
                if ($row['resets_gained'] > $prevRow['resets_gained']) {
                    $improvementDays++;
                } elseif ($row['resets_gained'] < $prevRow['resets_gained']) {
                    $declineDays++;
                }
            }
        }
        
        // Calculate improvement rate
        $improvementRate = $totalDays > 1 ? round(($improvementDays / ($totalDays - 1)) * 100, 1) : 0;
        
        // Find longest streak of improvements
        $longestImprovementStreak = 0;
        $currentStreak = 0;
        foreach ($timeline as $index => $row) {
            if ($index > 0) {
                $prevRow = $timeline[$index - 1];
                if ($row['resets_gained'] > $prevRow['resets_gained']) {
                    $currentStreak++;
                    $longestImprovementStreak = max($longestImprovementStreak, $currentStreak);
                } else {
                    $currentStreak = 0;
                }
            }
        }

        // Recent milestones
        $stmt = $pdo->prepare("SELECT * FROM level_milestones WHERE character_id = ? ORDER BY achieved_at DESC LIMIT 10");
        $stmt->execute([$characterId]);
        $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'character' => $character,
            'stats' => [
                'total_resets_gained' => (int)$totalResets,
                'total_grand_resets_gained' => (int)$totalGrand,
                'avg_resets_per_day' => $avgResetsPerDay,
                'active_days' => (int)$activeDays,
                'best_day' => $bestDay,
                'worst_day' => $worstDay,
                'improvement_rate' => $improvementRate,
                'improvement_days' => $improvementDays,
                'decline_days' => $declineDays,
                'longest_improvement_streak' => $longestImprovementStreak,
                'total_days' => $totalDays
            ],
            'milestones' => $milestones,
            'timeline' => $timeline
        ];
    } catch (Exception $e) {
        logError('[MU Tracker] getCharacterAnalytics error: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}


/**
 * Return analytics summary for all characters (used by Overview)
 */
function getAllCharactersAnalytics(int $days = 7): array
{
    $pdo = getDatabase();
    if (!$pdo) return [];

    try {
        $days = (int)$days;
        $sql = "
            SELECT c.id, c.name, c.level, c.resets,
                   COALESCE(SUM(dp.resets_gained), 0) AS resets_gained,
                   COALESCE(SUM(dp.grand_resets_gained), 0) AS grand_resets_gained,
                   COUNT(DISTINCT dp.date) AS active_days
            FROM characters c
            LEFT JOIN daily_progress dp
              ON dp.character_id = c.id
              AND dp.date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
            GROUP BY c.id, c.name, c.level, c.resets
            ORDER BY resets_gained DESC, c.name ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logError('[MU Tracker] getAllCharactersAnalytics error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get character statistics by period (day/week/month)
 */
function getCharacterStatsByPeriod($characterId, $period = 'day', $days = 30)
{
    $pdo = getDatabase();
    if (!$pdo) return ['error' => 'DB connection failed'];

    try {
        $character = getCharacterById($characterId);
        if (!$character) {
            return ['error' => 'Character not found'];
        }

        // Get daily progress data
        $stmt = $pdo->prepare("
            SELECT date, starting_level, ending_level, levels_gained,
                   starting_resets, ending_resets, resets_gained,
                   starting_grand_resets, ending_grand_resets, grand_resets_gained
            FROM daily_progress
            WHERE character_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$characterId, $days]);
        $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggregate by period
        $aggregated = [];
        $currentPeriod = null;
        $periodData = null;

        foreach ($dailyData as $row) {
            $date = new DateTime($row['date']);
            $periodKey = '';

            switch ($period) {
                case 'week':
                    $periodKey = $date->format('Y-W');
                    break;
                case 'month':
                    $periodKey = $date->format('Y-m');
                    break;
                default: // day
                    $periodKey = $date->format('Y-m-d');
                    break;
            }

            if ($currentPeriod !== $periodKey) {
                if ($currentPeriod !== null) {
                    $aggregated[] = $periodData;
                }
                $currentPeriod = $periodKey;
                $periodData = [
                    'period' => $periodKey,
                    'date' => $row['date'],
                    'starting_level' => $row['starting_level'],
                    'ending_level' => $row['ending_level'],
                    'levels_gained' => $row['levels_gained'],
                    'starting_resets' => $row['starting_resets'],
                    'ending_resets' => $row['ending_resets'],
                    'resets_gained' => $row['resets_gained'],
                    'starting_grand_resets' => $row['starting_grand_resets'],
                    'ending_grand_resets' => $row['ending_grand_resets'],
                    'grand_resets_gained' => $row['grand_resets_gained']
                ];
            } else {
                // Aggregate with existing period data
                $periodData['ending_level'] = $row['ending_level'];
                $periodData['levels_gained'] += $row['levels_gained'];
                $periodData['ending_resets'] = $row['ending_resets'];
                $periodData['resets_gained'] += $row['resets_gained'];
                $periodData['ending_grand_resets'] = $row['ending_grand_resets'];
                $periodData['grand_resets_gained'] += $row['grand_resets_gained'];
            }
        }

        if ($currentPeriod !== null) {
            $aggregated[] = $periodData;
        }

        // Calculate totals - focus on resets
        $totalResets = array_sum(array_column($aggregated, 'resets_gained'));
        $totalGrandResets = array_sum(array_column($aggregated, 'grand_resets_gained'));
        $activeDays = count(array_filter($aggregated, function ($row) {
            return $row['resets_gained'] > 0 || $row['grand_resets_gained'] > 0;
        }));
        $avgResetsPerDay = $activeDays > 0 ? $totalResets / $activeDays : 0;

        return [
            'character' => $character,
            'timeline' => $aggregated,
            'total_resets_gained' => $totalResets,
            'total_grand_resets_gained' => $totalGrandResets,
            'avg_resets_per_day' => $avgResetsPerDay,
            'active_days' => $activeDays
        ];
    } catch (Exception $e) {
        logError("Error getting character stats by period: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get hourly statistics for a specific date
 */
function getHourlyStats($characterId, $date)
{
    $pdo = getDatabase();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->prepare("
            SELECT hour, level_start, level_end, levels_gained,
                   resets_start, resets_end, resets_gained,
                   grand_resets_start, grand_resets_end, grand_resets_gained
            FROM hourly_analytics
            WHERE character_id = ? AND date = ?
            ORDER BY hour ASC
        ");
        $stmt->execute([$characterId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logError("Error getting hourly stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all characters comparison data with analytics
 */
function getAllCharactersComparison($period = 'day', $days = 30, $metric = 'level')
{
    $pdo = getDatabase();
    if (!$pdo) return ['error' => 'DB connection failed'];

    try {
        $characters = getAllCharacters();
        if (empty($characters)) {
            return ['labels' => [], 'characters' => [], 'analytics' => []];
        }

        // Get date range
        $endDate = new DateTime();
        $startDate = clone $endDate;
        $startDate->sub(new DateInterval("P{$days}D"));

        // Generate labels based on period
        $labels = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            switch ($period) {
                case 'week':
                    $labels[] = $currentDate->format('Y-W');
                    $currentDate->add(new DateInterval('P1W'));
                    break;
                case 'month':
                    $labels[] = $currentDate->format('Y-m');
                    $currentDate->add(new DateInterval('P1M'));
                    break;
                default: // day
                    $labels[] = $currentDate->format('Y-m-d');
                    $currentDate->add(new DateInterval('P1D'));
                    break;
            }
        }

        $characterData = [];
        $allAnalytics = [];

        foreach ($characters as $character) {
            $data = getCharacterStatsByPeriod($character['id'], $period, $days);

            if (isset($data['error'])) {
                continue;
            }

            $timeline = $data['timeline'] ?? [];
            $characterValues = [];

            // Create a map of period -> value
            $periodMap = [];
            foreach ($timeline as $row) {
                $periodKey = $row['period'] ?? $row['date'];
                $value = 0;

                switch ($metric) {
                    case 'level':
                        $value = (int)$row['ending_level'];
                        break;
                    case 'resets':
                        $value = (int)$row['ending_resets'];
                        break;
                    case 'grand_resets':
                        $value = (int)$row['ending_grand_resets'];
                        break;
                    case 'resets_gained':
                        $value = (int)$row['resets_gained'];
                        break;
                    case 'grand_resets_gained':
                        $value = (int)$row['grand_resets_gained'];
                        break;
                }

                $periodMap[$periodKey] = $value;
            }

            // Fill in values for all labels
            foreach ($labels as $label) {
                $characterValues[] = $periodMap[$label] ?? null;
            }

            $characterData[] = [
                'id' => $character['id'],
                'name' => $character['name'],
                'data' => $characterValues
            ];

            // Collect analytics for this character
            $allAnalytics[] = [
                'character_id' => $character['id'],
                'character_name' => $character['name'],
                'total_resets_gained' => $data['total_resets_gained'] ?? 0,
                'total_grand_resets_gained' => $data['total_grand_resets_gained'] ?? 0,
                'avg_resets_per_day' => $data['avg_resets_per_day'] ?? 0,
                'active_days' => $data['active_days'] ?? 0,
                'improvement_rate' => $data['improvement_rate'] ?? 0,
                'longest_improvement_streak' => $data['longest_improvement_streak'] ?? 0
            ];
        }

        // Calculate overall analytics
        $overallAnalytics = calculateOverallAnalytics($allAnalytics);

        return [
            'labels' => $labels,
            'characters' => $characterData,
            'analytics' => $allAnalytics,
            'overall_analytics' => $overallAnalytics,
            'metric' => $metric,
            'period' => $period
        ];
    } catch (Exception $e) {
        logError("Error getting all characters comparison: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Calculate overall analytics for all characters
 */
function calculateOverallAnalytics($allAnalytics)
{
    if (empty($allAnalytics)) {
        return [];
    }

    $totalCharacters = count($allAnalytics);
    $totalResets = array_sum(array_column($allAnalytics, 'total_resets_gained'));
    $totalGrandResets = array_sum(array_column($allAnalytics, 'total_grand_resets_gained'));
    $totalActiveDays = array_sum(array_column($allAnalytics, 'active_days'));
    $avgImprovementRate = array_sum(array_column($allAnalytics, 'improvement_rate')) / $totalCharacters;
    $avgLongestStreak = array_sum(array_column($allAnalytics, 'longest_improvement_streak')) / $totalCharacters;

    // Find best and worst performers
    $bestPerformer = null;
    $worstPerformer = null;
    $mostActive = null;
    $mostImproved = null;

    foreach ($allAnalytics as $analytics) {
        // Best performer (highest total resets)
        if ($bestPerformer === null || $analytics['total_resets_gained'] > $bestPerformer['total_resets_gained']) {
            $bestPerformer = $analytics;
        }

        // Worst performer (lowest total resets, but not zero)
        if ($worstPerformer === null || ($analytics['total_resets_gained'] < $worstPerformer['total_resets_gained'] && $analytics['total_resets_gained'] > 0)) {
            $worstPerformer = $analytics;
        }

        // Most active (highest active days)
        if ($mostActive === null || $analytics['active_days'] > $mostActive['active_days']) {
            $mostActive = $analytics;
        }

        // Most improved (highest improvement rate)
        if ($mostImproved === null || $analytics['improvement_rate'] > $mostImproved['improvement_rate']) {
            $mostImproved = $analytics;
        }
    }

    return [
        'total_characters' => $totalCharacters,
        'total_resets_gained' => $totalResets,
        'total_grand_resets_gained' => $totalGrandResets,
        'total_active_days' => $totalActiveDays,
        'avg_improvement_rate' => round($avgImprovementRate, 1),
        'avg_longest_streak' => round($avgLongestStreak, 1),
        'best_performer' => $bestPerformer,
        'worst_performer' => $worstPerformer,
        'most_active' => $mostActive,
        'most_improved' => $mostImproved
    ];
}

/**
 * Update all characters in batches to avoid timeouts (used by AJAX refresh_all)
 * @param int $batchSize number of characters per mini-batch
 * @param int $sleepMs delay between each character in ms
 */
function updateAllCharactersInBatches(int $batchSize = 5, int $sleepMs = 200): bool
{
    $pdo = getDatabase();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->query("SELECT id, character_url FROM characters ORDER BY id ASC");
        $chars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($chars)) return true;

        $chunks = array_chunk($chars, max(1, (int)$batchSize));
        foreach ($chunks as $chunk) {
            foreach ($chunk as $c) {
                // small retry loop
                $tries = 2;
                while ($tries-- >= 0) {
                    try {
                        updateCharacterDataWithAnalytics((int)$c['id'], $c['character_url']);
                        break;
                    } catch (Exception $e) {
                        logError("[MU Tracker] update failed for id={$c['id']}: " . $e->getMessage());
                        if ($tries <= 0) break;
                        usleep(200 * 1000);
                    }
                }
                if ($sleepMs > 0) usleep((int)$sleepMs * 1000);
            }
            // brief pause between chunks
            usleep(500 * 1000);
        }
        return true;
    } catch (Exception $e) {
        logError('[MU Tracker] updateAllCharactersInBatches error: ' . $e->getMessage());
        return false;
    }
}
