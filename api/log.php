<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

function ok($data = []) {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit();
}

function fail($msg) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit();
}

// Cek hak akses ke modul log_aktivitas aksi 'lihat' (admin, manajer_hrd, manajer_ga)
if ($role !== 'admin' && !hasAccess($role, 'log_aktivitas', 'lihat')) {
    http_response_code(403);
    fail('Akses ditolak. Anda tidak memiliki izin untuk melihat log aktivitas.');
}

$isHRD = in_array($role, ['manajer_hrd', 'staff_hrd'], true);
$isManajerGA = ($role === 'manajer_ga');

// Helper function untuk mengambil seluruh user_id bawahan GA (semua tingkat) + user_id Manajer GA
function getGAUserIdsHierarchy($userId) {
    global $conn;
    $myKar = getKaryawanByUserId($userId);
    $gaUserIds = [(int)$userId];
    if ($myKar) {
        $subKaryawanIds = getSubordinateKaryawanIds($myKar['id'], true);
        if (!empty($subKaryawanIds)) {
            $idsStr = implode(',', array_map('intval', $subKaryawanIds));
            $uRows = $conn->query("SELECT user_id FROM karyawan WHERE id IN ($idsStr) AND user_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
            foreach ($uRows as $ur) {
                $gaUserIds[] = (int)$ur['user_id'];
            }
        }
    }
    return array_values(array_unique($gaUserIds));
}

// Aturan pembatasan modul di sisi server:
// 1. Admin: melihat semua modul
// 2. HRD: daftar putih HANYA modul 'karyawan' (data administratif)
// 3. Manajer GA: daftar putih HANYA modul 'karyawan_kinerja' (kinerja karyawan)
if ($role === 'admin') {
    $filterModul = trim($_REQUEST['modul'] ?? '');
} elseif ($isHRD) {
    $reqModul = trim($_REQUEST['modul'] ?? '');
    if ($reqModul !== '' && $reqModul !== 'karyawan') {
        http_response_code(403);
        fail('Akses ditolak. Anda hanya berwenang melihat log aktivitas administratif modul karyawan.');
    }
    $filterModul = 'karyawan';
} elseif ($isManajerGA) {
    $reqModul = trim($_REQUEST['modul'] ?? '');
    if ($reqModul !== '' && $reqModul !== 'karyawan_kinerja') {
        http_response_code(403);
        fail('Akses ditolak. Anda hanya berwenang melihat log aktivitas modul kinerja karyawan.');
    }
    $filterModul = 'karyawan_kinerja';
} else {
    http_response_code(403);
    fail('Akses ditolak.');
}

switch ($action) {

    case 'filter_options':
        // Daftar pengguna untuk dropdown filter
        if ($isManajerGA) {
            $gaUserIds = getGAUserIdsHierarchy($user['id']);
            $ph = implode(',', array_fill(0, count($gaUserIds), '?'));
            $stmtU = $conn->prepare("SELECT id, name, username FROM users WHERE id IN ($ph) ORDER BY name ASC");
            $typesU = str_repeat('i', count($gaUserIds));
            $stmtU->bind_param($typesU, ...$gaUserIds);
            $stmtU->execute();
            $usersList = $stmtU->get_result()->fetch_all(MYSQLI_ASSOC);
        } else {
            $usersList = $conn->query("SELECT id, name, username FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
        }

        // Daftar modul untuk dropdown filter
        if ($role === 'admin') {
            $modulesList = [
                ['id' => 'karyawan', 'nama' => 'Karyawan (Administratif)'],
                ['id' => 'karyawan_kinerja', 'nama' => 'Kinerja Karyawan'],
                ['id' => 'inventaris', 'nama' => 'Inventaris'],
                ['id' => 'event', 'nama' => 'Event'],
                ['id' => 'maintenance', 'nama' => 'Maintenance'],
                ['id' => 'arsip', 'nama' => 'Arsip'],
                ['id' => 'pengajuan_unit', 'nama' => 'Pengajuan Unit'],
                ['id' => 'laporan', 'nama' => 'Laporan'],
                ['id' => 'akun', 'nama' => 'Akun Pengguna'],
                ['id' => 'auth', 'nama' => 'Autentikasi / Sesi'],
                ['id' => 'log_aktivitas', 'nama' => 'Log Aktivitas']
            ];
        } elseif ($isManajerGA) {
            $modulesList = [
                ['id' => 'karyawan_kinerja', 'nama' => 'Kinerja Karyawan']
            ];
        } else {
            $modulesList = [
                ['id' => 'karyawan', 'nama' => 'Karyawan (Administratif)']
            ];
        }

        ok([
            'users' => $usersList,
            'modules' => $modulesList,
            'can_filter_modul' => ($role === 'admin')
        ]);

    case 'list':
    default:
        $where = ["1=1"];
        $params = [];
        $types = "";

        // Filter Modul (terkunci sesuai whitelist peran)
        if ($filterModul !== '') {
            $where[] = "l.modul = ?";
            $params[] = $filterModul;
            $types .= "s";
        }

        // Pembatasan Manajer GA: hanya log yang dilakukan oleh dirinya sendiri atau bawahannya (semua tingkat)
        if ($isManajerGA) {
            $gaUserIds = getGAUserIdsHierarchy($user['id']);
            $ph = implode(',', array_fill(0, count($gaUserIds), '?'));
            $where[] = "l.user_id IN ($ph)";
            foreach ($gaUserIds as $guid) {
                $params[] = $guid;
                $types .= "i";
            }
        }

        // Filter Pengguna (user_id)
        $userIdFilter = !empty($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
        if ($userIdFilter > 0) {
            $where[] = "l.user_id = ?";
            $params[] = $userIdFilter;
            $types .= "i";
        }

        // Filter Rentang Tanggal Mulai
        $tglMulai = trim($_REQUEST['tgl_mulai'] ?? '');
        if ($tglMulai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglMulai)) {
            $where[] = "l.waktu >= ?";
            $params[] = $tglMulai . " 00:00:00";
            $types .= "s";
        }

        // Filter Rentang Tanggal Selesai
        $tglSelesai = trim($_REQUEST['tgl_selesai'] ?? '');
        if ($tglSelesai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglSelesai)) {
            $where[] = "l.waktu <= ?";
            $params[] = $tglSelesai . " 23:59:59";
            $types .= "s";
        }

        // Filter Pencarian kata kunci (Aktivitas, Nama Pengguna, atau IP)
        $q = trim($_REQUEST['q'] ?? '');
        if ($q !== '') {
            $where[] = "(l.aktivitas LIKE ? OR COALESCE(u.name, l.nama_user, '') LIKE ? OR l.ip_address LIKE ?)";
            $like = "%$q%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= "sss";
        }

        $whereSql = implode(" AND ", $where);

        // Hitung total data untuk pagination
        $countSql = "SELECT COUNT(*) AS total 
                     FROM log_aktivitas l 
                     LEFT JOIN users u ON u.id = l.user_id 
                     WHERE $whereSql";
        $stmtCount = $conn->prepare($countSql);
        if (!empty($params)) {
            $stmtCount->bind_param($types, ...$params);
        }
        $stmtCount->execute();
        $totalRows = (int)$stmtCount->get_result()->fetch_assoc()['total'];

        // Pagination parameter
        $isExportAll = isset($_REQUEST['all']) && ($_REQUEST['all'] === '1' || $_REQUEST['all'] === 'true');
        $page = max(1, (int)($_REQUEST['page'] ?? 1));
        $limit = $isExportAll ? 1000 : max(5, min(250, (int)($_REQUEST['limit'] ?? 25)));
        $totalPages = max(1, (int)ceil($totalRows / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        // Query data dengan LEFT JOIN ke users dan roles, fallback ke nama_user dan role_user jika user_id NULL
        $dataSql = "SELECT l.id, l.waktu, l.user_id, l.modul, l.aktivitas, l.ip_address,
                           COALESCE(u.name, l.nama_user, 'Pengguna Dihapus') AS nama_user,
                           COALESCE(r.nama_role, l.role_user, '-') AS role_user
                    FROM log_aktivitas l
                    LEFT JOIN users u ON u.id = l.user_id
                    LEFT JOIN roles r ON r.id = u.role_id
                    WHERE $whereSql
                    ORDER BY l.id DESC
                    LIMIT ? OFFSET ?";

        $paramsWithLimit = $params;
        $paramsWithLimit[] = $limit;
        $paramsWithLimit[] = $offset;
        $typesWithLimit = $types . "ii";

        $stmtData = $conn->prepare($dataSql);
        $stmtData->bind_param($typesWithLimit, ...$paramsWithLimit);
        $stmtData->execute();
        $items = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);

        ok([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
            ]
        ]);
}
