ALTER TABLE peminjaman ADD COLUMN IF NOT EXISTS atasan_id INT DEFAULT NULL;
ALTER TABLE event ADD COLUMN IF NOT EXISTS atasan_id INT DEFAULT NULL;

ALTER TABLE peminjaman ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;
ALTER TABLE event ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;
ALTER TABLE pengajuan_maintenance ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;

ALTER TABLE peminjaman MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';
ALTER TABLE event MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';
ALTER TABLE pengajuan_maintenance MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';
ALTER TABLE pengajuan_maintenance MODIFY COLUMN fasilitas_id INT NULL DEFAULT NULL;
ALTER TABLE pengajuan_maintenance ADD COLUMN IF NOT EXISTS foto VARCHAR(255) DEFAULT NULL;
ALTER TABLE proses_maintenance ADD COLUMN IF NOT EXISTS foto_pekerjaan VARCHAR(255) DEFAULT NULL;
ALTER TABLE penilaian_kinerja ADD COLUMN IF NOT EXISTS kegiatan_id INT DEFAULT NULL;

ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS status_pemeriksaan VARCHAR(50) DEFAULT 'selesai';
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS tindakan_spv VARCHAR(50) DEFAULT NULL;
ALTER TABLE pengembalian ADD COLUMN IF NOT EXISTS catatan_spv VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS riwayat_inventaris (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  jenis_perubahan VARCHAR(50) NOT NULL,
  jumlah_sebelum INT NOT NULL,
  jumlah_sesudah INT NOT NULL,
  kondisi_sebelum VARCHAR(50) DEFAULT NULL,
  kondisi_sesudah VARCHAR(50) DEFAULT NULL,
  alasan VARCHAR(255) DEFAULT NULL,
  user_id INT NOT NULL,
  role_user VARCHAR(50) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Hak Akses SPV Inventaris
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'kelola' FROM roles WHERE kode = 'spv_inventaris';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'tambah' FROM roles WHERE kode = 'spv_inventaris';

-- Hak Akses Staff Inventaris
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'tambah' FROM roles WHERE kode = 'staff_inventaris';

-- Manajer GA
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'lihat' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'monitoring' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'monitoring' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'kelola' FROM roles WHERE kode = 'manajer_ga';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'laporan', 'lihat' FROM roles WHERE kode = 'manajer_ga';

-- Manajer HRD
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'kelola' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'approve_atasan' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'approve_atasan' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'kelola' FROM roles WHERE kode = 'manajer_hrd';

-- Manajer QMS
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'monitoring' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'approve_atasan' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'approve_atasan' FROM roles WHERE kode = 'manajer_qms';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'laporan', 'lihat' FROM roles WHERE kode = 'manajer_qms';

-- Manajer Markom
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'event', 'lihat' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'event', 'monitoring' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'monitoring' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'kelola' FROM roles WHERE kode = 'manajer_markom';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'laporan', 'lihat' FROM roles WHERE kode = 'manajer_markom';

-- Atasan Roles
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'approve_atasan' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'approve_atasan' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'event', 'lihat' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'event', 'approve_atasan' FROM roles WHERE kode IN ('spv_inventaris', 'spv_sapras', 'team_leader', 'spv_crm', 'kepala_sekolah');

-- SPV CRM remove 'ajukan'
DELETE h FROM hak_akses h JOIN roles r ON h.role_id = r.id WHERE r.kode = 'spv_crm' AND h.modul IN ('inventaris', 'maintenance', 'event') AND h.aksi = 'ajukan';

-- ═══════════════════════════════════════════════════════════════════
-- PERAN BARU: DIVISI FINANCE & ACCOUNTING (manajer_fa & staff_fa)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Unit Finance & Accounting (jika belum ada atau pembaruan nama dari FA / Keuangan)
UPDATE struktur_unit SET nama_unit = 'Finance & Accounting' WHERE nama_unit = 'FA / Keuangan';

INSERT INTO struktur_unit (nama_unit, tipe)
SELECT 'Finance & Accounting', 'divisi'
WHERE NOT EXISTS (SELECT 1 FROM struktur_unit WHERE nama_unit = 'Finance & Accounting');

-- 2. Role Manajer FA & Staff FA
INSERT IGNORE INTO roles (kode, nama_role, deskripsi) VALUES
('manajer_fa', 'Manajer FA', 'Memvalidasi & menyetujui pengajuan kebutuhan dari Staff FA sebelum diteruskan ke pengelola modul terkait serta monitoring laporan'),
('staff_fa', 'Staff FA', 'Membuat pengajuan kebutuhan (inventaris/maintenance) untuk diverifikasi Manajer FA serta menyimpan dokumen di Arsip Digital');

UPDATE roles SET nama_role='Staff FA', deskripsi='Membuat pengajuan kebutuhan (inventaris/maintenance) untuk diverifikasi Manajer FA serta menyimpan dokumen di Arsip Digital' WHERE kode='staff_fa';
UPDATE roles SET nama_role='Manajer FA', deskripsi='Memvalidasi & menyetujui pengajuan kebutuhan dari Staff FA sebelum diteruskan ke pengelola modul terkait serta monitoring laporan' WHERE kode='manajer_fa';

-- 3. Akun Demo Manajer FA (manajerfa) & Staff FA (stafffa) (password: password123)
INSERT INTO users (username, email, password, name, role_id, unit_id, status)
SELECT 'manajerfa', 'manajerfa@alsyukro.sch.id', '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Fauzi Rahman, SE', r.id, su.id, 'aktif'
FROM roles r
LEFT JOIN struktur_unit su ON su.nama_unit = 'Finance & Accounting'
WHERE r.kode = 'manajer_fa'
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'manajerfa');

