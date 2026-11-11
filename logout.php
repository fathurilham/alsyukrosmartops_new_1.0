<?php
require_once 'config/auth.php';
if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    $sid = session_id();
    removeUserSession($uid, $sid);
    logAktivitas($uid, 'auth', 'Logout dari sistem');
}
$_SESSION = [];
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logout</title>
</head>
<body>
<script>
    try {
        localStorage.removeItem('alsyukro_last_notif_id');
    } catch(e) {}
    window.location.replace('index.php');
</script>
</body>
</html>
