<?php

/**
 * MU Tracker - User Authentication System
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

class UserAuth
{
    private $pdo;
    private $session_timeout = 3600; // 1 hour
    private $max_login_attempts = 5;
    private $lockout_duration = 900; // 15 minutes

    public function __construct()
    {
        try {
            // Check if logDebug function exists before calling it
            if (function_exists('logDebug')) {
                logDebug("Initializing UserAuth system");
            }

            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
                if (function_exists('logDebug')) {
                    logDebug("Session started successfully");
                }
            } else {
                if (function_exists('logDebug')) {
                    logDebug("Session already active");
                }
            }

            $this->pdo = getDatabase();
            if (!$this->pdo) {
                logError("Database connection failed in UserAuth constructor");
                throw new Exception('Database connection failed');
            }

            if (function_exists('logDebug')) {
                logDebug("UserAuth system initialized successfully");
            }
        } catch (Exception $e) {
            logError("Failed to initialize UserAuth system", ['error' => $e->getMessage()]);
            ErrorHandler::handleDatabaseError('auth_initialization', $e->getMessage());
        }
    }

    /**
     * Register a new user
     */
    public function register($username, $email, $password, $license_key)
    {
        try {
            // Validate license key
            $license = $this->validateLicenseKey($license_key);
            if (!$license) {
                return ['success' => false, 'message' => 'Invalid or expired license key'];
            }

            // Check if username or email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Determine user role based on license type
            $user_role = $license['license_type'] === 'vip' ? 'vip' : 'regular';

            // Start transaction
            $this->pdo->beginTransaction();

            // Create user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, license_key, user_role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $password_hash, $license_key, $user_role]);
            $user_id = $this->pdo->lastInsertId();

            // Mark license key as used
            $stmt = $this->pdo->prepare("
                UPDATE license_keys 
                SET is_used = TRUE, used_by_user_id = ?, used_at = NOW(), current_uses = current_uses + 1 
                WHERE license_key = ?
            ");
            $stmt->execute([$user_id, $license_key]);

            $this->pdo->commit();

            // Log successful registration
            logActivity($username, 'User registration', "New user registered with license: $license_key (Type: {$license['license_type']}, Role: $user_role)", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id, 'user_role' => $user_role];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    /**
     * Login user
     */
    public function login($username, $password)
    {
        try {
            if (function_exists('logDebug')) {
                logDebug("Login attempt", ['username' => $username]);
            }

            // Check if user is locked out
            $stmt = $this->pdo->prepare("
                SELECT id, username, password_hash, is_active, locked_until, login_attempts 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user) {
                logAuthEvent($username, 'login_failed_user_not_found', false);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            if (function_exists('logDebug')) {
                logDebug("User found for login", ['user_id' => $user['id'], 'username' => $user['username']]);
            }

            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                logAuthEvent($user['username'], 'login_failed_account_locked', false, [
                    'locked_until' => $user['locked_until'],
                    'attempts' => $user['login_attempts']
                ]);
                return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
            }

            // Check if account is active
            if (!$user['is_active']) {
                logAuthEvent($user['username'], 'login_failed_account_inactive', false);
                return ['success' => false, 'message' => 'Account is deactivated'];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                logAuthEvent($user['username'], 'login_failed_invalid_password', false, [
                    'attempts' => $user['login_attempts'] + 1
                ]);
                $this->incrementLoginAttempts($user['id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Reset login attempts on successful login
            $this->resetLoginAttempts($user['id']);

            // Create session
            $session_token = $this->createSession($user['id']);

            // Update last login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['session_token'] = $session_token;
            $_SESSION['login_time'] = time();

            // Log successful login
            logAuthEvent($user['username'], 'login_successful', true, [
                'user_id' => $user['id'],
                'session_token' => substr($session_token, 0, 8) . '...' // Only log partial token for security
            ]);
            logActivity($user['username'], 'User login', 'Successful login', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            return ['success' => true, 'message' => 'Login successful', 'user_id' => $user['id']];
        } catch (Exception $e) {
            logError("Login system error", ['username' => $username, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        $username = $_SESSION['username'] ?? 'unknown';

        if (isset($_SESSION['session_token'])) {
            // Deactivate session in database
            $stmt = $this->pdo->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }

        // Log logout
        logActivity($username, 'User logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Destroy session
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        // Verify session in database
        $stmt = $this->pdo->prepare("
            SELECT us.*, u.is_active 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.id 
            WHERE us.session_token = ? AND us.is_active = TRUE AND us.expires_at > NOW() AND u.is_active = TRUE
        ");
        $stmt->execute([$_SESSION['session_token']]);

        if ($stmt->rowCount() === 0) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Get current user ID
     */
    public function getCurrentUserId()
    {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }

    /**
     * Get current user info
     */
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id, username, email, user_role, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }

    /**
     * Check if current user is VIP or admin
     */
    public function isVipOrAdmin()
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['user_role'], ['vip', 'admin']);
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin()
    {
        $user = $this->getCurrentUser();
        return $user && $user['user_role'] === 'admin';
    }

    /**
     * Get current user role
     */
    public function getUserRole()
    {
        $user = $this->getCurrentUser();
        return $user ? $user['user_role'] : 'regular';
    }

    /**
     * Validate license key
     */
    public function validateLicenseKey($license_key)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, license_type, max_uses, current_uses, expires_at 
            FROM license_keys 
            WHERE license_key = ? AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$license_key]);
        $license = $stmt->fetch();

        if (!$license) {
            return false;
        }

        // Check if license has remaining uses
        if ($license['current_uses'] >= $license['max_uses']) {
            return false;
        }

        return $license;
    }

    /**
     * Create user session
     */
    private function createSession($user_id)
    {
        $session_token = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
        ");
        $stmt->execute([
            $user_id,
            $session_token,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $this->session_timeout
        ]);

        return $session_token;
    }

    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($user_id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE 
                    WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                    ELSE locked_until
                END
            WHERE id = ?
        ");
        $stmt->execute([$this->max_login_attempts, $this->lockout_duration, $user_id]);
    }

    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($user_id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }

    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions()
    {
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    }

    /**
     * Generate license key
     */
    public function generateLicenseKey($max_uses = 1, $expires_days = 365)
    {
        $license_key = 'MUTRACK-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', time() + ($expires_days * 24 * 60 * 60));

        $stmt = $this->pdo->prepare("
            INSERT INTO license_keys (license_key, max_uses, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$license_key, $max_uses, $expires_at]);

        return $license_key;
    }
}

// Initialize auth system
$auth = new UserAuth();
