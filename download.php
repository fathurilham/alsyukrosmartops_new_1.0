<?php
require_once 'config/auth.php';
requireLogin();

$user = getCurrentUser();
$role = $user['role'];

// Handle streaming/unduh foto bukti kegiatan karyawan (CSO & Gardener) terproteksi
if (isset($_GET['view']) && $_GET['view'] === 'karyawan_foto') {
    if (isHrdAdministratifRole($user) || !hasAccess($role, 'karyawan', 'lihat')) {
        http_response_code(403);
        die('Akses ditolak.');
    }

    $fotoId = (int)($_GET['id'] ?? 0);
    $filename = basename($_GET['file'] ?? '');

    $row = null;
    if ($fotoId > 0) {
        $stmt = $conn->prepare("
            SELECT kf.path, kk.karyawan_id, k.user_id, k.atasan_id, k.jabatan
            FROM kegiatan_foto kf
            JOIN kegiatan_karyawan kk ON kk.id = kf.kegiatan_id
            JOIN karyawan k ON k.id = kk.karyawan_id
            WHERE kf.id = ?
        ");
        $stmt->bind_param("i", $fotoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    } elseif ($filename !== '') {
        $cleanPath1 = 'uploads/karyawan/' . $filename;
        $stmt = $conn->prepare("
            SELECT kf.path, kk.karyawan_id, k.user_id, k.atasan_id, k.jabatan
            FROM kegiatan_foto kf
            JOIN kegiatan_karyawan kk ON kk.id = kf.kegiatan_id
            JOIN karyawan k ON k.id = kk.karyawan_id
            WHERE kf.path = ? OR kf.path LIKE CONCAT('%', ?)
            LIMIT 1
        ");
        $stmt->bind_param("ss", $cleanPath1, $filename);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            $stmt = $conn->prepare("
                SELECT kk.foto_laporan AS path, kk.karyawan_id, k.user_id, k.atasan_id, k.jabatan
                FROM kegiatan_karyawan kk
                JOIN karyawan k ON k.id = kk.karyawan_id
                WHERE kk.foto_laporan = ? OR kk.foto_laporan LIKE CONCAT('%', ?)
                LIMIT 1
            ");
            $stmt->bind_param("ss", $cleanPath1, $filename);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
        }
    }

    if (!$row) {
        http_response_code(404);
        die('Foto tidak ditemukan.');
    }

    $isOwner = ((int)$row['user_id'] === (int)$user['id']);
    $isSpvOrAdmin = in_array($role, ['spv_sapras', 'admin'], true);
    $isAtasanLangsung = false;
    if ($role === 'team_leader') {
        $myKar = getKaryawanByUserId($user['id']);
        if ($myKar && (int)$myKar['id'] === (int)$row['atasan_id']) {
            $isAtasanLangsung = true;
        }
    }

    if (!$isOwner && !$isSpvOrAdmin && !$isAtasanLangsung) {
        http_response_code(403);
        die('Akses ditolak. Anda tidak memiliki wewenang melihat foto kegiatan ini.');
    }

    $rawRel = $row['path'];
    $fullPath = __DIR__ . '/' . ltrim($rawRel, '/');
    $realPath = realpath($fullPath);
    $baseDir = realpath(__DIR__ . '/uploads/karyawan');

    if ($realPath === false || strpos($realPath, $baseDir) !== 0 || !file_exists($realPath)) {
        http_response_code(404);
        die('Berkas foto tidak ditemukan di server.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $realPath) ?: 'image/jpeg';
    finfo_close($finfo);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($realPath);
    exit();
}

if (!hasAccess($role, 'arsip', 'lihat')) {
    http_response_code(403);
    die('Akses ditolak.');
}

// Handle unduh berkas backup arsip jika parameter backup_id diberikan
$backupId = (int)($_GET['backup_id'] ?? 0);
if ($backupId) {
    // 1. Pembatasan Peran: Hanya pengelola arsip (Admin, Manajer QMS, Staff QMS) yang berhak mengunduh berkas backup
    $allowedBackupRoles = ['admin', 'manajer_qms', 'staff_qms'];
    if (!in_array($role, $allowedBackupRoles, true) || !hasAccess($role, 'arsip', 'kelola')) {
        http_response_code(403);
        logAktivitas($user['id'], 'arsip', "Ditolak: Peran '$role' mencoba mengunduh berkas backup #$backupId tanpa wewenang");
        die('Akses ditolak. Pengunduhan berkas backup hanya diizinkan untuk pengelola arsip (Admin, Manajer QMS, Staff QMS).');
    }

    $stmt = $conn->prepare("SELECT b.id, b.lokasi_backup, a.judul_dokumen, a.tingkat_akses, a.uploader_id FROM backup_arsip b LEFT JOIN arsip a ON a.id = b.arsip_id WHERE b.id = ?");
    $stmt->bind_param("i", $backupId);
    $stmt->execute();
    $bRow = $stmt->get_result()->fetch_assoc();
    if (!$bRow) {
        http_response_code(404);
        die('Data backup tidak ditemukan.');
    }

    // 2. Pembatasan Tingkat Akses Dokumen:
    // Dokumen berklasifikasi 'rahasia' hanya boleh diunduh oleh Admin, Manajer QMS, atau uploader asli dokumen tersebut
    $tingkatAkses = $bRow['tingkat_akses'] ?? 'umum';
    $uploaderId = (int)($bRow['uploader_id'] ?? 0);

    if ($tingkatAkses === 'rahasia' && $role === 'staff_qms' && $uploaderId !== (int)$user['id']) {
        http_response_code(403);
        logAktivitas($user['id'], 'arsip', "Ditolak: Staff QMS ({$user['name']}) mencoba mengunduh backup dokumen rahasia #$backupId (" . ($bRow['judul_dokumen'] ?? '') . ")");
        die('Akses ditolak. Anda tidak memiliki wewenang mengunduh berkas backup dokumen berklasifikasi Rahasia.');
    }

    $filePath = __DIR__ . '/' . $bRow['lokasi_backup'];
    $realPath = realpath($filePath);
    $baseDir = realpath(__DIR__ . '/backups');
    if ($realPath === false || strpos($realPath, $baseDir) !== 0 || !file_exists($realPath)) {
        http_response_code(404);
        die('Berkas backup tidak ditemukan di server.');
    }

    // 3. Catat Riwayat Pengunduhan di Log Aktivitas
    $judulDok = $bRow['judul_dokumen'] ?? basename($realPath);
    logAktivitas($user['id'], 'arsip', "Mengunduh berkas backup #$backupId ($judulDok) [Tingkat: $tingkatAkses]");

    $filename = basename($realPath);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($realPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($realPath);
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('ID dokumen tidak valid.');
}

$where = visibleWhere($role, $user['id']);
$stmt = $conn->prepare("SELECT file_path, judul_dokumen FROM arsip WHERE id = ? AND $where");
$stmt->bind_param("i", $id);
$stmt->execute();
$arsip = $stmt->get_result()->fetch_assoc();

if (!$arsip) {
    http_response_code(404);
    die('Dokumen tidak ditemukan atau Anda tidak memiliki akses ke dokumen ini.');
}

$filePath = __DIR__ . '/' . $arsip['file_path'];
$realPath = realpath($filePath);
$baseDir = realpath(__DIR__ . '/uploads/arsip');

if ($realPath === false || strpos($realPath, $baseDir) !== 0 || !file_exists($realPath)) {
    http_response_code(404);
    die('File tidak ditemukan di server.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realPath);
finfo_close($finfo);

// Fallback jika finfo gagal
if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

$ext = pathinfo($realPath, PATHINFO_EXTENSION);
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $arsip['judul_dokumen']) . '.' . $ext;

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($realPath);
exit();
