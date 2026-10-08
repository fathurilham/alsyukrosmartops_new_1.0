<?php
require_once 'config/auth.php';
if (isLoggedIn()) { logAktivitas($_SESSION['user_id'], 'auth', 'Logout dari sistem'); }
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit();
