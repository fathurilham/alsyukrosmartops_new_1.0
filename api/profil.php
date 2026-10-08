<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'ganti_password') {
    $oldPass = $_POST['old_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    
    if (empty($oldPass) || empty($newPass)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Semua kolom wajib diisi.']);
        exit();
    }
    
    global $conn;
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $currPass = $stmt->get_result()->fetch_assoc()['password'];
    
    if (!password_verify($oldPass, $currPass)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Password lama salah.']);
        exit();
    }
    
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt2->bind_param("si", $hash, $user['id']);
    $stmt2->execute();
    
    logAktivitas($user['id'], 'auth', 'Berhasil mengganti password sendiri');
    echo json_encode(['ok' => true]);
    exit();
}

http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Aksi tidak valid.']);
