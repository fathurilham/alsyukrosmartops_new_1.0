<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SESSION HARDENING (dipertahankan dari sistem sebelumnya)
 * ═══════════════════════════════════════════════════════════════════
 */
define('SESSION_IDLE_MINUTES', 30);
define('SESSION_IDLE_SECONDS', SESSION_IDLE_MINUTES * 60);
define('SESSION_DB_TOUCH_INTERVAL', 60);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

/** Ambil batas waktu idle sesi dalam detik. */
function getSessionIdleSeconds() {
    if (defined('SESSION_IDLE_SECONDS_OVERRIDE') && SESSION_IDLE_SECONDS_OVERRIDE > 0) {
        return (int)SESSION_IDLE_SECONDS_OVERRIDE;
    }
    return SESSION_IDLE_SECONDS;
}

/** Deteksi apakah request saat ini menuju endpoint API (`api/`). */
function isApiRequest() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
    return stripos($uri, '/api/') !== false || stripos($script, '/api/') !== false;
}

/** Bersihkan state autentikasi pada sesi PHP. */
function clearAuthSessionState($destroyFullSession = true) {
    unset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['db_last_touch'], $_SESSION['last_reminder_check']);
    if ($destroyFullSession && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_unset();
        session_destroy();
    }
}

/** Hentikan request dengan respons 401 JSON (untuk API) atau redirect ke halaman login. */
function terminateInvalidSession($msg = 'Sesi tidak valid atau telah berakhir. Silakan login kembali.') {
    clearAuthSessionState(true);
    if (isApiRequest()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => $msg]);
        exit();
    } else {
        header('Location: index.php');
        exit();
    }
}

