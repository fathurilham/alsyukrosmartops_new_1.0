-- =====================================================================
-- AL-SYUKROSMART OPS — SKEMA DATABASE (REBUILD TOTAL)
-- Disusun mengikuti PERSIS struktur organisasi & rancangan data pada:
--   - Laporan Model 1 (Analisis & Perancangan Sistem)
--   - Laporan Model 2 (Perancangan UI/UX & Struktur Database)
--   - RENCANA KAK - Project Work
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifikasi, laporan, backup_arsip, arsip, kategori_arsip,
  dokumentasi_event, jadwal_event, event, proses_maintenance, pengajuan_maintenance,
  fasilitas, pengembalian, peminjaman, transaksi_barang, barang, penilaian_kinerja,
  kegiatan_karyawan, karyawan, pengajuan_unit, log_aktivitas, hak_akses, users,
  struktur_unit, roles;

-- ---------------------------------------------------------------------
-- 1. PENGGUNA & HAK AKSES
-- ---------------------------------------------------------------------

-- Daftar peran PERSIS sesuai struktur organisasi pada 3 dokumen acuan.
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(40) NOT NULL UNIQUE,
  nama_role VARCHAR(80) NOT NULL,
  deskripsi VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

INSERT INTO roles (kode, nama_role, deskripsi) VALUES
('admin',           'Administrator Sistem', 'Akses penuh untuk keperluan administrasi & konfigurasi sistem'),
('manajer_ga',      'Manajer GA',            'Monitoring inventaris/peminjaman, maintenance, kegiatan & penilaian karyawan sesuai kewenangan'),
('spv_inventaris',  'SPV Inventaris',        'Memeriksa hasil verifikasi & memberi approval/penolakan peminjaman, memantau transaksi'),
('staff_inventaris','Staff Inventaris',      'Mengelola data inventaris, memverifikasi pengajuan, konfirmasi pengembalian'),
('spv_sapras',      'SPV Sapras',            'Approve/menolak pengajuan maintenance & monitoring proses perbaikan'),
('team_leader',     'Team Leader (TL)',      'Memeriksa pengajuan maintenance & mengelola kegiatan karyawan (CSO/Gardener)'),
('engineering',     'Engineering',           'Menjalankan pekerjaan maintenance yang telah disetujui'),
('cso',             'CSO',                   'Menerima & menjalankan kegiatan, memberikan laporan hasil kegiatan'),
('gardener',        'Gardener',              'Menerima & menjalankan kegiatan, memberikan laporan hasil kegiatan'),
('manajer_hrd',     'Manajer HRD',           'Administrasi, pengumpulan, rekapitulasi & pengarsipan data karyawan; approve pengajuan lintas modul sebelum diteruskan'),
('staff_hrd',        'Staff HRD',             'Administrasi data karyawan; membuat pengajuan kebutuhan (inventaris/maintenance/event) untuk diteruskan ke Manajer HRD'),
('manajer_markom',  'Manajer MARKOM',        'Monitoring event, jadwal & approval sesuai kewenangan'),
('spv_crm',         'SPV CRM',               'Memeriksa & memverifikasi pengajuan event serta memantau pelaksanaan'),
('staff_crm',       'Staff CRM',             'Menerima, memeriksa, memverifikasi & mengelola jadwal event'),
('manajer_qms',     'Manajer QMS',           'Mengelola & memonitor arsip digital, laporan, & backup sesuai kewenangan'),
('staff_qms',       'Staff QMS',             'Mengelola & memonitor arsip digital sesuai tingkat akses'),
('tu',              'TU',                    'Membuat pengajuan/request pada modul yang dibutuhkan unit pendidikan'),
('kepala_sekolah',  'Kepala Sekolah',        'Memvalidasi & menyetujui pengajuan dari unit pendidikan');

-- Struktur unit/divisi (GA, HRD, MARKOM, QMS, serta unit pendidikan TK/SD/SMP/SMA)
CREATE TABLE struktur_unit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_unit VARCHAR(60) NOT NULL,
  tipe ENUM('divisi','unit_pendidikan') NOT NULL
) ENGINE=InnoDB;

INSERT INTO struktur_unit (nama_unit, tipe) VALUES
('General Affair (GA)','divisi'),
('HRD','divisi'),
('MARKOM / CRM','divisi'),
('QMS','divisi'),
('TK Al-Syukro','unit_pendidikan'),
('SD Al-Syukro','unit_pendidikan'),
('SMP Al-Syukro','unit_pendidikan'),
('SMA Al-Syukro','unit_pendidikan');

-- Akun login seluruh aktor
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  role_id INT NOT NULL,
  unit_id INT DEFAULT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (unit_id) REFERENCES struktur_unit(id)
) ENGINE=InnoDB;

