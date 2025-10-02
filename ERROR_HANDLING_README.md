# MU Tracker - Error Handling System

## Overview
The MU Tracker now includes a comprehensive error handling system with custom error pages and detailed logging for debugging purposes.

## Files Added

### Core Error Handling
- **`error_handler.php`** - Centralized error handler class
- **`error_page_template.php`** - Beautiful, responsive error page template
- **`404.php`** - Page not found error page
- **`403.php`** - Access forbidden error page  
- **`500.php`** - Internal server error page
- **`test_errors.php`** - Error testing page (development only)

## Features

### 🎨 Beautiful Error Pages
- **Responsive design** that works on all devices
- **Professional styling** matching the MU Tracker theme
- **Contextual suggestions** based on error type
- **Unique error IDs** for tracking and support
- **Auto-refresh** for 500 errors (temporary issues)

### 📊 Comprehensive Logging
- **Detailed error context** including IP, user agent, request details
- **Unique error IDs** for easy tracking
- **Multiple error types**: PHP errors, exceptions, fatal errors, 404s, 403s
- **Debug information** in development mode
- **Integration with existing log viewer**

### 🔧 Error Types Handled

#### 404 - Page Not Found
- Logs requested URL and referrer
- Provides navigation suggestions
- Links to homepage and dashboard

#### 403 - Access Forbidden
- Logs access attempts with user context
- Suggests login or permission checks
- Links to login page

#### 500 - Internal Server Error
- Logs full error context and stack traces
- Auto-refresh functionality for temporary issues
- Detailed debugging information

#### PHP Errors & Exceptions
- Catches all PHP errors, warnings, and notices
- Handles uncaught exceptions
- Captures fatal errors during shutdown

## Usage

### Automatic Error Handling
The error handler is automatically initialized when `config.php` is loaded. It will:
- Catch all PHP errors and exceptions
- Display beautiful error pages to users
- Log detailed information for debugging

### Manual Error Triggering
```php
// Trigger specific error pages
ErrorHandler::handle404('/nonexistent-page');
ErrorHandler::handle403('Access denied reason');
ErrorHandler::handleDatabaseError('operation', 'error message', 'query');
ErrorHandler::handleAuthError('Authentication failed');
```

### Testing Errors (Development)
Visit `/test_errors` (admin access required) to test all error types:
- 404 errors
- 403 errors  
- 500 errors
- PHP errors
- Exceptions
- Fatal errors
- Database errors

## Configuration

### Production vs Development
- **Production**: Shows user-friendly error pages, detailed logging
- **Development**: Additional debug information available with `?debug=1`

### Error Logging
All errors are logged with:
- Timestamp and unique error ID
- User IP address and user agent
- Request method and URI
- Full error context and stack traces
- User session information (if available)

### Log Files
- **PHP Error Log**: Standard PHP error log location
- **Custom Log**: `/logs/mu_tracker.log` (if writable)
- **Log Viewer**: Available at `/log_viewer` (admin only)

## Security Features

### Protected Information
- Sensitive data is never exposed in error messages
- Database credentials and internal paths are hidden
- Stack traces only shown in development mode
- Partial session tokens logged (never full tokens)

### Access Control
- Error testing page requires admin access
- Log viewer requires admin access
- Debug information only in development mode

## Integration

### .htaccess Configuration
```apache
ErrorDocument 404 /404.php
ErrorDocument 403 /403.php
ErrorDocument 500 /500.php
```

### Admin Panel Integration
- Link to log viewer in admin dashboard
- Link to error testing page (development)
- Error statistics and monitoring

## Troubleshooting

### Common Issues

1. **Error pages not showing**
   - Check .htaccess configuration
   - Verify file permissions
   - Check server error logs

2. **Logging not working**
   - Verify `/logs/` directory is writable
   - Check PHP error_log configuration
   - Ensure error handler is initialized

3. **Debug info not showing**
   - Add `?debug=1` to URL in development
   - Check `$is_production` setting in config

### Error ID Format
Error IDs follow the format: `ERR-YYYYMMDD-HHMMSS-XXXXXX`
- Date and time of error
- 6-character unique identifier
- Use this ID when reporting issues

## Best Practices

### For Developers
1. Always use the error handler methods instead of direct `die()` or `exit()`
2. Include meaningful context in error logs
3. Test error scenarios using the test page
4. Monitor logs regularly for patterns

### For Production
1. Remove or restrict access to `test_errors.php`
2. Ensure proper file permissions on `/logs/` directory
3. Set up log rotation for large log files
4. Monitor error rates and patterns

## Future Enhancements

Potential improvements:
- Email notifications for critical errors
- Error rate monitoring and alerts
- Integration with external monitoring services
- Automated error pattern analysis
- Performance impact monitoring

---

**Note**: Remember to remove `test_errors.php` or restrict its access in production environments.
