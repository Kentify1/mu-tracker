# 🎮 MU Online Character Tracker

A comprehensive web-based tracker for monitoring MU Online characters with advanced analytics, VIP features, and admin capabilities.

![MU Tracker](https://img.shields.io/badge/MU-Tracker-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

## ✨ Features

### 🔥 Core Features
- **Real-time Character Tracking** - Monitor character status, level, resets, and location
- **Auto-refresh System** - Automatic updates every hour (configurable)
- **Multi-user Support** - User authentication with role-based access
- **Responsive Design** - Works on desktop, tablet, and mobile devices

### 📊 Analytics & Insights
- **Character History** - Track level progression over time
- **Daily/Hourly Progress** - Detailed analytics with charts
- **Milestone Tracking** - Level and reset achievements
- **Leaderboards** - Compare with other players
- **Export Data** - CSV export functionality

### 👑 VIP Features
- **Predictive Analytics** - AI-powered insights and recommendations
- **Activity Heatmaps** - Visual activity patterns
- **Advanced Charts** - Enhanced data visualization
- **Priority Support** - Faster refresh rates

### 🛡️ Admin Features
- **System Health Monitoring** - Server performance metrics
- **User Management** - Bulk operations and analytics
- **Advanced User Analytics** - Registration trends and engagement
- **Activity Logging** - Comprehensive audit trails

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- cURL extension enabled

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/mu-tracker.git
   cd mu-tracker
   ```

2. **Configure the database**
   ```bash
   cp config.example.php config.php
   ```
   Edit `config.php` with your database credentials:
   ```php
   $db_host = 'localhost';
   $db_name = 'mu_tracker';
   $db_user = 'your_username';
   $db_pass = 'your_password';
   ```

3. **Set up the database**
   - Create a MySQL database named `mu_tracker`
   - The application will automatically create the required tables

4. **Configure web server**
   - Point your web server to the project directory
   - Ensure PHP has write permissions for the `logs/` directory

5. **Access the application**
   - Open your browser and navigate to your domain
   - Create an admin account on first visit

## 🔧 Configuration

### Environment Variables
You can use environment variables for sensitive configuration:

```php
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'mu_tracker';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
```

### Auto-refresh Setup
For server-side auto-refresh without keeping the browser open:

#### Option 1: Cron Job
```bash
# Add to crontab (crontab -e)
0 * * * * /usr/bin/php /path/to/mu-tracker/cron_refresh.php
```

#### Option 2: Web Cron Service
Use services like cron-job.org to call:
```
https://yourdomain.com/auto_refresh_endpoint.php?key=your-secret-key
```

## 📱 Hosting Options

### 🌐 Free Hosting
- **InfinityFree** - Free PHP hosting with MySQL
- **000webhost** - Free hosting with good PHP support
- **Heroku** - Free tier with PostgreSQL

### ☁️ Cloud Hosting
- **Railway** - Easy deployment from GitHub
- **Vercel** - Serverless PHP support
- **DigitalOcean** - VPS hosting starting at $5/month

### 🏠 Self-Hosting
- **XAMPP/WAMP** - Local development
- **Docker** - Containerized deployment
- **VPS** - Full control hosting

## 🛠️ Development

### Project Structure
```
mu-tracker/
├── config.php              # Database configuration
├── index.php               # Main character list
├── dashboard.php            # Analytics dashboard
├── admin.php               # Admin panel
├── auth.php                # Authentication system
├── functions.php           # Core functions
├── analytics.php           # Analytics functions
├── vip_admin_enhancements.php # VIP/Admin features
├── error_handler.php       # Error handling
├── logs/                   # Log files
├── vendor/                 # Composer dependencies
└── assets/                 # CSS, JS, images
```

### Adding New Features
1. Create feature branch: `git checkout -b feature/new-feature`
2. Make changes and test locally
3. Commit changes: `git commit -m "Add new feature"`
4. Push branch: `git push origin feature/new-feature`
5. Create pull request

### Database Schema
The application uses these main tables:
- `users` - User accounts and authentication
- `characters` - Character data and tracking
- `character_history` - Historical character data
- `daily_progress` - Daily analytics
- `activity_logs` - User activity tracking
- `auth_logs` - Authentication events

## 🔒 Security

### Best Practices
- ✅ Password hashing with PHP's `password_hash()`
- ✅ CSRF protection on all forms
- ✅ SQL injection prevention with prepared statements
- ✅ Input sanitization and validation
- ✅ Rate limiting on sensitive operations
- ✅ Session security with secure cookies

### Production Security
- Change default passwords and secret keys
- Use HTTPS in production
- Regularly update dependencies
- Monitor error logs
- Implement proper backup procedures

## 📊 Analytics

### Tracking Metrics
- Character level progression
- Reset achievements
- Online/offline patterns
- Location changes
- Guild activities

### VIP Analytics
- Predictive modeling for level progression
- Efficiency recommendations
- Activity pattern analysis
- Performance comparisons

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable names
- Comment complex logic
- Keep functions focused and small

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Getting Help
- 📖 Check the [Wiki](https://github.com/yourusername/mu-tracker/wiki) for detailed guides
- 🐛 Report bugs in [Issues](https://github.com/yourusername/mu-tracker/issues)
- 💬 Join our [Discord](https://discord.gg/your-invite) for community support

### Common Issues
- **Database connection failed**: Check your database credentials
- **Characters not updating**: Verify cURL is enabled and URLs are correct
- **Permission denied**: Ensure web server has write access to logs directory

## 🎯 Roadmap

### Upcoming Features
- [ ] Mobile app companion
- [ ] Discord bot integration
- [ ] Multi-server support
- [ ] Advanced guild analytics
- [ ] Real-time notifications
- [ ] API for third-party integrations

### Version History
- **v2.0** - VIP features, admin panel, advanced analytics
- **v1.5** - User authentication, role-based access
- **v1.0** - Basic character tracking and auto-refresh

## 🙏 Acknowledgments

- MU Online community for inspiration
- Chart.js for beautiful charts
- AdminLTE for admin interface
- Bootstrap for responsive design

---

<div align="center">

**⭐ Star this repository if you find it useful!**

[Report Bug](https://github.com/yourusername/mu-tracker/issues) • [Request Feature](https://github.com/yourusername/mu-tracker/issues) • [Documentation](https://github.com/yourusername/mu-tracker/wiki)

</div>