INSERT INTO users (username, email, password, name, role_id, unit_id, status)
SELECT 'stafffa', 'stafffa@alsyukro.sch.id', '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Rizky Fauzi', r.id, su.id, 'aktif'
FROM roles r
LEFT JOIN struktur_unit su ON su.nama_unit = 'Finance & Accounting'
WHERE r.kode = 'staff_fa'
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'stafffa');

-- 4. Matriks Hak Akses Staff FA (Inventaris & Maintenance, tanpa Event)
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'ajukan' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'ajukan' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'staff_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'dashboard', 'lihat' FROM roles WHERE kode = 'staff_fa';

-- 5. Matriks Hak Akses Manajer FA (Inventaris, Maintenance, Arsip, Laporan, tanpa Event)
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'approve_atasan' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'approve_atasan' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'laporan', 'lihat' FROM roles WHERE kode = 'manajer_fa';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'dashboard', 'lihat' FROM roles WHERE kode = 'manajer_fa';

-- Pastikan hak akses modul event untuk Unit FA (staff_fa & manajer_fa) dihapus jika ada dari instalasi sebelumnya
DELETE ha FROM hak_akses ha JOIN roles r ON r.id = ha.role_id WHERE r.kode IN ('staff_fa', 'manajer_fa') AND ha.modul = 'event';

-- 6. Konversi Skala Penilaian Kinerja Karyawan dari 1-100 menjadi 1-5 (Idempotent)
-- Kondisi WHERE nilai > 5 memastikan migrasi hanya menyentuh data berskala lama (skor 1-100)
-- dan aman dijalankan berulang kali tanpa mengubah data yang sudah berskala 1-5.
UPDATE penilaian_kinerja
SET nilai = LEAST(5, GREATEST(1, ROUND(nilai / 20)))
WHERE nilai > 5;

-- 7. Tambahkan kolom foto_laporan sebagai bukti pengerjaan tugas (CSO & Gardener)
ALTER TABLE kegiatan_karyawan ADD COLUMN IF NOT EXISTS foto_laporan VARCHAR(255) DEFAULT NULL;

