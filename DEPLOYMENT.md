# Puja Fund - Deployment Guide

## System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- mod_rewrite enabled (for Apache)

## Installation Steps

### 1. Upload Files
Upload all files to your web server's document root or subdirectory.

### 2. Database Setup
1. Create a new MySQL database named `puja_fund`
2. Import the database schema:
   ```sql
   mysql -u username -p puja_fund < db_schema.sql
   ```
3. Or run the SQL commands from `db_schema.sql` in your database management tool

### 3. Configure Database Connection
Edit `db.php` and update the database credentials:
```php
$DB_HOST = 'your_host';        // Usually 'localhost'
$DB_USER = 'your_username';    // Your database username
$DB_PASS = 'your_password';    // Your database password
$DB_NAME = 'puja_fund';        // Database name
```

### 4. Set Permissions
Ensure proper file permissions:
- Files: 644
- Directories: 755
- Make sure PHP can read all files

### 5. Initial Setup
1. Navigate to your website URL
2. If no users exist, the system will redirect to `installation.php`
3. Create the first manager account
4. Login and start using the application

## Features
- ✅ Multi-language support (English/Bengali)
- ✅ Responsive design (mobile-friendly)
- ✅ Transaction management (collections/expenses)
- ✅ User management (manager/member roles)
- ✅ Financial reports with date filtering
- ✅ Dashboard with year-wise filtering
- ✅ Modern glassmorphism UI design

## File Structure
```
puja-fund/
├── index.php           # Dashboard
├── login.php          # Login page
├── transactions.php   # Transaction management
├── users.php          # User management (managers only)
├── report.php         # Financial reports (managers only)
├── edit.php           # Edit transactions
├── delete.php         # Delete transactions
├── installation.php   # Initial setup
├── auth.php           # Authentication handler
├── db.php             # Database configuration
├── lang.php           # Language translations
├── logout.php         # Logout handler
├── db_schema.sql      # Database schema
└── README.md          # Project documentation
```

## Security Notes
- Change default database credentials
- Use strong passwords for user accounts
- Keep PHP and MySQL updated
- Consider enabling HTTPS
- Regular database backups recommended

## Support
- The application includes Bengali language support
- All pages are mobile-responsive
- Modern browser compatibility required
- UTF-8 encoding for proper Bengali text display

## Default Login
After installation, login with the manager account you created during setup.

## Troubleshooting
- If Bengali text doesn't display properly, ensure UTF-8 charset is set
- Check database connection if pages show errors
- Verify file permissions if uploads fail
- Clear browser cache if styling issues occur
