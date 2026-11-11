<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$user = getCurrentUser();
if ($user['role'] !== 'admin' && !hasAccess($user['role'], 'akun', 'kelola')) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Khusus Administrator Sistem.']); exit(); }
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

switch ($action) {
    case 'list':
        $idleLimit = getSessionIdleSeconds();
        $cutoff = date('Y-m-d H:i:s', time() - $idleLimit);
        $conn->query("DELETE FROM user_sessions WHERE last_activity < '$cutoff'");

        $sql = "SELECT u.id, u.username, u.email, u.name, u.status, u.role_id, u.unit_id, r.kode AS role, r.nama_role, su.nama_unit,
                       us.session_id AS active_session_id, us.login_at AS session_login_at, us.last_activity AS session_last_activity
                FROM users u
                JOIN roles r ON r.id=u.role_id
                LEFT JOIN struktur_unit su ON su.id=u.unit_id
                LEFT JOIN user_sessions us ON us.user_id=u.id
                ORDER BY u.id";
        $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        $now = time();
        foreach ($rows as &$r) {
            $lastActTs = !empty($r['session_last_activity']) ? strtotime($r['session_last_activity']) : false;
            $isActive = !empty($r['active_session_id']) && $lastActTs !== false && (($now - $lastActTs) <= $idleLimit);
            $r['sedang_login'] = $isActive ? 1 : 0;
            unset($r['active_session_id']);
        }
        unset($r);
        ok($rows);

    case 'tambah':
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0) ?: null;
        if (!$username || !$name || !$password || !$roleId) fail('Semua kolom wajib bertanda * harus diisi.');
        $pimUnitId = getPimpinanUnitId();
        if ($pimUnitId > 0 && $unitId === $pimUnitId && !canAccessPimpinanUnit($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Akses ditolak ke unit Pimpinan/Direksi.']);
            exit();
        }
        if (!validatePassword($password)) fail('Password minimal 8 karakter dan harus mengandung huruf serta angka.');
        $exists = $conn->query("SELECT id FROM users WHERE username='" . $conn->real_escape_string($username) . "'")->fetch_assoc();
        if ($exists) fail('Username sudah digunakan.');
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, name, role_id, unit_id, status) VALUES (?,?,?,?,?,?, 'aktif')");
        $stmt->bind_param("ssssii", $username, $email, $hash, $name, $roleId, $unitId);
        $stmt->execute();
        $newUserId = $stmt->insert_id;

        // Otomatis sinkronkan ke tabel karyawan
        $rRole = $conn->query("SELECT nama_role FROM roles WHERE id = $roleId")->fetch_assoc();
        $namaRole = $rRole['nama_role'] ?? 'Karyawan';
        $nipBaru = 'EMP-' . str_pad($newUserId, 4, '0', STR_PAD_LEFT);
        $stmtK = $conn->prepare("INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, status, tanggal_masuk) VALUES (?, ?, ?, ?, ?, 'aktif', CURDATE())");
        $stmtK->bind_param("isssi", $newUserId, $nipBaru, $name, $namaRole, $unitId);
        $stmtK->execute();

        logAktivitas($user['id'], 'akun', "Membuat akun baru: $username");
        ok(['id' => $newUserId]);

    case 'edit':
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID pengguna tidak valid.');

        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0) ?: null;
        $status = trim($_POST['status'] ?? '');

        if (!$username || !$name || !$roleId) fail('Semua kolom wajib bertanda * harus diisi.');
        $pimUnitId = getPimpinanUnitId();
        if ($pimUnitId > 0 && $unitId === $pimUnitId && !canAccessPimpinanUnit($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Akses ditolak ke unit Pimpinan/Direksi.']);
            exit();
        }

        // Cek data user yang akan diubah
        $targetUser = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
        if (!$targetUser) fail('Pengguna tidak ditemukan.');

        // Cek apakah username sudah dipakai pengguna lain
        $stmtU = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $stmtU->bind_param("si", $username, $id);
        $stmtU->execute();
        if ($stmtU->get_result()->fetch_assoc()) fail('Username sudah digunakan oleh akun lain.');

        // Validasi status
        if (!empty($status) && !in_array($status, ['aktif', 'nonaktif'])) {
            fail('Status akun tidak valid.');
        }
        if (empty($status)) {
            $status = $targetUser['status'];
        }

        // Perlindungan untuk akun administrator yang sedang login
        if ($id === (int)$user['id']) {
            if ($status === 'nonaktif') {
                fail('Anda tidak dapat menonaktifkan akun sendiri.');
            }
            $targetRole = $conn->query("SELECT kode FROM roles WHERE id=$roleId")->fetch_assoc();
            if ($targetRole && $targetRole['kode'] !== 'admin') {
                fail('Anda tidak dapat mengubah peran akun administrator Anda sendiri.');
            }
        }

        // Update password jika diisi, atau pertahankan password lama jika dikosongkan
        if (!empty($password)) {
            if (!validatePassword($password)) fail('Password baru minimal 8 karakter dan harus mengandung huruf serta angka.');
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username=?, name=?, email=?, password=?, role_id=?, unit_id=?, status=?, failed_attempts=0, locked_until=NULL WHERE id=?");
            $stmt->bind_param("ssssiisi", $username, $name, $email, $hash, $roleId, $unitId, $status, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, name=?, email=?, role_id=?, unit_id=?, status=? WHERE id=?");
            $stmt->bind_param("sssissi", $username, $name, $email, $roleId, $unitId, $status, $id);
        }
        $stmt->execute();

        // Sinkronkan data ke tabel karyawan
        $rRole = $conn->query("SELECT nama_role FROM roles WHERE id = $roleId")->fetch_assoc();
        $namaRole = $rRole['nama_role'] ?? '';
        $stmtK = $conn->prepare("UPDATE karyawan SET nama=?, jabatan=?, divisi_id=?, status=? WHERE user_id=?");
        $stmtK->bind_param("sssii", $name, $namaRole, $unitId, $status, $id);
        $stmtK->execute();

        if ($status === 'nonaktif') {
            removeUserSession($id);
        }

        logAktivitas($user['id'], 'akun', "Memperbarui data akun: $username (ID: $id)");
        ok(['id' => $id]);

    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        if (!in_array($status, ['aktif', 'nonaktif'])) fail('Status tidak valid.');
        if ($id === (int)$user['id'] && $status === 'nonaktif') fail('Anda tidak dapat menonaktifkan akun sendiri.');
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Sinkronkan status karyawan
        $conn->query("UPDATE karyawan SET status='" . $conn->real_escape_string($status) . "' WHERE user_id=$id");

        if ($status === 'nonaktif') {
            removeUserSession($id);
        }

        logAktivitas($user['id'], 'akun', "Mengubah status akun #$id menjadi $status");
        ok();

    case 'paksa_logout':
    case 'force_logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode request tidak valid.');
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID pengguna tidak valid.');

        $targetStmt = $conn->prepare("SELECT id, username, name FROM users WHERE id = ?");
        $targetStmt->bind_param("i", $id);
        $targetStmt->execute();
        $target = $targetStmt->get_result()->fetch_assoc();
        if (!$target) fail('Akun pengguna tidak ditemukan.');

        removeUserSession($id);
        logAktivitas($user['id'], 'akun', "Paksa logout sesi aktif untuk akun: {$target['username']} (ID: $id)");
        ok(['id' => $id, 'username' => $target['username']]);

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

    case 'hapus':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID pengguna tidak valid.');
        if ($id === (int)$user['id']) fail('Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');

        $target = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
        if (!$target) fail('Akun pengguna tidak ditemukan.');

        // Cek keterkaitan data operasional
        $checks = [
            'Peminjaman Barang' => "SELECT COUNT(*) n FROM peminjaman WHERE peminjam_id=$id OR verifikator_id=$id OR atasan_id=$id OR approval_id=$id",
            'Kegiatan / Event' => "SELECT COUNT(*) n FROM event WHERE pemohon_id=$id OR verifikator_id=$id OR approval_id=$id OR atasan_id=$id",
            'Maintenance Fasilitas' => "SELECT COUNT(*) n FROM pengajuan_maintenance WHERE pemohon_id=$id OR tl_verifikasi_id=$id OR spv_approval_id=$id OR atasan_id=$id",
            'Penugasan Lapangan' => "SELECT COUNT(*) n FROM kegiatan_karyawan WHERE assigned_by=$id",
            'Arsip Digital' => "SELECT COUNT(*) n FROM arsip WHERE uploader_id=$id",
            'Penilaian Kinerja' => "SELECT COUNT(*) n FROM penilaian_kinerja WHERE penilai_id=$id",
            'Pengembalian Barang' => "SELECT COUNT(*) n FROM pengembalian WHERE petugas_id=$id",
            'Pengerjaan Teknisi' => "SELECT COUNT(*) n FROM proses_maintenance WHERE teknisi_id=$id",
        ];

        $reasons = [];
        foreach ($checks as $lbl => $q) {
            $cnt = $conn->query($q)->fetch_assoc()['n'] ?? 0;
            if ($cnt > 0) $reasons[] = "$lbl ($cnt catatan)";
        }

        if (!empty($reasons)) {
            fail("Akun '{$target['username']}' tidak dapat dihapus permanen karena masih memiliki riwayat data: " . implode(', ', $reasons) . ". Untuk keamanan data, disarankan menggunakan fitur 'Nonaktifkan'.");
        }

        // Hapus data karyawan terkait (termasuk kegiatan, reminder, evaluasi) agar tidak menjadi orphan
        $conn->query("DELETE FROM penilaian_kinerja WHERE karyawan_id IN (SELECT id FROM karyawan WHERE user_id=$id)");
        $conn->query("DELETE FROM reminder_kegiatan WHERE kegiatan_id IN (SELECT id FROM kegiatan_karyawan WHERE karyawan_id IN (SELECT id FROM karyawan WHERE user_id=$id))");
        $conn->query("DELETE FROM kegiatan_karyawan WHERE karyawan_id IN (SELECT id FROM karyawan WHERE user_id=$id)");
        $conn->query("UPDATE karyawan SET atasan_id=NULL WHERE atasan_id IN (SELECT id FROM (SELECT id FROM karyawan WHERE user_id=$id) AS tmp)");
        $conn->query("DELETE FROM karyawan WHERE user_id=$id");

        // Hapus notifikasi terkait pengguna ini (log aktivitas tetap dipertahankan dengan ON DELETE SET NULL)
        $conn->query("DELETE FROM notifikasi WHERE user_id=$id");

        // Hapus akun pengguna
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logAktivitas($user['id'], 'akun', "Menghapus akun pengguna: {$target['username']} (ID: $id)");
            ok();
        } else {
            fail('Gagal menghapus akun: ' . $conn->error);
        }

    default:
        fail('Aksi tidak dikenal.');
}
