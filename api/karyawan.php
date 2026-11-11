<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }
function forbidden($msg) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

/**
 * Normalisasi teks nama/jabatan untuk deteksi duplikat:
 * lowercase, hapus tanda baca (termasuk variasi ' dan ’), rapikan spasi.
 */
function normalizeKaryawanText($text) {
    $s = mb_strtolower(trim((string)$text), 'UTF-8');
    // Hapus variasi apostrof terlebih dahulu agar kata seperti Rif'atul / Rif’atul menyatu konsisten
    $s = str_replace(["'", "’", "`", "‘", "\x92", "\x91"], "", $s);
    // Ganti tanda baca lainnya (titik, koma, kurung, garis miring, hubung) dengan spasi
    // sehingga "Ahmad Qosim,S.Pd.I" dan "Ahmad Qosim, S.Pd.I" menghasilkan token yang identik
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/**
 * Pemetaan nama_unit (struktur_unit) -> awalan NIP standar
 */
function getDivisiNipPrefix($conn, $divisiId) {
    if (!$divisiId) return null;
    $divisiId = (int)$divisiId;
    $stmt = $conn->prepare("SELECT nama_unit FROM struktur_unit WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $divisiId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return null;

    $map = [
        'General Affair (GA)'  => 'GA',
        'HRD'                  => 'HRD',
        'MARKOM / CRM'         => 'CRM',
        'QMS'                  => 'QM',
        'Finance & Accounting' => 'FA',
        'TK Al-Syukro'         => 'TK',
        'SD Al-Syukro'         => 'SD',
        'SMP Al-Syukro'        => 'SMP',
        'SMA Al-Syukro'        => 'SMA',
        'Pimpinan/Direksi'     => 'PIM',
    ];
    $namaUnit = trim($row['nama_unit']);
    return $map[$namaUnit] ?? null;
}

/**
 * Menghasilkan NIP berikutnya (AWALAN-NNNN) berdasarkan angka tertinggi yang sudah ada.
 * Gunakan $useLock = true di dalam transaksi saat INSERT untuk mencegah race condition.
 */
function generateNextNipByPrefix($conn, $prefix, $useLock = false) {
    if (!$prefix) return null;
    $like = $prefix . '-%';
    $sql = "SELECT nip FROM karyawan WHERE nip LIKE ?" . ($useLock ? " FOR UPDATE" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $maxNum = 0;
    $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
    while ($r = $res->fetch_assoc()) {
        if (preg_match($pattern, $r['nip'], $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
    }
    return sprintf('%s-%04d', $prefix, $maxNum + 1);
}

if (!hasAccess($role, 'karyawan', 'lihat') || !canAccessKaryawanModule($user)) {
    forbidden('Anda tidak memiliki hak akses ke modul Manajemen Karyawan.');
}

switch ($action) {

    /* ── 1. DATA KARYAWAN (KAK a - SEMUA KARYAWAN) ── */
    case 'list':
        if (in_array($role, ['cso', 'gardener'], true)) {
            forbidden('Akses ditolak. CSO dan Gardener hanya dapat mengakses Kegiatan Karyawan dan Penilaian Kinerja.');
        }
        $pimFilter = getPimpinanExclusionSql($user, 'k');
        if (canViewAllKaryawanAdministratif($user)) {
            $where = $pimFilter;
        } else {
            $accessibleIds = getAccessibleKaryawanIds($user);
            if (empty($accessibleIds)) {
                ok([]);
            }
            $idsStr = implode(',', array_map('intval', $accessibleIds));
            $where = "k.id IN ($idsStr) AND ($pimFilter)";
        }

        $sql = "SELECT k.*, su.nama_unit, a.nama AS nama_atasan, u.username, u.name AS nama_user
                FROM karyawan k
                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                LEFT JOIN karyawan a ON a.id = k.atasan_id
                LEFT JOIN users u ON u.id = k.user_id
                WHERE $where
                ORDER BY k.nip ASC, k.nama ASC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'dropdown_data':
        $canPimpinan = canAccessPimpinanUnit($user);
        $pimFilterK = getPimpinanExclusionSql($user, 'k');

        if ($canPimpinan) {
            $units = $conn->query("SELECT id, nama_unit FROM struktur_unit ORDER BY nama_unit ASC")->fetch_all(MYSQLI_ASSOC);
        } else {
            $units = $conn->query("SELECT id, nama_unit FROM struktur_unit WHERE nama_unit != 'Pimpinan/Direksi' ORDER BY nama_unit ASC")->fetch_all(MYSQLI_ASSOC);
        }

        if (canViewAllKaryawanAdministratif($user)) {
            $atasanList = $conn->query("SELECT k.id, k.nip, k.nama, k.jabatan FROM karyawan k WHERE k.status='aktif' AND ($pimFilterK) ORDER BY k.nama ASC")->fetch_all(MYSQLI_ASSOC);
        } else {
            $accessibleIds = getAccessibleKaryawanIds($user);
            $idsStr = !empty($accessibleIds) ? implode(',', array_map('intval', $accessibleIds)) : '0';
            $atasanList = $conn->query("SELECT k.id, k.nip, k.nama, k.jabatan FROM karyawan k WHERE k.status='aktif' AND k.id IN ($idsStr) AND ($pimFilterK) ORDER BY k.nama ASC")->fetch_all(MYSQLI_ASSOC);
        }
        $users = $conn->query("SELECT id, username, name FROM users WHERE status='aktif' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
        $jabatanList = $conn->query("SELECT DISTINCT k.jabatan FROM karyawan k WHERE k.jabatan IS NOT NULL AND TRIM(k.jabatan) != '' AND ($pimFilterK) ORDER BY k.jabatan ASC")->fetch_all(MYSQLI_ASSOC);

        // Karyawan pelaksana yang dapat diberikan tugas (hanya CSO atau Gardener; HRD tidak berwenang)
        $isHRD = isHrdAdministratifRole($user);
        $pelaksanaSqlFilter = "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener') AND ($pimFilterK)";
        if ($isHRD) {
            $assignableKaryawan = [];
        } elseif (hasAccess($role, 'karyawan', 'monitoring')) {
            $assignableKaryawan = $conn->query("SELECT k.id, k.nip, k.nama, k.jabatan FROM karyawan k WHERE k.status='aktif' AND $pelaksanaSqlFilter ORDER BY k.nama ASC")->fetch_all(MYSQLI_ASSOC);
        } else {
            $myKar = getKaryawanByUserId($user['id']);
            if ($myKar) {
                $myKid = (int)$myKar['id'];
                $assignableKaryawan = $conn->query("SELECT k.id, k.nip, k.nama, k.jabatan FROM karyawan k WHERE k.status='aktif' AND k.atasan_id = $myKid AND $pelaksanaSqlFilter ORDER BY k.nama ASC")->fetch_all(MYSQLI_ASSOC);
            } else {
                $assignableKaryawan = [];
            }
        }

        // Daftar tugas selesai yang belum dinilai: HANYA untuk pemegang izin 'penilaian' (SPV Sapras) dan pelaksana CSO/Gardener (khusus tugas non-rutin)
        $hasPenilaianAksi = hasAccess($role, 'karyawan', 'penilaian');
        if ($isHRD || !$hasPenilaianAksi) {
            $tugasBelumDinilai = [];
        } else {
            $whereTugas = "kk.status = 'selesai' AND kk.jenis = 'non-rutin' AND pk.id IS NULL AND $pelaksanaSqlFilter";
            $sqlTugasBelum = "SELECT kk.id, kk.deskripsi, kk.tanggal, kk.tenggat, kk.jam, kk.hasil_laporan, kk.foto_laporan, kk.selesai_pada,
                                     k.id AS karyawan_id, k.nama AS karyawan_nama, k.jabatan, k.atasan_id
                              FROM kegiatan_karyawan kk
                              JOIN karyawan k ON k.id = kk.karyawan_id
                              LEFT JOIN penilaian_kinerja pk ON pk.kegiatan_id = kk.id
                              WHERE $whereTugas
                              ORDER BY kk.tanggal DESC, kk.id DESC";
            $tugasBelumDinilai = $conn->query($sqlTugasBelum)->fetch_all(MYSQLI_ASSOC);
        }

        $nextNipByDivisi = [];
        foreach ($units as $u) {
            $pfx = getDivisiNipPrefix($conn, (int)$u['id']);
            if ($pfx) {
                $nextNipByDivisi[$u['id']] = generateNextNipByPrefix($conn, $pfx, false);
            }
        }

        ok([
            'units' => $units,
            'next_nip_by_divisi' => $nextNipByDivisi,
            'atasan' => $atasanList,
            'users' => $users,
            'jabatan_list' => array_column($jabatanList, 'jabatan'),
            'karyawan_tugas' => $assignableKaryawan,
            'karyawan_lapangan' => $assignableKaryawan, // Kompatibilitas UI lama
            'tugas_belum_dinilai' => $tugasBelumDinilai
        ]);

    case 'next_nip':
        $divisi_id = !empty($_GET['divisi_id']) ? (int)$_GET['divisi_id'] : (!empty($_POST['divisi_id']) ? (int)$_POST['divisi_id'] : 0);
        $pimId = getPimpinanUnitId();
        if ($pimId > 0 && $divisi_id === $pimId && !canAccessPimpinanUnit($user)) {
            forbidden('Akses ditolak. Hanya Manajer HRD yang dapat mengakses unit Pimpinan/Direksi.');
        }
        $pfx = getDivisiNipPrefix($conn, $divisi_id);
        $nextNip = $pfx ? generateNextNipByPrefix($conn, $pfx, false) : '';
        ok(['nip' => $nextNip, 'prefix' => $pfx]);

    case 'tambah':
        if (!hasAccess($role, 'karyawan', 'kelola_master') && !hasAccess($role, 'karyawan', 'tambah')) {
            forbidden('Akses ditolak. Anda tidak memiliki izin untuk menambahkan data karyawan.');
        }

        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $divisi_id = !empty($_POST['divisi_id']) ? (int)$_POST['divisi_id'] : null;
        $atasan_id = !empty($_POST['atasan_id']) ? (int)$_POST['atasan_id'] : null;
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $tanggal_masuk = !empty($_POST['tanggal_masuk']) ? trim($_POST['tanggal_masuk']) : null;
        $kontak = !empty($_POST['kontak']) ? trim($_POST['kontak']) : null;
        $confirmSameName = !empty($_POST['confirm_same_name']) && $_POST['confirm_same_name'] === '1';

        if (!$nama || !$jabatan) {
            fail('Nama dan Jabatan wajib diisi.');
        }

        // Validasi akses unit Pimpinan/Direksi
        if (!canAccessPimpinanUnit($user)) {
            if (isKaryawanPimpinanRow(['divisi_id' => $divisi_id, 'jabatan' => $jabatan]) || stripos($nip, 'PIM-') === 0) {
                forbidden('Akses ditolak. Hanya Manajer HRD yang berwenang mengelola data unit Pimpinan/Direksi.');
            }
        }

        // Validasi izin kelola struktur atasan
        if ($atasan_id !== null && !hasAccess($role, 'karyawan', 'kelola_struktur')) {
            forbidden('Akses ditolak. Anda tidak memiliki izin untuk mengatur struktur atasan langsung karyawan.');
        }
        if ($atasan_id !== null) {
            $atasanTarget = $conn->query("SELECT id, divisi_id, jabatan, user_id FROM karyawan WHERE id = $atasan_id")->fetch_assoc();
            if (!$atasanTarget || !canUserAccessKaryawanRow($user, $atasanTarget)) {
                forbidden('Akses ditolak pada pemilihan atasan langsung.');
            }
        }

        $conn->begin_transaction();
        try {
            // 1. Cek duplikat nama (ternormalisasi) + jabatan (tolak) atau hanya nama sama (peringatan)
            $normNama = normalizeKaryawanText($nama);
            $normJabatan = normalizeKaryawanText($jabatan);
            $allKar = $conn->query("SELECT id, nip, nama, jabatan FROM karyawan FOR UPDATE")->fetch_all(MYSQLI_ASSOC);

            $sameNameList = [];
            foreach ($allKar as $row) {
                if (normalizeKaryawanText($row['nama']) === $normNama) {
                    if (normalizeKaryawanText($row['jabatan']) === $normJabatan) {
                        $conn->rollback();
                        fail("Ditolak: Karyawan dengan nama '{$row['nama']}' dan jabatan '{$row['jabatan']}' sudah terdaftar (NIP: {$row['nip']}).");
                    }
                    $sameNameList[] = "{$row['nama']} ({$row['nip']} - {$row['jabatan']})";
                }
            }

            if (!empty($sameNameList) && !$confirmSameName) {
                $conn->rollback();
                echo json_encode([
                    'ok' => false,
                    'warning_same_name' => true,
                    'msg' => "Peringatan: Nama '$nama' sudah terdaftar dengan jabatan berbeda: " . implode(', ', $sameNameList) . ". Apakah Anda yakin ingin tetap menambahkan karyawan ini?"
                ]);
                exit();
            }

            // 2. Buat NIP otomatis dari awalan divisi + nomor berikutnya (dengan lock FOR UPDATE di dalam transaksi)
            $prefix = getDivisiNipPrefix($conn, $divisi_id);
            if ($prefix) {
                $nip = generateNextNipByPrefix($conn, $prefix, true);
            } elseif (!$nip) {
                $conn->rollback();
                fail('Pilih Unit / Divisi untuk membuat NIP otomatis, atau isi NIP secara manual.');
            }

            // 3. Cek keunikan NIP
            $cekNip = $conn->prepare("SELECT id FROM karyawan WHERE nip = ? FOR UPDATE");
            $cekNip->bind_param("s", $nip);
            $cekNip->execute();
            if ($cekNip->get_result()->num_rows > 0) {
                $conn->rollback();
                fail("NIP '$nip' sudah terdaftar dalam sistem.");
            }

            $stmt = $conn->prepare("INSERT INTO karyawan (nip, nama, jabatan, divisi_id, atasan_id, user_id, status, tanggal_masuk, kontak) VALUES (?,?,?,?,?,?, 'aktif', ?, ?)");
            $stmt->bind_param("sssiiiss", $nip, $nama, $jabatan, $divisi_id, $atasan_id, $user_id, $tanggal_masuk, $kontak);
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $conn->rollback();
                fail("Gagal menyimpan data karyawan: $err");
            }
            $insertId = $stmt->insert_id;
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            fail('Terjadi kesalahan saat menyimpan karyawan: ' . $e->getMessage());
        }

        $unitName = '-';
        if ($divisi_id) {
            $uRow = $conn->query("SELECT nama_unit FROM struktur_unit WHERE id = $divisi_id")->fetch_assoc();
            if ($uRow) $unitName = $uRow['nama_unit'];
        }
        $atasanNama = '-';
        if ($atasan_id) {
            $aRow = $conn->query("SELECT nama FROM karyawan WHERE id = $atasan_id")->fetch_assoc();
            if ($aRow) $atasanNama = $aRow['nama'];
        }

        $detailTambah = "nip: $nip, jabatan: $jabatan, unit: $unitName, status: aktif";
        if ($atasanNama !== '-') $detailTambah .= ", atasan: $atasanNama";
        logAktivitas($user['id'], 'karyawan', "Menambahkan karyawan baru: $nama ($detailTambah)");
        $newRow = $conn->query("SELECT k.*, su.nama_unit, a.nama AS nama_atasan, u.username, u.name AS nama_user
                                FROM karyawan k
                                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                                LEFT JOIN karyawan a ON a.id = k.atasan_id
                                LEFT JOIN users u ON u.id = k.user_id
                                WHERE k.id = " . (int)$insertId)->fetch_assoc();
        ok(['id' => $insertId, 'nip' => $nip, 'row' => $newRow]);

    case 'edit':
        if (!hasAccess($role, 'karyawan', 'kelola_master')) {
            forbidden('Akses ditolak. Anda tidak memiliki izin untuk mengubah data administratif karyawan.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            fail('ID karyawan tidak valid.');
        }

        $existing = $conn->query("SELECT k.*, su.nama_unit, a.nama AS nama_atasan FROM karyawan k LEFT JOIN struktur_unit su ON su.id = k.divisi_id LEFT JOIN karyawan a ON a.id = k.atasan_id WHERE k.id = $id")->fetch_assoc();
        if (!$existing) {
            fail('Data karyawan tidak ditemukan.');
        }

        // Proteksi Unit Pimpinan/Direksi pada data lama
        if (!canUserAccessKaryawanRow($user, $existing)) {
            forbidden('Akses ditolak. Hanya Manajer HRD yang berwenang mengakses atau mengubah data Pimpinan/Direksi.');
        }

        $canKelolaStruktur = hasAccess($role, 'karyawan', 'kelola_struktur');
        $canUbahStatus = hasAccess($role, 'karyawan', 'ubah_status');

        $existingDivisi = !empty($existing['divisi_id']) ? (int)$existing['divisi_id'] : null;
        $existingAtasan = !empty($existing['atasan_id']) ? (int)$existing['atasan_id'] : null;
        $existingStatus = $existing['status'] ?? 'aktif';

        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $user_id = array_key_exists('user_id', $_POST) ? (!empty($_POST['user_id']) ? (int)$_POST['user_id'] : null) : (!empty($existing['user_id']) ? (int)$existing['user_id'] : null);
        $tanggal_masuk = !empty($_POST['tanggal_masuk']) ? trim($_POST['tanggal_masuk']) : null;
        $kontak = !empty($_POST['kontak']) ? trim($_POST['kontak']) : null;
        $confirmSameName = !empty($_POST['confirm_same_name']) && $_POST['confirm_same_name'] === '1';

        if (!$nip || !$nama || !$jabatan) {
            fail('Data karyawan tidak lengkap.');
        }

        $postedDivisi = array_key_exists('divisi_id', $_POST)
            ? (!empty($_POST['divisi_id']) ? (int)$_POST['divisi_id'] : null)
            : $existingDivisi;

        // Proteksi Unit Pimpinan/Direksi pada data baru (divisi / jabatan / awalan NIP PIM-)
        if (!canAccessPimpinanUnit($user)) {
            if (isKaryawanPimpinanRow(['divisi_id' => $postedDivisi, 'jabatan' => $jabatan]) || stripos($nip, 'PIM-') === 0) {
                forbidden('Akses ditolak. Hanya Manajer HRD yang berwenang memindahkan atau mengatur data ke unit Pimpinan/Direksi.');
            }
        }

        // Cek duplikat nama (ternormalisasi) + jabatan pada karyawan lain
        $normNama = normalizeKaryawanText($nama);
        $normJabatan = normalizeKaryawanText($jabatan);
        $otherKar = $conn->query("SELECT id, nip, nama, jabatan FROM karyawan WHERE id != $id")->fetch_all(MYSQLI_ASSOC);
        $sameNameList = [];
        foreach ($otherKar as $row) {
            if (normalizeKaryawanText($row['nama']) === $normNama) {
                if (normalizeKaryawanText($row['jabatan']) === $normJabatan) {
                    fail("Ditolak: Karyawan dengan nama '{$row['nama']}' dan jabatan '{$row['jabatan']}' sudah terdaftar (NIP: {$row['nip']}).");
                }
                $sameNameList[] = "{$row['nama']} ({$row['nip']} - {$row['jabatan']})";
            }
        }
        // Beri peringatan jika nama (ternormalisasi) sama dengan baris lain (dan nama/jabatan diubah)
        $existingNormNama = normalizeKaryawanText($existing['nama']);
        if (!empty($sameNameList) && !$confirmSameName && $normNama !== $existingNormNama) {
            echo json_encode([
                'ok' => false,
                'warning_same_name' => true,
                'msg' => "Peringatan: Nama '$nama' sudah terdaftar dengan jabatan berbeda: " . implode(', ', $sameNameList) . ". Apakah Anda yakin ingin tetap menyimpan perubahan ini?"
            ]);
            exit();
        }

        // Cek duplikasi NIP pada karyawan lain
        $cekNip = $conn->prepare("SELECT id FROM karyawan WHERE nip = ? AND id != ?");
        $cekNip->bind_param("si", $nip, $id);
        $cekNip->execute();
        if ($cekNip->get_result()->num_rows > 0) {
            fail("NIP '$nip' sudah digunakan oleh karyawan lain.");
        }

        // Divisi administratif boleh diubah oleh pengelola master (selama bukan unit Pimpinan bagi non-manajer_hrd)
        $divisi_id = $postedDivisi;

        // Validasi izin kelola struktur atasan langsung (atasan_id)
        $postedAtasan = array_key_exists('atasan_id', $_POST)
            ? (!empty($_POST['atasan_id']) ? (int)$_POST['atasan_id'] : null)
            : $existingAtasan;
        if (!$canKelolaStruktur) {
            if (array_key_exists('atasan_id', $_POST) && $postedAtasan !== $existingAtasan) {
                forbidden('Akses ditolak. Anda tidak memiliki izin untuk mengubah struktur atasan langsung karyawan.');
            }
            $atasan_id = $existingAtasan;
        } else {
            $atasan_id = $postedAtasan;
        }

        if ($atasan_id !== null && $atasan_id !== $existingAtasan) {
            $atasanTarget = $conn->query("SELECT id, divisi_id, jabatan, user_id FROM karyawan WHERE id = $atasan_id")->fetch_assoc();
            if (!$atasanTarget || !canUserAccessKaryawanRow($user, $atasanTarget)) {
                forbidden('Akses ditolak pada pemilihan atasan langsung.');
            }
        }

        // Validasi izin ubah status aktif/nonaktif
        $postedStatus = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : $existingStatus;
        if (!$canUbahStatus) {
            if (isset($_POST['status']) && $postedStatus !== $existingStatus) {
                forbidden('Akses ditolak. Anda tidak memiliki izin untuk mengubah status aktif/nonaktif karyawan.');
            }
            $status = $existingStatus;
        } else {
            $status = $postedStatus;
        }

        // Validasi atasan: tidak boleh diri sendiri dan tidak boleh membentuk siklus
        if ($atasan_id) {
            if ($atasan_id === $id) {
                fail('Atasan langsung tidak boleh diri sendiri.');
            }
            if (wouldCreateKaryawanCycle($id, $atasan_id)) {
                fail('Atasan tidak valid karena akan membentuk siklus hierarki (atasan termasuk bawahan dari karyawan ini).');
            }
        }

        // Catat detail perubahan sebelum dan sesudah
        $perubahan = [];
        if ($existing['nama'] !== $nama) {
            $perubahan[] = "nama: '{$existing['nama']}' -> '$nama'";
        }
        if ($existing['nip'] !== $nip) {
            $perubahan[] = "nip: '{$existing['nip']}' -> '$nip'";
        }
        if ($existing['jabatan'] !== $jabatan) {
            $perubahan[] = "jabatan: {$existing['jabatan']} -> $jabatan";
        }
        if ((int)($existing['divisi_id'] ?? 0) !== (int)($divisi_id ?? 0)) {
            $oldUnit = $existing['nama_unit'] ?: '-';
            $newUnit = '-';
            if ($divisi_id) {
                $uRow = $conn->query("SELECT nama_unit FROM struktur_unit WHERE id = $divisi_id")->fetch_assoc();
                if ($uRow) $newUnit = $uRow['nama_unit'];
            }
            $perubahan[] = "unit: $oldUnit -> $newUnit";
        }
        if ((int)($existing['atasan_id'] ?? 0) !== (int)($atasan_id ?? 0)) {
            $oldAtasan = $existing['nama_atasan'] ?: '-';
            $newAtasan = '-';
            if ($atasan_id) {
                $aRow = $conn->query("SELECT nama FROM karyawan WHERE id = $atasan_id")->fetch_assoc();
                if ($aRow) $newAtasan = $aRow['nama'];
            }
            $perubahan[] = "atasan: $oldAtasan -> $newAtasan";
        }
        if (($existing['status'] ?? 'aktif') !== $status) {
            $perubahan[] = "status: {$existing['status']} -> $status";
        }
        $existingTgl = $existing['tanggal_masuk'] ?: '';
        $newTgl = $tanggal_masuk ?: '';
        if ($existingTgl !== $newTgl) {
            $oldTgl = $existingTgl ?: '-';
            $showNewTgl = $newTgl ?: '-';
            $perubahan[] = "tanggal_masuk: $oldTgl -> $showNewTgl";
        }
        $existingKontak = $existing['kontak'] ?: '';
        $newKontak = $kontak ?: '';
        if ($existingKontak !== $newKontak) {
            $oldK = $existingKontak ?: '-';
            $showNewK = $newKontak ?: '-';
            $perubahan[] = "kontak: $oldK -> $showNewK";
        }
        if ((int)($existing['user_id'] ?? 0) !== (int)($user_id ?? 0)) {
            $oldUid = $existing['user_id'] ? $existing['user_id'] : '-';
            $newUid = $user_id ? $user_id : '-';
            $perubahan[] = "user_id: $oldUid -> $newUid";
        }

        $stmt = $conn->prepare("UPDATE karyawan SET nip=?, nama=?, jabatan=?, divisi_id=?, atasan_id=?, user_id=?, status=?, tanggal_masuk=?, kontak=? WHERE id=?");
        $stmt->bind_param("sssiiisssi", $nip, $nama, $jabatan, $divisi_id, $atasan_id, $user_id, $status, $tanggal_masuk, $kontak, $id);
        $stmt->execute();

        // Jika terhubung dengan akun user, sinkronkan nama dan status ke tabel users
        if ($user_id) {
            $stmtU = $conn->prepare("UPDATE users SET name=?, status=? WHERE id=?");
            $stmtU->bind_param("ssi", $nama, $status, $user_id);
            $stmtU->execute();
            if ($status === 'nonaktif') {
                removeUserSession($user_id);
            }
        }

        $detailPerubahan = !empty($perubahan) ? ": " . implode(', ', $perubahan) : " (tanpa perubahan field)";
        logAktivitas($user['id'], 'karyawan', "Memperbarui data karyawan $nama ($nip)$detailPerubahan");
        $updatedRow = $conn->query("SELECT k.*, su.nama_unit, a.nama AS nama_atasan, u.username, u.name AS nama_user
                                    FROM karyawan k
                                    LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                                    LEFT JOIN karyawan a ON a.id = k.atasan_id
                                    LEFT JOIN users u ON u.id = k.user_id
                                    WHERE k.id = " . (int)$id)->fetch_assoc();
        ok(['id' => $id, 'nip' => $nip, 'row' => $updatedRow]);

    case 'toggle_status':
        if (!hasAccess($role, 'karyawan', 'ubah_status')) {
            forbidden('Akses ditolak. Anda tidak memiliki izin untuk mengubah status aktif/nonaktif karyawan.');
        }
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID karyawan tidak valid.');

        $current = $conn->query("SELECT k.id, k.nama, k.nip, k.jabatan, k.divisi_id, su.nama_unit, k.status, k.user_id FROM karyawan k LEFT JOIN struktur_unit su ON su.id = k.divisi_id WHERE k.id = $id")->fetch_assoc();
        if (!$current) fail('Data karyawan tidak ditemukan.');
        if (!canUserAccessKaryawanRow($user, $current)) {
            forbidden('Akses ditolak. Hanya Manajer HRD yang berwenang mengubah status data Pimpinan/Direksi.');
        }
        $oldStatus = $current['status'] ?? 'aktif';

        $status = ($_POST['status'] ?? '') === 'aktif' ? 'aktif' : 'nonaktif';
        $stmt = $conn->prepare("UPDATE karyawan SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        // Sinkronkan ke akun users jika ada
        $conn->query("UPDATE users SET status='" . $conn->real_escape_string($status) . "' WHERE id = (SELECT user_id FROM karyawan WHERE id=$id AND user_id IS NOT NULL)");
        if ($status === 'nonaktif' && !empty($current['user_id'])) {
            removeUserSession((int)$current['user_id']);
        }

        logAktivitas($user['id'], 'karyawan', "Mengubah status karyawan {$current['nama']} ({$current['nip']}): status: $oldStatus -> $status");
        $toggledRow = $conn->query("SELECT k.*, su.nama_unit, a.nama AS nama_atasan, u.username, u.name AS nama_user
                                    FROM karyawan k
                                    LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                                    LEFT JOIN karyawan a ON a.id = k.atasan_id
                                    LEFT JOIN users u ON u.id = k.user_id
                                    WHERE k.id = " . (int)$id)->fetch_assoc();
        ok(['id' => $id, 'status' => $status, 'row' => $toggledRow]);

    case 'hapus':
        if (!hasAccess($role, 'karyawan', 'hapus')) {
            forbidden('Akses ditolak. Anda tidak memiliki izin untuk menghapus data karyawan.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            fail('ID karyawan tidak valid.');
        }

        $existing = $conn->query("SELECT k.id, k.nama, k.nip, k.jabatan, k.divisi_id, su.nama_unit, k.user_id FROM karyawan k LEFT JOIN struktur_unit su ON su.id = k.divisi_id WHERE k.id = $id")->fetch_assoc();
        if (!$existing) {
            fail('Data karyawan tidak ditemukan.');
        }
        if (!canUserAccessKaryawanRow($user, $existing)) {
            forbidden('Akses ditolak. Hanya Manajer HRD yang berwenang menghapus data Pimpinan/Direksi.');
        }

        $nama = $existing['nama'];
        $nip = $existing['nip'];

        // Lepas relasi bawahan terlebih dahulu agar tidak terhalang foreign key constraint
        $stmtAtasan = $conn->prepare("UPDATE karyawan SET atasan_id = NULL WHERE atasan_id = ?");
        $stmtAtasan->bind_param("i", $id);
        $stmtAtasan->execute();

        // Hapus data karyawan
        $stmtDel = $conn->prepare("DELETE FROM karyawan WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();

        logAktivitas($user['id'], 'karyawan', "Menghapus data karyawan: $nama ($nip)");
        ok();

    /* ── 2. KEGIATAN KARYAWAN (RUTIN MANDIRI & NON-RUTIN) ── */
    case 'list_kegiatan':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak memiliki akses ke daftar tugas dan kegiatan operasional.');
        }

        $pimFilter = getPimpinanExclusionSql($user, 'k');
        $pelaksanaFilter = "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener') AND ($pimFilter)";

        $where = "";
        if (canViewAllKaryawan($user) || hasAccess($role, 'karyawan', 'penilaian')) {
            $where = $pelaksanaFilter;
        } else {
            $myKar = getKaryawanByUserId($user['id']);
            if ($myKar) {
                if (in_array($role, ['cso', 'gardener'], true)) {
                    $where = "kk.karyawan_id = " . (int)$myKar['id'] . " AND $pelaksanaFilter";
                } else {
                    $subIds = getSubordinateKaryawanIds($myKar['id'], true);
                    $idsStr = !empty($subIds) ? implode(',', array_map('intval', $subIds)) : '0';
                    $where = "(kk.karyawan_id IN ($idsStr) OR kk.assigned_by = {$user['id']}) AND $pelaksanaFilter";
                }
            } else {
                $where = "kk.assigned_by = {$user['id']} AND $pelaksanaFilter";
            }
        }

        if (!empty($_GET['tanggal'])) {
            $tglEsc = $conn->real_escape_string($_GET['tanggal']);
            $where .= " AND kk.tanggal = '$tglEsc'";
        }
        if (!empty($_GET['karyawan_id'])) {
            $kid = (int)$_GET['karyawan_id'];
            $where .= " AND kk.karyawan_id = $kid";
        }
        if (!empty($_GET['jenis'])) {
            $jEsc = $conn->real_escape_string($_GET['jenis']);
            $where .= " AND kk.jenis = '$jEsc'";
        }

        $sql = "SELECT kk.*, k.nama AS karyawan_nama, k.nip AS karyawan_nip, k.jabatan, k.atasan_id, k.user_id AS karyawan_user_id,
                       u.name AS assigner_nama,
                       pk.id AS penilaian_id, pk.nilai AS skor_nilai, pk.catatan AS catatan_penilaian,
                       pk.periode AS periode_penilaian, u_penilai.name AS penilai_nama,
                       ph.id AS penilaian_harian_id, ph.nilai AS nilai_harian, ph.catatan AS catatan_harian,
                       ph_user.name AS penilai_harian_nama
                FROM kegiatan_karyawan kk
                JOIN karyawan k ON k.id = kk.karyawan_id
                LEFT JOIN users u ON u.id = kk.assigned_by
                LEFT JOIN penilaian_kinerja pk ON pk.kegiatan_id = kk.id
                LEFT JOIN users u_penilai ON u_penilai.id = pk.penilai_id
                LEFT JOIN penilaian_harian ph ON ph.karyawan_id = kk.karyawan_id AND ph.tanggal = kk.tanggal
                LEFT JOIN users ph_user ON ph_user.id = ph.dinilai_oleh
                WHERE $where
                ORDER BY kk.tanggal DESC, kk.id DESC";
        $tasks = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

        $taskIds = array_column($tasks, 'id');
        $fotosByKegiatan = [];
        if (!empty($taskIds)) {
            $idsStr = implode(',', array_map('intval', $taskIds));
            $fRes = $conn->query("SELECT id, kegiatan_id, path FROM kegiatan_foto WHERE kegiatan_id IN ($idsStr) ORDER BY id ASC");
            while ($fRow = $fRes->fetch_assoc()) {
                $fotosByKegiatan[$fRow['kegiatan_id']][] = [
                    'id'   => (int)$fRow['id'],
                    'path' => $fRow['path'],
                    'url'  => 'download.php?view=karyawan_foto&id=' . (int)$fRow['id']
                ];
            }
        }

        foreach ($tasks as &$t) {
            $kId = (int)$t['id'];
            $t['fotos'] = $fotosByKegiatan[$kId] ?? [];
            if (empty($t['fotos']) && !empty($t['foto_laporan'])) {
                $t['fotos'][] = [
                    'id'   => 0,
                    'path' => $t['foto_laporan'],
                    'url'  => 'download.php?view=karyawan_foto&file=' . basename($t['foto_laporan'])
                ];
            }
            $t['is_locked'] = !empty($t['penilaian_harian_id']);
        }

        ok($tasks);

    case 'tambah_kegiatan':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak berwenang memberikan penugasan kegiatan.');
        }

        $myKar = getKaryawanByUserId($user['id']);
        $isPelaksana = in_array($role, ['cso', 'gardener'], true) || hasAccess($role, 'karyawan', 'tugas_rutin_sendiri');

        if ($isPelaksana) {
            if (!$myKar) {
                fail('Data profil karyawan Anda tidak ditemukan.');
            }
            $karyawan_id = (int)$myKar['id'];
            $jenis = 'rutin'; // CSO & Gardener hanya buat tugas rutin harian
            if (!empty($_POST['tanggal']) && trim($_POST['tanggal']) !== date('Y-m-d')) {
                forbidden('Tugas rutin harian hanya dapat dibuat untuk tanggal hari ini.');
            }
            $tanggal = date('Y-m-d'); // Tanggal wajib hari ini
            $tenggat = !empty($_POST['tenggat']) ? trim($_POST['tenggat']) : null;
            $jam = !empty($_POST['jam']) ? trim($_POST['jam']) : null;
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['ditugaskan', 'dikerjakan', 'selesai']) ? $_POST['status'] : 'dikerjakan';

            if (!$deskripsi) {
                fail('Deskripsi tugas rutin harian wajib diisi.');
            }

            // Validasi jam mulai 04:30 WIB & lock hari ini
            $chk = canInputTugasRutinSekarang($conn, $karyawan_id, $tanggal);
            if (!$chk['allowed']) {
                fail($chk['reason']);
            }

            $stmt = $conn->prepare("INSERT INTO kegiatan_karyawan (karyawan_id, assigned_by, jenis, deskripsi, tanggal, tenggat, jam, status, selesai_pada) VALUES (?,?,?,?,?,?,?,?,?)");
            $selesaiPada = ($status === 'selesai') ? date('Y-m-d H:i:s') : null;
            $stmt->bind_param("iisssssss", $karyawan_id, $user['id'], $jenis, $deskripsi, $tanggal, $tenggat, $jam, $status, $selesaiPada);
            $stmt->execute();
            $newId = $stmt->insert_id;

            // Penanganan upload bukti foto
            $fotoCount = 0;
            if (!empty($_FILES['fotos']) || !empty($_FILES['foto_laporan'])) {
                require_once '../config/upload.php';
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $dir = __DIR__ . '/../uploads/karyawan/';

                $filesToProcess = [];
                if (!empty($_FILES['fotos']['name']) && is_array($_FILES['fotos']['name'])) {
                    foreach ($_FILES['fotos']['name'] as $k => $name) {
                        if (!empty($_FILES['fotos']['tmp_name'][$k]) && is_uploaded_file($_FILES['fotos']['tmp_name'][$k])) {
                            $filesToProcess[] = [
                                'name' => $name,
                                'type' => $_FILES['fotos']['type'][$k] ?? '',
                                'tmp_name' => $_FILES['fotos']['tmp_name'][$k],
                                'error' => $_FILES['fotos']['error'][$k] ?? 0,
                                'size' => $_FILES['fotos']['size'][$k] ?? 0
                            ];
                        }
                    }
                } elseif (!empty($_FILES['fotos']['tmp_name']) && is_uploaded_file($_FILES['fotos']['tmp_name'])) {
                    $filesToProcess[] = $_FILES['fotos'];
                }
                if (!empty($_FILES['foto_laporan']['tmp_name']) && is_uploaded_file($_FILES['foto_laporan']['tmp_name'])) {
                    $filesToProcess[] = $_FILES['foto_laporan'];
                }

                if (count($filesToProcess) > 3) {
                    $conn->query("DELETE FROM kegiatan_karyawan WHERE id = $newId");
                    fail('Maksimal 3 foto dokumentasi per tugas.');
                }

                $stmtFoto = $conn->prepare("INSERT INTO kegiatan_foto (kegiatan_id, path) VALUES (?, ?)");
                foreach ($filesToProcess as $fObj) {
                    try {
                        $absPath = secureUpload($fObj, $allowed, $dir, 5242880);
                    } catch (Exception $e) {
                        $conn->query("DELETE FROM kegiatan_karyawan WHERE id = $newId");
                        fail($e->getMessage());
                    }
                    $relP = 'uploads/karyawan/' . basename($absPath);
                    $stmtFoto->bind_param("is", $newId, $relP);
                    $stmtFoto->execute();
                    $fotoCount++;
                    if ($fotoCount === 1) {
                        $conn->query("UPDATE kegiatan_karyawan SET foto_laporan = '" . $conn->real_escape_string($relP) . "' WHERE id = $newId");
                    }
                }
            }

            if ($status === 'selesai' && $fotoCount < 1) {
                $conn->query("DELETE FROM kegiatan_karyawan WHERE id = $newId");
                fail('Menandai tugas rutin selesai wajib melampirkan minimal 1 foto bukti.');
            }

            logAktivitas($user['id'], 'karyawan_kinerja', "Menambahkan tugas rutin harian: " . mb_substr($deskripsi, 0, 45));
            ok(['id' => $newId]);
        }

        // Jalur Atasan (Team Leader, SPV Sapras, Admin)
        if (!hasAccess($role, 'karyawan', 'kelola') && !hasAccess($role, 'karyawan', 'monitoring')) {
            forbidden('Akses ditolak.');
        }

        $jenis = trim($_POST['jenis'] ?? 'non-rutin');
        if ($jenis === 'rutin') {
            forbidden('Atasan hanya berwenang memberikan tugas non-rutin. Tugas rutin harian dibuat sendiri oleh karyawan pelaksana.');
        }
        $jenis = 'non-rutin';

        $karyawan_id = (int)($_POST['karyawan_id'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = !empty($_POST['tanggal']) ? trim($_POST['tanggal']) : date('Y-m-d');
        $tenggat = !empty($_POST['tenggat']) ? trim($_POST['tenggat']) : null;
        $jam = !empty($_POST['jam']) ? trim($_POST['jam']) : null;

        if (!$karyawan_id || !$deskripsi || !$tanggal) {
            fail('Karyawan pelaksana, tanggal, dan deskripsi kegiatan/tugas wajib diisi.');
        }

        $kar = $conn->query("SELECT id, user_id, nama, jabatan, divisi_id, atasan_id FROM karyawan WHERE id = $karyawan_id")->fetch_assoc();
        if (!$kar || !canUserAccessKaryawanRow($user, $kar)) {
            fail('Karyawan tidak ditemukan.');
        }
        if (!isPelaksanaOperasionalJabatan($kar['jabatan'] ?? '')) {
            fail('Penugasan ditolak. Pelaksana tugas hanya diperbolehkan untuk karyawan berjabatan CSO atau Gardener.');
        }

        // Validasi: Team Leader hanya boleh memberi tugas kepada bawahan langsungnya
        if (!hasAccess($role, 'karyawan', 'monitoring') && $role !== 'spv_sapras') {
            if (!$myKar || (int)$kar['atasan_id'] !== (int)$myKar['id']) {
                forbidden('Akses ditolak. Anda hanya berhak memberikan tugas kepada bawahan langsung Anda.');
            }
        }

        if (isPenilaianHarianLocked($conn, $karyawan_id, $tanggal)) {
            fail('Tugas pada tanggal ini sudah dinilai dan terkunci permanen.');
        }

        $stmt = $conn->prepare("INSERT INTO kegiatan_karyawan (karyawan_id, assigned_by, jenis, deskripsi, tanggal, tenggat, jam, status) VALUES (?,?,?,?,?,?,?, 'ditugaskan')");
        $stmt->bind_param("iisssss", $karyawan_id, $user['id'], $jenis, $deskripsi, $tanggal, $tenggat, $jam);
        $stmt->execute();
        $newId = $stmt->insert_id;

        // Notifikasi ke karyawan pelaksana
        if ($kar['user_id'] && (int)$kar['user_id'] !== (int)$user['id']) {
            kirimNotifikasi($kar['user_id'], 'karyawan', "Anda mendapat penugasan non-rutin baru: " . mb_substr($deskripsi, 0, 45) . "...", [
                'kategori' => 'tugas',
                'prioritas' => 'normal',
                'link_page' => 'karyawan',
                'link_tab' => 'tabKegiatan'
            ]);
        }

        logAktivitas($user['id'], 'karyawan_kinerja', "Memberikan tugas non-rutin kepada {$kar['nama']} ({$kar['jabatan']})");
        ok(['id' => $newId]);

    case 'update_kegiatan':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak berwenang mengelola kegiatan karyawan.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID kegiatan tidak valid.');

        $task = $conn->query("
            SELECT kk.*, k.id AS kar_id, k.user_id AS kar_user_id, k.nama AS kar_nama, k.jabatan, k.atasan_id, k.divisi_id
            FROM kegiatan_karyawan kk
            JOIN karyawan k ON k.id = kk.karyawan_id
            WHERE kk.id = $id
        ")->fetch_assoc();
        if (!$task) fail('Kegiatan tidak ditemukan.');

        // PENGUNCIAN PERMANEN: Jika tanggal tugas sudah dinilai di penilaian_harian, tolak SEMUA user (CSO, TL, SPV, Admin)
        if (isPenilaianHarianLocked($conn, $task['karyawan_id'], $task['tanggal'])) {
            forbidden('Tugas pada tanggal ini sudah dinilai dan terkunci permanen.');
        }

        $isPelaksanaRole = in_array($role, ['cso', 'gardener'], true);
        $isPelaksana = ($task['kar_user_id'] && (int)$task['kar_user_id'] === (int)$user['id']);

        if ($isPelaksanaRole) {
            if (!$isPelaksana) {
                forbidden('Akses ditolak. Anda tidak berwenang mengubah tugas karyawan lain.');
            }
            if ($task['tanggal'] !== date('Y-m-d')) {
                forbidden('Tugas rutin hanya dapat diperbarui pada tanggal hari ini.');
            }
        } elseif ($task['jenis'] === 'rutin') {
            forbidden('Tugas rutin dikelola sendiri oleh karyawan pelaksana bersangkutan.');
        } else {
            $hasMonitoring = hasAccess($role, 'karyawan', 'monitoring');
            $myKar = getKaryawanByUserId($user['id']);
            $subIds = $myKar ? getSubordinateKaryawanIds($myKar['id'], true) : [];
            $isHierarchicalSuperior = in_array((int)$task['kar_id'], $subIds, true);
            if (!$hasMonitoring && !$isPelaksana && !$isHierarchicalSuperior) {
                forbidden('Akses ditolak. Anda tidak memiliki wewenang pada kegiatan ini.');
            }
        }

        $status = in_array($_POST['status'] ?? '', ['ditugaskan', 'dikerjakan', 'selesai']) ? $_POST['status'] : $task['status'];
        $hasil = isset($_POST['hasil_laporan']) ? trim($_POST['hasil_laporan']) : $task['hasil_laporan'];
        $deskripsi = (!empty($_POST['deskripsi']) && $isPelaksana) ? trim($_POST['deskripsi']) : $task['deskripsi'];
        $selesaiPada = ($status === 'selesai') ? ($task['selesai_pada'] ?: date('Y-m-d H:i:s')) : null;

        // Penanganan hapus foto yang diminta
        if (!empty($_POST['hapus_foto_ids'])) {
            $delFotoIds = is_array($_POST['hapus_foto_ids']) ? $_POST['hapus_foto_ids'] : explode(',', $_POST['hapus_foto_ids']);
            foreach ($delFotoIds as $fId) {
                $fId = (int)$fId;
                if ($fId > 0) {
                    $fRow = $conn->query("SELECT path FROM kegiatan_foto WHERE id = $fId AND kegiatan_id = $id")->fetch_assoc();
                    if ($fRow) {
                        if (file_exists(__DIR__ . '/../' . $fRow['path'])) {
                            @unlink(__DIR__ . '/../' . $fRow['path']);
                        }
                        $conn->query("DELETE FROM kegiatan_foto WHERE id = $fId");
                    }
                }
            }
        }
        if (!empty($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1' && !empty($task['foto_laporan'])) {
            if (file_exists(__DIR__ . '/../' . $task['foto_laporan'])) {
                @unlink(__DIR__ . '/../' . $task['foto_laporan']);
            }
            $conn->query("UPDATE kegiatan_karyawan SET foto_laporan = NULL WHERE id = $id");
            $task['foto_laporan'] = null;
        }

        // Hitung foto yang sudah ada di database
        $currFotosCount = (int)$conn->query("SELECT COUNT(*) AS n FROM kegiatan_foto WHERE kegiatan_id = $id")->fetch_assoc()['n'];

        // Penanganan upload bukti foto baru
        $filesToProcess = [];
        if (!empty($_FILES['fotos']['name'])) {
            if (is_array($_FILES['fotos']['name'])) {
                foreach ($_FILES['fotos']['name'] as $k => $name) {
                    if (!empty($_FILES['fotos']['tmp_name'][$k]) && is_uploaded_file($_FILES['fotos']['tmp_name'][$k])) {
                        $filesToProcess[] = [
                            'name' => $name,
                            'type' => $_FILES['fotos']['type'][$k] ?? '',
                            'tmp_name' => $_FILES['fotos']['tmp_name'][$k],
                            'error' => $_FILES['fotos']['error'][$k] ?? 0,
                            'size' => $_FILES['fotos']['size'][$k] ?? 0
                        ];
                    }
                }
            } elseif (!empty($_FILES['fotos']['tmp_name']) && is_uploaded_file($_FILES['fotos']['tmp_name'])) {
                $filesToProcess[] = $_FILES['fotos'];
            }
        }
        if (!empty($_FILES['foto_laporan']['tmp_name']) && is_uploaded_file($_FILES['foto_laporan']['tmp_name'])) {
            $filesToProcess[] = $_FILES['foto_laporan'];
        }

        if (!empty($filesToProcess)) {
            if ($currFotosCount + count($filesToProcess) > 3) {
                fail("Maksimal 3 foto dokumentasi per tugas (saat ini sudah ada $currFotosCount foto).");
            }
            require_once '../config/upload.php';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $dir = __DIR__ . '/../uploads/karyawan/';

            $stmtFoto = $conn->prepare("INSERT INTO kegiatan_foto (kegiatan_id, path) VALUES (?, ?)");
            foreach ($filesToProcess as $fObj) {
                try {
                    $absPath = secureUpload($fObj, $allowed, $dir, 5242880);
                } catch (Exception $e) {
                    fail($e->getMessage());
                }
                $relP = 'uploads/karyawan/' . basename($absPath);
                $stmtFoto->bind_param("is", $id, $relP);
                $stmtFoto->execute();
                $currFotosCount++;
                if (empty($task['foto_laporan'])) {
                    $task['foto_laporan'] = $relP;
                    $conn->query("UPDATE kegiatan_karyawan SET foto_laporan = '" . $conn->real_escape_string($relP) . "' WHERE id = $id");
                }
            }
        }

        // Jika status selesai, wajib minimal ada 1 foto (di kegiatan_foto atau foto_laporan)
        if ($status === 'selesai' && $currFotosCount < 1 && empty($task['foto_laporan'])) {
            fail('Menandai tugas rutin selesai wajib melampirkan minimal 1 foto bukti.');
        }

        $stmt = $conn->prepare("UPDATE kegiatan_karyawan SET deskripsi=?, status=?, hasil_laporan=?, selesai_pada=? WHERE id=?");
        $stmt->bind_param("ssssi", $deskripsi, $status, $hasil, $selesaiPada, $id);
        $stmt->execute();

        // Notifikasi hanya dikirim jika tugas NON-RUTIN selesai
        if ($status === 'selesai' && $task['status'] !== 'selesai' && $task['jenis'] === 'non-rutin') {
            if (!empty($task['atasan_id'])) {
                $atasanRow = $conn->query("SELECT user_id FROM karyawan WHERE id = " . (int)$task['atasan_id'])->fetch_assoc();
                $atasanUid = $atasanRow['user_id'] ?? null;
                if ($atasanUid && (int)$atasanUid !== (int)$user['id']) {
                    kirimNotifikasi($atasanUid, 'karyawan', "Tugas non-rutin oleh {$task['kar_nama']} ({$task['jabatan']}) telah selesai dikerjakan.", [
                        'kategori' => 'tugas',
                        'prioritas' => 'normal',
                        'link_page' => 'karyawan',
                        'link_tab' => 'tabKegiatan'
                    ]);
                }
            }
        }

        logAktivitas($user['id'], 'karyawan_kinerja', "Memperbarui kegiatan #$id status: $status");
        ok();

    case 'hapus_kegiatan':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak berwenang mengelola kegiatan karyawan.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) fail('ID kegiatan tidak valid.');

        $task = $conn->query("
            SELECT kk.*, k.id AS kar_id, k.user_id AS kar_user_id
            FROM kegiatan_karyawan kk
            JOIN karyawan k ON k.id = kk.karyawan_id
            WHERE kk.id = $id
        ")->fetch_assoc();
        if (!$task) fail('Kegiatan tidak ditemukan.');

        // PENGUNCIAN PERMANEN: Jika tanggal tugas sudah dinilai di penilaian_harian, tolak SEMUA user
        if (isPenilaianHarianLocked($conn, $task['karyawan_id'], $task['tanggal'])) {
            forbidden('Tugas pada tanggal ini sudah dinilai dan terkunci permanen.');
        }

        $isPelaksanaRole = in_array($role, ['cso', 'gardener'], true);
        $isPelaksana = ($task['kar_user_id'] && (int)$task['kar_user_id'] === (int)$user['id']);

        if ($isPelaksanaRole) {
            if (!$isPelaksana) {
                forbidden('Akses ditolak. Anda tidak berwenang menghapus tugas karyawan lain.');
            }
            if ($task['tanggal'] !== date('Y-m-d')) {
                forbidden('Tugas rutin hanya dapat dihapus pada tanggal hari ini.');
            }
            if ($task['jenis'] !== 'rutin') {
                forbidden('Karyawan hanya dapat menghapus tugas rutin miliknya sendiri.');
            }
        } elseif ($task['jenis'] === 'rutin') {
            forbidden('Team Leader tidak berwenang menghapus tugas rutin karyawan.');
        } else {
            $hasMonitoring = hasAccess($role, 'karyawan', 'monitoring');
            $myKar = getKaryawanByUserId($user['id']);
            $subIds = $myKar ? getSubordinateKaryawanIds($myKar['id'], false) : [];
            $isHierarchicalSuperior = in_array((int)$task['kar_id'], $subIds, true);
            if (!$hasMonitoring && !$isHierarchicalSuperior) {
                forbidden('Akses ditolak. Penghapusan tugas non-rutin hanya dapat dilakukan oleh atasan dalam hierarki.');
            }
        }

        $conn->query("DELETE FROM kegiatan_karyawan WHERE id = $id");
        logAktivitas($user['id'], 'karyawan_kinerja', "Menghapus penugasan kegiatan #$id");
        ok();

    /* ── 3. MONITORING & REKAP KINERJA (KAK c) ── */
    case 'rekap_karyawan':
        if (in_array($role, ['cso', 'gardener'], true)) {
            forbidden('Akses ditolak. CSO dan Gardener hanya dapat mengakses Kegiatan Karyawan dan Penilaian Kinerja.');
        }
        $bulan = $_GET['bulan'] ?? '';
        $divisi_id = !empty($_GET['divisi_id']) ? (int)$_GET['divisi_id'] : 0;
        $isHRD = isHrdAdministratifRole($user);
        $pimFilter = getPimpinanExclusionSql($user, 'k');

        // Jika filter divisi mengarah ke unit Pimpinan dan user tidak punya akses
        $pimUnitId = getPimpinanUnitId();
        if ($pimUnitId > 0 && $divisi_id === $pimUnitId && !canAccessPimpinanUnit($user)) {
            ok([]);
        }

        if ($isHRD) {
            // HRD dibatasi ke data administratif saja (tanpa jumlah tugas, penilaian, atau skor)
            $whereConditions = [$pimFilter];
            if (!empty($_GET['status'])) {
                $stEsc = $conn->real_escape_string($_GET['status']);
                $whereConditions[] = "k.status = '$stEsc'";
            } else {
                $whereConditions[] = "k.status = 'aktif'";
            }

            if ($divisi_id > 0) {
                $whereConditions[] = "k.divisi_id = $divisi_id";
            }

            if (!empty($bulan)) {
                $bulanEsc = $conn->real_escape_string($bulan);
                $whereConditions[] = "DATE_FORMAT(k.tanggal_masuk, '%Y-%m') = '$bulanEsc'";
            }

            $whereSql = implode(' AND ', $whereConditions);

            $sql = "
                SELECT 
                    k.id,
                    k.nip,
                    k.nama,
                    k.jabatan,
                    su.nama_unit,
                    k.status,
                    k.tanggal_masuk
                FROM karyawan k
                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                WHERE $whereSql
                ORDER BY k.nama ASC
            ";

            ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));
        }

        // Untuk non-HRD (Rekap Kinerja): hanya menampilkan karyawan pelaksana tugas (CSO & Gardener) dan menyembunyikan Pimpinan
        $whereConditions = [
            "k.status = 'aktif'",
            "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')",
            $pimFilter
        ];

        // Cakupan akses data
        if (!canViewAllKaryawan($user) && !hasAccess($role, 'karyawan', 'penilaian')) {
            $myKar = getKaryawanByUserId($user['id']);
            if ($myKar) {
                $subIds = getSubordinateKaryawanIds($myKar['id'], true);
                if (!empty($subIds)) {
                    $idsStr = implode(',', array_map('intval', $subIds));
                    $whereConditions[] = "k.id IN ($idsStr)";
                } else {
                    $whereConditions[] = "1=0";
                }
            } else {
                $whereConditions[] = "1=0";
            }
        }

        if ($divisi_id > 0) {
            $whereConditions[] = "k.divisi_id = $divisi_id";
        }

        $whereSql = implode(' AND ', $whereConditions);
        $bulanFilter = !empty($bulan) ? $conn->real_escape_string($bulan) : date('Y-m');

        $sql = "
            SELECT 
                k.id,
                k.nip,
                k.nama,
                k.jabatan,
                su.nama_unit,
                (SELECT COUNT(DISTINCT kk.tanggal) 
                 FROM kegiatan_karyawan kk 
                 WHERE kk.karyawan_id = k.id AND kk.jenis = 'rutin' AND DATE_FORMAT(kk.tanggal, '%Y-%m') = '$bulanFilter') AS hari_rutin,
                (SELECT COUNT(DISTINCT ph.tanggal) 
                 FROM penilaian_harian ph 
                 WHERE ph.karyawan_id = k.id AND DATE_FORMAT(ph.tanggal, '%Y-%m') = '$bulanFilter') AS hari_dinilai,
                (SELECT ROUND(AVG(ph.nilai), 2) 
                 FROM penilaian_harian ph 
                 WHERE ph.karyawan_id = k.id AND DATE_FORMAT(ph.tanggal, '%Y-%m') = '$bulanFilter') AS rata_harian,
                (SELECT ROUND(AVG(pk.nilai), 2) 
                 FROM penilaian_kinerja pk 
                 JOIN kegiatan_karyawan kk ON kk.id = pk.kegiatan_id 
                 WHERE kk.karyawan_id = k.id AND kk.jenis = 'non-rutin' AND DATE_FORMAT(kk.tanggal, '%Y-%m') = '$bulanFilter') AS rata_nonrutin,
                (SELECT COUNT(DISTINCT kk.tanggal) 
                 FROM kegiatan_karyawan kk 
                 LEFT JOIN penilaian_harian ph ON ph.karyawan_id = kk.karyawan_id AND ph.tanggal = kk.tanggal 
                 WHERE kk.karyawan_id = k.id AND kk.jenis = 'rutin' AND ph.id IS NULL 
                   AND (kk.tanggal < CURDATE() OR (kk.tanggal = CURDATE() AND CURTIME() > '16:30:00')) 
                   AND DATE_FORMAT(kk.tanggal, '%Y-%m') = '$bulanFilter') AS hari_belum_dinilai,
                COUNT(DISTINCT kk_all.id) AS total_tugas,
                COUNT(DISTINCT CASE WHEN kk_all.status = 'selesai' THEN kk_all.id END) AS tugas_selesai
            FROM karyawan k
            LEFT JOIN struktur_unit su ON su.id = k.divisi_id
            LEFT JOIN kegiatan_karyawan kk_all ON kk_all.karyawan_id = k.id AND DATE_FORMAT(kk_all.tanggal, '%Y-%m') = '$bulanFilter'
            WHERE $whereSql
            GROUP BY k.id
            ORDER BY k.nama ASC
        ";

        $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$r) {
            $harian = $r['rata_harian'] !== null ? (float)$r['rata_harian'] : null;
            $nonrutin = $r['rata_nonrutin'] !== null ? (float)$r['rata_nonrutin'] : null;

            if ($harian !== null && $nonrutin !== null) {
                $r['nilai_akhir'] = round((BOBOT_HARIAN * $harian) + (BOBOT_NONRUTIN * $nonrutin), 2);
            } elseif ($harian !== null) {
                $r['nilai_akhir'] = round($harian, 2);
            } elseif ($nonrutin !== null) {
                $r['nilai_akhir'] = round($nonrutin, 2);
            } else {
                $r['nilai_akhir'] = null;
            }
        }
        ok($rows);

    case 'list_penilaian':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak memiliki akses ke daftar penilaian kinerja.');
        }

        $pimFilter = getPimpinanExclusionSql($user, 'k');
        $pelaksanaFilter = "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener') AND ($pimFilter)";

        if (canViewAllKaryawan($user) || hasAccess($role, 'karyawan', 'penilaian')) {
            $where = $pelaksanaFilter;
        } else {
            $accessibleIds = getAccessibleKaryawanIds($user, true);
            if (empty($accessibleIds)) {
                ok([]);
            }
            $idsStr = implode(',', array_map('intval', $accessibleIds));
            $where = "pk.karyawan_id IN ($idsStr) AND $pelaksanaFilter";
        }

        $sql = "SELECT pk.*, k.nama AS karyawan_nama, k.nip AS karyawan_nip, k.jabatan,
                       su.nama_unit, u.name AS penilai_nama,
                       kk.deskripsi AS tugas_deskripsi, kk.tanggal AS tugas_tanggal, kk.hasil_laporan AS tugas_hasil, kk.foto_laporan AS tugas_foto
                FROM penilaian_kinerja pk
                JOIN karyawan k ON k.id = pk.karyawan_id
                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                JOIN users u ON u.id = pk.penilai_id
                LEFT JOIN kegiatan_karyawan kk ON kk.id = pk.kegiatan_id
                WHERE $where
                ORDER BY pk.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'tambah_penilaian':
        // Tugas 1b: Yang boleh memberi nilai HANYA role dengan izin 'penilaian' (spv_sapras).
        if (isHrdAdministratifRole($user) || !hasAccess($role, 'karyawan', 'penilaian')) {
            forbidden('Akses ditolak. Penilaian kinerja hanya dapat diberikan oleh SPV Sapras.');
        }

        $kegiatan_id = !empty($_POST['kegiatan_id']) ? (int)$_POST['kegiatan_id'] : null;
        $karyawan_id = (int)($_POST['karyawan_id'] ?? 0);
        $periode = trim($_POST['periode'] ?? date('F Y'));
        $catatan = trim($_POST['catatan'] ?? '');

        // Validasi skor nilai: wajib bilangan bulat 1-5
        $rawNilai = $_POST['nilai'] ?? null;
        if ($rawNilai === null || trim((string)$rawNilai) === '' || filter_var($rawNilai, FILTER_VALIDATE_INT) === false) {
            fail('Skor nilai (1-5) wajib diisi.');
        }
        $nilai = (int)$rawNilai;
        if ($nilai < 1 || $nilai > 5) {
            fail('Skor nilai (1-5) wajib diisi.');
        }

        if ($kegiatan_id) {
            $task = $conn->query("SELECT kk.*, k.id AS kar_id, k.nama AS kar_nama, k.jabatan, k.user_id, k.atasan_id
                                  FROM kegiatan_karyawan kk 
                                  JOIN karyawan k ON k.id = kk.karyawan_id 
                                  WHERE kk.id = $kegiatan_id")->fetch_assoc();
            if (!$task) fail('Tugas tidak ditemukan.');
            // Tugas rutin dinilai lewat penilaian_harian, BUKAN per tugas!
            if ($task['jenis'] === 'rutin') {
                fail('Penilaian per tugas hanya berlaku untuk tugas non-rutin. Tugas rutin dinilai melalui penilaian harian.');
            }
            if ($task['status'] !== 'selesai') {
                fail('Penilaian kinerja hanya dapat diberikan setelah tugas berstatus selesai.');
            }
            $karyawan_id = (int)$task['kar_id'];
        }

        if (!$karyawan_id) {
            fail('Karyawan yang dinilai wajib diisi.');
        }

        $kar = $conn->query("SELECT id, user_id, nama, jabatan, divisi_id, atasan_id FROM karyawan WHERE id = $karyawan_id")->fetch_assoc();
        if (!$kar) fail('Data karyawan tidak ditemukan.');

        // Penilaian hanya boleh untuk pelaksana CSO atau Gardener
        if (!isPelaksanaOperasionalJabatan($kar['jabatan'] ?? '')) {
            forbidden('Penilaian ditolak. Penilaian kinerja hanya berlaku untuk karyawan berjabatan CSO atau Gardener.');
        }

        // Cek jika tugas ini sudah pernah dinilai sebelumnya, update saja nilainya
        if ($kegiatan_id) {
            $cek = $conn->query("SELECT id FROM penilaian_kinerja WHERE kegiatan_id = $kegiatan_id")->fetch_assoc();
            if ($cek) {
                $stmt = $conn->prepare("UPDATE penilaian_kinerja SET penilai_id=?, periode=?, nilai=?, catatan=? WHERE id=?");
                $stmt->bind_param("isisi", $user['id'], $periode, $nilai, $catatan, $cek['id']);
                $stmt->execute();

                if ($kar['user_id']) {
                    kirimNotifikasi($kar['user_id'], 'karyawan', "{$user['name']} memperbarui penilaian kinerja tugas Anda.", [
                        'kategori' => 'tugas',
                        'prioritas' => 'normal',
                        'link_page' => 'karyawan',
                        'link_tab' => 'tabPenilaian'
                    ]);
                }
                logAktivitas($user['id'], 'karyawan_kinerja', "Memperbarui penilaian kinerja tugas #$kegiatan_id untuk {$kar['nama']}");
                ok(['id' => $cek['id'], 'updated' => true]);
            }
        }

        $stmt = $conn->prepare("INSERT INTO penilaian_kinerja (karyawan_id, kegiatan_id, penilai_id, periode, nilai, catatan) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iiisis", $karyawan_id, $kegiatan_id, $user['id'], $periode, $nilai, $catatan);
        $stmt->execute();

        if ($kar['user_id']) {
            kirimNotifikasi($kar['user_id'], 'karyawan', "{$user['name']} memberikan penilaian kinerja untuk tugas Anda.", [
                'kategori' => 'tugas',
                'prioritas' => 'normal',
                'link_page' => 'karyawan',
                'link_tab' => 'tabPenilaian'
            ]);
        }

        $logAktivitasTeks = $kegiatan_id 
            ? "Memberikan penilaian kinerja tugas #$kegiatan_id kepada {$kar['nama']}" 
            : "Memberikan penilaian kinerja kepada {$kar['nama']}";
        logAktivitas($user['id'], 'karyawan_kinerja', $logAktivitasTeks);
        ok(['id' => $stmt->insert_id]);

    case 'simpan_penilaian_harian':
        if ($role !== 'spv_sapras') {
            forbidden('Akses ditolak. Penilaian harian hanya dapat diberikan oleh SPV Sapras.');
        }

        $karyawan_id = (int)($_POST['karyawan_id'] ?? 0);
        $tanggal = trim($_POST['tanggal'] ?? '');
        $nilai = (int)($_POST['nilai'] ?? 0);
        $catatan = trim($_POST['catatan'] ?? '');

        if (!$karyawan_id || !$tanggal) {
            fail('Karyawan pelaksana dan tanggal penilaian wajib diisi.');
        }
        if ($nilai < 1 || $nilai > 5) {
            fail('Skor nilai harian wajib antara 1 sampai 5.');
        }

        $check = canSpvRateHarian($conn, $karyawan_id, $tanggal);
        if (!$check['allowed']) {
            fail($check['reason']);
        }
        $kar = $check['karyawan'];

        // INSERT ONLY (Terkunci permanen, penanganan balapan data / race condition)
        $conn->begin_transaction();
        try {
            $cekStmt = $conn->prepare("SELECT id FROM penilaian_harian WHERE karyawan_id = ? AND tanggal = ? FOR UPDATE");
            $cekStmt->bind_param("is", $karyawan_id, $tanggal);
            $cekStmt->execute();
            if ($cekStmt->get_result()->fetch_assoc()) {
                $conn->rollback();
                fail('Nilai harian sudah diberikan dan terkunci.');
            }

            $stmt = $conn->prepare("INSERT INTO penilaian_harian (karyawan_id, tanggal, nilai, catatan, dinilai_oleh) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isisi", $karyawan_id, $tanggal, $nilai, $catatan, $user['id']);
            $stmt->execute();
            $newId = $stmt->insert_id;

            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ($e->getCode() == 1062) {
                fail('Nilai harian sudah diberikan dan terkunci.');
            }
            fail('Gagal menyimpan penilaian harian: ' . $e->getMessage());
        }

        if (!empty($kar['user_id'])) {
            kirimNotifikasi($kar['user_id'], 'karyawan', "SPV Sapras telah memberikan penilaian harian untuk tanggal $tanggal.", [
                'kategori' => 'tugas',
                'prioritas' => 'normal',
                'link_page' => 'karyawan',
                'link_tab' => 'tabKegiatan'
            ]);
        }

        logAktivitas($user['id'], 'karyawan_kinerja', "Memberikan penilaian harian tanggal $tanggal kepada {$kar['nama']} (Skor: $nilai/5)");
        ok(['id' => $newId]);

    case 'get_penilaian_harian':
        $karyawan_id = (int)($_GET['karyawan_id'] ?? 0);
        $tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));
        if (!$karyawan_id) fail('ID karyawan wajib diisi.');

        $stmt = $conn->prepare("
            SELECT ph.*, u.name AS penilai_nama, k.nama AS karyawan_nama, k.jabatan
            FROM penilaian_harian ph
            JOIN users u ON u.id = ph.dinilai_oleh
            JOIN karyawan k ON k.id = ph.karyawan_id
            WHERE ph.karyawan_id = ? AND ph.tanggal = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $karyawan_id, $tanggal);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        ok($row ?: null);

    case 'list_penilaian_harian':
        if (isHrdAdministratifRole($user)) {
            forbidden('Akses ditolak. HRD tidak memiliki akses ke penilaian harian.');
        }
        $pimFilter = getPimpinanExclusionSql($user, 'k');
        $pelaksanaFilter = "LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener') AND ($pimFilter)";

        $where = "";
        if (canViewAllKaryawan($user) || hasAccess($role, 'karyawan', 'penilaian')) {
            $where = $pelaksanaFilter;
        } else {
            $myKar = getKaryawanByUserId($user['id']);
            if ($myKar) {
                $subIds = getSubordinateKaryawanIds($myKar['id'], true);
                $idsStr = !empty($subIds) ? implode(',', array_map('intval', $subIds)) : '0';
                $where = "ph.karyawan_id IN ($idsStr) AND $pelaksanaFilter";
            } else {
                ok([]);
            }
        }
        if (!empty($_GET['bulan'])) {
            $bEsc = $conn->real_escape_string($_GET['bulan']);
            $where .= " AND DATE_FORMAT(ph.tanggal, '%Y-%m') = '$bEsc'";
        }
        if (!empty($_GET['karyawan_id'])) {
            $kid = (int)$_GET['karyawan_id'];
            $where .= " AND ph.karyawan_id = $kid";
        }
        $sql = "SELECT ph.*, k.nama AS karyawan_nama, k.nip AS karyawan_nip, k.jabatan,
                       su.nama_unit, u.name AS penilai_nama,
                       (SELECT COUNT(*) FROM kegiatan_karyawan kk WHERE kk.karyawan_id = ph.karyawan_id AND kk.tanggal = ph.tanggal AND kk.jenis = 'rutin') AS total_tugas_rutin,
                       (SELECT COUNT(*) FROM kegiatan_karyawan kk WHERE kk.karyawan_id = ph.karyawan_id AND kk.tanggal = ph.tanggal AND kk.jenis = 'rutin' AND kk.status = 'selesai') AS tugas_rutin_selesai
                FROM penilaian_harian ph
                JOIN karyawan k ON k.id = ph.karyawan_id
                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                JOIN users u ON u.id = ph.dinilai_oleh
                WHERE $where
                ORDER BY ph.tanggal DESC, ph.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    default:
        fail('Aksi tidak dikenal.');
}
