# 🚀 TrackIt - Quick Start Setup Guide

## ⚡ Getting Started (3 Simple Steps)

### Step 1: Start Your Web Server
- Ensure **Apache** and **MySQL** are running
- The application should be accessible at: `http://localhost/trackit/`

### Step 2: Initialize the Database
1. Open your browser and go to: **`http://localhost/trackit/setup.php`**
2. The page will automatically create and configure the database
3. You should see a ✅ **"Database Setup Successful!"** message
4. **⚠️ Important:** Delete the `setup.php` file after setup for security

### Step 3: Login to the Application
1. Navigate to: **`http://localhost/trackit/`**
2. You'll be automatically redirected to the login page
3. Use one of these demo accounts:

| Role | Username | Password |
|------|----------|----------|
| **Moderator** | `admin_mod` | `password` |
| **Accountant** | `admin_acc` | `password` |
| **Storeman** | `admin_store` | `password` |

---

## ❓ Troubleshooting

### Issue: Nothing displays on the website

**Solution:**
1. Ensure MySQL is running
2. Run `setup.php` to initialize the database
3. Check PHP error logs for database connection errors

### Issue: "Database Connection Failed" error

**Causes & Fixes:**
- ❌ MySQL is not running → Start MySQL service
- ❌ Database doesn't exist → Run `setup.php`
- ❌ Wrong database credentials → Edit `includes/config.php`

### Issue: Login page appears but login fails

**Solution:**
- Make sure you ran `setup.php` first to create the users table
- Use the exact credentials: `admin_mod` / `password`
- Check that database connection is working

### Issue: CSS/Styling looks broken

**Solution:**
- Ensure `assets/css/main.css` is accessible
- Ensure `assets/js/main.js` is accessible
- Clear browser cache (Ctrl+Shift+Delete)

---

## 🔧 Database Configuration

If your MySQL credentials are different, edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');      // MySQL host
define('DB_NAME', 'trackit');        // Database name
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', '');               // MySQL password (blank by default)
```

---

## 📋 Default Demo Data

After setup, the system includes:

**Users (3 accounts):**
- Moderator: `admin_mod`
- Accountant: `admin_acc`
- Storeman: `admin_store`
- All have password: `password`

**Sample Products:**
- Wireless Headphones
- Smartphone Case
- Bluetooth Speaker

---

## 👥 User Roles & Features

### 👔 Moderator (`admin_mod`)
- View product inventory
- Create new booking requests
- Track their bookings
- Monitor low stock alerts

### 📊 Accountant (`admin_acc`)
- View all bookings
- Approve or reject bookings
- Manage payments
- Generate revenue reports
- Track payment status

### 📦 Storeman (`admin_store`)
- View assigned deliveries
- Update delivery status
- Manage tracking information
- Process shipments

---

## 🔄 Typical Workflow

1. **Moderator** creates a booking with products and customer info
2. **Accountant** reviews and approves the booking
3. **Storeman** prepares and delivers the order
4. **Accountant** updates payment status
5. **All Users** can view their respective dashboards

---

## 🛡️ Security Notes

✅ **After Setup:**
- Delete `setup.php` immediately
- Only share credentials with authorized users
- Change default passwords in production
- Use HTTPS in production

---

## 📞 Need Help?

If you encounter issues:
1. Check that MySQL is running
2. Run `setup.php` again
3. Verify database credentials in `includes/config.php`
4. Check web server error logs
5. Ensure PHP 7.4+ is installed

---

**Your inventory system is ready to use!** 🎉
