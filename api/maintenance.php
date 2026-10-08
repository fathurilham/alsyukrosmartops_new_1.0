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

if (!hasAccess($role, 'maintenance', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Maintenance.');

switch ($action) {

    case 'list_fasilitas':
        ok($conn->query("SELECT * FROM fasilitas ORDER BY id")->fetch_all(MYSQLI_ASSOC));

    case 'list_pengajuan':
        $where = "pm.pemohon_id = " . $user['id'];
        if (hasAccess($role, 'maintenance', 'approve_atasan')) {
            $where = "($where OR u1.unit_id = " . (int)$user['unit_id'] . ")";
        }
        if (hasAccess($role, 'maintenance', 'kelola') || hasAccess($role, 'maintenance', 'approve') || hasAccess($role, 'maintenance', 'verifikasi') || hasAccess($role, 'maintenance', 'monitoring') || $role === 'admin' || $role === 'manajer_ga') {
            $where = "($where OR pm.status != 'menunggu_approval_atasan')";
        }

        $sql = "SELECT pm.*, f.nama_fasilitas, f.lokasi, u1.name AS pemohon_nama,
                       u2.name AS tl_nama, u3.name AS spv_nama, u5.name AS atasan_nama,
                       prm.teknisi_id, u4.name AS teknisi_nama, prm.hasil_pekerjaan, prm.tanggal_selesai
                FROM pengajuan_maintenance pm
                JOIN fasilitas f ON f.id = pm.fasilitas_id
                JOIN users u1 ON u1.id = pm.pemohon_id
                LEFT JOIN users u2 ON u2.id = pm.tl_verifikasi_id
                LEFT JOIN users u3 ON u3.id = pm.spv_approval_id
                LEFT JOIN proses_maintenance prm ON prm.pengajuan_id = pm.id
                LEFT JOIN users u4 ON u4.id = prm.teknisi_id
                LEFT JOIN users u5 ON u5.id = pm.atasan_id
                WHERE $where
                ORDER BY pm.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'ajukan':
        if (!hasAccess($role, 'maintenance', 'ajukan')) fail('Akses ditolak.');
        $fasilitasId = (int)($_POST['fasilitas_id'] ?? 0);
        $deskripsi = trim($_POST['deskripsi_kerusakan'] ?? '');
        if (!$fasilitasId || !$deskripsi) fail('Fasilitas dan deskripsi kerusakan wajib diisi.');
        $atasanId = getAtasanId($user['id']);
        if ($atasanId) {
            $stmt = $conn->prepare("INSERT INTO pengajuan_maintenance (fasilitas_id, pemohon_id, deskripsi_kerusakan, status, tanggal_pengajuan) VALUES (?,?,?, 'menunggu_approval_atasan', CURDATE())");
            $stmt->bind_param("iis", $fasilitasId, $user['id'], $deskripsi);
            $stmt->execute();
            logAktivitas($user['id'], 'maintenance', 'Mengajukan permintaan maintenance fasilitas (menunggu atasan)');
            kirimNotifikasi($atasanId, 'maintenance', $user['name'] . ' mengajukan permintaan maintenance, mohon diperiksa.');
        } else {
            $stmt = $conn->prepare("INSERT INTO pengajuan_maintenance (fasilitas_id, pemohon_id, deskripsi_kerusakan, status, tanggal_pengajuan) VALUES (?,?,?, 'diajukan', CURDATE())");
            $stmt->bind_param("iis", $fasilitasId, $user['id'], $deskripsi);
            $stmt->execute();
            logAktivitas($user['id'], 'maintenance', 'Mengajukan permintaan maintenance fasilitas');
            notifyRole('team_leader', 'maintenance', $user['name'] . ' mengajukan permintaan maintenance, mohon diperiksa.');
        }
        ok(['id' => $stmt->insert_id]);

    case 'approve_atasan':
        if (!hasAccess($role, 'maintenance', 'approve_atasan')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $catatan = trim($_POST['catatan_atasan'] ?? '');
        $status = $setuju ? 'disetujui_atasan' : 'ditolak_atasan';
        $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, atasan_id=?, catatan_atasan=? WHERE id=?");
        $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'maintenance', "Approval Atasan untuk maintenance #$id: $status");
        $pm = $conn->query("SELECT pemohon_id FROM pengajuan_maintenance WHERE id=$id")->fetch_assoc();
        if ($setuju) {
            notifyRole('team_leader', 'maintenance', 'Ada pengajuan maintenance yang disetujui atasan, mohon diperiksa.');
            kirimNotifikasi($pm['pemohon_id'], 'maintenance', 'Pengajuan maintenance Anda disetujui atasan, diteruskan ke Team Leader.');
        } else {
            kirimNotifikasi($pm['pemohon_id'], 'maintenance', 'Pengajuan maintenance Anda DITOLAK oleh atasan.');
        }
        ok();

    case 'verifikasi':
        if (!hasAccess($role, 'maintenance', 'verifikasi')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $valid = ($_POST['valid'] ?? '1') === '1';
        $status = $valid ? 'diverifikasi' : 'ditolak';
        $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, tl_verifikasi_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $user['id'], $id);
        $stmt->execute();
        logAktivitas($user['id'], 'maintenance', "Verifikasi kondisi fasilitas pengajuan #$id: $status");
        if ($valid) notifyRole('spv_sapras', 'maintenance', "Pengajuan maintenance #$id telah diverifikasi TL, menunggu approval.");
        ok();

    case 'approve':
        if (!hasAccess($role, 'maintenance', 'approve')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $status = $setuju ? 'disetujui' : 'ditolak';
        $stmt = $conn->prepare("UPDATE pengajuan_maintenance SET status=?, spv_approval_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $user['id'], $id);
        $stmt->execute();
        if ($setuju) {
            $stmt2 = $conn->prepare("INSERT INTO proses_maintenance (pengajuan_id, tanggal_mulai) VALUES (?, CURDATE())");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            notifyRole('engineering', 'maintenance', "Pengajuan maintenance #$id disetujui, silakan dikerjakan.");
        }
        $pm = $conn->query("SELECT pemohon_id FROM pengajuan_maintenance WHERE id=$id")->fetch_assoc();
        if ($pm) kirimNotifikasi($pm['pemohon_id'], 'maintenance', "Pengajuan maintenance Anda #$id: $status.");
        logAktivitas($user['id'], 'maintenance', "Approval pengajuan maintenance #$id: $status");
        ok();

    case 'proses':
        // Engineering: mulai/selesaikan pekerjaan
        if (!hasAccess($role, 'maintenance', 'kelola')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $hasil = trim($_POST['hasil_pekerjaan'] ?? '');
        $selesai = ($_POST['selesai'] ?? '0') === '1';
        $stmt = $conn->prepare("UPDATE proses_maintenance SET teknisi_id=?, hasil_pekerjaan=?" . ($selesai ? ", tanggal_selesai=CURDATE()" : "") . " WHERE pengajuan_id=?");
        $stmt->bind_param("isi", $user['id'], $hasil, $id);
        $stmt->execute();
        if ($selesai) {
            $conn->query("UPDATE pengajuan_maintenance SET status='selesai' WHERE id=$id");
            $pm = $conn->query("SELECT pemohon_id FROM pengajuan_maintenance WHERE id=$id")->fetch_assoc();
            if ($pm) kirimNotifikasi($pm['pemohon_id'], 'maintenance', "Maintenance #$id telah selesai dikerjakan.");
        } else {
            $conn->query("UPDATE pengajuan_maintenance SET status='dikerjakan' WHERE id=$id");
        }
        logAktivitas($user['id'], 'maintenance', "Update proses maintenance #$id" . ($selesai ? ' (selesai)' : ''));
        ok();

    default:
        fail('Aksi tidak dikenal.');
}
