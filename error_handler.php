<?php

/**
 * MU Tracker - Centralized Error Handler
 * Handles all types of errors with logging and user-friendly pages
 */

require_once __DIR__ . '/config.php';

class ErrorHandler
{
    private static $instance = null;
    private $isProduction;

    public function __construct()
    {
        global $is_production;
        $this->isProduction = $is_production ?? true;

        // Set up error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line)
    {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorType = $this->getErrorType($severity);
        $context = [
            'type' => $errorType,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];

        logError("PHP Error: $message", $context, 'PHP_ERROR');

        // For fatal errors, show error page
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            $this->showErrorPage(500, 'Internal Server Error', $message);
        }

        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception)
    {
        $context = [
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        logError("Uncaught Exception: " . $exception->getMessage(), $context, 'EXCEPTION');

        $this->showErrorPage(500, 'Internal Server Error', $exception->getMessage());
    }

    /**
     * Handle fatal errors
     */
    public function handleFatalError()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $context = [
                'type' => $this->getErrorType($error['type']),
                'file' => $error['file'],
                'line' => $error['line']
            ];

            logError("Fatal Error: " . $error['message'], $context, 'FATAL_ERROR');

            $this->showErrorPage(500, 'Internal Server Error', $error['message']);
        }
    }

    /**
     * Show custom error page
     */
    public function showErrorPage($code, $title, $message = null)
    {
        // Prevent infinite loops
        static $errorPageShown = false;
        if ($errorPageShown) {
            return;
        }
        $errorPageShown = true;

        // Log the error page request
        logError("Error page displayed", [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
        ], 'ERROR_PAGE');

        // Set appropriate HTTP status
        http_response_code($code);

        // Clean any previous output
        if (ob_get_level()) {
            ob_clean();
        }

        // Include the error page template
        include __DIR__ . '/error_page_template.php';
        exit;
    }

    /**
     * Get human-readable error type
     */
    private function getErrorType($severity)
    {
        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $errorTypes[$severity] ?? 'Unknown Error';
    }

    /**
     * Handle 404 errors
     */
    public static function handle404($requestedUrl = null)
    {
        $requestedUrl = $requestedUrl ?? $_SERVER['REQUEST_URI'] ?? 'unknown';

        logError("404 Not Found", [
            'requested_url' => $requestedUrl,
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], '404_ERROR');

        $handler = self::getInstance();
        $handler->showErrorPage(404, 'Page Not Found', "The requested page '$requestedUrl' could not be found.");
    }

    /**
     * Handle 403 errors
     */
    public static function handle403($reason = null)
    {
        $reason = $reason ?? 'Access denied';

        logError("403 Forbidden", [
            'reason' => $reason,
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? 'guest'
        ], '403_ERROR');

        $handler = self::getInstance();
        $handler->showErrorPage(403, 'Access Forbidden', $reason);
    }

    /**
     * Handle database errors
     */
    public static function handleDatabaseError($operation, $error, $query = null)
    {
        logDatabaseError($operation, $error, $query);

        $handler = self::getInstance();
        $handler->showErrorPage(500, 'Database Error', 'A database error occurred. Please try again later.');
    }

    /**
     * Handle authentication errors
     */
    public static function handleAuthError($message = null)
    {
        $message = $message ?? 'Authentication required';

        logError("Authentication Error", [
            'message' => $message,
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? 'none'
        ], 'AUTH_ERROR');

        // Redirect to login instead of showing error page
        header('Location: login?error=' . urlencode($message));
        exit;
    }
}

// Initialize error handler
ErrorHandler::getInstance();
