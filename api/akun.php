<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');
$user = getCurrentUser();
if ($user['role'] !== 'admin') { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Khusus Administrator Sistem.']); exit(); }
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

switch ($action) {
    case 'list':
        $sql = "SELECT u.id, u.username, u.email, u.name, u.status, r.kode AS role, r.nama_role, su.nama_unit
                FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN struktur_unit su ON su.id=u.unit_id ORDER BY u.id";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'tambah':
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0) ?: null;
        if (!$username || !$name || !$password || !$roleId) fail('Semua kolom wajib bertanda * harus diisi.');
        if (!validatePassword($password)) fail('Password minimal 8 karakter dan harus mengandung huruf serta angka.');
        $exists = $conn->query("SELECT id FROM users WHERE username='" . $conn->real_escape_string($username) . "'")->fetch_assoc();
        if ($exists) fail('Username sudah digunakan.');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, name, role_id, unit_id, status) VALUES (?,?,?,?,?,?, 'aktif')");
        $stmt->bind_param("ssssii", $username, $email, $hash, $name, $roleId, $unitId);
        $stmt->execute();
        logAktivitas($user['id'], 'akun', "Membuat akun baru: $username");
        ok(['id' => $stmt->insert_id]);

    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        if (!in_array($status, ['aktif', 'nonaktif'])) fail('Status tidak valid.');
        if ($id === (int)$user['id'] && $status === 'nonaktif') fail('Anda tidak dapat menonaktifkan akun sendiri.');
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'akun', "Mengubah status akun #$id menjadi $status");
        ok();

    case 'reset_password':
        $id = (int)($_POST['id'] ?? 0);
        $newPass = $_POST['password'] ?? '';
        if (empty($newPass)) fail('Password baru wajib diisi.');
        if (!validatePassword($newPass)) fail('Password minimal 8 karakter dan harus mengandung huruf serta angka.');
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password=?, failed_attempts=0, locked_until=NULL WHERE id=?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'akun', "Reset password akun #$id");
        ok();

    case 'hak_akses':
        $role_kode = $_GET['role'] ?? $_POST['role'] ?? '';
        $matrix = getRoleAccessMatrix($role_kode);
        ok($matrix);

    default:
        fail('Aksi tidak dikenal.');
}
