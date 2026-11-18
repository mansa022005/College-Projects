<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DB.php';

if (!isset($pdo)) {
    exit('DB.php must create a PDO connection in the $pdo variable.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $customerIdText = trim($_POST['customer_id'] ?? '');
        $customerId = $customerIdText === ''
            ? null
            : filter_var($customerIdText, FILTER_VALIDATE_INT);

        if ($customerIdText !== '' && (!$customerId || $customerId < 1)) {
            throw new RuntimeException('Select a valid customer.');
        }

        $productIds = $_POST['item_product'] ?? [];
        $quantities = $_POST['item_quantity'] ?? [];
        $notes = trim($_POST['notes'] ?? '');

        $items = [];
        $rowCount = max(count($productIds), count($quantities));

        for ($i = 0; $i < $rowCount; $i++) {
            $productIdText = trim($productIds[$i] ?? '');
            $quantityText = trim($quantities[$i] ?? '');

            if ($productIdText === '' && $quantityText === '') {
                continue;
            }

            $productId = filter_var($productIdText, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantityText, FILTER_VALIDATE_INT);

            if (!$productId || $productId < 1) {
                throw new RuntimeException('Choose a valid product for each sale line.');
            }
            if (!$quantity || $quantity < 1) {
                throw new RuntimeException('Each sale quantity must be a whole number greater than zero.');
            }

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity
            ];
        }

        if (!$items) {
            throw new RuntimeException('Add at least one product to the sale.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO sales (customer_id, total_amount, notes)
             VALUES (?, 0, ?)'
        );
        $stmt->execute([$customerId, $notes]);
        $saleId = (int)$pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO sale_items
             (sale_id, product_id, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $decreaseStock = $pdo->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity - ?
             WHERE product_id = ?'
        );

        $total = 0;

        foreach ($items as $item) {
            $findProduct = $pdo->prepare(
                'SELECT product_id, name, unit_price, stock_quantity
                 FROM products
                 WHERE product_id = ?
                 FOR UPDATE'
            );
            $findProduct->execute([$item['product_id']]);
            $product = $findProduct->fetch();

            if (!$product) {
                throw new RuntimeException('A selected product no longer exists. Refresh and try again.');
            }

            if ((int)$product['stock_quantity'] < $item['quantity']) {
                throw new RuntimeException(
                    'Not enough stock for "' . $product['name'] .
                    '". Available: ' . $product['stock_quantity'] . '.'
                );
            }

            $unitPrice = (float)$product['unit_price'];
            $lineTotal = round($item['quantity'] * $unitPrice, 2);
            $total += $lineTotal;

            $insertItem->execute([
                $saleId,
                $item['product_id'],
                $item['quantity'],
                $unitPrice,
                $lineTotal
            ]);

            $decreaseStock->execute([
                $item['quantity'],
                $item['product_id']
            ]);
        }

        $updateTotal = $pdo->prepare(
            'UPDATE sales SET total_amount = ? WHERE sale_id = ?'
        );
        $updateTotal->execute([round($total, 2), $saleId]);

        $pdo->commit();

        header('Location: sales.php?status=saved');
        exit;
    }
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = 'The sale could not be saved. Check the customer, products, and database tables.';
}

$customers = $pdo->query(
    'SELECT customer_id, name FROM customers ORDER BY name'
)->fetchAll();

$products = $pdo->query(
    'SELECT product_id, sku, name, unit_price, stock_quantity
     FROM products ORDER BY name'
)->fetchAll();

$sales = $pdo->query(
    'SELECT s.sale_id, s.sale_date, s.total_amount, c.name AS customer_name
     FROM sales s
     LEFT JOIN customers c ON s.customer_id = c.customer_id
     ORDER BY s.sale_id DESC
     LIMIT 20'
)->fetchAll();

if (($_GET['status'] ?? '') === 'saved') {
    $message = 'Sale saved and product stock updated.';
}

$productOptions = '<option value="">Choose product</option>';
foreach ($products as $product) {
    $label = $product['sku'] . ' - ' . $product['name'] .
        ' | Price: ' . number_format((float)$product['unit_price'], 2) .
        ' | Stock: ' . $product['stock_quantity'];
    $productOptions .= '<option value="' . h($product['product_id']) . '">' .
        h($label) . '</option>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1100px; margin: auto; }
        h1, h2 { color: #183b5b; }
        .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, textarea, select { box-sizing: border-box; width: 100%; padding: 9px; margin-top: 5px; }
        button { display: inline-block; padding: 9px 13px; border: 0; border-radius: 4px; background: #245b86; color: white; cursor: pointer; }
        .remove { background: #a33333; }
        .notice { padding: 12px; margin-bottom: 16px; background: #e7f5e9; }
        .error { padding: 12px; margin-bottom: 16px; background: #fde8e7; }
        .item-row { display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Sales Management</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php">Purchases</a>
        <a href="Sales.php" aria-current="page">Sales</a>
        <a href="Reports.php">Reports</a>
        <a href="logout.php" class="logout-link">Sign out</a>
    </nav>

    <?php if ($message): ?>
        <p class="notice"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if (!$products): ?>
        <div class="panel">Add products before recording a sale.</div>
    <?php else: ?>
        <section class="panel">
            <h2>Record a Sale</h2>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                <label for="customer_id">Customer (optional)</label>
                <select id="customer_id" name="customer_id">
                    <option value="">Walk-in customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= h($customer['customer_id']) ?>">
                            <?= h($customer['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="items">
                    <div class="item-row">
                        <div>
                            <label>Product *</label>
                            <select name="item_product[]" required>
                                <?= $productOptions ?>
                            </select>
                        </div>
                        <div>
                            <label>Quantity *</label>
                            <input name="item_quantity[]" type="number" min="1" step="1" required>
                        </div>
                        <button type="button" class="remove" onclick="removeRow(this)">Remove</button>
                    </div>
                </div>

                <p><button type="button" onclick="addRow()">Add Another Product</button></p>

                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="255"></textarea>

                <p><button type="submit">Save Sale</button></p>
            </form>
        </section>
    <?php endif; ?>

    <section>
        <h2>Recent Sales</h2>
        <table>
            <thead>
            <tr><th>Sale ID</th><th>Date</th><th>Customer</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><?= h($sale['sale_id']) ?></td>
                    <td><?= h($sale['sale_date']) ?></td>
                    <td><?= h($sale['customer_name'] ?: 'Walk-in customer') ?></td>
                    <td><?= number_format((float)$sale['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$sales): ?>
                <tr><td colspan="4">No sales recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<template id="item-template">
    <div class="item-row">
        <div>
            <label>Product *</label>
            <select name="item_product[]" required>
                <?= $productOptions ?>
            </select>
        </div>
        <div>
            <label>Quantity *</label>
            <input name="item_quantity[]" type="number" min="1" step="1" required>
        </div>
        <button type="button" class="remove" onclick="removeRow(this)">Remove</button>
    </div>
</template>

<script>
function addRow() {
    const template = document.getElementById('item-template');
    document.getElementById('items').appendChild(template.content.cloneNode(true));
}

function removeRow(button) {
    const rows = document.querySelectorAll('#items .item-row');
    if (rows.length > 1) {
        button.closest('.item-row').remove();
    }
}
</script>
</body>
</html>