-- ═══════════════════════════════════════════════════════════════════
-- 8. PENYESUAIAN MODUL MANAJEMEN KARYAWAN SESUAI KAK (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- A. Kolom baru di tabel karyawan (tanggal_masuk & kontak)
ALTER TABLE karyawan ADD COLUMN IF NOT EXISTS tanggal_masuk DATE NULL DEFAULT NULL AFTER status;
ALTER TABLE karyawan ADD COLUMN IF NOT EXISTS kontak VARCHAR(30) NULL DEFAULT NULL AFTER tanggal_masuk;

-- B. Kolom baru di tabel kegiatan_karyawan (tenggat, jam, selesai_pada)
ALTER TABLE kegiatan_karyawan ADD COLUMN IF NOT EXISTS tenggat DATE NULL DEFAULT NULL AFTER tanggal;
ALTER TABLE kegiatan_karyawan ADD COLUMN IF NOT EXISTS jam TIME NULL DEFAULT NULL AFTER tenggat;
ALTER TABLE kegiatan_karyawan ADD COLUMN IF NOT EXISTS selesai_pada DATETIME NULL DEFAULT NULL AFTER status;

-- C. Index untuk optimalisasi query jadwal & reminder
CREATE INDEX IF NOT EXISTS idx_kegiatan_status_jadwal ON kegiatan_karyawan (status, tenggat, tanggal);

-- D. Tabel reminder_kegiatan (pencegah notifikasi duplikat)
CREATE TABLE IF NOT EXISTS reminder_kegiatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kegiatan_id INT NOT NULL,
  jenis ENUM('h-1','hari_h','terlambat') NOT NULL,
  tanggal DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reminder (kegiatan_id, jenis, tanggal),
  FOREIGN KEY (kegiatan_id) REFERENCES kegiatan_karyawan(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- E. Perluasan Matriks Hak Akses Modul Karyawan
-- Hak Akses Karyawan Pelaksana (lihat, input)
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'lihat' FROM roles WHERE kode IN ('staff_inventaris', 'staff_crm', 'staff_qms', 'staff_fa', 'tu', 'engineering', 'cso', 'gardener');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'input' FROM roles WHERE kode IN ('staff_inventaris', 'staff_crm', 'staff_qms', 'staff_fa', 'tu', 'engineering', 'cso', 'gardener');

-- Hak Akses Atasan / Pimpinan Unit (lihat, kelola)
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'lihat' FROM roles WHERE kode IN ('spv_inventaris', 'manajer_markom', 'spv_crm', 'manajer_qms', 'manajer_fa', 'kepala_sekolah', 'spv_sapras', 'team_leader', 'manajer_hrd', 'staff_hrd');
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'kelola' FROM roles WHERE kode IN ('spv_inventaris', 'manajer_markom', 'spv_crm', 'manajer_qms', 'manajer_fa', 'kepala_sekolah', 'spv_sapras', 'team_leader', 'manajer_hrd', 'staff_hrd');

-- Cabut izin karyawan:monitoring dari manajer_hrd dan staff_hrd untuk database yang sudah ada (Notulen Rapat HRD)
DELETE FROM hak_akses 
WHERE modul = 'karyawan' 
  AND aksi = 'monitoring' 
  AND role_id IN (SELECT id FROM roles WHERE kode IN ('manajer_hrd', 'staff_hrd'));

-- Cabut izin karyawan:monitoring dari manajer_ga agar Manajer GA hanya memantau bawahannya sesuai hierarki
DELETE FROM hak_akses 
WHERE modul = 'karyawan' 
  AND aksi = 'monitoring' 
  AND role_id IN (SELECT id FROM roles WHERE kode = 'manajer_ga');

-- Izin khusus 'kelola_struktur' dan 'ubah_status' pada modul 'karyawan' hanya untuk admin dan manajer_hrd
INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'kelola_struktur' FROM roles WHERE kode IN ('admin', 'manajer_hrd');

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'ubah_status' FROM roles WHERE kode IN ('admin', 'manajer_hrd');


-- F. Pengisian Data Karyawan untuk Akun yang Belum Memiliki Baris Karyawan (Idempotent)
-- Level 1: Atasan / Peran Mandiri (atasan_id NULL)
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'ADM-0001', u.name, 'Administrator', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'admin' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'INV-0001', u.name, 'Staff Inventaris', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'staffinventaris' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'FA-0001', u.name, 'Manajer FA', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'manajerfa' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SD-0001', u.name, 'Kepala Sekolah SD', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'kepsek_sd' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'TK-0001', u.name, 'Kepala Sekolah TK', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'kepsek_tk' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SMP-0001', u.name, 'Kepala Sekolah SMP', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'kepsek_smp' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SMA-0001', u.name, 'Kepala Sekolah SMA', u.unit_id, NULL, 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'kepsek_sma' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

-- Field staff GA tanpa mapping atasan khusus
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0009', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso2' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0010', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso3' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0011', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso4' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0012', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso5' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0013', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso6' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0014', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso7' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0015', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso8' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0016', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso9' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0017', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso10' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0018', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso11' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0019', u.name, 'CSO', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'cso12' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0020', u.name, 'Gardener', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'gardener1' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0021', u.name, 'Gardener', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'gardener2' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0022', u.name, 'Gardener', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'gardener3' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0023', u.name, 'Gardener', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'gardener4' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0025', u.name, 'Gardener', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'gardener5' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'GA-0024', u.name, 'Engineering', u.unit_id, NULL, 'aktif', '2024-01-01', NULL FROM users u WHERE u.username = 'engineering2' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

-- Level 2: Atasan Menengah (SPV CRM -> atasan: Manajer MARKOM)
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'CRM-0001', u.name, 'SPV CRM', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='manajermarkom' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'spvcrm' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

-- Level 3: Bawahan Mapped (Staff CRM, Staff QMS, TU unit, Staff FA)
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'CRM-0002', u.name, 'Staff CRM', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='spvcrm' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'staffcrm' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'QM-0002', u.name, 'Staff QMS', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='manajerqms' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'staffqms' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SD-0002', u.name, 'Staff TU SD', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='kepsek_sd' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'tu_sd' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'TK-0002', u.name, 'Staff TU TK', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='kepsek_tk' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'tu_tk' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SMP-0002', u.name, 'Staff TU SMP', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='kepsek_smp' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'tu_smp' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'SMA-0002', u.name, 'Staff TU SMA', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='kepsek_sma' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'tu_sma' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk, kontak)
SELECT u.id, 'FA-0002', u.name, 'Staff FA', u.unit_id, (SELECT k.id FROM karyawan k JOIN users u2 ON u2.id=k.user_id WHERE u2.username='manajerfa' LIMIT 1), 'aktif', '2024-01-01', NULL
FROM users u WHERE u.username = 'stafffa' AND NOT EXISTS (SELECT 1 FROM karyawan WHERE user_id = u.id);

-- G. Sinkronisasi Data Karyawan dengan Kelola Akun Pengguna (Tanpa Menghapus Karyawan Non-Login)
-- 1. Jika ada karyawan yang menunjuk user_id yang sudah tidak ada di tabel users, lepaskan tautan user_id-nya
UPDATE karyawan
SET user_id = NULL
WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users);

