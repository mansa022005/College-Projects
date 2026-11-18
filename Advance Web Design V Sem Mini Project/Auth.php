<?php
// Shared session setup and access control for the ERP pages.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
const ERP_LOGIN_USERNAME = 'admin';
const ERP_LOGIN_PASSWORD_HASH = '$2y$10$IV5LoH6WxAEe1ppRVLygSe/GANJwybHZFuLSOPt/ML8QgGTVrr63y';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!in_array($currentPage, ['login.php', 'logout.php'], true) && empty($_SESSION['erp_user'])) {
    $_SESSION['erp_return_to'] = $currentPage;
    header('Location: login.php');
    exit;
}