/** Hapus baris sesi aktif dari tabel user_sessions. */
function removeUserSession($userId, $sessionId = null) {
    global $conn;
    $userId = (int)$userId;
    if ($userId <= 0 || !$conn) return false;
    try {
        if ($sessionId !== null && $sessionId !== '') {
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("is", $userId, $sessionId);
        } else {
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            if (!$stmt) return false;
            $stmt->bind_param("i", $userId);
        }
        $stmt->execute();
        return $stmt->affected_rows > 0;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

/**
 * Klaim sesi aktif untuk user saat login (Satu Akun = Satu Sesi Aktif).
 * Menggunakan transaksi + SELECT ... FOR UPDATE pada baris user & user_sessions
 * untuk mencegah balapan data (race condition) saat terjadi dua login bersamaan.
 * Mengembalikan true jika berhasil klaim sesi, atau false jika masih ada sesi aktif lain.
 */
function claimUserSession($userId, $sessionId, $ip = null, $userAgent = null) {
    global $conn;
    $userId = (int)$userId;
    $sessionId = (string)$sessionId;
    if ($userId <= 0 || $sessionId === '') return false;

    $now = time();
    $nowStr = date('Y-m-d H:i:s', $now);
    $idleSeconds = getSessionIdleSeconds();
    $ipAddr = $ip !== null ? (string)$ip : (function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    $uaShort = mb_substr((string)($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

    $conn->begin_transaction();
    try {
        // 1. Kunci baris user di tabel `users` agar dua percobaan login bersamaan pada akun yang sama mengantre
        $stmtLock = $conn->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $resLock = $stmtLock->get_result();
        if (!$resLock || $resLock->num_rows === 0) {
            $conn->rollback();
            return false;
        }
        $resLock->free();

        // 2. Cek apakah user sudah memiliki baris sesi di `user_sessions`
        $stmtSess = $conn->prepare("SELECT session_id, last_activity FROM user_sessions WHERE user_id = ? FOR UPDATE");
        $stmtSess->bind_param("i", $userId);
        $stmtSess->execute();
        $existing = $stmtSess->get_result()->fetch_assoc();

        if ($existing) {
            $lastActTs = strtotime($existing['last_activity'] ?? '');
            $isExpired = ($lastActTs === false) || (($now - $lastActTs) > $idleSeconds);

            if (!$isExpired && !hash_equals((string)$existing['session_id'], $sessionId)) {
                // Masih ada sesi aktif yang belum kedaluwarsa di perangkat/browser lain
                $conn->rollback();
                return false;
            }

            // Sesi lama sudah kedaluwarsa (atau berasal dari session_id yang sama) -> gantikan
            $stmtUpd = $conn->prepare("UPDATE user_sessions SET session_id = ?, ip = ?, user_agent = ?, login_at = ?, last_activity = ? WHERE user_id = ?");
            $stmtUpd->bind_param("sssssi", $sessionId, $ipAddr, $uaShort, $nowStr, $nowStr, $userId);
            $stmtUpd->execute();
        } else {
            // Belum ada sesi aktif -> masukkan baris baru
            $stmtIns = $conn->prepare("INSERT INTO user_sessions (user_id, session_id, ip, user_agent, login_at, last_activity) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtIns->bind_param("isssss", $userId, $sessionId, $ipAddr, $uaShort, $nowStr, $nowStr);
            $stmtIns->execute();
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Validasi sesi PHP saat ini terhadap tabel `user_sessions`, timeout idle, dan status akun.
 * Sekaligus memperbarui `last_activity` di database maksimal 1 kali per 60 detik.
 */
function validateAndTouchActiveSession($terminateOnInvalid = true, $touchActivity = true) {
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $uid = (int)$_SESSION['user_id'];
    $sid = session_id();
    $now = time();
    $idleSeconds = getSessionIdleSeconds();

    // 1. Cek timeout idle di sesi PHP
    if (isset($_SESSION['last_activity']) && (($now - (int)$_SESSION['last_activity']) > $idleSeconds)) {
        removeUserSession($uid, $sid);
        if ($terminateOnInvalid) {
            terminateInvalidSession('Sesi berakhir karena tidak ada aktivitas.');
        }
        clearAuthSessionState(false);
        return false;
    }

    // 2. Cek kecocokan session_id di database serta status/kunci akun
    $stmt = $conn->prepare("SELECT u.status AS user_status, u.locked_until, us.session_id, us.last_activity
                             FROM users u
                             LEFT JOIN user_sessions us ON us.user_id = u.id
                             WHERE u.id = ? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || $row['user_status'] !== 'aktif' || (!empty($row['locked_until']) && strtotime($row['locked_until']) > $now)) {
        removeUserSession($uid);
        if ($terminateOnInvalid) {
            terminateInvalidSession('Sesi tidak valid atau akun dinonaktifkan.');
        }
        clearAuthSessionState(false);
        return false;
    }

    // Pastikan baris user_sessions ada dan session_id sama persis
    if (empty($row['session_id']) || !hash_equals((string)$row['session_id'], (string)$sid)) {
        if ($terminateOnInvalid) {
            terminateInvalidSession('Sesi tidak valid atau telah berakhir.');
        }
        clearAuthSessionState(false);
        return false;
    }

    // Cek apakah last_activity di database telah melewati batas idle
    $dbLastActTs = strtotime($row['last_activity'] ?? '');
    if ($dbLastActTs === false || (($now - $dbLastActTs) > $idleSeconds)) {
        removeUserSession($uid, $sid);
        if ($terminateOnInvalid) {
            terminateInvalidSession('Sesi berakhir karena tidak ada aktivitas.');
        }
        clearAuthSessionState(false);
        return false;
    }

    // 3. Sesi valid -> perbarui timestamp aktivitas di memori sesi PHP HANYA jika bukan polling pasif
    if ($touchActivity) {
        $_SESSION['last_activity'] = $now;

        // 4. Perbarui last_activity di database maksimal sekali setiap 60 detik (SESSION_DB_TOUCH_INTERVAL)
        $lastDbTouch = (int)($_SESSION['db_last_touch'] ?? $dbLastActTs);
        if (($now - $lastDbTouch) >= SESSION_DB_TOUCH_INTERVAL) {
            $nowStr = date('Y-m-d H:i:s', $now);
            $stmtTouch = $conn->prepare("UPDATE user_sessions SET last_activity = ? WHERE user_id = ? AND session_id = ?");
            if ($stmtTouch) {
                $stmtTouch->bind_param("sis", $nowStr, $uid, $sid);
                $stmtTouch->execute();
            }
            $_SESSION['db_last_touch'] = $now;
        }
    }

    return true;
}

// Jalankan pemeriksaan sesi otomatis saat auth.php dimuat (kecuali pada logout.php yang menangani pembersihannya sendiri)
$currentScriptBase = basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
if (isset($_SESSION['user_id']) && $currentScriptBase !== 'logout.php') {
    $shouldTerminate = ($currentScriptBase !== 'index.php');
    $isPollAction = ($currentScriptBase === 'notifikasi.php' && (($_GET['action'] ?? $_POST['action'] ?? '') === 'poll'));
    $touchActivity = !$isPollAction;
    validateAndTouchActiveSession($shouldTerminate, $touchActivity);
}

/* ───────────────────────── CSRF PROTECTION ───────────────────────── */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
if (!function_exists('esc')) {
    function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
        $stmt->execute();
        removeUserSession($userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET failed_attempts=? WHERE id=?");
        $stmt->bind_param("ii", $attempts, $userId);
        $stmt->execute();
    }
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
    if (!isLoggedIn() || !validateAndTouchActiveSession(true)) {
        terminateInvalidSession('Sesi tidak valid atau telah berakhir. Silakan login kembali.');
    }
}

/** Ambil data user + nama & kode peran (role) dalam satu query (JOIN roles). */
function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    static $cached = [];
    $uid = (int)$_SESSION['user_id'];
    if (isset($cached[$uid])) return $cached[$uid];
    $stmt = $conn->prepare("SELECT u.*, r.kode AS role, r.nama_role, su.nama_unit, su.tipe AS unit_tipe
                             FROM users u
                             JOIN roles r ON r.id = u.role_id
                             LEFT JOIN struktur_unit su ON su.id = u.unit_id
                             WHERE u.id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    
    if (!$userRow || $userRow['status'] !== 'aktif' || isAccountLocked($userRow)) {
        removeUserSession($uid);
        terminateInvalidSession('Sesi tidak valid atau akun dinonaktifkan.');
    }
    
    $cached[$uid] = $userRow;
    return $userRow;
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
    $user = getCurrentUser();
    $modules = [];
    foreach ($matrix as $m => $aksiList) {
        if ($m !== 'pengajuan_unit' && in_array('lihat', $aksiList, true)) {
            if ($m === 'karyawan' && $user && !canAccessKaryawanModule($user)) {
                continue;
            }
            $modules[] = $m;
        }
    }
    return $modules;
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
        'log_aktivitas'  => 'Log Aktivitas Sistem',
    ];
    return $labels[$modul] ?? ucfirst($modul);
}
function getModuleIcon($modul) {
    $icons = [
        'inventaris' => 'package', 'karyawan' => 'users', 'event' => 'calendar',
        'maintenance' => 'wrench', 'arsip' => 'archive', 'laporan' => 'bar-chart-2',
        'log_aktivitas' => 'activity',
    ];
    return $icons[$modul] ?? 'circle';
}
function getModulePage($modul) {
    $pages = [
        'inventaris' => 'inventory', 'karyawan' => 'karyawan', 'event' => 'event',
        'maintenance' => 'maintenance', 'arsip' => 'arsip', 'laporan' => 'laporan',
        'log_aktivitas' => 'log_aktivitas',
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
        'manajer_fa' => '#0369a1', 'spv_fa' => '#0284c7', 'staff_fa' => '#38bdf8',
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
    
    $role = $user['role_kode'];

    // Kondisi sementara Divisi FA tanpa SPV:
    // Untuk peran staff_fa, cari user AKTIF berperan spv_fa pada unit FA terlebih dahulu.
    // Jika ada -> pakai dia (otomatis kembali ke SPV saat akun spvfa diaktifkan lagi).
    // Jika tidak ada -> langsung ke manajer_fa aktif.
    if ($role === 'staff_fa') {
        $unitFa = (int)($user['unit_id'] ?? 0);
        if ($unitFa > 0) {
            $stmtSpv = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode = 'spv_fa' AND u.unit_id = ? AND u.status = 'aktif' LIMIT 1");
            $stmtSpv->bind_param("i", $unitFa);
        } else {
            $stmtSpv = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode = 'spv_fa' AND u.status = 'aktif' LIMIT 1");
        }
        $stmtSpv->execute();
        $spvRow = $stmtSpv->get_result()->fetch_assoc();
        if ($spvRow && !empty($spvRow['id'])) {
            return (int)$spvRow['id'];
        }

        if ($unitFa > 0) {
            $stmtMgr = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode = 'manajer_fa' AND u.unit_id = ? AND u.status = 'aktif' LIMIT 1");
            $stmtMgr->bind_param("i", $unitFa);
        } else {
            $stmtMgr = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode = 'manajer_fa' AND u.status = 'aktif' LIMIT 1");
        }
        $stmtMgr->execute();
        $mgrRow = $stmtMgr->get_result()->fetch_assoc();
        return ($mgrRow && !empty($mgrRow['id'])) ? (int)$mgrRow['id'] : null;
    }

    // 2. Cek hierarki di tabel karyawan (khusus GA / karyawan umum, pastikan akun atasan aktif)
    $stmt2 = $conn->prepare("SELECT k2.user_id FROM karyawan k1 JOIN karyawan k2 ON k2.id = k1.atasan_id JOIN users u2 ON u2.id = k2.user_id WHERE k1.user_id=? AND k2.user_id IS NOT NULL AND u2.status='aktif'");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $karyawanAtasan = $stmt2->get_result()->fetch_assoc();
    if ($karyawanAtasan && $karyawanAtasan['user_id']) {
        return (int)$karyawanAtasan['user_id'];
    }
    
    // 3. Mapping Atasan berdasarkan Role & Unit (Lintas Divisi)
    $targetRole = null;
    
    if ($role === 'tu') $targetRole = 'kepala_sekolah';
    else if ($role === 'staff_hrd') $targetRole = 'manajer_hrd';
    else if ($role === 'staff_qms') $targetRole = 'manajer_qms';
    else if ($role === 'staff_crm') $targetRole = 'spv_crm';
    else if ($role === 'spv_fa') $targetRole = 'manajer_fa';
    
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
        return $atasan ? (int)$atasan['id'] : null;
    }
    
    return null;
}

/**
 * Daftar peran pimpinan unit/divisi yang dapat "Ajukan Sendiri" (mewakili staf yang tidak masuk)
 * dan langsung melewati tahap menunggu_approval_atasan (status awal langsung 'diajukan').
 * Catatan: Daftar ini adalah aturan bisnis yang dapat diubah oleh pemilik sistem.
 * Role spv_fa tetap disertakan agar siap otomatis jika kelak akun SPV FA diaktifkan kembali.
 */
function getPengajuSetaraAtasan($modul) {
    $map = [
        'inventaris'  => ['kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_sapras', 'manajer_fa', 'spv_fa'],
        'maintenance' => ['kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_inventaris', 'manajer_fa', 'spv_fa'],
        'event'       => ['kepala_sekolah', 'manajer_hrd'],
    ];
    return $map[$modul] ?? [];
}

/** Mengecek apakah peran pengguna termasuk pimpinan unit yang melewati approval atasan saat mengajukan. */
function isPengajuSetaraAtasan($role, $modul) {
    return in_array((string)$role, getPengajuSetaraAtasan($modul), true);
}

/**
 * Peta aturan bisnis pejabat pengganti untuk tahap menunggu_approval_atasan saat atasan asli tidak masuk.
 * Catatan: Daftar ini ditetapkan oleh pemilik sistem dan dapat disesuaikan di satu tempat ini.
 * Peran yang tidak ada di peta (termasuk team_leader) = TIDAK ADA pengganti.
 */
function getPeranPenggantiApprover($roleApproverAsli) {
    $map = [
        'kepala_sekolah' => ['admin'],
        'spv_inventaris' => ['manajer_ga'],
        'spv_sapras'     => ['manajer_ga'],
        'spv_crm'        => ['manajer_markom'],
        'spv_fa'         => ['manajer_fa'], // hanya berlaku jika SPV FA diaktifkan kembali
        'manajer_hrd'    => ['admin'],
        'manajer_qms'    => ['admin'],
        'manajer_fa'     => ['admin'],
    ];
    return $map[(string)$roleApproverAsli] ?? [];
}

/**
 * Evaluasi lengkap apakah $user berhak menyetujui/menolak sebagai pengganti untuk pengajuan milik $pemohonId.
 * Mengembalikan array ['allowed' => bool, 'code' => int, 'msg' => string, 'approver' => ?array, 'pemohon' => ?array].
 */
function getDetailApprovePengganti($user, $pemohonId) {
    global $conn;
    if (!$user || empty($user['id']) || empty($user['role'])) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pengguna tidak valid.', 'approver' => null, 'pemohon' => null];
    }

    $uid = (int)$user['id'];
    $userRole = (string)$user['role'];
    $userNamaRole = (string)($user['nama_role'] ?? $userRole);
    $pemohonId = (int)$pemohonId;

    if ($pemohonId <= 0) {
        return ['allowed' => false, 'code' => 400, 'msg' => 'Pemohon tidak valid.', 'approver' => null, 'pemohon' => null];
    }

    // Pemohon tidak boleh menyetujui pengajuannya sendiri
    if ($uid === $pemohonId) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pemohon tidak dapat menyetujui atau menolak pengajuannya sendiri sebagai pengganti.', 'approver' => null, 'pemohon' => null];
    }

    $approverAsliId = (int)(getAtasanId($pemohonId) ?? 0);
    if ($approverAsliId <= 0) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Pengajuan ini tidak memiliki atasan langsung yang perlu digantikan.', 'approver' => null, 'pemohon' => null];
    }

    // Jika user adalah approver asli sendiri, wajib memakai jalur normal (bukan jalur pengganti)
    if ($uid === $approverAsliId) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Anda adalah atasan langsung dari pemohon ini. Silakan gunakan persetujuan atasan normal (bukan jalur pengganti).', 'approver' => null, 'pemohon' => null];
    }

    $stmtU = $conn->prepare("SELECT u.id, u.name, r.kode AS role_kode, r.nama_role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $stmtU->bind_param("i", $approverAsliId);
    $stmtU->execute();
    $approver = $stmtU->get_result()->fetch_assoc();
    if (!$approver) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Data atasan langsung tidak ditemukan.', 'approver' => null, 'pemohon' => null];
    }

    $stmtP = $conn->prepare("SELECT u.id, u.name, r.kode AS role_kode, r.nama_role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $stmtP->bind_param("i", $pemohonId);
    $stmtP->execute();
    $pemohon = $stmtP->get_result()->fetch_assoc();

    $daftarPengganti = getPeranPenggantiApprover($approver['role_kode']);
    if (empty($daftarPengganti)) {
        return [
            'allowed'  => false,
            'code'     => 403,
            'msg'      => "Akses ditolak. Peran atasan asli ({$approver['nama_role']}) tidak memiliki pejabat pengganti yang ditetapkan.",
            'approver' => $approver,
            'pemohon'  => $pemohon,
        ];
    }

    if (!in_array($userRole, $daftarPengganti, true)) {
        return [
            'allowed'  => false,
            'code'     => 403,
            'msg'      => "Akses ditolak. Peran {$userNamaRole} tidak berwenang menggantikan persetujuan {$approver['nama_role']}.",
            'approver' => $approver,
            'pemohon'  => $pemohon,
        ];
    }

    return [
        'allowed'  => true,
        'code'     => 200,
        'msg'      => 'OK',
        'approver' => $approver,
        'pemohon'  => $pemohon,
    ];
}

/**
 * Fungsi pembantu boolean: apakah $user sah menyetujui sebagai pengganti untuk pengajuan milik $pemohonId.
 */
function bolehApprovePengganti($user, $pemohonId) {
    $res = getDetailApprovePengganti($user, $pemohonId);
    return !empty($res['allowed']);
}

/**
 * Peta aturan bisnis pejabat pengganti untuk tahap verifikasi modul ('diajukan' / 'disetujui_atasan')
 * saat petugas verifikator asli berhalangan hadir:
 * - Inventaris: Staff Inventaris digantikan oleh SPV Inventaris
 * - Maintenance: Team Leader digantikan oleh SPV Sapras
 * - Event: Staff CRM digantikan oleh SPV CRM
 */
function getPeranPenggantiVerifikator($modul) {
    $map = [
        'inventaris' => [
            'verifikator_role'  => 'staff_inventaris',
            'verifikator_label' => 'Staff Inventaris',
            'pengganti_roles'   => ['spv_inventaris'],
            'next_approve_role' => 'spv_inventaris',
            'next_approve_label'=> 'SPV Inventaris',
        ],
        'maintenance' => [
            'verifikator_role'  => 'team_leader',
            'verifikator_label' => 'Team Leader',
            'pengganti_roles'   => ['spv_sapras'],
            'next_approve_role' => 'spv_sapras',
            'next_approve_label'=> 'SPV Sapras',
        ],
        'event' => [
            'verifikator_role'  => 'staff_crm',
            'verifikator_label' => 'Staff CRM',
            'pengganti_roles'   => ['spv_crm'],
            'next_approve_role' => 'spv_crm',
            'next_approve_label'=> 'SPV CRM',
        ],
    ];
    return $map[(string)$modul] ?? null;
}

/**
 * Evaluasi apakah $user berhak memverifikasi pengajuan sebagai pengganti pada $modul untuk $pemohonId.
 */
function getDetailVerifikasiPengganti($user, $modul, $pemohonId) {
    global $conn;
    if (!$user || empty($user['id']) || empty($user['role'])) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pengguna tidak valid.', 'cfg' => null, 'pemohon' => null];
    }

    $uid = (int)$user['id'];
    $userRole = (string)$user['role'];
    $userNamaRole = (string)($user['nama_role'] ?? $userRole);
    $pemohonId = (int)$pemohonId;

    if ($pemohonId <= 0) {
        return ['allowed' => false, 'code' => 400, 'msg' => 'Pemohon tidak valid.', 'cfg' => null, 'pemohon' => null];
    }

    // Anti Self-Approval: pemohon tidak boleh memverifikasi pengajuannya sendiri
    if ($uid === $pemohonId) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pemohon tidak dapat memverifikasi pengajuannya sendiri sebagai pengganti.', 'cfg' => null, 'pemohon' => null];
    }

    $cfg = getPeranPenggantiVerifikator($modul);
    if (!$cfg) {
        return ['allowed' => false, 'code' => 400, 'msg' => 'Modul tidak dikenali.', 'cfg' => null, 'pemohon' => null];
    }

    if (!hasAccess($userRole, $modul, 'verifikasi_pengganti') || !in_array($userRole, $cfg['pengganti_roles'], true)) {
        return [
            'allowed' => false,
            'code'    => 403,
            'msg'     => "Akses ditolak. Peran {$userNamaRole} tidak berwenang menggantikan verifikasi {$cfg['verifikator_label']}.",
            'cfg'     => $cfg,
            'pemohon' => null,
        ];
    }

    $stmtP = $conn->prepare("SELECT u.id, u.name, r.kode AS role_kode, r.nama_role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $stmtP->bind_param("i", $pemohonId);
    $stmtP->execute();
    $pemohon = $stmtP->get_result()->fetch_assoc();

    return [
        'allowed' => true,
        'code'    => 200,
        'msg'     => 'OK',
        'cfg'     => $cfg,
        'pemohon' => $pemohon,
    ];
}

function bolehVerifikasiPengganti($user, $modul, $pemohonId) {
    $res = getDetailVerifikasiPengganti($user, $modul, $pemohonId);
    return !empty($res['allowed']);
}

/**
 * Peta aturan bisnis pejabat pengganti untuk tahap approve akhir ('diverifikasi')
 * saat SPV pengelola berhalangan hadir:
 * - Inventaris: SPV Inventaris digantikan oleh Manajer GA
 * - Maintenance: SPV Sapras digantikan oleh Manajer GA
 * - Event: SPV CRM digantikan oleh Manajer MARKOM
 */
function getPeranPenggantiApproveAkhir($modul) {
    $map = [
        'inventaris' => [
            'approver_role'   => 'spv_inventaris',
            'approver_label'  => 'SPV Inventaris',
            'pengganti_roles' => ['manajer_ga'],
        ],
        'maintenance' => [
            'approver_role'   => 'spv_sapras',
            'approver_label'  => 'SPV Sapras',
            'pengganti_roles' => ['manajer_ga'],
        ],
        'event' => [
            'approver_role'   => 'spv_crm',
            'approver_label'  => 'SPV CRM',
            'pengganti_roles' => ['manajer_markom'],
        ],
    ];
    return $map[(string)$modul] ?? null;
}

/**
 * Evaluasi apakah $user berhak melakukan approve akhir sebagai pengganti pada $modul untuk $pemohonId.
 */
function getDetailApproveAkhirPengganti($user, $modul, $pemohonId) {
    global $conn;
    if (!$user || empty($user['id']) || empty($user['role'])) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pengguna tidak valid.', 'cfg' => null, 'pemohon' => null];
    }

    $uid = (int)$user['id'];
    $userRole = (string)$user['role'];
    $userNamaRole = (string)($user['nama_role'] ?? $userRole);
    $pemohonId = (int)$pemohonId;

    if ($pemohonId <= 0) {
        return ['allowed' => false, 'code' => 400, 'msg' => 'Pemohon tidak valid.', 'cfg' => null, 'pemohon' => null];
    }

    // Anti Self-Approval: pemohon tidak boleh menyetujui pengajuannya sendiri
    if ($uid === $pemohonId) {
        return ['allowed' => false, 'code' => 403, 'msg' => 'Akses ditolak. Pemohon tidak dapat menyetujui pengajuannya sendiri sebagai pengganti.', 'cfg' => null, 'pemohon' => null];
    }

    $cfg = getPeranPenggantiApproveAkhir($modul);
    if (!$cfg) {
        return ['allowed' => false, 'code' => 400, 'msg' => 'Modul tidak dikenali.', 'cfg' => null, 'pemohon' => null];
    }

    if (!hasAccess($userRole, $modul, 'approve_akhir_pengganti') || !in_array($userRole, $cfg['pengganti_roles'], true)) {
        return [
            'allowed' => false,
            'code'    => 403,
            'msg'     => "Akses ditolak. Peran {$userNamaRole} tidak berwenang menggantikan persetujuan akhir {$cfg['approver_label']}.",
            'cfg'     => $cfg,
            'pemohon' => null,
        ];
    }

    $stmtP = $conn->prepare("SELECT u.id, u.name, r.kode AS role_kode, r.nama_role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $stmtP->bind_param("i", $pemohonId);
    $stmtP->execute();
    $pemohon = $stmtP->get_result()->fetch_assoc();

    return [
        'allowed' => true,
        'code'    => 200,
        'msg'     => 'OK',
        'cfg'     => $cfg,
        'pemohon' => $pemohon,
    ];
}

function bolehApproveAkhirPengganti($user, $modul, $pemohonId) {
    $res = getDetailApproveAkhirPengganti($user, $modul, $pemohonId);
    return !empty($res['allowed']);
}

/**
 * Fungsi bersama untuk memproses approve_atasan (jalur normal maupun jalur pengganti)
 * pada modul 'inventaris', 'maintenance', dan 'event' di dalam transaksi SELECT ... FOR UPDATE.
 */
function prosesApproveAtasanModul($conn, $modul, $id, $user, $setuju, $catatanAtasan = '', $viaPengganti = false, $alasanPengganti = '') {
    $id = (int)$id;
    if ($id < 1) {
        return ['ok' => false, 'code' => 400, 'msg' => 'ID pengajuan tidak valid.'];
    }

    $role = (string)($user['role'] ?? '');
    $uid = (int)($user['id'] ?? 0);
    $catatanAtasan = trim((string)$catatanAtasan);
    $alasan = trim((string)$alasanPengganti);

    $configMap = [
        'inventaris' => [
            'table'       => 'peminjaman',
            'pemohon_col' => 'peminjam_id',
            'jenis_label' => 'peminjaman',
            'next_role'   => 'staff_inventaris',
            'link_page'   => 'inventory',
            'link_tab'    => 'pinjam',
        ],
        'maintenance' => [
            'table'       => 'pengajuan_maintenance',
            'pemohon_col' => 'pemohon_id',
            'jenis_label' => 'maintenance',
            'next_role'   => 'team_leader',
            'link_page'   => 'maintenance',
            'link_tab'    => 'pengajuan',
        ],
        'event' => [
            'table'       => 'event',
            'pemohon_col' => 'pemohon_id',
            'jenis_label' => 'event',
            'next_role'   => 'staff_crm',
            'link_page'   => 'event',
            'link_tab'    => 'pengajuan',
        ],
    ];

    if (!isset($configMap[$modul])) {
        return ['ok' => false, 'code' => 400, 'msg' => 'Modul tidak dikenali.'];
    }
    $cfg = $configMap[$modul];
    $table = $cfg['table'];
    $pemohonCol = $cfg['pemohon_col'];

    if ($viaPengganti) {
        if (!hasAccess($role, $modul, 'approve_pengganti')) {
            return ['ok' => false, 'code' => 403, 'msg' => 'Akses ditolak. Anda tidak memiliki hak akses persetujuan pengganti.'];
        }
        $alasanLen = mb_strlen($alasan, 'UTF-8');
        if ($alasanLen < 10 || $alasanLen > 255) {
            return ['ok' => false, 'code' => 400, 'msg' => 'Alasan pengganti wajib diisi antara 10 hingga 255 karakter.'];
        }
    } else {
        if (!hasAccess($role, $modul, 'approve_atasan')) {
            return ['ok' => false, 'code' => 403, 'msg' => 'Akses ditolak. Anda tidak memiliki hak akses persetujuan atasan.'];
        }
    }

    $statusBaru = $setuju ? 'disetujui_atasan' : 'ditolak_atasan';
    $eval = null;
    $row = null;

    $conn->begin_transaction();
    try {
        $stmtLock = $conn->prepare("SELECT * FROM {$table} WHERE id = ? FOR UPDATE");
        $stmtLock->bind_param("i", $id);
        $stmtLock->execute();
        $row = $stmtLock->get_result()->fetch_assoc();

        if (!$row) {
            $conn->rollback();
            return ['ok' => false, 'code' => 400, 'msg' => 'Data pengajuan tidak ditemukan.'];
        }

        if ($row['status'] !== 'menunggu_approval_atasan') {
            $conn->rollback();
            return [
                'ok'   => false,
                'code' => 400,
                'msg'  => "Pengajuan #$id tidak dapat diproses karena status saat ini adalah '{$row['status']}' (harus 'menunggu_approval_atasan').",
            ];
        }

        $pemohonId = (int)$row[$pemohonCol];

        if ($viaPengganti) {
            $eval = getDetailApprovePengganti($user, $pemohonId);
            if (empty($eval['allowed'])) {
                $conn->rollback();
                return ['ok' => false, 'code' => (int)($eval['code'] ?? 403), 'msg' => $eval['msg']];
            }

            $catatanFinal = $catatanAtasan !== '' ? $catatanAtasan : $alasan;
            $stmtUpd = $conn->prepare("UPDATE {$table} SET status = ?, atasan_id = ?, catatan_atasan = ?, approval_pengganti_oleh = ?, approval_pengganti_alasan = ?, approval_pengganti_pada = NOW() WHERE id = ?");
            $stmtUpd->bind_param("sisisi", $statusBaru, $uid, $catatanFinal, $uid, $alasan, $id);
            $stmtUpd->execute();
        } else {
            $expectedAtasanId = (int)(getAtasanId($pemohonId) ?? 0);
            if (!$expectedAtasanId || $uid !== $expectedAtasanId) {
                $conn->rollback();
                return ['ok' => false, 'code' => 403, 'msg' => 'Akses ditolak. Anda bukan atasan langsung dari pemohon ini.'];
            }

            $stmtUpd = $conn->prepare("UPDATE {$table} SET status = ?, atasan_id = ?, catatan_atasan = ? WHERE id = ?");
            $stmtUpd->bind_param("sisi", $statusBaru, $uid, $catatanAtasan, $id);
            $stmtUpd->execute();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    $pemohonId = (int)$row[$pemohonCol];
    $prioritas = ($modul === 'maintenance' && ($row['prioritas'] ?? '') === 'darurat') ? 'darurat' : 'normal';
    $prefix = ($prioritas === 'darurat') ? '[DARURAT] ' : '';

    if ($viaPengganti) {
        $kataKeputusan = $setuju ? 'menyetujui' : 'menolak';
        $namaPengganti = $user['name'] ?? $user['username'] ?? 'Pengganti';
        $rolePengganti = $user['nama_role'] ?? $role;
        $namaPemohon = $eval['pemohon']['name'] ?? ('User #' . $pemohonId);
        $namaApproverAsli = $eval['approver']['name'] ?? 'Atasan';
        $approverAsliId = (int)($eval['approver']['id'] ?? 0);

        $logText = "Approval sebagai PENGGANTI: {$namaPengganti} ({$rolePengganti}) {$kataKeputusan} {$cfg['jenis_label']} #{$id} milik {$namaPemohon} menggantikan {$namaApproverAsli}. Alasan: {$alasan}";
        logAktivitas($uid, $modul, $logText);

        // Notifikasi ke approver asli bahwa pengajuan diambil alih
        if ($approverAsliId > 0) {
            kirimNotifikasi(
                $approverAsliId,
                $modul,
                $prefix . "Pengajuan {$cfg['jenis_label']} #{$id} milik {$namaPemohon} diambil alih ({$kataKeputusan}) oleh {$namaPengganti} ({$rolePengganti}), alasan: {$alasan}",
                [
                    'kategori'  => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => $cfg['link_page'],
                    'link_tab'  => $cfg['link_tab'],
                ]
            );
        }
    } else {
        logAktivitas($uid, $modul, "Approval Atasan untuk {$cfg['jenis_label']} #$id: $statusBaru");
    }

    // Notifikasi ke pengelola tahap berikutnya (jika disetujui) dan ke pemohon
    if ($modul === 'inventaris') {
        if ($setuju) {
            notifyRole('staff_inventaris', 'inventaris', 'Ada peminjaman barang (' . ($row['keperluan'] ?? '') . ') yang disetujui atasan, mohon diverifikasi.', null, [
                'kategori'  => 'persetujuan',
                'prioritas' => 'normal',
                'link_page' => 'inventory',
                'link_tab'  => 'pinjam',
            ]);
            $msgPemohon = $viaPengganti
                ? "Pengajuan peminjaman #{$id} Anda disetujui oleh pejabat pengganti ({$user['name']}), diteruskan ke Staff Inventaris. Alasan: {$alasan}"
                : 'Pengajuan peminjaman Anda disetujui atasan, diteruskan ke Staff Inventaris.';
            kirimNotifikasi($pemohonId, 'inventaris', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'prioritas' => 'normal',
                'link_page' => 'inventory',
                'link_tab'  => 'pinjam',
            ]);
        } else {
            $msgPemohon = $viaPengganti
                ? "Pengajuan peminjaman #{$id} Anda DITOLAK oleh pejabat pengganti ({$user['name']}). Alasan: {$alasan}"
                : 'Pengajuan peminjaman Anda DITOLAK oleh atasan.';
            kirimNotifikasi($pemohonId, 'inventaris', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'prioritas' => 'normal',
                'link_page' => 'inventory',
                'link_tab'  => 'pinjam',
            ]);
        }
    } elseif ($modul === 'maintenance') {
        if ($setuju) {
            notifyRole('team_leader', 'maintenance', $prefix . 'Ada pengajuan maintenance yang disetujui atasan, mohon diperiksa.', null, [
                'kategori'  => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab'  => 'pengajuan',
            ]);
            $msgPemohon = $viaPengganti
                ? $prefix . "Pengajuan maintenance #{$id} Anda disetujui oleh pejabat pengganti ({$user['name']}), diteruskan ke Team Leader. Alasan: {$alasan}"
                : $prefix . 'Pengajuan maintenance Anda disetujui atasan, diteruskan ke Team Leader.';
            kirimNotifikasi($pemohonId, 'maintenance', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab'  => 'pengajuan',
            ]);
        } else {
            $msgPemohon = $viaPengganti
                ? $prefix . "Pengajuan maintenance #{$id} Anda DITOLAK oleh pejabat pengganti ({$user['name']}). Alasan: {$alasan}"
                : $prefix . 'Pengajuan maintenance Anda DITOLAK oleh atasan.';
            kirimNotifikasi($pemohonId, 'maintenance', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab'  => 'pengajuan',
            ]);
        }
    } elseif ($modul === 'event') {
        $namaEv = $row['nama_event'] ?? ('#' . $id);
        if ($setuju) {
            notifyRole('staff_crm', 'event', "Pengajuan event \"{$namaEv}\" telah disetujui atasan, mohon diverifikasi.", null, [
                'kategori'  => 'persetujuan',
                'link_page' => 'event',
                'link_tab'  => 'pengajuan',
            ]);
            $msgPemohon = $viaPengganti
                ? "Pengajuan event Anda (\"{$namaEv}\") disetujui oleh pejabat pengganti ({$user['name']}), diteruskan ke Staff CRM. Alasan: {$alasan}"
                : "Pengajuan event Anda (\"{$namaEv}\") disetujui atasan, diteruskan ke Staff CRM.";
            kirimNotifikasi($pemohonId, 'event', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'link_page' => 'event',
                'link_tab'  => 'pengajuan',
            ]);
        } else {
            $msgPemohon = $viaPengganti
                ? "Pengajuan event Anda (\"{$namaEv}\") DITOLAK oleh pejabat pengganti ({$user['name']}). Alasan: {$alasan}"
                : "Pengajuan event Anda (\"{$namaEv}\") DITOLAK oleh atasan.";
            kirimNotifikasi($pemohonId, 'event', $msgPemohon, [
                'kategori'  => 'persetujuan',
                'link_page' => 'event',
                'link_tab'  => 'pengajuan',
            ]);
        }
    }

    return ['ok' => true, 'status' => $statusBaru];
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
    $namaUser = null;
    $roleUser = null;
    $userIdInt = !empty($userId) ? (int)$userId : null;

    if ($userIdInt) {
        $currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
        if ($currentUser && (int)($currentUser['id'] ?? 0) === $userIdInt) {
            $namaUser = $currentUser['name'] ?? null;
            $roleUser = $currentUser['nama_role'] ?? ($currentUser['role'] ?? null);
        } else {
            $stmtU = $conn->prepare("SELECT u.name, r.nama_role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
            if ($stmtU) {
                $stmtU->bind_param("i", $userIdInt);
                $stmtU->execute();
                $uRow = $stmtU->get_result()->fetch_assoc();
                $namaUser = $uRow['name'] ?? null;
                $roleUser = $uRow['nama_role'] ?? null;
            }
        }
    }

    $ipAddress = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? null);
    $aktivitas = mb_substr((string)$aktivitas, 0, 500);

    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, nama_user, role_user, ip_address, modul, aktivitas) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userIdInt, $namaUser, $roleUser, $ipAddress, $modul, $aktivitas);
    $stmt->execute();
}
function kirimNotifikasi($userId, $modulAsal, $pesan, $opts = []) {
    global $conn;
    $userId = (int)$userId;
    if ($userId <= 0) return false;

    $pesan = trim((string)$pesan);
    if ($pesan === '') return false;

    // 1. Anti-spam: Lewati notifikasi yang isinya sama persis untuk user yang sama dalam 30 detik terakhir
    $cekStmt = $conn->prepare("SELECT id FROM notifikasi WHERE user_id = ? AND pesan = ? AND waktu >= DATE_SUB(NOW(), INTERVAL 30 SECOND) LIMIT 1");
    if ($cekStmt) {
        $cekStmt->bind_param("is", $userId, $pesan);
        $cekStmt->execute();
        if ($cekStmt->get_result()->fetch_assoc()) {
            return false; // Skip duplikat dalam 30 detik
        }
    }

    // 2. Opsi default (kompatibel ke belakang)
    $kategori = !empty($opts['kategori']) ? trim($opts['kategori']) : 'info';
    $prioritas = (!empty($opts['prioritas']) && in_array($opts['prioritas'], ['normal', 'darurat'], true)) ? $opts['prioritas'] : 'normal';
    $linkPage = !empty($opts['link_page']) ? trim($opts['link_page']) : null;
    $linkTab = !empty($opts['link_tab']) ? trim($opts['link_tab']) : null;

    $stmt = $conn->prepare("INSERT INTO notifikasi (user_id, modul_asal, pesan, kategori, prioritas, link_page, link_tab) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssss", $userId, $modulAsal, $pesan, $kategori, $prioritas, $linkPage, $linkTab);
        $ok = $stmt->execute();
        return $ok ? ($stmt->insert_id ?: true) : false;
    }
    return false;
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
function notifyRole($role, $modulAsal, $pesan, $unitId = null, $opts = []) {
    foreach (getUserIdsByRole($role, $unitId) as $uid) {
        kirimNotifikasi($uid, $modulAsal, $pesan, $opts);
    }
}