-- 2. Update data karyawan yang terhubung dengan akun pengguna agar sinkron dengan tabel users & roles
UPDATE karyawan k
JOIN users u ON u.id = k.user_id
JOIN roles r ON r.id = u.role_id
SET 
  k.nama = u.name,
  k.jabatan = r.nama_role,
  k.divisi_id = COALESCE(k.divisi_id, u.unit_id),
  k.status = u.status;

-- 5. Pastikan setiap akun pengguna memiliki baris di tabel karyawan (idempotent jika ada akun baru di hosting)
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status, tanggal_masuk)
SELECT 
  u.id, 
  CONCAT('EMP-', LPAD(u.id, 4, '0')), 
  u.name, 
  r.nama_role, 
  u.unit_id, 
  NULL, 
  u.status, 
  '2024-01-01'
FROM users u
JOIN roles r ON r.id = u.role_id
WHERE NOT EXISTS (SELECT 1 FROM karyawan k WHERE k.user_id = u.id);

-- 6. Atur atasan langsung personil lapangan GA ke Team Leader, dan Staff Inventaris ke SPV Inventaris
UPDATE karyawan 
SET atasan_id = (SELECT id FROM (SELECT k2.id FROM karyawan k2 JOIN users u2 ON u2.id = k2.user_id WHERE u2.username = 'teamleader' LIMIT 1) AS t)
WHERE jabatan IN ('CSO', 'Gardener', 'Engineering') AND atasan_id IS NULL;

UPDATE karyawan 
SET atasan_id = (SELECT id FROM (SELECT k2.id FROM karyawan k2 JOIN users u2 ON u2.id = k2.user_id WHERE u2.username = 'spvinventaris' LIMIT 1) AS t)
WHERE user_id = (SELECT id FROM users WHERE username = 'staffinventaris' LIMIT 1) AND atasan_id IS NULL;

-- H. Migrasi Audit Trail Log Aktivitas & Hak Akses Modul Log Aktivitas
-- 1. Modifikasi kolom tabel log_aktivitas (user_id NULL-able, penambahan snapshot user/role/ip, perbesar aktivitas)
ALTER TABLE log_aktivitas MODIFY COLUMN user_id INT NULL DEFAULT NULL;
ALTER TABLE log_aktivitas ADD COLUMN IF NOT EXISTS nama_user VARCHAR(100) DEFAULT NULL;
ALTER TABLE log_aktivitas ADD COLUMN IF NOT EXISTS role_user VARCHAR(40) DEFAULT NULL;
ALTER TABLE log_aktivitas ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT NULL;
ALTER TABLE log_aktivitas MODIFY COLUMN aktivitas VARCHAR(500) NOT NULL;

-- 2. Ubah Foreign Key constraint user_id agar ON DELETE SET NULL (idempotent)
SET @fk_cascade = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.REFERENTIAL_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'log_aktivitas' 
      AND REFERENCED_TABLE_NAME = 'users'
      AND DELETE_RULE != 'SET NULL'
    LIMIT 1
);

SET @drop_fk = IF(@fk_cascade IS NOT NULL, CONCAT('ALTER TABLE log_aktivitas DROP FOREIGN KEY `', @fk_cascade, '`'), 'SELECT 1');
PREPARE stmt_drop_fk FROM @drop_fk;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

SET @has_fk_set_null = (
    SELECT COUNT(*) 
    FROM information_schema.REFERENTIAL_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'log_aktivitas' 
      AND REFERENCED_TABLE_NAME = 'users'
      AND DELETE_RULE = 'SET NULL'
);

