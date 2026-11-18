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

function validDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');
$from = $_GET['from'] ?? $defaultFrom;
$to = $_GET['to'] ?? $defaultTo;
$error = '';

if (!validDate($from) || !validDate($to) || $from > $to) {
    $error = 'Enter a valid date range. The start date must not be after the end date.';
    $from = $defaultFrom;
    $to = $defaultTo;
}

$inventory = $pdo->query(
    'SELECT
        COUNT(*) AS product_count,
        COALESCE(SUM(stock_quantity), 0) AS total_units,
        COALESCE(SUM(stock_quantity * unit_price), 0) AS stock_value
     FROM products'
)->fetch();

$lowStock = $pdo->query(
    'SELECT sku, name, stock_quantity, reorder_level
     FROM products
     WHERE stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC, name ASC'
)->fetchAll();

$salesStmt = $pdo->prepare(
    'SELECT COUNT(*) AS transaction_count,
            COALESCE(SUM(total_amount), 0) AS total_amount
     FROM sales
     WHERE sale_date >= ? AND sale_date < DATE_ADD(?, INTERVAL 1 DAY)'
);
$salesStmt->execute([$from, $to]);
$salesSummary = $salesStmt->fetch();

$purchasesStmt = $pdo->prepare(
    'SELECT COUNT(*) AS transaction_count,
            COALESCE(SUM(total_amount), 0) AS total_amount
     FROM purchases
     WHERE purchase_date >= ? AND purchase_date < DATE_ADD(?, INTERVAL 1 DAY)'
);
$purchasesStmt->execute([$from, $to]);
$purchasesSummary = $purchasesStmt->fetch();

$topProductsStmt = $pdo->prepare(
    'SELECT p.sku, p.name,
            SUM(si.quantity) AS units_sold,
            SUM(si.line_total) AS sales_amount
     FROM sale_items si
     JOIN sales s ON si.sale_id = s.sale_id
     JOIN products p ON si.product_id = p.product_id
     WHERE s.sale_date >= ? AND s.sale_date < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY p.product_id, p.sku, p.name
     ORDER BY units_sold DESC, p.name ASC
     LIMIT 5'
);
$topProductsStmt->execute([$from, $to]);
$topProducts = $topProductsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ERP Reports</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1100px; margin: auto; }
        h1, h2 { color: #183b5b; }
        nav { margin-bottom: 20px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin: 20px 0; }
        .card, .panel { background: white; padding: 18px; border-radius: 8px; }
        .card strong { display: block; margin-top: 8px; font-size: 1.35rem; }
        form { background: white; padding: 16px; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
        label { display: block; font-weight: bold; }
        input { padding: 8px; margin-top: 5px; }
        button { padding: 9px 14px; border: 0; border-radius: 4px; background: #245b86; color: white; cursor: pointer; }
        .error { padding: 12px; background: #fde8e7; }
        table { width: 100%; border-collapse: collapse; background: white; margin-bottom: 24px; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Reports Dashboard</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php">Purchases</a>
        <a href="Sales.php">Sales</a>
        <a href="Reports.php" aria-current="page">Reports</a>
        <a href="logout.php" class="logout-link">Sign out</a>
    </nav>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="get">
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= h($from) ?>" required>
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= h($to) ?>" required>
        </div>
        <button type="submit">Update Report</button>
    </form>

    <section class="cards">
        <div class="card">Products<strong><?= h($inventory['product_count']) ?></strong></div>
        <div class="card">Units in Stock<strong><?= h($inventory['total_units']) ?></strong></div>
        <div class="card">Stock Value at Selling Price<strong><?= number_format((float)$inventory['stock_value'], 2) ?></strong></div>
        <div class="card">Sales in Selected Period<strong><?= number_format((float)$salesSummary['total_amount'], 2) ?></strong></div>
        <div class="card">Purchases in Selected Period<strong><?= number_format((float)$purchasesSummary['total_amount'], 2) ?></strong></div>
    </section>

    <section>
        <h2>Selected Period Summary</h2>
        <p>
            <?= h($from) ?> to <?= h($to) ?>:
            <?= h($salesSummary['transaction_count']) ?> sales and
            <?= h($purchasesSummary['transaction_count']) ?> purchases.
        </p>
    </section>

    <section>
        <h2>Low Stock Products</h2>
        <table>
            <thead>
            <tr><th>SKU</th><th>Product</th><th>Current Stock</th><th>Reorder Level</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lowStock as $product): ?>
                <tr>
                    <td><?= h($product['sku']) ?></td>
                    <td><?= h($product['name']) ?></td>
                    <td><?= h($product['stock_quantity']) ?></td>
                    <td><?= h($product['reorder_level']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?>
                <tr><td colspan="4">No products are currently at or below their reorder level.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Top Products by Units Sold</h2>
        <table>
            <thead>
            <tr><th>SKU</th><th>Product</th><th>Units Sold</th><th>Sales Amount</th></tr>
            </thead>
            <tbody>
            <?php foreach ($topProducts as $product): ?>
                <tr>
                    <td><?= h($product['sku']) ?></td>
                    <td><?= h($product['name']) ?></td>
                    <td><?= h($product['units_sold']) ?></td>
                    <td><?= number_format((float)$product['sales_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$topProducts): ?>
                <tr><td colspan="4">No sales in this date range yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>