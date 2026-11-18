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
$form = [
    'customer_id' => '',
    'name' => '',
    'phone' => '',
    'email' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('The form expired. Refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = filter_var($_POST['customer_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid customer selected.');
            }

            // The sales table uses ON DELETE SET NULL, preserving past sales.
            $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = ?');
            $stmt->execute([$id]);

            header('Location: customers.php?status=deleted');
            exit;
        }

        $form = [
            'customer_id' => trim($_POST['customer_id'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];

        if ($form['name'] === '') {
            throw new RuntimeException('Customer name is required.');
        }

        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        if ($action === 'update') {
            $id = filter_var($form['customer_id'], FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid customer selected.');
            }

            $stmt = $pdo->prepare(
                'UPDATE customers SET name = ?, phone = ?, email = ? WHERE customer_id = ?'
            );
            $stmt->execute([$form['name'], $form['phone'], $form['email'], $id]);
        } elseif ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)'
            );
            $stmt->execute([$form['name'], $form['phone'], $form['email']]);
        }

        header('Location: customers.php?status=saved');
        exit;
    }

    if (isset($_GET['edit'])) {
        $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

        if ($id && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
            $stmt->execute([$id]);
            $found = $stmt->fetch();

            if ($found) {
                $form = $found;
            } else {
                $error = 'Customer not found.';
            }
        }
    }

    $customers = $pdo->query(
        'SELECT * FROM customers ORDER BY customer_id DESC'
    )->fetchAll();

    if (($_GET['status'] ?? '') === 'saved') {
        $message = 'Customer saved successfully.';
    } elseif (($_GET['status'] ?? '') === 'deleted') {
        $message = 'Customer deleted. Past sales have been preserved.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
    $customers = $pdo->query(
        'SELECT * FROM customers ORDER BY customer_id DESC'
    )->fetchAll();
} catch (PDOException $e) {
    $error = 'Could not save or delete the customer. Check the database tables.';
    $customers = $pdo->query(
        'SELECT * FROM customers ORDER BY customer_id DESC'
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f4f6f8; color: #202832; }
        main { max-width: 1000px; margin: auto; }
        h1, h2 { color: #183b5b; }
        .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input { box-sizing: border-box; width: 100%; padding: 9px; margin-top: 5px; }
        button, .button { display: inline-block; padding: 8px 12px; border: 0; border-radius: 4px; background: #245b86; color: white; cursor: pointer; text-decoration: none; }
        .delete { background: #a33333; }
        .notice { padding: 12px; margin-bottom: 16px; background: #e7f5e9; }
        .error { padding: 12px; margin-bottom: 16px; background: #fde8e7; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #d6dde3; text-align: left; }
        th { background: #183b5b; color: white; }
    </style>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main>
    <h1>Customer Management</h1>

    <nav class="app-nav" aria-label="Main navigation">
        <a href="Index.php">Dashboard</a>
        <a href="Products.php">Products</a>
        <a href="Suppliers.php">Suppliers</a>
        <a href="Customers.php" aria-current="page">Customers</a>
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
        <h2><?= $form['customer_id'] ? 'Edit Customer' : 'Add Customer' ?></h2>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="customer_id" value="<?= h($form['customer_id']) ?>">
            <input type="hidden" name="action" value="<?= $form['customer_id'] ? 'update' : 'create' ?>">

            <label for="name">Customer Name *</label>
            <input id="name" name="name" required maxlength="100" value="<?= h($form['name']) ?>">

            <label for="phone">Phone</label>
            <input id="phone" name="phone" maxlength="20" value="<?= h($form['phone']) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="100" value="<?= h($form['email']) ?>">

            <p><button type="submit"><?= $form['customer_id'] ? 'Update Customer' : 'Save Customer' ?></button></p>
        </form>
    </section>

    <section>
        <h2>Customer List</h2>
        <table>
            <thead>
            <tr><th>Name</th><th>Phone</th><th>Email</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= h($customer['name']) ?></td>
                    <td><?= h($customer['phone']) ?></td>
                    <td><?= h($customer['email']) ?></td>
                    <td>
                        <div class="actions">
                            <a class="button" href="customers.php?edit=<?= h($customer['customer_id']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this customer?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="customer_id" value="<?= h($customer['customer_id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="delete" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$customers): ?>
                <tr><td colspan="4">No customers have been added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>