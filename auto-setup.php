<?php
/**
 * JTDIS Asset Management System - Automatic Setup Script
 * This script will automatically create database and tables
 */

header('Content-Type: text/html; charset=utf-8');

// Connect to MySQL without selecting a database first
$conn = @mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die("MySQL Connection Failed: " . mysqli_connect_error());
}

$messages = [];

// 1. Create Database
$db_name = 'jtdis_asset';
$create_db = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";

if (mysqli_query($conn, $create_db)) {
    $messages[] = ['type' => 'success', 'text' => "✅ Database '$db_name' created/exists"];
} else {
    $messages[] = ['type' => 'error', 'text' => "❌ Error creating database: " . mysqli_error($conn)];
    exit;
}

// 2. Select Database
if (!mysqli_select_db($conn, $db_name)) {
    $messages[] = ['type' => 'error', 'text' => "❌ Error selecting database: " . mysqli_error($conn)];
    exit;
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// 3. Check if tables exist
$tables_query = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$db_name'";
$result = mysqli_query($conn, $tables_query);
$row = mysqli_fetch_assoc($result);

if ($row['table_count'] > 0) {
    $messages[] = ['type' => 'info', 'text' => "ℹ️ Database tables already exist (" . $row['table_count'] . " tables)"];
} else {
    $messages[] = ['type' => 'info', 'text' => "📝 Creating database tables..."];
    
    // Create all tables
    $sql_queries = [
        // Status Pengguna
        "CREATE TABLE IF NOT EXISTS `status_pengguna` (
            `status_pengguna_id` tinyint(4) NOT NULL,
            `status` varchar(20) NOT NULL,
            PRIMARY KEY (`status_pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Peranan (Roles)
        "CREATE TABLE IF NOT EXISTS `peranan` (
            `peranan_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_peranan` varchar(50) NOT NULL UNIQUE,
            `tahap_hierarki` tinyint(4) NOT NULL,
            `keterangan` text,
            PRIMARY KEY (`peranan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Wilayah
        "CREATE TABLE IF NOT EXISTS `wilayah` (
            `wilayah_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_wilayah` varchar(100) NOT NULL,
            `jenis` enum('ibu_pejabat','wilayah') NOT NULL,
            `kod_wilayah` varchar(20),
            PRIMARY KEY (`wilayah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Daerah
        "CREATE TABLE IF NOT EXISTS `daerah` (
            `daerah_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_daerah` varchar(100) NOT NULL UNIQUE,
            `wilayah_id` int(11) NOT NULL,
            `kod_daerah` varchar(20),
            PRIMARY KEY (`daerah_id`),
            FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Agensi
        "CREATE TABLE IF NOT EXISTS `agensi` (
            `agensi_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_agensi` varchar(200) NOT NULL UNIQUE,
            `daerah_id` int(11) NOT NULL,
            `jenis_agensi` varchar(50),
            PRIMARY KEY (`agensi_id`),
            FOREIGN KEY (`daerah_id`) REFERENCES `daerah`(`daerah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Pengguna
        "CREATE TABLE IF NOT EXISTS `pengguna` (
            `pengguna_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_penuh` varchar(150) NOT NULL,
            `emel` varchar(150) NOT NULL UNIQUE,
            `kata_laluan_hash` varchar(255) NOT NULL,
            `peranan_id` int(11) NOT NULL,
            `wilayah_id` int(11),
            `agensi_id` int(11),
            `status_pengguna_id` tinyint(4) NOT NULL DEFAULT 1,
            `tarikh_daftar` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`pengguna_id`),
            FOREIGN KEY (`peranan_id`) REFERENCES `peranan`(`peranan_id`),
            FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`),
            FOREIGN KEY (`agensi_id`) REFERENCES `agensi`(`agensi_id`),
            FOREIGN KEY (`status_pengguna_id`) REFERENCES `status_pengguna`(`status_pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Status Aset
        "CREATE TABLE IF NOT EXISTS `status_aset` (
            `status_aset_id` tinyint(4) NOT NULL,
            `status` varchar(30) NOT NULL,
            `warna` varchar(20),
            PRIMARY KEY (`status_aset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Aset
        "CREATE TABLE IF NOT EXISTS `aset` (
            `aset_id` int(11) NOT NULL AUTO_INCREMENT,
            `no_pendaftaran` varchar(100) NOT NULL UNIQUE,
            `jenis_aset` enum('NB','PC','Pencetak','Monitor','Lain') NOT NULL,
            `jenis_perolehan` enum('KERAJAAN_NEGERI','SEWA','PINJAMAN') NOT NULL,
            `tahun_beli` year,
            `jenama` varchar(100),
            `model` varchar(100),
            `status_aset_id` tinyint(4) NOT NULL,
            `agensi_id` int(11) NOT NULL,
            `pengguna_id_daftar` int(11) NOT NULL,
            `catatan` text,
            `tarikh_input` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`aset_id`),
            FOREIGN KEY (`status_aset_id`) REFERENCES `status_aset`(`status_aset_id`),
            FOREIGN KEY (`agensi_id`) REFERENCES `agensi`(`agensi_id`),
            FOREIGN KEY (`pengguna_id_daftar`) REFERENCES `pengguna`(`pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Spesifikasi Komputer
        "CREATE TABLE IF NOT EXISTS `spesifikasi_komputer` (
            `spesifikasi_id` int(11) NOT NULL AUTO_INCREMENT,
            `aset_id` int(11) NOT NULL,
            `prosesor` varchar(150),
            `ram_gb` int(11),
            `cakera_keras` varchar(100),
            PRIMARY KEY (`spesifikasi_id`),
            FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($sql_queries as $query) {
        if (!mysqli_query($conn, $query)) {
            $messages[] = ['type' => 'error', 'text' => "❌ Error: " . mysqli_error($conn)];
        }
    }
    
    $messages[] = ['type' => 'success', 'text' => "✅ All tables created successfully"];
}

// 4. Check if data exists
$check_data = "SELECT COUNT(*) as count FROM pengguna";
$result = mysqli_query($conn, $check_data);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    $messages[] = ['type' => 'info', 'text' => "ℹ️ User data already exists (" . $row['count'] . " users)"];
} else {
    $messages[] = ['type' => 'info', 'text' => "📝 Inserting sample data..."];
    
    // Insert Status Data
    $inserts = [
        "INSERT IGNORE INTO `status_pengguna` VALUES (1, 'Aktif'), (2, 'Tidak Aktif'), (3, 'Suspend')",
        "INSERT IGNORE INTO `status_aset` VALUES (1, 'Baru', '#28a745'), (2, 'Guna', '#0066cc'), (3, 'Rosak', '#dc3545'), (4, 'Hilang', '#6c757d')",
        "INSERT IGNORE INTO `peranan` VALUES (1, 'Admin', 1, 'Pentadbir Sistem'), (2, 'Pengurus Aset', 2, 'Pengurus Aset'), (3, 'Pegawai', 3, 'Pegawai Biasa')",
        "INSERT IGNORE INTO `wilayah` VALUES (1, 'Ibu Pejabat', 'ibu_pejabat', 'HP')",
        "INSERT IGNORE INTO `daerah` VALUES (1, 'Ibu Pejabat', 1, 'HP')",
        "INSERT IGNORE INTO `agensi` VALUES (1, 'JTDIS', 1, 'Agensi Utama')",
        "INSERT IGNORE INTO `pengguna` (pengguna_id, nama_penuh, emel, kata_laluan_hash, peranan_id, wilayah_id, agensi_id, status_pengguna_id) VALUES (1, 'Pentadbir Sistem', 'admin@jtdis.gov.my', '\$2y\$10\$Q9r9TZSfEpqXZ6OZvZg4wuUr4.EGo5kGkGXrQqKxQKqHvXh5TGZ9W', 1, 1, 1, 1)"
    ];
    
    foreach ($inserts as $query) {
        if (!mysqli_query($conn, $query)) {
            $messages[] = ['type' => 'error', 'text' => "❌ Error: " . mysqli_error($conn)];
        }
    }
    
    $messages[] = ['type' => 'success', 'text' => "✅ Sample data inserted successfully"];
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Sistem - JTDIS Aset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4>⚙️ Persediaan Sistem JTDIS Aset</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= $msg['type'] === 'success' ? 'success' : ($msg['type'] === 'error' ? 'danger' : 'info') ?>">
                                <?= $msg['text'] ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <div class="alert alert-success">
                            <h5>✅ Sistem Siap!</h5>
                            <p>Sila log masuk dengan akaun berikut:</p>
                            <ul>
                                <li><strong>Emel:</strong> admin@jtdis.gov.my</li>
                                <li><strong>Kata Laluan:</strong> 12345678</li>
                            </ul>
                        </div>
                        
                        <a href="pages/login.php" class="btn btn-primary w-100">Pergi ke Log Masuk</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
