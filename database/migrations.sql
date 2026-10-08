ALTER TABLE peminjaman ADD COLUMN IF NOT EXISTS atasan_id INT DEFAULT NULL;
ALTER TABLE event ADD COLUMN IF NOT EXISTS atasan_id INT DEFAULT NULL;

ALTER TABLE peminjaman ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;
ALTER TABLE event ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;
ALTER TABLE pengajuan_maintenance ADD COLUMN IF NOT EXISTS catatan_atasan VARCHAR(255) DEFAULT NULL;

ALTER TABLE peminjaman MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';
ALTER TABLE event MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';
ALTER TABLE pengajuan_maintenance MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'diajukan';

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
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'monitoring' FROM roles WHERE kode = 'manajer_ga';
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
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'monitoring' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'karyawan', 'kelola' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'inventaris', 'approve_atasan' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'maintenance', 'approve_atasan' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'lihat' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'tambah' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'arsip', 'kelola' FROM roles WHERE kode = 'manajer_hrd';
INSERT IGNORE INTO hak_akses (role_id, modul, aksi) SELECT id, 'laporan', 'lihat' FROM roles WHERE kode = 'manajer_hrd';

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

