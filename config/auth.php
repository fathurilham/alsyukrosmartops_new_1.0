<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SESSION HARDENING (dipertahankan dari sistem sebelumnya)
 * ═══════════════════════════════════════════════════════════════════
 */
define('SESSION_IDLE_MINUTES', 30);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

// Timeout logic
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_IDLE_MINUTES * 60)) {
        session_unset();
        session_destroy();
        if (stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'Sesi berakhir karena tidak ada aktivitas.']);
            exit();
        } else {
            header('Location: index.php');
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

require_once __DIR__ . '/database.php';

/* ───────────────────────── CSRF PROTECTION ───────────────────────── */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!(!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent))) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Sesi tidak valid atau kedaluwarsa (CSRF). Silakan muat ulang halaman.']);
        exit();
    }
}
function csrf_valid_form() {
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/* ───────────────────── LOGIN RATE LIMITING / LOCKOUT ─────────────── */
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function checkIpRateLimit() {
    global $conn;
    $ip = getClientIp();
    $timeLimit = date('Y-m-d H:i:s', time() - 15 * 60);
    // Cleanup old attempts occasionally
    if (rand(1, 100) === 1) {
        $conn->query("DELETE FROM login_attempts WHERE created_at < '$timeLimit'");
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE ip = ? AND created_at >= ?");
        if (!$stmt) return false;
        $stmt->bind_param("ss", $ip, $timeLimit);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return ($row['count'] ?? 0) >= 10;
    } catch (mysqli_sql_exception $e) {
        return false; // Tabel belum ada
    }
}

function registerIpFailedLogin() {
    global $conn;
    $ip = getClientIp();
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
        if (!$stmt) return;
        $stmt->bind_param("s", $ip);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Abaikan jika tabel belum ada
    }
}

function isAccountLocked($user) {
    if (empty($user['locked_until'])) return false;
    return strtotime($user['locked_until']) > time();
}
function registerFailedLogin($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT failed_attempts FROM users WHERE id=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $attempts = (int)($row['failed_attempts'] ?? 0) + 1;
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
        $stmt = $conn->prepare("UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?");
        $stmt->bind_param("isi", $attempts, $lockedUntil, $userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET failed_attempts=? WHERE id=?");
        $stmt->bind_param("ii", $attempts, $userId);
    }
    $stmt->execute();
    return $attempts;
}
function resetFailedLogin($userId) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
}

/* ───────────────────────── SESI / IDENTITAS ───────────────────────── */
function isLoggedIn() { return isset($_SESSION['user_id']); }

function requireLogin() {
    if (!isLoggedIn()) { header('Location: index.php'); exit(); }
}

/** Ambil data user + nama & kode peran (role) dalam satu query (JOIN roles). */
function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $conn->prepare("SELECT u.*, r.kode AS role, r.nama_role, su.nama_unit, su.tipe AS unit_tipe
                             FROM users u
                             JOIN roles r ON r.id = u.role_id
                             LEFT JOIN struktur_unit su ON su.id = u.unit_id
                             WHERE u.id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cached = $stmt->get_result()->fetch_assoc();
    
    if (!$cached || $cached['status'] !== 'aktif') {
        session_unset();
        session_destroy();
        if (stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'Sesi tidak valid atau akun dinonaktifkan.']);
            exit();
        } else {
            header('Location: index.php');
            exit();
        }
    }
    
    return $cached;
}

function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Za-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

/* ═══════════════════════════════════════════════════════════════════
 * RBAC — MATRIKS HAK AKSES (role x modul x aksi) PERSIS SESUAI STRUKTUR
 * ORGANISASI PADA KETIGA DOKUMEN (Model 1, Model 2, KAK Project Work).
 * Sumber kebenaran: tabel `hak_akses` (role_id, modul, aksi).
 * ═══════════════════════════════════════════════════════════════════ */