/* ═══════════════════════════════════════════════════════════════════
 * HIERARKI KARYAWAN & CAKUPAN AKSES (LOOP PHP, KOMPATIBEL SEMUA DB)
 * ═══════════════════════════════════════════════════════════════════ */

/** Ambil baris data karyawan berdasarkan user_id login. */
function getKaryawanByUserId($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM karyawan WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/** Menghitung seluruh karyawan.id bawahan (semua tingkat) dari seorang atasan menggunakan PHP loop. */
function getSubordinateKaryawanIds($karyawanId, $includeSelf = true) {
    global $conn;
    $karyawanId = (int)$karyawanId;
    if ($karyawanId <= 0) return [];

    $result = $includeSelf ? [$karyawanId] : [];
    $currentLevel = [$karyawanId];

    while (!empty($currentLevel)) {
        $idsList = implode(',', array_map('intval', $currentLevel));
        $q = $conn->query("SELECT id FROM karyawan WHERE atasan_id IN ($idsList)");
        $nextLevel = [];
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $cid = (int)$row['id'];
                if (!in_array($cid, $result, true)) {
                    $result[] = $cid;
                    $nextLevel[] = $cid;
                }
            }
        }
        $currentLevel = $nextLevel;
    }
    return $result;
}

/** Validasi apakah pemilihan atasan baru akan membentuk siklus hierarki (lingkaran atasan-bawahan) atau diri sendiri. */
function wouldCreateKaryawanCycle($karyawanId, $newAtasanId) {
    $karyawanId = (int)$karyawanId;
    $newAtasanId = !empty($newAtasanId) ? (int)$newAtasanId : null;
    if (!$newAtasanId || !$karyawanId) return false;
    if ($newAtasanId === $karyawanId) return true;

    // Atasan tidak boleh termasuk dalam bawahan dari karyawan ini
    $subordinates = getSubordinateKaryawanIds($karyawanId, false);
    return in_array($newAtasanId, $subordinates, true);
}

