# Delivery Inventory Check & Product Permission Feature

## Overview

This feature allows delivery men to check if products exist in the inventory and request permission to add new products when they receive items from local suppliers. It also includes comprehensive inventory management with automatic stock updates.

## Feature Components

### 1. Delivery Inventory Check (`delivery-inventory.php`)
- **For**: Delivery men (storeman role)
- **Purpose**: Check if a product exists in inventory using Product Code and Product Name
- **Functionality**:
  - Search existing inventory by product code/SKU and product name
  - If product exists: Shows product details, no action needed
  - If product doesn't exist: Shows permission request form

### 2. Product Permission Requests (`product-permissions.php`)
- **For**: Accountants
- **Purpose**: Review and approve/reject permission requests from delivery men
- **Functionality**:
  - View all permission requests with filtering options
  - Approve or reject requests with review notes
  - Automatically add approved products to inventory
  - Update existing product quantities if product code already exists

### 3. Inventory Management System
- **Automatic Stock Updates**: 
  - When products are delivered: Stock quantity decreases automatically
  - When delivery requests are approved: Stock quantity increases automatically
- **Automatic Product Code Generation**: 
  - 8-digit unique codes generated based on category
  - Format: [2-letter category prefix][6 random digits]
  - Examples: AC123456 (Accessories), BP789012 (Beauty), SR345678 (Saree)
- **Transaction Logging**: All inventory changes are logged for audit trail
- **Duplicate Prevention**: Prevents double-deduction of inventory
- **Stock Warnings**: Alerts when insufficient stock is delivered

### 4. Product Code System
**Category Mappings:**
- **Electronics**: EL (e.g., EL123456)
- **Accessories**: AC (e.g., AC123456) 
- **Beauty**: BP (e.g., BP123456)
- **Saree**: SR (e.g., SR123456)
- **Clothing**: CL (e.g., CL123456)
- **Home & Garden**: HG (e.g., HG123456)
- **Sports**: SP (e.g., SP123456)
- **Books**: BK (e.g., BK123456)
- **Toys**: TY (e.g., TY123456)
- **Jewelry**: JW (e.g., JW123456)
- **Shoes**: SH (e.g., SH123456)
- **Bags**: BG (e.g., BG123456)
- **Other**: OT (e.g., OT123456)

### 4. Inventory History (`inventory-history.php`)
- **For**: Accountants
- **Purpose**: View complete audit trail of all inventory changes
- **Functionality**:
  - Filter by product, transaction type, date range
  - View delivery reductions and restock additions
  - Track who made changes and when
  - Reference original bookings and requests

### 5. Supporting Files
- `ajax/get-request-details.php` - Load detailed request information
- `ajax/get-request-summary.php` - Load request summary for review modal
- `setup-delivery-feature.php` - Database setup script

## Inventory Management Flow

### Delivery Process:
1. **Booking Created**: Products reserved but not yet deducted from inventory
2. **Delivery Marked**: When delivery man marks as "delivered":
   - Stock quantities automatically reduced
   - Transaction logged with booking reference
   - Warnings shown if insufficient stock
   - Prevents duplicate deductions

### Restock Process:
1. **Request Submitted**: Delivery man requests permission for new product
2. **Request Approved**: When accountant approves:
   - If product exists: Quantity added to existing stock
   - If new product: New product created with initial stock
   - Transaction logged with request reference

### Transaction Types:
- **Delivery**: Stock reduction when products are delivered to customers
- **Restock**: Stock increase when new products are added via delivery requests
- **Adjustment**: Manual stock corrections (future feature)
- **Return**: Stock increase when products are returned (future feature)

## Database Schema

### New Table: `product_permission_requests`
```sql
CREATE TABLE product_permission_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_man_id INT NOT NULL,
    supplier_name VARCHAR(200) NOT NULL,
    product_code VARCHAR(100) NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    mrp DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    invoice_image VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    accountant_id INT NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (delivery_man_id) REFERENCES users(id),
    FOREIGN KEY (accountant_id) REFERENCES users(id)
);
```

