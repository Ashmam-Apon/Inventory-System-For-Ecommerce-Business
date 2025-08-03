# TrackIt - Inventory Management System

A comprehensive web-based inventory management system built with PHP, MySQL, HTML, CSS, and JavaScript. Designed for managing product inventory and fulfillment across three distinct user roles with clear workflows and automation.

## 🚀 Features

### 📊 Dashboard
- Role-specific dashboards with relevant statistics
- Real-time data updates
- Quick action buttons
- Visual analytics

### 👥 User Management
- **Moderator**: Creates booking requests, views inventory
- **Accountant**: Approves bookings, manages payments, generates reports
- **Storeman**: Handles delivery and dispatch operations

### 📦 Inventory Management
- Product catalog with categories
- Stock tracking
- Low stock alerts
- Search and filtering

### 📋 Booking System
- Multi-product booking creation
- Customer information management
- Payment type selection (Online Paid / Cash on Delivery)
- Approval workflow

### 🚚 Delivery Management
- Delivery company assignment
- Tracking number management
- Delivery status updates
- Notes and documentation

### 💰 Payment Processing
- Payment status tracking
- Multiple payment methods
- Transaction recording
- Revenue analytics

### 📈 Reports & Analytics
- Revenue statistics
- Monthly/weekly trends
- Top-selling products
- Payment method analysis
- Exportable reports

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Charts**: Chart.js
- **Icons**: Font Awesome 6
- **Styling**: Custom CSS with CSS Grid and Flexbox

## 📁 Project Structure

```
TrackIt/
├── assets/
│   ├── css/
│   │   └── main.css          # Main stylesheet
│   └── js/
│       └── main.js           # JavaScript functionality
├── database/
│   └── schema.sql            # Database schema
├── includes/
│   ├── config.php            # Configuration and utilities
│   ├── header.php            # Header template
│   └── footer.php            # Footer template
├── ajax/
│   └── booking-details.php   # AJAX endpoints
├── index.php                 # Entry point (redirects to login)
├── login.php                 # Login page
├── logout.php                # Logout handler
├── dashboard.php             # Role-specific dashboard
├── products.php              # Product catalog (Moderator)
├── create-booking.php        # Booking creation (Moderator)
├── bookings.php              # Booking management (Accountant)
├── deliveries.php            # Delivery management (Storeman)
├── payments.php              # Payment management (Accountant)
└── reports.php               # Reports & analytics (Accountant)
```

## 🔧 Installation

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Setup Steps

1. **Clone/Download** the project to your web server directory
2. **Database Setup**:
   - Create a new MySQL database named `trackit`
   - Import the schema: `mysql -u username -p trackit < database/schema.sql`
3. **Configuration**:
   - Edit `includes/config.php` to match your database credentials
   - Update `BASE_URL` if needed
4. **File Permissions**:
   - Ensure web server has read access to all files
   - Set appropriate permissions for uploads (if any)

### Database Configuration

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'trackit');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## 👤 Default Users

The system comes with three demo users (password: `password`):

| Role | Username | Purpose |
|------|----------|---------|
| Moderator | `admin_mod` | Create bookings, view products |
| Accountant | `admin_acc` | Approve bookings, manage payments |
| Storeman | `admin_store` | Handle deliveries |

## 🔄 Workflow

1. **Moderator** browses products and creates booking requests
2. **Accountant** reviews and approves/rejects bookings
3. **Storeman** processes approved bookings for delivery
4. **Accountant** updates payment status and tracks revenue

## 📱 Responsive Design

- Mobile-first approach
- Responsive navigation
- Adaptive layouts
- Touch-friendly interfaces
- Print-optimized styles

## 🔒 Security Features

- Session management
- CSRF protection
- Input sanitization
- SQL injection prevention
- Role-based access control

## 🎨 UI/UX Features

- Clean, modern interface
- Consistent color scheme
- Intuitive navigation
- Loading states
- Success/error notifications
- Modal dialogs
- Search and filtering
- Data tables with sorting

## 📊 Analytics & Reporting

- Revenue tracking
- Sales trends
- Product performance
- Payment analytics
- Exportable data (CSV)
- Printable reports

## 🔧 Customization

The system is built with modularity in mind:

- **CSS Variables**: Easy theme customization in `main.css`
- **Modular PHP**: Separate concerns across files
- **Configurable**: Database and app settings in `config.php`
- **Extensible**: Clear structure for adding features

## 🐛 Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📞 Support

For issues or questions:
- Check the documentation
- Review the code comments
- Create an issue in the repository

## 🔮 Future Enhancements

- API endpoints for mobile apps
- Email notifications
- Advanced reporting
- Multi-location support
- Barcode scanning
- Automated reordering

---

**TrackIt** - Streamlining inventory management with simplicity and efficiency.