/** Cache hak akses per-role dalam satu request supaya hemat query. */
function getRoleAccessMatrix($role) {
    global $conn;
    static $cache = [];
    if (isset($cache[$role])) return $cache[$role];
    $stmt = $conn->prepare("SELECT ha.modul, ha.aksi FROM hak_akses ha
                             JOIN roles r ON r.id = ha.role_id WHERE r.kode=?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $res = $stmt->get_result();
    $matrix = [];
    while ($row = $res->fetch_assoc()) {
        $matrix[$row['modul']][] = $row['aksi'];
    }
    
    // Hak akses hard-coded telah dipindahkan ke database/migrations.sql

    $cache[$role] = $matrix;
    return $matrix;
}

/** Apakah role memiliki aksi tertentu pada modul tertentu? */
function hasAccess($role, $modul, $aksi) {
    $matrix = getRoleAccessMatrix($role);
    return isset($matrix[$modul]) && in_array($aksi, $matrix[$modul], true);
}

/** Wajibkan hak akses tertentu — hentikan eksekusi (403) jika tidak punya. */
function requireAccess($modul, $aksi) {
    $user = getCurrentUser();
    if (!$user || !hasAccess($user['role'], $modul, $aksi)) {
        http_response_code(403);
        if (php_sapi_name() !== 'cli' && stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'Anda tidak memiliki hak akses untuk aksi ini.']);
        } else {
            echo '<div style="padding:40px;font-family:sans-serif">Anda tidak memiliki hak akses ke halaman ini.</div>';
        }
        exit();
    }
}

/** Daftar modul yang BISA DILIHAT oleh sebuah role (untuk sidebar & dashboard). */
function getRoleModules($role) {
    $matrix = getRoleAccessMatrix($role);
    $modules = array_keys($matrix);
    return array_values(array_filter($modules, fn($m) => $m !== 'pengajuan_unit'));
}

/** Filter arsip yang dapat dilihat oleh user berdasarkan role. */
function visibleWhere($role, $userId) {
    if (hasAccess($role, 'arsip', 'kelola')) return "1=1";
    return "(tingkat_akses='umum' OR uploader_id=$userId)";
}

/** Label modul untuk tampilan UI. */
function getModuleLabel($modul) {
    $labels = [
        'inventaris'     => 'Inventaris & Peminjaman Barang',
        'karyawan'       => 'Manajemen Karyawan',
        'event'          => 'Event Management System',
        'maintenance'    => 'Maintenance Fasilitas',
        'arsip'          => 'Arsip Digital & Pelaporan Analitik',
        'laporan'        => 'Laporan & Monitoring',
    ];
    return $labels[$modul] ?? ucfirst($modul);
}
function getModuleIcon($modul) {
    $icons = [
        'inventaris' => 'package', 'karyawan' => 'users', 'event' => 'calendar',
        'maintenance' => 'wrench', 'arsip' => 'archive', 'laporan' => 'bar-chart-2',
    ];
    return $icons[$modul] ?? 'circle';
}
function getModulePage($modul) {
    $pages = [
        'inventaris' => 'inventory', 'karyawan' => 'karyawan', 'event' => 'event',
        'maintenance' => 'maintenance', 'arsip' => 'arsip', 'laporan' => 'laporan',
    ];
    return $pages[$modul] ?? $modul;
}

/** Nama peran (untuk badge / tampilan) — diambil dari tabel roles. */
function getRoleName($role) {
    global $conn;
    static $cache = [];
    if (isset($cache[$role])) return $cache[$role];
    $stmt = $conn->prepare("SELECT nama_role FROM roles WHERE kode=?");
    $stmt->bind_param("s", $role); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $cache[$role] = $row['nama_role'] ?? $role;
}

/** Warna badge per kelompok peran (untuk konsistensi visual saja). */
function getRoleColor($role) {
    $groups = [
        'admin' => '#111827',
        'manajer_ga' => '#059669', 'spv_inventaris' => '#10b981', 'staff_inventaris' => '#34d399',
        'spv_sapras' => '#0d9488', 'team_leader' => '#14b8a6', 'engineering' => '#2dd4bf',
        'cso' => '#5eead4', 'gardener' => '#5eead4',
        'manajer_hrd' => '#9b59b6', 'staff_hrd' => '#c39bd3',
        'manajer_markom' => '#f39c12', 'spv_crm' => '#f59e0b', 'staff_crm' => '#fbbf24',
        'manajer_qms' => '#7f8c8d', 'staff_qms' => '#94a3b8',
        'tu' => '#3498db', 'kepala_sekolah' => '#c0392b',
    ];
    return $groups[$role] ?? '#95a5a6';
}

