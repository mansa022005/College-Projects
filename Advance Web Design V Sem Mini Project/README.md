Small Business Inventory and Sales ERP

A lightweight PHP and MySQL web application for managing a small business's products, suppliers, customers, purchases, sales, and inventory reports. The dashboard summarizes current stock, low-stock items, sales for today, and recent sales.

## Features

- **Dashboard:** product count, total stock units, low-stock count, today's sales, and recent sales.
- **Products:** create, update, and delete product records with SKU, category, price, stock quantity, reorder level, and supplier.
- **Suppliers and customers:** maintain contact records and link them to products, purchases, and sales.
- **Purchases:** record items received from suppliers; purchase quantities increase product stock.
- **Sales:** record customer or walk-in sales; sale quantities reduce product stock, with checks against available inventory.
- **Reports:** view inventory value, low-stock products, sales and purchase totals for a selected date range, and top products by units sold.
- **Responsive styling:** shared styles in `assets/app.css` adapt the interface for smaller screens.

## Requirements

- PHP with the PDO MySQL extension enabled
- MySQL or MariaDB
- Apache (XAMPP is a convenient local setup)
- A browser

## Setup with XAMPP

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Copy or clone the repository into XAMPP's `htdocs` directory. The project folder is `Advance Web Design V Sem Mini Project`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin` and create a database named `small_business_erp`.
4. Select that database, choose **Import**, and import `small_business_erp.sql`. The dump creates the tables and includes demonstration records.
5. Check the connection settings in `DB.php`. The defaults are host `127.0.0.1`, database `small_business_erp`, user `root`, and an empty password. Update them to match your local MySQL account if needed.
6. Open the application in your browser. If the repository is cloned directly into `htdocs`, use:

   ```text
   http://localhost/College-Projects/Advance%20Web%20Design%20V%20Sem%20Mini%20Project/Index.php
   ```

   If you placed the project folder elsewhere under `htdocs`, adjust the URL to match that location.

## Project files

```text
Advance Web Design V Sem Mini Project/
├── Index.php                 # Dashboard
├── Products.php              # Product and stock management
├── Suppliers.php             # Supplier records
├── Customers.php             # Customer records
├── Purchases.php             # Purchase entry and stock receiving
├── Sales.php                 # Sales entry and stock reduction
├── Reports.php               # Inventory and date-range reports
├── DB.php                    # PDO database connection
├── small_business_erp.sql    # Database schema and demo data
├── assets/
│   └── app.css               # Shared responsive styles
└── Small_Business_Inventory_and_Sales_ERP_Report.pdf
```

## Database overview

The schema uses the `suppliers`, `customers`, `products`, `purchases`, `purchase_items`, `sales`, and `sale_items` tables. Purchases and sales store line items separately from their transaction headers. Product stock is adjusted when purchases and sales are recorded. Foreign-key relationships keep transaction line items and master records linked consistently; deleting a customer preserves historical sales by clearing the customer reference.

## Notes

This project is intended for local learning and demonstration. `DB.php` contains local connection defaults; configure credentials for your environment and do not expose a development database directly to the public internet. The project does not include a production deployment or authentication setup.

## Report

See [Small_Business_Inventory_and_Sales_ERP_Report.pdf](Small_Business_Inventory_and_Sales_ERP_Report.pdf) for the project report.
