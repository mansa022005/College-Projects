<?php
require_once __DIR__ . '/Auth.php';
if (!empty($_SESSION['erp_user'])) { header('Location: Index.php'); exit; }
if (empty($_SESSION['erp_csrf'])) { $_SESSION['erp_csrf'] = bin2hex(random_bytes(32)); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!hash_equals($_SESSION['erp_csrf'], $postedToken)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (hash_equals(ERP_LOGIN_USERNAME, $username) && password_verify($password, ERP_LOGIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['erp_user'] = ERP_LOGIN_USERNAME;
        unset($_SESSION['erp_csrf']);
        $pages = ['Index.php', 'Products.php', 'Suppliers.php', 'Customers.php', 'Purchases.php', 'Sales.php', 'Reports.php'];
        $target = $_SESSION['erp_return_to'] ?? 'Index.php';
        unset($_SESSION['erp_return_to']);
        if (!in_array($target, $pages, true)) { $target = 'Index.php'; }
        header('Location: ' . $target);
        exit;
    } else {
        $error = 'The username or password is incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Small Business ERP</title>
    <link rel="stylesheet" href="assets/app.css">
    <style>
        body.auth-page { min-height: 100vh; display: grid; place-items: center; padding: 28px 18px; }
        .auth-page main.login-card { width: min(100%, 470px); margin: 0; padding: 38px; }
        .login-card h1 { font-size: 1.9rem; }
        .login-card > p { color: var(--muted); margin: 8px 0 24px; }
        .login-card form { display: block; margin: 0; }
        .login-card form label { margin: 18px 0 7px; }
        .login-card form button { width: 100%; margin-top: 24px; }
        .login-error { padding: 12px 14px; border: 1px solid #f1cbc7; border-radius: 10px; background: var(--danger-bg); color: var(--danger); font-weight: 600; }
        .login-hint { margin-top: 20px !important; padding-top: 16px; border-top: 1px solid var(--line); font-size: .88rem; }
        @media (max-width: 480px) { .auth-page main.login-card { padding: 26px 21px; } }
    </style>
</head>
<body class="auth-page">
<main class="login-card">
    <h1>Welcome back</h1>
    <p>Sign in to manage your inventory and sales.</p>
    <?php if ($error !== ''): ?>
        <div class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['erp_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Sign in</button>
    </form>
    <p class="login-hint">Demo sign-in: <strong>admin</strong> / <strong>admin123</strong></p>
</main>
</body>
</html>
