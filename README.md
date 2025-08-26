# Ganesh Puja Fund Manager (Raw PHP + MySQL)

Lightweight, responsive, raw-PHP web application to manage collections and expenses for Ganesh Puja.

## 🚀 Quick Setup

### Option 1: Automated Installation (Recommended)
1. Upload all files to your web server
2. Navigate to `installation.php` in your browser
3. Follow the 3-step installation wizard:
   - Configure database connection
   - Create database tables automatically
   - Set up admin user account
4. Delete `installation.php` after completion for security

### Option 2: Manual Setup
1. Create MySQL database (e.g. puja_fund)
2. Import `db_schema.sql` into your database
3. Edit `db.php` with your database credentials
4. Access the application via `login.php`

## ✨ Features

### 🎨 Modern Design
- **Glass Morphism UI**: Beautiful translucent cards with backdrop blur effects
- **Responsive Design**: Optimized for desktop, tablet, and mobile devices
- **Smooth Animations**: Engaging slide-up, fade-in, and floating animations
- **Dark Theme**: Elegant gradient backgrounds with floating geometric shapes
- **Interactive Elements**: Hover effects, loading states, and micro-interactions

### 👥 User Management
- **Role-Based Access**: Manager and Member roles with different permissions
- **User Registration**: Managers can add new users to the system
- **Profile Management**: User profile viewing and management
- **Secure Authentication**: Password hashing and session management

### 💰 Financial Management
- **Transaction Tracking**: Record collections and expenses with detailed descriptions
- **Category System**: Organize expenses by type (decoration, food, supplies, etc.)
- **Real-Time Balance**: Live calculation of current fund balance
- **Transaction History**: Complete audit trail of all financial activities
- **Monthly Reports**: Summarized views of collections and expenses

### 📊 Dashboard & Analytics
- **Interactive Dashboard**: Overview of fund status with key metrics
- **Quick Actions**: Fast access to common tasks
- **Recent Transactions**: Latest activity at a glance
- **Fund Health Indicators**: Visual status of fund surplus/deficit
- **Member Statistics**: Track user contributions and activities

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Download & Upload**
   - Download all project files
   - Upload to your web server directory

2. **Automated Setup (Recommended)**
   - Navigate to `installation.php` in your browser
   - Complete the 3-step installation wizard
   - Delete `installation.php` after successful setup

3. **Manual Setup (Alternative)**
   - Create MySQL database
   - Import `db_schema.sql`
   - Configure `db.php` with database credentials
   - Create admin user via user management

4. **Access Application**
   - Navigate to your domain/folder
   - Login with your created admin credentials

## 📱 Usage Guide

### For Managers
- **Dashboard**: View complete fund overview and statistics
- **Add Transactions**: Record both collections and expenses
- **Manage Users**: Add new members, change roles, delete users
- **View Reports**: Access detailed financial reports
- **User Management**: Full administrative control

### For Members
- **Dashboard**: View fund status and recent activity
- **Add Transactions**: Record collections and expenses
- **View History**: Access transaction history
- **Profile**: Manage personal account settings

## 🛠️ Technical Details

### Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Bootstrap 5.3.2
- **Icons**: Bootstrap Icons 1.11.0
- **Fonts**: Inter (Google Fonts)
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Architecture**: MVC-inspired structure

### File Structure
```
puja-fund/
├── index.php                  # Main dashboard
├── login.php                  # Authentication page
├── add.php                    # Add transaction form
├── transactions.php           # Transaction listing
├── users.php                  # User management (managers only)
├── report.php                 # Financial reports
├── db.php                     # Database connection
├── auth.php                   # Authentication middleware
├── installation.php           # Setup wizard (delete after use)
├── edit.php                   # Edit transaction form
├── delete.php                 # Delete transaction handler
├── logout.php                 # Logout handler
├── db_schema.sql              # Database structure & sample data
└── README.md                  # This file
```

### Database Schema
- **users**: User accounts with roles and authentication
- **transactions**: Financial records with categories and metadata
- **transaction_summary**: View for monthly summaries

## 🎯 Key Improvements

### From Previous Version
1. **Modern UI/UX**: Complete redesign with glass morphism and animations
2. **Responsive Design**: Mobile-first approach with fluid layouts
3. **Enhanced Security**: Improved authentication and input validation
4. **Better UX**: Loading states, notifications, and smooth interactions
5. **Category System**: Expense categorization for better organization
6. **User Management**: Complete admin panel for user administration
7. **Performance**: Optimized queries and database indexes

## 🔧 Customization

### Styling
- Modify CSS variables in `assets/css/style.css` to change colors and themes
- Update gradient backgrounds and glass morphism effects
- Customize animation timings and effects

### Features
- Add new expense categories in the add transaction form
- Extend user roles and permissions
- Add new report types and analytics
- Integrate with external payment systems

## 🔒 Security Features

- **Password Hashing**: Secure bcrypt password hashing
- **SQL Injection Protection**: Prepared statements throughout
- **XSS Prevention**: Input sanitization and output escaping
- **Session Management**: Secure session handling
- **Role-Based Access**: Proper authorization checks

## 📈 Future Enhancements

- **Email Notifications**: Alert users about important transactions
- **Export Features**: PDF/Excel export of reports
- **Mobile App**: Native mobile application
- **Multi-Language**: Support for regional languages
- **Advanced Analytics**: Charts and graphs for better insights
- **Backup System**: Automated database backups

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is open source and available under the MIT License.

## 📞 Support

For support and questions:
- Check the documentation above
- Review the code comments
- Contact your system administrator

---

**Built with ❤️ for the community** - Making puja fund management beautiful and efficient!
