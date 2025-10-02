<?php

/**
 * Simple Authentication Setup for MU Tracker
 * Run this to add user authentication to your existing database
 */

require_once __DIR__ . '/config.php';

echo "<h1>MU Tracker - Simple Authentication Setup</h1>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h2>Creating authentication tables...</h2>";

// Create users table
try {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        license_key VARCHAR(100) NOT NULL UNIQUE,
        is_active BOOLEAN DEFAULT TRUE,
        user_role ENUM('regular', 'vip', 'admin') DEFAULT 'regular',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_license_key (license_key),
        INDEX idx_user_role (user_role)
    )";
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ Users table created with role support</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Users table: " . $e->getMessage() . "</p>";
}

// Create user_sessions table
try {
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(255) NOT NULL UNIQUE,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_session_token (session_token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at)
    )";
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ User sessions table created</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ User sessions table: " . $e->getMessage() . "</p>";
}

// Create license_keys table
try {
    $sql = "CREATE TABLE IF NOT EXISTS license_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_key VARCHAR(100) NOT NULL UNIQUE,
        is_used BOOLEAN DEFAULT FALSE,
        used_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        max_uses INT DEFAULT 1,
        current_uses INT DEFAULT 0,
        INDEX idx_license_key (license_key),
        INDEX idx_is_used (is_used)
    )";
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ License keys table created</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ License keys table: " . $e->getMessage() . "</p>";
}

echo "<h2>Adding user_id columns to existing tables...</h2>";

// Add user_id to characters table
try {
    $pdo->exec("ALTER TABLE characters ADD COLUMN user_id INT NULL AFTER id");
    $pdo->exec("ALTER TABLE characters ADD INDEX idx_user_id (user_id)");
    echo "<p style='color: green;'>✓ Added user_id to characters table</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Characters table: " . $e->getMessage() . "</p>";
}

// Add user_id to character_history table
try {
    $pdo->exec("ALTER TABLE character_history ADD COLUMN user_id INT NULL AFTER id");
    $pdo->exec("ALTER TABLE character_history ADD INDEX idx_user_id (user_id)");
    echo "<p style='color: green;'>✓ Added user_id to character_history table</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Character history table: " . $e->getMessage() . "</p>";
}

// Add user_id to daily_analytics table
try {
    $pdo->exec("ALTER TABLE daily_analytics ADD COLUMN user_id INT NULL AFTER id");
    $pdo->exec("ALTER TABLE daily_analytics ADD INDEX idx_user_id (user_id)");
    echo "<p style='color: green;'>✓ Added user_id to daily_analytics table</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Daily analytics table: " . $e->getMessage() . "</p>";
}

// Add user_id to hourly_analytics table
try {
    $pdo->exec("ALTER TABLE hourly_analytics ADD COLUMN user_id INT NULL AFTER id");
    $pdo->exec("ALTER TABLE hourly_analytics ADD INDEX idx_user_id (user_id)");
    echo "<p style='color: green;'>✓ Added user_id to hourly_analytics table</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Hourly analytics table: " . $e->getMessage() . "</p>";
}

echo "<h2>Adding sample data...</h2>";

// Insert default admin user
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, license_key, is_active, user_role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@mutracker.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN-2024-001', TRUE, 'admin']);
    echo "<p style='color: green;'>✓ Default admin user created with admin role</p>";
    echo "<p style='color: blue;'>ℹ Admin credentials: username='admin', password='admin123'</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Admin user: " . $e->getMessage() . "</p>";
}

// Insert sample license keys
try {
    $licenses = [
        ['MUTRACK-2024-001', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['MUTRACK-2024-002', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['MUTRACK-2024-003', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['MUTRACK-2024-004', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['MUTRACK-2024-005', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['VIP-2024-001', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['VIP-2024-002', 1, date('Y-m-d H:i:s', strtotime('+1 year'))],
        ['VIP-2024-003', 1, date('Y-m-d H:i:s', strtotime('+1 year'))]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO license_keys (license_key, max_uses, expires_at) VALUES (?, ?, ?)");
    foreach ($licenses as $license) {
        $stmt->execute($license);
    }
    echo "<p style='color: green;'>✓ Sample license keys created (including VIP keys)</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ License keys: " . $e->getMessage() . "</p>";
}

echo "<h2>Setup Complete!</h2>";
echo "<p>Your MU Tracker now has user authentication enabled with role-based access control.</p>";

echo "<h3>New Features Added:</h3>";
echo "<ul>";
echo "<li><strong>Character Limits:</strong> Regular users limited to 10 characters, VIP/Admin users unlimited</li>";
echo "<li><strong>User Roles:</strong> Regular, VIP, and Admin roles with different permissions</li>";
echo "<li><strong>Cloudflare Protection:</strong> Warnings about frequent refreshing to prevent verification checks</li>";
echo "<li><strong>Enhanced Security:</strong> Role-based access control and improved user management</li>";
echo "</ul>";

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Visit <a href='login'>login page</a> to test authentication</li>";
echo "<li>Use admin credentials: username='admin', password='admin123'</li>";
echo "<li>Or register a new user with one of the license keys</li>";
echo "<li>Test the main application at <a href='index'>index</a></li>";
echo "<li>Check the analytics dashboard at <a href='dashboard'>dashboard</a></li>";
echo "<li>Delete this setup file for security: <code>rm setup_auth_simple.php</code></li>";
echo "</ul>";

echo "<p><strong>Available License Keys:</strong></p>";
try {
    $stmt = $pdo->query("SELECT license_key, is_used FROM license_keys ORDER BY license_key");
    $licenses = $stmt->fetchAll();
    echo "<ul>";
    foreach ($licenses as $license) {
        $status = $license['is_used'] ? 'Used' : 'Available';
        $color = $license['is_used'] ? 'red' : 'green';
        echo "<li style='color: $color;'>{$license['license_key']} - $status</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error fetching license keys: " . $e->getMessage() . "</p>";
}

echo "<p style='color: red;'><strong>Important:</strong> Delete this setup file after running it for security reasons!</p>";
