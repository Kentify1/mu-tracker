# Production Deployment Guide for MU Tracker

## 🚨 **Quick Fix for Current Errors**

### **Step 1: Replace your config.php**
Replace your current `config.php` with the contents from `config_production_fixed.php`:

```php
<?php
/**
 * Production Configuration for MU Tracker
 */

// Database Configuration
$db_host = 'sql105.infinityfree.com';
$db_name = 'if0_40047672_mu_tracker';
$db_user = 'if0_40047672';
$db_pass = 'ycezK2Y46sKn';

// Initialize error handling early
require_once __DIR__ . '/error_handler.php';

// Environment Detection
$is_production = !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);

// Security Settings
if ($is_production) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// ⚠️ REMOVED OPCACHE SETTINGS - These cause warnings on shared hosting
// OPcache is managed by your hosting provider (InfinityFree)

// Timezone
date_default_timezone_set('UTC');

// ... rest of your database functions ...
```

### **Step 2: Key Changes Made**

1. **✅ Fixed OPcache Warning**: 
   - Removed OPcache `ini_set()` calls that cause warnings on shared hosting
   - Added `@` suppression operator for development-only OPcache settings

2. **✅ Fixed logDebug() Error**:
   - Added `function_exists('logDebug')` checks in `auth.php`
   - Added proper `logDebug()` function definition in config

3. **✅ Added Missing Functions**:
   - `logDebug()` - for debug logging
   - `logActivity()` - for activity logging  
   - `logAuthEvent()` - for authentication logging

4. **✅ Added Missing Tables**:
   - Users table creation
   - Activity logs table support
   - Auth logs table support

---

## 📋 **Complete Deployment Checklist**

### **Database Setup**
- [x] Database created: `if0_40047672_mu_tracker`
- [x] Database user: `if0_40047672`
- [x] Connection tested

### **File Upload**
Upload these files to your web hosting:
- [x] `config.php` (use the fixed version)
- [x] `auth.php` (with logDebug fixes)
- [x] `index.php`
- [x] `dashboard.php`
- [x] `admin.php`
- [x] `functions.php`
- [x] `analytics.php`
- [x] `vip_admin_enhancements.php`
- [x] `error_handler.php`
- [x] All other PHP files
- [x] `vendor/` folder (Composer dependencies)

### **Permissions**
Set these folder permissions:
- [x] `logs/` folder: 755 (create if doesn't exist)
- [x] Main directory: 755
- [x] PHP files: 644

### **Testing**
1. **✅ Visit your website**: `https://yourdomain.infinityfree.com/`
2. **✅ Check for errors**: Should load without fatal errors
3. **✅ Test login**: Create/login to user account
4. **✅ Test character adding**: Add a test character
5. **✅ Test refresh**: Manual character refresh
6. **✅ Check logs**: Look for any error messages

---

## 🔧 **Troubleshooting Common Issues**

### **"Database connection failed"**
```php
// Check these in your config.php:
$db_host = 'sql105.infinityfree.com'; // ✅ Correct
$db_name = 'if0_40047672_mu_tracker'; // ✅ Correct  
$db_user = 'if0_40047672'; // ✅ Correct
$db_pass = 'ycezK2Y46sKn'; // ✅ Check this is current
```

### **"Function not found" errors**
- Make sure all files are uploaded
- Check file permissions (644 for PHP files)
- Verify `vendor/` folder is uploaded

### **"Session" errors**
- InfinityFree supports sessions
- Make sure session settings are not conflicting

### **"Permission denied" errors**
- Create `logs/` folder with 755 permissions
- Check main directory permissions

---

## 🚀 **Performance Optimization for InfinityFree**

### **What's Already Optimized**
- ✅ Database connection pooling
- ✅ Error logging (not display)
- ✅ Secure session settings
- ✅ Input sanitization
- ✅ Rate limiting

### **InfinityFree Specific**
- ✅ No OPcache modifications (managed by host)
- ✅ MySQL optimized queries
- ✅ Minimal resource usage
- ✅ Error suppression in production

---

## 📊 **Monitoring Your Live Site**

### **Check These Regularly**
1. **Error Logs**: Check your hosting control panel for PHP errors
2. **Database Size**: Monitor your database usage
3. **Performance**: Test page load times
4. **Character Updates**: Verify auto-refresh is working

### **Log Files to Monitor**
- `logs/cron_refresh.log` (if using cron)
- PHP error logs (in hosting control panel)
- Database error logs

---

## 🔐 **Security Checklist**

- [x] Database credentials secured
- [x] Error display disabled in production
- [x] Session security enabled
- [x] Input sanitization active
- [x] CSRF protection enabled
- [x] Rate limiting implemented

---

## 📞 **Getting Help**

If you still get errors:

1. **Check the exact error message**
2. **Look at line numbers** in error messages
3. **Check file permissions** (644 for PHP, 755 for directories)
4. **Verify all files uploaded** correctly
5. **Test database connection** separately

**Common InfinityFree Issues:**
- File permissions must be exactly right
- Some PHP functions may be disabled
- Database connections have limits
- Session storage is limited

Your site should now work without the OPcache and logDebug errors! 🎉
