-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: alsyukrosmart1
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `arsip`
--

DROP TABLE IF EXISTS `arsip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arsip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul_dokumen` varchar(150) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploader_id` int(11) NOT NULL,
  `tingkat_akses` enum('umum','terbatas','rahasia') NOT NULL DEFAULT 'umum',
  `tanggal_unggah` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  KEY `uploader_id` (`uploader_id`),
  CONSTRAINT `arsip_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_arsip` (`id`),
  CONSTRAINT `arsip_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arsip`
--

LOCK TABLES `arsip` WRITE;
/*!40000 ALTER TABLE `arsip` DISABLE KEYS */;
INSERT INTO `arsip` VALUES (1,'dokumen',3,'uploads/arsip/d0bc817215f00a0f5a27867884eefd49.docx',5,'umum','2026-10-19 20:13:53');
/*!40000 ALTER TABLE `arsip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_arsip`
--

DROP TABLE IF EXISTS `backup_arsip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_arsip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arsip_id` int(11) DEFAULT NULL,
  `tanggal_backup` datetime DEFAULT current_timestamp(),
  `lokasi_backup` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `arsip_id` (`arsip_id`),
  CONSTRAINT `backup_arsip_ibfk_1` FOREIGN KEY (`arsip_id`) REFERENCES `arsip` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_arsip`
--

