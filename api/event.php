<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

if (!hasAccess($role, 'event', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Event.');

switch ($action) {

    case 'list':
        $where = "e.pemohon_id = " . $user['id'];
        if (hasAccess($role, 'event', 'approve_atasan')) {
            $where = "($where OR u1.unit_id = " . (int)$user['unit_id'] . ")";
        }
        if (hasAccess($role, 'event', 'kelola') || hasAccess($role, 'event', 'approve') || hasAccess($role, 'event', 'verifikasi') || hasAccess($role, 'event', 'monitoring') || $role === 'admin' || $role === 'manajer_ga') {
            $where = "($where OR e.status != 'menunggu_approval_atasan')";
        }

        $sql = "SELECT e.*, u1.name AS pemohon_nama, u2.name AS verifikator_nama, u3.name AS approval_nama, u4.name AS atasan_nama,
                       j.tanggal, j.jam_mulai, j.jam_selesai, j.lokasi
                FROM event e
                JOIN users u1 ON u1.id = e.pemohon_id
                LEFT JOIN users u2 ON u2.id = e.verifikator_id
                LEFT JOIN users u3 ON u3.id = e.approval_id
                LEFT JOIN users u4 ON u4.id = e.atasan_id
                LEFT JOIN jadwal_event j ON j.event_id = e.id
                WHERE $where
                ORDER BY e.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'ajukan':
        if (!hasAccess($role, 'event', 'ajukan')) fail('Akses ditolak.');
        $nama = trim($_POST['nama_event'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? '';
        $jamMulai = $_POST['jam_mulai'] ?? '08:00';
        $jamSelesai = $_POST['jam_selesai'] ?? '10:00';
        $lokasi = trim($_POST['lokasi'] ?? '');
        if (!$nama || !$tanggal) fail('Nama event dan tanggal wajib diisi.');

        // Cegah benturan jadwal pada lokasi & tanggal yang sama
        $stmt = $conn->prepare("SELECT e.nama_event FROM jadwal_event j JOIN event e ON e.id=j.event_id
                                 WHERE j.tanggal=? AND j.lokasi=? AND e.status NOT IN ('ditolak')
                                 AND NOT (j.jam_selesai <= ? OR j.jam_mulai >= ?)");
        $stmt->bind_param("ssss", $tanggal, $lokasi, $jamMulai, $jamSelesai);
        $stmt->execute();
        if ($bentrok = $stmt->get_result()->fetch_assoc()) {
            fail('Jadwal bentrok dengan event "' . $bentrok['nama_event'] . '" pada lokasi & waktu yang sama.');
        }

        $atasanId = getAtasanId($user['id']);
        if ($atasanId) {
            $stmt = $conn->prepare("INSERT INTO event (nama_event, pemohon_id, deskripsi, status) VALUES (?,?,?, 'menunggu_approval_atasan')");
            $stmt->bind_param("sis", $nama, $user['id'], $deskripsi);
            $stmt->execute();
            $eventId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO jadwal_event (event_id, tanggal, jam_mulai, jam_selesai, lokasi) VALUES (?,?,?,?,?)");
            $stmt2->bind_param("issss", $eventId, $tanggal, $jamMulai, $jamSelesai, $lokasi);
            $stmt2->execute();
            logAktivitas($user['id'], 'event', "Mengajukan event: $nama (menunggu atasan)");
            kirimNotifikasi($atasanId, 'event', $user['name'] . " mengajukan event \"$nama\", mohon diperiksa.");
        } else {
            $stmt = $conn->prepare("INSERT INTO event (nama_event, pemohon_id, deskripsi, status) VALUES (?,?,?, 'diajukan')");
            $stmt->bind_param("sis", $nama, $user['id'], $deskripsi);
            $stmt->execute();
            $eventId = $stmt->insert_id;
            $stmt2 = $conn->prepare("INSERT INTO jadwal_event (event_id, tanggal, jam_mulai, jam_selesai, lokasi) VALUES (?,?,?,?,?)");
            $stmt2->bind_param("issss", $eventId, $tanggal, $jamMulai, $jamSelesai, $lokasi);
            $stmt2->execute();
            logAktivitas($user['id'], 'event', "Mengajukan event: $nama");
            notifyRole('staff_crm', 'event', $user['name'] . " mengajukan event \"$nama\", mohon diperiksa.");
        }
        ok(['id' => $eventId]);

    case 'approve_atasan':
        if (!hasAccess($role, 'event', 'approve_atasan')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $catatan = trim($_POST['catatan_atasan'] ?? '');
        $status = $setuju ? 'disetujui_atasan' : 'ditolak_atasan';
        $stmt = $conn->prepare("UPDATE event SET status=?, atasan_id=?, catatan_atasan=? WHERE id=?");
        $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'event', "Approval Atasan untuk event #$id: $status");
        $ev = $conn->query("SELECT pemohon_id, nama_event FROM event WHERE id=$id")->fetch_assoc();
        if ($setuju) {
            notifyRole('staff_crm', 'event', 'Ada pengajuan event yang disetujui atasan, mohon diperiksa.');
            kirimNotifikasi($ev['pemohon_id'], 'event', 'Pengajuan event Anda disetujui atasan, diteruskan ke Staff CRM.');
        } else {
            kirimNotifikasi($ev['pemohon_id'], 'event', 'Pengajuan event Anda DITOLAK oleh atasan.');
        }
        ok();

    case 'verifikasi':
        if (!hasAccess($role, 'event', 'verifikasi')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $valid = ($_POST['valid'] ?? '1') === '1';
        $status = $valid ? 'diverifikasi' : 'ditolak';
        $stmt = $conn->prepare("UPDATE event SET status=?, verifikator_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $user['id'], $id);
        $stmt->execute();
        logAktivitas($user['id'], 'event', "Verifikasi pengajuan event #$id: $status");
        if ($valid) notifyRole('spv_crm', 'event', "Pengajuan event #$id telah diverifikasi, menunggu approval.");
        ok();

    case 'approve':
        if (!hasAccess($role, 'event', 'approve')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $status = $setuju ? 'disetujui' : 'ditolak';
        $stmt = $conn->prepare("UPDATE event SET status=?, approval_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $user['id'], $id);
        $stmt->execute();
        $ev = $conn->query("SELECT pemohon_id, nama_event FROM event WHERE id=$id")->fetch_assoc();
        if ($ev) kirimNotifikasi($ev['pemohon_id'], 'event', "Pengajuan event \"{$ev['nama_event']}\" telah $status.");
        logAktivitas($user['id'], 'event', "Approval event #$id: $status");
        ok();

    case 'update_status':
        // Staff/SPV CRM: tandai berlangsung/selesai + dokumentasi
        if (!hasAccess($role, 'event', 'kelola') && !hasAccess($role, 'event', 'verifikasi')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['berlangsung', 'selesai'])) fail('Status tidak valid.');
        $conn->query("UPDATE event SET status='$status' WHERE id=" . $id);
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
