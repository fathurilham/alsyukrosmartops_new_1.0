<?php
function secureUpload($file, $allowedMap, $destDir, $maxBytes = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception("Parameter upload tidak valid.");
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Terjadi kesalahan saat mengunggah file (Kode: " . $file['error'] . ").");
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception("File upload tidak valid.");
    }
    if ($file['size'] > $maxBytes) {
        throw new Exception("Ukuran file melebihi batas (maks. " . ($maxBytes / 1048576) . "MB).");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, $allowedMap)) {
        throw new Exception("Format file tidak diizinkan. Tipe file Anda: " . htmlspecialchars($mime));
    }

    $ext = $allowedMap[$mime];
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    
    $destPath = rtrim($destDir, '/') . '/' . $randomName;
    
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception("Gagal menyimpan file ke direktori tujuan.");
    }
    
    return $destPath;
}
