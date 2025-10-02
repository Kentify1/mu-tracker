# 🚀 MU Tracker Deployment Guide

Complete guide for deploying your MU Tracker to various hosting platforms using GitHub.

## 📋 Pre-Deployment Checklist

### 1. Prepare Your Local Repository
```bash
# Navigate to your project directory
cd C:\xampp\htdocs\mu-tracker

# Initialize Git repository
git init

# Add all files
git add .

# Make initial commit
git commit -m "Initial commit: MU Tracker v2.0"
```

### 2. Create GitHub Repository
1. Go to [GitHub.com](https://github.com)
2. Click "New Repository"
3. Name it: `mu-tracker`
4. Set to Public or Private
5. Don't initialize with README (we already have one)
6. Click "Create Repository"

### 3. Connect Local to GitHub
```bash
# Add GitHub remote
git remote add origin https://github.com/YOUR_USERNAME/mu-tracker.git

# Push to GitHub
git branch -M main
git push -u origin main
```

---

## 🌐 Hosting Options

### Option 1: Railway (Recommended) ⭐

**Why Railway?**
- ✅ Free tier with 500 hours/month
- ✅ Automatic deployments from GitHub
- ✅ Built-in MySQL database
- ✅ Easy environment variables
- ✅ Custom domains

**Setup Steps:**
1. **Sign up at [Railway.app](https://railway.app)**
2. **Connect GitHub account**
3. **Create new project**
   - Click "Deploy from GitHub repo"
   - Select your `mu-tracker` repository
4. **Add MySQL database**
   - Click "Add Service" → "Database" → "MySQL"
   - Railway will provide connection details
5. **Set environment variables**
   ```
   DB_HOST=mysql.railway.internal
   DB_NAME=railway
   DB_USER=root
   DB_PASS=[auto-generated]
   APP_ENV=production
   ```
6. **Deploy**
   - Railway automatically builds and deploys
   - Get your URL: `https://your-app.up.railway.app`

---

### Option 2: Vercel (Serverless)

**Why Vercel?**
- ✅ Free tier
- ✅ Global CDN
- ✅ Automatic HTTPS
- ✅ GitHub integration

**Setup Steps:**
1. **Sign up at [Vercel.com](https://vercel.com)**
2. **Import GitHub repository**
3. **Configure build settings**
   - Framework: Other
   - Build Command: (leave empty)
   - Output Directory: (leave empty)
4. **Add environment variables**
   ```
   DB_HOST=your-database-host
   DB_NAME=your-database-name
   DB_USER=your-database-user
   DB_PASS=your-database-password
   ```
5. **Deploy**

**Note:** Vercel is serverless, so you'll need an external MySQL database (PlanetScale, Railway, etc.)

---

### Option 3: Heroku

**Why Heroku?**
- ✅ Easy deployment
- ✅ Add-on ecosystem
- ✅ Git-based deployment

**Setup Steps:**
1. **Install Heroku CLI**
2. **Login to Heroku**
   ```bash
   heroku login
   ```
3. **Create Heroku app**
   ```bash
   heroku create your-mu-tracker
   ```
4. **Add MySQL addon**
   ```bash
   heroku addons:create cleardb:ignite
   ```
5. **Set environment variables**
   ```bash
   heroku config:set APP_ENV=production
   ```
6. **Deploy**
   ```bash
   git push heroku main
   ```

---

### Option 4: DigitalOcean App Platform

**Why DigitalOcean?**
- ✅ $5/month starting price
- ✅ Managed databases
- ✅ GitHub integration

**Setup Steps:**
1. **Sign up at [DigitalOcean.com](https://digitalocean.com)**
2. **Create new app**
3. **Connect GitHub repository**
4. **Configure app**
   - Runtime: PHP
   - Build command: (none)
   - Run command: `php -S 0.0.0.0:8080`
5. **Add managed database**
6. **Set environment variables**
7. **Deploy**

---

## 🔧 Environment Configuration

### Database Setup
For each platform, you'll need to:

1. **Create database tables**
   - The app auto-creates tables on first run
   - Or run the SQL files manually

2. **Set environment variables**
   ```
   DB_HOST=your-database-host
   DB_NAME=your-database-name
   DB_USER=your-database-user
   DB_PASS=your-database-password
   APP_ENV=production
   ```

### Security Configuration
```
APP_SECRET_KEY=generate-random-32-char-string
CSRF_SECRET=generate-random-32-char-string
CRON_SECRET_KEY=generate-random-32-char-string
```

---

## 🔄 Continuous Deployment

### Automatic Deployments
Most platforms support automatic deployment when you push to GitHub:

```bash
# Make changes to your code
git add .
git commit -m "Add new feature"
git push origin main
# Platform automatically deploys changes
```

### Manual Deployment
For platforms requiring manual deployment:

```bash
# Railway
railway up

# Heroku
git push heroku main

# Vercel
vercel --prod
```

---

## 🗄️ Database Options

### Free Database Options
1. **Railway MySQL** - 1GB free
2. **PlanetScale** - 10GB free
3. **Heroku ClearDB** - 5MB free
4. **Aiven MySQL** - 1 month free trial

### Paid Database Options
1. **DigitalOcean Managed Database** - $15/month
2. **AWS RDS** - Pay per use
3. **Google Cloud SQL** - Pay per use

---

## 📊 Monitoring & Maintenance

### Health Checks
Add health check endpoints:
```php
// health_check.php
<?php
echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'database' => getDatabase() ? 'connected' : 'disconnected'
]);
?>
```

### Error Monitoring
- Use platform-specific logging
- Set up error alerts
- Monitor database performance

### Backups
- Enable automatic database backups
- Export character data regularly
- Version control your code changes

---

## 🚨 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check environment variables
echo $DB_HOST $DB_NAME $DB_USER

# Test connection manually
mysql -h $DB_HOST -u $DB_USER -p $DB_NAME
```

**File Permissions**
```bash
# Ensure logs directory is writable
chmod 755 logs/
```

**Memory Limits**
- Increase PHP memory limit in platform settings
- Optimize database queries
- Use caching where possible

### Platform-Specific Issues

**Railway**
- Check build logs in dashboard
- Verify environment variables
- Monitor resource usage

**Vercel**
- Check function logs
- Verify serverless compatibility
- Monitor execution time limits

**Heroku**
- Check dyno logs: `heroku logs --tail`
- Monitor dyno usage
- Check add-on status

---

## 🎯 Next Steps

After successful deployment:

1. **Test all features**
   - Character tracking
   - User authentication
   - Admin panel
   - VIP features

2. **Set up monitoring**
   - Error tracking
   - Performance monitoring
   - Uptime monitoring

3. **Configure backups**
   - Database backups
   - Code repository backups

4. **Set up custom domain** (optional)
   - Purchase domain
   - Configure DNS
   - Enable SSL

5. **Optimize performance**
   - Enable caching
   - Optimize images
   - Minify CSS/JS

---

## 🔗 Useful Links

- [Railway Documentation](https://docs.railway.app)
- [Vercel Documentation](https://vercel.com/docs)
- [Heroku PHP Documentation](https://devcenter.heroku.com/articles/getting-started-with-php)
- [DigitalOcean App Platform](https://docs.digitalocean.com/products/app-platform/)

---

**🎉 Congratulations!** Your MU Tracker is now live and accessible from anywhere in the world!