/**
 * Mengecek apakah suatu jabatan termasuk pelaksana tugas operasional yang dinilai (CSO atau Gardener).
 */
function isPelaksanaOperasionalJabatan($jabatan) {
    $norm = mb_strtolower(trim((string)$jabatan), 'UTF-8');
    return in_array($norm, ['cso', 'gardener'], true);
}

// Konfigurasi Penilaian Kinerja Operasional CSO & Gardener
if (!defined('JAM_MULAI_RUTIN')) {
    define('JAM_MULAI_RUTIN', '04:30');
}
if (!defined('BOBOT_HARIAN')) {
    define('BOBOT_HARIAN', 0.85);
}
if (!defined('BOBOT_NONRUTIN')) {
    define('BOBOT_NONRUTIN', 0.15);
}

/**
 * Mendapatkan jam pulang berdasarkan jabatan karyawan (Gardener 15:30, CSO 16:30).
 */
function getJamPulangPelaksana($jabatan) {
    $norm = mb_strtolower(trim((string)$jabatan), 'UTF-8');
    if ($norm === 'gardener') return '15:30';
    if ($norm === 'cso') return '16:30';
    return null;
}

/**
 * Mengecek apakah penilaian harian seorang karyawan pada tanggal tertentu sudah tersimpan dan terkunci permanen.
 */