SET @add_fk = IF(@has_fk_set_null = 0, 'ALTER TABLE log_aktivitas ADD CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt_add_fk FROM @add_fk;
EXECUTE stmt_add_fk;
DEALLOCATE PREPARE stmt_add_fk;

-- 3. Backfill nama_user dan role_user untuk data log yang sudah ada
UPDATE log_aktivitas l
JOIN users u ON u.id = l.user_id
JOIN roles r ON r.id = u.role_id
SET 
  l.nama_user = COALESCE(l.nama_user, u.name),
  l.role_user = COALESCE(l.role_user, r.nama_role)
WHERE l.nama_user IS NULL OR l.role_user IS NULL;

-- 4. Hak Akses Modul Log Aktivitas (Admin, Manajer HRD, Manajer GA)
INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'log_aktivitas', 'lihat' FROM roles WHERE kode IN ('admin', 'manajer_hrd', 'manajer_ga');

-- ═══════════════════════════════════════════════════════════════════
-- I. PEMBERSIHAN SKOR PENILAIAN DARI LOG AKTIVITAS (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════
-- Menghapus skor nilai dari riwayat log aktivitas modul karyawan agar tidak terlihat HRD
UPDATE log_aktivitas
SET aktivitas = TRIM(SUBSTRING_INDEX(aktivitas, ' (Skor:', 1))
WHERE modul = 'karyawan' AND aktivitas LIKE '%penilaian%' AND aktivitas LIKE '%(Skor:%';

UPDATE log_aktivitas
SET aktivitas = TRIM(SUBSTRING_INDEX(aktivitas, ' (skor:', 1))
WHERE modul = 'karyawan' AND aktivitas LIKE '%penilaian%' AND aktivitas LIKE '%(skor:%';

UPDATE log_aktivitas
SET aktivitas = TRIM(SUBSTRING_INDEX(aktivitas, ' skor:', 1))
WHERE modul = 'karyawan' AND aktivitas LIKE '%penilaian%' AND aktivitas LIKE '% skor:%';

UPDATE log_aktivitas
SET aktivitas = TRIM(SUBSTRING_INDEX(aktivitas, ' Skor:', 1))
WHERE modul = 'karyawan' AND aktivitas LIKE '%penilaian%' AND aktivitas LIKE '% Skor:%';

-- ═══════════════════════════════════════════════════════════════════
-- J. PEMISAHAN MODUL LOG KINERJA KARYAWAN & HIERARKI GA (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Pisahkan baris log tugas, kegiatan, dan penilaian ke modul 'karyawan_kinerja'
UPDATE log_aktivitas
SET modul = 'karyawan_kinerja'
WHERE modul = 'karyawan'
  AND (
    aktivitas LIKE 'Memberikan kegiatan%'
    OR aktivitas LIKE 'Memberikan tugas%'
    OR aktivitas LIKE 'Memperbarui status tugas%'
    OR aktivitas LIKE 'Menghapus penugasan%'
    OR aktivitas LIKE '%penilaian%'
  );

-- 2. Sambungkan Staff Inventaris ke SPV Inventaris (idempotent, hanya jika atasan_id IS NULL)
UPDATE karyawan 
SET atasan_id = (SELECT id FROM (SELECT k2.id FROM karyawan k2 JOIN users u2 ON u2.id = k2.user_id WHERE u2.username = 'spvinventaris' LIMIT 1) AS t)
WHERE user_id = (SELECT id FROM users WHERE username = 'staffinventaris' LIMIT 1) AND atasan_id IS NULL;

-- 3. Sambungkan Team Leader ke SPV Sapras (idempotent, hanya jika atasan_id IS NULL)
UPDATE karyawan 
SET atasan_id = (SELECT id FROM (SELECT k2.id FROM karyawan k2 JOIN users u2 ON u2.id = k2.user_id WHERE u2.username = 'spvsapras' LIMIT 1) AS t)
WHERE user_id = (SELECT id FROM users WHERE username = 'teamleader' LIMIT 1) AND atasan_id IS NULL;

-- ═══════════════════════════════════════════════════════════════════
-- K. FITUR SATU AKUN = SATU SESI AKTIF (TABEL user_sessions)
-- ═══════════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  `login_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════
-- L. PENILAIAN KHUSUS SPV SAPRAS (CSO/GARDENER), WEWENANG HRD,
--    DAN UNIT SENSITIF PIMPINAN/DIREKSI (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 0. Backup tabel karyawan dan hak_akses sebelum perubahan (idempotent)
CREATE TABLE IF NOT EXISTS `karyawan_backup_20261101` AS SELECT * FROM `karyawan`;
CREATE TABLE IF NOT EXISTS `hak_akses_backup_20261101` AS SELECT * FROM `hak_akses`;

-- 1. Tugas 1b: Hak 'penilaian' pada modul 'karyawan' HANYA untuk spv_sapras
DELETE ha FROM hak_akses ha
JOIN roles r ON r.id = ha.role_id
WHERE ha.modul = 'karyawan'
  AND ha.aksi = 'penilaian'
  AND r.kode != 'spv_sapras';

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'penilaian' FROM roles WHERE kode = 'spv_sapras';

-- 2. Tugas 2: Hak administratif HRD (staff_hrd vs manajer_hrd) via tabel hak_akses
-- Izin 'kelola_master' untuk tambah/edit data administratif karyawan (admin, manajer_hrd, staff_hrd)
DELETE ha FROM hak_akses ha
JOIN roles r ON r.id = ha.role_id
WHERE ha.modul = 'karyawan'
  AND ha.aksi = 'kelola_master'
  AND r.kode NOT IN ('admin', 'manajer_hrd', 'staff_hrd');

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'kelola_master' FROM roles WHERE kode IN ('admin', 'manajer_hrd', 'staff_hrd');

-- Izin 'hapus', 'kelola_struktur', 'ubah_status' hanya untuk manajer_hrd dan admin (bukan staff_hrd)
DELETE ha FROM hak_akses ha
JOIN roles r ON r.id = ha.role_id
WHERE ha.modul = 'karyawan'
  AND ha.aksi IN ('hapus', 'kelola_struktur', 'ubah_status')
  AND r.kode NOT IN ('admin', 'manajer_hrd');

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'hapus' FROM roles WHERE kode IN ('admin', 'manajer_hrd');

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'kelola_struktur' FROM roles WHERE kode IN ('admin', 'manajer_hrd');

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'ubah_status' FROM roles WHERE kode IN ('admin', 'manajer_hrd');

-- 3. Tugas 3d: Izin 'lihat_pimpinan' HANYA untuk manajer_hrd
DELETE ha FROM hak_akses ha
JOIN roles r ON r.id = ha.role_id
WHERE ha.modul = 'karyawan'
  AND ha.aksi = 'lihat_pimpinan'
  AND r.kode != 'manajer_hrd';

INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'lihat_pimpinan' FROM roles WHERE kode = 'manajer_hrd';

-- 4. Tugas 3a: Buat unit baru "Pimpinan/Direksi" di struktur_unit (idempotent)
INSERT INTO struktur_unit (nama_unit, tipe)
SELECT 'Pimpinan/Direksi', 'divisi'
WHERE NOT EXISTS (SELECT 1 FROM struktur_unit WHERE nama_unit = 'Pimpinan/Direksi');

-- 5. Tugas 3b, 3c, 3e: Pindahkan Direktur, GMO, GMP ke unit Pimpinan/Direksi,
--    seragamkan NIP menjadi PIM-0001..0003 (id tetap), dan pastikan hierarki GMO/GMP -> Direktur
SET @pim_unit_id = (SELECT id FROM struktur_unit WHERE nama_unit = 'Pimpinan/Direksi' LIMIT 1);

UPDATE karyawan
SET nip = 'PIM-0001',
    divisi_id = @pim_unit_id,
    atasan_id = NULL
WHERE jabatan = 'Direktur';

SET @direktur_id = (SELECT id FROM karyawan WHERE jabatan = 'Direktur' LIMIT 1);

UPDATE karyawan
SET nip = 'PIM-0002',
    divisi_id = @pim_unit_id,
    atasan_id = @direktur_id
WHERE jabatan IN ('General Manager Operasional', 'GMO');

UPDATE karyawan
SET nip = 'PIM-0003',
    divisi_id = @pim_unit_id,
    atasan_id = @direktur_id
WHERE jabatan IN ('General Manager Pendidikan', 'GMP');

-- 6. Hilangkan akses menu Laporan & Monitoring untuk HRD (manajer_hrd & staff_hrd)
DELETE ha FROM hak_akses ha
JOIN roles r ON r.id = ha.role_id
WHERE ha.modul = 'laporan'
  AND r.kode IN ('manajer_hrd', 'staff_hrd');

-- ═══════════════════════════════════════════════════════════════════
-- M. TUGAS RUTIN MANDIRI CSO & GARDENER, FOTO DOKUMENTASI,
--    DAN PENILAIAN HARIAN TERKUNCI PERMANEN (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Backup tabel kegiatan_karyawan, penilaian_kinerja, hak_akses
CREATE TABLE IF NOT EXISTS `kegiatan_karyawan_backup_20261103` AS SELECT * FROM `kegiatan_karyawan`;
CREATE TABLE IF NOT EXISTS `penilaian_kinerja_backup_20261103` AS SELECT * FROM `penilaian_kinerja`;
CREATE TABLE IF NOT EXISTS `hak_akses_backup_20261103` AS SELECT * FROM `hak_akses`;

-- 2. Tabel kegiatan_foto (maksimal 3 foto per tugas rutin)
CREATE TABLE IF NOT EXISTS `kegiatan_foto` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kegiatan_id` INT NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_kegiatan_foto_kegiatan` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan_karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel penilaian_harian (1 nilai per karyawan per tanggal, terkunci permanen)
CREATE TABLE IF NOT EXISTS `penilaian_harian` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `karyawan_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `nilai` TINYINT(1) NOT NULL,
  `catatan` TEXT DEFAULT NULL,
  `dinilai_oleh` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_penilaian_harian_karyawan_tanggal` (`karyawan_id`, `tanggal`),
  KEY `idx_penilaian_harian_tgl` (`tanggal`),
  CONSTRAINT `fk_penilaian_harian_karyawan` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_penilaian_harian_dinilai` FOREIGN KEY (`dinilai_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Izin tugas_rutin_sendiri untuk role cso dan gardener
INSERT IGNORE INTO hak_akses (role_id, modul, aksi)
SELECT id, 'karyawan', 'tugas_rutin_sendiri' FROM roles WHERE kode IN ('cso', 'gardener');

-- ═══════════════════════════════════════════════════════════════════
-- N. NOTIFIKASI LAYAR LANGSUNG (IN-APP BANNER), PREFERENSI USER,
--    DAN PENGINGAT TERJADWAL IDEMPOTEN
-- ═══════════════════════════════════════════════════════════════════

-- 1. Backup tabel notifikasi
CREATE TABLE IF NOT EXISTS `notifikasi_backup_20261104` AS SELECT * FROM `notifikasi`;

-- 2. Modifikasi kolom & indeks tabel notifikasi
ALTER TABLE `notifikasi`
  ADD COLUMN IF NOT EXISTS `kategori` VARCHAR(30) NOT NULL DEFAULT 'info',
  ADD COLUMN IF NOT EXISTS `prioritas` ENUM('normal','darurat') NOT NULL DEFAULT 'normal',
  ADD COLUMN IF NOT EXISTS `link_page` VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS `link_tab` VARCHAR(40) NULL;

ALTER TABLE `notifikasi`
  ADD INDEX IF NOT EXISTS `idx_notif_user_id` (`user_id`, `id`),
  ADD INDEX IF NOT EXISTS `idx_notif_user_unread` (`user_id`, `status_baca`);

-- 3. Tambah kolom prioritas pada pengajuan_maintenance
ALTER TABLE `pengajuan_maintenance`
  ADD COLUMN IF NOT EXISTS `prioritas` ENUM('normal','darurat') NOT NULL DEFAULT 'normal';

-- 4. Tabel preferensi notifikasi pengguna (Gir Pengaturan)
CREATE TABLE IF NOT EXISTS `notif_pref` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `kategori_persetujuan` TINYINT(1) NOT NULL DEFAULT 1,
  `kategori_tugas` TINYINT(1) NOT NULL DEFAULT 1,
  `kategori_pengingat` TINYINT(1) NOT NULL DEFAULT 1,
  `kategori_info` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel log pengingat (mencegah duplikat pengingat malas)
CREATE TABLE IF NOT EXISTS `reminder_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `jenis` VARCHAR(40) NOT NULL,
  `ref_id` INT NULL,
  `tanggal` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_reminder_log` (`user_id`, `jenis`, `ref_id`, `tanggal`),
  INDEX `idx_reminder_user_tgl` (`user_id`, `tanggal`),
  CONSTRAINT `fk_reminder_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Sanitasi privasi: hilangkan angka skor dari teks notifikasi lama
UPDATE `notifikasi`
SET `pesan` = REGEXP_REPLACE(`pesan`, ' \\(Skor: [0-9.]+/5\\)', '.')
WHERE `pesan` LIKE '%Skor%';

-- ═══════════════════════════════════════════════════════════════════
-- O. FASE 0 — DIVISI FA TANPA SPV (SEMENTARA, IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Nonaktifkan akun spvfa secara reversibel (tanpa menghapus user, karyawan, role, atau hak_akses)
UPDATE `users`
SET `status` = 'nonaktif'
WHERE `username` = 'spvfa';

UPDATE `karyawan` k
JOIN `users` u ON u.id = k.user_id
SET k.status = 'nonaktif'
WHERE u.username = 'spvfa';

DELETE us FROM `user_sessions` us
JOIN `users` u ON u.id = us.user_id
WHERE u.username = 'spvfa';

-- 2. Pastikan nama Manajer FA adalah 'Muhamad Fahmi, S.Akun' di users dan karyawan
UPDATE `users`
SET `name` = 'Muhamad Fahmi, S.Akun'
WHERE `username` = 'manajerfa';

UPDATE `karyawan` k
JOIN `users` u ON u.id = k.user_id
SET k.nama = 'Muhamad Fahmi, S.Akun'
WHERE u.username = 'manajerfa';

-- 3. Pastikan manajer_fa memiliki hak lihat dan approve_atasan pada inventaris & maintenance
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'lihat' FROM `roles` WHERE `kode` = 'manajer_fa';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'approve_atasan' FROM `roles` WHERE `kode` = 'manajer_fa';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'lihat' FROM `roles` WHERE `kode` = 'manajer_fa';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'approve_atasan' FROM `roles` WHERE `kode` = 'manajer_fa';

-- ═══════════════════════════════════════════════════════════════════
-- P. FASE 1 — "AJUKAN SENDIRI" OLEH ATASAN / PIMPINAN UNIT (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Modul Inventaris: pastikan hak 'lihat' dan 'ajukan' untuk pimpinan unit
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'lihat' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_sapras', 'manajer_fa', 'spv_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'ajukan' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_sapras', 'manajer_fa', 'spv_fa');

-- 2. Modul Maintenance: pastikan hak 'lihat' dan 'ajukan' untuk pimpinan unit
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'lihat' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_inventaris', 'manajer_fa', 'spv_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'ajukan' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd', 'manajer_qms', 'spv_crm', 'spv_inventaris', 'manajer_fa', 'spv_fa');

-- 3. Modul Event: pastikan hak 'lihat' dan 'ajukan' untuk kepala_sekolah & manajer_hrd (spv_crm TIDAK diberi ajukan event)
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'lihat' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'ajukan' FROM `roles`
WHERE `kode` IN ('kepala_sekolah', 'manajer_hrd');

-- ═══════════════════════════════════════════════════════════════════
-- Q. FASE 2 — "SETUJUI SEBAGAI PENGGANTI" (KOLOM & HAK AKSES, IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Kolom baru pada tabel peminjaman, pengajuan_maintenance, dan event
ALTER TABLE `peminjaman`
  ADD COLUMN IF NOT EXISTS `approval_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_pada` DATETIME NULL DEFAULT NULL;

ALTER TABLE `pengajuan_maintenance`
  ADD COLUMN IF NOT EXISTS `approval_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_pada` DATETIME NULL DEFAULT NULL;

ALTER TABLE `event`
  ADD COLUMN IF NOT EXISTS `approval_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approval_pengganti_pada` DATETIME NULL DEFAULT NULL;

-- 2. Pastikan hak 'lihat' pada modul terkait bagi peran pengganti (admin, manajer_ga, manajer_markom, manajer_fa)
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'lihat' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom', 'manajer_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'lihat' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom', 'manajer_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'lihat' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom');

-- 3. Tambahkan hak 'approve_pengganti' untuk peran pengganti pada ketiga modul
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'approve_pengganti' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom', 'manajer_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'approve_pengganti' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom', 'manajer_fa');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'approve_pengganti' FROM `roles`
WHERE `kode` IN ('admin', 'manajer_ga', 'manajer_markom', 'manajer_fa');

-- 4. Pastikan kolom teks yang menerima alasan/catatan pengganti, log aktivitas, dan pesan notifikasi mendukung karakter UTF-8 4-byte (utf8mb4)
ALTER TABLE `peminjaman`
  MODIFY COLUMN `catatan_atasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;

ALTER TABLE `pengajuan_maintenance`
  MODIFY COLUMN `catatan_atasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;

ALTER TABLE `event`
  MODIFY COLUMN `catatan_atasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;

ALTER TABLE `log_aktivitas`
  MODIFY COLUMN `aktivitas` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;

ALTER TABLE `notifikasi`
  MODIFY COLUMN `pesan` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- 5. Pastikan hak akses pengajuan dan sinkronisasi riwayat untuk staff_crm dan engineering
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'ajukan' FROM `roles` WHERE `kode` = 'staff_crm';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'lihat' FROM `roles` WHERE `kode` IN ('staff_crm', 'engineering');

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'ajukan' FROM `roles` WHERE `kode` = 'staff_crm';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'lihat' FROM `roles` WHERE `kode` = 'staff_crm';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'ajukan' FROM `roles` WHERE `kode` IN ('staff_crm', 'engineering');

-- 6. Pastikan atasan langsung Engineering di tabel karyawan menunjuk ke SPV Sapras (sesuai rantai komando Sapras)
UPDATE `karyawan` k
JOIN `users` u ON u.id = k.user_id
JOIN `roles` r ON r.id = u.role_id
SET k.atasan_id = (
    SELECT spv_id FROM (
        SELECT k2.id AS spv_id
        FROM `karyawan` k2
        JOIN `users` u2 ON u2.id = k2.user_id
        WHERE u2.username = 'spvsapras'
        LIMIT 1
    ) AS tmp_spv
)
WHERE r.kode = 'engineering';

-- ═══════════════════════════════════════════════════════════════════
-- R. FASE 3 — PENYELARASAN ALUR EVENT + VERIFIKASI & APPROVE AKHIR SEBAGAI PENGGANTI + REMINDER 24 JAM (IDEMPOTENT)
-- ═══════════════════════════════════════════════════════════════════

-- 1. Pastikan pengajuan_maintenance memiliki kolom created_at untuk perhitungan eskalasi 24 jam
ALTER TABLE `pengajuan_maintenance`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 2. Tambahkan kolom audit Verifikasi Pengganti & Approve Akhir Pengganti pada peminjaman, pengajuan_maintenance, dan event
ALTER TABLE `peminjaman`
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_pada` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_pada` DATETIME NULL DEFAULT NULL;

ALTER TABLE `pengajuan_maintenance`
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_pada` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_pada` DATETIME NULL DEFAULT NULL;

ALTER TABLE `event`
  ADD COLUMN IF NOT EXISTS `catatan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `verifikasi_pengganti_pada` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_oleh` INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_alasan` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approve_akhir_pengganti_pada` DATETIME NULL DEFAULT NULL;

-- 3. Ubah Alur Pengelola Event: Verifikasi oleh Staff CRM (staff_crm), Approve Akhir oleh SPV CRM (spv_crm)
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'verifikasi' FROM `roles` WHERE `kode` = 'staff_crm';

DELETE h FROM `hak_akses` h
JOIN `roles` r ON r.id = h.role_id
WHERE r.kode = 'spv_crm' AND h.modul = 'event' AND h.aksi = 'verifikasi';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'approve' FROM `roles` WHERE `kode` = 'spv_crm';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'kelola' FROM `roles` WHERE `kode` = 'spv_crm';

DELETE h FROM `hak_akses` h
JOIN `roles` r ON r.id = h.role_id
WHERE r.kode = 'manajer_markom' AND h.modul = 'event' AND h.aksi = 'approve';

-- 4. Hak akses Verifikasi sebagai Pengganti (verifikasi_pengganti)
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'verifikasi_pengganti' FROM `roles` WHERE `kode` = 'spv_inventaris';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'verifikasi_pengganti' FROM `roles` WHERE `kode` = 'spv_sapras';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'verifikasi_pengganti' FROM `roles` WHERE `kode` = 'spv_crm';

-- 5. Hak akses Approve Akhir sebagai Pengganti (approve_akhir_pengganti)
INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'inventaris', 'approve_akhir_pengganti' FROM `roles` WHERE `kode` = 'manajer_ga';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'maintenance', 'approve_akhir_pengganti' FROM `roles` WHERE `kode` = 'manajer_ga';

INSERT IGNORE INTO `hak_akses` (`role_id`, `modul`, `aksi`)
SELECT `id`, 'event', 'approve_akhir_pengganti' FROM `roles` WHERE `kode` = 'manajer_markom';




