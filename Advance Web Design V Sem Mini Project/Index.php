<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DB.php';

if (!isset($pdo)) {
    exit('DB.php must create a PDO connection in the $pdo variable.');
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$inventory = $pdo->query(
    'SELECT
        COUNT(*) AS product_count,
        COALESCE(SUM(stock_quantity), 0) AS total_units,
        COALESCE(SUM(CASE WHEN stock_quantity <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_count
     FROM products'
)->fetch();

$todaySalesStmt = $pdo->query(
    'SELECT COALESCE(SUM(total_amount), 0)
     FROM sales
     WHERE sale_date >= CURDATE()
       AND sale_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
);
$todaySales = (float)$todaySalesStmt->fetchColumn();

$recentSales = $pdo->query(
    'SELECT s.sale_id, s.sale_date, s.total_amount, c.name AS customer_name
     FROM sales s
     LEFT JOIN customers c ON s.customer_id = c.customer_id
     ORDER BY s.sale_id DESC
     LIMIT 5'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Small Business ERP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1100px; margin: auto; }
        h1, h2 { color: #183b5b; }
        nav { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
        nav a { padding: 10px 14px; border-radius: 5px; background: #245b86; color: white; text-decoration: none; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin: 22px 0; }
        .card, .panel { background: white; padding: 18px; border-radius: 8px; }
        .card strong { display: block; margin-top: 8px; font-size: 1.4rem; }
        .alert { color: #9c2f2f; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Small Business Inventory and Sales ERP</h1>
    <p>Manage products, suppliers, customers, purchases, sales, and stock reports.</p>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php" aria-current="page">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php">Purchases</a>
        <a href="Sales.php">Sales</a>
        <a href="Reports.php">Reports</a>
        <a href="logout.php" class="logout-link">Sign out</a>
    </nav>

    <section class="cards">
        <div class="card">
            Products
            <strong><?= h($inventory['product_count']) ?></strong>
        </div>
        <div class="card">
            Units in Stock
            <strong><?= h($inventory['total_units']) ?></strong>
        </div>
        <div class="card">
            Low Stock Products
            <strong class="<?= (int)$inventory['low_stock_count'] > 0 ? 'alert' : '' ?>">
                <?= h($inventory['low_stock_count']) ?>
            </strong>
        </div>
        <div class="card">
            Sales Today
            <strong><?= number_format($todaySales, 2) ?></strong>
        </div>
    </section>

    <section class="panel">
        <h2>Recent Sales</h2>
        <table>
            <thead>
            <tr><th>Sale ID</th><th>Date</th><th>Customer</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentSales as $sale): ?>
                <tr>
                    <td><?= h($sale['sale_id']) ?></td>
                    <td><?= h($sale['sale_date']) ?></td>
                    <td><?= h($sale['customer_name'] ?: 'Walk-in customer') ?></td>
                    <td><?= number_format((float)$sale['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$recentSales): ?>
                <tr><td colspan="4">No sales have been recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>