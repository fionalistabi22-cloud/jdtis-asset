<?php
/**
 * JTDIS ASSET - ULTIMATE PASSWORD FIX
 * This is the nuclear option - completely rebuild the database with correct data
 */

header('Content-Type: text/html; charset=utf-8');

$password = '12345678';
$email = 'admin@jtdis.gov.my';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Ultimate Fix</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 30px 0; }
        .card { border: none; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #667eea; border-radius: 5px; }
        .step.success { background: #d4edda; border-left-color: #28a745; }
        .step.error { background: #f8d7da; border-left-color: #dc3545; }
    </style>
</head>
<body>
<div class='container' style='max-width: 900px;'>
    <div class='card'>
        <div class='card-header bg-success text-white p-4'>
            <h4 class='mb-0'>🚀 Ultimate Password Fix - Complete Rebuild</h4>
        </div>
        <div class='card-body p-4'>
";

try {
    // STEP 1: Connect without database
    echo "<div class='step'>";
    echo "<h6>Step 1: Connecting to MySQL...</h6>";
    
    $conn = @mysqli_connect('localhost', 'root', '');
    if (!$conn) {
        throw new Exception("MySQL connection failed: " . mysqli_connect_error());
    }
    echo "<p class='text-success'>✅ Connected</p>";
    echo "</div>";
    
    // STEP 2: Drop and recreate database
    echo "<div class='step'>";
    echo "<h6>Step 2: Recreating Database...</h6>";
    
    $db_name = 'jtdis_asset';
    
    // Drop old database
    if (!mysqli_query($conn, "DROP DATABASE IF EXISTS `$db_name`")) {
        throw new Exception("Failed to drop database: " . mysqli_error($conn));
    }
    echo "<p>- Old database dropped</p>";
    
    // Create new database
    if (!mysqli_query($conn, "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        throw new Exception("Failed to create database: " . mysqli_error($conn));
    }
    echo "<p>- New database created</p>";
    
    // Select database
    if (!mysqli_select_db($conn, $db_name)) {
        throw new Exception("Failed to select database: " . mysqli_error($conn));
    }
    mysqli_set_charset($conn, "utf8mb4");
    echo "<p class='text-success'>✅ Database ready</p>";
    echo "</div>";
    
    // STEP 3: Create tables
    echo "<div class='step'>";
    echo "<h6>Step 3: Creating Tables...</h6>";
    
    $tables = [
        "CREATE TABLE `status_pengguna` (
            `status_pengguna_id` tinyint(4) PRIMARY KEY,
            `status` varchar(20) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `status_aset` (
            `status_aset_id` tinyint(4) PRIMARY KEY,
            `nama_status` varchar(50) NOT NULL,
            `warna` varchar(7)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `wilayah` (
            `wilayah_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `nama_wilayah` varchar(100) NOT NULL,
            `jenis` varchar(50),
            `kod_wilayah` varchar(20)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `daerah` (
            `daerah_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `nama_daerah` varchar(100) NOT NULL,
            `wilayah_id` int(11) NOT NULL,
            `kod_daerah` varchar(20),
            FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `agensi` (
            `agensi_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `nama_agensi` varchar(200) NOT NULL,
            `daerah_id` int(11) NOT NULL,
            `jenis_agensi` varchar(50),
            FOREIGN KEY (`daerah_id`) REFERENCES `daerah`(`daerah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `peranan` (
            `peranan_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `nama_peranan` varchar(50) NOT NULL,
            `tahap_hierarki` tinyint(4) NOT NULL,
            `keterangan` text
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `pengguna` (
            `pengguna_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `nama_penuh` varchar(150) NOT NULL,
            `emel` varchar(150) NOT NULL UNIQUE,
            `no_telefon` varchar(20),
            `kata_laluan_hash` varchar(255) NOT NULL,
            `peranan_id` int(11) NOT NULL,
            `wilayah_id` int(11),
            `agensi_id` int(11),
            `status_pengguna_id` tinyint(4) NOT NULL DEFAULT 1,
            `tarikh_daftar` datetime DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`peranan_id`) REFERENCES `peranan`(`peranan_id`),
            FOREIGN KEY (`status_pengguna_id`) REFERENCES `status_pengguna`(`status_pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `aset` (
            `aset_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `no_pendaftaran` varchar(50) NOT NULL UNIQUE,
            `jenis_aset` varchar(100),
            `model` varchar(100),
            `no_siri` varchar(100),
            `tarikh_perolehan` date,
            `nilai_perolehan` decimal(12,2),
            `status_aset_id` tinyint(4),
            `pengguna_id` int(11),
            `agensi_id` int(11),
            `keterangan` text,
            `tarikh_daftar` datetime DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`pengguna_id`),
            FOREIGN KEY (`agensi_id`) REFERENCES `agensi`(`agensi_id`),
            FOREIGN KEY (`status_aset_id`) REFERENCES `status_aset`(`status_aset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `log_aktiviti` (
            `log_id` int(11) PRIMARY KEY AUTO_INCREMENT,
            `pengguna_id` int(11),
            `jenis_aktiviti` varchar(100),
            `keterangan` text,
            `aset_id` int(11),
            `tarikh_aktiviti` datetime DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`pengguna_id`),
            FOREIGN KEY (`aset_id`) REFERENCES `aset`(`aset_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed to create table: " . mysqli_error($conn));
        }
    }
    echo "<p class='text-success'>✅ " . count($tables) . " tables created</p>";
    echo "</div>";
    
    // STEP 4: Insert base data
    echo "<div class='step'>";
    echo "<h6>Step 4: Inserting Base Data...</h6>";
    
    $inserts = [
        "INSERT INTO `status_pengguna` VALUES (1, 'Aktif'), (2, 'Tidak Aktif'), (3, 'Suspend')",
        "INSERT INTO `status_aset` VALUES 
         (1, 'Menunggu Pengesahan', '#FFC107'),
         (2, 'Aktif', '#28a745'),
         (3, 'Rosak', '#dc3545'),
         (4, 'Selenggara', '#17a2b8'),
         (5, 'Tolak', '#6c757d'),
         (6, 'Hilang', '#666666')",
        "INSERT INTO `peranan` VALUES 
         (1, 'Super Admin', 1, 'Pentadbir Sistem'),
         (2, 'Admin Wilayah', 2, 'Pentadbir Wilayah'),
         (3, 'PPTM', 3, 'Pemeriksa Teknikal'),
         (4, 'PTM', 3, 'Pemeriksa Teknikal'),
         (5, 'Ketua Wilayah', 4, 'Ketua Wilayah'),
         (6, 'Ketua Bahagian', 4, 'Ketua Bahagian'),
         (7, 'Pengarah', 5, 'Pengarah'),
         (8, 'Juruteknik', 6, 'Juruteknik')",
        "INSERT INTO `wilayah` VALUES (1, 'Ibu Pejabat', 'ibu_pejabat', 'HP')",
        "INSERT INTO `daerah` VALUES (1, 'Ibu Pejabat', 1, 'HP')",
        "INSERT INTO `agensi` VALUES (1, 'JTDIS', 1, 'Agensi Utama')"
    ];
    
    foreach ($inserts as $sql) {
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Failed to insert data: " . mysqli_error($conn));
        }
    }
    echo "<p class='text-success'>✅ Base data inserted</p>";
    echo "</div>";
    
    // STEP 5: Generate and insert password
    echo "<div class='step'>";
    echo "<h6>Step 5: Generating Password Hash...</h6>";
    
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    echo "<p>Hash: <code style='font-size: 10px; word-break: break-all;'>" . htmlspecialchars($hash) . "</code></p>";
    
    $user_sql = "INSERT INTO `pengguna` (pengguna_id, nama_penuh, emel, no_telefon, kata_laluan_hash, peranan_id, wilayah_id, agensi_id, status_pengguna_id)
                 VALUES (1, 'Pentadbir Sistem', ?, '01234567890', ?, 1, 1, 1, 1)";
    
    $stmt = mysqli_prepare($conn, $user_sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $email, $hash);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to insert user: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    echo "<p class='text-success'>✅ User inserted with hash</p>";
    echo "</div>";
    
    // STEP 6: Verify password
    echo "<div class='step'>";
    echo "<h6>Step 6: Testing Password Verification...</h6>";
    
    $test_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = ?";
    $test_stmt = mysqli_prepare($conn, $test_query);
    mysqli_stmt_bind_param($test_stmt, "s", $email);
    mysqli_stmt_execute($test_stmt);
    $test_result = mysqli_stmt_get_result($test_stmt);
    $test_row = mysqli_fetch_assoc($test_result);
    mysqli_stmt_close($test_stmt);
    
    $verify = password_verify($password, $test_row['kata_laluan_hash']);
    
    if ($verify) {
        echo "<p><strong>Password Test:</strong> <span class='text-success'>✅ PASS</span></p>";
        echo "<p>Password '<strong>" . htmlspecialchars($password) . "</strong>' verifies correctly with stored hash</p>";
        echo "<p class='text-success'>✅ Ready to login</p>";
    } else {
        throw new Exception("Password verification failed!");
    }
    echo "</div>";
    
    // FINAL SUCCESS MESSAGE
    echo "<div class='step success' style='margin-top: 30px;'>";
    echo "<h4>✅✅✅ SUCCESS!</h4>";
    echo "<p><strong>Database has been completely rebuilt.</strong></p>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>📧 Email: <code>" . htmlspecialchars($email) . "</code></li>";
    echo "<li>🔐 Password: <code>" . htmlspecialchars($password) . "</code></li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='pages/login.php' class='btn btn-success btn-lg'>👉 Go to Login Page</a>";
    echo "</p>";
    echo "</div>";
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "<div class='step error'>";
    echo "<h4>❌ ERROR</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "
        </div>
    </div>
</div>
</body>
</html>
";
?>