/* ═══════════════════════════════════════════════════════════════════
 * ATASAN UNIT (PENGGANTI JEMBATAN PENGAJUAN LINTAS DIVISI)
 * ═══════════════════════════════════════════════════════════════════ */

/** Mendapatkan ID user yang menjadi Atasan Unit dari user yang diberikan. */
function getAtasanId($userId) {
    global $conn;
    
    // 1. Ambil data user beserta rolenya
    $stmt = $conn->prepare("SELECT u.unit_id, r.kode as role_kode FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) return null;
    
    // 2. Cek hierarki di tabel karyawan (khusus GA / karyawan umum)
    $stmt2 = $conn->prepare("SELECT k2.user_id FROM karyawan k1 JOIN karyawan k2 ON k2.id = k1.atasan_id WHERE k1.user_id=? AND k2.user_id IS NOT NULL");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $karyawanAtasan = $stmt2->get_result()->fetch_assoc();
    if ($karyawanAtasan && $karyawanAtasan['user_id']) {
        return $karyawanAtasan['user_id'];
    }
    
    // 3. Mapping Atasan berdasarkan Role & Unit (Lintas Divisi)
    $role = $user['role_kode'];
    $targetRole = null;
    
    if ($role === 'tu') $targetRole = 'kepala_sekolah';
    else if ($role === 'staff_hrd') $targetRole = 'manajer_hrd';
    else if ($role === 'staff_qms') $targetRole = 'manajer_qms';
    else if ($role === 'staff_crm') $targetRole = 'spv_crm';
    else if ($role === 'spv_crm') $targetRole = 'manajer_markom';
    
    if ($targetRole) {
        if ($targetRole === 'kepala_sekolah') {
            $stmt3 = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode=? AND u.unit_id=? AND u.status='aktif' LIMIT 1");
            $stmt3->bind_param("si", $targetRole, $user['unit_id']);
        } else {
            $stmt3 = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode=? AND u.status='aktif' LIMIT 1");
            $stmt3->bind_param("s", $targetRole);
        }
        $stmt3->execute();
        $atasan = $stmt3->get_result()->fetch_assoc();
        return $atasan ? $atasan['id'] : null;
    }
    
    return null;
}

/** Semua daftar role (untuk form registrasi / kelola akun oleh admin). */
function getAllRoles() {
    global $conn;
    $res = $conn->query("SELECT id, kode, nama_role FROM roles ORDER BY id");
    return $res->fetch_all(MYSQLI_ASSOC);
}

/* ───────────────────────── AKTIVITAS & NOTIFIKASI ───────────────────────── */
function logAktivitas($userId, $modul, $aktivitas) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, modul, aktivitas) VALUES (?,?,?)");
    $stmt->bind_param("iss", $userId, $modul, $aktivitas);
    $stmt->execute();
}
function kirimNotifikasi($userId, $modulAsal, $pesan) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifikasi (user_id, modul_asal, pesan) VALUES (?,?,?)");
    $stmt->bind_param("iss", $userId, $modulAsal, $pesan);
    $stmt->execute();
}

/** Cari user_id pemegang suatu role (dipakai untuk auto-notify approver berikutnya).
 *  Jika role terikat unit (mis. kepala_sekolah per unit), filter juga by unit_id. */
function getUserIdsByRole($role, $unitId = null) {
    global $conn;
    if ($unitId) {
        $stmt = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.kode=? AND u.unit_id=? AND u.status='aktif'");
        $stmt->bind_param("si", $role, $unitId);
    } else {
        $stmt = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.kode=? AND u.status='aktif'");
        $stmt->bind_param("s", $role);
    }
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function notifyRole($role, $modulAsal, $pesan, $unitId = null) {
    foreach (getUserIdsByRole($role, $unitId) as $uid) kirimNotifikasi($uid, $modulAsal, $pesan);
}
