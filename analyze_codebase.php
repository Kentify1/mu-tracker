<?php

/**
 * Codebase Analysis - Find Unused Files
 */

echo "<h1>MU Tracker - Codebase Analysis</h1>";

$root_dir = __DIR__;
$files_to_analyze = [];

// Core application files (should be kept)
$core_files = [
    'index.php',
    'dashboard.php',
    'login.php',
    'logout.php',
    'auth.php',
    'config.php',
    'functions.php',
    'analytics.php',
    '.htaccess'
];

// Files that might be unused
$potentially_unused = [];

// Scan directory
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root_dir));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relative_path = str_replace($root_dir . DIRECTORY_SEPARATOR, '', $file->getPathname());

        // Skip vendor and node_modules
        if (
            strpos($relative_path, 'vendor/') === 0 ||
            strpos($relative_path, 'node_modules/') === 0 ||
            strpos($relative_path, 'mu-tracker-deployment/') === 0
        ) {
            continue;
        }

        $files_to_analyze[] = $relative_path;
    }
}

echo "<h2>File Analysis</h2>";

// Categorize files
$categories = [
    'Core Application' => [],
    'Documentation' => [],
    'Database/SQL' => [],
    'Setup/Config' => [],
    'Debug/Test' => [],
    'Backup Files' => [],
    'Unused/Redundant' => []
];

foreach ($files_to_analyze as $file) {
    $basename = basename($file);
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    if (in_array($basename, $core_files)) {
        $categories['Core Application'][] = $file;
    } elseif (strpos($basename, '.md') !== false) {
        $categories['Documentation'][] = $file;
    } elseif (in_array($extension, ['sql', 'db'])) {
        $categories['Database/SQL'][] = $file;
    } elseif (strpos($basename, 'setup_') === 0 || strpos($basename, 'config') === 0) {
        $categories['Setup/Config'][] = $file;
    } elseif (strpos($basename, 'debug_') === 0 || strpos($basename, 'test_') === 0 || strpos($basename, 'health_check') === 0) {
        $categories['Debug/Test'][] = $file;
    } elseif (strpos($basename, '.bak') !== false) {
        $categories['Backup Files'][] = $file;
    } else {
        $categories['Unused/Redundant'][] = $file;
    }
}

// Display analysis
foreach ($categories as $category => $files) {
    if (empty($files)) continue;

    echo "<h3>$category (" . count($files) . " files)</h3>";
    echo "<ul>";
    foreach ($files as $file) {
        $size = filesize($file);
        $size_str = $size > 1024 ? round($size / 1024, 1) . 'KB' : $size . 'B';
        echo "<li>$file <small>($size_str)</small></li>";
    }
    echo "</ul>";
}

echo "<h2>Recommendations</h2>";

// Check for specific issues
$issues = [];

// Check for duplicate deployment folders
if (is_dir('mu-tracker-deployment')) {
    $issues[] = "❌ Remove 'mu-tracker-deployment' folder - it's a duplicate created by the packaging script";
}

// Check for backup files
$backup_files = glob('*.bak');
if (!empty($backup_files)) {
    $issues[] = "❌ Remove backup files: " . implode(', ', $backup_files);
}

// Check for debug files
$debug_files = glob('debug_*');
if (!empty($debug_files)) {
    $issues[] = "❌ Remove debug files: " . implode(', ', $debug_files);
}

// Check for unused SQL files
if (file_exists('analytics-schema.sql') && file_exists('mu_tracker.sql')) {
    $issues[] = "❌ You have duplicate SQL files - keep only one";
}

// Check for unused documentation
$doc_files = glob('*.md');
if (count($doc_files) > 3) {
    $issues[] = "❌ Too many documentation files - consider consolidating";
}

// Check for Node.js files (not needed for PHP app)
if (file_exists('package.json') || file_exists('node_modules')) {
    $issues[] = "❌ Remove Node.js files - not needed for PHP application";
}

// Check for unused vendor packages
if (is_dir('vendor/spatie/browsershot')) {
    $issues[] = "❌ Remove spatie/browsershot - no longer used (replaced with Guzzle)";
}

if (empty($issues)) {
    echo "<p style='color: green;'>✅ No major issues found!</p>";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}

echo "<h2>Cleanup Commands</h2>";
echo "<pre>";
echo "# Remove deployment folder\n";
echo "rm -rf mu-tracker-deployment\n\n";

echo "# Remove backup files\n";
echo "rm *.bak\n\n";

echo "# Remove debug files\n";
echo "rm debug_*\n\n";

echo "# Remove Node.js files\n";
echo "rm package.json package-lock.json\n";
echo "rm -rf node_modules\n\n";

echo "# Remove unused vendor packages\n";
echo "rm -rf vendor/spatie\n\n";

echo "# Remove duplicate SQL files (keep mu_tracker.sql)\n";
echo "rm analytics-schema.sql\n\n";

echo "# Remove excessive documentation (keep README.md and one guide)\n";
echo "rm ANALYTICS_GUIDE.md DEPLOYMENT_GUIDE.md DEPLOYMENT_CHECKLIST.md FREE_HOSTING_GUIDE.md\n";
echo "</pre>";

echo "<h2>File Size Analysis</h2>";
$total_size = 0;
foreach ($files_to_analyze as $file) {
    if (file_exists($file)) {
        $total_size += filesize($file);
    }
}

echo "<p>Total size: " . round($total_size / 1024 / 1024, 2) . " MB</p>";

// Check largest files
$file_sizes = [];
foreach ($files_to_analyze as $file) {
    if (file_exists($file)) {
        $file_sizes[$file] = filesize($file);
    }
}
arsort($file_sizes);

echo "<h3>Largest Files:</h3>";
echo "<ul>";
$count = 0;
foreach ($file_sizes as $file => $size) {
    if ($count++ >= 10) break;
    $size_str = $size > 1024 ? round($size / 1024, 1) . 'KB' : $size . 'B';
    echo "<li>$file <small>($size_str)</small></li>";
}
echo "</ul>";

echo "<h2>Summary</h2>";
echo "<p>Your codebase has some cleanup opportunities. The main issues are:</p>";
echo "<ul>";
echo "<li>Duplicate deployment folders</li>";
echo "<li>Backup and debug files</li>";
echo "<li>Unused Node.js dependencies</li>";
echo "<li>Excessive documentation files</li>";
echo "<li>Unused vendor packages</li>";
echo "</ul>";
echo "<p>Cleaning these up will reduce your codebase size and make it more maintainable.</p>";
