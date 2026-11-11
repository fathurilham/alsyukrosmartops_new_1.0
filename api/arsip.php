<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

if (!hasAccess($role, 'arsip', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Arsip.');

// visibleWhere dipindah ke config/auth.php agar bisa dipakai oleh download.php

switch ($action) {

    case 'list_kategori':
        ok($conn->query("SELECT * FROM kategori_arsip ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC));

    case 'list':
        $where = visibleWhere($role, $user['id']);
        $sql = "SELECT a.*, ka.nama_kategori, u.name AS uploader_nama FROM arsip a
                LEFT JOIN kategori_arsip ka ON ka.id=a.kategori_id
                JOIN users u ON u.id=a.uploader_id
                WHERE $where ORDER BY a.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'tambah_kategori':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $nama = trim($_POST['nama_kategori'] ?? '');
        if (!$nama) fail('Nama kategori wajib diisi.');
        $desk = trim($_POST['deskripsi'] ?? '');
        $stmt = $conn->prepare("INSERT INTO kategori_arsip (nama_kategori, deskripsi) VALUES (?,?)");
        $stmt->bind_param("ss", $nama, $desk);
        $stmt->execute();
        ok(['id' => $stmt->insert_id]);

    case 'upload':
        if (!hasAccess($role, 'arsip', 'tambah') && !hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $judul = trim($_POST['judul_dokumen'] ?? '');
        $kategoriId = (int)($_POST['kategori_id'] ?? 0) ?: null;
        $tingkat = $_POST['tingkat_akses'] ?? 'umum';
        if (!in_array($tingkat, ['umum', 'terbatas', 'rahasia'])) fail('Tingkat akses tidak valid.');
        if (!$judul) fail('Judul dokumen wajib diisi.');

        $filePath = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            require_once '../config/upload.php';
            try {
                $allowed = [
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];
                $dir = __DIR__ . '/../uploads/arsip/';
                $absPath = secureUpload($_FILES['file'], $allowed, $dir);
                $filePath = 'uploads/arsip/' . basename($absPath);
            } catch (Exception $e) {
                fail($e->getMessage());
            }
        }
        if (!$filePath) fail('File wajib diunggah.');

        $stmt = $conn->prepare("INSERT INTO arsip (judul_dokumen, kategori_id, file_path, uploader_id, tingkat_akses) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sisis", $judul, $kategoriId, $filePath, $user['id'], $tingkat);
        $stmt->execute();
        logAktivitas($user['id'], 'arsip', "Mengunggah dokumen: $judul");
        ok(['id' => $stmt->insert_id]);

    case 'backup':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $arsipId = (int)($_POST['arsip_id'] ?? 0);
        $stmtArsip = $conn->prepare("SELECT * FROM arsip WHERE id = ?");
        $stmtArsip->bind_param("i", $arsipId);
        $stmtArsip->execute();
        $a = $stmtArsip->get_result()->fetch_assoc();
        if (!$a) fail('Dokumen tidak ditemukan.');
        $sourceRel = $a['file_path'];
        $sourcePath = __DIR__ . '/../' . $sourceRel;
        if (!file_exists($sourcePath) || !is_file($sourcePath)) {
            fail('Berkas fisik dokumen tidak ditemukan di server.');
        }

        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                fail('Gagal membuat folder backups/ di server.');
            }
        }
        $htaccessBackup = $backupDir . '/.htaccess';
        if (!file_exists($htaccessBackup)) {
            @file_put_contents($htaccessBackup, "Require all denied\n");
        }

        $filename = 'arsip_' . $arsipId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.bak';
        $targetPath = $backupDir . '/' . $filename;
        $lokasi = 'backups/' . $filename;

        if (!copy($sourcePath, $targetPath)) {
            fail('Gagal menyalin berkas ke folder backups/.');
        }

        $stmt = $conn->prepare("INSERT INTO backup_arsip (arsip_id, lokasi_backup) VALUES (?,?)");
        $stmt->bind_param("is", $arsipId, $lokasi);
        $stmt->execute();
        logAktivitas($user['id'], 'arsip', "Backup dokumen #$arsipId ($lokasi)");
        ok(['lokasi' => $lokasi]);

    case 'list_backup':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $sql = "SELECT b.*, a.judul_dokumen, a.tingkat_akses, a.uploader_id FROM backup_arsip b LEFT JOIN arsip a ON a.id=b.arsip_id ORDER BY b.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    default:
        fail('Aksi tidak dikenal.');
}
