<?php
require_once 'config/auth.php';
requireLogin();

$user = getCurrentUser();
$role = $user['role'];

if (!hasAccess($role, 'arsip', 'lihat')) {
    http_response_code(403);
    die('Akses ditolak.');
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
