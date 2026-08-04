<?php
/**
 * JTDIS ASSET MANAGEMENT SYSTEM - AUTO SETUP
 * Setup otomatis untuk membina database, jadual dan data permulaan
 */

header('Content-Type: text/html; charset=utf-8');

// Connect to MySQL
$conn = @mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die("Ralat Sambungan MySQL: " . mysqli_connect_error());
}

$messages = [];

// 1. BUAT DATABASE
$db_name = 'jtdis_asset';
if (mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    $messages[] = ['type' => 'success', 'text' => "✅ Database '$db_name' berjaya disediakan"];
} else {
    $messages[] = ['type' => 'error', 'text' => "❌ Ralat membuat database: " . mysqli_error($conn)];
}

if (!mysqli_select_db($conn, $db_name)) {
    $messages[] = ['type' => 'error', 'text' => "❌ Ralat memilih database"];
    mysqli_close($conn);
    die();
}

mysqli_set_charset($conn, "utf8mb4");

// 2. BUAT SEMUA JADUAL
$tables = [
    // Status Pengguna
    "CREATE TABLE IF NOT EXISTS `status_pengguna` (
        `status_pengguna_id` tinyint(4) NOT NULL,
        `status` varchar(20) NOT NULL,
        PRIMARY KEY (`status_pengguna_id`)
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
        FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Agensi
    "CREATE TABLE IF NOT EXISTS `agensi` (
        `agensi_id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_agensi` varchar(200) NOT NULL UNIQUE,
        `daerah_id` int(11) NOT NULL,
        `jenis_agensi` varchar(50),
        PRIMARY KEY (`agensi_id`),
        FOREIGN KEY (`daerah_id`) REFERENCES `daerah`(`daerah_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Peranan (Roles)
    "CREATE TABLE IF NOT EXISTS `peranan` (
        `peranan_id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_peranan` varchar(50) NOT NULL UNIQUE,
        `tahap_hierarki` tinyint(4) NOT NULL,
        `keterangan` text,
        PRIMARY KEY (`peranan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Pengguna
    "CREATE TABLE IF NOT EXISTS `pengguna` (
        `pengguna_id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_penuh` varchar(150) NOT NULL,
        `emel` varchar(150) NOT NULL UNIQUE,
        `no_telefon` varchar(20),
        `kata_laluan_hash` varchar(255) NOT NULL,
        `peranan_id` int(11) NOT NULL,
        `wilayah_id` int(11),
        `agensi_id` int(11),
        `status_pengguna_id` tinyint(4) NOT NULL DEFAULT 1,
        `tarikh_daftar` datetime DEFAULT CURRENT_TIMESTAMP,
        `tarikh_kemaskini` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`pengguna_id`),
        FOREIGN KEY (`peranan_id`) REFERENCES `peranan`(`peranan_id`),
        FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`) ON DELETE SET NULL,
        FOREIGN KEY (`agensi_id`) REFERENCES `agensi`(`agensi_id`) ON DELETE SET NULL,
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
        `tarikh_kemaskini` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`aset_id`),
        UNIQUE KEY (`no_pendaftaran`),
        FOREIGN KEY (`status_aset_id`) REFERENCES `status_aset`(`status_aset_id`),
        FOREIGN KEY (`agensi_id`) REFERENCES `agensi`(`agensi_id`) ON DELETE RESTRICT,
        FOREIGN KEY (`pengguna_id_daftar`) REFERENCES `pengguna`(`pengguna_id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Spesifikasi Komputer
    "CREATE TABLE IF NOT EXISTS `spesifikasi_komputer` (
        `spesifikasi_id` int(11) NOT NULL AUTO_INCREMENT,
        `aset_id` int(11) NOT NULL,
        `prosesor` varchar(150),
        `ram_gb` int(11),
        `cakera_keras` varchar(100),
        PRIMARY KEY (`spesifikasi_id`),
        FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Pengesahan Aset
    "CREATE TABLE IF NOT EXISTS `pengesahan_aset` (
        `pengesahan_id` int(11) NOT NULL AUTO_INCREMENT,
        `aset_id` int(11) NOT NULL,
        `pengguna_id_pengesah` int(11) NOT NULL,
        `status_pengesahan` enum('Menunggu','Lulus','Tolak') DEFAULT 'Menunggu',
        `catatan` text,
        `tarikh_pengesahan` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`pengesahan_id`),
        FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`) ON DELETE CASCADE,
        FOREIGN KEY (`pengguna_id_pengesah`) REFERENCES `pengguna`(`pengguna_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Catatan Tindakan Aset
    "CREATE TABLE IF NOT EXISTS `catatan_tindakan_aset` (
        `catatan_id` int(11) NOT NULL AUTO_INCREMENT,
        `aset_id` int(11) NOT NULL,
        `pengguna_id_tindakan` int(11) NOT NULL,
        `jenis_tindakan` enum('Upgrade','Selenggara','Perbaharui','Lain') NOT NULL,
        `keterangan` text,
        `status_catatan` enum('Menunggu','Selesai','Perlu Tindakan Semula') DEFAULT 'Menunggu',
        `tarikh_input` datetime DEFAULT CURRENT_TIMESTAMP,
        `tarikh_kemaskini` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`catatan_id`),
        FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`) ON DELETE CASCADE,
        FOREIGN KEY (`pengguna_id_tindakan`) REFERENCES `pengguna`(`pengguna_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // Log Aktiviti
    "CREATE TABLE IF NOT EXISTS `log_aktiviti` (
        `log_id` int(11) NOT NULL AUTO_INCREMENT,
        `pengguna_id` int(11) NOT NULL,
        `jenis_aktiviti` varchar(100),
        `keterangan` text,
        `aset_id` int(11),
        `tarikh_aktiviti` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`),
        FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`pengguna_id`) ON DELETE CASCADE,
        FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $query) {
    if (!mysqli_query($conn, $query)) {
        $messages[] = ['type' => 'error', 'text' => "❌ Ralat: " . mysqli_error($conn)];
    }
}
$messages[] = ['type' => 'success', 'text' => "✅ Semua jadual berjaya disediakan"];

// 3. MASUKKAN DATA PERMULAAN
// Cek jika data sudah ada
$check_query = "SELECT COUNT(*) as count FROM pengguna";
$result = mysqli_query($conn, $check_query);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    $messages[] = ['type' => 'info', 'text' => "📝 Memasukkan data permulaan..."];
    
    // Hasilkan bcrypt hash untuk password: 12345678
    $password_hash = password_hash('12345678', PASSWORD_BCRYPT, ['cost' => 10]);
    
    $inserts = [
        // Status Pengguna
        "INSERT IGNORE INTO `status_pengguna` VALUES (1, 'Aktif'), (2, 'Tidak Aktif'), (3, 'Suspend')",
        
        // Status Aset
        "INSERT IGNORE INTO `status_aset` VALUES 
         (1, 'Menunggu Pengesahan', '#FFC107'),
         (2, 'Aktif', '#28a745'),
         (3, 'Rosak', '#dc3545'),
         (4, 'Selenggara', '#17a2b8'),
         (5, 'Tolak', '#6c757d'),
         (6, 'Hilang', '#666666')",
        
        // Peranan
        "INSERT IGNORE INTO `peranan` VALUES 
         (1, 'Super Admin', 1, 'Pentadbir Sistem Utama'),
         (2, 'Admin Wilayah', 2, 'Pentadbir Wilayah'),
         (3, 'PPTM', 3, 'Pemeriksa Teknikal Terpilih'),
         (4, 'PTM', 3, 'Pemeriksa Teknikal Mingguan'),
         (5, 'Ketua Wilayah', 4, 'Ketua Wilayah'),
         (6, 'Ketua Bahagian', 4, 'Ketua Bahagian'),
         (7, 'Pengarah', 5, 'Pengarah'),
         (8, 'Juruteknik', 6, 'Juruteknik/Ejen IT')",
        
        // Wilayah
        "INSERT IGNORE INTO `wilayah` VALUES (1, 'Ibu Pejabat', 'ibu_pejabat', 'HP')",
        
        // Daerah
        "INSERT IGNORE INTO `daerah` VALUES (1, 'Ibu Pejabat', 1, 'HP')",
        
        // Agensi
        "INSERT IGNORE INTO `agensi` VALUES (1, 'JTDIS', 1, 'Agensi Utama')"
    ];
    
    // Insert pengguna dengan hash yang dijana
    $pengguna_query = "INSERT IGNORE INTO `pengguna` (pengguna_id, nama_penuh, emel, no_telefon, kata_laluan_hash, peranan_id, wilayah_id, agensi_id, status_pengguna_id) 
                       VALUES (1, 'Pentadbir Sistem', 'admin@jtdis.gov.my', '01234567890', ?, 1, 1, 1, 1)";
    
    $stmt = mysqli_prepare($conn, $pengguna_query);
    mysqli_stmt_bind_param($stmt, "s", $password_hash);
    
    // Jalankan semua insert
    foreach ($inserts as $query) {
        if (!mysqli_query($conn, $query)) {
            $messages[] = ['type' => 'error', 'text' => "❌ Ralat: " . mysqli_error($conn)];
        }
    }
    
    // Insert pengguna
    if (mysqli_stmt_execute($stmt)) {
        $messages[] = ['type' => 'success', 'text' => "✅ Pengguna admin berjaya dibuat"];
    } else {
        $messages[] = ['type' => 'error', 'text' => "❌ Ralat insert pengguna: " . mysqli_error($conn)];
    }
    
    $messages[] = ['type' => 'success', 'text' => "✅ Data permulaan berjaya dimasukkan"];
} else {
    $messages[] = ['type' => 'info', 'text' => "ℹ️ Data sudah ada (" . $row['count'] . " pengguna)"];
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persediaan Sistem JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px 15px 0 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header text-white p-4">
                        <h3 class="mb-0">⚙️ Persediaan Sistem JTDIS</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php foreach ($messages as $msg): ?>
                            <?php
                            $alert_class = '';
                            if ($msg['type'] === 'success') $alert_class = 'success';
                            elseif ($msg['type'] === 'error') $alert_class = 'danger';
                            else $alert_class = 'info';
                            ?>
                            <div class="alert alert-<?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                                <?php echo $msg['text']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr class="my-4">
                        
                        <div class="alert alert-success">
                            <h5 class="mb-3">✅ Sistem Siap Digunakan!</h5>
                            <p class="mb-2"><strong>Akaun Log Masuk:</strong></p>
                            <ul class="mb-3">
                                <li><strong>Emel:</strong> admin@jtdis.gov.my</li>
                                <li><strong>Kata Laluan:</strong> 12345678</li>
                            </ul>
                            <small class="text-muted">Sila tukar kata laluan selepas log masuk pertama.</small>
                        </div>
                        
                        <a href="pages/login.php" class="btn btn-primary btn-lg w-100">Pergi ke Log Masuk →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