LOCK TABLES `backup_arsip` WRITE;
/*!40000 ALTER TABLE `backup_arsip` DISABLE KEYS */;
INSERT INTO `backup_arsip` VALUES (1,1,'2026-10-19 20:17:22','backups/arsip_1_20261019_201722.bak');
/*!40000 ALTER TABLE `backup_arsip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barang`
--

DROP TABLE IF EXISTS `barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(30) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `kategori` varchar(60) DEFAULT NULL,
  `lokasi` varchar(80) DEFAULT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `status` enum('tersedia','habis','rusak') NOT NULL DEFAULT 'tersedia',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_barang` (`kode_barang`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barang`
--

LOCK TABLES `barang` WRITE;
/*!40000 ALTER TABLE `barang` DISABLE KEYS */;
INSERT INTO `barang` VALUES (1,'BRG-001','Proyektor Epson','Elektronik','Gudang GA',3,'tersedia',NULL,'2026-10-14 07:40:47'),(2,'BRG-002','Kursi Futura','Furnitur','Gudang GA',93,'tersedia',NULL,'2026-10-14 07:40:47'),(3,'BRG-003','Sound System Portable','Elektronik','Gudang GA',2,'tersedia',NULL,'2026-10-14 07:40:47'),(4,'BRG-004','Meja Panitia','Furnitur','Gudang GA',20,'tersedia',NULL,'2026-10-14 07:40:47');
/*!40000 ALTER TABLE `barang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dokumentasi_event`
--

DROP TABLE IF EXISTS `dokumentasi_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokumentasi_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `tanggal_unggah` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `dokumentasi_event_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dokumentasi_event`
--

LOCK TABLES `dokumentasi_event` WRITE;
/*!40000 ALTER TABLE `dokumentasi_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `dokumentasi_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event`
--

DROP TABLE IF EXISTS `event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_event` varchar(120) NOT NULL,
  `pemohon_id` int(11) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'diajukan',
  `verifikator_id` int(11) DEFAULT NULL,
  `approval_id` int(11) DEFAULT NULL,
  `atasan_id` int(11) DEFAULT NULL,
  `catatan_atasan` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pemohon_id` (`pemohon_id`),
  KEY `verifikator_id` (`verifikator_id`),
  KEY `approval_id` (`approval_id`),
  KEY `atasan_id` (`atasan_id`),
  CONSTRAINT `event_ibfk_1` FOREIGN KEY (`pemohon_id`) REFERENCES `users` (`id`),
  CONSTRAINT `event_ibfk_2` FOREIGN KEY (`verifikator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `event_ibfk_3` FOREIGN KEY (`approval_id`) REFERENCES `users` (`id`),
  CONSTRAINT `event_ibfk_4` FOREIGN KEY (`atasan_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event`
--

LOCK TABLES `event` WRITE;
/*!40000 ALTER TABLE `event` DISABLE KEYS */;
/*!40000 ALTER TABLE `event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fasilitas`
--

DROP TABLE IF EXISTS `fasilitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fasilitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_fasilitas` varchar(100) NOT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `kondisi_terkini` enum('baik','perlu_perhatian','rusak') NOT NULL DEFAULT 'baik',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fasilitas`
--

LOCK TABLES `fasilitas` WRITE;
/*!40000 ALTER TABLE `fasilitas` DISABLE KEYS */;
INSERT INTO `fasilitas` VALUES (1,'AC Ruang Kelas 3A','Gedung SD Lt.1','baik'),(2,'Pompa Air Utama','Area Belakang','baik'),(3,'Lift Gedung SMA','Gedung SMA','baik');
/*!40000 ALTER TABLE `fasilitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hak_akses`
--

DROP TABLE IF EXISTS `hak_akses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hak_akses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `modul` varchar(30) NOT NULL,
  `aksi` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hak` (`role_id`,`modul`,`aksi`),
  CONSTRAINT `hak_akses_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2023 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hak_akses`
--

LOCK TABLES `hak_akses` WRITE;
/*!40000 ALTER TABLE `hak_akses` DISABLE KEYS */;
INSERT INTO `hak_akses` VALUES (26,1,'arsip','kelola'),(24,1,'arsip','lihat'),(27,1,'arsip','monitoring'),(25,1,'arsip','tambah'),(16,1,'event','approve'),(14,1,'event','kelola'),(12,1,'event','lihat'),(17,1,'event','monitoring'),(13,1,'event','tambah'),(15,1,'event','verifikasi'),(5,1,'inventaris','approve'),(3,1,'inventaris','kelola'),(1,1,'inventaris','lihat'),(6,1,'inventaris','monitoring'),(2,1,'inventaris','tambah'),(4,1,'inventaris','verifikasi'),(9,1,'karyawan','kelola'),(1529,1,'karyawan','kelola_struktur'),(7,1,'karyawan','lihat'),(10,1,'karyawan','monitoring'),(11,1,'karyawan','penilaian'),(8,1,'karyawan','tambah'),(1532,1,'karyawan','ubah_status'),(29,1,'laporan','kelola'),(28,1,'laporan','lihat'),(1733,1,'log_aktivitas','lihat'),(22,1,'maintenance','approve'),(20,1,'maintenance','kelola'),(18,1,'maintenance','lihat'),(23,1,'maintenance','monitoring'),(19,1,'maintenance','tambah'),(21,1,'maintenance','verifikasi'),(31,1,'pengajuan_unit','kelola'),(30,1,'pengajuan_unit','lihat'),(197,2,'arsip','kelola'),(38,2,'arsip','lihat'),(39,2,'arsip','tambah'),(32,2,'inventaris','lihat'),(33,2,'inventaris','monitoring'),(36,2,'karyawan','lihat'),(40,2,'laporan','lihat'),(1734,2,'log_aktivitas','lihat'),(34,2,'maintenance','lihat'),(35,2,'maintenance','monitoring'),(48,3,'arsip','lihat'),(49,3,'arsip','tambah'),(237,3,'event','approve_atasan'),(233,3,'event','lihat'),(42,3,'inventaris','approve'),(227,3,'inventaris','approve_atasan'),(186,3,'inventaris','kelola'),(41,3,'inventaris','lihat'),(43,3,'inventaris','monitoring'),(187,3,'inventaris','tambah'),(669,3,'karyawan','kelola'),(668,3,'karyawan','lihat'),(50,3,'laporan','lihat'),(45,3,'maintenance','approve_atasan'),(44,3,'maintenance','lihat'),(47,3,'pengajuan_unit','approve'),(46,3,'pengajuan_unit','lihat'),(59,4,'arsip','lihat'),(60,4,'arsip','tambah'),(53,4,'inventaris','kelola'),(51,4,'inventaris','lihat'),(52,4,'inventaris','tambah'),(54,4,'inventaris','verifikasi'),(653,4,'karyawan','input'),(652,4,'karyawan','lihat'),(56,4,'maintenance','ajukan'),(55,4,'maintenance','lihat'),(58,4,'pengajuan_unit','ajukan'),(57,4,'pengajuan_unit','lihat'),(71,5,'arsip','lihat'),(72,5,'arsip','tambah'),(238,5,'event','approve_atasan'),(234,5,'event','lihat'),(65,5,'inventaris','approve_atasan'),(64,5,'inventaris','lihat'),(67,5,'karyawan','kelola'),(66,5,'karyawan','lihat'),(68,5,'karyawan','penilaian'),(73,5,'laporan','lihat'),(62,5,'maintenance','approve'),(231,5,'maintenance','approve_atasan'),(61,5,'maintenance','lihat'),(63,5,'maintenance','monitoring'),(70,5,'pengajuan_unit','approve'),(69,5,'pengajuan_unit','lihat'),(82,6,'arsip','lihat'),(83,6,'arsip','tambah'),(239,6,'event','approve_atasan'),(235,6,'event','lihat'),(79,6,'inventaris','ajukan'),(228,6,'inventaris','approve_atasan'),(78,6,'inventaris','lihat'),(77,6,'karyawan','kelola'),(76,6,'karyawan','lihat'),(232,6,'maintenance','approve_atasan'),(74,6,'maintenance','lihat'),(75,6,'maintenance','verifikasi'),(81,6,'pengajuan_unit','ajukan'),(80,6,'pengajuan_unit','lihat'),(663,7,'karyawan','input'),(662,7,'karyawan','lihat'),(85,7,'maintenance','kelola'),(84,7,'maintenance','lihat'),(87,8,'karyawan','input'),(86,8,'karyawan','lihat'),(89,9,'karyawan','input'),(88,9,'karyawan','lihat'),(208,10,'arsip','kelola'),(101,10,'arsip','lihat'),(102,10,'arsip','tambah'),(98,10,'event','approve_atasan'),(97,10,'event','lihat'),(94,10,'inventaris','approve_atasan'),(93,10,'inventaris','lihat'),(92,10,'karyawan','kelola'),(1530,10,'karyawan','kelola_struktur'),(90,10,'karyawan','lihat'),(91,10,'karyawan','tambah'),(1533,10,'karyawan','ubah_status'),(103,10,'laporan','lihat'),(1735,10,'log_aktivitas','lihat'),(96,10,'maintenance','approve_atasan'),(95,10,'maintenance','lihat'),(100,10,'pengajuan_unit','approve'),(99,10,'pengajuan_unit','lihat'),(115,11,'arsip','lihat'),(116,11,'arsip','tambah'),(112,11,'event','ajukan'),(111,11,'event','lihat'),(108,11,'inventaris','ajukan'),(107,11,'inventaris','lihat'),(106,11,'karyawan','kelola'),(104,11,'karyawan','lihat'),(105,11,'karyawan','tambah'),(110,11,'maintenance','ajukan'),(109,11,'maintenance','lihat'),(114,11,'pengajuan_unit','ajukan'),(113,11,'pengajuan_unit','lihat'),(223,12,'arsip','kelola'),(128,12,'arsip','lihat'),(129,12,'arsip','tambah'),(120,12,'event','approve'),(119,12,'event','kelola'),(117,12,'event','lihat'),(121,12,'event','monitoring'),(118,12,'event','tambah'),(123,12,'inventaris','approve_atasan'),(122,12,'inventaris','lihat'),(220,12,'inventaris','monitoring'),(671,12,'karyawan','kelola'),(670,12,'karyawan','lihat'),(130,12,'laporan','lihat'),(125,12,'maintenance','approve_atasan'),(124,12,'maintenance','lihat'),(127,12,'pengajuan_unit','approve'),(126,12,'pengajuan_unit','lihat'),(140,13,'arsip','lihat'),(141,13,'arsip','tambah'),(236,13,'event','approve_atasan'),(131,13,'event','lihat'),(133,13,'event','monitoring'),(132,13,'event','verifikasi'),(226,13,'inventaris','approve_atasan'),(134,13,'inventaris','lihat'),(673,13,'karyawan','kelola'),(672,13,'karyawan','lihat'),(230,13,'maintenance','approve_atasan'),(136,13,'maintenance','lihat'),(139,13,'pengajuan_unit','ajukan'),(138,13,'pengajuan_unit','lihat'),(144,14,'event','kelola'),(142,14,'event','lihat'),(143,14,'event','tambah'),(655,14,'karyawan','input'),(654,14,'karyawan','lihat'),(146,15,'arsip','kelola'),(145,15,'arsip','lihat'),(147,15,'arsip','monitoring'),(149,15,'inventaris','approve_atasan'),(148,15,'inventaris','lihat'),(675,15,'karyawan','kelola'),(674,15,'karyawan','lihat'),(155,15,'laporan','kelola'),(154,15,'laporan','lihat'),(151,15,'maintenance','approve_atasan'),(150,15,'maintenance','lihat'),(153,15,'pengajuan_unit','approve'),(152,15,'pengajuan_unit','lihat'),(158,16,'arsip','kelola'),(156,16,'arsip','lihat'),(157,16,'arsip','tambah'),(160,16,'inventaris','ajukan'),(159,16,'inventaris','lihat'),(657,16,'karyawan','input'),(656,16,'karyawan','lihat'),(162,16,'maintenance','ajukan'),(161,16,'maintenance','lihat'),(164,16,'pengajuan_unit','ajukan'),(163,16,'pengajuan_unit','lihat'),(173,17,'arsip','lihat'),(174,17,'arsip','tambah'),(170,17,'event','ajukan'),(169,17,'event','lihat'),(166,17,'inventaris','ajukan'),(165,17,'inventaris','lihat'),(661,17,'karyawan','input'),(660,17,'karyawan','lihat'),(168,17,'maintenance','ajukan'),(167,17,'maintenance','lihat'),(172,17,'pengajuan_unit','ajukan'),(171,17,'pengajuan_unit','lihat'),(183,18,'arsip','lihat'),(184,18,'arsip','tambah'),(180,18,'event','approve_atasan'),(179,18,'event','lihat'),(176,18,'inventaris','approve_atasan'),(175,18,'inventaris','lihat'),(679,18,'karyawan','kelola'),(678,18,'karyawan','lihat'),(185,18,'laporan','lihat'),(178,18,'maintenance','approve_atasan'),(177,18,'maintenance','lihat'),(182,18,'pengajuan_unit','approve'),(181,18,'pengajuan_unit','lihat'),(249,19,'arsip','lihat'),(250,19,'arsip','tambah'),(251,19,'dashboard','lihat'),(244,19,'inventaris','ajukan'),(243,19,'inventaris','lihat'),(246,19,'maintenance','ajukan'),(245,19,'maintenance','lihat'),(366,21,'arsip','lihat'),(367,21,'arsip','tambah'),(369,21,'dashboard','lihat'),(360,21,'inventaris','lihat'),(362,21,'maintenance','lihat'),(2009,61,'arsip','lihat'),(2010,61,'arsip','tambah'),(2000,61,'dashboard','lihat'),(2002,61,'inventaris','ajukan'),(2003,61,'inventaris','approve_atasan'),(2001,61,'inventaris','lihat'),(2005,61,'maintenance','ajukan'),(2006,61,'maintenance','approve_atasan'),(2004,61,'maintenance','lihat');
/*!40000 ALTER TABLE `hak_akses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal_event`
--

DROP TABLE IF EXISTS `jadwal_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jadwal_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `jadwal_event_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal_event`
--

LOCK TABLES `jadwal_event` WRITE;
/*!40000 ALTER TABLE `jadwal_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `jadwal_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `karyawan`
--

DROP TABLE IF EXISTS `karyawan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `nip` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jabatan` varchar(60) NOT NULL,
  `divisi_id` int(11) DEFAULT NULL,
  `atasan_id` int(11) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `tanggal_masuk` date DEFAULT NULL,
  `kontak` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nip` (`nip`),
  KEY `user_id` (`user_id`),
  KEY `divisi_id` (`divisi_id`),
  KEY `atasan_id` (`atasan_id`),
  CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `karyawan_ibfk_2` FOREIGN KEY (`divisi_id`) REFERENCES `struktur_unit` (`id`),
  CONSTRAINT `karyawan_ibfk_3` FOREIGN KEY (`atasan_id`) REFERENCES `karyawan` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `karyawan`
--

LOCK TABLES `karyawan` WRITE;
/*!40000 ALTER TABLE `karyawan` DISABLE KEYS */;
INSERT INTO `karyawan` VALUES (1,2,'GA-0001','Ahmad Ubaydilah, S.E','Manajer GA',1,NULL,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(2,3,'GA-0002','Dona Nuryahya, S.Kom','SPV Inventaris',1,1,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(3,5,'GA-0003','Riki indramars','SPV Sapras',1,1,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(5,7,'GA-0005','Ahmad San Rohmadi','Engineering',1,50,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(8,10,'HRD-0001','Ratna Sari Dewi, S.H','Manajer HRD',2,NULL,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(9,11,'HRD-0002','Munadya Hasna Dewi, S.Psi','Staff HRD',2,8,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(10,12,'MK-0001','Henrizal Saidi Harahap, M.Ag','Manajer MARKOM',3,NULL,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(11,15,'QM-0001','Budi Santoso, SP','Manajer QMS',4,NULL,'aktif',NULL,NULL,'2026-10-14 07:40:47'),(13,1,'ADM-0001','Dona Nuryahya, S.Kom','Administrator Sistem',NULL,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(14,4,'INV-0001','Inang Setiawati Siregar, S.M','Staff Inventaris',1,2,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(15,43,'FA-0001','Muhamad Fahmi, S.Akun','Manajer FA',9,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(16,18,'SD-0001','Tarni, S.Pd','Kepala Sekolah',6,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(17,36,'TK-0001','Wati, SP.d','Kepala Sekolah',5,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(18,34,'SMP-0001','Umairah, SP.d','Kepala Sekolah',7,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(19,35,'SMA-0001','Rizal, SP.d','Kepala Sekolah',8,NULL,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(20,19,'GA-0009','Hasbullah','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(22,21,'GA-0011','Waliman Antoni','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(23,22,'GA-0012','Hilman Sunan Munawar','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(24,23,'GA-0013','Zian Pauzian Syah','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(25,24,'GA-0014','Muhammad Cahyagam','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(26,25,'GA-0015','Aldi Refaldy','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(27,26,'GA-0016','Mustadi','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(28,27,'GA-0017','Hendra Evin','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(29,28,'GA-0018','Agus Rohendi','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(30,29,'GA-0019','Dandi','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(31,30,'GA-0020','Sakirun','Gardener',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(32,31,'GA-0021','Makbul','Gardener',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(33,32,'GA-0022','Hasim HM','Gardener',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(34,33,'GA-0023','Sodikin','Gardener',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(35,46,'GA-0025','Iim Ibrahim','Gardener',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(36,41,'GA-0024','Arif Mulyana','Engineering',1,50,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(37,13,'CRM-0001','Ahmad Qosim, S.Pd.I','SPV CRM',3,10,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(38,14,'CRM-0002','Apri Supriani, S.M','Staff CRM',3,37,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(39,16,'QM-0002','Husna Assabiila Yassarah, S.Kom','Staff QMS',4,11,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(40,17,'SD-0002','Ansor Nurjaya, S.Pd','TU',6,16,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(41,37,'TK-0002','Desti Tri Indahsari, S.E','TU',5,17,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(42,38,'SMP-0002','Muhammad Rizqi Fuazin','TU',7,18,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(43,39,'SMA-0002','Muhammad Adib Riza, S.Pd.I','TU',8,19,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(44,42,'FA-0003','Nabila Rif\'atul Izzah, S.Ak','Staff FA',9,173,'aktif','2024-01-01',NULL,'2026-10-21 02:10:09'),(50,6,'EMP-0006','Muhammad Giyanto','Team Leader (TL)',1,3,'aktif','2024-01-01',NULL,'2026-10-22 01:59:31'),(51,8,'EMP-0008','Asep Setiawan','CSO',1,50,'aktif','2024-01-01',NULL,'2026-10-22 01:59:31'),(55,NULL,'SD-0039','Abdul Kholiq, S.Pd.I.','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(56,NULL,'GA-0041','Ade Kurniawan','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(57,NULL,'SD-0049','Adisti Tresnawati, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(59,NULL,'CRM-0004','Aflah Wazihul Haq','Staf Honor Markom',3,37,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(60,NULL,'0885.0220.00','Ahmad Abdul Wasiudin, M.Pd','GMP',NULL,86,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(65,NULL,'SMP-0004','Aka Roma Fatikhatus Sa\'adah, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(66,NULL,'SMP-0013','Akhmad Ferdiansyah, S.Si','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(67,NULL,'SMA-0010','Al Muntahanatul Auliya Salamah, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(68,NULL,'SD-0020','Alfiatun Nikmah S.Ag','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(69,NULL,'TK-0003','Ana Lestari, S.Pd','Wakil Kepala TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(70,NULL,'GA-0032','Anggi Sanjaya','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(71,NULL,'SD-0035','Ani, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(72,NULL,'SD-0006','Anisah, S.Pd.I','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(73,NULL,'SD-0044','Annisa Fizry Yulistiani, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(74,NULL,'SMA-0015','Annisa Salsabila Nuzululliana, S.Si','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(77,NULL,'SD-0013','Ari Eka Hani Puspita, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(80,NULL,'SD-0028','Asep Tejakusumah, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(81,NULL,'GA-0042','Asma\'i','Driver',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(82,NULL,'SD-0048','Asri Aryaning Nugraheni, M.Si','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(84,NULL,'SMP-0007','Cahya Dima Anugrah, S.Si','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(85,NULL,'SD-0012','Childa Erika, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(86,NULL,'0587.0417.00','Cicih Kurniasih, M.Pd','Direktur',NULL,NULL,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(87,NULL,'SD-0037','Deadora Rahma Muthia, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(88,NULL,'TK-0013','Deasy Arummita, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(90,NULL,'SD-0023','Devi Hidayat, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(91,NULL,'SD-0033','Diannida, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(92,NULL,'HRD-0003','Diaz Ajeng Kharunnisa, S.H','Staf Kompensasi & Benefit',2,8,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(93,NULL,'0884.0710.00','Dina Fitriani, S.Pt','General Manager Operasional',NULL,86,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(94,NULL,'SD-0018','Djaenudin, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(96,NULL,'SMP-0015','Edy Humaidi, S.Ag','Wakasek Kesiswaan SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(97,NULL,'SD-0031','Elan Jaelani Sidiq, S.Pd.I','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(98,NULL,'TK-0009','Eri Irawati, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(99,NULL,'SD-0024','Erna Mei Andriayna, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(100,NULL,'SMA-0011','Ervan Jaya, M.Pd','Kepala Sekolah SMA',8,NULL,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(101,NULL,'SD-0010','Fara Kurnia Pramesti, S.S','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(103,NULL,'SD-0007','Fidiarsih Wahyuni, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(104,NULL,'SD-0051','Fika Muthiaturrahmah, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(105,NULL,'TK-0004','Fina Maria. S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(106,NULL,'SMA-0012','Ghina Octaviana, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(107,NULL,'SMA-0008','Ghufran Noor Rohman, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(108,NULL,'TK-0011','Halimatussa\'diah, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(109,NULL,'TK-0014','Hania Fatma Asy Syifa, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(110,NULL,'SD-0017','Hanipah S.Pd.I.','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(111,NULL,'SD-0042','Harun Hasibuan, M.Ag','Guru Tahfidz SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(112,NULL,'TK-0010','Haryati,M.M','Kepala Sekolah TK',5,NULL,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(114,NULL,'GA-0035','Helmi','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(116,NULL,'SMP-0009','Hildawati, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(118,NULL,'SD-0027','Hilya Hariza, S.IP','Pustakawan SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(119,NULL,'GA-0031','Hufroni Hanafi','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(122,NULL,'CRM-0003','Ikhwan Fauzi, M.Kom','Staf Honor Digital Marketing',3,37,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(123,NULL,'SMP-0016','Imam Lutfi Saputra, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(124,NULL,'GA-0040','Imam Syafi\'i','Staf Logistik',1,179,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(125,NULL,'GA-0027','Imam Yahya','Staf Pelaksana Pengelola Program Masjid & Sarana Prasarana',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(127,NULL,'SD-0036','Irma Aprilianti, M.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(128,NULL,'SMA-0007','Irwin Pratama Putra, S.Hum','Wakasek Kesiswaan SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(129,NULL,'GA-0045','Ismail Marzuki','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(130,NULL,'SMP-0010','Isnain Khizanah, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(131,NULL,'SMP-0003','Kosaman, SE','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(132,NULL,'TK-0005','Kurnianti S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(133,NULL,'SD-0021','Lailya Hasmy, S.Pd','Wakasek Kurikulum SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(134,NULL,'SD-0004','Lina Sahayati S.Ag','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(135,NULL,'SMA-0013','Lufthansa Chabsoh, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(136,NULL,'SMA-0009','Lusy Syarifah, S.S','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(137,NULL,'SD-0005','Lydia Pertiwi, S.Sos.I','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(138,NULL,'GA-0034','M. Yasin','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(139,NULL,'TK-0012','Mahany, A.Md','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(141,NULL,'SD-0029','Mamay Mayang Sari, S.Psi','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(142,NULL,'SD-0019','Mariatul Khiftiyah, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(143,NULL,'SD-0040','Mastari S.Pd.I','Wakasek Kesiswaan SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(144,NULL,'SMP-0008','Maulidina Miftahul Alma, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(145,NULL,'SD-0034','Mawar Elista S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(146,NULL,'SD-0014','Mega Anggitha, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(148,NULL,'SMP-0005','Muhammad Abi Farhan, S.Pd','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(150,NULL,'GA-0038','Muhammad Aris Saputra','CSO',1,50,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(152,NULL,'SMA-0006','Muhammad Fadly Syawal, S.Pd','Guru BK SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(158,NULL,'SMP-0017','Nasrini, S.Pd','Wakasek Kurikulum SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(159,NULL,'SD-0052','Nazaruddin, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(160,NULL,'SD-0032','Nina Nur\'aini, S.Pd.','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(161,NULL,'SMP-0018','Nur Rahma, S.Pd','Guru BK SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(162,NULL,'SD-0047','Nurdiani Naibaho, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(163,NULL,'SD-0009','Nurul Badriyah, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(164,NULL,'SD-0050','Nurul Nabila, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(165,NULL,'SMA-0005','Octaviani Mahartini Prihatiningrum, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(166,NULL,'SD-0016','Partono Iman, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(167,NULL,'GA-0044','Prasojo','Gardener',1,50,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(168,NULL,'SD-0003','Qotrunnada Salsabila, S.S','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(169,NULL,'SMA-0004','Rahma Paramitha, S.Pd','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(172,NULL,'SD-0043','Redifa Fajar Prasetya, S.Sos','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(173,94,'FA-0002','Rista Yuniarti, S.Ak','Supervisor Finance',9,15,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(174,NULL,'FA-0004','Riyani, S.Ak','Staf Finance',9,173,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(175,NULL,'SD-0026','Rofiah, S.Pd.I','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(176,NULL,'SD-0022','Rusmaryana, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(177,NULL,'SD-0038','Sabrina Mulia Sari, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(179,NULL,'GA-0036','Silvia Rahayu, S.E.I','Supervisor Logistik & Pengadaan',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(180,NULL,'SMA-0003','Siti Nur Istiqomah, S.Ag','Guru SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(181,NULL,'TK-0007','Siti Qoiriah, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(182,NULL,'SD-0025','Siti Sarah Fathonah, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(183,NULL,'SMP-0012','Siti Umroh, S.Ag','Kepala Sekolah SMP',7,NULL,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(184,NULL,'SD-0030','Siti Zahronah, S.Pd.I','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(185,NULL,'GA-0029','Slamet','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(187,NULL,'SMA-0014','Suchy Luchiyanti Pratiwi, S.Pd','Wakasek Kurikulum SMA',8,100,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(188,NULL,'GA-0039','Surono','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(189,NULL,'SD-0041','Suwandi, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(191,NULL,'SD-0008','Syarah Nuur Rahmah Awalia, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(193,NULL,'SD-0045','Tri Astuti, S.Psi','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(195,NULL,'TK-0008','Widha Khalida Razak','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(196,NULL,'SD-0046','Widiya Ayu Lestari, S.Psi','Guru BK SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(197,NULL,'SD-0011','Yayu Eko Rusmiati, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(198,NULL,'TK-0006','Yuli Susanti, S.Pd','Guru TK',5,112,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(199,NULL,'SMP-0006','Zaimul Haq, M.Ag','Guru Tahfidz SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(200,NULL,'GA-0043','Zainal Arifin','Security',1,1,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(201,NULL,'SD-0015','Zarkhoni Imam Suteguh, S.Pd','Guru SD',6,16,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(202,NULL,'SMP-0014','Zeti Kesuma Dewi, S.S','Guru SMP',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15'),(204,NULL,'SMP-0011','Zihan Fauziah Rahmah, S.Pd. Kom','Pustakawan SMP & SMA',7,183,'aktif',NULL,NULL,'2026-10-26 02:07:15');
/*!40000 ALTER TABLE `karyawan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategori_arsip`
--

DROP TABLE IF EXISTS `kategori_arsip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategori_arsip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(60) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori_arsip`
--

LOCK TABLES `kategori_arsip` WRITE;
/*!40000 ALTER TABLE `kategori_arsip` DISABLE KEYS */;
INSERT INTO `kategori_arsip` VALUES (1,'Surat Menyurat','Surat masuk/keluar yayasan & unit'),(2,'Dokumen Kepegawaian','Data & dokumen administrasi karyawan'),(3,'Laporan Kegiatan','Dokumentasi & laporan pelaksanaan kegiatan/event'),(4,'Legal & Perizinan','Dokumen legal, izin, dan sertifikat'),(6,'laporan keuangan','pengeluaran');
/*!40000 ALTER TABLE `kategori_arsip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kegiatan_karyawan`
--

DROP TABLE IF EXISTS `kegiatan_karyawan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kegiatan_karyawan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `jenis` enum('rutin','non-rutin') NOT NULL DEFAULT 'rutin',
  `deskripsi` text NOT NULL,
  `tanggal` date NOT NULL,
  `tenggat` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `status` enum('ditugaskan','dikerjakan','selesai') NOT NULL DEFAULT 'ditugaskan',
  `selesai_pada` datetime DEFAULT NULL,
  `hasil_laporan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `foto_laporan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `karyawan_id` (`karyawan_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `idx_kegiatan_status_jadwal` (`status`,`tenggat`,`tanggal`),
  CONSTRAINT `kegiatan_karyawan_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kegiatan_karyawan_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kegiatan_karyawan`
--

LOCK TABLES `kegiatan_karyawan` WRITE;
/*!40000 ALTER TABLE `kegiatan_karyawan` DISABLE KEYS */;
INSERT INTO `kegiatan_karyawan` VALUES (24,5,5,'rutin','Perbaikan instalasi AC Ruang Rapat','2026-10-23',NULL,NULL,'selesai','2026-10-23 15:21:09',NULL,'2026-10-23 15:21:09',NULL),(25,5,5,'rutin','Pemeliharaan genset darurat','2026-10-23',NULL,NULL,'selesai','2026-10-23 15:22:26',NULL,'2026-10-23 15:22:26',NULL),(26,5,5,'rutin','Pemeliharaan genset darurat','2026-10-23',NULL,NULL,'selesai','2026-10-23 15:22:51',NULL,'2026-10-23 15:22:51',NULL);
/*!40000 ALTER TABLE `kegiatan_karyawan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan`
--

DROP TABLE IF EXISTS `laporan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `laporan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jenis_laporan` varchar(80) NOT NULL,
  `modul_asal` varchar(30) NOT NULL,
  `periode` varchar(20) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `tanggal_generate` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `laporan_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan`
--

LOCK TABLES `laporan` WRITE;
/*!40000 ALTER TABLE `laporan` DISABLE KEYS */;
/*!40000 ALTER TABLE `laporan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_aktivitas`
--

DROP TABLE IF EXISTS `log_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `modul` varchar(30) NOT NULL,
  `aktivitas` varchar(500) NOT NULL,
  `waktu` datetime DEFAULT current_timestamp(),
  `nama_user` varchar(100) DEFAULT NULL,
  `role_user` varchar(40) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=412 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_aktivitas`
--

LOCK TABLES `log_aktivitas` WRITE;
/*!40000 ALTER TABLE `log_aktivitas` DISABLE KEYS */;
INSERT INTO `log_aktivitas` VALUES (1,1,'auth','Logout dari sistem','2026-10-14 07:49:49','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(2,17,'auth','Login ke sistem','2026-10-14 07:49:54','Ansor Nurjaya, S.Pd','TU',NULL),(3,17,'auth','Logout dari sistem','2026-10-14 07:51:09','Ansor Nurjaya, S.Pd','TU',NULL),(4,1,'auth','Login ke sistem','2026-10-14 07:51:13','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(5,1,'auth','Login ke sistem','2026-10-14 09:35:57','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(6,1,'auth','Logout dari sistem','2026-10-14 10:30:59','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(7,17,'auth','Login ke sistem','2026-10-14 10:31:04','Ansor Nurjaya, S.Pd','TU',NULL),(8,17,'maintenance','Mengajukan perbaikan/perawatan fasilitas (menunggu atasan)','2026-10-14 10:34:17','Ansor Nurjaya, S.Pd','TU',NULL),(9,17,'auth','Logout dari sistem','2026-10-14 10:34:21','Ansor Nurjaya, S.Pd','TU',NULL),(10,18,'auth','Login ke sistem','2026-10-14 10:34:26','Tarni, S.Pd','Kepala Sekolah',NULL),(11,18,'maintenance','Approval Atasan untuk maintenance #1: disetujui_atasan','2026-10-14 10:34:33','Tarni, S.Pd','Kepala Sekolah',NULL),(12,18,'auth','Logout dari sistem','2026-10-14 10:34:37','Tarni, S.Pd','Kepala Sekolah',NULL),(13,6,'auth','Login ke sistem','2026-10-14 10:34:42','Muhammad Giyanto','Team Leader (TL)',NULL),(14,6,'maintenance','Verifikasi kondisi fasilitas pengajuan #1: diverifikasi','2026-10-14 10:34:48','Muhammad Giyanto','Team Leader (TL)',NULL),(15,6,'auth','Logout dari sistem','2026-10-14 10:34:53','Muhammad Giyanto','Team Leader (TL)',NULL),(16,5,'auth','Login ke sistem','2026-10-14 10:35:10','Riki indramars','SPV Sapras',NULL),(17,5,'maintenance','Approval pengajuan maintenance #1: disetujui','2026-10-14 10:35:15','Riki indramars','SPV Sapras',NULL),(18,5,'auth','Logout dari sistem','2026-10-14 10:36:56','Riki indramars','SPV Sapras',NULL),(19,7,'auth','Login ke sistem','2026-10-14 10:37:01','Ahmad San Rohmadi','Engineering',NULL),(20,7,'maintenance','Update proses maintenance #1','2026-10-14 10:54:39','Ahmad San Rohmadi','Engineering',NULL),(21,7,'maintenance','Update proses maintenance #1 (selesai)','2026-10-14 10:56:23','Ahmad San Rohmadi','Engineering',NULL),(22,7,'auth','Logout dari sistem','2026-10-14 10:56:35','Ahmad San Rohmadi','Engineering',NULL),(23,6,'auth','Login ke sistem','2026-10-14 10:56:40','Muhammad Giyanto','Team Leader (TL)',NULL),(24,6,'auth','Logout dari sistem','2026-10-14 11:37:41','Muhammad Giyanto','Team Leader (TL)',NULL),(25,1,'auth','Login ke sistem','2026-10-14 11:37:44','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(26,1,'auth','Logout dari sistem','2026-10-14 11:39:48','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(27,11,'auth','Login ke sistem','2026-10-14 19:29:37','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(28,11,'auth','Logout dari sistem','2026-10-14 19:30:05','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(29,10,'auth','Login ke sistem','2026-10-14 19:30:10','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(30,11,'auth','Login ke sistem','2026-10-14 19:30:40','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(31,11,'auth','Logout dari sistem','2026-10-14 19:31:35','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(32,10,'auth','Login ke sistem','2026-10-14 19:31:41','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(33,10,'auth','Logout dari sistem','2026-10-14 19:32:00','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(34,11,'auth','Login ke sistem','2026-10-14 19:32:08','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(35,11,'karyawan','Menambahkan personil: Cahya (CSO - GA-0008)','2026-10-14 19:34:03','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(36,11,'auth','Logout dari sistem','2026-10-14 19:35:51','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(37,10,'auth','Login ke sistem','2026-10-14 19:35:56','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(38,10,'karyawan_kinerja','Memberikan tugas non-rutinitas kepada Slamet Riyadi (CSO)','2026-10-14 19:38:11','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(39,10,'auth','Logout dari sistem','2026-10-14 19:39:27','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(40,11,'auth','Login ke sistem','2026-10-14 19:39:41','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(41,11,'inventaris','Mengajukan peminjaman barang (menunggu atasan)','2026-10-14 19:59:34','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(42,11,'auth','Logout dari sistem','2026-10-14 19:59:58','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(43,10,'auth','Login ke sistem','2026-10-14 20:00:03','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(44,10,'inventaris','Approval Atasan untuk peminjaman #1: disetujui_atasan','2026-10-14 20:00:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(45,10,'auth','Logout dari sistem','2026-10-14 20:00:49','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(46,14,'auth','Login ke sistem','2026-10-14 20:01:06','Apri Supriani, S.M','Staff CRM',NULL),(47,1,'auth','Login ke sistem','2026-10-15 03:01:59','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(48,1,'akun','Membuat akun baru: cso2','2026-10-15 03:16:45','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(49,1,'akun','Membuat akun baru: cso3','2026-10-15 03:18:06','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(50,1,'akun','Membuat akun baru: cso4','2026-10-15 03:25:19','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(51,1,'akun','Membuat akun baru: cso5','2026-10-15 03:28:02','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(52,1,'akun','Membuat akun baru: cso6','2026-10-15 03:28:45','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(53,1,'akun','Membuat akun baru: cso7','2026-10-15 03:29:29','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(54,1,'akun','Membuat akun baru: cso8','2026-10-15 03:30:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(55,1,'akun','Membuat akun baru: cso9','2026-10-15 03:31:31','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(56,1,'akun','Membuat akun baru: cso10','2026-10-15 03:32:05','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(57,1,'akun','Membuat akun baru: cso11','2026-10-15 03:32:55','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(58,1,'akun','Membuat akun baru: cso12','2026-10-15 03:33:37','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(59,1,'akun','Membuat akun baru: gardener2','2026-10-15 03:34:59','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(60,1,'akun','Membuat akun baru: gardener3','2026-10-15 03:35:40','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(61,1,'akun','Membuat akun baru: gardener4','2026-10-15 03:38:03','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(62,1,'akun','Membuat akun baru: gardener5','2026-10-15 03:39:01','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(63,1,'akun','Membuat akun baru: kepsek_smp','2026-10-15 03:53:14','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(64,1,'akun','Membuat akun baru: kepsek_sma','2026-10-15 03:54:26','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(65,1,'akun','Membuat akun baru: kepsek_tk','2026-10-15 03:56:07','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(66,1,'akun','Membuat akun baru: tu_tk','2026-10-15 03:58:09','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(67,1,'akun','Membuat akun baru: tu_smp','2026-10-15 03:59:03','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(68,1,'akun','Membuat akun baru: tu_sma','2026-10-15 04:02:08','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(69,1,'auth','Logout dari sistem','2026-10-15 04:29:01','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(70,1,'auth','Login ke sistem','2026-10-15 23:40:15','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(71,1,'auth','Login ke sistem','2026-10-15 23:48:44','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(72,1,'akun','Memperbarui data akun: engineering1 (ID: 7)','2026-10-15 23:49:16','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(73,1,'akun','Membuat akun baru: engineering2','2026-10-15 23:50:01','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(74,1,'akun','Membuat akun baru: engineering3','2026-10-15 23:50:44','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(75,1,'akun','Menghapus akun pengguna: gardener1 (ID: 9)','2026-10-15 23:54:53','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(76,1,'akun','Memperbarui data akun: gardener1 (ID: 30)','2026-10-15 23:55:35','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(77,1,'akun','Memperbarui data akun: gardener2 (ID: 31)','2026-10-15 23:55:42','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(78,1,'akun','Memperbarui data akun: gardener3 (ID: 32)','2026-10-15 23:55:47','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(79,1,'akun','Memperbarui data akun: gardener4 (ID: 33)','2026-10-15 23:55:58','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(80,1,'auth','Logout dari sistem','2026-10-16 01:35:03','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(81,1,'auth','Login ke sistem','2026-10-16 05:36:13','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(82,1,'auth','Logout dari sistem','2026-10-16 05:36:49','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(83,1,'auth','Login ke sistem','2026-10-18 12:02:57','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(84,42,'auth','Login ke sistem','2026-10-19 02:42:19','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(85,1,'auth','Logout dari sistem','2026-10-19 02:56:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(86,42,'auth','Login ke sistem','2026-10-19 02:56:27','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(87,42,'auth','Logout dari sistem','2026-10-19 02:56:42','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(88,1,'auth','Login ke sistem','2026-10-19 02:56:46','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(89,1,'auth','Logout dari sistem','2026-10-19 03:27:38','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(90,42,'auth','Login ke sistem','2026-10-19 03:27:44','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(91,42,'inventaris','Mengajukan peminjaman barang (menunggu atasan)','2026-10-19 03:28:09','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(92,42,'auth','Logout dari sistem','2026-10-19 03:28:13','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(93,43,'auth','Login ke sistem','2026-10-19 03:28:17','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(94,43,'inventaris','Approval Atasan untuk peminjaman #3: disetujui_atasan','2026-10-19 03:28:22','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(95,43,'auth','Logout dari sistem','2026-10-19 03:28:26','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(96,4,'auth','Login ke sistem','2026-10-19 03:28:29','Inang Setiawati Siregar, S.M','Staff Inventaris',NULL),(97,4,'auth','Logout dari sistem','2026-10-19 03:28:44','Inang Setiawati Siregar, S.M','Staff Inventaris',NULL),(98,3,'auth','Login ke sistem','2026-10-19 03:28:47','Dona Nuryahya, S.Kom','SPV Inventaris',NULL),(99,3,'inventaris','Memberikan approval () untuk peminjaman #3','2026-10-19 03:28:53','Dona Nuryahya, S.Kom','SPV Inventaris',NULL),(100,3,'inventaris','Memberikan approval () untuk peminjaman #1','2026-10-19 03:28:56','Dona Nuryahya, S.Kom','SPV Inventaris',NULL),(101,3,'auth','Logout dari sistem','2026-10-19 03:29:03','Dona Nuryahya, S.Kom','SPV Inventaris',NULL),(102,43,'auth','Login ke sistem','2026-10-19 03:29:07','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(103,43,'auth','Logout dari sistem','2026-10-19 03:29:15','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(104,4,'auth','Login ke sistem','2026-10-19 03:29:18','Inang Setiawati Siregar, S.M','Staff Inventaris',NULL),(105,4,'inventaris','Memperbarui data barang #2','2026-10-19 03:30:20','Inang Setiawati Siregar, S.M','Staff Inventaris',NULL),(106,1,'auth','Login ke sistem','2026-10-19 19:23:05','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(107,1,'auth','Logout dari sistem','2026-10-19 20:00:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(108,6,'auth','Login ke sistem','2026-10-19 20:00:40','Muhammad Giyanto','Team Leader (TL)',NULL),(109,6,'karyawan_kinerja','Memberikan tugas non-rutinitas kepada Cahya (CSO)','2026-10-19 20:04:00','Muhammad Giyanto','Team Leader (TL)',NULL),(110,6,'karyawan_kinerja','Memperbarui status tugas non-rutin #2 menjadi: dikerjakan','2026-10-19 20:04:11','Muhammad Giyanto','Team Leader (TL)',NULL),(111,6,'karyawan_kinerja','Memperbarui status tugas non-rutin #2 menjadi: selesai','2026-10-19 20:04:26','Muhammad Giyanto','Team Leader (TL)',NULL),(112,6,'auth','Logout dari sistem','2026-10-19 20:04:42','Muhammad Giyanto','Team Leader (TL)',NULL),(113,8,'auth','Login ke sistem','2026-10-19 20:04:53','Asep Setiawan','CSO',NULL),(114,8,'karyawan_kinerja','Memperbarui status tugas non-rutin #1 menjadi: selesai','2026-10-19 20:05:36','Asep Setiawan','CSO',NULL),(115,8,'auth','Logout dari sistem','2026-10-19 20:05:49','Asep Setiawan','CSO',NULL),(116,5,'auth','Login ke sistem','2026-10-19 20:05:52','Riki indramars','SPV Sapras',NULL),(117,5,'karyawan_kinerja','SPV Sapras memberikan penilaian kinerja tugas kepada Cahya (CSO)','2026-10-19 20:06:57','Riki indramars','SPV Sapras',NULL),(118,5,'arsip','Mengunggah dokumen: dokumen','2026-10-19 20:13:53','Riki indramars','SPV Sapras',NULL),(119,5,'auth','Logout dari sistem','2026-10-19 20:14:02','Riki indramars','SPV Sapras',NULL),(120,16,'auth','Login ke sistem','2026-10-19 20:14:16','Husna Assabiila Yassarah, S.Kom','Staff QMS',NULL),(121,16,'arsip','Backup dokumen #1','2026-10-19 20:17:22','Husna Assabiila Yassarah, S.Kom','Staff QMS',NULL),(122,16,'auth','Logout dari sistem','2026-10-19 20:27:49','Husna Assabiila Yassarah, S.Kom','Staff QMS',NULL),(123,1,'auth','Login ke sistem','2026-10-20 01:45:42','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(124,1,'akun','Memperbarui data akun: manajerga (ID: 2)','2026-10-20 02:11:41','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(125,1,'akun','Memperbarui data akun: spvinventaris (ID: 3)','2026-10-20 02:12:50','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(126,1,'akun','Memperbarui data akun: staffinventaris (ID: 4)','2026-10-20 02:14:09','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(127,1,'akun','Memperbarui data akun: teamleader (ID: 6)','2026-10-20 02:16:37','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(128,1,'akun','Memperbarui data akun: engineering1 (ID: 7)','2026-10-20 02:29:29','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(129,1,'akun','Menghapus akun pengguna: engineering2 (ID: 40)','2026-10-20 02:29:45','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(130,1,'akun','Memperbarui data akun: engineering1 (ID: 7)','2026-10-20 02:30:16','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(131,1,'akun','Memperbarui data akun: engineering2 (ID: 41)','2026-10-20 02:30:49','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(132,1,'akun','Memperbarui data akun: cso1 (ID: 8)','2026-10-20 02:32:41','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(133,1,'akun','Memperbarui data akun: manajerhrd (ID: 10)','2026-10-20 02:34:12','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(134,1,'akun','Memperbarui data akun: staffhrd (ID: 11)','2026-10-20 02:36:00','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(135,1,'akun','Memperbarui data akun: manajermarkom (ID: 12)','2026-10-20 02:37:34','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(136,1,'akun','Memperbarui data akun: spvcrm (ID: 13)','2026-10-20 02:39:40','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(137,1,'akun','Memperbarui data akun: staffcrm (ID: 14)','2026-10-20 02:51:50','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(138,1,'akun','Memperbarui data akun: manajerqms (ID: 15)','2026-10-20 02:53:20','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(139,1,'akun','Memperbarui data akun: staffqms (ID: 16)','2026-10-20 02:55:13','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(140,1,'akun','Memperbarui data akun: tu_sd (ID: 17)','2026-10-20 02:55:49','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(141,1,'akun','Memperbarui data akun: kepsek_sd (ID: 18)','2026-10-20 02:56:19','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(142,1,'akun','Memperbarui data akun: cso2 (ID: 19)','2026-10-20 02:56:49','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(143,1,'akun','Memperbarui data akun: cso2 (ID: 19)','2026-10-20 02:58:32','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(144,1,'akun','Memperbarui data akun: cso2 (ID: 19)','2026-10-20 02:58:42','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(145,1,'akun','Memperbarui data akun: cso3 (ID: 20)','2026-10-20 02:59:26','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(146,1,'akun','Memperbarui data akun: cso4 (ID: 21)','2026-10-20 03:00:17','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(147,1,'akun','Memperbarui data akun: cso5 (ID: 22)','2026-10-20 03:00:42','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(148,1,'akun','Memperbarui data akun: cso6 (ID: 23)','2026-10-20 03:01:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(149,1,'akun','Memperbarui data akun: cso7 (ID: 24)','2026-10-20 03:01:57','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(150,1,'akun','Memperbarui data akun: cso8 (ID: 25)','2026-10-20 03:02:28','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(151,1,'akun','Memperbarui data akun: cso9 (ID: 26)','2026-10-20 03:03:04','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(152,1,'akun','Memperbarui data akun: cso9 (ID: 26)','2026-10-20 03:03:19','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(153,1,'akun','Memperbarui data akun: cso10 (ID: 27)','2026-10-20 03:03:59','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(154,1,'akun','Memperbarui data akun: cso10 (ID: 27)','2026-10-20 03:04:32','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(155,1,'akun','Memperbarui data akun: cso11 (ID: 28)','2026-10-20 03:05:04','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(156,1,'akun','Memperbarui data akun: cso12 (ID: 29)','2026-10-20 03:05:36','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(157,1,'auth','Logout dari sistem','2026-10-20 03:06:51','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(158,30,'auth','Login ke sistem','2026-10-20 03:06:59','Sakirun','Gardener',NULL),(159,30,'auth','Logout dari sistem','2026-10-20 03:07:05','Sakirun','Gardener',NULL),(160,1,'auth','Login ke sistem','2026-10-20 03:07:07','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(161,42,'auth','Login ke sistem','2026-10-20 03:10:21','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(162,43,'auth','Login ke sistem','2026-10-20 03:10:21','Muhamad Fahmi, S.Akun','Manajer FA',NULL),(163,13,'auth','Login ke sistem','2026-10-20 03:10:21','Ahmad Qosim, S.Pd.I','SPV CRM',NULL),(164,42,'auth','Login ke sistem','2026-10-20 03:11:43','Nabila Rif\'atul Izzah, S.Ak','Staff FA',NULL),(165,1,'auth','Login ke sistem','2026-10-20 03:21:23','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(166,1,'akun','Membuat akun baru: gardener_uji01','2026-10-20 03:28:39','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(167,1,'auth','Logout dari sistem','2026-10-20 03:30:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(170,1,'auth','Login ke sistem','2026-10-20 03:33:08','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(171,1,'auth','Login ke sistem','2026-10-20 03:33:43','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(172,1,'auth','Login ke sistem','2026-10-20 03:34:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(173,1,'akun','Membuat akun baru: gardener_uji02_diff','2026-10-20 03:34:31','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(175,1,'auth','Login ke sistem','2026-10-20 03:35:31','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(176,1,'akun','Mengubah status akun #44 menjadi nonaktif','2026-10-20 03:36:19','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(177,1,'akun','Menghapus akun pengguna: gardener_uji01 (ID: 44)','2026-10-20 03:36:55','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(178,1,'akun','Memperbarui data akun: tu_sd (ID: 17)','2026-10-20 03:44:29','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(179,1,'akun','Memperbarui data akun: tu_sd (ID: 17)','2026-10-20 03:44:41','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(180,1,'akun','Memperbarui data akun: gardener2 (ID: 31)','2026-10-20 03:45:59','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(181,1,'akun','Memperbarui data akun: gardener2 (ID: 31)','2026-10-20 03:46:08','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(182,1,'akun','Memperbarui data akun: gardener1 (ID: 30)','2026-10-20 03:46:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(183,1,'akun','Memperbarui data akun: gardener2 (ID: 31)','2026-10-20 03:46:41','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(184,1,'akun','Memperbarui data akun: gardener3 (ID: 32)','2026-10-20 03:47:02','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(185,1,'akun','Memperbarui data akun: gardener4 (ID: 33)','2026-10-20 03:47:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(186,1,'akun','Membuat akun baru: gardener5','2026-10-20 03:50:44','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(187,1,'akun','Memperbarui data akun: gardener3 (ID: 32)','2026-10-20 03:51:34','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(188,1,'akun','Memperbarui data akun: gardener5 (ID: 46)','2026-10-20 03:51:51','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(189,1,'auth','Logout dari sistem','2026-10-20 03:52:03','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(190,1,'auth','Login ke sistem','2026-10-20 18:21:19','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(191,1,'akun','Memperbarui data akun: tu_tk (ID: 37)','2026-10-20 18:28:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(192,1,'akun','Memperbarui data akun: tu_tk (ID: 37)','2026-10-20 18:29:34','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(193,1,'akun','Memperbarui data akun: tu_smp (ID: 38)','2026-10-20 18:30:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(194,1,'akun','Memperbarui data akun: tu_sma (ID: 39)','2026-10-20 18:30:48','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(195,1,'akun','Memperbarui data akun: stafffa (ID: 42)','2026-10-20 18:31:45','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(196,1,'akun','Memperbarui data akun: manajerfa (ID: 43)','2026-10-20 18:32:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(197,1,'akun','Memperbarui data akun: admin (ID: 1)','2026-10-20 18:33:27','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(198,1,'auth','Logout dari sistem','2026-10-20 19:18:50','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(199,5,'auth','Login ke sistem','2026-10-20 19:18:57','Riki indramars','SPV Sapras',NULL),(200,5,'auth','Logout dari sistem','2026-10-20 19:19:38','Riki indramars','SPV Sapras',NULL),(201,1,'auth','Login ke sistem','2026-10-20 19:19:43','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(202,1,'akun','Memperbarui data akun: spvsapras (ID: 5)','2026-10-20 19:20:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(203,1,'auth','Logout dari sistem','2026-10-20 19:20:43','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(204,17,'auth','Login ke sistem','2026-10-20 19:20:59','Ansor Nurjaya, S.Pd','TU',NULL),(205,17,'auth','Logout dari sistem','2026-10-20 19:21:37','Ansor Nurjaya, S.Pd','TU',NULL),(206,6,'auth','Login ke sistem','2026-10-20 19:21:40','Muhammad Giyanto','Team Leader (TL)',NULL),(207,6,'auth','Logout dari sistem','2026-10-20 19:22:30','Muhammad Giyanto','Team Leader (TL)',NULL),(208,5,'auth','Login ke sistem','2026-10-20 19:22:35','Riki indramars','SPV Sapras',NULL),(209,5,'auth','Logout dari sistem','2026-10-20 19:23:15','Riki indramars','SPV Sapras',NULL),(210,6,'auth','Login ke sistem','2026-10-20 19:23:20','Muhammad Giyanto','Team Leader (TL)',NULL),(211,6,'auth','Logout dari sistem','2026-10-20 19:23:34','Muhammad Giyanto','Team Leader (TL)',NULL),(212,5,'auth','Login ke sistem','2026-10-20 19:23:41','Riki indramars','SPV Sapras',NULL),(213,5,'auth','Login ke sistem','2026-10-20 19:59:34','Riki indramars','SPV Sapras',NULL),(214,5,'karyawan_kinerja','SPV Sapras memberikan penilaian kinerja tugas kepada Slamet Riyadi (CSO)','2026-10-20 19:59:34','Riki indramars','SPV Sapras',NULL),(215,5,'karyawan_kinerja','SPV Sapras memperbarui penilaian kinerja tugas #1 untuk Slamet Riyadi','2026-10-20 19:59:35','Riki indramars','SPV Sapras',NULL),(216,5,'karyawan_kinerja','SPV Sapras memperbarui penilaian kinerja tugas #1 untuk Slamet Riyadi','2026-10-20 19:59:35','Riki indramars','SPV Sapras',NULL),(217,5,'karyawan_kinerja','SPV Sapras memperbarui penilaian kinerja tugas #1 untuk Slamet Riyadi','2026-10-20 19:59:35','Riki indramars','SPV Sapras',NULL),(218,5,'auth','Logout dari sistem','2026-10-20 20:40:17','Riki indramars','SPV Sapras',NULL),(219,8,'auth','Login ke sistem','2026-10-20 20:40:26','Asep Setiawan','CSO',NULL),(220,8,'auth','Logout dari sistem','2026-10-20 20:40:58','Asep Setiawan','CSO',NULL),(221,6,'auth','Login ke sistem','2026-10-20 20:41:06','Muhammad Giyanto','Team Leader (TL)',NULL),(222,6,'karyawan_kinerja','Memberikan tugas non-rutinitas kepada Slamet Riyadi (CSO)','2026-10-20 20:43:50','Muhammad Giyanto','Team Leader (TL)',NULL),(223,6,'auth','Logout dari sistem','2026-10-20 20:43:59','Muhammad Giyanto','Team Leader (TL)',NULL),(224,8,'auth','Login ke sistem','2026-10-20 20:44:18','Asep Setiawan','CSO',NULL),(225,8,'auth','Logout dari sistem','2026-10-20 20:49:24','Asep Setiawan','CSO',NULL),(226,1,'auth','Login ke sistem','2026-10-20 20:49:27','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(227,8,'auth','Login ke sistem','2026-10-21 00:53:47','Asep Setiawan','CSO',NULL),(228,8,'karyawan_kinerja','Memperbarui status tugas non-rutin #3 menjadi: selesai','2026-10-21 00:53:47','Asep Setiawan','CSO',NULL),(229,8,'auth','Login ke sistem','2026-10-21 00:54:12','Asep Setiawan','CSO',NULL),(230,8,'karyawan_kinerja','Memperbarui status tugas non-rutin #3 menjadi: selesai','2026-10-21 00:54:13','Asep Setiawan','CSO',NULL),(231,8,'auth','Login ke sistem','2026-10-21 00:58:49','Asep Setiawan','CSO',NULL),(232,8,'auth','Logout dari sistem','2026-10-21 00:59:21','Asep Setiawan','CSO',NULL),(233,6,'auth','Login ke sistem','2026-10-21 00:59:24','Muhammad Giyanto','Team Leader (TL)',NULL),(234,6,'auth','Logout dari sistem','2026-10-21 01:37:13','Muhammad Giyanto','Team Leader (TL)',NULL),(235,1,'auth','Login ke sistem','2026-10-21 01:37:15','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(236,1,'auth','Login ke sistem','2026-10-21 02:23:21','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(237,10,'auth','Login ke sistem','2026-10-21 02:23:21','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(238,6,'auth','Login ke sistem','2026-10-21 02:23:21','Muhammad Giyanto','Team Leader (TL)',NULL),(239,8,'auth','Login ke sistem','2026-10-21 02:23:21','Asep Setiawan','CSO',NULL),(240,17,'auth','Login ke sistem','2026-10-21 02:23:21','Ansor Nurjaya, S.Pd','TU',NULL),(241,10,'karyawan','Menambahkan karyawan: Budi IT Tester (Staff IT Support - TEST-IT-888)','2026-10-21 02:23:21','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(242,6,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Slamet Riyadi (CSO)','2026-10-21 02:23:21','Muhammad Giyanto','Team Leader (TL)',NULL),(243,8,'karyawan_kinerja','Memperbarui status tugas #7 menjadi: selesai','2026-10-21 02:23:21','Asep Setiawan','CSO',NULL),(244,6,'karyawan_kinerja','Memberikan penilaian kinerja tugas kepada Slamet Riyadi (CSO)','2026-10-21 02:23:22','Muhammad Giyanto','Team Leader (TL)',NULL),(245,1,'auth','Login ke sistem','2026-10-21 03:16:54','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(246,1,'auth','Login ke sistem','2026-10-21 03:36:34','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(247,10,'auth','Login ke sistem','2026-10-21 03:36:34','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(248,6,'auth','Login ke sistem','2026-10-21 03:36:34','Muhammad Giyanto','Team Leader (TL)',NULL),(249,8,'auth','Login ke sistem','2026-10-21 03:36:34','Asep Setiawan','CSO',NULL),(250,17,'auth','Login ke sistem','2026-10-21 03:36:34','Ansor Nurjaya, S.Pd','TU',NULL),(251,10,'karyawan','Menambahkan karyawan: Budi IT Tester (Staff IT Support - TEST-IT-888)','2026-10-21 03:36:34','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(252,6,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Asep Setiawan (CSO)','2026-10-21 03:36:35','Muhammad Giyanto','Team Leader (TL)',NULL),(253,8,'karyawan_kinerja','Memperbarui status tugas #11 menjadi: selesai','2026-10-21 03:36:35','Asep Setiawan','CSO',NULL),(254,6,'karyawan_kinerja','Memberikan penilaian kinerja tugas kepada Asep Setiawan (CSO)','2026-10-21 03:36:35','Muhammad Giyanto','Team Leader (TL)',NULL),(255,1,'auth','Logout dari sistem','2026-10-21 03:43:52','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(256,10,'auth','Login ke sistem','2026-10-21 22:39:25','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(257,10,'auth','Logout dari sistem','2026-10-21 23:27:52','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(258,1,'auth','Login ke sistem','2026-10-21 23:27:55','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(259,11,'auth','Login ke sistem','2026-10-22 00:49:16','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(260,11,'auth','Login ke sistem','2026-10-22 00:50:40','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(261,11,'auth','Login ke sistem','2026-10-22 00:51:00','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(262,11,'auth','Login ke sistem','2026-10-22 00:51:29','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(263,10,'auth','Login ke sistem','2026-10-22 00:51:30','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(264,2,'auth','Login ke sistem','2026-10-22 00:51:30','Ahmad Ubaydilah, S.E','Manajer GA',NULL),(265,5,'auth','Login ke sistem','2026-10-22 00:51:31','Riki indramars','SPV Sapras',NULL),(266,6,'auth','Login ke sistem','2026-10-22 00:51:31','Muhammad Giyanto','Team Leader (TL)',NULL),(267,8,'auth','Login ke sistem','2026-10-22 00:51:31','Asep Setiawan','CSO',NULL),(268,1,'auth','Login ke sistem','2026-10-22 00:51:32','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(269,5,'karyawan_kinerja','Memberikan kegiatan (non-rutin) kepada Muhammad Giyanto (Team Leader (TL))','2026-10-22 00:51:34','Riki indramars','SPV Sapras',NULL),(270,6,'karyawan_kinerja','Memberikan kegiatan (non-rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:51:34','Muhammad Giyanto','Team Leader (TL)',NULL),(271,1,'karyawan_kinerja','Memberikan kegiatan (non-rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:51:34','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(272,11,'auth','Login ke sistem','2026-10-22 00:53:48','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(273,10,'auth','Login ke sistem','2026-10-22 00:53:49','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(274,2,'auth','Login ke sistem','2026-10-22 00:53:49','Ahmad Ubaydilah, S.E','Manajer GA',NULL),(275,5,'auth','Login ke sistem','2026-10-22 00:53:50','Riki indramars','SPV Sapras',NULL),(276,6,'auth','Login ke sistem','2026-10-22 00:53:50','Muhammad Giyanto','Team Leader (TL)',NULL),(277,8,'auth','Login ke sistem','2026-10-22 00:53:50','Asep Setiawan','CSO',NULL),(278,1,'auth','Login ke sistem','2026-10-22 00:53:51','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(279,5,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Muhammad Giyanto (Team Leader (TL))','2026-10-22 00:53:51','Riki indramars','SPV Sapras',NULL),(280,6,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:53:52','Muhammad Giyanto','Team Leader (TL)',NULL),(281,1,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:53:52','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(282,11,'auth','Login ke sistem','2026-10-22 00:54:29','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(283,10,'auth','Login ke sistem','2026-10-22 00:54:29','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(284,2,'auth','Login ke sistem','2026-10-22 00:54:30','Ahmad Ubaydilah, S.E','Manajer GA',NULL),(285,5,'auth','Login ke sistem','2026-10-22 00:54:30','Riki indramars','SPV Sapras',NULL),(286,6,'auth','Login ke sistem','2026-10-22 00:54:30','Muhammad Giyanto','Team Leader (TL)',NULL),(287,8,'auth','Login ke sistem','2026-10-22 00:54:30','Asep Setiawan','CSO',NULL),(288,1,'auth','Login ke sistem','2026-10-22 00:54:30','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(289,5,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Muhammad Giyanto (Team Leader (TL))','2026-10-22 00:54:31','Riki indramars','SPV Sapras',NULL),(290,6,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:54:31','Muhammad Giyanto','Team Leader (TL)',NULL),(291,1,'karyawan_kinerja','Memberikan kegiatan (rutin) kepada Asep Setiawan (CSO)','2026-10-22 00:54:31','Dona Nuryahya, S.Kom','Administrator Sistem',NULL),(292,11,'auth','Login ke sistem','2026-10-22 01:50:12','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(293,10,'auth','Login ke sistem','2026-10-22 01:50:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(294,11,'karyawan','Menambahkan karyawan: Karyawan Uji Coba Staff HRD (Staf Uji - TEST-9870)','2026-10-22 01:50:12','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(295,11,'karyawan','Memperbarui data karyawan: Asep Setiawan (Updated) (GA-0006)','2026-10-22 01:50:12','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(296,10,'karyawan','Memperbarui data karyawan: Asep Setiawan (GA-0006)','2026-10-22 01:50:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(297,10,'karyawan','Mengubah status karyawan #6 menjadi: nonaktif','2026-10-22 01:50:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(298,10,'karyawan','Mengubah status karyawan #6 menjadi: aktif','2026-10-22 01:50:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(299,10,'karyawan','Memperbarui data karyawan: Muhammad Giyanto (GA-0004)','2026-10-22 01:50:12','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(300,11,'auth','Login ke sistem','2026-10-22 01:50:37','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(301,10,'auth','Login ke sistem','2026-10-22 01:50:37','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(302,11,'karyawan','Menambahkan karyawan: Karyawan Uji Coba Staff HRD (Staf Uji - TEST-8500)','2026-10-22 01:50:37','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(303,10,'karyawan','Memperbarui data karyawan: Asep Setiawan (GA-0006)','2026-10-22 01:50:38','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(304,10,'karyawan','Mengubah status karyawan #6 menjadi: nonaktif','2026-10-22 01:50:38','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(305,10,'karyawan','Mengubah status karyawan #6 menjadi: aktif','2026-10-22 01:50:38','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(306,11,'auth','Login ke sistem','2026-10-22 01:50:59','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(307,10,'auth','Login ke sistem','2026-10-22 01:50:59','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(308,11,'karyawan','Menambahkan karyawan: Karyawan Uji Coba Staff HRD (Staf Uji - TEST-5926)','2026-10-22 01:50:59','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(309,11,'karyawan','Memperbarui data karyawan: Asep Setiawan (Updated) (GA-0006)','2026-10-22 01:51:00','Munadya Hasna Dewi, S.Psi','Staff HRD',NULL),(310,10,'karyawan','Memperbarui data karyawan: Asep Setiawan (GA-0006)','2026-10-22 01:51:00','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(311,10,'karyawan','Mengubah status karyawan #6 menjadi: nonaktif','2026-10-22 01:51:00','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(312,10,'karyawan','Mengubah status karyawan #6 menjadi: aktif','2026-10-22 01:51:00','Ratna Sari Dewi, S.H','Manajer HRD',NULL),(317,1,'karyawan','Aktivitas Uji User 1','2026-10-22 02:08:07','User 1','role1',NULL),(324,1,'auth','Login ke sistem','2026-10-23 14:59:56','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(325,5,'auth','Login ke sistem','2026-10-23 15:22:27','Riki indramars','SPV Sapras','::1'),(326,10,'auth','Login ke sistem','2026-10-23 15:22:27','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(327,1,'auth','Login ke sistem','2026-10-23 15:22:27','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(328,5,'auth','Login ke sistem','2026-10-23 15:22:51','Riki indramars','SPV Sapras','::1'),(329,5,'karyawan_kinerja','Memberikan penilaian kinerja tugas #26 kepada Ahmad San Rohmadi','2026-10-23 15:22:51','Riki indramars','SPV Sapras','::1'),(330,5,'karyawan_kinerja','Memperbarui penilaian kinerja tugas #26 untuk Ahmad San Rohmadi','2026-10-23 15:22:51','Riki indramars','SPV Sapras','::1'),(331,10,'auth','Login ke sistem','2026-10-23 15:22:51','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(332,1,'auth','Login ke sistem','2026-10-23 15:22:51','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(333,1,'auth','Logout dari sistem','2026-10-23 16:04:03','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(334,2,'auth','Login ke sistem','2026-10-23 16:04:07','Ahmad Ubaydilah, S.E','Manajer GA','::1'),(335,10,'auth','Login ke sistem','2026-10-23 16:58:34','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(336,2,'auth','Login ke sistem','2026-10-23 16:58:34','Ahmad Ubaydilah, S.E','Manajer GA','::1'),(337,1,'auth','Login ke sistem','2026-10-23 16:58:35','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(338,6,'auth','Login ke sistem','2026-10-23 16:58:35','Muhammad Giyanto','Team Leader (TL)','::1'),(339,5,'auth','Login ke sistem','2026-10-23 16:58:35','Riki indramars','SPV Sapras','::1'),(340,4,'auth','Login ke sistem','2026-10-23 16:58:36','Inang Setiawati Siregar, S.M','Staff Inventaris','::1'),(341,3,'auth','Login ke sistem','2026-10-23 16:58:36','Dona Nuryahya, S.Kom','SPV Inventaris','::1'),(342,3,'maintenance','Approval Atasan untuk maintenance #0: disetujui_atasan','2026-10-23 16:58:36','Dona Nuryahya, S.Kom','SPV Inventaris','::1'),(343,10,'auth','Login ke sistem','2026-10-23 17:01:33','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(344,2,'auth','Login ke sistem','2026-10-23 17:01:34','Ahmad Ubaydilah, S.E','Manajer GA','::1'),(345,1,'auth','Login ke sistem','2026-10-23 17:01:34','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(346,6,'auth','Login ke sistem','2026-10-23 17:01:34','Muhammad Giyanto','Team Leader (TL)','::1'),(347,5,'auth','Login ke sistem','2026-10-23 17:01:35','Riki indramars','SPV Sapras','::1'),(348,4,'auth','Login ke sistem','2026-10-23 17:01:35','Inang Setiawati Siregar, S.M','Staff Inventaris','::1'),(349,3,'auth','Login ke sistem','2026-10-23 17:01:35','Dona Nuryahya, S.Kom','SPV Inventaris','::1'),(350,4,'maintenance','Mengajukan perbaikan/perawatan fasilitas (menunggu atasan)','2026-10-23 17:01:35','Inang Setiawati Siregar, S.M','Staff Inventaris','::1'),(351,3,'maintenance','Approval Atasan untuk maintenance #2: disetujui_atasan','2026-10-23 17:01:35','Dona Nuryahya, S.Kom','SPV Inventaris','::1'),(352,1,'auth','Login ke sistem','2026-10-23 17:28:32','Dona Nuryahya, S.Kom','Administrator Sistem','127.0.0.1'),(353,2,'auth','Login ke sistem','2026-10-23 17:28:32','Ahmad Ubaydilah, S.E','Manajer GA','127.0.0.1'),(354,3,'auth','Login ke sistem','2026-10-23 17:28:32','Dona Nuryahya, S.Kom','SPV Inventaris','127.0.0.1'),(355,4,'auth','Login ke sistem','2026-10-23 17:28:32','Inang Setiawati Siregar, S.M','Staff Inventaris','127.0.0.1'),(356,5,'auth','Login ke sistem','2026-10-23 17:28:32','Riki indramars','SPV Sapras','127.0.0.1'),(357,6,'auth','Login ke sistem','2026-10-23 17:28:33','Muhammad Giyanto','Team Leader (TL)','127.0.0.1'),(358,8,'auth','Login ke sistem','2026-10-23 17:28:33','Asep Setiawan','CSO','127.0.0.1'),(359,30,'auth','Login ke sistem','2026-10-23 17:28:33','Sakirun','Gardener','127.0.0.1'),(360,10,'auth','Login ke sistem','2026-10-23 17:28:34','Ratna Sari Dewi, S.H','Manajer HRD','127.0.0.1'),(361,11,'auth','Login ke sistem','2026-10-23 17:28:34','Munadya Hasna Dewi, S.Psi','Staff HRD','127.0.0.1'),(362,12,'auth','Login ke sistem','2026-10-23 17:28:34','Henrizal Saidi Harahap, M.Ag','Manajer MARKOM','127.0.0.1'),(363,13,'auth','Login ke sistem','2026-10-23 17:28:34','Ahmad Qosim, S.Pd.I','SPV CRM','127.0.0.1'),(364,14,'auth','Login ke sistem','2026-10-23 17:28:34','Apri Supriani, S.M','Staff CRM','127.0.0.1'),(365,15,'auth','Login ke sistem','2026-10-23 17:28:34','Budi Santoso, SP','Manajer QMS','127.0.0.1'),(366,16,'auth','Login ke sistem','2026-10-23 17:28:35','Husna Assabiila Yassarah, S.Kom','Staff QMS','127.0.0.1'),(367,17,'auth','Login ke sistem','2026-10-23 17:28:35','Ansor Nurjaya, S.Pd','TU','127.0.0.1'),(368,18,'auth','Login ke sistem','2026-10-23 17:28:35','Tarni, S.Pd','Kepala Sekolah','127.0.0.1'),(369,43,'auth','Login ke sistem','2026-10-23 17:28:35','Muhamad Fahmi, S.Akun','Manajer FA','127.0.0.1'),(370,42,'auth','Login ke sistem','2026-10-23 17:28:35','Nabila Rif\'atul Izzah, S.Ak','Staff FA','127.0.0.1'),(371,1,'auth','Login ke sistem','2026-10-23 17:32:16','Dona Nuryahya, S.Kom','Administrator Sistem','127.0.0.1'),(372,1,'auth','Login ke sistem','2026-10-26 01:30:48','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(373,1,'auth','Login ke sistem','2026-10-26 01:39:16','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(374,1,'karyawan','Memperbarui data karyawan Zihan Fauziah Rahmah, S.Pd. Kom (0601.0724.03): unit: - -> SMP Al-Syukro, atasan: - -> Umairah, SP.d','2026-10-26 02:14:22','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(375,1,'auth','Login ke sistem','2026-10-26 02:41:23','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(376,1,'auth','Login ke sistem','2026-10-28 01:21:19','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(377,1,'auth','Logout dari sistem','2026-10-28 01:26:44','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(378,10,'auth','Login ke sistem','2026-10-28 01:26:50','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(379,10,'karyawan','Menghapus data karyawan: Muhamad Fahmi, S.Akun (1194.0121.00)','2026-10-28 01:27:52','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(380,10,'karyawan','Memperbarui data karyawan Nabila Rif\'atul Izzah, S.Ak (FA-0002): jabatan: Staff FA -> Staf FA','2026-10-28 01:29:09','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(381,10,'auth','Logout dari sistem','2026-10-28 02:18:45','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(382,94,'auth','Login ke sistem','2026-10-28 02:32:29','Rista Yuniarti, S.Ak','Supervisor FA','::1'),(383,94,'auth','Logout dari sistem','2026-10-28 02:32:55','Rista Yuniarti, S.Ak','Supervisor FA','::1'),(384,42,'auth','Login ke sistem','2026-10-28 02:33:05','Nabila Rif\'atul Izzah, S.Ak','Staff FA','::1'),(385,42,'auth','Logout dari sistem','2026-10-28 02:33:16','Nabila Rif\'atul Izzah, S.Ak','Staff FA','::1'),(386,43,'auth','Login ke sistem','2026-10-28 02:33:20','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(387,43,'auth','Logout dari sistem','2026-10-28 02:33:39','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(388,43,'auth','Login ke sistem','2026-10-28 02:35:20','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(389,42,'auth','Login ke sistem','2026-10-28 02:58:29','Nabila Rif\'atul Izzah, S.Ak','Staff FA','::1'),(390,42,'inventaris','Mengajukan peminjaman barang (menunggu atasan)','2026-10-28 03:00:04','Nabila Rif\'atul Izzah, S.Ak','Staff FA','::1'),(391,42,'auth','Logout dari sistem','2026-10-28 03:00:24','Nabila Rif\'atul Izzah, S.Ak','Staff FA','::1'),(392,94,'auth','Login ke sistem','2026-10-28 03:01:00','Rista Yuniarti, S.Ak','Supervisor FA','::1'),(393,94,'inventaris','Approval Atasan untuk peminjaman #5: disetujui_atasan','2026-10-28 03:01:32','Rista Yuniarti, S.Ak','Supervisor FA','::1'),(394,94,'auth','Logout dari sistem','2026-10-28 03:01:39','Rista Yuniarti, S.Ak','Supervisor FA','::1'),(395,43,'auth','Login ke sistem','2026-10-28 03:02:14','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(396,43,'auth','Logout dari sistem','2026-10-28 03:02:48','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(397,4,'auth','Login ke sistem','2026-10-28 03:05:30','Inang Setiawati Siregar, S.M','Staff Inventaris','::1'),(398,43,'auth','Login ke sistem','2026-10-28 18:48:59','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(399,43,'auth','Logout dari sistem','2026-10-28 18:49:03','Muhamad Fahmi, S.Akun','Manajer FA','::1'),(400,10,'auth','Login ke sistem','2026-10-28 18:49:11','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(401,10,'auth','Login ke sistem','2026-10-29 01:33:18','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(402,10,'auth','Login ke sistem','2026-10-31 18:20:56','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(403,10,'auth','Login ke sistem','2026-11-01 01:10:54','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(404,10,'karyawan','Memperbarui data karyawan Ahmad Abdul Wasiudin, M.Pd (0885.0220.00): atasan: Dina Fitriani, S.Pt -> Cicih Kurniasih, M.Pd','2026-11-01 01:17:10','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(405,10,'auth','Logout dari sistem','2026-11-01 01:21:50','Ratna Sari Dewi, S.H','Manajer HRD','::1'),(406,1,'auth','Login ke sistem','2026-11-01 01:21:55','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(407,1,'auth','Logout dari sistem','2026-11-01 01:23:39','Dona Nuryahya, S.Kom','Administrator Sistem','::1'),(408,17,'auth','Login ke sistem','2026-11-01 01:23:45','Ansor Nurjaya, S.Pd','TU','::1'),(409,17,'auth','Logout dari sistem','2026-11-01 01:23:56','Ansor Nurjaya, S.Pd','TU','::1'),(410,4,'auth','Login ke sistem','2026-11-01 01:24:13','Inang Setiawati Siregar, S.M','Staff Inventaris','::1'),(411,10,'auth','Login ke sistem','2026-11-01 20:14:31','Ratna Sari Dewi, S.H','Manajer HRD','::1');
/*!40000 ALTER TABLE `log_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (5,'::1','2026-10-28 02:19:23'),(6,'::1','2026-10-28 02:19:36'),(7,'::1','2026-10-28 02:24:24');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `modul_asal` varchar(30) NOT NULL,
  `pesan` varchar(255) NOT NULL,
  `status_baca` enum('belum','sudah') NOT NULL DEFAULT 'belum',
  `waktu` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi`
--

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
INSERT INTO `notifikasi` VALUES (1,18,'maintenance','Umi Kalsum mengajukan perbaikan/perawatan fasilitas, mohon diperiksa.','belum','2026-10-14 10:34:17'),(2,6,'maintenance','Ada pengajuan maintenance yang disetujui atasan, mohon diperiksa.','belum','2026-10-14 10:34:33'),(3,17,'maintenance','Pengajuan maintenance Anda disetujui atasan, diteruskan ke Team Leader.','belum','2026-10-14 10:34:33'),(4,5,'maintenance','Pengajuan maintenance #1 telah diverifikasi TL, menunggu approval.','belum','2026-10-14 10:34:48'),(5,7,'maintenance','Pengajuan maintenance #1 disetujui, silakan dikerjakan.','belum','2026-10-14 10:35:15'),(6,17,'maintenance','Pengajuan maintenance Anda #1: disetujui.','belum','2026-10-14 10:35:15'),(7,17,'maintenance','Maintenance #1 telah selesai dikerjakan.','belum','2026-10-14 10:56:23'),(8,8,'karyawan','Anda mendapat penugasan non-rutinitas baru: Pengecekan & Pembersihan Ruangan Kelas SD...','belum','2026-10-14 19:38:11'),(9,10,'inventaris','Anisa Rahmawati mengajukan peminjaman barang, mohon di-approve.','belum','2026-10-14 19:59:34'),(10,4,'inventaris','Ada peminjaman barang (Keperluan Rapat) yang disetujui atasan, mohon diverifikasi.','belum','2026-10-14 20:00:12'),(11,11,'inventaris','Pengajuan peminjaman Anda disetujui atasan, diteruskan ke Staff Inventaris.','belum','2026-10-14 20:00:12'),(12,43,'inventaris','Rizky Fauzi mengajukan peminjaman barang, mohon di-approve.','belum','2026-10-19 03:28:09'),(13,4,'inventaris','Ada peminjaman barang (untuk rapat) yang disetujui atasan, mohon diverifikasi.','belum','2026-10-19 03:28:22'),(14,42,'inventaris','Pengajuan peminjaman Anda disetujui atasan, diteruskan ke Staff Inventaris.','belum','2026-10-19 03:28:22'),(15,42,'inventaris','Pengajuan peminjaman #3 Anda telah disetujui.','belum','2026-10-19 03:28:53'),(16,11,'inventaris','Pengajuan peminjaman #1 Anda telah disetujui.','belum','2026-10-19 03:28:56'),(17,5,'karyawan','Tugas non-rutin oleh Cahya (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-19 20:04:26'),(18,5,'karyawan','Tugas non-rutin oleh Slamet Riyadi (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-19 20:05:36'),(19,10,'karyawan','Tugas non-rutin oleh Slamet Riyadi telah diselesaikan.','belum','2026-10-19 20:05:36'),(20,8,'karyawan','SPV Sapras memberikan penilaian kinerja untuk tugas Anda (Skor: 1/5).','belum','2026-10-20 19:59:34'),(21,8,'karyawan','SPV Sapras memperbarui penilaian kinerja tugas Anda (Skor: 3/5).','belum','2026-10-20 19:59:35'),(22,8,'karyawan','SPV Sapras memperbarui penilaian kinerja tugas Anda (Skor: 5/5).','belum','2026-10-20 19:59:35'),(23,8,'karyawan','SPV Sapras memperbarui penilaian kinerja tugas Anda (Skor: 4/5).','belum','2026-10-20 19:59:35'),(24,8,'karyawan','Anda mendapat penugasan non-rutinitas baru: brushing area koridor dekat gedung wisma...','belum','2026-10-20 20:43:50'),(25,5,'karyawan','Tugas non-rutin oleh Slamet Riyadi (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-21 00:53:47'),(26,6,'karyawan','Tugas non-rutin oleh Slamet Riyadi telah diselesaikan.','belum','2026-10-21 00:53:47'),(27,5,'karyawan','Tugas non-rutin oleh Slamet Riyadi (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-21 00:54:13'),(28,6,'karyawan','Tugas non-rutin oleh Slamet Riyadi telah diselesaikan.','belum','2026-10-21 00:54:13'),(33,8,'karyawan','Anda mendapat penugasan rutin baru: Pembersihan rutin lobi utama dan kaca...','belum','2026-10-21 02:23:21'),(34,6,'karyawan','Tugas oleh Slamet Riyadi (CSO) telah selesai dikerjakan.','belum','2026-10-21 02:23:21'),(35,5,'karyawan','Tugas operasional oleh Slamet Riyadi (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-21 02:23:21'),(36,8,'karyawan','Muhammad Giyanto memberikan penilaian kinerja untuk tugas Anda (Skor: 5/5).','belum','2026-10-21 02:23:22'),(41,8,'karyawan','Anda mendapat penugasan rutin baru: Pembersihan rutin lobi utama dan kaca...','belum','2026-10-21 03:36:35'),(42,6,'karyawan','Tugas oleh Asep Setiawan (CSO) telah selesai dikerjakan.','belum','2026-10-21 03:36:35'),(43,5,'karyawan','Tugas operasional oleh Asep Setiawan (CSO) telah selesai dikerjakan, mohon diberikan penilaian kinerja.','belum','2026-10-21 03:36:35'),(44,8,'karyawan','Muhammad Giyanto memberikan penilaian kinerja untuk tugas Anda (Skor: 5/5).','belum','2026-10-21 03:36:35'),(49,6,'karyawan','Anda mendapat penugasan tugas baru: Deskripsi uji coba otomatis penugasan...','belum','2026-10-22 00:51:33'),(50,8,'karyawan','Anda mendapat penugasan tugas baru: Deskripsi uji coba otomatis penugasan...','belum','2026-10-22 00:51:34'),(51,8,'karyawan','Anda mendapat penugasan tugas baru: Deskripsi uji coba otomatis penugasan...','belum','2026-10-22 00:51:34'),(52,6,'karyawan','Anda mendapat penugasan rutin baru: Tugas SPV ke TL...','belum','2026-10-22 00:53:51'),(53,8,'karyawan','Anda mendapat penugasan rutin baru: Tugas TL ke CSO...','belum','2026-10-22 00:53:52'),(54,8,'karyawan','Anda mendapat penugasan rutin baru: Tugas Admin ke CSO...','belum','2026-10-22 00:53:52'),(55,6,'karyawan','Anda mendapat penugasan rutin baru: Tugas SPV ke TL...','belum','2026-10-22 00:54:31'),(56,8,'karyawan','Anda mendapat penugasan rutin baru: Tugas TL ke CSO...','belum','2026-10-22 00:54:31'),(57,8,'karyawan','Anda mendapat penugasan rutin baru: Tugas Admin ke CSO...','belum','2026-10-22 00:54:31'),(58,7,'karyawan','Riki indramars memberikan penilaian kinerja untuk tugas Anda (Skor: 5/5).','belum','2026-10-23 15:22:51'),(59,7,'karyawan','Riki indramars memperbarui penilaian kinerja tugas Anda (Skor: 4/5).','belum','2026-10-23 15:22:51'),(60,6,'maintenance','Ada pengajuan maintenance yang disetujui atasan, mohon diperiksa.','belum','2026-10-23 16:58:36'),(61,3,'maintenance','Inang Setiawati Siregar, S.M mengajukan perbaikan/perawatan fasilitas, mohon diperiksa.','belum','2026-10-23 17:01:35'),(62,6,'maintenance','Ada pengajuan maintenance yang disetujui atasan, mohon diperiksa.','belum','2026-10-23 17:01:35'),(63,4,'maintenance','Pengajuan maintenance Anda disetujui atasan, diteruskan ke Team Leader.','belum','2026-10-23 17:01:35'),(64,94,'inventaris','Nabila Rif\'atul Izzah, S.Ak mengajukan peminjaman barang, mohon di-approve.','belum','2026-10-28 03:00:04'),(65,4,'inventaris','Ada peminjaman barang (Testing Pengajuan Staff FA Otomatis) yang disetujui atasan, mohon diverifikasi.','belum','2026-10-28 03:01:32'),(66,42,'inventaris','Pengajuan peminjaman Anda disetujui atasan, diteruskan ke Staff Inventaris.','belum','2026-10-28 03:01:32');
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `peminjaman`
--

DROP TABLE IF EXISTS `peminjaman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `peminjam_id` int(11) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `rencana_kembali` date DEFAULT NULL,
  `keperluan` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'diajukan',
  `verifikator_id` int(11) DEFAULT NULL,
  `atasan_id` int(11) DEFAULT NULL,
  `catatan_atasan` varchar(255) DEFAULT NULL,
  `approval_id` int(11) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `barang_id` (`barang_id`),
  KEY `peminjam_id` (`peminjam_id`),
  KEY `verifikator_id` (`verifikator_id`),
  KEY `atasan_id` (`atasan_id`),
  KEY `approval_id` (`approval_id`),
  CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`),
  CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`peminjam_id`) REFERENCES `users` (`id`),
  CONSTRAINT `peminjaman_ibfk_3` FOREIGN KEY (`verifikator_id`) REFERENCES `users` (`id`),
  CONSTRAINT `peminjaman_ibfk_4` FOREIGN KEY (`atasan_id`) REFERENCES `users` (`id`),
  CONSTRAINT `peminjaman_ibfk_5` FOREIGN KEY (`approval_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `peminjaman`
--

LOCK TABLES `peminjaman` WRITE;
/*!40000 ALTER TABLE `peminjaman` DISABLE KEYS */;
INSERT INTO `peminjaman` VALUES (1,2,7,11,'2026-09-22','2026-09-23','Keperluan Rapat','disetujui',4,10,'',3,'','2026-10-14 19:59:34'),(3,1,2,42,'2026-10-19','2026-10-20','untuk rapat','disetujui',4,43,'',3,'','2026-10-19 03:28:09'),(5,1,1,42,'2026-10-27','0000-00-00','Testing Pengajuan Staff FA Otomatis','disetujui_atasan',NULL,94,'',NULL,NULL,'2026-10-28 03:00:04');
/*!40000 ALTER TABLE `peminjaman` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengajuan_maintenance`
--

DROP TABLE IF EXISTS `pengajuan_maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengajuan_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fasilitas_id` int(11) DEFAULT NULL,
  `pemohon_id` int(11) NOT NULL,
  `deskripsi_kerusakan` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'diajukan',
  `tl_verifikasi_id` int(11) DEFAULT NULL,
  `spv_approval_id` int(11) DEFAULT NULL,
  `atasan_id` int(11) DEFAULT NULL,
  `catatan_atasan` varchar(255) DEFAULT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fasilitas_id` (`fasilitas_id`),
  KEY `pemohon_id` (`pemohon_id`),
  KEY `tl_verifikasi_id` (`tl_verifikasi_id`),
  KEY `spv_approval_id` (`spv_approval_id`),
  KEY `atasan_id` (`atasan_id`),
  CONSTRAINT `pengajuan_maintenance_ibfk_1` FOREIGN KEY (`fasilitas_id`) REFERENCES `fasilitas` (`id`),
  CONSTRAINT `pengajuan_maintenance_ibfk_2` FOREIGN KEY (`pemohon_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pengajuan_maintenance_ibfk_3` FOREIGN KEY (`tl_verifikasi_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pengajuan_maintenance_ibfk_4` FOREIGN KEY (`spv_approval_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pengajuan_maintenance_ibfk_5` FOREIGN KEY (`atasan_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengajuan_maintenance`
--

LOCK TABLES `pengajuan_maintenance` WRITE;
/*!40000 ALTER TABLE `pengajuan_maintenance` DISABLE KEYS */;
INSERT INTO `pengajuan_maintenance` VALUES (1,NULL,17,'lantai kelas 1A dekat rak sepatu rusak seperti ini','uploads/maintenance/fa2c7e49c8d95ff5b022144bc3afef75.webp','selesai',6,5,18,'','2026-10-14',NULL);
/*!40000 ALTER TABLE `pengajuan_maintenance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengajuan_unit`
--

DROP TABLE IF EXISTS `pengajuan_unit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengajuan_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` int(11) DEFAULT NULL,
  `pemohon_id` int(11) NOT NULL,
  `approver_role` varchar(40) NOT NULL,
  `jenis_modul` enum('inventaris','maintenance','event') NOT NULL,
  `deskripsi` text NOT NULL,
  `status` enum('diajukan','disetujui','ditolak','diteruskan') NOT NULL DEFAULT 'diajukan',
  `approver_id` int(11) DEFAULT NULL,
  `catatan_atasan` varchar(255) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `unit_id` (`unit_id`),
  KEY `pemohon_id` (`pemohon_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `pengajuan_unit_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `struktur_unit` (`id`),
  CONSTRAINT `pengajuan_unit_ibfk_2` FOREIGN KEY (`pemohon_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pengajuan_unit_ibfk_3` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengajuan_unit`
--

LOCK TABLES `pengajuan_unit` WRITE;
/*!40000 ALTER TABLE `pengajuan_unit` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengajuan_unit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengembalian`
--

DROP TABLE IF EXISTS `pengembalian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengembalian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `peminjaman_id` int(11) NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `kondisi_barang` enum('baik','rusak_ringan','rusak_berat','hilang') NOT NULL DEFAULT 'baik',
  `status_pemeriksaan` varchar(50) DEFAULT 'selesai',
  `tindakan_spv` varchar(50) DEFAULT NULL,
  `catatan_spv` varchar(255) DEFAULT NULL,
  `petugas_id` int(11) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `peminjaman_id` (`peminjaman_id`),
  KEY `petugas_id` (`petugas_id`),
  CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengembalian`
--

LOCK TABLES `pengembalian` WRITE;
/*!40000 ALTER TABLE `pengembalian` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengembalian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penilaian_kinerja`
--

DROP TABLE IF EXISTS `penilaian_kinerja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penilaian_kinerja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `kegiatan_id` int(11) DEFAULT NULL,
  `penilai_id` int(11) NOT NULL,
  `periode` varchar(20) NOT NULL,
  `nilai` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `karyawan_id` (`karyawan_id`),
  KEY `penilai_id` (`penilai_id`),
  CONSTRAINT `penilaian_kinerja_ibfk_1` FOREIGN KEY (`karyawan_id`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_kinerja_ibfk_2` FOREIGN KEY (`penilai_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penilaian_kinerja`
--

LOCK TABLES `penilaian_kinerja` WRITE;
/*!40000 ALTER TABLE `penilaian_kinerja` DISABLE KEYS */;
INSERT INTO `penilaian_kinerja` VALUES (6,5,26,5,'October 2026',4,'Catatan diperbarui setelah evaluasi berkala','2026-10-23 15:22:51');
/*!40000 ALTER TABLE `penilaian_kinerja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proses_maintenance`
--

DROP TABLE IF EXISTS `proses_maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proses_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int(11) NOT NULL,
  `teknisi_id` int(11) DEFAULT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `hasil_pekerjaan` text DEFAULT NULL,
  `foto_pekerjaan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengajuan_id` (`pengajuan_id`),
  KEY `teknisi_id` (`teknisi_id`),
  CONSTRAINT `proses_maintenance_ibfk_1` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_maintenance` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proses_maintenance_ibfk_2` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proses_maintenance`
--

LOCK TABLES `proses_maintenance` WRITE;
/*!40000 ALTER TABLE `proses_maintenance` DISABLE KEYS */;
INSERT INTO `proses_maintenance` VALUES (1,1,7,'2026-10-14','2026-10-14','sudah selesai di perbaiki','uploads/maintenance/6e8ed7130315171bc466bc9ed9e94e4c.webp');
/*!40000 ALTER TABLE `proses_maintenance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reminder_kegiatan`
--

DROP TABLE IF EXISTS `reminder_kegiatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reminder_kegiatan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kegiatan_id` int(11) NOT NULL,
  `jenis` enum('h-1','hari_h','terlambat') NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reminder` (`kegiatan_id`,`jenis`,`tanggal`),
  CONSTRAINT `reminder_kegiatan_ibfk_1` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan_karyawan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reminder_kegiatan`
--

LOCK TABLES `reminder_kegiatan` WRITE;
/*!40000 ALTER TABLE `reminder_kegiatan` DISABLE KEYS */;
/*!40000 ALTER TABLE `reminder_kegiatan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `riwayat_inventaris`
--

DROP TABLE IF EXISTS `riwayat_inventaris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `riwayat_inventaris` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barang_id` int(11) NOT NULL,
  `jenis_perubahan` varchar(50) NOT NULL,
  `jumlah_sebelum` int(11) NOT NULL,
  `jumlah_sesudah` int(11) NOT NULL,
  `kondisi_sebelum` varchar(50) DEFAULT NULL,
  `kondisi_sesudah` varchar(50) DEFAULT NULL,
  `alasan` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `role_user` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `barang_id` (`barang_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `riwayat_inventaris_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `riwayat_inventaris_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `riwayat_inventaris`
--

LOCK TABLES `riwayat_inventaris` WRITE;
/*!40000 ALTER TABLE `riwayat_inventaris` DISABLE KEYS */;
/*!40000 ALTER TABLE `riwayat_inventaris` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode` varchar(40) NOT NULL,
  `nama_role` varchar(80) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Administrator Sistem','Akses penuh untuk keperluan administrasi & konfigurasi sistem'),(2,'manajer_ga','Manajer GA','Monitoring inventaris/peminjaman, maintenance, kegiatan & penilaian karyawan sesuai kewenangan'),(3,'spv_inventaris','SPV Inventaris','Memeriksa hasil verifikasi & memberi approval/penolakan peminjaman, memantau transaksi'),(4,'staff_inventaris','Staff Inventaris','Mengelola data inventaris, memverifikasi pengajuan, konfirmasi pengembalian'),(5,'spv_sapras','SPV Sapras','Approve/menolak pengajuan maintenance & monitoring proses perbaikan'),(6,'team_leader','Team Leader (TL)','Memeriksa pengajuan maintenance & mengelola kegiatan karyawan (CSO/Gardener)'),(7,'engineering','Engineering','Menjalankan pekerjaan maintenance yang telah disetujui'),(8,'cso','CSO','Menerima & menjalankan kegiatan, memberikan laporan hasil kegiatan'),(9,'gardener','Gardener','Menerima & menjalankan kegiatan, memberikan laporan hasil kegiatan'),(10,'manajer_hrd','Manajer HRD','Administrasi, pengumpulan, rekapitulasi & pengarsipan data karyawan; approve pengajuan lintas modul sebelum diteruskan'),(11,'staff_hrd','Staff HRD','Administrasi data karyawan; membuat pengajuan kebutuhan (inventaris/maintenance/event) untuk diteruskan ke Manajer HRD'),(12,'manajer_markom','Manajer MARKOM','Monitoring event, jadwal & approval sesuai kewenangan'),(13,'spv_crm','SPV CRM','Memeriksa & memverifikasi pengajuan event serta memantau pelaksanaan'),(14,'staff_crm','Staff CRM','Menerima, memeriksa, memverifikasi & mengelola jadwal event'),(15,'manajer_qms','Manajer QMS','Mengelola & memonitor arsip digital, laporan, & backup sesuai kewenangan'),(16,'staff_qms','Staff QMS','Mengelola & memonitor arsip digital sesuai tingkat akses'),(17,'tu','TU','Membuat pengajuan/request pada modul yang dibutuhkan unit pendidikan'),(18,'kepala_sekolah','Kepala Sekolah','Memvalidasi & menyetujui pengajuan dari unit pendidikan'),(19,'staff_fa','Staff FA','Membuat pengajuan kebutuhan (inventaris/maintenance) untuk diverifikasi Manajer FA serta menyimpan dokumen di Arsip Digital'),(21,'manajer_fa','Manajer FA','Memvalidasi & menyetujui pengajuan kebutuhan dari Staff FA sebelum diteruskan ke pengelola modul terkait serta monitoring laporan'),(61,'spv_fa','Supervisor FA','Memvalidasi & menyetujui pengajuan kebutuhan operasional dari Staff FA serta monitoring penugasan staf');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `struktur_unit`
--

DROP TABLE IF EXISTS `struktur_unit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `struktur_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_unit` varchar(60) NOT NULL,
  `tipe` enum('divisi','unit_pendidikan') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `struktur_unit`
--

LOCK TABLES `struktur_unit` WRITE;
/*!40000 ALTER TABLE `struktur_unit` DISABLE KEYS */;
INSERT INTO `struktur_unit` VALUES (1,'General Affair (GA)','divisi'),(2,'HRD','divisi'),(3,'MARKOM / CRM','divisi'),(4,'QMS','divisi'),(5,'TK Al-Syukro','unit_pendidikan'),(6,'SD Al-Syukro','unit_pendidikan'),(7,'SMP Al-Syukro','unit_pendidikan'),(8,'SMA Al-Syukro','unit_pendidikan'),(9,'Finance & Accounting','divisi');
/*!40000 ALTER TABLE `struktur_unit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaksi_barang`
--

DROP TABLE IF EXISTS `transaksi_barang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_barang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barang_id` int(11) NOT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barang_id` (`barang_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `transaksi_barang_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaksi_barang_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaksi_barang`
--

LOCK TABLES `transaksi_barang` WRITE;
/*!40000 ALTER TABLE `transaksi_barang` DISABLE KEYS */;
INSERT INTO `transaksi_barang` VALUES (1,2,'keluar',7,'2026-10-19','Peminjaman disetujui (Sinkronisasi)',3),(2,1,'keluar',2,'2026-10-19','Peminjaman disetujui (Sinkronisasi)',3);
/*!40000 ALTER TABLE `transaksi_barang` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `struktur_unit` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Dona Nuryahya, S.Kom',1,NULL,'aktif',0,NULL,'2026-10-14 07:40:46'),(2,'manajerga','manajerga@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Ahmad Ubaydilah, S.E',2,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(3,'spvinventaris','spvinv@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Dona Nuryahya, S.Kom',3,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(4,'staffinventaris','staffinv@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Inang Setiawati Siregar, S.M',4,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(5,'spvsapras','spvsapras@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Riki indramars',5,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(6,'teamleader','tl@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Muhammad Giyanto',6,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(7,'engineering1','engineering1@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Ahmad San Rohmadi',7,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(8,'cso1','cso1@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Asep Setiawan',8,1,'aktif',0,NULL,'2026-10-14 07:40:46'),(10,'manajerhrd','manajerhrd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Ratna Sari Dewi, S.H',10,2,'aktif',0,NULL,'2026-10-14 07:40:46'),(11,'staffhrd','staffhrd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Munadya Hasna Dewi, S.Psi',11,2,'aktif',0,NULL,'2026-10-14 07:40:46'),(12,'manajermarkom','markom@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Henrizal Saidi Harahap, M.Ag',12,3,'aktif',0,NULL,'2026-10-14 07:40:46'),(13,'spvcrm','spvcrm@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Ahmad Qosim, S.Pd.I',13,3,'aktif',0,NULL,'2026-10-14 07:40:46'),(14,'staffcrm','staffcrm@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Apri Supriani, S.M',14,3,'aktif',0,NULL,'2026-10-14 07:40:46'),(15,'manajerqms','qms@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Budi Santoso, SP',15,4,'aktif',0,NULL,'2026-10-14 07:40:46'),(16,'staffqms','staffqms@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Husna Assabiila Yassarah, S.Kom',16,4,'aktif',0,NULL,'2026-10-14 07:40:46'),(17,'tu_sd','tu.sd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Ansor Nurjaya, S.Pd',17,6,'aktif',0,NULL,'2026-10-14 07:40:46'),(18,'kepsek_sd','kepsek.sd@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Tarni, S.Pd',18,6,'aktif',0,NULL,'2026-10-14 07:40:46'),(19,'cso2','cso2@alsyukro.sch.id','$2y$12$4U0HNuYQqVTSVo0CGmpVl.bn8o54uYnh/vKLORR3czb3TGA4W/QsK','Hasbullah',8,1,'aktif',0,NULL,'2026-10-15 03:16:45'),(20,'cso3','cso3@alsyukro.sch.id','$2y$12$GLZMnUGGdW6Q0SzeaKSd2.zdUuxdaPa0xYMl51nEz77ndzJcrcdH.','Muhamad Aris Saputra',8,1,'aktif',0,NULL,'2026-10-15 03:18:06'),(21,'cso4','cso4@alsyukro.sch.id','$2y$12$K7GWjAg.LSZJuVWX9J1YwO/Zl6U20uSOnE6jnyo7MbtJ927oKjiLa','Waliman Antoni',8,1,'aktif',0,NULL,'2026-10-15 03:25:19'),(22,'cso5','cso5@alsyukro.sch.id','$2y$12$O2/6CbutgQKchJ6WZ./9puyZXpt9n0ImfGYtPQCyNodXPhS0bM8uu','Hilman Sunan Munawar',8,1,'aktif',0,NULL,'2026-10-15 03:28:02'),(23,'cso6','cso6@alsyukro.sch.id','$2y$12$DmPmL5kzs71MfSUqFUsaHeK9y9KOSxe9hUikItfzuA3osXgBDLsZS','Zian Pauzian Syah',8,1,'aktif',0,NULL,'2026-10-15 03:28:45'),(24,'cso7','cso7@alsyukro.sch.id','$2y$12$4k9RMyX6Mxr7G36ekULsb.OP.Zu7QKlzKsAQAHQdHEHgV/iHBXa0y','Muhammad Cahyagam',8,1,'aktif',0,NULL,'2026-10-15 03:29:29'),(25,'cso8','cso8@alsyukro.sch.id','$2y$12$GwckPBL44uHatYG3EIEeMetPYv7jCvvVO4VxWMNZi/febqg89nJ5e','Aldi Refaldy',8,1,'aktif',0,NULL,'2026-10-15 03:30:21'),(26,'cso9','cso9@alsyukro.sch.id','$2y$12$JZCPjD0mCMXryOt4jgSQeuKWH8lYBzoedb3xImFvffVukzQNM/j/u','Mustadi',8,1,'aktif',0,NULL,'2026-10-15 03:31:31'),(27,'cso10','cso10@alsyukro.sch.id','$2y$12$zH5BJX7jcR24pNzsKU0UFuAsYk5HcaNHnuNBvRhdDkX8qXrRBT7sC','Hendra Evin',8,1,'aktif',0,NULL,'2026-10-15 03:32:05'),(28,'cso11','cso11@alsyukro.sch.id','$2y$12$U5CXS/.AjhI34pM.spq.aej2DOxV.qw3RmpqWDIJTdvrUlj.PaV4C','Agus Rohendi',8,1,'aktif',0,NULL,'2026-10-15 03:32:55'),(29,'cso12','cso12@alsyukro.sch.id','$2y$12$Wk2SNt5WADU6qIxFvLAib.6llKcI5kp/cB9vqB8dunSrrJdYaJ5eu','Dandi',8,1,'aktif',0,NULL,'2026-10-15 03:33:37'),(30,'gardener1','gardener1@alsyukro.sch.id','$2y$12$ly6JrfYozRqLzbYkspqMAuMf/FC0.piIpOzGYdLuF6EKoDD5e/nOW','Sakirun',9,1,'aktif',0,NULL,'2026-10-15 03:34:59'),(31,'gardener2','gardener2@alsyukro.sch.id','$2y$12$8rOOPwnzqAIIAX3lCOrBUO0uPjzbaHJywe6IrkXZQRwK4FpQfwecS','Makbul',9,1,'aktif',0,NULL,'2026-10-15 03:35:40'),(32,'gardener3','gardener3@alsyukro.sch.id','$2y$12$ZqxJNrW5g/QMto4a1Z0qc.XGU7rfkej/e1DwYLSCZD3BkZkmr2yxS','Hasim HM',9,1,'aktif',0,NULL,'2026-10-15 03:38:03'),(33,'gardener4','gardener4@alsyukro.sch.id','$2y$12$solE2/JyAt4.VKoCfsRrCuN/58JKUTLXt.uplCH7MHIeSKdv2db7i','Sodikin',9,1,'aktif',0,NULL,'2026-10-15 03:39:01'),(34,'kepsek_smp','umairah@gmail.com','$2y$12$eU00.0sLee91nSqLKoDDCeVLgMxv4KckbyGBGM890AYUaspHYdECm','Umairah, SP.d',18,7,'aktif',0,NULL,'2026-10-15 03:53:14'),(35,'kepsek_sma','rizal@@gmail.com','$2y$12$Bjs.mPHaoTjBo6uJvzutIe1RSZNOj8IKmst86aib3CJ1fp6GBgxJa','Rizal, SP.d',18,8,'aktif',0,NULL,'2026-10-15 03:54:26'),(36,'kepsek_tk','wati@gmail.com','$2y$12$iHujpUqBUHHMANEXAGpvFOQ2nEOHrJ5vcH0tkuaDQ1F7Q4FCw2pEy','Wati, SP.d',18,5,'aktif',0,NULL,'2026-10-15 03:56:07'),(37,'tu_tk','tu.tk@alsyukro.sch.id','$2y$12$DGFsG.em2lt.BLH3N1Sw1u5VOz8Oa9zbhGSFa6MyJi20u3udkC3GG','Desti Tri Indahsari, S.E',17,5,'aktif',0,NULL,'2026-10-15 03:58:09'),(38,'tu_smp','tu.smp@alsyukro.sch.id','$2y$12$.semaE/HpedW2PerLx8vqOtQe1tUvjAkxfRczh6EsnI9Tp1YlVkTi','Muhammad Rizqi Fuazin',17,7,'aktif',0,NULL,'2026-10-15 03:59:03'),(39,'tu_sma','tu.sma@alsyukro.sch.id','$2y$12$P2NQmEtOrZtH9HVBNzq2r.5Z527qAXDRY3jUfrTcR1TCLVwm6yqJC','Muhammad Adib Riza, S.Pd.I',17,8,'aktif',0,NULL,'2026-10-15 04:02:08'),(41,'engineering2','engineering@alsyukro.sch.id','$2y$12$EQwyGK2OGXwsb8f2s/34heo0iDzwy6ezE.giElgC3KKtDxyLxPmWm','Arif Mulyana',7,1,'aktif',0,NULL,'2026-10-15 23:50:44'),(42,'stafffa','fa@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Nabila Rif\'atul Izzah, S.Ak',19,9,'aktif',0,NULL,'2026-10-19 02:39:21'),(43,'manajerfa','manajerfa@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Muhamad Fahmi, S.Akun',21,9,'aktif',0,NULL,'2026-10-19 03:11:22'),(46,'gardener5','gardener5@alsyukro.sch.id','$2y$10$8.EKOl/kyHzqUn2oaEn2xOdIkM6ATcLqfzjNVqW6tMKNJzYV8XZd6','Iim Ibrahim',9,1,'aktif',0,NULL,'2026-10-20 03:50:44'),(94,'spvfa','spvfa@alsyukro.sch.id','$2b$10$Ck4iJVxuXnotibcgZloeAuWPFi2fi0X/whvH97b.vIgU6Zsam4t66','Rista Yuniarti, S.Ak',61,9,'aktif',0,NULL,'2026-10-28 02:30:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-01 20:49:26