function isPenilaianHarianLocked($conn, $karyawanId, $tanggal) {
    $stmt = $conn->prepare("SELECT id, nilai, catatan, dinilai_oleh, created_at FROM penilaian_harian WHERE karyawan_id = ? AND tanggal = ? LIMIT 1");
    $stmt->bind_param("is", $karyawanId, $tanggal);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Validasi apakah CSO/Gardener boleh menginput/mengubah tugas rutin harian.
 */
function canInputTugasRutinSekarang($conn, $karyawanId, $tanggal = null, $currentTime = null) {
    $today = date('Y-m-d');
    $tgl = $tanggal ?: $today;
    if ($tgl !== $today) {
        return ['allowed' => false, 'reason' => 'Tugas rutin harian hanya dapat diisi untuk tanggal hari ini.'];
    }
    $testTime = (!empty($_SERVER['HTTP_X_TEST_TIME']) && preg_match('/^\d{2}:\d{2}$/', $_SERVER['HTTP_X_TEST_TIME'])) ? $_SERVER['HTTP_X_TEST_TIME'] : null;
    $now = $currentTime ?: ($testTime ?: date('H:i'));
    if ($now < JAM_MULAI_RUTIN) {
        return ['allowed' => false, 'reason' => 'Pengisian tugas rutin harian baru dapat dilakukan mulai pukul ' . JAM_MULAI_RUTIN . ' WIB.'];
    }
    if (isPenilaianHarianLocked($conn, $karyawanId, $tgl)) {
        return ['allowed' => false, 'reason' => 'Tugas hari ini sudah dinilai dan terkunci permanen.'];
    }
    return ['allowed' => true];
}

/**
 * Validasi apakah SPV Sapras boleh memberikan penilaian harian untuk seorang karyawan pada tanggal tertentu.
 */
function canSpvRateHarian($conn, $karyawanId, $tanggal, $customTime = null) {
    $today = date('Y-m-d');
    if ($tanggal > $today) {
        return ['allowed' => false, 'reason' => 'Tanggal penilaian tidak boleh di masa depan.'];
    }

    $locked = isPenilaianHarianLocked($conn, $karyawanId, $tanggal);
    if ($locked) {
        return ['allowed' => false, 'reason' => 'Nilai harian sudah diberikan dan terkunci permanen.'];
    }

    $stmtK = $conn->prepare("SELECT id, nama, jabatan FROM karyawan WHERE id = ? LIMIT 1");
    $stmtK->bind_param("i", $karyawanId);
    $stmtK->execute();
    $kar = $stmtK->get_result()->fetch_assoc();
    if (!$kar) {
        return ['allowed' => false, 'reason' => 'Data karyawan tidak ditemukan.'];
    }
    if (!isPelaksanaOperasionalJabatan($kar['jabatan'])) {
        return ['allowed' => false, 'reason' => 'Penilaian harian hanya berlaku untuk karyawan berjabatan CSO atau Gardener.'];
    }

    // Jika tanggal hari ini, hanya boleh dinilai setelah jam pulang
    if ($tanggal === $today) {
        $jamPulang = getJamPulangPelaksana($kar['jabatan']);
        $testTime = (!empty($_SERVER['HTTP_X_TEST_TIME']) && preg_match('/^\d{2}:\d{2}$/', $_SERVER['HTTP_X_TEST_TIME'])) ? $_SERVER['HTTP_X_TEST_TIME'] : null;
        $now = $customTime ?: ($testTime ?: date('H:i'));
        if ($jamPulang && $now <= $jamPulang) {
            return ['allowed' => false, 'reason' => "Penilaian hari ini hanya dapat dilakukan setelah jam pulang ({$jamPulang} WIB)."];
        }
    }

    // Wajib ada minimal 1 tugas rutin pada tanggal tersebut
    $stmtT = $conn->prepare("SELECT COUNT(*) AS n FROM kegiatan_karyawan WHERE karyawan_id = ? AND tanggal = ? AND jenis = 'rutin'");
    $stmtT->bind_param("is", $karyawanId, $tanggal);
    $stmtT->execute();
    $jmlTugas = (int)($stmtT->get_result()->fetch_assoc()['n'] ?? 0);
    if ($jmlTugas < 1) {
        return ['allowed' => false, 'reason' => 'Penilaian harian hanya dapat dilakukan jika ada minimal 1 tugas rutin pada tanggal tersebut.'];
    }

    return ['allowed' => true, 'karyawan' => $kar];
}

/**
 * Mengambil ID unit "Pimpinan/Direksi" dari struktur_unit (di-cache per request).
 */
function getPimpinanUnitId() {
    global $conn;
    static $cachedPimId = null;
    if ($cachedPimId !== null) return $cachedPimId;
    $res = $conn->query("SELECT id FROM struktur_unit WHERE nama_unit = 'Pimpinan/Direksi' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $cachedPimId = $row ? (int)$row['id'] : 0;
    return $cachedPimId;
}

/**
 * Mengecek apakah user memiliki hak akses melihat/mengelola unit sensitif Pimpinan/Direksi.
 * Diatur melalui izin 'lihat_pimpinan' pada modul 'karyawan' di tabel hak_akses (hanya manajer_hrd).
 */
function canAccessPimpinanUnit($user) {
    if (!$user) return false;
    $role = $user['role'] ?? '';
    return hasAccess($role, 'karyawan', 'lihat_pimpinan');
}

/**
 * Mengecek apakah sebuah baris karyawan (atau divisi_id) berada di unit Pimpinan/Direksi.
 */
function isKaryawanPimpinanRow($karRow) {
    if (!$karRow) return false;
    $pimId = getPimpinanUnitId();
    if ($pimId > 0 && isset($karRow['divisi_id']) && (int)$karRow['divisi_id'] === $pimId) {
        return true;
    }
    if (!empty($karRow['nama_unit']) && trim((string)$karRow['nama_unit']) === 'Pimpinan/Direksi') {
        return true;
    }
    $jab = mb_strtolower(trim((string)($karRow['jabatan'] ?? '')), 'UTF-8');
    if (in_array($jab, ['direktur', 'general manager operasional', 'general manager pendidikan', 'gmo', 'gmp'], true)) {
        return true;
    }
    return false;
}

/**
 * Mengecek apakah user berhak melihat/mengakses suatu baris karyawan spesifik (termasuk proteksi unit Pimpinan/Direksi).
 * Anggota Pimpinan/Direksi hanya boleh diakses oleh pemegang izin 'lihat_pimpinan' (manajer_hrd)
 * atau pemilik data itu sendiri bila punya akun login (user_id).
 */
function canUserAccessKaryawanRow($user, $karRow) {
    if (!$user || !$karRow) return false;
    if (!isKaryawanPimpinanRow($karRow)) return true;
    if (canAccessPimpinanUnit($user)) return true;
    $uid = (int)($user['id'] ?? 0);
    if ($uid > 0 && !empty($karRow['user_id']) && (int)$karRow['user_id'] === $uid) {
        return true;
    }
    return false;
}

/**
 * Menghasilkan klausa SQL WHERE untuk menyembunyikan karyawan unit Pimpinan/Direksi
 * bagi pengguna yang tidak memiliki izin 'lihat_pimpinan' (kecuali data miliknya sendiri).
 */
function getPimpinanExclusionSql($user, $tableAlias = 'k') {
    if (canAccessPimpinanUnit($user)) {
        return "1=1";
    }
    $pimId = getPimpinanUnitId();
    $uid = (int)($user['id'] ?? 0);
    $pfx = ($tableAlias !== '') ? ($tableAlias . '.') : '';
    $conds = [];
    if ($pimId > 0) {
        $conds[] = "({$pfx}divisi_id IS NULL OR {$pfx}divisi_id != $pimId)";
    }
    $conds[] = "LOWER(TRIM(COALESCE({$pfx}jabatan, ''))) NOT IN ('direktur', 'general manager operasional', 'general manager pendidikan', 'gmo', 'gmp')";
    $notPimSql = implode(' AND ', $conds);
    if ($uid > 0) {
        return "(($notPimSql) OR {$pfx}user_id = $uid)";
    }
    return "($notPimSql)";
}

/**
 * Mengecek apakah user adalah pengelola administratif HRD yang tidak boleh melihat tugas maupun penilaian.
 */
function isHrdAdministratifRole($user) {
    if (!$user) return false;
    $role = $user['role'] ?? '';
    return (hasAccess($role, 'karyawan', 'kelola_master') && !hasAccess($role, 'karyawan', 'monitoring'))
        || in_array($role, ['manajer_hrd', 'staff_hrd'], true);
}

/** 
 * Mengecek apakah user memiliki hak akses melihat seluruh daftar / data administratif karyawan.
 * Berdasarkan izin 'kelola_master' atau 'monitoring' pada tabel hak_akses.
 */
function canViewAllKaryawanAdministratif($user) {
    if (!$user) return false;
    $role = $user['role'] ?? '';
    return hasAccess($role, 'karyawan', 'kelola_master') || hasAccess($role, 'karyawan', 'monitoring');
}

/** 
 * Mengecek apakah user memiliki hak akses monitoring seluruh tugas, kegiatan, dan kinerja karyawan.
 * Berdasarkan izin 'monitoring' pada modul 'karyawan' di tabel hak_akses.
 */
function canViewAllKaryawan($user) {
    if (!$user) return false;
    $role = $user['role'] ?? '';
    return hasAccess($role, 'karyawan', 'monitoring');
}

/** Mendapatkan daftar ID karyawan yang dapat diakses oleh user saat ini. Mengembalikan null jika tanpa batas (lihat semua). */
function getAccessibleKaryawanIds($user, $includeSelf = false) {
    if (canViewAllKaryawan($user)) {
        return null;
    }
    $kar = getKaryawanByUserId($user['id']);
    if (!$kar) {
        return [];
    }
    return getSubordinateKaryawanIds($kar['id'], $includeSelf);
}

/**
 * Mengecek apakah user relevan untuk mengakses modul Manajemen Karyawan:
 * - Admin & HRD (pengelola master data karyawan)
 * - CSO & Gardener (pelaksana tugas rutin harian & penerima penilaian)
 * - Atasan yang memiliki minimal 1 bawahan di tabel karyawan
 */
function canAccessKaryawanModule($user) {
    if (!$user) return false;
    $role = $user['role'] ?? '';
    if (!hasAccess($role, 'karyawan', 'lihat')) return false;
    if (canViewAllKaryawanAdministratif($user) || hasAccess($role, 'karyawan', 'penilaian')) return true;
    if (in_array($role, ['cso', 'gardener'], true) || hasAccess($role, 'karyawan', 'tugas_rutin_sendiri')) return true;
    $subIds = getAccessibleKaryawanIds($user, false);
    return $subIds === null || !empty($subIds);
}

/**
 * Pemeriksaan ringan reminder tugas kegiatan belum selesai (tanpa cron).
 * Dipanggil dari polling notifikasi (maksimal 1 kali per jam per sesi).
 * Jenis: H-1, Hari-H, dan Terlambat (terlambat dikirimkan juga ke atasan langsung).
 */
function triggerReminderKegiatan($userId, $force = false) {
    global $conn;
    if (!$userId) return 0;

    // Pembatas frekuensi: maksimal 1x per jam per sesi untuk efisiensi polling
    if (!$force && isset($_SESSION['last_reminder_check']) && (time() - $_SESSION['last_reminder_check']) < 3600) {
        return 0;
    }
    $_SESSION['last_reminder_check'] = time();

    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // Query ringan terindeks status dan tenggat/tanggal
    $sql = "
        SELECT kk.id, kk.deskripsi, kk.tanggal, kk.tenggat, kk.status,
               COALESCE(kk.tenggat, kk.tanggal) AS effective_due,
               k.id AS karyawan_id, k.nama AS karyawan_nama, k.user_id,
               k_atasan.user_id AS atasan_user_id
        FROM kegiatan_karyawan kk
        JOIN karyawan k ON k.id = kk.karyawan_id
        LEFT JOIN karyawan k_atasan ON k_atasan.id = k.atasan_id
        WHERE kk.status IN ('ditugaskan', 'dikerjakan')
          AND COALESCE(kk.tenggat, kk.tanggal) <= '$tomorrow'
    ";

    $res = $conn->query($sql);
    if (!$res) return 0;

    $notifSent = 0;
    while ($row = $res->fetch_assoc()) {
        $taskId = (int)$row['id'];
        $dueDate = $row['effective_due'];
        $descShort = mb_substr($row['deskripsi'], 0, 45);
        $karyawanUserId = $row['user_id'] ? (int)$row['user_id'] : null;
        $atasanUserId = $row['atasan_user_id'] ? (int)$row['atasan_user_id'] : null;

        $type = null;
        if ($dueDate === $tomorrow) {
            $type = 'h-1';
        } elseif ($dueDate === $today) {
            $type = 'hari_h';
        } elseif ($dueDate < $today) {
            $type = 'terlambat';
        }

        if (!$type) continue;

        // Cegah duplikat dengan UNIQUE KEY uq_reminder(kegiatan_id, jenis, tanggal)
        $ins = $conn->prepare("INSERT IGNORE INTO reminder_kegiatan (kegiatan_id, jenis, tanggal) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $taskId, $type, $today);
        $ins->execute();

        if ($ins->affected_rows > 0) {
            $notifSent++;
            if ($type === 'h-1' && $karyawanUserId) {
                kirimNotifikasi($karyawanUserId, 'karyawan', "Pengingat H-1: Tugas '$descShort...' jatuh tempo besok ($dueDate).", ['kategori' => 'pengingat', 'link_page' => 'karyawan', 'link_tab' => 'tabKegiatan']);
            } elseif ($type === 'hari_h' && $karyawanUserId) {
                kirimNotifikasi($karyawanUserId, 'karyawan', "Pengingat Hari-H: Tugas '$descShort...' jatuh tempo hari ini ($dueDate).", ['kategori' => 'pengingat', 'link_page' => 'karyawan', 'link_tab' => 'tabKegiatan']);
            } elseif ($type === 'terlambat') {
                if ($karyawanUserId) {
                    kirimNotifikasi($karyawanUserId, 'karyawan', "Peringatan Terlambat: Tugas '$descShort...' telah melewati tenggat ($dueDate).", ['kategori' => 'pengingat', 'prioritas' => 'darurat', 'link_page' => 'karyawan', 'link_tab' => 'tabKegiatan']);
                }
                if ($atasanUserId && $atasanUserId !== $karyawanUserId) {
                    kirimNotifikasi($atasanUserId, 'karyawan', "Peringatan Terlambat: Tugas '$descShort...' oleh {$row['karyawan_nama']} belum selesai dan telah melewati tenggat ($dueDate).", ['kategori' => 'pengingat', 'prioritas' => 'darurat', 'link_page' => 'karyawan', 'link_tab' => 'tabKegiatan']);
                }
            }
        }
    }

    return $notifSent;
}

/**
 * Evaluasi malas (lazy evaluation) untuk semua pengingat berkala saat polling.
 * Dibatasi maksimal 1x per menit per sesi untuk menjaga efisiensi server.
 * Menggunakan reminder_log (UNIQUE: user_id, jenis, ref_id, tanggal) untuk anti-duplikasi.
 */
function evaluateLazyReminders($userId) {
    global $conn;
    $userId = (int)$userId;
    if ($userId <= 0 || !$conn) return 0;

    $nowTs = time();
    if (isset($_SESSION['last_lazy_reminder_check']) && ($nowTs - (int)$_SESSION['last_lazy_reminder_check'] < 60)) {
        return 0;
    }
    $_SESSION['last_lazy_reminder_check'] = $nowTs;
    $sentCount = 0;

    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $testTime = (!empty($_SERVER['HTTP_X_TEST_TIME']) && preg_match('/^\d{2}:\d{2}$/', $_SERVER['HTTP_X_TEST_TIME'])) ? $_SERVER['HTTP_X_TEST_TIME'] : null;
    $currentTime = $testTime ?: date('H:i');

    $uRes = $conn->query("SELECT u.id, u.username, r.kode AS role_kode FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = $userId LIMIT 1");
    $uRow = $uRes ? $uRes->fetch_assoc() : null;
    if (!$uRow) return 0;
    $userRole = $uRow['role_kode'];

    // 1. PENGINGAT SPV SAPRAS (Evaluasi Tugas Rutin Belum Dinilai Setelah Jam Pulang)
    // Gardener 15:30, CSO 16:30
    if ($userRole === 'spv_sapras') {
        if ($currentTime > '15:30') {
            $sqlUnrated = "
                SELECT COUNT(DISTINCT kk.karyawan_id) AS n
                FROM kegiatan_karyawan kk
                JOIN karyawan k ON k.id = kk.karyawan_id
                LEFT JOIN penilaian_harian ph ON ph.karyawan_id = kk.karyawan_id AND ph.tanggal = kk.tanggal
                WHERE kk.tanggal = '$today'
                  AND kk.jenis = 'rutin'
                  AND ph.id IS NULL
                  AND (
                      (LOWER(TRIM(k.jabatan)) LIKE '%gardener%' AND '$currentTime' > '15:30') OR
                      (LOWER(TRIM(k.jabatan)) LIKE '%cso%' AND '$currentTime' > '16:30')
                  )
            ";
            $resUnrated = $conn->query($sqlUnrated);
            $unratedCount = (int)($resUnrated ? $resUnrated->fetch_assoc()['n'] : 0);

            if ($unratedCount > 0) {
                $logIns = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'spv_rutin_harian', 0, ?)");
                if ($logIns) {
                    $logIns->bind_param("is", $userId, $today);
                    $logIns->execute();
                    if ($logIns->affected_rows > 0) {
                        kirimNotifikasi(
                            $userId,
                            'karyawan',
                            "Terdapat $unratedCount karyawan operasional yang tugas rutinnya belum dinilai hari ini.",
                            [
                                'kategori' => 'pengingat',
                                'prioritas' => 'normal',
                                'link_page' => 'karyawan',
                                'link_tab' => 'tabKegiatan'
                            ]
                        );
                        $sentCount++;
                    }
                }
            }
        }
    }

    // 2. PENGINGAT PENGEMBALIAN BARANG INVENTARIS (H-1 & Terlambat)
    $sqlInv = "
        SELECT p.id, p.peminjam_id, p.barang_id, p.rencana_kembali, b.nama_barang, p.peminjam_id AS peminjam_uid
        FROM peminjaman p
        JOIN barang b ON b.id = p.barang_id
        WHERE p.status IN ('disetujui', 'dipinjam')
          AND p.rencana_kembali IS NOT NULL
          AND p.rencana_kembali <= '$tomorrow'
    ";
    $resInv = $conn->query($sqlInv);
    if ($resInv) {
        while ($inv = $resInv->fetch_assoc()) {
            $pId = (int)$inv['id'];
            $peminjamUid = (int)$inv['peminjam_uid'];
            $dueDate = $inv['rencana_kembali'];
            $namaB = mb_substr($inv['nama_barang'], 0, 30);

            if ($dueDate === $tomorrow && $peminjamUid > 0) {
                // H-1
                $logIns = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'inv_kembali_h1', ?, ?)");
                if ($logIns) {
                    $logIns->bind_param("iis", $peminjamUid, $pId, $today);
                    $logIns->execute();
                    if ($logIns->affected_rows > 0) {
                        kirimNotifikasi(
                            $peminjamUid,
                            'inventaris',
                            "Pengingat: Batas waktu pengembalian barang '$namaB' jatuh tempo besok ($dueDate).",
                            [
                                'kategori' => 'pengingat',
                                'prioritas' => 'normal',
                                'link_page' => 'inventory',
                                'link_tab' => 'pengembalian'
                            ]
                        );
                        $sentCount++;
                    }
                }
            } elseif ($dueDate < $today) {
                // Terlambat
                if ($peminjamUid > 0) {
                    $logIns = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'inv_kembali_terlambat', ?, ?)");
                    if ($logIns) {
                        $logIns->bind_param("iis", $peminjamUid, $pId, $today);
                        $logIns->execute();
                        if ($logIns->affected_rows > 0) {
                            kirimNotifikasi(
                                $peminjamUid,
                                'inventaris',
                                "Peringatan Terlambat: Batas waktu pengembalian barang '$namaB' telah terlewat ($dueDate).",
                                [
                                    'kategori' => 'pengingat',
                                    'prioritas' => 'darurat',
                                    'link_page' => 'inventory',
                                    'link_tab' => 'pengembalian'
                                ]
                            );
                            $sentCount++;
                        }
                    }
                }

                // Notifikasi ke Staff Inventaris (terlambat)
                $staffIds = getUserIdsByRole('staff_inventaris');
                foreach ($staffIds as $sUid) {
                    $logStaff = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'inv_staff_terlambat', ?, ?)");
                    if ($logStaff) {
                        $logStaff->bind_param("iis", $sUid, $pId, $today);
                        $logStaff->execute();
                        if ($logStaff->affected_rows > 0) {
                            kirimNotifikasi(
                                $sUid,
                                'inventaris',
                                "Peringatan: Peminjaman barang '$namaB' telah melewati batas waktu pengembalian ($dueDate).",
                                [
                                    'kategori' => 'pengingat',
                                    'prioritas' => 'darurat',
                                    'link_page' => 'inventory',
                                    'link_tab' => 'pengembalian'
                                ]
                            );
                            $sentCount++;
                        }
                    }
                }
            }
        }
    }

    // 3. Pengingat tugas kegiatan karyawan (H-1 / Hari-H / Terlambat)
    triggerReminderKegiatan($userId, false);

    // 4. PENGINGAT LONCENG OTOMATIS 24 JAM (1 HARI): Pengajuan Belum Diverifikasi -> Eskalasi ke SPV Terkait
    // a) Inventaris -> SPV Inventaris
    $resEscInv = $conn->query("
        SELECT p.id, p.keperluan, b.nama_barang, DATE(p.created_at) AS tgl_ajuan
        FROM peminjaman p
        JOIN barang b ON b.id = p.barang_id
        WHERE p.status IN ('diajukan', 'disetujui_atasan')
          AND TIMESTAMPDIFF(HOUR, p.created_at, NOW()) >= 24
    ");
    if ($resEscInv) {
        $spvInvIds = getUserIdsByRole('spv_inventaris');
        while ($rowEsc = $resEscInv->fetch_assoc()) {
            $escId = (int)$rowEsc['id'];
            $namaB = mb_substr($rowEsc['nama_barang'] ?? 'Barang', 0, 35);
            $tglAjuan = $rowEsc['tgl_ajuan'] ?: $today;
            foreach ($spvInvIds as $spvUid) {
                $logEsc = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'eskalasi_verif_inv', ?, ?)");
                if ($logEsc) {
                    $logEsc->bind_param("iis", $spvUid, $escId, $tglAjuan);
                    $logEsc->execute();
                    if ($logEsc->affected_rows > 0) {
                        kirimNotifikasi(
                            $spvUid,
                            'inventaris',
                            "Pengingat 24 Jam: Peminjaman #$escId ($namaB) belum diverifikasi Staff Inventaris lebih dari 24 jam. Anda dapat memverifikasi sebagai pengganti bila petugas berhalangan.",
                            [
                                'kategori'  => 'pengingat',
                                'prioritas' => 'normal',
                                'link_page' => 'inventory',
                                'link_tab'  => 'pinjam',
                            ]
                        );
                        $sentCount++;
                    }
                }
            }
        }
    }

    // b) Maintenance -> SPV Sapras
    $resEscMaint = $conn->query("
        SELECT pm.id, pm.deskripsi_kerusakan, pm.prioritas, DATE(pm.created_at) AS tgl_ajuan
        FROM pengajuan_maintenance pm
        WHERE pm.status IN ('diajukan', 'disetujui_atasan')
          AND TIMESTAMPDIFF(HOUR, pm.created_at, NOW()) >= 24
    ");
    if ($resEscMaint) {
        $spvSaprasIds = getUserIdsByRole('spv_sapras');
        while ($rowEsc = $resEscMaint->fetch_assoc()) {
            $escId = (int)$rowEsc['id'];
            $descShort = mb_substr($rowEsc['deskripsi_kerusakan'] ?? 'Maintenance', 0, 35);
            $prioEsc = ($rowEsc['prioritas'] ?? 'normal') === 'darurat' ? 'darurat' : 'normal';
            $tglAjuan = $rowEsc['tgl_ajuan'] ?: $today;
            foreach ($spvSaprasIds as $spvUid) {
                $logEsc = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'eskalasi_verif_maint', ?, ?)");
                if ($logEsc) {
                    $logEsc->bind_param("iis", $spvUid, $escId, $tglAjuan);
                    $logEsc->execute();
                    if ($logEsc->affected_rows > 0) {
                        kirimNotifikasi(
                            $spvUid,
                            'maintenance',
                            "Pengingat 24 Jam: Pengajuan maintenance #$escId ($descShort) belum diverifikasi Team Leader lebih dari 24 jam. Anda dapat memverifikasi sebagai pengganti bila petugas berhalangan.",
                            [
                                'kategori'  => 'pengingat',
                                'prioritas' => $prioEsc,
                                'link_page' => 'maintenance',
                                'link_tab'  => 'pengajuan',
                            ]
                        );
                        $sentCount++;
                    }
                }
            }
        }
    }

    // c) Event -> SPV CRM
    $resEscEv = $conn->query("
        SELECT e.id, e.nama_event, DATE(e.created_at) AS tgl_ajuan
        FROM event e
        WHERE e.status IN ('diajukan', 'disetujui_atasan')
          AND TIMESTAMPDIFF(HOUR, e.created_at, NOW()) >= 24
    ");
    if ($resEscEv) {
        $spvCrmIds = getUserIdsByRole('spv_crm');
        while ($rowEsc = $resEscEv->fetch_assoc()) {
            $escId = (int)$rowEsc['id'];
            $namaEv = mb_substr($rowEsc['nama_event'] ?? 'Event', 0, 35);
            $tglAjuan = $rowEsc['tgl_ajuan'] ?: $today;
            foreach ($spvCrmIds as $spvUid) {
                $logEsc = $conn->prepare("INSERT IGNORE INTO reminder_log (user_id, jenis, ref_id, tanggal) VALUES (?, 'eskalasi_verif_event', ?, ?)");
                if ($logEsc) {
                    $logEsc->bind_param("iis", $spvUid, $escId, $tglAjuan);
                    $logEsc->execute();
                    if ($logEsc->affected_rows > 0) {
                        kirimNotifikasi(
                            $spvUid,
                            'event',
                            "Pengingat 24 Jam: Pengajuan event \"$namaEv\" (#$escId) belum diverifikasi Staff CRM lebih dari 24 jam. Anda dapat memverifikasi sebagai pengganti bila petugas berhalangan.",
                            [
                                'kategori'  => 'pengingat',
                                'prioritas' => 'normal',
                                'link_page' => 'event',
                                'link_tab'  => 'pengajuan',
                            ]
                        );
                        $sentCount++;
                    }
                }
            }
        }
    }

    return $sentCount;
}

