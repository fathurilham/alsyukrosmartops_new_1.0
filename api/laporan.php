<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = [])  { echo json_encode(['ok' => true,  'data' => $data]); exit(); }
function fail($msg, $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

if (!hasAccess($role, 'laporan', 'lihat')) {
    fail('Akses ditolak. Anda tidak memiliki izin melihat Laporan & Monitoring.', 403);
}

$modules = getRoleModules($role);

// ─── Helper: validasi & sanitasi filter ──────────────────────────────────────
function sanitizeDate(string $raw): string {
    $d = DateTime::createFromFormat('Y-m-d', trim($raw));
    return ($d && $d->format('Y-m-d') === trim($raw)) ? trim($raw) : '';
}

$tglMulai  = sanitizeDate($_GET['tgl_mulai']  ?? '');
$tglSampai = sanitizeDate($_GET['tgl_sampai'] ?? '');
$unitId    = isset($_GET['unit_id']) && ctype_digit(strval($_GET['unit_id'])) ? (int)$_GET['unit_id'] : 0;

// Balik tanggal jika terbalik
if ($tglMulai && $tglSampai && $tglMulai > $tglSampai) {
    [$tglMulai, $tglSampai] = [$tglSampai, $tglMulai];
}

// ─── Helper: Bangun klausa WHERE untuk periode ───────────────────────────────
/**
 * @param string $col   Nama kolom tanggal (harus literal, bukan dari input user)
 * @return string SQL fragment (WITHOUT leading AND)
 */
function periodClause(string $col, string $tglMulai, string $tglSampai): string {
    // col sudah hardcoded di kode, aman dipakai langsung
    if ($tglMulai && $tglSampai) {
        return "DATE($col) BETWEEN '$tglMulai' AND '$tglSampai'";
    } elseif ($tglMulai) {
        return "DATE($col) >= '$tglMulai'";
    } elseif ($tglSampai) {
        return "DATE($col) <= '$tglSampai'";
    }
    return '1=1';
}

// ─── Helper: jalankan query GROUP BY status dan kembalikan array ─────────────
function fetchGroupByStatus(mysqli $c, string $sql, ?string &$error = null): array {
    $r = $c->query($sql);
    if (!$r) { $error = $c->error; return []; }
    return $r->fetch_all(MYSQLI_ASSOC);
}

switch ($action) {

    // ─── Daftar unit/divisi untuk filter dropdown ────────────────────────────
    case 'list_units':
        $units = $conn->query("SELECT id, nama_unit FROM struktur_unit ORDER BY nama_unit ASC")->fetch_all(MYSQLI_ASSOC);
        ok(['units' => $units]);

    // ─── Rekap per modul dengan filter ───────────────────────────────────────
    case 'rekap':
        $data   = [];
        $hasPeriod = $tglMulai || $tglSampai;

        // ── A. INVENTARIS ────────────────────────────────────────────────────
        if (in_array('inventaris', $modules) && !in_array($role, ['manajer_markom', 'manajer_qms'], true)) {
            $where = [];
            $where[] = periodClause('p.created_at', $tglMulai, $tglSampai);
            if ($unitId > 0) {
                // Filter berdasarkan unit peminjam (users.unit_id)
                $where[] = "u.unit_id = $unitId";
                $sql = "SELECT p.status, COUNT(*) n
                        FROM peminjaman p
                        JOIN users u ON u.id = p.peminjam_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY p.status ORDER BY p.status";
            } else {
                $sql = "SELECT status, COUNT(*) n
                        FROM peminjaman p
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY status ORDER BY status";
            }
            $data['Inventaris'] = fetchGroupByStatus($conn, $sql);
        }

        // ── B. MAINTENANCE ───────────────────────────────────────────────────
        if (in_array('maintenance', $modules) && !in_array($role, ['manajer_markom', 'manajer_qms'], true)) {
            $where = [];
            $where[] = periodClause('pm.created_at', $tglMulai, $tglSampai);
            if ($unitId > 0) {
                $where[] = "u.unit_id = $unitId";
                $sql = "SELECT pm.status, COUNT(*) n
                        FROM pengajuan_maintenance pm
                        JOIN users u ON u.id = pm.pemohon_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY pm.status ORDER BY pm.status";
            } else {
                $sql = "SELECT status, COUNT(*) n
                        FROM pengajuan_maintenance pm
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY status ORDER BY status";
            }
            $data['Maintenance'] = fetchGroupByStatus($conn, $sql);
        }

        // ── C. EVENT ─────────────────────────────────────────────────────────
        if (in_array('event', $modules) && $role !== 'manajer_qms') {
            $where = [];
            $where[] = periodClause('e.created_at', $tglMulai, $tglSampai);
            if ($unitId > 0) {
                $where[] = "u.unit_id = $unitId";
                $sql = "SELECT e.status, COUNT(*) n
                        FROM event e
                        JOIN users u ON u.id = e.pemohon_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY e.status ORDER BY e.status";
            } else {
                $sql = "SELECT status, COUNT(*) n
                        FROM event e
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY status ORDER BY status";
            }
            $data['Event'] = fetchGroupByStatus($conn, $sql);
        }

        // ── D. KEGIATAN KARYAWAN ──────────────────────────────────────────────
        if (in_array('karyawan', $modules)
            && !isHrdAdministratifRole($user)
            && !in_array($role, ['manajer_markom', 'manajer_qms'], true)
        ) {
            $where = [];
            $where[] = "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')";
            $where[] = periodClause('kk.tanggal', $tglMulai, $tglSampai);
            if ($unitId > 0) {
                $where[] = "k.divisi_id = $unitId";
            }
            $sql = "SELECT kk.status, COUNT(*) n
                    FROM kegiatan_karyawan kk
                    JOIN karyawan k ON k.id = kk.karyawan_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY kk.status ORDER BY kk.status";
            $data['Kegiatan Karyawan'] = fetchGroupByStatus($conn, $sql);
        }

        // ── E. ARSIP ─────────────────────────────────────────────────────────
        if (in_array('arsip', $modules)
            && hasAccess($role, 'arsip', 'monitoring')
            && $role !== 'manajer_markom'
        ) {
            $where = [];
            $where[] = periodClause('a.tanggal_unggah', $tglMulai, $tglSampai);
            // Arsip tidak memiliki relasi unit langsung; filter unit via uploader
            if ($unitId > 0) {
                $where[] = "u.unit_id = $unitId";
                $sql = "SELECT a.tingkat_akses AS status, COUNT(*) n
                        FROM arsip a
                        JOIN users u ON u.id = a.uploader_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY a.tingkat_akses ORDER BY a.tingkat_akses";
            } else {
                $sql = "SELECT tingkat_akses AS status, COUNT(*) n
                        FROM arsip a
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY tingkat_akses ORDER BY tingkat_akses";
            }
            $data['Arsip'] = fetchGroupByStatus($conn, $sql);
        }

        ok([
            'rekap'      => $data,
            'tgl_mulai'  => $tglMulai,
            'tgl_sampai' => $tglSampai,
            'unit_id'    => $unitId,
            'has_period' => $hasPeriod,
        ]);

    default:
        fail('Aksi tidak dikenali.', 400);
}
