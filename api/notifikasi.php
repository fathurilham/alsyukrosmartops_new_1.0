<?php
require_once '../config/auth.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
header('Content-Type: application/json');
$user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $stmt = $conn->prepare("SELECT * FROM notifikasi WHERE user_id=? ORDER BY id DESC LIMIT 20");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $unreadStmt = $conn->prepare("SELECT COUNT(*) n FROM notifikasi WHERE user_id=? AND status_baca='belum'");
        $unreadStmt->bind_param("i", $user['id']);
        $unreadStmt->execute();
        $unread = $unreadStmt->get_result()->fetch_assoc()['n'];
        echo json_encode(['ok' => true, 'data' => $data, 'unread' => (int)$unread]);
        exit();
    case 'mark_read':
        $stmt = $conn->prepare("UPDATE notifikasi SET status_baca='sudah' WHERE user_id=?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        exit();
    default:
        echo json_encode(['ok' => false, 'msg' => 'Aksi tidak dikenal.']);
}
