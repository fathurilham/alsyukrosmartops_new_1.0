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

if (!hasAccess($role, 'maintenance', 'lihat')) forbid('Anda tidak memiliki hak akses ke modul Maintenance.');

switch ($action) {

    case 'list_fasilitas':
        ok($conn->query("SELECT * FROM fasilitas ORDER BY id")->fetch_all(MYSQLI_ASSOC));

    case 'list_pengajuan':
        $uid = (int)$user['id'];
        $unitId = (int)($user['unit_id'] ?? 0);
        $atasanSayaId = (int)(getAtasanId($uid) ?: 0);
        $canSeeAllSubmitted = hasAccess($role, 'maintenance', 'kelola')
            || hasAccess($role, 'maintenance', 'approve')
            || hasAccess($role, 'maintenance', 'verifikasi')
            || hasAccess($role, 'maintenance', 'verifikasi_pengganti')
            || hasAccess($role, 'maintenance', 'approve_akhir_pengganti')
            || hasAccess($role, 'maintenance', 'monitoring')
            || $role === 'admin'
            || $role === 'manajer_ga';
        $canApproveAtasan = hasAccess($role, 'maintenance', 'approve_atasan');
        $canApprovePengganti = hasAccess($role, 'maintenance', 'approve_pengganti');

        $sql = "SELECT pm.*, f.nama_fasilitas, f.lokasi, u1.name AS pemohon_nama, u1.unit_id AS pemohon_unit_id,
                       u2.name AS tl_nama, u3.name AS spv_nama, u5.name AS atasan_nama, u6.name AS pengganti_nama,
                       u7.name AS verifikasi_pengganti_nama, u8.name AS approve_akhir_pengganti_nama,
                       prm.teknisi_id, u4.name AS teknisi_nama, prm.hasil_pekerjaan, prm.foto_pekerjaan, prm.tanggal_selesai
                FROM pengajuan_maintenance pm
                LEFT JOIN fasilitas f ON f.id = pm.fasilitas_id
                JOIN users u1 ON u1.id = pm.pemohon_id
                LEFT JOIN users u2 ON u2.id = pm.tl_verifikasi_id
                LEFT JOIN users u3 ON u3.id = pm.spv_approval_id
                LEFT JOIN proses_maintenance prm ON prm.pengajuan_id = pm.id
                LEFT JOIN users u4 ON u4.id = prm.teknisi_id
                LEFT JOIN users u5 ON u5.id = pm.atasan_id
                LEFT JOIN users u6 ON u6.id = pm.approval_pengganti_oleh
                LEFT JOIN users u7 ON u7.id = pm.verifikasi_pengganti_oleh
                LEFT JOIN users u8 ON u8.id = pm.approve_akhir_pengganti_oleh
                WHERE (
                    pm.pemohon_id = ?
                    OR (? > 0 AND ? > 0 AND pm.pemohon_id = ? AND u1.unit_id = ?)
                    OR (? = 1 AND (
                        u1.unit_id = ?
                        OR EXISTS (
                            SELECT 1 FROM karyawan k1
                            JOIN karyawan k2 ON k2.id = k1.atasan_id
                            WHERE k1.user_id = pm.pemohon_id AND k2.user_id = ?
                        )
                    ))
                    OR (? = 1 AND pm.status != 'menunggu_approval_atasan')
                    OR (? = 1 AND pm.status = 'menunggu_approval_atasan')
                    OR pm.approval_pengganti_oleh = ?
                    OR pm.verifikasi_pengganti_oleh = ?
                    OR pm.approve_akhir_pengganti_oleh = ?
                )
                ORDER BY pm.id DESC";
        $flagAtasan = $canApproveAtasan ? 1 : 0;
        $flagAll = $canSeeAllSubmitted ? 1 : 0;
        $flagPengganti = $canApprovePengganti ? 1 : 0;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiiiiiiiii", $uid, $atasanSayaId, $unitId, $atasanSayaId, $unitId, $flagAtasan, $unitId, $uid, $flagAll, $flagPengganti, $uid, $uid, $uid);
        $stmt->execute();
        $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $rows = [];
        foreach ($rawRows as $row) {
            $pemohonId = (int)$row['pemohon_id'];
            $pemohonUnitId = (int)($row['pemohon_unit_id'] ?? 0);
            $expectedAtasanId = (int)getAtasanId($pemohonId);
            $isAtasanSeunitRow = ($atasanSayaId > 0 && $unitId > 0 && $pemohonId === $atasanSayaId && $pemohonUnitId === $unitId);
            $isApproveAtasanRow = ($canApproveAtasan && ($pemohonUnitId === $unitId || $expectedAtasanId === $uid));
            $isOwnOrHistoryRow = (
                $pemohonId === $uid
                || (int)($row['approval_pengganti_oleh'] ?? 0) === $uid
                || (int)($row['verifikasi_pengganti_oleh'] ?? 0) === $uid
                || (int)($row['approve_akhir_pengganti_oleh'] ?? 0) === $uid
                || $isAtasanSeunitRow
            );
            $isSubmittedRow = ($canSeeAllSubmitted && $row['status'] !== 'menunggu_approval_atasan');
            $canActPengganti = ($row['status'] === 'menunggu_approval_atasan'
                && $canApprovePengganti
                && bolehApprovePengganti($user, $pemohonId));

            if (!$isOwnOrHistoryRow && !$isApproveAtasanRow && !$isSubmittedRow && !$canActPengganti) {
                continue;
            }

            $row['can_approve_atasan'] = ($row['status'] === 'menunggu_approval_atasan'
                && $canApproveAtasan
                && $expectedAtasanId === $uid);
            $row['can_approve_pengganti'] = $canActPengganti;
            $row['can_verifikasi'] = (in_array($row['status'], ['diajukan', 'disetujui_atasan'], true)
                && hasAccess($role, 'maintenance', 'verifikasi')
                && $pemohonId !== $uid);
            $row['can_verifikasi_pengganti'] = (in_array($row['status'], ['diajukan', 'disetujui_atasan'], true)
                && bolehVerifikasiPengganti($user, 'maintenance', $pemohonId));
            $row['can_approve_akhir'] = ($row['status'] === 'diverifikasi'
                && hasAccess($role, 'maintenance', 'approve')
                && $pemohonId !== $uid);
            $row['can_approve_akhir_pengganti'] = ($row['status'] === 'diverifikasi'
                && bolehApproveAkhirPengganti($user, 'maintenance', $pemohonId));
            $rows[] = $row;
        }
        ok($rows);

    case 'ajukan':
        if (!hasAccess($role, 'maintenance', 'ajukan')) fail('Akses ditolak.');
        $deskripsi = trim($_POST['deskripsi_kerusakan'] ?? '');
        if (!$deskripsi) fail('Deskripsi kerusakan atau kendala wajib diisi.');
        $fasilitasId = !empty($_POST['fasilitas_id']) ? (int)$_POST['fasilitas_id'] : null;
        $prioritas = (isset($_POST['prioritas']) && $_POST['prioritas'] === 'darurat') ? 'darurat' : 'normal';

        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            require_once '../config/upload.php';
            try {
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif'
                ];
                $dir = __DIR__ . '/../uploads/maintenance/';
                $absPath = secureUpload($_FILES['foto'], $allowed, $dir);
                $fotoPath = 'uploads/maintenance/' . basename($absPath);
            } catch (Exception $e) {
                fail($e->getMessage());
            }
        }
        if (!$fotoPath) {
            fail('Foto kerusakan atau kendala wajib diunggah.');
        }

        $prefix = ($prioritas === 'darurat') ? '[DARURAT] ' : '';
        $bypassAtasan = isPengajuSetaraAtasan($role, 'maintenance');
        $atasanId = $bypassAtasan ? null : getAtasanId($user['id']);
        if ($atasanId) {
            $stmt = $conn->prepare("INSERT INTO pengajuan_maintenance (fasilitas_id, pemohon_id, deskripsi_kerusakan, foto, prioritas, status, tanggal_pengajuan) VALUES (?,?,?,?,?, 'menunggu_approval_atasan', CURDATE())");
            $stmt->bind_param("iisss", $fasilitasId, $user['id'], $deskripsi, $fotoPath, $prioritas);
            $stmt->execute();
            $insertId = $stmt->insert_id;
            logAktivitas($user['id'], 'maintenance', "Mengajukan perbaikan/perawatan fasilitas #{$insertId} (menunggu atasan)");
            kirimNotifikasi($atasanId, 'maintenance', $prefix . $user['name'] . ' mengajukan perbaikan/perawatan fasilitas, mohon diperiksa.', [
                'kategori' => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab' => 'pengajuan'
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO pengajuan_maintenance (fasilitas_id, pemohon_id, deskripsi_kerusakan, foto, prioritas, status, tanggal_pengajuan) VALUES (?,?,?,?,?, 'diajukan', CURDATE())");
            $stmt->bind_param("iisss", $fasilitasId, $user['id'], $deskripsi, $fotoPath, $prioritas);
            $stmt->execute();
            $insertId = $stmt->insert_id;
            $logMsg = $bypassAtasan
                ? "Mengajukan perbaikan/perawatan fasilitas #{$insertId} (jalur pengganti: diajukan langsung oleh pimpinan unit)"
                : "Mengajukan perbaikan/perawatan fasilitas #{$insertId}";
            logAktivitas($user['id'], 'maintenance', $logMsg);
            notifyRole('team_leader', 'maintenance', $prefix . $user['name'] . ' mengajukan perbaikan/perawatan fasilitas, mohon diperiksa.', null, [
                'kategori' => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab' => 'pengajuan'
            ]);
        }
        ok(['id' => $insertId, 'status' => $atasanId ? 'menunggu_approval_atasan' : 'diajukan']);

    case 'approve_atasan':
        $id = (int)($_POST['id'] ?? 0);
        $setuju = (string)($_POST['setuju'] ?? '1') === '1';
        $catatan = $_POST['catatan_atasan'] ?? '';
        $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
        $alasanPengganti = $_POST['alasan'] ?? '';

        $res = prosesApproveAtasanModul($conn, 'maintenance', $id, $user, $setuju, $catatan, $viaPengganti, $alasanPengganti);
        if (!$res['ok']) {
            fail($res['msg'], $res['code'] ?? 400);
        }
        ok();

    case 'verifikasi':
        $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
        $alasanPengganti = trim($_POST['alasan'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        if ($viaPengganti) {
            if (!hasAccess($role, 'maintenance', 'verifikasi_pengganti')) {
                forbid('Akses ditolak. Anda tidak memiliki hak akses verifikasi pengganti.');
            }
            $lenAlasan = mb_strlen($alasanPengganti, 'UTF-8');
            if ($lenAlasan < 10 || $lenAlasan > 255) {
                fail('Alasan menggantikan wajib diisi antara 10 hingga 255 karakter.');
            }
        } else {
            if (!hasAccess($role, 'maintenance', 'verifikasi')) forbid('Akses ditolak.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID pengajuan tidak valid.');
        $valid = ($_POST['valid'] ?? '1') === '1';
        $status = $valid ? 'diverifikasi' : 'ditolak';
        $evalVerif = null;

        $conn->begin_transaction();
        try {
            $stmtPm = $conn->prepare("SELECT * FROM pengajuan_maintenance WHERE id = ? FOR UPDATE");
            $stmtPm->bind_param("i", $id);
            $stmtPm->execute();
            $pm = $stmtPm->get_result()->fetch_assoc();

            if (!$pm) {
                $conn->rollback();
                fail('Data pengajuan maintenance tidak ditemukan.');
            }
            if ((int)$pm['pemohon_id'] === (int)$user['id']) {
                $conn->rollback();
                forbid('Akses ditolak. Pemohon tidak dapat memverifikasi pengajuan maintenance miliknya sendiri.');
            }
            if (!in_array($pm['status'], ['diajukan', 'disetujui_atasan'], true)) {
                $conn->rollback();
                fail("Pengajuan maintenance #$id tidak dapat diverifikasi karena status saat ini adalah '{$pm['status']}'.");
            }

            if ($viaPengganti) {
                $evalVerif = getDetailVerifikasiPengganti($user, 'maintenance', (int)$pm['pemohon_id']);
                if (empty($evalVerif['allowed'])) {
                    $conn->rollback();
                    fail($evalVerif['msg'], (int)($evalVerif['code'] ?? 403));
                }
                $catatanFinal = $catatan !== '' ? $catatan : $alasanPengganti;
                $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, tl_verifikasi_id=?, catatan=?, verifikasi_pengganti_oleh=?, verifikasi_pengganti_alasan=?, verifikasi_pengganti_pada=NOW() WHERE id=?");
                $stmt->bind_param("sisisi", $status, $user['id'], $catatanFinal, $user['id'], $alasanPengganti, $id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, tl_verifikasi_id=? WHERE id=?");
                $stmt->bind_param("sii", $status, $user['id'], $id);
                $stmt->execute();
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        $prioritas = $pm['prioritas'] ?? 'normal';
        $prefix = ($prioritas === 'darurat') ? '[DARURAT] ' : '';

        if ($viaPengganti) {
            $kata = $valid ? 'memverifikasi' : 'menolak verifikasi';
            $namaPemohon = $evalVerif['pemohon']['name'] ?? ('User #' . $pm['pemohon_id']);
            logAktivitas($user['id'], 'maintenance', "Verifikasi sebagai PENGGANTI: {$user['name']} ({$user['nama_role']}) {$kata} maintenance #$id milik {$namaPemohon} menggantikan Team Leader. Alasan: {$alasanPengganti}");
            notifyRole('team_leader', 'maintenance', $prefix . "Pengajuan maintenance #$id milik {$namaPemohon} diambil alih verifikasinya ($status) oleh {$user['name']} ({$user['nama_role']}). Alasan: {$alasanPengganti}", null, [
                'kategori'  => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab'  => 'pengajuan',
            ]);
            if ($valid) {
                kirimNotifikasi($pm['pemohon_id'], 'maintenance', $prefix . "Pengajuan maintenance #$id Anda telah diverifikasi oleh pejabat pengganti ({$user['name']}), menunggu approval SPV Sapras. Alasan: {$alasanPengganti}", [
                    'kategori'  => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab'  => 'pengajuan',
                ]);
            } else {
                kirimNotifikasi($pm['pemohon_id'], 'maintenance', $prefix . "Pengajuan maintenance #$id Anda ditolak pada tahap verifikasi oleh pejabat pengganti ({$user['name']}). Alasan: {$alasanPengganti}", [
                    'kategori'  => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab'  => 'pengajuan',
                ]);
            }
        } else {
            logAktivitas($user['id'], 'maintenance', "Verifikasi kondisi fasilitas pengajuan #$id: $status");
            if ($valid) {
                notifyRole('spv_sapras', 'maintenance', $prefix . "Pengajuan maintenance #$id telah diverifikasi TL, menunggu approval.", null, [
                    'kategori' => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab' => 'pengajuan'
                ]);
            }
        }
        ok();

    case 'approve':
        $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
        $alasanPengganti = trim($_POST['alasan'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        if ($viaPengganti) {
            if (!hasAccess($role, 'maintenance', 'approve_akhir_pengganti')) {
                forbid('Akses ditolak. Anda tidak memiliki hak akses persetujuan akhir pengganti.');
            }
            $lenAlasan = mb_strlen($alasanPengganti, 'UTF-8');
            if ($lenAlasan < 10 || $lenAlasan > 255) {
                fail('Alasan menggantikan wajib diisi antara 10 hingga 255 karakter.');
            }
        } else {
            if (!hasAccess($role, 'maintenance', 'approve')) forbid('Akses ditolak.');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID pengajuan tidak valid.');
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $status = $setuju ? 'disetujui' : 'ditolak';
        $evalApp = null;

        $conn->begin_transaction();
        try {
            $stmtPm = $conn->prepare("SELECT * FROM pengajuan_maintenance WHERE id = ? FOR UPDATE");
            $stmtPm->bind_param("i", $id);
            $stmtPm->execute();
            $pm = $stmtPm->get_result()->fetch_assoc();

            if (!$pm) {
                $conn->rollback();
                fail('Data pengajuan maintenance tidak ditemukan.');
            }
            if ((int)$pm['pemohon_id'] === (int)$user['id']) {
                $conn->rollback();
                forbid('Akses ditolak. Pemohon tidak dapat menyetujui pengajuan maintenance miliknya sendiri.');
            }
            if ($pm['status'] !== 'diverifikasi') {
                $conn->rollback();
                fail("Pengajuan maintenance #$id tidak dapat disetujui karena status saat ini adalah '{$pm['status']}' (harus 'diverifikasi').");
            }

            if ($viaPengganti) {
                $evalApp = getDetailApproveAkhirPengganti($user, 'maintenance', (int)$pm['pemohon_id']);
                if (empty($evalApp['allowed'])) {
                    $conn->rollback();
                    fail($evalApp['msg'], (int)($evalApp['code'] ?? 403));
                }
                $catatanFinal = $catatan !== '' ? $catatan : $alasanPengganti;
                $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, spv_approval_id=?, catatan=?, approve_akhir_pengganti_oleh=?, approve_akhir_pengganti_alasan=?, approve_akhir_pengganti_pada=NOW() WHERE id=?");
                $stmt->bind_param("sisisi", $status, $user['id'], $catatanFinal, $user['id'], $alasanPengganti, $id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, spv_approval_id=? WHERE id=?");
                $stmt->bind_param("sii", $status, $user['id'], $id);
                $stmt->execute();
            }

            if ($setuju) {
                $stmt2 = $conn->prepare("INSERT INTO proses_maintenance (pengajuan_id, tanggal_mulai) VALUES (?, CURDATE())");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        $prioritas = $pm['prioritas'] ?? 'normal';
        $prefix = ($prioritas === 'darurat') ? '[DARURAT] ' : '';

        if ($setuju) {
            notifyRole('engineering', 'maintenance', $prefix . "Pengajuan maintenance #$id disetujui, silakan dikerjakan.", null, [
                'kategori' => 'tugas',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab' => 'proses'
            ]);
        }
        if ($viaPengganti) {
            $kata = $setuju ? 'menyetujui' : 'menolak';
            $namaPemohon = $evalApp['pemohon']['name'] ?? ('User #' . $pm['pemohon_id']);
            logAktivitas($user['id'], 'maintenance', "Approve Akhir sebagai PENGGANTI: {$user['name']} ({$user['nama_role']}) {$kata} maintenance #$id milik {$namaPemohon} menggantikan SPV Sapras. Alasan: {$alasanPengganti}");
            notifyRole('spv_sapras', 'maintenance', $prefix . "Pengajuan maintenance #$id milik {$namaPemohon} diambil alih persetujuan akhirnya ($status) oleh {$user['name']} ({$user['nama_role']}). Alasan: {$alasanPengganti}", null, [
                'kategori'  => 'persetujuan',
                'prioritas' => $prioritas,
                'link_page' => 'maintenance',
                'link_tab'  => 'pengajuan',
            ]);
            if ($pm) {
                kirimNotifikasi($pm['pemohon_id'], 'maintenance', $prefix . "Pengajuan maintenance Anda #$id telah $status oleh pejabat pengganti ({$user['name']}). Alasan: {$alasanPengganti}", [
                    'kategori'  => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab'  => 'pengajuan',
                ]);
            }
        } else {
            if ($pm) {
                kirimNotifikasi($pm['pemohon_id'], 'maintenance', $prefix . "Pengajuan maintenance Anda #$id: $status.", [
                    'kategori' => 'persetujuan',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab' => 'pengajuan'
                ]);
            }
            logAktivitas($user['id'], 'maintenance', "Approval pengajuan maintenance #$id: $status");
        }
        ok();

    case 'proses':
        // Engineering: mulai/selesaikan pekerjaan
        if (!hasAccess($role, 'maintenance', 'kelola')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID pengajuan maintenance tidak valid.');
        $hasil = trim($_POST['hasil_pekerjaan'] ?? '');
        $selesai = ($_POST['selesai'] ?? '0') === '1';

        $stmtCheck = $conn->prepare("SELECT id, status, pemohon_id, prioritas FROM pengajuan_maintenance WHERE id = ?");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $pmCheck = $stmtCheck->get_result()->fetch_assoc();
        if (!$pmCheck) {
            fail('Data pengajuan maintenance tidak ditemukan.');
        }
        if (!in_array($pmCheck['status'], ['disetujui', 'dikerjakan'], true)) {
            fail("Pengajuan maintenance #$id tidak dapat diproses karena status saat ini adalah '{$pmCheck['status']}' (harus 'disetujui' atau 'dikerjakan').");
        }

        $fotoPath = null;
        if (!empty($_FILES['foto_pekerjaan']['tmp_name'])) {
            require_once '../config/upload.php';
            try {
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif'
                ];
                $dir = __DIR__ . '/../uploads/maintenance/';
                $absPath = secureUpload($_FILES['foto_pekerjaan'], $allowed, $dir);
                $fotoPath = 'uploads/maintenance/' . basename($absPath);
            } catch (Exception $e) {
                fail($e->getMessage());
            }
        }

        // Cek jika menandai selesai, pastikan foto bukti pekerjaan ada (baik baru diunggah atau sebelumnya sudah ada)
        $curr = $conn->query("SELECT foto_pekerjaan FROM proses_maintenance WHERE pengajuan_id=$id")->fetch_assoc();
        if ($selesai && !$fotoPath && empty($curr['foto_pekerjaan'])) {
            fail('Silakan unggah foto bukti pekerjaan yang telah selesai.');
        }

        if ($fotoPath) {
            $stmt = $conn->prepare("UPDATE proses_maintenance SET teknisi_id=?, hasil_pekerjaan=?, foto_pekerjaan=?" . ($selesai ? ", tanggal_selesai=CURDATE()" : "") . " WHERE pengajuan_id=?");
            $stmt->bind_param("issi", $user['id'], $hasil, $fotoPath, $id);
        } else {
            $stmt = $conn->prepare("UPDATE proses_maintenance SET teknisi_id=?, hasil_pekerjaan=?" . ($selesai ? ", tanggal_selesai=CURDATE()" : "") . " WHERE pengajuan_id=?");
            $stmt->bind_param("isi", $user['id'], $hasil, $id);
        }
        $stmt->execute();

        if ($selesai) {
            $conn->query("UPDATE pengajuan_maintenance SET status='selesai' WHERE id=$id");
            $pm = $conn->query("SELECT pemohon_id, prioritas FROM pengajuan_maintenance WHERE id=$id")->fetch_assoc();
            $prioritas = $pm['prioritas'] ?? 'normal';
            $prefix = ($prioritas === 'darurat') ? '[DARURAT] ' : '';
            if ($pm) {
                kirimNotifikasi($pm['pemohon_id'], 'maintenance', $prefix . "Maintenance #$id telah selesai dikerjakan.", [
                    'kategori' => 'info',
                    'prioritas' => $prioritas,
                    'link_page' => 'maintenance',
                    'link_tab' => 'pengajuan'
                ]);
            }
        } else {
            $conn->query("UPDATE pengajuan_maintenance SET status='dikerjakan' WHERE id=$id");
        }
        logAktivitas($user['id'], 'maintenance', "Update proses maintenance #$id" . ($selesai ? ' (selesai)' : ''));
        ok();

    default:
        fail('Aksi tidak dikenal.');
}
