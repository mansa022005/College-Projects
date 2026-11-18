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

        $supplierId = filter_var($_POST['supplier_id'] ?? '', FILTER_VALIDATE_INT);
        $productIds = $_POST['item_product'] ?? [];
        $quantities = $_POST['item_quantity'] ?? [];
        $costs = $_POST['item_cost'] ?? [];
        $notes = trim($_POST['notes'] ?? '');

        if (!$supplierId || $supplierId < 1) {
            throw new RuntimeException('Select a supplier.');
        }

        $items = [];
        $rowCount = max(count($productIds), count($quantities), count($costs));

        for ($i = 0; $i < $rowCount; $i++) {
            $productIdText = trim($productIds[$i] ?? '');
            $quantityText = trim($quantities[$i] ?? '');
            $costText = trim($costs[$i] ?? '');

            if ($productIdText === '' && $quantityText === '' && $costText === '') {
                continue;
            }

            $productId = filter_var($productIdText, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantityText, FILTER_VALIDATE_INT);

            if (!$productId || $productId < 1) {
                throw new RuntimeException('Choose a valid product for each purchase line.');
            }
            if (!$quantity || $quantity < 1) {
                throw new RuntimeException('Each purchase quantity must be a whole number greater than zero.');
            }
            if (!is_numeric($costText) || (float)$costText < 0) {
                throw new RuntimeException('Enter a valid, non-negative cost price for each item.');
            }

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'cost_price' => (float)$costText
            ];
        }

        if (!$items) {
            throw new RuntimeException('Add at least one product to the purchase.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO purchases (supplier_id, total_amount, notes)
             VALUES (?, 0, ?)'
        );
        $stmt->execute([$supplierId, $notes]);
        $purchaseId = (int)$pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO purchase_items
             (purchase_id, product_id, quantity, cost_price, line_total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $increaseStock = $pdo->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + ?
             WHERE product_id = ?'
        );

        $total = 0;

        foreach ($items as $item) {
            $check = $pdo->prepare(
                'SELECT product_id FROM products WHERE product_id = ? FOR UPDATE'
            );
            $check->execute([$item['product_id']]);

            if (!$check->fetch()) {
                throw new RuntimeException('A selected product no longer exists. Refresh and try again.');
            }

            $lineTotal = round($item['quantity'] * $item['cost_price'], 2);
            $total += $lineTotal;

            $insertItem->execute([
                $purchaseId,
                $item['product_id'],
                $item['quantity'],
                $item['cost_price'],
                $lineTotal
            ]);

            $increaseStock->execute([
                $item['quantity'],
                $item['product_id']
            ]);
        }

        $updateTotal = $pdo->prepare(
            'UPDATE purchases SET total_amount = ? WHERE purchase_id = ?'
        );
        $updateTotal->execute([round($total, 2), $purchaseId]);

        $pdo->commit();

        header('Location: purchases.php?status=saved');
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
    $error = 'The purchase could not be saved. Check the supplier, products, and database tables.';
}

$suppliers = $pdo->query(
    'SELECT supplier_id, name FROM suppliers ORDER BY name'
)->fetchAll();

$products = $pdo->query(
    'SELECT product_id, sku, name, unit_price, stock_quantity
     FROM products ORDER BY name'
)->fetchAll();

$purchases = $pdo->query(
    'SELECT p.purchase_id, p.purchase_date, p.total_amount, s.name AS supplier_name
     FROM purchases p
     JOIN suppliers s ON p.supplier_id = s.supplier_id
     ORDER BY p.purchase_id DESC
     LIMIT 20'
)->fetchAll();

if (($_GET['status'] ?? '') === 'saved') {
    $message = 'Purchase saved and product stock updated.';
}

$productOptions = '<option value="">Choose product</option>';
foreach ($products as $product) {
    $label = $product['sku'] . ' - ' . $product['name'] .
        ' (stock: ' . $product['stock_quantity'] . ')';
    $productOptions .= '<option value="' . h($product['product_id']) . '">' .
        h($label) . '</option>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Record Purchase</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1100px; margin: auto; }
        h1, h2 { color: #183b5b; }
        .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, textarea, select { box-sizing: border-box; width: 100%; padding: 9px; margin-top: 5px; }
        button, .button { display: inline-block; padding: 9px 13px; border: 0; border-radius: 4px; background: #245b86; color: white; cursor: pointer; text-decoration: none; }
        .remove { background: #a33333; }
        .notice { padding: 12px; margin-bottom: 16px; background: #e7f5e9; }
        .error { padding: 12px; margin-bottom: 16px; background: #fde8e7; }
        .item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
        .links { margin-bottom: 18px; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Purchase Management</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php" aria-current="page">Purchases</a>
        <a href="Sales.php">Sales</a>
        <a href="Reports.php">Reports</a>
        <a href="logout.php" class="logout-link">Sign out</a>
    </nav>

    <?php if ($message): ?>
        <p class="notice"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if (!$suppliers || !$products): ?>
        <div class="panel">
            Add at least one supplier and one product before recording a purchase.
        </div>
    <?php else: ?>
        <section class="panel">
            <h2>Record a Purchase</h2>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                <label for="supplier_id">Supplier *</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">Choose supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= h($supplier['supplier_id']) ?>">
                            <?= h($supplier['name']) ?>
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
                        <div>
                            <label>Cost per unit *</label>
                            <input name="item_cost[]" type="number" min="0" step="0.01" required>
                        </div>
                        <button type="button" class="remove" onclick="removeRow(this)">Remove</button>
                    </div>
                </div>

                <p>
                    <button type="button" onclick="addRow()">Add Another Product</button>
                </p>

                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="255"></textarea>

                <p><button type="submit">Save Purchase</button></p>
            </form>
        </section>
    <?php endif; ?>

    <section>
        <h2>Recent Purchases</h2>
        <table>
            <thead>
            <tr><th>Purchase ID</th><th>Date</th><th>Supplier</th><th>Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td><?= h($purchase['purchase_id']) ?></td>
                    <td><?= h($purchase['purchase_date']) ?></td>
                    <td><?= h($purchase['supplier_name']) ?></td>
                    <td><?= number_format((float)$purchase['total_amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$purchases): ?>
                <tr><td colspan="4">No purchases recorded yet.</td></tr>
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
        <div>
            <label>Cost per unit *</label>
            <input name="item_cost[]" type="number" min="0" step="0.01" required>
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