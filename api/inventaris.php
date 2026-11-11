<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg, $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }
function forbid($msg = 'Akses ditolak. Anda tidak memiliki hak akses untuk aksi ini.') { fail($msg, 403); }

if (!hasAccess($role, 'inventaris', 'lihat')) forbid('Anda tidak memiliki hak akses ke modul Inventaris.');

// Auto-migrate database tables/columns for production if missing
// (Dipindahkan ke database/migrations.sql)

try {
    switch ($action) {

        case 'list_barang':
            $sql = "SELECT b.*,
                           GREATEST(0, b.stok - COALESCE((
                               SELECT SUM(p.jumlah)
                               FROM peminjaman p
                               WHERE p.barang_id = b.id
                                 AND p.status IN ('menunggu_approval_atasan', 'diajukan', 'disetujui_atasan', 'diverifikasi')
                           ), 0)) AS stok_tersedia
                    FROM barang b
                    ORDER BY b.kode_barang ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            ok($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

        case 'list_peminjaman':
            $uid = (int)$user['id'];
            $unitId = (int)($user['unit_id'] ?? 0);
            $atasanSayaId = (int)(getAtasanId($uid) ?: 0);
            $canSeeAllSubmitted = hasAccess($role, 'inventaris', 'kelola')
                || hasAccess($role, 'inventaris', 'approve')
                || hasAccess($role, 'inventaris', 'monitoring')
                || hasAccess($role, 'inventaris', 'verifikasi')
                || hasAccess($role, 'inventaris', 'verifikasi_pengganti')
                || hasAccess($role, 'inventaris', 'approve_akhir_pengganti')
                || $role === 'admin'
                || $role === 'manajer_ga';
            $canApproveAtasan = hasAccess($role, 'inventaris', 'approve_atasan');
            $canApprovePengganti = hasAccess($role, 'inventaris', 'approve_pengganti');

            $sql = "SELECT p.*, b.nama_barang, b.kode_barang, u1.name AS peminjam_nama, u1.unit_id AS peminjam_unit_id, r1.nama_role AS peminjam_jabatan,
                           u2.name AS verifikator_nama, u3.name AS approval_nama, u4.name AS atasan_nama,
                           u5.name AS pengganti_nama, u6.name AS verifikasi_pengganti_nama, u7.name AS approve_akhir_pengganti_nama
                    FROM peminjaman p
                    JOIN barang b ON b.id = p.barang_id
                    JOIN users u1 ON u1.id = p.peminjam_id
                    LEFT JOIN roles r1 ON r1.id = u1.role_id
                    LEFT JOIN users u2 ON u2.id = p.verifikator_id
                    LEFT JOIN users u3 ON u3.id = p.approval_id
                    LEFT JOIN users u4 ON u4.id = p.atasan_id
                    LEFT JOIN users u5 ON u5.id = p.approval_pengganti_oleh
                    LEFT JOIN users u6 ON u6.id = p.verifikasi_pengganti_oleh
                    LEFT JOIN users u7 ON u7.id = p.approve_akhir_pengganti_oleh
                    WHERE (
                        p.peminjam_id = ?
                        OR (? > 0 AND ? > 0 AND p.peminjam_id = ? AND u1.unit_id = ?)
                        OR (? = 1 AND (
                            u1.unit_id = ?
                            OR EXISTS (
                                SELECT 1 FROM karyawan k1
                                JOIN karyawan k2 ON k2.id = k1.atasan_id
                                WHERE k1.user_id = p.peminjam_id AND k2.user_id = ?
                            )
                        ))
                        OR (? = 1 AND p.status != 'menunggu_approval_atasan')
                        OR (? = 1 AND p.status = 'menunggu_approval_atasan')
                        OR p.approval_pengganti_oleh = ?
                        OR p.verifikasi_pengganti_oleh = ?
                        OR p.approve_akhir_pengganti_oleh = ?
                    )
                    ORDER BY p.id DESC";
            $flagAtasan = $canApproveAtasan ? 1 : 0;
            $flagAll = $canSeeAllSubmitted ? 1 : 0;
            $flagPengganti = $canApprovePengganti ? 1 : 0;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiiiiiiiiiii", $uid, $atasanSayaId, $unitId, $atasanSayaId, $unitId, $flagAtasan, $unitId, $uid, $flagAll, $flagPengganti, $uid, $uid, $uid);
            $stmt->execute();
            $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $rows = [];
            foreach ($rawRows as $row) {
                $peminjamId = (int)$row['peminjam_id'];
                $peminjamUnitId = (int)($row['peminjam_unit_id'] ?? 0);
                $expectedAtasanId = (int)getAtasanId($peminjamId);
                $isAtasanSeunitRow = ($atasanSayaId > 0 && $unitId > 0 && $peminjamId === $atasanSayaId && $peminjamUnitId === $unitId);
                $isApproveAtasanRow = ($canApproveAtasan && ($peminjamUnitId === $unitId || $expectedAtasanId === $uid));
                $isOwnOrHistoryRow = (
                    $peminjamId === $uid
                    || (int)($row['approval_pengganti_oleh'] ?? 0) === $uid
                    || (int)($row['verifikasi_pengganti_oleh'] ?? 0) === $uid
                    || (int)($row['approve_akhir_pengganti_oleh'] ?? 0) === $uid
                    || $isAtasanSeunitRow
                );
                $isSubmittedRow = ($canSeeAllSubmitted && $row['status'] !== 'menunggu_approval_atasan');
                $canActPengganti = ($row['status'] === 'menunggu_approval_atasan'
                    && $canApprovePengganti
                    && bolehApprovePengganti($user, $peminjamId));

                if (!$isOwnOrHistoryRow && !$isApproveAtasanRow && !$isSubmittedRow && !$canActPengganti) {
                    continue;
                }

                $row['can_approve_atasan'] = ($row['status'] === 'menunggu_approval_atasan'
                    && $canApproveAtasan
                    && $expectedAtasanId === $uid);
                $row['can_approve_pengganti'] = $canActPengganti;
                $row['can_verifikasi'] = (in_array($row['status'], ['diajukan', 'disetujui_atasan'], true)
                    && hasAccess($role, 'inventaris', 'verifikasi')
                    && $peminjamId !== $uid);
                $row['can_verifikasi_pengganti'] = (in_array($row['status'], ['diajukan', 'disetujui_atasan'], true)
                    && bolehVerifikasiPengganti($user, 'inventaris', $peminjamId));
                $row['can_approve_akhir'] = ($row['status'] === 'diverifikasi'
                    && hasAccess($role, 'inventaris', 'approve')
                    && $peminjamId !== $uid);
                $row['can_approve_akhir_pengganti'] = ($row['status'] === 'diverifikasi'
                    && bolehApproveAkhirPengganti($user, 'inventaris', $peminjamId));
                $row['can_cancel'] = ($peminjamId === $uid
                    && in_array($row['status'], ['menunggu_approval_atasan', 'diajukan', 'disetujui_atasan', 'diverifikasi'], true));
                $row['can_return'] = ($row['status'] === 'disetujui' && (
                    hasAccess($role, 'inventaris', 'verifikasi')
                    || hasAccess($role, 'inventaris', 'kelola')
                    || $peminjamId === $uid
                    || $isAtasanSeunitRow
                ));
                $rows[] = $row;
            }
            ok($rows);

        case 'list_pemeriksaan':
            if (!hasAccess($role, 'inventaris', 'kelola')) ok([]);
            $sql = "SELECT peng.*, pj.tanggal_pinjam, pj.jumlah, b.nama_barang, u.name AS peminjam_nama 
                    FROM pengembalian peng 
                    JOIN peminjaman pj ON pj.id = peng.peminjaman_id 
                    JOIN barang b ON b.id = pj.barang_id
                    JOIN users u ON u.id = pj.peminjam_id
                    WHERE peng.kondisi_barang != 'baik'
                    ORDER BY peng.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            ok($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            
        case 'list_riwayat':
            if (!hasAccess($role, 'inventaris', 'kelola')) ok([]);
            $sql = "SELECT r.*, b.nama_barang, u.name AS nama_user 
                    FROM riwayat_inventaris r 
                    JOIN barang b ON b.id = r.barang_id
                    LEFT JOIN users u ON u.id = r.user_id
                    ORDER BY r.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            ok($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

        case 'tambah_barang':
            if (!hasAccess($role, 'inventaris', 'tambah')) forbid('Akses ditolak.');
            $nama = trim($_POST['nama_barang'] ?? '');
            if (!$nama) fail('Nama barang wajib diisi.');
            $kategori = trim($_POST['kategori'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            $stok = (int)($_POST['stok'] ?? 0);
            if ($stok < 0) fail('Stok tidak boleh negatif.');
            $ket = trim($_POST['keterangan'] ?? '');
            $stmtCnt = $conn->prepare("SELECT COUNT(*) + 1 AS n FROM barang");
            $stmtCnt->execute();
            $row = $stmtCnt->get_result()->fetch_assoc();
            $kode = 'BRG-' . str_pad((string)$row['n'], 3, '0', STR_PAD_LEFT);
            $statusBrg = ($stok === 0) ? 'habis' : 'tersedia';
            $stmt = $conn->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori, lokasi, stok, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $kode, $nama, $kategori, $lokasi, $stok, $statusBrg, $ket);
            $stmt->execute();
            logAktivitas($user['id'], 'inventaris', "Menambahkan barang $nama ($kode)");
            ok(['id' => $stmt->insert_id]);

        case 'update_barang':
            if (!hasAccess($role, 'inventaris', 'kelola')) forbid('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID barang tidak valid.');
            $nama = trim($_POST['nama_barang'] ?? '');
            if (!$nama) fail('Nama barang wajib diisi.');
            $kategori = trim($_POST['kategori'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            $stok = (int)($_POST['stok'] ?? 0);
            if ($stok < 0) fail('Stok tidak boleh negatif.');
            $status = $_POST['status'] ?? 'tersedia';
            if (!in_array($status, ['tersedia', 'habis', 'rusak'], true)) fail('Status barang tidak valid.');
            $stmt = $conn->prepare("UPDATE barang SET nama_barang = ?, kategori = ?, lokasi = ?, stok = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssisi", $nama, $kategori, $lokasi, $stok, $status, $id);
            $stmt->execute();
            logAktivitas($user['id'], 'inventaris', "Memperbarui data barang #$id");
            ok();

        case 'ajukan_pinjam':
            if (!hasAccess($role, 'inventaris', 'ajukan')) forbid('Akses ditolak. Anda tidak memiliki hak akses untuk mengajukan peminjaman.');

            $rawBarangId = $_POST['barang_id'] ?? '';
            if ($rawBarangId === '' || filter_var($rawBarangId, FILTER_VALIDATE_INT) === false || (int)$rawBarangId < 1) {
                fail('Barang wajib dipilih.');
            }
            $barangId = (int)$rawBarangId;

            $rawJumlah = $_POST['jumlah'] ?? '';
            if ($rawJumlah === '' || filter_var($rawJumlah, FILTER_VALIDATE_INT) === false || (int)$rawJumlah < 1) {
                fail('Jumlah peminjaman harus berupa angka bulat minimal 1.');
            }
            $jumlah = (int)$rawJumlah;

            $tglPinjam = trim($_POST['tanggal_pinjam'] ?? '');
            $dtPinjam = DateTime::createFromFormat('Y-m-d', $tglPinjam);
            if (!$tglPinjam || !$dtPinjam || $dtPinjam->format('Y-m-d') !== $tglPinjam) {
                fail('Format tanggal pinjam tidak valid (gunakan YYYY-MM-DD).');
            }
            $today = date('Y-m-d');
            if ($tglPinjam < $today) {
                fail("Tanggal pinjam tidak boleh kurang dari hari ini ($today).");
            }

            $rencanaRaw = trim($_POST['rencana_kembali'] ?? '');
            $rencana = null;
            if ($rencanaRaw !== '') {
                $dtRencana = DateTime::createFromFormat('Y-m-d', $rencanaRaw);
                if (!$dtRencana || $dtRencana->format('Y-m-d') !== $rencanaRaw) {
                    fail('Format tanggal rencana kembali tidak valid (gunakan YYYY-MM-DD).');
                }
                if ($rencanaRaw < $tglPinjam) {
                    fail('Tanggal rencana kembali tidak boleh lebih awal dari tanggal pinjam.');
                }
                $rencana = $rencanaRaw;
            }

            $keperluan = trim($_POST['keperluan'] ?? '');
            if ($keperluan === '') {
                fail('Keperluan peminjaman wajib diisi.');
            }
            if (mb_strlen($keperluan, 'UTF-8') > 255) {
                fail('Keperluan peminjaman maksimal 255 karakter.');
            }

            $conn->begin_transaction();
            try {
                $stmtBrg = $conn->prepare("SELECT id, nama_barang, stok, status FROM barang WHERE id = ? FOR UPDATE");
                $stmtBrg->bind_param("i", $barangId);
                $stmtBrg->execute();
                $b = $stmtBrg->get_result()->fetch_assoc();

                if (!$b) {
                    $conn->rollback();
                    fail('Barang tidak ditemukan.');
                }
                if ($b['status'] === 'rusak') {
                    $conn->rollback();
                    fail('Barang sedang berstatus rusak dan tidak dapat dipinjam.');
                }
                if ($b['status'] === 'habis' || (int)$b['stok'] <= 0) {
                    $conn->rollback();
                    fail('Barang berstatus habis dan tidak dapat dipinjam.');
                }

                // Hitung ketersediaan = stok - jumlah pengajuan yang masih berjalan
                $stmtPend = $conn->prepare("
                    SELECT COALESCE(SUM(jumlah), 0) AS total_berjalan
                    FROM peminjaman
                    WHERE barang_id = ?
                      AND status IN ('menunggu_approval_atasan', 'diajukan', 'disetujui_atasan', 'diverifikasi')
                    FOR UPDATE
                ");
                $stmtPend->bind_param("i", $barangId);
                $stmtPend->execute();
                $rowPend = $stmtPend->get_result()->fetch_assoc();
                $totalBerjalan = (int)($rowPend['total_berjalan'] ?? 0);
                $ketersediaan = (int)$b['stok'] - $totalBerjalan;

                if ($jumlah > $ketersediaan) {
                    $conn->rollback();
                    fail("Stok barang tidak mencukupi. Stok fisik: {$b['stok']}, sedang diajukan/diproses: {$totalBerjalan}, tersedia: " . max(0, $ketersediaan) . ".");
                }

                $bypassAtasan = isPengajuSetaraAtasan($role, 'inventaris');
                $atasanId = $bypassAtasan ? null : getAtasanId($user['id']);
                $status = $atasanId ? 'menunggu_approval_atasan' : 'diajukan';

                $stmtIns = $conn->prepare("INSERT INTO peminjaman (barang_id, jumlah, peminjam_id, tanggal_pinjam, rencana_kembali, keperluan, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtIns->bind_param("iiissss", $barangId, $jumlah, $user['id'], $tglPinjam, $rencana, $keperluan, $status);
                if (!$stmtIns->execute()) {
                    $conn->rollback();
                    fail('Gagal menyimpan pengajuan peminjaman.');
                }
                $insertId = $stmtIns->insert_id;
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            if ($atasanId) {
                logAktivitas($user['id'], 'inventaris', "Mengajukan peminjaman barang #{$insertId} (menunggu atasan)");
                kirimNotifikasi($atasanId, 'inventaris', $user['name'] . ' mengajukan peminjaman barang, mohon di-approve.', [
                    'kategori' => 'persetujuan',
                    'prioritas' => 'normal',
                    'link_page' => 'inventory',
                    'link_tab' => 'pinjam'
                ]);
            } else {
                $logMsg = $bypassAtasan
                    ? "Mengajukan peminjaman barang #{$insertId} (jalur pengganti: diajukan langsung oleh pimpinan unit)"
                    : "Mengajukan peminjaman barang #{$insertId}";
                logAktivitas($user['id'], 'inventaris', $logMsg);
                notifyRole('staff_inventaris', 'inventaris', $user['name'] . ' mengajukan peminjaman barang, mohon diverifikasi.', null, [
                    'kategori' => 'persetujuan',
                    'prioritas' => 'normal',
                    'link_page' => 'inventory',
                    'link_tab' => 'pinjam'
                ]);
            }
            ok(['id' => $insertId, 'status' => $status]);

        case 'approve_atasan':
            $id = (int)($_POST['id'] ?? 0);
            $setuju = (string)($_POST['setuju'] ?? '1') === '1';
            $catatan = $_POST['catatan_atasan'] ?? '';
            $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
            $alasanPengganti = $_POST['alasan'] ?? '';

            $res = prosesApproveAtasanModul($conn, 'inventaris', $id, $user, $setuju, $catatan, $viaPengganti, $alasanPengganti);
            if (!$res['ok']) {
                fail($res['msg'], $res['code'] ?? 400);
            }
            ok();

        case 'verifikasi_pinjam':
            $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
            $alasanPengganti = trim($_POST['alasan'] ?? '');
            if ($viaPengganti) {
                if (!hasAccess($role, 'inventaris', 'verifikasi_pengganti')) {
                    forbid('Akses ditolak. Anda tidak memiliki hak akses verifikasi pengganti.');
                }
                $lenAlasan = mb_strlen($alasanPengganti, 'UTF-8');
                if ($lenAlasan < 10 || $lenAlasan > 255) {
                    fail('Alasan menggantikan wajib diisi antara 10 hingga 255 karakter.');
                }
            } else {
                if (!hasAccess($role, 'inventaris', 'verifikasi')) {
                    forbid('Akses ditolak. Hanya Staff Inventaris yang dapat memverifikasi peminjaman.');
                }
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID peminjaman tidak valid.');
            $setuju = (int)($_POST['setuju'] ?? 1) === 1;
            $catatan = trim($_POST['catatan'] ?? '');
            
            $status = $setuju ? 'diverifikasi' : 'ditolak_staff';
            if (!$setuju && !$viaPengganti && $catatan === '') fail('Catatan wajib diisi jika menolak.');
            $evalVerif = null;
            
            $conn->begin_transaction();
            try {
                $stmtPj = $conn->prepare("SELECT * FROM peminjaman WHERE id = ? FOR UPDATE");
                $stmtPj->bind_param("i", $id);
                $stmtPj->execute();
                $pj = $stmtPj->get_result()->fetch_assoc();

                if (!$pj) {
                    $conn->rollback();
                    fail('Data peminjaman tidak ditemukan.');
                }
                if ((int)$pj['peminjam_id'] === (int)$user['id']) {
                    $conn->rollback();
                    forbid('Akses ditolak. Pemohon tidak dapat memverifikasi pengajuan peminjaman miliknya sendiri.');
                }
                if (!in_array($pj['status'], ['diajukan', 'disetujui_atasan'], true)) {
                    $conn->rollback();
                    fail("Peminjaman #$id tidak dapat diverifikasi karena status saat ini adalah '{$pj['status']}' (harus 'diajukan' atau 'disetujui_atasan').");
                }

                if ($viaPengganti) {
                    $evalVerif = getDetailVerifikasiPengganti($user, 'inventaris', (int)$pj['peminjam_id']);
                    if (empty($evalVerif['allowed'])) {
                        $conn->rollback();
                        fail($evalVerif['msg'], (int)($evalVerif['code'] ?? 403));
                    }
                    $catatanFinal = $catatan !== '' ? $catatan : $alasanPengganti;
                    $stmt = $conn->prepare("UPDATE peminjaman SET status = ?, verifikator_id = ?, catatan = ?, verifikasi_pengganti_oleh = ?, verifikasi_pengganti_alasan = ?, verifikasi_pengganti_pada = NOW() WHERE id = ?");
                    $stmt->bind_param("sisisi", $status, $user['id'], $catatanFinal, $user['id'], $alasanPengganti, $id);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("UPDATE peminjaman SET status = ?, verifikator_id = ?, catatan = ? WHERE id = ?");
                    $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
                    $stmt->execute();
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            
            if ($viaPengganti) {
                $kata = $setuju ? 'memverifikasi' : 'menolak verifikasi';
                $namaPemohon = $evalVerif['pemohon']['name'] ?? ('User #' . $pj['peminjam_id']);
                logAktivitas($user['id'], 'inventaris', "Verifikasi sebagai PENGGANTI: {$user['name']} ({$user['nama_role']}) {$kata} peminjaman #$id milik {$namaPemohon} menggantikan Staff Inventaris. Alasan: {$alasanPengganti}");
                notifyRole('staff_inventaris', 'inventaris', "Peminjaman #$id milik {$namaPemohon} diambil alih verifikasinya ($status) oleh {$user['name']} ({$user['nama_role']}). Alasan: {$alasanPengganti}", null, [
                    'kategori'  => 'persetujuan',
                    'prioritas' => 'normal',
                    'link_page' => 'inventory',
                    'link_tab'  => 'pinjam',
                ]);
                if ($setuju) {
                    kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda telah diverifikasi oleh pejabat pengganti ({$user['name']}), menunggu approval SPV Inventaris. Alasan: {$alasanPengganti}", [
                        'kategori'  => 'persetujuan',
                        'prioritas' => 'normal',
                        'link_page' => 'inventory',
                        'link_tab'  => 'pinjam',
                    ]);
                } else {
                    $infoTolak = $catatan !== '' ? "$catatan (Alasan pengganti: $alasanPengganti)" : $alasanPengganti;
                    kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda ditolak pada tahap verifikasi oleh pejabat pengganti ({$user['name']}): $infoTolak", [
                        'kategori'  => 'persetujuan',
                        'prioritas' => 'normal',
                        'link_page' => 'inventory',
                        'link_tab'  => 'pinjam',
                    ]);
                }
            } else {
                logAktivitas($user['id'], 'inventaris', "Verifikasi Staff untuk peminjaman #$id: $status");
                if ($setuju) {
                    notifyRole('spv_inventaris', 'inventaris', 'Peminjaman #' . $id . ' telah diverifikasi, menunggu approval Anda.', null, [
                        'kategori' => 'persetujuan',
                        'prioritas' => 'normal',
                        'link_page' => 'inventory',
                        'link_tab' => 'pinjam'
                    ]);
                } else {
                    kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda ditolak oleh Staff Inventaris: $catatan", [
                        'kategori' => 'persetujuan',
                        'prioritas' => 'normal',
                        'link_page' => 'inventory',
                        'link_tab' => 'pinjam'
                    ]);
                }
            }
            ok();

        case 'approve_pinjam':
            $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
            $alasanPengganti = trim($_POST['alasan'] ?? '');
            if ($viaPengganti) {
                if (!hasAccess($role, 'inventaris', 'approve_akhir_pengganti')) {
                    forbid('Akses ditolak. Anda tidak memiliki hak akses persetujuan akhir pengganti.');
                }
                $lenAlasan = mb_strlen($alasanPengganti, 'UTF-8');
                if ($lenAlasan < 10 || $lenAlasan > 255) {
                    fail('Alasan menggantikan wajib diisi antara 10 hingga 255 karakter.');
                }
            } else {
                if (!hasAccess($role, 'inventaris', 'approve')) {
                    forbid('Akses ditolak. Hanya SPV Inventaris yang dapat menyetujui peminjaman.');
                }
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID peminjaman tidak valid.');
            $setuju = (int)($_POST['setuju'] ?? 1) === 1;
            $catatan = trim($_POST['catatan'] ?? '');
            
            $status = $setuju ? 'disetujui' : 'ditolak';
            $evalApp = null;
            
            $conn->begin_transaction();
            try {
                $stmtPj = $conn->prepare("SELECT * FROM peminjaman WHERE id = ? FOR UPDATE");
                $stmtPj->bind_param("i", $id);
                $stmtPj->execute();
                $pj = $stmtPj->get_result()->fetch_assoc();

                if (!$pj) {
                    $conn->rollback();
                    fail('Peminjaman tidak ditemukan.');
                }
                if ((int)$pj['peminjam_id'] === (int)$user['id']) {
                    $conn->rollback();
                    forbid('Akses ditolak. Pemohon tidak dapat menyetujui pengajuan peminjaman miliknya sendiri.');
                }
                if ($pj['status'] !== 'diverifikasi') {
                    $conn->rollback();
                    fail("Peminjaman #$id tidak dapat diproses oleh SPV karena status saat ini adalah '{$pj['status']}' (harus 'diverifikasi').");
                }

                if ($viaPengganti) {
                    $evalApp = getDetailApproveAkhirPengganti($user, 'inventaris', (int)$pj['peminjam_id']);
                    if (empty($evalApp['allowed'])) {
                        $conn->rollback();
                        fail($evalApp['msg'], (int)($evalApp['code'] ?? 403));
                    }
                }

                if ($setuju) {
                    $stmtBrg = $conn->prepare("SELECT id, nama_barang, stok, status FROM barang WHERE id = ? FOR UPDATE");
                    $stmtBrg->bind_param("i", $pj['barang_id']);
                    $stmtBrg->execute();
                    $b = $stmtBrg->get_result()->fetch_assoc();

                    if (!$b) {
                        $conn->rollback();
                        fail('Barang tidak ditemukan.');
                    }
                    if ($b['status'] === 'rusak') {
                        $conn->rollback();
                        fail('Barang berstatus rusak dan tidak dapat disetujui untuk dipinjam.');
                    }
                    $stokSebelum = (int)$b['stok'];
                    $jmlPinjam = (int)$pj['jumlah'];
                    if ($stokSebelum < $jmlPinjam || ($stokSebelum - $jmlPinjam) < 0) {
                        $conn->rollback();
                        fail('Stok barang saat ini tidak cukup untuk disetujui.');
                    }

                    $sisaStok = $stokSebelum - $jmlPinjam;
                    $statusBrg = ($sisaStok === 0) ? 'habis' : 'tersedia';

                    $stmtUpdBrg = $conn->prepare("UPDATE barang SET stok = ?, status = ? WHERE id = ?");
                    $stmtUpdBrg->bind_param("isi", $sisaStok, $statusBrg, $pj['barang_id']);
                    $stmtUpdBrg->execute();

                    $ketTrans = 'Peminjaman disetujui';
                    $stmtTrx = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'keluar', ?, CURDATE(), ?, ?)");
                    $stmtTrx->bind_param("iisi", $pj['barang_id'], $jmlPinjam, $ketTrans, $user['id']);
                    $stmtTrx->execute();

                    $jenisPerubahan = 'peminjaman_keluar';
                    $kondisiSebelum = $b['status'];
                    $kondisiSesudah = $statusBrg;
                    $alasanRiwayat = $catatan !== '' ? "Peminjaman #$id disetujui: $catatan" : "Peminjaman #$id disetujui ({$pj['keperluan']})";
                    $alasanRiwayat = mb_substr($alasanRiwayat, 0, 255);
                    $stmtRiw = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtRiw->bind_param("isiisssis", $pj['barang_id'], $jenisPerubahan, $stokSebelum, $sisaStok, $kondisiSebelum, $kondisiSesudah, $alasanRiwayat, $user['id'], $role);
                    $stmtRiw->execute();
                }

                if ($viaPengganti) {
                    $catatanFinal = $catatan !== '' ? $catatan : $alasanPengganti;
                    $stmtUpdPj = $conn->prepare("UPDATE peminjaman SET status = ?, approval_id = ?, catatan = ?, approve_akhir_pengganti_oleh = ?, approve_akhir_pengganti_alasan = ?, approve_akhir_pengganti_pada = NOW() WHERE id = ?");
                    $stmtUpdPj->bind_param("sisisi", $status, $user['id'], $catatanFinal, $user['id'], $alasanPengganti, $id);
                    $stmtUpdPj->execute();
                } else {
                    $stmtUpdPj = $conn->prepare("UPDATE peminjaman SET status = ?, approval_id = ?, catatan = ? WHERE id = ?");
                    $stmtUpdPj->bind_param("sisi", $status, $user['id'], $catatan, $id);
                    $stmtUpdPj->execute();
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            
            $keputusan = $setuju ? 'disetujui' : 'ditolak';
            if ($viaPengganti) {
                $kata = $setuju ? 'menyetujui' : 'menolak';
                $namaPemohon = $evalApp['pemohon']['name'] ?? ('User #' . $pj['peminjam_id']);
                logAktivitas($user['id'], 'inventaris', "Approve Akhir sebagai PENGGANTI: {$user['name']} ({$user['nama_role']}) {$kata} peminjaman #$id milik {$namaPemohon} menggantikan SPV Inventaris. Alasan: {$alasanPengganti}");
                notifyRole('spv_inventaris', 'inventaris', "Peminjaman #$id milik {$namaPemohon} diambil alih persetujuan akhirnya ($status) oleh {$user['name']} ({$user['nama_role']}). Alasan: {$alasanPengganti}", null, [
                    'kategori'  => 'persetujuan',
                    'prioritas' => 'normal',
                    'link_page' => 'inventory',
                    'link_tab'  => 'pinjam',
                ]);
                kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda telah " . str_replace('_', ' ', $status) . " oleh pejabat pengganti ({$user['name']}). Alasan: {$alasanPengganti}", [
                    'kategori'  => 'persetujuan',
                    'link_page' => 'inventory',
                    'link_tab'  => 'pinjam',
                ]);
            } else {
                kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda telah " . str_replace('_', ' ', $status) . ".", [
                    'kategori' => 'persetujuan',
                    'link_page' => 'inventory',
                    'link_tab' => 'pinjam'
                ]);
                logAktivitas($user['id'], 'inventaris', "Memberikan approval ($keputusan) untuk peminjaman #$id");
            }
            ok();

        case 'batalkan_pinjam':
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID peminjaman tidak valid.');

            $conn->begin_transaction();
            try {
                $stmtPj = $conn->prepare("SELECT p.*, b.nama_barang FROM peminjaman p JOIN barang b ON b.id = p.barang_id WHERE p.id = ? FOR UPDATE");
                $stmtPj->bind_param("i", $id);
                $stmtPj->execute();
                $pj = $stmtPj->get_result()->fetch_assoc();

                if (!$pj) {
                    $conn->rollback();
                    fail('Data peminjaman tidak ditemukan.');
                }
                if ((int)$pj['peminjam_id'] !== (int)$user['id']) {
                    $conn->rollback();
                    forbid('Akses ditolak. Anda hanya dapat membatalkan pengajuan peminjaman milik Anda sendiri.');
                }

                $oldStatus = $pj['status'];
                $cancellable = ['menunggu_approval_atasan', 'diajukan', 'disetujui_atasan', 'diverifikasi'];
                if (!in_array($oldStatus, $cancellable, true)) {
                    $conn->rollback();
                    fail("Pengajuan peminjaman #$id tidak dapat dibatalkan karena status saat ini adalah '$oldStatus'.");
                }

                $newStatus = 'dibatalkan';
                $stmtCancel = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
                $stmtCancel->bind_param("si", $newStatus, $id);
                $stmtCancel->execute();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            logAktivitas($user['id'], 'inventaris', "Membatalkan pengajuan peminjaman #$id ({$pj['nama_barang']}) dari status $oldStatus");

            if ($oldStatus === 'menunggu_approval_atasan') {
                $atasanId = getAtasanId($user['id']);
                if ($atasanId) {
                    kirimNotifikasi($atasanId, 'inventaris', "Pengajuan peminjaman #$id ({$pj['nama_barang']}) telah dibatalkan oleh {$user['name']}.", [
                        'kategori' => 'info',
                        'link_page' => 'inventory',
                        'link_tab' => 'pinjam'
                    ]);
                }
            } elseif ($oldStatus === 'diajukan' || $oldStatus === 'disetujui_atasan') {
                notifyRole('staff_inventaris', 'inventaris', "Pengajuan peminjaman #$id ({$pj['nama_barang']}) telah dibatalkan oleh {$user['name']}.", null, [
                    'kategori' => 'info',
                    'link_page' => 'inventory',
                    'link_tab' => 'pinjam'
                ]);
            } elseif ($oldStatus === 'diverifikasi') {
                notifyRole('spv_inventaris', 'inventaris', "Pengajuan peminjaman #$id ({$pj['nama_barang']}) telah dibatalkan oleh {$user['name']}.", null, [
                    'kategori' => 'info',
                    'link_page' => 'inventory',
                    'link_tab' => 'pinjam'
                ]);
            }
            ok();

        case 'kembalikan':
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID peminjaman tidak valid.');
            $kondisi = $_POST['kondisi_barang'] ?? 'baik';
            if (!in_array($kondisi, ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'], true)) fail('Kondisi barang tidak valid.');
            $catatan = trim($_POST['catatan'] ?? '');
            if ($kondisi !== 'baik' && $catatan === '') fail('Catatan wajib diisi jika kondisi barang bermasalah.');

            $canManageReturn = hasAccess($role, 'inventaris', 'verifikasi') || hasAccess($role, 'inventaris', 'kelola');

            $conn->begin_transaction();
            try {
                $stmtPj = $conn->prepare("SELECT p.*, u1.unit_id AS peminjam_unit_id FROM peminjaman p JOIN users u1 ON u1.id = p.peminjam_id WHERE p.id = ? FOR UPDATE");
                $stmtPj->bind_param("i", $id);
                $stmtPj->execute();
                $pj = $stmtPj->get_result()->fetch_assoc();

                if (!$pj) {
                    $conn->rollback();
                    fail('Data peminjaman tidak ditemukan.');
                }

                $peminjamId = (int)$pj['peminjam_id'];
                $peminjamUnitId = (int)($pj['peminjam_unit_id'] ?? 0);
                $uid = (int)$user['id'];
                $userUnitId = (int)($user['unit_id'] ?? 0);
                $atasanSayaId = (int)(getAtasanId($uid) ?: 0);
                $isAtasanSeunit = ($atasanSayaId > 0 && $userUnitId > 0 && $peminjamId === $atasanSayaId && $peminjamUnitId === $userUnitId);

                if (!$canManageReturn && $peminjamId !== $uid && !$isAtasanSeunit) {
                    $conn->rollback();
                    forbid('Akses ditolak. Anda tidak berhak memproses pengembalian peminjaman ini.');
                }

                if ($pj['status'] !== 'disetujui') {
                    $conn->rollback();
                    fail('Peminjaman tidak valid untuk pengembalian (status harus disetujui).');
                }

                $status_pemeriksaan = ($kondisi === 'baik') ? 'selesai' : 'menunggu_spv';

                $stmt = $conn->prepare("INSERT INTO pengembalian (peminjaman_id, tanggal_kembali, kondisi_barang, petugas_id, catatan, status_pemeriksaan) VALUES (?, CURDATE(), ?, ?, ?, ?)");
                $stmt->bind_param("isiss", $id, $kondisi, $user['id'], $catatan, $status_pemeriksaan);
                $stmt->execute();
                $pengembalianId = $stmt->insert_id;

                $statusKembali = 'dikembalikan';
                $stmtUpdPj = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
                $stmtUpdPj->bind_param("si", $statusKembali, $id);
                $stmtUpdPj->execute();

                if ($kondisi === 'baik') {
                    $stmtBrg = $conn->prepare("SELECT id, stok, status FROM barang WHERE id = ? FOR UPDATE");
                    $stmtBrg->bind_param("i", $pj['barang_id']);
                    $stmtBrg->execute();
                    $b = $stmtBrg->get_result()->fetch_assoc();

                    $stokSebelum = (int)$b['stok'];
                    $stokSesudah = $stokSebelum + (int)$pj['jumlah'];
                    $statusBrgBaru = ($b['status'] === 'habis' && $stokSesudah > 0) ? 'tersedia' : $b['status'];

                    $stmtUpdBrg = $conn->prepare("UPDATE barang SET stok = ?, status = ? WHERE id = ?");
                    $stmtUpdBrg->bind_param("isi", $stokSesudah, $statusBrgBaru, $pj['barang_id']);
                    $stmtUpdBrg->execute();

                    $ketTrans = 'Pengembalian barang';
                    $stmt2 = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'masuk', ?, CURDATE(), ?, ?)");
                    $stmt2->bind_param("iisi", $pj['barang_id'], $pj['jumlah'], $ketTrans, $user['id']);
                    $stmt2->execute();

                    $jenisPerubahan = 'pengembalian_barang';
                    $alasanRiw = $catatan !== '' ? "Pengembalian peminjaman #$id: $catatan" : "Pengembalian peminjaman #$id (kondisi baik)";
                    $alasanRiw = mb_substr($alasanRiw, 0, 255);
                    $stmtRiw = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtRiw->bind_param("isiisssis", $pj['barang_id'], $jenisPerubahan, $stokSebelum, $stokSesudah, $b['status'], $kondisi, $alasanRiw, $user['id'], $role);
                    $stmtRiw->execute();
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            if ($kondisi !== 'baik') {
                notifyRole('spv_inventaris', 'inventaris', 'Ada pengembalian barang (' . $kondisi . ') yang menunggu pemeriksaan Anda.', null, [
                    'kategori' => 'persetujuan',
                    'link_page' => 'inventory',
                    'link_tab' => 'kembali'
                ]);
            }
            logAktivitas($user['id'], 'inventaris', "Konfirmasi pengembalian peminjaman #$id ($kondisi)");
            ok(['pengembalian_id' => $pengembalianId]);

        case 'periksa_pengembalian':
            if (!hasAccess($role, 'inventaris', 'kelola')) forbid('Akses ditolak. Hanya SPV/Pengelola yang dapat memeriksa.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) fail('ID pengembalian tidak valid.');
            $tindakan = $_POST['tindakan'] ?? '';
            if (!in_array($tindakan, ['tetap_digunakan', 'perlu_perbaikan', 'tidak_layak', 'hilang'], true)) fail('Tindakan tidak valid.');
            $catatan_spv = trim($_POST['catatan_spv'] ?? '');
            if ($catatan_spv === '') fail('Alasan / keterangan pemeriksaan wajib diisi.');

            $conn->begin_transaction();
            try {
                $stmtPeng = $conn->prepare("SELECT p.*, pj.barang_id, pj.jumlah FROM pengembalian p JOIN peminjaman pj ON pj.id = p.peminjaman_id WHERE p.id = ? FOR UPDATE");
                $stmtPeng->bind_param("i", $id);
                $stmtPeng->execute();
                $peng = $stmtPeng->get_result()->fetch_assoc();

                if (!$peng || $peng['status_pemeriksaan'] !== 'menunggu_spv') {
                    $conn->rollback();
                    fail('Data pengembalian tidak valid atau sudah selesai diperiksa.');
                }

                $stmtBrg = $conn->prepare("SELECT * FROM barang WHERE id = ? FOR UPDATE");
                $stmtBrg->bind_param("i", $peng['barang_id']);
                $stmtBrg->execute();
                $b = $stmtBrg->get_result()->fetch_assoc();
                if (!$b) {
                    $conn->rollback();
                    fail('Data barang tidak ditemukan.');
                }

                $j_s = (int)$b['stok'];
                if ($tindakan === 'tetap_digunakan' || $tindakan === 'perlu_perbaikan') {
                    $j_n = $j_s + (int)$peng['jumlah'];
                    $statusBrgBaru = ($b['status'] === 'habis' && $j_n > 0) ? 'tersedia' : $b['status'];

                    $stmtUpdBrg = $conn->prepare("UPDATE barang SET stok = ?, status = ? WHERE id = ?");
                    $stmtUpdBrg->bind_param("isi", $j_n, $statusBrgBaru, $peng['barang_id']);
                    $stmtUpdBrg->execute();

                    $ketTrans = 'Pemeriksaan SPV: Dikembalikan ke stok';
                    $stmt2 = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'masuk', ?, CURDATE(), ?, ?)");
                    $stmt2->bind_param("iisi", $peng['barang_id'], $peng['jumlah'], $ketTrans, $user['id']);
                    $stmt2->execute();
                    
                    $stmt3 = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, 'penyesuaian_stok_masuk', ?, ?, ?, ?, ?, ?, ?)");
                    $stmt3->bind_param("iiisssis", $peng['barang_id'], $j_s, $j_n, $peng['kondisi_barang'], $tindakan, $catatan_spv, $user['id'], $role);
                    $stmt3->execute();
                } else {
                    $j_n = $j_s;
                    $stmt3 = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, 'penghapusan_stok', ?, ?, ?, ?, ?, ?, ?)");
                    $stmt3->bind_param("iiisssis", $peng['barang_id'], $j_s, $j_n, $peng['kondisi_barang'], $tindakan, $catatan_spv, $user['id'], $role);
                    $stmt3->execute();
                }

                $stmt = $conn->prepare("UPDATE pengembalian SET status_pemeriksaan = 'selesai', tindakan_spv = ?, catatan_spv = ? WHERE id = ?");
                $stmt->bind_param("ssi", $tindakan, $catatan_spv, $id);
                $stmt->execute();
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            
            logAktivitas($user['id'], 'inventaris', "Pemeriksaan SPV untuk pengembalian #$id: $tindakan");
            ok();

        default:
            fail('Aksi tidak dikenal.');
    }
} catch (Throwable $e) {
    error_log('System Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Terjadi kesalahan pada server.']);
    exit();
}
