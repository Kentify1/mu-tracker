# Auto-Refresh Setup Instructions

Your MU Tracker currently requires the website to be open for auto-refresh to work. Here are 3 ways to make it work **without keeping the website open**:

## 🎯 **Option 1: Server Cron Job (Best for VPS/Dedicated Servers)**

### Setup Steps:
1. **Upload the `cron_refresh.php` file** to your mu-tracker directory
2. **SSH into your server** and run: `crontab -e`
3. **Add this line** to run every hour:
   ```bash
   0 * * * * /usr/bin/php /path/to/your/mu-tracker/cron_refresh.php
   ```
4. **Save and exit**

### Different Intervals:
- **Every 30 minutes**: `*/30 * * * * /usr/bin/php /path/to/your/mu-tracker/cron_refresh.php`
- **Every 2 hours**: `0 */2 * * * /usr/bin/php /path/to/your/mu-tracker/cron_refresh.php`
- **Every 6 hours**: `0 */6 * * * /usr/bin/php /path/to/your/mu-tracker/cron_refresh.php`

### Check if it's working:
- Look for logs in: `logs/cron_refresh.log`
- Check your characters are updating automatically

---

## 🌐 **Option 2: Web-Based Cron (Best for Shared Hosting)**

### Setup Steps:
1. **Upload `auto_refresh_endpoint.php`** to your mu-tracker directory
2. **Edit the file** and change this line:
   ```php
   define('CRON_SECRET_KEY', 'your-secret-key-change-this-12345');
   ```
   Change it to a random secret key like: `'my-super-secret-key-xyz789'`

3. **Sign up for a free web cron service**:
   - [cron-job.org](https://cron-job.org) (Free, reliable)
   - [EasyCron.com](https://www.easycron.com) (Free tier available)
   - Your hosting provider's cron panel

4. **Set up the cron job** to call:
   ```
   https://yourdomain.com/mu-tracker/auto_refresh_endpoint.php?key=your-secret-key-here
   ```

5. **Set frequency** to every hour (or your preferred interval)

### Test it:
Visit the URL in your browser - you should see a JSON response with refresh results.

---

## 🏠 **Option 3: Hosting Provider Cron (Easiest)**

Many hosting providers offer cron job panels in their control panel:

### cPanel:
1. Go to **"Cron Jobs"** in cPanel
2. **Add New Cron Job**
3. **Set timing**: `0 * * * *` (every hour)
4. **Command**: `/usr/bin/php /home/username/public_html/mu-tracker/cron_refresh.php`

### Plesk:
1. Go to **"Scheduled Tasks"**
2. **Add Task**
3. **Set schedule** and **command**

### Other Panels:
Look for "Cron Jobs", "Scheduled Tasks", or "Task Scheduler"

---

## 📊 **Monitoring Your Auto-Refresh**

### Check Logs:
- **Cron logs**: `logs/cron_refresh.log`
- **Activity logs**: Check your admin panel → Activity Logs
- **Error logs**: Check your server's PHP error log

### Verify It's Working:
1. **Check character data** is updating automatically
2. **Look at timestamps** in your character list
3. **Monitor the logs** for successful refreshes

---

## ⚙️ **Advanced Configuration**

### Change Refresh Frequency:
Edit the cron timing or web cron frequency based on your needs:
- **More frequent**: Every 15 minutes for active monitoring
- **Less frequent**: Every 6 hours to reduce server load

### Customize Delays:
In `cron_refresh.php`, you can adjust the delay between character updates:
```php
usleep(500000); // 0.5 second delay (current)
usleep(1000000); // 1 second delay (slower, gentler)
usleep(250000); // 0.25 second delay (faster)
```

---

## 🔧 **Troubleshooting**

### Common Issues:

1. **"Command not found"**:
   - Try `/usr/local/bin/php` instead of `/usr/bin/php`
   - Ask your hosting provider for the correct PHP path

2. **"Permission denied"**:
   - Make sure `cron_refresh.php` has execute permissions: `chmod +x cron_refresh.php`

3. **"Database connection failed"**:
   - Check your database credentials in `config.php`
   - Ensure the database is accessible from cron jobs

4. **Web cron returns 401**:
   - Make sure you're using the correct secret key
   - Check the URL is exactly right

### Getting Help:
- Check the log files first
- Test the refresh manually: `php cron_refresh.php`
- Contact your hosting provider for cron job support

---

## 🎉 **Benefits of Server-Side Auto-Refresh**

✅ **Works 24/7** - No need to keep website open  
✅ **More reliable** - Not dependent on browser tabs  
✅ **Better performance** - Runs on server, not client  
✅ **Scheduled precisely** - Exact timing control  
✅ **Logs everything** - Full monitoring and debugging  

Choose the option that works best for your hosting setup!
