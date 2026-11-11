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

if (!hasAccess($role, 'event', 'lihat')) forbid('Anda tidak memiliki hak akses ke modul Event.');

switch ($action) {

    case 'list':
        $uid = (int)$user['id'];
        $unitId = (int)($user['unit_id'] ?? 0);
        $atasanSayaId = (int)(getAtasanId($uid) ?: 0);
        $canSeeAllSubmitted = hasAccess($role, 'event', 'kelola')
            || hasAccess($role, 'event', 'approve')
            || hasAccess($role, 'event', 'verifikasi')
            || hasAccess($role, 'event', 'monitoring')
            || $role === 'admin'
            || $role === 'manajer_ga';
        $canApproveAtasan = hasAccess($role, 'event', 'approve_atasan');
        $canApprovePengganti = hasAccess($role, 'event', 'approve_pengganti');

        $sql = "SELECT e.*, u1.name AS pemohon_nama, u1.unit_id AS pemohon_unit_id,
                       u2.name AS verifikator_nama, u3.name AS approval_nama, u4.name AS atasan_nama,
                       u5.name AS pengganti_nama,
                       j.tanggal, j.jam_mulai, j.jam_selesai, j.lokasi
                FROM event e
                JOIN users u1 ON u1.id = e.pemohon_id
                LEFT JOIN users u2 ON u2.id = e.verifikator_id
                LEFT JOIN users u3 ON u3.id = e.approval_id
                LEFT JOIN users u4 ON u4.id = e.atasan_id
                LEFT JOIN users u5 ON u5.id = e.approval_pengganti_oleh
                LEFT JOIN jadwal_event j ON j.event_id = e.id
                WHERE (
                    e.pemohon_id = ?
                    OR (? > 0 AND ? > 0 AND e.pemohon_id = ? AND u1.unit_id = ?)
                    OR (? = 1 AND (
                        u1.unit_id = ?
                        OR EXISTS (
                            SELECT 1 FROM karyawan k1
                            JOIN karyawan k2 ON k2.id = k1.atasan_id
                            WHERE k1.user_id = e.pemohon_id AND k2.user_id = ?
                        )
                    ))
                    OR (? = 1 AND e.status != 'menunggu_approval_atasan')
                    OR (? = 1 AND e.status = 'menunggu_approval_atasan')
                    OR e.approval_pengganti_oleh = ?
                )
                ORDER BY e.id DESC";
        $flagAtasan = $canApproveAtasan ? 1 : 0;
        $flagAll = $canSeeAllSubmitted ? 1 : 0;
        $flagPengganti = $canApprovePengganti ? 1 : 0;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiiiiiii", $uid, $atasanSayaId, $unitId, $atasanSayaId, $unitId, $flagAtasan, $unitId, $uid, $flagAll, $flagPengganti, $uid);
        $stmt->execute();
        $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $rows = [];
        foreach ($rawRows as $row) {
            $pemohonId = (int)$row['pemohon_id'];
            $pemohonUnitId = (int)($row['pemohon_unit_id'] ?? 0);
            $expectedAtasanId = (int)getAtasanId($pemohonId);
            $isAtasanSeunitRow = ($atasanSayaId > 0 && $unitId > 0 && $pemohonId === $atasanSayaId && $pemohonUnitId === $unitId);
            $isApproveAtasanRow = ($canApproveAtasan && ($pemohonUnitId === $unitId || $expectedAtasanId === $uid));
            $isOwnOrHistoryRow = ($pemohonId === $uid || (int)($row['approval_pengganti_oleh'] ?? 0) === $uid || $isAtasanSeunitRow);
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
            $rows[] = $row;
        }
        ok($rows);

    case 'ajukan':
        if (!hasAccess($role, 'event', 'ajukan')) fail('Akses ditolak.');
        $nama = trim($_POST['nama_event'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? '';
        $jamMulai = $_POST['jam_mulai'] ?? '08:00';
        $jamSelesai = $_POST['jam_selesai'] ?? '10:00';
        $lokasi = trim($_POST['lokasi'] ?? '');
        if (!$nama || !$tanggal) fail('Nama event dan tanggal wajib diisi.');

        // Cegah benturan jadwal pada lokasi & tanggal yang sama (abaikan yang berstatus ditolak, ditolak_atasan, atau batal)
        $stmt = $conn->prepare("SELECT e.nama_event FROM jadwal_event j JOIN event e ON e.id=j.event_id
                                 WHERE j.tanggal=? AND j.lokasi=? AND e.status NOT IN ('ditolak', 'ditolak_atasan', 'batal')
                                 AND NOT (j.jam_selesai <= ? OR j.jam_mulai >= ?)");
        $stmt->bind_param("ssss", $tanggal, $lokasi, $jamMulai, $jamSelesai);
        $stmt->execute();
        if ($bentrok = $stmt->get_result()->fetch_assoc()) {
            fail('Jadwal bentrok dengan event "' . $bentrok['nama_event'] . '" pada lokasi & waktu yang sama.');
        }

        $bypassAtasan = isPengajuSetaraAtasan($role, 'event');
        $atasanId = $bypassAtasan ? null : getAtasanId($user['id']);
        if ($atasanId) {
            $stmt = $conn->prepare("INSERT INTO event (nama_event, pemohon_id, deskripsi, status) VALUES (?,?,?, 'menunggu_approval_atasan')");
            $stmt->bind_param("sis", $nama, $user['id'], $deskripsi);
            $stmt->execute();
            $eventId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO jadwal_event (event_id, tanggal, jam_mulai, jam_selesai, lokasi) VALUES (?,?,?,?,?)");
            $stmt2->bind_param("issss", $eventId, $tanggal, $jamMulai, $jamSelesai, $lokasi);
            $stmt2->execute();
            logAktivitas($user['id'], 'event', "Mengajukan event: $nama (menunggu atasan)");
            kirimNotifikasi($atasanId, 'event', $user['name'] . " mengajukan event \"$nama\", mohon diperiksa.", [
                'kategori' => 'persetujuan',
                'link_page' => 'event',
                'link_tab' => 'pengajuan'
            ]);
        } else {
            $stmt = $conn->prepare("INSERT INTO event (nama_event, pemohon_id, deskripsi, status) VALUES (?,?,?, 'diajukan')");
            $stmt->bind_param("sis", $nama, $user['id'], $deskripsi);
            $stmt->execute();
            $eventId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO jadwal_event (event_id, tanggal, jam_mulai, jam_selesai, lokasi) VALUES (?,?,?,?,?)");
            $stmt2->bind_param("issss", $eventId, $tanggal, $jamMulai, $jamSelesai, $lokasi);
            $stmt2->execute();
            $logMsg = $bypassAtasan
                ? "Mengajukan event: $nama (jalur pengganti: diajukan langsung oleh pimpinan unit)"
                : "Mengajukan event: $nama";
            logAktivitas($user['id'], 'event', $logMsg);
            notifyRole('spv_crm', 'event', $user['name'] . " mengajukan event \"$nama\", mohon diverifikasi.", null, [
                'kategori' => 'persetujuan',
                'link_page' => 'event',
                'link_tab' => 'pengajuan'
            ]);
        }
        ok(['id' => $eventId, 'status' => $atasanId ? 'menunggu_approval_atasan' : 'diajukan']);

    case 'approve_atasan':
        $id = (int)($_POST['id'] ?? 0);
        $setuju = (string)($_POST['setuju'] ?? '1') === '1';
        $catatan = $_POST['catatan_atasan'] ?? '';
        $viaPengganti = (string)($_POST['pengganti'] ?? '0') === '1';
        $alasanPengganti = $_POST['alasan'] ?? '';

        $res = prosesApproveAtasanModul($conn, 'event', $id, $user, $setuju, $catatan, $viaPengganti, $alasanPengganti);
        if (!$res['ok']) {
            fail($res['msg'], $res['code'] ?? 400);
        }
        ok();

    case 'verifikasi':
        if (!hasAccess($role, 'event', 'verifikasi')) forbid('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID event tidak valid.');
        $valid = ($_POST['valid'] ?? '1') === '1';
        $status = $valid ? 'diverifikasi' : 'ditolak';

        $conn->begin_transaction();
        try {
            $stmtEv = $conn->prepare("SELECT * FROM event WHERE id = ? FOR UPDATE");
            $stmtEv->bind_param("i", $id);
            $stmtEv->execute();
            $ev = $stmtEv->get_result()->fetch_assoc();

            if (!$ev) {
                $conn->rollback();
                fail('Data event tidak ditemukan.');
            }
            if ((int)$ev['pemohon_id'] === (int)$user['id']) {
                $conn->rollback();
                forbid('Akses ditolak. Pemohon tidak dapat memverifikasi pengajuan event miliknya sendiri.');
            }
            if (!in_array($ev['status'], ['diajukan', 'disetujui_atasan'], true)) {
                $conn->rollback();
                fail("Event #$id tidak dapat diverifikasi karena status saat ini adalah '{$ev['status']}'.");
            }

            $stmt = $conn->prepare("UPDATE event SET status=?, verifikator_id=? WHERE id=?");
            $stmt->bind_param("sii", $status, $user['id'], $id);
            $stmt->execute();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        logAktivitas($user['id'], 'event', "Verifikasi pengajuan event #$id: $status");
        if ($valid) {
            notifyRole('manajer_markom', 'event', "Pengajuan event \"{$ev['nama_event']}\" telah diverifikasi oleh SPV CRM, menunggu approval Anda.", null, [
                'kategori' => 'persetujuan',
                'link_page' => 'event',
                'link_tab' => 'pengajuan'
            ]);
            if ($ev && $ev['pemohon_id']) {
                kirimNotifikasi($ev['pemohon_id'], 'event', "Pengajuan event \"{$ev['nama_event']}\" telah diverifikasi SPV CRM, menunggu approval Manajer MARKOM.", [
                    'kategori' => 'persetujuan',
                    'link_page' => 'event',
                    'link_tab' => 'pengajuan'
                ]);
            }
        } else {
            if ($ev && $ev['pemohon_id']) {
                kirimNotifikasi($ev['pemohon_id'], 'event', "Pengajuan event \"{$ev['nama_event']}\" ditolak pada tahap verifikasi.", [
                    'kategori' => 'persetujuan',
                    'link_page' => 'event',
                    'link_tab' => 'pengajuan'
                ]);
            }
        }
        ok();

    case 'approve':
        if (!hasAccess($role, 'event', 'approve')) forbid('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID event tidak valid.');
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $status = $setuju ? 'disetujui' : 'ditolak';

        $conn->begin_transaction();
        try {
            $stmtEv = $conn->prepare("SELECT * FROM event WHERE id = ? FOR UPDATE");
            $stmtEv->bind_param("i", $id);
            $stmtEv->execute();
            $ev = $stmtEv->get_result()->fetch_assoc();

            if (!$ev) {
                $conn->rollback();
                fail('Data event tidak ditemukan.');
            }
            if ((int)$ev['pemohon_id'] === (int)$user['id']) {
                $conn->rollback();
                forbid('Akses ditolak. Pemohon tidak dapat menyetujui pengajuan event miliknya sendiri.');
            }
            if ($ev['status'] !== 'diverifikasi') {
                $conn->rollback();
                fail("Event #$id tidak dapat disetujui karena status saat ini adalah '{$ev['status']}' (harus 'diverifikasi').");
            }

            $stmt = $conn->prepare("UPDATE event SET status=?, approval_id=? WHERE id=?");
            $stmt->bind_param("sii", $status, $user['id'], $id);
            $stmt->execute();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        if ($ev && $ev['pemohon_id']) {
            kirimNotifikasi($ev['pemohon_id'], 'event', "Pengajuan event \"{$ev['nama_event']}\" telah $status.", [
                'kategori' => 'persetujuan',
                'link_page' => 'event',
                'link_tab' => 'pengajuan'
            ]);
        }
        logAktivitas($user['id'], 'event', "Approval event #$id: $status");
        ok();

    case 'update_status':
        // Staff/SPV CRM: tandai berlangsung/selesai + dokumentasi
        if (!hasAccess($role, 'event', 'kelola') && !hasAccess($role, 'event', 'verifikasi')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) fail('ID event tidak valid.');
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['berlangsung', 'selesai'], true)) fail('Status tidak valid.');

        $conn->begin_transaction();
        try {
            $stmtEv = $conn->prepare("SELECT id, status FROM event WHERE id = ? FOR UPDATE");
            $stmtEv->bind_param("i", $id);
            $stmtEv->execute();
            $ev = $stmtEv->get_result()->fetch_assoc();
            if (!$ev) {
                $conn->rollback();
                fail('Data event tidak ditemukan.');
            }
            $currStatus = $ev['status'] ?? '';
            if ($status === 'berlangsung' && $currStatus !== 'disetujui') {
                $conn->rollback();
                fail("Event #$id tidak dapat ditandai 'berlangsung' karena status saat ini adalah '$currStatus' (harus 'disetujui').");
            }
            if ($status === 'selesai' && !in_array($currStatus, ['disetujui', 'berlangsung'], true)) {
                $conn->rollback();
                fail("Event #$id tidak dapat ditandai 'selesai' karena status saat ini adalah '$currStatus' (harus 'disetujui' atau 'berlangsung').");
            }

            $stmtUpd = $conn->prepare("UPDATE event SET status = ? WHERE id = ?");
            $stmtUpd->bind_param("si", $status, $id);
            $stmtUpd->execute();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        logAktivitas($user['id'], 'event', "Update status event #$id menjadi $status");
        ok();

    case 'list_dokumentasi':
        $eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
        $sql = $conn->prepare("SELECT * FROM dokumentasi_event WHERE event_id=? ORDER BY id DESC");
        $sql->bind_param("i", $eventId);
        $sql->execute();
        ok($sql->get_result()->fetch_all(MYSQLI_ASSOC));

    case 'upload_dokumentasi':
        // Staff/SPV CRM (kelola/verifikasi) mengunggah bukti pelaksanaan event
        if (!hasAccess($role, 'event', 'kelola') && !hasAccess($role, 'event', 'verifikasi')) fail('Akses ditolak.');
        $eventId = (int)($_POST['event_id'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');
        $ev = $conn->query("SELECT id, nama_event FROM event WHERE id=$eventId")->fetch_assoc();
        if (!$ev) fail('Event tidak ditemukan.');

        $filePath = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            require_once '../config/upload.php';
            try {
                $allowed = [
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];
                $dir = __DIR__ . '/../uploads/dokumentasi_event/';
                $absPath = secureUpload($_FILES['file'], $allowed, $dir);
                $filePath = 'uploads/dokumentasi_event/' . basename($absPath);
            } catch (Exception $e) {
                fail($e->getMessage());
            }
        }
        if (!$filePath) fail('File dokumentasi wajib diunggah.');

        $stmt = $conn->prepare("INSERT INTO dokumentasi_event (event_id, file_path, keterangan) VALUES (?,?,?)");
        $stmt->bind_param("iss", $eventId, $filePath, $keterangan);
        $stmt->execute();
        logAktivitas($user['id'], 'event', "Mengunggah dokumentasi event: {$ev['nama_event']}");
        ok(['id' => $stmt->insert_id]);

    default:
        fail('Aksi tidak dikenal.');
}
