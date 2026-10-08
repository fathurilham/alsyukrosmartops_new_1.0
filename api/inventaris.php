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

if (!hasAccess($role, 'inventaris', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Inventaris.');

// Auto-migrate database tables/columns for production if missing
// (Dipindahkan ke database/migrations.sql)

try {
    switch ($action) {

        case 'list_barang':
            $res = $conn->query("SELECT * FROM barang ORDER BY kode_barang ASC");
            ok($res->fetch_all(MYSQLI_ASSOC));

        case 'list_peminjaman':
            $where = "p.peminjam_id = " . $user['id'];
            if (hasAccess($role, 'inventaris', 'approve_atasan')) {
                $where = "($where OR u1.unit_id = " . (int)$user['unit_id'] . ")";
            }
            if (hasAccess($role, 'inventaris', 'kelola') || hasAccess($role, 'inventaris', 'approve') || hasAccess($role, 'inventaris', 'monitoring') || hasAccess($role, 'inventaris', 'verifikasi') || $role === 'admin' || $role === 'manajer_ga') {
                $where = "($where OR p.status != 'menunggu_approval_atasan')";
            }

            $sql = "SELECT p.*, b.nama_barang, b.kode_barang, u1.name AS peminjam_nama, r1.nama_role AS peminjam_jabatan,
                           u2.name AS verifikator_nama, u3.name AS approval_nama, u4.name AS atasan_nama
                    FROM peminjaman p
                    JOIN barang b ON b.id = p.barang_id
                    JOIN users u1 ON u1.id = p.peminjam_id
                    LEFT JOIN roles r1 ON r1.id = u1.role_id
                    LEFT JOIN users u2 ON u2.id = p.verifikator_id
                    LEFT JOIN users u3 ON u3.id = p.approval_id
                    LEFT JOIN users u4 ON u4.id = p.atasan_id
                    WHERE $where
                    ORDER BY p.id DESC";
            $res = $conn->query($sql);
            ok($res->fetch_all(MYSQLI_ASSOC));

        case 'list_pemeriksaan':
            if (!hasAccess($role, 'inventaris', 'kelola')) ok([]);
            $sql = "SELECT peng.*, pj.tanggal_pinjam, pj.jumlah, b.nama_barang, u.name as peminjam_nama 
                    FROM pengembalian peng 
                    JOIN peminjaman pj ON pj.id = peng.peminjaman_id 
                    JOIN barang b ON b.id = pj.barang_id
                    JOIN users u ON u.id = pj.peminjam_id
                    WHERE peng.kondisi_barang != 'baik'
                    ORDER BY peng.id DESC";
            $res = $conn->query($sql);
            ok($res->fetch_all(MYSQLI_ASSOC));
            
        case 'list_riwayat':
            if (!hasAccess($role, 'inventaris', 'kelola')) ok([]);
            $sql = "SELECT r.*, b.nama_barang, u.name as nama_user 
                    FROM riwayat_inventaris r 
                    JOIN barang b ON b.id = r.barang_id
                    LEFT JOIN users u ON u.id = r.user_id
                    ORDER BY r.id DESC";
            $res = $conn->query($sql);
            ok($res->fetch_all(MYSQLI_ASSOC));

        case 'tambah_barang':
            if (!hasAccess($role, 'inventaris', 'tambah')) fail('Akses ditolak.');
            $nama = trim($_POST['nama_barang'] ?? '');
            if (!$nama) fail('Nama barang wajib diisi.');
            $kategori = trim($_POST['kategori'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            $stok = (int)($_POST['stok'] ?? 0);
            $ket = trim($_POST['keterangan'] ?? '');
            $row = $conn->query("SELECT COUNT(*)+1 n FROM barang")->fetch_assoc();
            $kode = 'BRG-' . str_pad($row['n'], 3, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori, lokasi, stok, status, keterangan) VALUES (?,?,?,?,?, 'tersedia', ?)");
            $stmt->bind_param("sssiss", $kode, $nama, $kategori, $lokasi, $stok, $ket);
            $stmt->execute();
            logAktivitas($user['id'], 'inventaris', "Menambahkan barang $nama ($kode)");
            ok(['id' => $stmt->insert_id]);

        case 'update_barang':
            if (!hasAccess($role, 'inventaris', 'kelola')) fail('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_barang'] ?? '');
            $kategori = trim($_POST['kategori'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            $stok = (int)($_POST['stok'] ?? 0);
            $status = $_POST['status'] ?? 'tersedia';
            $stmt = $conn->prepare("UPDATE barang SET nama_barang=?, kategori=?, lokasi=?, stok=?, status=? WHERE id=?");
            $stmt->bind_param("sssisi", $nama, $kategori, $lokasi, $stok, $status, $id);
            $stmt->execute();
            logAktivitas($user['id'], 'inventaris', "Memperbarui data barang #$id");
            ok();

        case 'ajukan_pinjam':
            if (!hasAccess($role, 'inventaris', 'ajukan')) fail('Akses ditolak.');
            $barangId = (int)($_POST['barang_id'] ?? 0);
            $jumlah = max(1, (int)($_POST['jumlah'] ?? 1));
            $tglPinjam = $_POST['tanggal_pinjam'] ?? date('Y-m-d');
            $rencana = $_POST['rencana_kembali'] ?? null;
            $keperluan = trim($_POST['keperluan'] ?? '');
            if (!$barangId) fail('Barang wajib dipilih.');
            $b = $conn->query("SELECT stok FROM barang WHERE id=" . $barangId)->fetch_assoc();
            if (!$b || $b['stok'] < $jumlah) fail('Stok barang tidak mencukupi.');
            $atasanId = getAtasanId($user['id']);
            if ($atasanId) {
                $status = 'menunggu_approval_atasan';
                $stmt = $conn->prepare("INSERT INTO peminjaman (barang_id, jumlah, peminjam_id, tanggal_pinjam, rencana_kembali, keperluan, status) VALUES (?,?,?,?,?,?, ?)");
                $stmt->bind_param("iiissss", $barangId, $jumlah, $user['id'], $tglPinjam, $rencana, $keperluan, $status);
                $stmt->execute();
                logAktivitas($user['id'], 'inventaris', 'Mengajukan peminjaman barang (menunggu atasan)');
                kirimNotifikasi($atasanId, 'inventaris', $user['name'] . ' mengajukan peminjaman barang, mohon di-approve.');
            } else {
                $status = 'diajukan';
                $stmt = $conn->prepare("INSERT INTO peminjaman (barang_id, jumlah, peminjam_id, tanggal_pinjam, rencana_kembali, keperluan, status) VALUES (?,?,?,?,?,?, ?)");
                $stmt->bind_param("iiissss", $barangId, $jumlah, $user['id'], $tglPinjam, $rencana, $keperluan, $status);
                $stmt->execute();
                logAktivitas($user['id'], 'inventaris', 'Mengajukan peminjaman barang');
                notifyRole('staff_inventaris', 'inventaris', $user['name'] . ' mengajukan peminjaman barang, mohon diverifikasi.');
            }
            ok(['id' => $stmt->insert_id]);

        case 'approve_atasan':
            if (!hasAccess($role, 'inventaris', 'approve_atasan')) fail('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            $setuju = ($_POST['setuju'] ?? '1') === '1';
            $catatan = trim($_POST['catatan_atasan'] ?? '');
            $status = $setuju ? 'disetujui_atasan' : 'ditolak_atasan';
            $stmt = $conn->prepare("UPDATE peminjaman SET status=?, atasan_id=?, catatan_atasan=? WHERE id=?");
            $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
            $stmt->execute();
            logAktivitas($user['id'], 'inventaris', "Approval Atasan untuk peminjaman #$id: $status");
            $pj = $conn->query("SELECT peminjam_id, keperluan FROM peminjaman WHERE id=$id")->fetch_assoc();
            if ($setuju) {
                notifyRole('staff_inventaris', 'inventaris', 'Ada peminjaman barang (' . $pj['keperluan'] . ') yang disetujui atasan, mohon diverifikasi.');
                kirimNotifikasi($pj['peminjam_id'], 'inventaris', 'Pengajuan peminjaman Anda disetujui atasan, diteruskan ke Staff Inventaris.');
            } else {
                kirimNotifikasi($pj['peminjam_id'], 'inventaris', 'Pengajuan peminjaman Anda DITOLAK oleh atasan.');
            }
            ok();

        case 'verifikasi_pinjam':
            if (!hasAccess($role, 'inventaris', 'verifikasi')) fail('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            $setuju = (int)($_POST['setuju'] ?? 1) === 1;
            $catatan = trim($_POST['catatan'] ?? '');
            
            $status = $setuju ? 'diverifikasi' : 'ditolak_staff';
            if (!$setuju && empty($catatan)) fail('Catatan wajib diisi jika menolak.');
            
            $stmt = $conn->prepare("UPDATE peminjaman SET status=?, verifikator_id=?, catatan=? WHERE id=?");
            $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
            $stmt->execute();
            
            if ($keputusan === 'setujui') notifyRole('admin', 'inventaris', 'Peminjaman #'.$id.' telah diverifikasi, menunggu approval Anda.');
            ok();

        case 'approve_pinjam':
            if (!hasAccess($role, 'inventaris', 'approve')) fail('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            $setuju = (int)($_POST['setuju'] ?? 1) === 1;
            $catatan = trim($_POST['catatan'] ?? '');
            
            $status = $setuju ? 'disetujui' : 'ditolak';
            
            $pj = $conn->query("SELECT * FROM peminjaman WHERE id=$id")->fetch_assoc();
            if (!$pj) fail('Peminjaman tidak ditemukan.');
            
            if ($keputusan === 'setujui') {
                $b = $conn->query("SELECT stok FROM barang WHERE id={$pj['barang_id']}")->fetch_assoc();
                if ($b['stok'] < $pj['jumlah']) fail('Stok barang saat ini tidak cukup untuk disetujui.');
                $conn->query("UPDATE barang SET stok = stok - {$pj['jumlah']} WHERE id={$pj['barang_id']}");
                $stmt = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'keluar', ?, CURDATE(), 'Peminjaman disetujui', ?)");
                $stmt->bind_param("iii", $pj['barang_id'], $pj['jumlah'], $user['id']);
                $stmt->execute();
            }
            
            $stmt = $conn->prepare("UPDATE peminjaman SET status=?, approval_id=?, catatan=? WHERE id=?");
            $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
            $stmt->execute();
            
            kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman #$id Anda telah " . str_replace('_', ' ', $status) . ".");
            logAktivitas($user['id'], 'inventaris', "Memberikan approval ($keputusan) untuk peminjaman #$id");
            ok();

        case 'kembalikan':
            if (!hasAccess($role, 'inventaris', 'verifikasi') && !hasAccess($role, 'inventaris', 'kelola')) fail('Akses ditolak.');
            $id = (int)($_POST['id'] ?? 0);
            $kondisi = $_POST['kondisi_barang'] ?? 'baik';
            if (!in_array($kondisi, ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])) fail('Kondisi barang tidak valid.');
            $catatan = trim($_POST['catatan'] ?? '');
            
            $pj_res = $conn->query("SELECT * FROM peminjaman WHERE id=$id");
            if (!$pj_res) throw new Exception("Query SELECT peminjaman gagal: " . $conn->error);
            $pj = $pj_res->fetch_assoc();
            
            if (!$pj || $pj['status'] !== 'disetujui') fail('Peminjaman tidak valid untuk pengembalian.');
            
            $status_pemeriksaan = ($kondisi === 'baik') ? 'selesai' : 'menunggu_spv';
            
            $stmt = $conn->prepare("INSERT INTO pengembalian (peminjaman_id, tanggal_kembali, kondisi_barang, petugas_id, catatan, status_pemeriksaan) VALUES (?, CURDATE(), ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare INSERT pengembalian gagal: " . $conn->error);
            $stmt->bind_param("isiss", $id, $kondisi, $user['id'], $catatan, $status_pemeriksaan);
            if (!$stmt->execute()) throw new Exception("Execute INSERT pengembalian gagal: " . $stmt->error);
            
            if (!$conn->query("UPDATE peminjaman SET status='dikembalikan' WHERE id=$id")) throw new Exception("UPDATE peminjaman gagal: " . $conn->error);
            
            if ($kondisi === 'baik') {
                if (!$conn->query("UPDATE barang SET stok = stok + {$pj['jumlah']} WHERE id={$pj['barang_id']}")) throw new Exception("UPDATE barang gagal: " . $conn->error);
                
                $stmt2 = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'masuk', ?, CURDATE(), 'Pengembalian barang', ?)");
                if (!$stmt2) throw new Exception("Prepare INSERT transaksi_barang gagal: " . $conn->error);
                $stmt2->bind_param("iii", $pj['barang_id'], $pj['jumlah'], $user['id']);
                if (!$stmt2->execute()) throw new Exception("Execute INSERT transaksi_barang gagal: " . $stmt2->error);
            } else {
                notifyRole('spv_inventaris', 'inventaris', 'Ada pengembalian barang (' . $kondisi . ') yang menunggu pemeriksaan Anda.');
            }
            logAktivitas($user['id'], 'inventaris', "Konfirmasi pengembalian peminjaman #$id ($kondisi)");
            ok();

        case 'periksa_pengembalian':
            if (!hasAccess($role, 'inventaris', 'kelola')) fail('Akses ditolak. Hanya SPV/Pengelola yang dapat memeriksa.');
            $id = (int)($_POST['id'] ?? 0);
            $tindakan = $_POST['tindakan'] ?? '';
            if (!in_array($tindakan, ['tetap_digunakan', 'perlu_perbaikan', 'tidak_layak', 'hilang'])) fail('Tindakan tidak valid.');
            $catatan_spv = trim($_POST['catatan_spv'] ?? '');
            
            $peng_res = $conn->query("SELECT p.*, pj.barang_id, pj.jumlah FROM pengembalian p JOIN peminjaman pj ON pj.id=p.peminjaman_id WHERE p.id=$id");
            if (!$peng_res) throw new Exception("Query SELECT pengembalian gagal: " . $conn->error);
            $peng = $peng_res->fetch_assoc();
            
            if (!$peng || $peng['status_pemeriksaan'] !== 'menunggu_spv') fail('Data pengembalian tidak valid.');
            
            $b_res = $conn->query("SELECT * FROM barang WHERE id=" . $peng['barang_id']);
            if (!$b_res) throw new Exception("Query SELECT barang gagal: " . $conn->error);
            $b = $b_res->fetch_assoc();
            
            if ($tindakan === 'tetap_digunakan' || $tindakan === 'perlu_perbaikan') {
                if (!$conn->query("UPDATE barang SET stok = stok + {$peng['jumlah']} WHERE id={$peng['barang_id']}")) throw new Exception("UPDATE barang gagal: " . $conn->error);
                $stmt2 = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'masuk', ?, CURDATE(), 'Pemeriksaan SPV: Dikembalikan ke stok', ?)");
                if (!$stmt2) throw new Exception("Prepare INSERT transaksi_barang gagal: " . $conn->error);
                $stmt2->bind_param("iii", $peng['barang_id'], $peng['jumlah'], $user['id']);
                if (!$stmt2->execute()) throw new Exception("Execute INSERT transaksi_barang gagal: " . $stmt2->error);
                
                $stmt3 = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, 'penyesuaian_stok_masuk', ?, ?, ?, ?, ?, ?, ?)");
                $j_s = $b['stok']; $j_n = $b['stok'] + $peng['jumlah'];
                $stmt3->bind_param("iiisssis", $peng['barang_id'], $j_s, $j_n, $peng['kondisi_barang'], $tindakan, $catatan_spv, $user['id'], $role);
                $stmt3->execute();
                
            } elseif ($tindakan === 'tidak_layak' || $tindakan === 'hilang') {
                $stmt3 = $conn->prepare("INSERT INTO riwayat_inventaris (barang_id, jenis_perubahan, jumlah_sebelum, jumlah_sesudah, kondisi_sebelum, kondisi_sesudah, alasan, user_id, role_user) VALUES (?, 'penghapusan_stok', ?, ?, ?, ?, ?, ?, ?)");
                $j_s = $b['stok']; $j_n = $b['stok'];
                $stmt3->bind_param("iiisssis", $peng['barang_id'], $j_s, $j_n, $peng['kondisi_barang'], $tindakan, $catatan_spv, $user['id'], $role);
                $stmt3->execute();
            } else {
                fail('Tindakan tidak valid.');
            }
            
            $stmt = $conn->prepare("UPDATE pengembalian SET status_pemeriksaan='selesai', tindakan_spv=?, catatan_spv=? WHERE id=?");
            if (!$stmt) throw new Exception("Prepare UPDATE pengembalian gagal: " . $conn->error);
            $stmt->bind_param("ssi", $tindakan, $catatan_spv, $id);
            if (!$stmt->execute()) throw new Exception("Execute UPDATE pengembalian gagal: " . $stmt->error);
            
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