### New Table: `inventory_transactions`
```sql
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type ENUM('delivery', 'restock', 'adjustment', 'return') NOT NULL,
    quantity_change INT NOT NULL, -- Positive for additions, negative for subtractions
    booking_id INT NULL, -- For delivery transactions
    request_id INT NULL, -- For restock from delivery requests
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (request_id) REFERENCES product_permission_requests(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### Modified Table: `products`
- Added `product_code` column for unique product identification

## Installation & Setup

1. **Run Database Setup**:
   - Login as an accountant
   - Visit `/setup-delivery-feature.php`
   - This will create the required tables and indexes

2. **File Upload Directory**:
   - The `uploads/invoices/` directory is created automatically
   - Includes security `.htaccess` file to prevent PHP execution

3. **Navigation Updates**:
   - Delivery men see "Inventory Check" link
   - Accountants see "Product Permissions" and "Inventory History" links

## User Workflow

### For Delivery Men:

1. **Receive Product from Local Supplier**
2. **Check Inventory**:
   - Go to "Inventory Check" page
   - Enter Product Code/SKU and Product Name
   - Click "Check Inventory"

3. **If Product Exists**:
   - System shows "Product found in inventory! No action needed."
   - No further action required

4. **If Product Doesn't Exist**:
   - System shows permission request form
   - Fill in supplier details, pricing, quantity
   - Optionally upload invoice image
   - Submit permission request

5. **Complete Deliveries**:
   - Mark bookings as "delivered" in delivery management
   - Inventory automatically updated when delivered
   - Stock warnings shown if insufficient stock

6. **Track Requests**:
   - View status of submitted requests
   - See approval/rejection decisions
   - Read accountant review notes

### For Accountants:

1. **Review Requests**:
   - Go to "Product Permissions" page
   - View all pending requests
   - Use filters to find specific requests

2. **Examine Details**:
   - Click "View" to see full request details
   - Review supplier information, pricing, quantities
   - Check uploaded invoice images

3. **Make Decision**:
   - Click "Approve" or "Reject"
   - Add review notes explaining decision
   - Submit review

4. **Monitor Inventory**:
   - View "Inventory History" for complete audit trail
   - Filter transactions by product, type, or date
   - Track all stock changes and their sources

5. **Automatic Processing**:
   - **If Approved**: Product automatically added to inventory
   - **If Product Code Exists**: Quantity added to existing product
   - **If New Product**: Creates new inventory item
   - **If Rejected**: Request marked as rejected with notes

## Security Features

- **CSRF Protection**: All forms use CSRF tokens
- **File Upload Security**: Only image files allowed, PHP execution blocked
- **Role-based Access**: Delivery men can only see their own requests
- **Input Sanitization**: All user inputs are sanitized
- **Database Constraints**: Foreign keys ensure data integrity
- **Transaction Logging**: Complete audit trail of all changes
- **Duplicate Prevention**: Prevents double inventory deductions

## Features & Benefits

1. **Inventory Validation**: Prevents duplicate products
2. **Approval Workflow**: Maintains control over inventory additions
3. **Automatic Stock Management**: Real-time inventory updates
4. **Audit Trail**: Complete record of all requests and inventory changes
5. **Automated Processing**: Approved items automatically added to inventory
6. **Image Documentation**: Invoice upload for verification
7. **Real-time Status**: Instant feedback on request status
8. **Filtering & Search**: Easy request and transaction management
9. **Stock Warnings**: Alerts for insufficient inventory
10. **Duplicate Prevention**: Prevents multiple deductions for same delivery

## File Structure
```
delivery-inventory.php          # Main inventory check page for delivery men
product-permissions.php         # Permission review page for accountants
inventory-history.php           # Inventory transaction history for accountants
ajax/
  ├── get-request-details.php   # AJAX endpoint for request details
  └── get-request-summary.php   # AJAX endpoint for review summary
uploads/
  ├── invoices/                 # Invoice image storage
  └── .htaccess                 # Security configuration
database/
  └── delivery_inventory_schema.sql  # Database schema
setup-delivery-feature.php     # One-time setup script
```

## Usage Examples

### Example 1: Product Delivery Process
- Customer orders 5 units of "Wireless Headphones" (Current stock: 10)
- Delivery man marks booking as "delivered"
- System automatically reduces stock from 10 to 5
- Transaction logged as "delivery" type with booking reference

### Example 2: New Product Request
- Delivery man checks "KB001" + "Wireless Keyboard"
- Product not found
- Fills request form with supplier "TechCorp", MRP ৳1500, Selling ৳1200, Qty 20
- Accountant reviews and approves
- Product automatically added to inventory with 20 units
- Transaction logged as "restock" type with request reference

### Example 3: Existing Product Restock
- Delivery man requests "WH001" + "Wireless Headphones Premium" (20 units)
- Product code "WH001" exists with current stock 5
- Accountant approves
- System adds 20 units to existing product (new total: 25)
- Transaction logged as "restock" type

### Example 4: Stock Warning
- Customer orders 15 units of product with only 10 in stock
- Delivery man marks as delivered
- System reduces stock to 0 and shows warning
- Warning: "Wireless Headphones had insufficient stock. Delivered: 15, Available: 10"

## Troubleshooting

1. **Database Errors**: Run setup script again
2. **Upload Issues**: Check uploads directory permissions
3. **Navigation Missing**: Clear browser cache
4. **Access Denied**: Verify user roles are correct
5. **Double Deduction**: System prevents this automatically via transaction logging
6. **Stock Discrepancies**: Check inventory history for audit trail