-- Matriks hak akses per peran per modul per aksi
CREATE TABLE hak_akses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  modul VARCHAR(30) NOT NULL,       -- inventaris | karyawan | event | maintenance | arsip | laporan | pengajuan_unit
  aksi VARCHAR(30) NOT NULL,        -- lihat | tambah | kelola | verifikasi | approve | monitoring | input | penilaian | ajukan
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  UNIQUE KEY uq_hak (role_id, modul, aksi)
) ENGINE=InnoDB;

-- Pencatatan aktivitas pengguna
CREATE TABLE log_aktivitas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  modul VARCHAR(30) NOT NULL,
  aktivitas VARCHAR(255) NOT NULL,
  waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. KARYAWAN
-- ---------------------------------------------------------------------
CREATE TABLE karyawan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  nip VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  jabatan VARCHAR(60) NOT NULL,
  divisi_id INT DEFAULT NULL,
  atasan_id INT DEFAULT NULL,        -- self-reference: struktur GA (Manajer GA > SPV > TL > CSO/Gardener/Engineering)
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (divisi_id) REFERENCES struktur_unit(id),
  FOREIGN KEY (atasan_id) REFERENCES karyawan(id)
) ENGINE=InnoDB;

CREATE TABLE kegiatan_karyawan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  karyawan_id INT NOT NULL,
  assigned_by INT DEFAULT NULL,      -- users.id atasan pemberi kegiatan
  jenis ENUM('rutin','non-rutin') NOT NULL DEFAULT 'rutin',
  deskripsi TEXT NOT NULL,
  tanggal DATE NOT NULL,
  status ENUM('ditugaskan','dikerjakan','selesai') NOT NULL DEFAULT 'ditugaskan',
  hasil_laporan TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (karyawan_id) REFERENCES karyawan(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE penilaian_kinerja (
  id INT AUTO_INCREMENT PRIMARY KEY,
  karyawan_id INT NOT NULL,
  penilai_id INT NOT NULL,           -- users.id (SPV Sapras / Manajer GA sesuai kewenangan)
  periode VARCHAR(20) NOT NULL,      -- ex: 2026-09
  nilai INT NOT NULL,
  catatan TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (karyawan_id) REFERENCES karyawan(id) ON DELETE CASCADE,
  FOREIGN KEY (penilai_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. INVENTARIS & PEMINJAMAN BARANG
-- ---------------------------------------------------------------------
CREATE TABLE barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_barang VARCHAR(30) NOT NULL UNIQUE,
  nama_barang VARCHAR(100) NOT NULL,
  kategori VARCHAR(60) DEFAULT NULL,
  lokasi VARCHAR(80) DEFAULT NULL,
  stok INT NOT NULL DEFAULT 0,
  status ENUM('tersedia','habis','rusak') NOT NULL DEFAULT 'tersedia',
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE transaksi_barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  jenis ENUM('masuk','keluar') NOT NULL,
  jumlah INT NOT NULL,
  tanggal DATE NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  user_id INT DEFAULT NULL,
  FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE peminjaman (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  jumlah INT NOT NULL DEFAULT 1,
  peminjam_id INT NOT NULL,
  tanggal_pinjam DATE NOT NULL,
  rencana_kembali DATE DEFAULT NULL,
  keperluan VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'diajukan',
  verifikator_id INT DEFAULT NULL,   -- Staff Inventaris
  atasan_id INT DEFAULT NULL,        -- Atasan Unit
  catatan_atasan VARCHAR(255) DEFAULT NULL,
  approval_id INT DEFAULT NULL,      -- SPV Inventaris
  catatan VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (barang_id) REFERENCES barang(id),
  FOREIGN KEY (peminjam_id) REFERENCES users(id),
  FOREIGN KEY (verifikator_id) REFERENCES users(id),
  FOREIGN KEY (atasan_id) REFERENCES users(id),
  FOREIGN KEY (approval_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE pengembalian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  peminjaman_id INT NOT NULL,
  tanggal_kembali DATE NOT NULL,
  kondisi_barang ENUM('baik','rusak_ringan','rusak_berat','hilang') NOT NULL DEFAULT 'baik',
  petugas_id INT DEFAULT NULL,       -- Staff Inventaris yang konfirmasi
  catatan VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (peminjaman_id) REFERENCES peminjaman(id) ON DELETE CASCADE,
  FOREIGN KEY (petugas_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. MAINTENANCE FASILITAS
-- ---------------------------------------------------------------------
CREATE TABLE fasilitas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_fasilitas VARCHAR(100) NOT NULL,
  lokasi VARCHAR(100) DEFAULT NULL,
  kondisi_terkini ENUM('baik','perlu_perhatian','rusak') NOT NULL DEFAULT 'baik'
) ENGINE=InnoDB;

CREATE TABLE pengajuan_maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fasilitas_id INT NOT NULL,
  pemohon_id INT NOT NULL,
  deskripsi_kerusakan TEXT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'diajukan',
  tl_verifikasi_id INT DEFAULT NULL, -- Team Leader
  spv_approval_id INT DEFAULT NULL,  -- SPV Sapras
  atasan_id INT DEFAULT NULL,        -- Atasan Unit
  catatan_atasan VARCHAR(255) DEFAULT NULL,
  tanggal_pengajuan DATE NOT NULL,
  catatan VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (fasilitas_id) REFERENCES fasilitas(id),
  FOREIGN KEY (pemohon_id) REFERENCES users(id),
  FOREIGN KEY (tl_verifikasi_id) REFERENCES users(id),
  FOREIGN KEY (spv_approval_id) REFERENCES users(id),
  FOREIGN KEY (atasan_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE proses_maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pengajuan_id INT NOT NULL,
  teknisi_id INT DEFAULT NULL,       -- Engineering
  tanggal_mulai DATE DEFAULT NULL,
  tanggal_selesai DATE DEFAULT NULL,
  hasil_pekerjaan TEXT DEFAULT NULL,
  FOREIGN KEY (pengajuan_id) REFERENCES pengajuan_maintenance(id) ON DELETE CASCADE,
  FOREIGN KEY (teknisi_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. EVENT MANAGEMENT SYSTEM
-- ---------------------------------------------------------------------
CREATE TABLE event (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_event VARCHAR(120) NOT NULL,
  pemohon_id INT NOT NULL,
  deskripsi TEXT DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'diajukan',
  verifikator_id INT DEFAULT NULL,   -- SPV CRM / Staff CRM
  approval_id INT DEFAULT NULL,      -- Manajer MARKOM
  atasan_id INT DEFAULT NULL,        -- Atasan Unit
  catatan_atasan VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pemohon_id) REFERENCES users(id),
  FOREIGN KEY (verifikator_id) REFERENCES users(id),
  FOREIGN KEY (approval_id) REFERENCES users(id),
  FOREIGN KEY (atasan_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE jadwal_event (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  tanggal DATE NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  lokasi VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dokumentasi_event (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  tanggal_unggah DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES event(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. ARSIP DIGITAL
-- ---------------------------------------------------------------------
CREATE TABLE kategori_arsip (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(60) NOT NULL,
  deskripsi VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE arsip (
  id INT AUTO_INCREMENT PRIMARY KEY,
  judul_dokumen VARCHAR(150) NOT NULL,
  kategori_id INT DEFAULT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploader_id INT NOT NULL,
  tingkat_akses ENUM('umum','terbatas','rahasia') NOT NULL DEFAULT 'umum',
  tanggal_unggah DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (kategori_id) REFERENCES kategori_arsip(id),
  FOREIGN KEY (uploader_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE backup_arsip (
  id INT AUTO_INCREMENT PRIMARY KEY,
  arsip_id INT DEFAULT NULL,
  tanggal_backup DATETIME DEFAULT CURRENT_TIMESTAMP,
  lokasi_backup VARCHAR(255) NOT NULL,
  FOREIGN KEY (arsip_id) REFERENCES arsip(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. LAPORAN & MONITORING
-- ---------------------------------------------------------------------
CREATE TABLE laporan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jenis_laporan VARCHAR(80) NOT NULL,
  modul_asal VARCHAR(30) NOT NULL,
  periode VARCHAR(20) DEFAULT NULL,
  generated_by INT DEFAULT NULL,
  tanggal_generate DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifikasi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  modul_asal VARCHAR(30) NOT NULL,
  pesan VARCHAR(255) NOT NULL,
  status_baca ENUM('belum','sudah') NOT NULL DEFAULT 'belum',
  waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. ALUR KHUSUS UNIT PENDIDIKAN (TU -> Kepala Sekolah -> Modul terkait)
-- ---------------------------------------------------------------------
-- Jembatan pengajuan LINTAS DIVISI: setiap peran "staff/SPV" yang bukan
-- pengelola modul (inventaris/maintenance/event) mengajukan kebutuhan di sini,
-- lalu atasan langsungnya (approver_role) menyetujui SEBELUM diteruskan ke
-- pengelola modul terkait — sesuai rancangan alur pada dokumen (setiap divisi
-- punya pola "approve sebelum mengirim pengajuan" yang sama, bukan hanya
-- TU -> Kepala Sekolah).
CREATE TABLE pengajuan_unit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_id INT DEFAULT NULL,          -- struktur_unit pemohon (divisi atau unit pendidikan), jika ada
  pemohon_id INT NOT NULL,
  approver_role VARCHAR(40) NOT NULL, -- kode role atasan yang wajib approve (mis. kepala_sekolah, manajer_hrd, manajer_qms, manajer_markom, spv_sapras, spv_inventaris)
  jenis_modul ENUM('inventaris','maintenance','event') NOT NULL,
  deskripsi TEXT NOT NULL,
  status ENUM('diajukan','disetujui','ditolak','diteruskan') NOT NULL DEFAULT 'diajukan',
  approver_id INT DEFAULT NULL,
  catatan_atasan VARCHAR(255) DEFAULT NULL,
  ref_id INT DEFAULT NULL,           -- id record di modul terkait setelah diteruskan
  tanggal_pengajuan DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (unit_id) REFERENCES struktur_unit(id),
  FOREIGN KEY (pemohon_id) REFERENCES users(id),
  FOREIGN KEY (approver_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- --- USERS (1 akun contoh per peran; password semua = "password123") ---
-- Hash bcrypt untuk "password123":
-- $2y$10$oX0z8n1F5mYtqvz5nQ0Wl.9E7l1nH2pXWH5b0dQeYdVYb3fW9m8Nu (placeholder, generate ulang di app)
INSERT INTO users (username, email, password, name, role_id, unit_id, status) VALUES
('admin',        'admin@alsyukro.sch.id',   '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Administrator Sistem', (SELECT id FROM roles WHERE kode='admin'), NULL, 'aktif'),
('manajerga',    'manajerga@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Budi Santoso',  (SELECT id FROM roles WHERE kode='manajer_ga'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('spvinventaris','spvinv@alsyukro.sch.id',  '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Dewi Anggraini',(SELECT id FROM roles WHERE kode='spv_inventaris'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('staffinventaris','staffinv@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Rian Pratama', (SELECT id FROM roles WHERE kode='staff_inventaris'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('spvsapras',    'spvsapras@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Hendra Wijaya', (SELECT id FROM roles WHERE kode='spv_sapras'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('teamleader',   'tl@alsyukro.sch.id',      '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Agus Salim',     (SELECT id FROM roles WHERE kode='team_leader'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('engineering',  'engineering@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Joko Susilo',  (SELECT id FROM roles WHERE kode='engineering'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('cso1',         'cso1@alsyukro.sch.id',    '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Slamet Riyadi',  (SELECT id FROM roles WHERE kode='cso'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('gardener1',    'gardener1@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Warno',         (SELECT id FROM roles WHERE kode='gardener'), (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), 'aktif'),
('manajerhrd',   'manajerhrd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Ratna Sari Dewi',(SELECT id FROM roles WHERE kode='manajer_hrd'), (SELECT id FROM struktur_unit WHERE nama_unit='HRD'), 'aktif'),
('staffhrd',     'staffhrd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Anisa Rahmawati', (SELECT id FROM roles WHERE kode='staff_hrd'), (SELECT id FROM struktur_unit WHERE nama_unit='HRD'), 'aktif'),
('manajermarkom','markom@alsyukro.sch.id',  '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Fitri Handayani',(SELECT id FROM roles WHERE kode='manajer_markom'), (SELECT id FROM struktur_unit WHERE nama_unit='MARKOM / CRM'), 'aktif'),
('spvcrm',       'spvcrm@alsyukro.sch.id',  '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Yusuf Maulana',  (SELECT id FROM roles WHERE kode='spv_crm'), (SELECT id FROM struktur_unit WHERE nama_unit='MARKOM / CRM'), 'aktif'),
('staffcrm',     'staffcrm@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Nadia Putri',    (SELECT id FROM roles WHERE kode='staff_crm'), (SELECT id FROM struktur_unit WHERE nama_unit='MARKOM / CRM'), 'aktif'),
('manajerqms',   'qms@alsyukro.sch.id',     '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Bambang Sutrisno',(SELECT id FROM roles WHERE kode='manajer_qms'), (SELECT id FROM struktur_unit WHERE nama_unit='QMS'), 'aktif'),
('staffqms',     'staffqms@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Sinta Marlina',  (SELECT id FROM roles WHERE kode='staff_qms'), (SELECT id FROM struktur_unit WHERE nama_unit='QMS'), 'aktif'),
('tu_sd',        'tu.sd@alsyukro.sch.id',   '$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66', 'Umi Kalsum',     (SELECT id FROM roles WHERE kode='tu'), (SELECT id FROM struktur_unit WHERE nama_unit='SD Al-Syukro'), 'aktif'),
('kepsek_sd',    'kepsek.sd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Drs. Suherman', (SELECT id FROM roles WHERE kode='kepala_sekolah'), (SELECT id FROM struktur_unit WHERE nama_unit='SD Al-Syukro'), 'aktif');

-- --- MATRIKS HAK AKSES (modul, aksi) per role ---
INSERT INTO hak_akses (role_id, modul, aksi)
SELECT r.id, m.modul, m.aksi FROM roles r
JOIN (
  -- admin: akses penuh ke semua modul & aksi
  SELECT 'admin' rk,'inventaris' modul,'lihat' aksi UNION SELECT 'admin','inventaris','tambah' UNION SELECT 'admin','inventaris','kelola' UNION SELECT 'admin','inventaris','verifikasi' UNION SELECT 'admin','inventaris','approve' UNION SELECT 'admin','inventaris','monitoring'
  UNION SELECT 'admin','karyawan','lihat' UNION SELECT 'admin','karyawan','tambah' UNION SELECT 'admin','karyawan','kelola' UNION SELECT 'admin','karyawan','monitoring' UNION SELECT 'admin','karyawan','penilaian'
  UNION SELECT 'admin','event','lihat' UNION SELECT 'admin','event','tambah' UNION SELECT 'admin','event','kelola' UNION SELECT 'admin','event','verifikasi' UNION SELECT 'admin','event','approve' UNION SELECT 'admin','event','monitoring'
  UNION SELECT 'admin','maintenance','lihat' UNION SELECT 'admin','maintenance','tambah' UNION SELECT 'admin','maintenance','kelola' UNION SELECT 'admin','maintenance','verifikasi' UNION SELECT 'admin','maintenance','approve' UNION SELECT 'admin','maintenance','monitoring'
  UNION SELECT 'admin','arsip','lihat' UNION SELECT 'admin','arsip','tambah' UNION SELECT 'admin','arsip','kelola' UNION SELECT 'admin','arsip','monitoring'
  UNION SELECT 'admin','laporan','lihat' UNION SELECT 'admin','laporan','kelola'
  UNION SELECT 'admin','pengajuan_unit','lihat' UNION SELECT 'admin','pengajuan_unit','kelola'

  -- ═══════════════════════════════════════════════════════════════
  -- Matriks di bawah ini disusun PERSIS mengikuti dokumen acuan baru
  -- "Rancangan Tampilan Berdasarkan Hak Akses" (alur_user.pdf). Pola
  -- "approve sebelum mengirim pengajuan" pada dokumen diimplementasikan
  -- lewat modul jembatan `pengajuan_unit` (lihat/ajukan/approve), BUKAN
  -- lewat akses langsung ke modul inventaris/maintenance — supaya
  -- sidebar tiap role hanya menampilkan modul yang benar-benar ia
  -- kelola langsung, sesuai judul dokumen.
  -- ═══════════════════════════════════════════════════════════════

  -- Manajer GA: memonitoring semua proses & menerima laporan (inventaris,
  -- maintenance, karyawan) + user penyimpan dokumen di Arsip
  UNION SELECT 'manajer_ga','inventaris','lihat' UNION SELECT 'manajer_ga','inventaris','monitoring'
  UNION SELECT 'manajer_ga','maintenance','lihat' UNION SELECT 'manajer_ga','maintenance','monitoring'
  UNION SELECT 'manajer_ga','karyawan','lihat' UNION SELECT 'manajer_ga','karyawan','monitoring'
  UNION SELECT 'manajer_ga','arsip','lihat' UNION SELECT 'manajer_ga','arsip','tambah'
  UNION SELECT 'manajer_ga','laporan','lihat'

  -- SPV Inventaris: pengelola utama & approve pengajuan peminjaman barang +
  -- approve pengajuan Maintenance yang diajukan Staff Inventaris sebelum
  -- diteruskan ke Team Leader/SPV Sapras + user Arsip
  UNION SELECT 'spv_inventaris','inventaris','lihat' UNION SELECT 'spv_inventaris','inventaris','approve' UNION SELECT 'spv_inventaris','inventaris','monitoring'
  UNION SELECT 'spv_inventaris','maintenance','lihat' UNION SELECT 'spv_inventaris','maintenance','approve_atasan'
  UNION SELECT 'spv_inventaris','pengajuan_unit','lihat' UNION SELECT 'spv_inventaris','pengajuan_unit','approve'
  UNION SELECT 'spv_inventaris','arsip','lihat' UNION SELECT 'spv_inventaris','arsip','tambah'
  UNION SELECT 'spv_inventaris','laporan','lihat'

  -- Staff Inventaris: pengelola & cek pengajuan sebelum dikirim ke SPV
  -- Inventaris untuk approve, konfirmasi pengembalian barang + membuat
  -- pengajuan Maintenance (diapprove SPV Inventaris) + user Arsip
  UNION SELECT 'staff_inventaris','inventaris','lihat' UNION SELECT 'staff_inventaris','inventaris','tambah' UNION SELECT 'staff_inventaris','inventaris','kelola' UNION SELECT 'staff_inventaris','inventaris','verifikasi'
  UNION SELECT 'staff_inventaris','maintenance','lihat' UNION SELECT 'staff_inventaris','maintenance','ajukan'
  UNION SELECT 'staff_inventaris','pengajuan_unit','lihat' UNION SELECT 'staff_inventaris','pengajuan_unit','ajukan'
  UNION SELECT 'staff_inventaris','arsip','lihat' UNION SELECT 'staff_inventaris','arsip','tambah'

  -- SPV Sapras: pengelola utama & approve Maintenance + pengelola utama &
  -- penilaian Manajemen Karyawan + approve pengajuan Inventaris yang
  -- diajukan Team Leader sebelum diteruskan ke Staff Inventaris + Arsip
  UNION SELECT 'spv_sapras','maintenance','lihat' UNION SELECT 'spv_sapras','maintenance','approve' UNION SELECT 'spv_sapras','maintenance','monitoring'
  UNION SELECT 'spv_sapras','inventaris','lihat' UNION SELECT 'spv_sapras','inventaris','approve_atasan'
  UNION SELECT 'spv_sapras','karyawan','lihat' UNION SELECT 'spv_sapras','karyawan','kelola' UNION SELECT 'spv_sapras','karyawan','penilaian'
  UNION SELECT 'spv_sapras','pengajuan_unit','lihat' UNION SELECT 'spv_sapras','pengajuan_unit','approve'
  UNION SELECT 'spv_sapras','arsip','lihat' UNION SELECT 'spv_sapras','arsip','tambah'
  UNION SELECT 'spv_sapras','laporan','lihat'

  -- Team Leader: pengelola & cek pengajuan Maintenance sebelum dikirim ke
  -- SPV Sapras + pengelola & memberi kegiatan harian/non-harian karyawan
  -- SPV Sapras) + pengelola & memberi kegiatan harian/non-harian karyawan
  -- (CSO/Gardener) + membuat pengajuan Inventaris (diapprove SPV Sapras) + Arsip
  UNION SELECT 'team_leader','maintenance','lihat' UNION SELECT 'team_leader','maintenance','verifikasi'
  UNION SELECT 'team_leader','karyawan','lihat' UNION SELECT 'team_leader','karyawan','kelola'
  UNION SELECT 'team_leader','inventaris','lihat' UNION SELECT 'team_leader','inventaris','ajukan'
  UNION SELECT 'team_leader','pengajuan_unit','lihat' UNION SELECT 'team_leader','pengajuan_unit','ajukan'
  UNION SELECT 'team_leader','arsip','lihat' UNION SELECT 'team_leader','arsip','tambah'

  -- Engineering: menerima & mengerjakan laporan Maintenance yang sudah
  -- disetujui SPV Sapras
  UNION SELECT 'engineering','maintenance','lihat' UNION SELECT 'engineering','maintenance','kelola'

  -- CSO / Gardener: menerima & memberikan laporan kegiatan harian/non-harian
  -- dari Team Leader (Manajemen Karyawan saja)
  UNION SELECT 'cso','karyawan','lihat' UNION SELECT 'cso','karyawan','input'
  UNION SELECT 'gardener','karyawan','lihat' UNION SELECT 'gardener','karyawan','input'

  -- Manajer HRD: pengelolaan administrasi/rekap/arsip data karyawan +
  -- approve pengajuan Inventaris, EMS & Maintenance dari Staff HRD sebelum
  -- diteruskan ke pengelola modul terkait + Arsip
  UNION SELECT 'manajer_hrd','karyawan','lihat' UNION SELECT 'manajer_hrd','karyawan','tambah' UNION SELECT 'manajer_hrd','karyawan','kelola'
  UNION SELECT 'manajer_hrd','inventaris','lihat' UNION SELECT 'manajer_hrd','inventaris','approve_atasan'
  UNION SELECT 'manajer_hrd','maintenance','lihat' UNION SELECT 'manajer_hrd','maintenance','approve_atasan'
  UNION SELECT 'manajer_hrd','event','lihat' UNION SELECT 'manajer_hrd','event','approve_atasan'
  UNION SELECT 'manajer_hrd','pengajuan_unit','lihat' UNION SELECT 'manajer_hrd','pengajuan_unit','approve'
  UNION SELECT 'manajer_hrd','arsip','lihat' UNION SELECT 'manajer_hrd','arsip','tambah'
  UNION SELECT 'manajer_hrd','laporan','lihat'

  -- Staff HRD: pengelolaan administrasi/rekap/arsip data karyawan + membuat
  -- pengajuan Inventaris, EMS & Maintenance (diapprove Manajer HRD) + Arsip
  UNION SELECT 'staff_hrd','karyawan','lihat' UNION SELECT 'staff_hrd','karyawan','tambah' UNION SELECT 'staff_hrd','karyawan','kelola'
  UNION SELECT 'staff_hrd','inventaris','lihat' UNION SELECT 'staff_hrd','inventaris','ajukan'
  UNION SELECT 'staff_hrd','maintenance','lihat' UNION SELECT 'staff_hrd','maintenance','ajukan'
  UNION SELECT 'staff_hrd','event','lihat' UNION SELECT 'staff_hrd','event','ajukan'
  UNION SELECT 'staff_hrd','pengajuan_unit','lihat' UNION SELECT 'staff_hrd','pengajuan_unit','ajukan'
  UNION SELECT 'staff_hrd','arsip','lihat' UNION SELECT 'staff_hrd','arsip','tambah'

  -- Manajer MARKOM: pengelola utama & approve pengajuan Event/Kegiatan +
  -- approve pengajuan Inventaris & Maintenance dari SPV CRM sebelum
  -- diteruskan + Arsip
  UNION SELECT 'manajer_markom','event','lihat' UNION SELECT 'manajer_markom','event','tambah' UNION SELECT 'manajer_markom','event','kelola' UNION SELECT 'manajer_markom','event','approve' UNION SELECT 'manajer_markom','event','monitoring'
  UNION SELECT 'manajer_markom','inventaris','lihat' UNION SELECT 'manajer_markom','inventaris','approve_atasan'
  UNION SELECT 'manajer_markom','maintenance','lihat' UNION SELECT 'manajer_markom','maintenance','approve_atasan'
  UNION SELECT 'manajer_markom','pengajuan_unit','lihat' UNION SELECT 'manajer_markom','pengajuan_unit','approve'
  UNION SELECT 'manajer_markom','arsip','lihat' UNION SELECT 'manajer_markom','arsip','tambah'
  UNION SELECT 'manajer_markom','laporan','lihat'

  -- SPV CRM: pengelola & cek pengajuan Event + membuat pengajuan Inventaris
  -- & Maintenance (diapprove Manajer MARKOM) + Arsip
  UNION SELECT 'spv_crm','event','lihat' UNION SELECT 'spv_crm','event','verifikasi' UNION SELECT 'spv_crm','event','monitoring'
  UNION SELECT 'spv_crm','inventaris','lihat' UNION SELECT 'spv_crm','inventaris','ajukan'
  UNION SELECT 'spv_crm','maintenance','lihat' UNION SELECT 'spv_crm','maintenance','ajukan'
  UNION SELECT 'spv_crm','pengajuan_unit','lihat' UNION SELECT 'spv_crm','pengajuan_unit','ajukan'
  UNION SELECT 'spv_crm','arsip','lihat' UNION SELECT 'spv_crm','arsip','tambah'

  -- Staff CRM: menerima, memeriksa, kelola jadwal event (operasional EMS)
  UNION SELECT 'staff_crm','event','lihat' UNION SELECT 'staff_crm','event','tambah' UNION SELECT 'staff_crm','event','kelola'

  -- Manajer QMS: pengelola utama Arsip Digital + approve pengajuan
  -- Inventaris & Maintenance dari Staff QMS sebelum diteruskan
  UNION SELECT 'manajer_qms','arsip','lihat' UNION SELECT 'manajer_qms','arsip','kelola' UNION SELECT 'manajer_qms','arsip','monitoring'
  UNION SELECT 'manajer_qms','inventaris','lihat' UNION SELECT 'manajer_qms','inventaris','approve_atasan'
  UNION SELECT 'manajer_qms','maintenance','lihat' UNION SELECT 'manajer_qms','maintenance','approve_atasan'
  UNION SELECT 'manajer_qms','pengajuan_unit','lihat' UNION SELECT 'manajer_qms','pengajuan_unit','approve'
  UNION SELECT 'manajer_qms','laporan','lihat' UNION SELECT 'manajer_qms','laporan','kelola'

  -- Staff QMS: pengelola Arsip Digital + membuat pengajuan Inventaris &
  -- Maintenance (diapprove Manajer QMS)
  UNION SELECT 'staff_qms','arsip','lihat' UNION SELECT 'staff_qms','arsip','tambah' UNION SELECT 'staff_qms','arsip','kelola'
  UNION SELECT 'staff_qms','inventaris','lihat' UNION SELECT 'staff_qms','inventaris','ajukan'
  UNION SELECT 'staff_qms','maintenance','lihat' UNION SELECT 'staff_qms','maintenance','ajukan'
  UNION SELECT 'staff_qms','pengajuan_unit','lihat' UNION SELECT 'staff_qms','pengajuan_unit','ajukan'

  -- TU: membuat pengajuan Inventaris/Maintenance/EMS untuk unit pendidikan
  -- (diapprove Kepala Sekolah) + Arsip
  UNION SELECT 'tu','inventaris','lihat' UNION SELECT 'tu','inventaris','ajukan'
  UNION SELECT 'tu','maintenance','lihat' UNION SELECT 'tu','maintenance','ajukan'
  UNION SELECT 'tu','event','lihat' UNION SELECT 'tu','event','ajukan'
  UNION SELECT 'tu','pengajuan_unit','lihat' UNION SELECT 'tu','pengajuan_unit','ajukan'
  UNION SELECT 'tu','arsip','lihat' UNION SELECT 'tu','arsip','tambah'

  -- Kepala Sekolah: validasi/approve pengajuan dari TU + Arsip
  UNION SELECT 'kepala_sekolah','inventaris','lihat' UNION SELECT 'kepala_sekolah','inventaris','approve_atasan'
  UNION SELECT 'kepala_sekolah','maintenance','lihat' UNION SELECT 'kepala_sekolah','maintenance','approve_atasan'
  UNION SELECT 'kepala_sekolah','event','lihat' UNION SELECT 'kepala_sekolah','event','approve_atasan'
  UNION SELECT 'kepala_sekolah','pengajuan_unit','lihat' UNION SELECT 'kepala_sekolah','pengajuan_unit','approve'
  UNION SELECT 'kepala_sekolah','arsip','lihat' UNION SELECT 'kepala_sekolah','arsip','tambah'
  UNION SELECT 'kepala_sekolah','laporan','lihat'
) m ON m.rk = r.kode;

-- --- STRUKTUR KARYAWAN (hierarki GA: Manajer GA > SPV > TL > CSO/Gardener/Engineering) ---
INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='manajerga'), 'GA-0001', 'Budi Santoso', 'Manajer GA', (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), NULL, 'aktif');
SET @manajerga_kid = LAST_INSERT_ID();

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='spvinventaris'), 'GA-0002', 'Dewi Anggraini', 'SPV Inventaris', (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @manajerga_kid, 'aktif'),
((SELECT id FROM users WHERE username='spvsapras'),     'GA-0003', 'Hendra Wijaya',  'SPV Sapras',     (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @manajerga_kid, 'aktif');
SET @spvsapras_kid = (SELECT id FROM karyawan WHERE nip='GA-0003');

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='teamleader'), 'GA-0004', 'Agus Salim', 'Team Leader', (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @spvsapras_kid, 'aktif');
SET @tl_kid = (SELECT id FROM karyawan WHERE nip='GA-0004');

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='engineering'), 'GA-0005', 'Joko Susilo', 'Engineering', (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @tl_kid, 'aktif'),
((SELECT id FROM users WHERE username='cso1'),        'GA-0006', 'Slamet Riyadi', 'CSO',        (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @tl_kid, 'aktif'),
((SELECT id FROM users WHERE username='gardener1'),   'GA-0007', 'Warno', 'Gardener',            (SELECT id FROM struktur_unit WHERE nama_unit='General Affair (GA)'), @tl_kid, 'aktif');

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='manajerhrd'), 'HRD-0001', 'Ratna Sari Dewi', 'Manajer HRD', (SELECT id FROM struktur_unit WHERE nama_unit='HRD'), NULL, 'aktif');
SET @manajerhrd_kid = LAST_INSERT_ID();

INSERT INTO karyawan (user_id, nip, nama, jabatan, divisi_id, atasan_id, status) VALUES
((SELECT id FROM users WHERE username='staffhrd'), 'HRD-0002', 'Anisa Rahmawati', 'Staff HRD', (SELECT id FROM struktur_unit WHERE nama_unit='HRD'), @manajerhrd_kid, 'aktif'),
((SELECT id FROM users WHERE username='manajermarkom'), 'MK-0001', 'Fitri Handayani', 'Manajer MARKOM', (SELECT id FROM struktur_unit WHERE nama_unit='MARKOM / CRM'), NULL, 'aktif'),
((SELECT id FROM users WHERE username='manajerqms'), 'QM-0001', 'Bambang Sutrisno', 'Manajer QMS', (SELECT id FROM struktur_unit WHERE nama_unit='QMS'), NULL, 'aktif');

-- --- DATA AWAL LAIN ---
INSERT INTO barang (kode_barang, nama_barang, kategori, lokasi, stok, status) VALUES
('BRG-001', 'Proyektor Epson', 'Elektronik', 'Gudang GA', 5, 'tersedia'),
('BRG-002', 'Kursi Lipat', 'Furnitur', 'Gudang GA', 100, 'tersedia'),
('BRG-003', 'Sound System Portable', 'Elektronik', 'Gudang GA', 2, 'tersedia'),
('BRG-004', 'Meja Panitia', 'Furnitur', 'Gudang GA', 20, 'tersedia');

INSERT INTO fasilitas (nama_fasilitas, lokasi, kondisi_terkini) VALUES
('AC Ruang Kelas 3A', 'Gedung SD Lt.1', 'baik'),
('Pompa Air Utama', 'Area Belakang', 'baik'),
('Lift Gedung SMA', 'Gedung SMA', 'baik');

INSERT INTO kategori_arsip (nama_kategori, deskripsi) VALUES
('Surat Menyurat', 'Surat masuk/keluar yayasan & unit'),
('Dokumen Kepegawaian', 'Data & dokumen administrasi karyawan'),
('Laporan Kegiatan', 'Dokumentasi & laporan pelaksanaan kegiatan/event'),
('Legal & Perizinan', 'Dokumen legal, izin, dan sertifikat');
