-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2026 at 02:57 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jtdis_asset`
--

-- --------------------------------------------------------

--
-- Table structure for table `agensi`
--

CREATE TABLE `agensi` (
  `agensi_id` int(11) NOT NULL,
  `nama_agensi` varchar(200) NOT NULL,
  `daerah_id` int(11) DEFAULT NULL,
  `wilayah_id` int(11) DEFAULT NULL,
  `jenis_agensi` enum('kementerian','jabatan','pejabat_daerah','lain') DEFAULT 'lain',
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agensi`
--

INSERT INTO `agensi` (`agensi_id`, `nama_agensi`, `daerah_id`, `wilayah_id`, `jenis_agensi`, `catatan`) VALUES
(1, 'Pejabat Daerah Kota Belud', 1, 2, 'pejabat_daerah', NULL),
(2, 'Jabatan Pertanian Kota Belud', 1, 2, 'jabatan', NULL),
(3, 'Jabatan Perikanan Kota Belud', 1, 2, 'jabatan', NULL),
(4, 'Jabatan Pengairan Dan Saliran Kota Belud', 1, 2, 'jabatan', NULL),
(5, 'Jabatan Perkhidmatan Veterinar Kota Belud', 1, 2, 'jabatan', NULL),
(6, 'Kementerian Pertanian, Perikanan & Industri Makanan', NULL, 1, 'kementerian', NULL),
(7, 'Kementerian Pembangunan Perindustrian & Keusahawanan', NULL, 1, 'kementerian', NULL),
(8, 'Jabatan Kerja Raya Sabah', NULL, 1, 'jabatan', NULL),
(9, 'Jabatan Bendahari Negeri', NULL, 1, 'jabatan', NULL),
(10, 'Jabatan Air Sabah', NULL, 1, 'jabatan', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aset`
--

CREATE TABLE `aset` (
  `aset_id` int(11) NOT NULL,
  `no_pendaftaran` varchar(100) NOT NULL,
  `jenis_aset` enum('NB','PC','Pencetak','Monitor','Lain') NOT NULL,
  `jenis_perolehan` enum('Kerajaan Negeri','Kerajaan Persekutuan','Sewa Guna','Guna Sama','Lain') NOT NULL,
  `tahun_beli` year(4) DEFAULT NULL,
  `jenama` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `processor` varchar(150) DEFAULT NULL,
  `ram` varchar(50) DEFAULT NULL,
  `cakera_keras` varchar(100) DEFAULT NULL,
  `sistem_operasi` varchar(50) DEFAULT NULL,
  `spesifikasi_pencetak` varchar(255) DEFAULT NULL,
  `jenis_pencetak` enum('Laser','Inkjet','Matrik','Tiada') DEFAULT 'Tiada',
  `bil_pencetak_laser` int(11) DEFAULT 0,
  `bil_pencetak_inkjet` int(11) DEFAULT 0,
  `bil_pencetak_matrik` int(11) DEFAULT 0,
  `no_siri_pencetak` varchar(100) DEFAULT NULL,
  `pegawai_nama` varchar(100) DEFAULT NULL,
  `pegawai_jawatan` varchar(100) DEFAULT NULL,
  `pegawai_gred` varchar(50) DEFAULT NULL,
  `pengguna_semasa_id` int(11) DEFAULT NULL,
  `agensi_id` int(11) NOT NULL,
  `wilayah_id` int(11) DEFAULT NULL,
  `status_workflow_id` tinyint(4) NOT NULL DEFAULT 1,
  `status_aset_id` tinyint(4) NOT NULL DEFAULT 1,
  `pengguna_id_daftar` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `maklumat_pelupusan_aset` text DEFAULT NULL,
  `tarikh_input` datetime NOT NULL DEFAULT current_timestamp(),
  `tarikh_kemaskini` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daerah`
--

CREATE TABLE `daerah` (
  `daerah_id` int(11) NOT NULL,
  `nama_daerah` varchar(100) NOT NULL,
  `wilayah_id` int(11) NOT NULL,
  `kod_daerah` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daerah`
--

INSERT INTO `daerah` (`daerah_id`, `nama_daerah`, `wilayah_id`, `kod_daerah`) VALUES
(1, 'Kota Belud', 2, 'KBL'),
(2, 'Ranau', 2, 'RAN'),
(3, 'Kudat', 2, 'KUD'),
(4, 'Matunggong', 2, 'MAT'),
(5, 'Paitan', 2, 'PAI'),
(6, 'Kota Marudu', 2, 'KMA'),
(7, 'Pitas', 2, 'PIT'),
(8, 'Tuaran', 2, 'TUR'),
(9, 'Kota Kinabalu', 2, 'KKB');

-- --------------------------------------------------------

--
-- Table structure for table `log_audit`
--

CREATE TABLE `log_audit` (
  `log_id` int(11) NOT NULL,
  `pengguna_id` int(11) DEFAULT NULL,
  `nama_pengguna` varchar(150) DEFAULT NULL,
  `jadual` varchar(50) NOT NULL,
  `tindakan` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','AKSES_DITOLAK') NOT NULL,
  `rekod_id` int(11) DEFAULT NULL,
  `data_lama` text DEFAULT NULL,
  `data_baru` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `tarikh` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_pemindahan`
--

CREATE TABLE `log_pemindahan` (
  `log_id` int(11) NOT NULL,
  `aset_id` int(11) NOT NULL,
  `pengguna_lama_id` int(11) DEFAULT NULL,
  `pengguna_baru_id` int(11) DEFAULT NULL,
  `nama_pengguna_lama` varchar(150) DEFAULT NULL,
  `nama_pengguna_baru` varchar(150) DEFAULT NULL,
  `sebab` text DEFAULT NULL,
  `direkod_oleh` int(11) NOT NULL,
  `tarikh` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_selenggara`
--

CREATE TABLE `log_selenggara` (
  `log_id` int(11) NOT NULL,
  `aset_id` int(11) NOT NULL,
  `jenis_selenggara` varchar(100) DEFAULT NULL,
  `komponen_ditukar` text DEFAULT NULL,
  `kos` decimal(10,2) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status_selepas` varchar(50) DEFAULT NULL,
  `dibuat_oleh` int(11) NOT NULL,
  `tarikh` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_workflow`
--

CREATE TABLE `log_workflow` (
  `log_id` int(11) NOT NULL,
  `aset_id` int(11) NOT NULL,
  `tindakan` enum('Daftar','Semak','Lulus PPTM','Tolak PPTM','Lulus Wilayah','Tolak Wilayah','Lulus Bahagian','Tolak Bahagian') NOT NULL,
  `status_workflow_dari` tinyint(4) DEFAULT NULL,
  `status_workflow_ke` tinyint(4) NOT NULL,
  `catatan` text DEFAULT NULL,
  `oleh_pengguna_id` int(11) NOT NULL,
  `tarikh` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `notifikasi_id` int(11) NOT NULL,
  `penerima_id` int(11) NOT NULL,
  `aset_id` int(11) DEFAULT NULL,
  `jenis` enum('aset_baru','diluluskan','ditolak','peringatan','sistem') NOT NULL,
  `mesej` text NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `dibaca` tinyint(1) NOT NULL DEFAULT 0,
  `tarikh` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelupusan`
--

CREATE TABLE `pelupusan` (
  `pelupusan_id` int(11) NOT NULL,
  `aset_id` int(11) NOT NULL,
  `sebab` text NOT NULL,
  `dokumen_rujukan` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `disahkan_oleh` int(11) NOT NULL,
  `tarikh_lupus` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `pengguna_id` int(11) NOT NULL,
  `nama_penuh` varchar(150) NOT NULL,
  `emel` varchar(150) NOT NULL,
  `kata_laluan_hash` varchar(255) NOT NULL,
  `peranan_id` int(11) NOT NULL,
  `wilayah_id` int(11) DEFAULT NULL,
  `daerah_id` int(11) DEFAULT NULL,
  `agensi_id` int(11) DEFAULT NULL,
  `status_pengguna_id` tinyint(4) NOT NULL DEFAULT 1,
  `tarikh_daftar` datetime NOT NULL DEFAULT current_timestamp(),
  `tarikh_kemaskini` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`pengguna_id`, `nama_penuh`, `emel`, `kata_laluan_hash`, `peranan_id`, `wilayah_id`, `daerah_id`, `agensi_id`, `status_pengguna_id`, `tarikh_daftar`, `tarikh_kemaskini`) VALUES
(1, 'Pentadbir Sistem JTDIS', 'admin@jtdis.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NULL, NULL, 1, '2026-07-01 08:55:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `peranan`
--

CREATE TABLE `peranan` (
  `peranan_id` int(11) NOT NULL,
  `nama_peranan` varchar(50) NOT NULL,
  `tahap_hierarki` tinyint(4) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peranan`
--

INSERT INTO `peranan` (`peranan_id`, `nama_peranan`, `tahap_hierarki`, `keterangan`) VALUES
(1, 'Super Admin', 1, 'Pengurusan sistem, pengguna, dan data induk seluruh sistem'),
(2, 'Admin Wilayah', 2, 'Pengurusan pengguna dalam wilayah sendiri sahaja'),
(3, 'Juruteknik', 6, 'Pendaftaran dan pengurusan aset dalam wilayah'),
(4, 'PPTM', 4, 'Pengesahan data aset peringkat wilayah'),
(5, 'PTM', 4, 'Pengesahan data aset peringkat wilayah'),
(6, 'Ketua Wilayah', 5, 'Kelulusan aset dan laporan peringkat wilayah'),
(7, 'Ketua Bahagian', 5, 'Penerimaan laporan semua wilayah dan PID'),
(8, 'Pengarah', 3, 'Laporan statistik dan eksport data seluruh Sabah'),
(9, 'Agen IT', 6, 'Pendaftaran aset di agensi yang dilantik'),
(10, 'PID', 6, 'Pendaftaran aset kementerian dan jabatan ibu pejabat');

-- --------------------------------------------------------

--
-- Table structure for table `status_aset`
--

CREATE TABLE `status_aset` (
  `status_aset_id` tinyint(4) NOT NULL,
  `status` varchar(30) NOT NULL,
  `warna` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status_aset`
--

INSERT INTO `status_aset` (`status_aset_id`, `status`, `warna`) VALUES
(1, 'Aktif', 'success'),
(2, 'Rosak', 'danger'),
(3, 'Selenggara', 'warning'),
(4, 'Hilang', 'dark'),
(5, 'Dilupuskan', 'secondary');

-- --------------------------------------------------------

--
-- Table structure for table `status_pengguna`
--

CREATE TABLE `status_pengguna` (
  `status_pengguna_id` tinyint(4) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status_pengguna`
--

INSERT INTO `status_pengguna` (`status_pengguna_id`, `status`) VALUES
(1, 'Aktif'),
(2, 'Tidak Aktif'),
(3, 'Suspend');

-- --------------------------------------------------------

--
-- Table structure for table `status_workflow`
--

CREATE TABLE `status_workflow` (
  `status_workflow_id` tinyint(4) NOT NULL,
  `status` varchar(50) NOT NULL,
  `warna` varchar(20) NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `status_workflow`
--

INSERT INTO `status_workflow` (`status_workflow_id`, `status`, `warna`, `keterangan`) VALUES
(1, 'Draf', 'secondary', 'Baru didaftar, belum dihantar'),
(2, 'Menunggu PPTM', 'warning', 'Menunggu pengesahan PPTM/PTM'),
(3, 'Ditolak PPTM', 'danger', 'Ditolak oleh PPTM, perlu diperbetul'),
(4, 'Menunggu KW', 'info', 'Lulus PPTM, menunggu Ketua Wilayah'),
(5, 'Ditolak KW', 'danger', 'Ditolak Ketua Wilayah, kembali ke PPTM'),
(6, 'Menunggu KB', 'primary', 'Lulus KW, menunggu Ketua Bahagian'),
(7, 'Ditolak KB', 'danger', 'Ditolak Ketua Bahagian'),
(8, 'Lulus', 'success', 'Diluluskan sepenuhnya — data final');

-- --------------------------------------------------------

--
-- Table structure for table `wilayah`
--

CREATE TABLE `wilayah` (
  `wilayah_id` int(11) NOT NULL,
  `nama_wilayah` varchar(100) NOT NULL,
  `jenis` enum('ibu_pejabat','wilayah') NOT NULL,
  `kod_wilayah` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wilayah`
--

INSERT INTO `wilayah` (`wilayah_id`, `nama_wilayah`, `jenis`, `kod_wilayah`) VALUES
(1, 'Ibu Pejabat JTDIS', 'ibu_pejabat', 'IPJ'),
(2, 'Wilayah Pantai Barat Utara', 'wilayah', 'WPBU'),
(3, 'Wilayah Sandakan', 'wilayah', 'WSK'),
(4, 'Wilayah Tawau', 'wilayah', 'WTW'),
(5, 'Wilayah Pedalaman Bawah', 'wilayah', 'WPB'),
(6, 'Wilayah Pedalaman Atas', 'wilayah', 'WPA'),
(7, 'Wilayah Bandaraya', 'wilayah', 'WBD');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agensi`
--
ALTER TABLE `agensi`
  ADD PRIMARY KEY (`agensi_id`),
  ADD KEY `daerah_id` (`daerah_id`),
  ADD KEY `wilayah_id` (`wilayah_id`);

--
-- Indexes for table `aset`
--
ALTER TABLE `aset`
  ADD PRIMARY KEY (`aset_id`),
  ADD UNIQUE KEY `no_pendaftaran` (`no_pendaftaran`),
  ADD KEY `agensi_id` (`agensi_id`),
  ADD KEY `wilayah_id` (`wilayah_id`),
  ADD KEY `status_workflow_id` (`status_workflow_id`),
  ADD KEY `status_aset_id` (`status_aset_id`),
  ADD KEY `pengguna_id_daftar` (`pengguna_id_daftar`);

--
-- Indexes for table `daerah`
--
ALTER TABLE `daerah`
  ADD PRIMARY KEY (`daerah_id`),
  ADD KEY `wilayah_id` (`wilayah_id`);

--
-- Indexes for table `log_audit`
--
ALTER TABLE `log_audit`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `log_pemindahan`
--
ALTER TABLE `log_pemindahan`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `aset_id` (`aset_id`);

--
-- Indexes for table `log_selenggara`
--
ALTER TABLE `log_selenggara`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `aset_id` (`aset_id`),
  ADD KEY `fk_logsl_pengguna` (`dibuat_oleh`);

--
-- Indexes for table `log_workflow`
--
ALTER TABLE `log_workflow`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `aset_id` (`aset_id`),
  ADD KEY `oleh_pengguna_id` (`oleh_pengguna_id`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`notifikasi_id`),
  ADD KEY `penerima_id` (`penerima_id`),
  ADD KEY `aset_id` (`aset_id`);

--
-- Indexes for table `pelupusan`
--
ALTER TABLE `pelupusan`
  ADD PRIMARY KEY (`pelupusan_id`),
  ADD UNIQUE KEY `aset_id` (`aset_id`),
  ADD KEY `fk_pelupusan_pengguna` (`disahkan_oleh`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`pengguna_id`),
  ADD UNIQUE KEY `emel` (`emel`),
  ADD KEY `peranan_id` (`peranan_id`),
  ADD KEY `wilayah_id` (`wilayah_id`),
  ADD KEY `status_pengguna_id` (`status_pengguna_id`),
  ADD KEY `fk_pengguna_agensi` (`agensi_id`);

--
-- Indexes for table `peranan`
--
ALTER TABLE `peranan`
  ADD PRIMARY KEY (`peranan_id`),
  ADD UNIQUE KEY `nama_peranan` (`nama_peranan`);

--
-- Indexes for table `status_aset`
--
ALTER TABLE `status_aset`
  ADD PRIMARY KEY (`status_aset_id`);

--
-- Indexes for table `status_pengguna`
--
ALTER TABLE `status_pengguna`
  ADD PRIMARY KEY (`status_pengguna_id`);

--
-- Indexes for table `status_workflow`
--
ALTER TABLE `status_workflow`
  ADD PRIMARY KEY (`status_workflow_id`);

--
-- Indexes for table `wilayah`
--
ALTER TABLE `wilayah`
  ADD PRIMARY KEY (`wilayah_id`),
  ADD UNIQUE KEY `nama_wilayah` (`nama_wilayah`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agensi`
--
ALTER TABLE `agensi`
  MODIFY `agensi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `aset`
--
ALTER TABLE `aset`
  MODIFY `aset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daerah`
--
ALTER TABLE `daerah`
  MODIFY `daerah_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `log_audit`
--
ALTER TABLE `log_audit`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_pemindahan`
--
ALTER TABLE `log_pemindahan`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_selenggara`
--
ALTER TABLE `log_selenggara`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_workflow`
--
ALTER TABLE `log_workflow`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `notifikasi_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pelupusan`
--
ALTER TABLE `pelupusan`
  MODIFY `pelupusan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `pengguna_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `peranan`
--
ALTER TABLE `peranan`
  MODIFY `peranan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `wilayah`
--
ALTER TABLE `wilayah`
  MODIFY `wilayah_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agensi`
--
ALTER TABLE `agensi`
  ADD CONSTRAINT `fk_agensi_daerah` FOREIGN KEY (`daerah_id`) REFERENCES `daerah` (`daerah_id`),
  ADD CONSTRAINT `fk_agensi_wilayah` FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah` (`wilayah_id`);

--
-- Constraints for table `aset`
--
ALTER TABLE `aset`
  ADD CONSTRAINT `fk_aset_agensi` FOREIGN KEY (`agensi_id`) REFERENCES `agensi` (`agensi_id`),
  ADD CONSTRAINT `fk_aset_daftar` FOREIGN KEY (`pengguna_id_daftar`) REFERENCES `pengguna` (`pengguna_id`),
  ADD CONSTRAINT `fk_aset_status` FOREIGN KEY (`status_aset_id`) REFERENCES `status_aset` (`status_aset_id`),
  ADD CONSTRAINT `fk_aset_wilayah` FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah` (`wilayah_id`),
  ADD CONSTRAINT `fk_aset_workflow` FOREIGN KEY (`status_workflow_id`) REFERENCES `status_workflow` (`status_workflow_id`);

--
-- Constraints for table `daerah`
--
ALTER TABLE `daerah`
  ADD CONSTRAINT `fk_daerah_wilayah` FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah` (`wilayah_id`);

--
-- Constraints for table `log_pemindahan`
--
ALTER TABLE `log_pemindahan`
  ADD CONSTRAINT `fk_logpm_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset` (`aset_id`) ON DELETE CASCADE;

--
-- Constraints for table `log_selenggara`
--
ALTER TABLE `log_selenggara`
  ADD CONSTRAINT `fk_logsl_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset` (`aset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logsl_pengguna` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`pengguna_id`);

--
-- Constraints for table `log_workflow`
--
ALTER TABLE `log_workflow`
  ADD CONSTRAINT `fk_logwf_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset` (`aset_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logwf_pengguna` FOREIGN KEY (`oleh_pengguna_id`) REFERENCES `pengguna` (`pengguna_id`);

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notif_penerima` FOREIGN KEY (`penerima_id`) REFERENCES `pengguna` (`pengguna_id`) ON DELETE CASCADE;

--
-- Constraints for table `pelupusan`
--
ALTER TABLE `pelupusan`
  ADD CONSTRAINT `fk_pelupusan_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset` (`aset_id`),
  ADD CONSTRAINT `fk_pelupusan_pengguna` FOREIGN KEY (`disahkan_oleh`) REFERENCES `pengguna` (`pengguna_id`);

--
-- Constraints for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD CONSTRAINT `fk_pengguna_agensi` FOREIGN KEY (`agensi_id`) REFERENCES `agensi` (`agensi_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pengguna_peranan` FOREIGN KEY (`peranan_id`) REFERENCES `peranan` (`peranan_id`),
  ADD CONSTRAINT `fk_pengguna_status` FOREIGN KEY (`status_pengguna_id`) REFERENCES `status_pengguna` (`status_pengguna_id`),
  ADD CONSTRAINT `fk_pengguna_wilayah` FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah` (`wilayah_id`);
COMMIT;


-- ============================================================
-- JTDIS - MODUL IMPORT ASET PUKAL
-- Jalankan sekali melalui phpMyAdmin > database jtdis_asset > SQL.
-- ============================================================

CREATE TABLE IF NOT EXISTS import_batch (
    import_batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_fail VARCHAR(255) NOT NULL,
    format_fail VARCHAR(10) NOT NULL,
    mod_import VARCHAR(30) NOT NULL,
    pengguna_id INT NOT NULL,
    peranan VARCHAR(50) NOT NULL,
    wilayah_id INT NULL,
    agensi_id INT NULL,
    jumlah_baris INT NOT NULL DEFAULT 0,
    jumlah_sah INT NOT NULL DEFAULT 0,
    jumlah_berjaya INT NOT NULL DEFAULT 0,
    jumlah_gagal INT NOT NULL DEFAULT 0,
    status_batch VARCHAR(30) NOT NULL DEFAULT 'dipreview',
    tarikh_mula DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tarikh_selesai DATETIME NULL,
    INDEX idx_import_batch_pengguna (pengguna_id),
    INDEX idx_import_batch_status (status_batch),
    INDEX idx_import_batch_tarikh (tarikh_mula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batch_item (
    import_item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_batch_id INT UNSIGNED NOT NULL,
    nombor_baris INT NOT NULL,
    no_pendaftaran VARCHAR(150) NULL,
    nama_agensi VARCHAR(255) NULL,
    aset_id INT NULL,
    status_item VARCHAR(30) NOT NULL,
    mesej TEXT NULL,
    data_json LONGTEXT NULL,
    tarikh DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_import_item_batch (import_batch_id),
    INDEX idx_import_item_status (status_item),
    INDEX idx_import_item_aset (aset_id),
    CONSTRAINT fk_import_item_batch
        FOREIGN KEY (import_batch_id)
        REFERENCES import_batch (import_batch_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_aset_wilayah_workflow
ON aset (wilayah_id, status_workflow_id);

CREATE INDEX idx_aset_agensi_tahun
ON aset (agensi_id, tahun_beli);

CREATE INDEX idx_aset_jenis_status_fizikal
ON aset (jenis_aset, status_aset_id);

CREATE INDEX idx_aset_pendaftar
ON aset (pengguna_id_daftar);


ELECT
    w.wilayah_id,
    w.nama_wilayah,
    d.daerah_id,
    d.nama_daerah,
    COUNT(ag.agensi_id) AS jumlah_agensi
FROM wilayah w
LEFT JOIN daerah d
    ON d.wilayah_id = w.wilayah_id
LEFT JOIN agensi ag
    ON ag.wilayah_id = w.wilayah_id
   AND ag.daerah_id = d.daerah_id
GROUP BY
    w.wilayah_id,
    w.nama_wilayah,
    d.daerah_id,
    d.nama_daerah
ORDER BY
    w.wilayah_id,
    d.nama_daerah;

-- 2. Agensi wilayah yang belum dipetakan kepada daerah.
-- Rekod ini tidak akan muncul selepas pengguna memilih daerah.
SELECT
    ag.agensi_id,
    ag.nama_agensi,
    ag.wilayah_id,
    w.nama_wilayah,
    ag.daerah_id
FROM agensi ag
LEFT JOIN wilayah w
    ON w.wilayah_id = ag.wilayah_id
WHERE ag.wilayah_id > 1
  AND ag.daerah_id IS NULL
ORDER BY w.nama_wilayah, ag.nama_agensi;

-- 3. Pemetaan agensi yang tidak sepadan antara wilayah agensi dan daerah.
SELECT
    ag.agensi_id,
    ag.nama_agensi,
    ag.wilayah_id AS wilayah_agensi,
    d.wilayah_id AS wilayah_daerah,
    ag.daerah_id,
    d.nama_daerah
FROM agensi ag
INNER JOIN daerah d
    ON d.daerah_id = ag.daerah_id
WHERE ag.wilayah_id <> d.wilayah_id;

-- 4. Aset yang wilayahnya tidak sama dengan wilayah agensi.
SELECT
    a.aset_id,
    a.no_pendaftaran,
    a.wilayah_id AS wilayah_aset,
    ag.wilayah_id AS wilayah_agensi,
    ag.nama_agensi
FROM aset a
INNER JOIN agensi ag
    ON ag.agensi_id = a.agensi_id
WHERE a.wilayah_id <> ag.wilayah_id
  AND a.wilayah_id <> 1;

-- 1. Agensi wilayah yang belum dipetakan kepada daerah.
SELECT ag.agensi_id, ag.nama_agensi, ag.wilayah_id, ag.daerah_id
FROM agensi ag
WHERE ag.wilayah_id > 1 AND ag.daerah_id IS NULL
ORDER BY ag.wilayah_id, ag.nama_agensi;

-- 2. Aset yang wilayahnya tidak sama dengan wilayah agensi.
SELECT a.aset_id, a.no_pendaftaran, a.wilayah_id AS wilayah_aset,
       ag.wilayah_id AS wilayah_agensi, ag.nama_agensi
FROM aset a
INNER JOIN agensi ag ON ag.agensi_id = a.agensi_id
WHERE a.wilayah_id <> ag.wilayah_id;


-- Pastikan nombor pendaftaran aset tidak boleh berulang.
-- Jalankan arahan berikut hanya jika jadual aset belum mempunyai UNIQUE INDEX:
-- ALTER TABLE aset
-- ADD UNIQUE KEY uk_aset_no_pendaftaran (no_pendaftaran);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
