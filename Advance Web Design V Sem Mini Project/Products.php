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
$suppliers = [];
$products = [];
$form = [
    'product_id' => '',
    'sku' => '',
    'name' => '',
    'description' => '',
    'category' => '',
    'unit_price' => '0.00',
    'stock_quantity' => '0',
    'reorder_level' => '5',
    'supplier_id' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid product selected.');
            }

            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
            $stmt->execute([$id]);

            header('Location: products.php?status=deleted');
            exit;
        }

        $form = [
            'product_id' => trim($_POST['product_id'] ?? ''),
            'sku' => trim($_POST['sku'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'unit_price' => trim($_POST['unit_price'] ?? ''),
            'stock_quantity' => trim($_POST['stock_quantity'] ?? ''),
            'reorder_level' => trim($_POST['reorder_level'] ?? ''),
            'supplier_id' => trim($_POST['supplier_id'] ?? '')
        ];

        if ($form['sku'] === '' || $form['name'] === '') {
            throw new RuntimeException('SKU and product name are required.');
        }

        if (!is_numeric($form['unit_price']) || (float)$form['unit_price'] < 0) {
            throw new RuntimeException('Enter a valid, non-negative unit price.');
        }

        foreach (['stock_quantity', 'reorder_level'] as $field) {
            if (filter_var($form[$field], FILTER_VALIDATE_INT) === false || (int)$form[$field] < 0) {
                throw new RuntimeException('Stock quantity and reorder level must be whole numbers of 0 or more.');
            }
        }

        $supplierId = $form['supplier_id'] === '' ? null : (int)$form['supplier_id'];

        if ($supplierId !== null && $supplierId < 1) {
            throw new RuntimeException('Select a valid supplier.');
        }

        if ($action === 'update') {
            $id = filter_var($form['product_id'], FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid product selected.');
            }

            $stmt = $pdo->prepare(
                'UPDATE products
                 SET sku = ?, name = ?, description = ?, category = ?,
                     unit_price = ?, stock_quantity = ?, reorder_level = ?, supplier_id = ?
                 WHERE product_id = ?'
            );

            $stmt->execute([
                $form['sku'],
                $form['name'],
                $form['description'],
                $form['category'],
                $form['unit_price'],
                $form['stock_quantity'],
                $form['reorder_level'],
                $supplierId,
                $id
            ]);
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO products
                 (sku, name, description, category, unit_price, stock_quantity, reorder_level, supplier_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $form['sku'],
                $form['name'],
                $form['description'],
                $form['category'],
                $form['unit_price'],
                $form['stock_quantity'],
                $form['reorder_level'],
                $supplierId
            ]);
        }

        header('Location: products.php?status=saved');
        exit;
    }

    if (isset($_GET['edit'])) {
        $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

        if ($id && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
            $stmt->execute([$id]);
            $found = $stmt->fetch();

            if ($found) {
                $form = $found;
            } else {
                $error = 'Product not found.';
            }
        }
    }

    $suppliers = $pdo->query(
        'SELECT supplier_id, name FROM suppliers ORDER BY name'
    )->fetchAll();

    $products = $pdo->query(
        'SELECT p.*, s.name AS supplier_name
         FROM products p
         LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
         ORDER BY p.product_id DESC'
    )->fetchAll();

    if (isset($_GET['status']) && $_GET['status'] === 'saved') {
        $message = 'Product saved successfully.';
    } elseif (isset($_GET['status']) && $_GET['status'] === 'deleted') {
        $message = 'Product deleted successfully.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (PDOException $e) {
    if (($_POST['action'] ?? '') === 'delete') {
        $error = 'This product may be used in a purchase or sale, so it cannot be deleted.';
    } else {
        $error = 'Could not save the product. Check that the SKU is unique and the supplier exists.';
    }

    $suppliers = $pdo->query(
        'SELECT supplier_id, name FROM suppliers ORDER BY name'
    )->fetchAll();

    $products = $pdo->query(
        'SELECT p.*, s.name AS supplier_name
         FROM products p
         LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
         ORDER BY p.product_id DESC'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1100px; margin: auto; }
        h1, h2 { color: #183b5b; }
        form, .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, textarea, select { box-sizing: border-box; width: 100%; padding: 9px; margin-top: 5px; }
        button, .button { display: inline-block; padding: 8px 12px; border: 0; border-radius: 4px; background: #245b86; color: white; cursor: pointer; text-decoration: none; }
        .delete { background: #a33333; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
        .notice { padding: 12px; margin-bottom: 16px; background: #e7f5e9; }
        .error { padding: 12px; margin-bottom: 16px; background: #fde8e7; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { padding: 0; margin: 0; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Product Management</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php" aria-current="page">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php">Purchases</a>
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

    <section class="panel">
        <h2><?= $form['product_id'] ? 'Edit Product' : 'Add Product' ?></h2>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="product_id" value="<?= h($form['product_id']) ?>">
            <input type="hidden" name="action" value="<?= $form['product_id'] ? 'update' : 'create' ?>">

            <label for="sku">SKU *</label>
            <input id="sku" name="sku" required maxlength="40" value="<?= h($form['sku']) ?>">

            <label for="name">Product Name *</label>
            <input id="name" name="name" required maxlength="120" value="<?= h($form['name']) ?>">

            <label for="category">Category</label>
            <input id="category" name="category" maxlength="80" value="<?= h($form['category']) ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= h($form['description']) ?></textarea>

            <label for="unit_price">Unit Price</label>
            <input id="unit_price" name="unit_price" type="number" min="0" step="0.01" required value="<?= h($form['unit_price']) ?>">

            <label for="stock_quantity">Stock Quantity</label>
            <input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" required value="<?= h($form['stock_quantity']) ?>">

            <label for="reorder_level">Low Stock Alert Level</label>
            <input id="reorder_level" name="reorder_level" type="number" min="0" step="1" required value="<?= h($form['reorder_level']) ?>">

            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">No supplier selected</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= h($supplier['supplier_id']) ?>"
                        <?= (string)$form['supplier_id'] === (string)$supplier['supplier_id'] ? 'selected' : '' ?>>
                        <?= h($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p><button type="submit"><?= $form['product_id'] ? 'Update Product' : 'Save Product' ?></button></p>
        </form>
    </section>

    <section>
        <h2>Product List</h2>
        <table>
            <thead>
            <tr>
                <th>SKU</th><th>Name</th><th>Category</th><th>Price</th>
                <th>Stock</th><th>Supplier</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= h($product['sku']) ?></td>
                    <td><?= h($product['name']) ?></td>
                    <td><?= h($product['category']) ?></td>
                    <td><?= number_format((float)$product['unit_price'], 2) ?></td>
                    <td><?= h($product['stock_quantity']) ?></td>
                    <td><?= h($product['supplier_name'] ?? '') ?></td>
                    <td>
                        <div class="actions">
                            <a class="button" href="products.php?edit=<?= h($product['product_id']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="product_id" value="<?= h($product['product_id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="delete" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$products): ?>
                <tr><td colspan="7">No products have been added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>