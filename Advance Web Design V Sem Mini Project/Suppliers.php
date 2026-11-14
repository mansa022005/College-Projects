<?php
session_start();
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
$form = [
    'supplier_id' => '',
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = filter_var($_POST['supplier_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid supplier selected.');
            }

            $stmt = $pdo->prepare('DELETE FROM suppliers WHERE supplier_id = ?');
            $stmt->execute([$id]);

            header('Location: suppliers.php?status=deleted');
            exit;
        }

        $form = [
            'supplier_id' => trim($_POST['supplier_id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ];

        if ($form['name'] === '') {
            throw new RuntimeException('Supplier name is required.');
        }

        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        if ($action === 'update') {
            $id = filter_var($form['supplier_id'], FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid supplier selected.');
            }

            $stmt = $pdo->prepare(
                'UPDATE suppliers
                 SET name = ?, phone = ?, email = ?, address = ?
                 WHERE supplier_id = ?'
            );
            $stmt->execute([
                $form['name'],
                $form['phone'],
                $form['email'],
                $form['address'],
                $id
            ]);
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO suppliers (name, phone, email, address)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['name'],
                $form['phone'],
                $form['email'],
                $form['address']
            ]);
        }

        header('Location: suppliers.php?status=saved');
        exit;
    }

    if (isset($_GET['edit'])) {
        $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

        if ($id && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_id = ?');
            $stmt->execute([$id]);
            $found = $stmt->fetch();

            if ($found) {
                $form = $found;
            } else {
                $error = 'Supplier not found.';
            }
        }
    }

    $suppliers = $pdo->query(
        'SELECT * FROM suppliers ORDER BY supplier_id DESC'
    )->fetchAll();

    if (($_GET['status'] ?? '') === 'saved') {
        $message = 'Supplier saved successfully.';
    } elseif (($_GET['status'] ?? '') === 'deleted') {
        $message = 'Supplier deleted successfully.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
    $suppliers = $pdo->query(
        'SELECT * FROM suppliers ORDER BY supplier_id DESC'
    )->fetchAll();
} catch (PDOException $e) {
    $error = 'Could not delete this supplier because it may be linked to a purchase.';
    $suppliers = $pdo->query(
        'SELECT * FROM suppliers ORDER BY supplier_id DESC'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1000px; margin: auto; }
        h1, h2 { color: #183b5b; }
        form, .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, textarea { box-sizing: border-box; width: 100%; padding: 9px; margin-top: 5px; }
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
    <h1>Supplier Management</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php" aria-current="page">Suppliers</a>
        <a href="Customers.php">Customers</a>
        <a href="Purchases.php">Purchases</a>
        <a href="Sales.php">Sales</a>
        <a href="Reports.php">Reports</a>
    </nav>

    <?php if ($message): ?>
        <p class="notice"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $form['supplier_id'] ? 'Edit Supplier' : 'Add Supplier' ?></h2>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="supplier_id" value="<?= h($form['supplier_id']) ?>">
            <input type="hidden" name="action" value="<?= $form['supplier_id'] ? 'update' : 'create' ?>">

            <label for="name">Supplier Name *</label>
            <input id="name" name="name" required maxlength="100" value="<?= h($form['name']) ?>">

            <label for="phone">Phone</label>
            <input id="phone" name="phone" maxlength="20" value="<?= h($form['phone']) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="100" value="<?= h($form['email']) ?>">

            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3" maxlength="255"><?= h($form['address']) ?></textarea>

            <p><button type="submit"><?= $form['supplier_id'] ? 'Update Supplier' : 'Save Supplier' ?></button></p>
        </form>
    </section>

    <section>
        <h2>Supplier List</h2>
        <table>
            <thead>
            <tr>
                <th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= h($supplier['name']) ?></td>
                    <td><?= h($supplier['phone']) ?></td>
                    <td><?= h($supplier['email']) ?></td>
                    <td><?= h($supplier['address']) ?></td>
                    <td>
                        <div class="actions">
                            <a class="button" href="suppliers.php?edit=<?= h($supplier['supplier_id']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this supplier?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="supplier_id" value="<?= h($supplier['supplier_id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="delete" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$suppliers): ?>
                <tr><td colspan="5">No suppliers have been added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <p><a href="products.php">Go to Products</a></p>
</main>
</body>
</html>