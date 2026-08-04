<?php
/**
 * JTDIS ASSET - COMPREHENSIVE PASSWORD FIX & TEST
 * Skrip ini akan:
 * 1. Drop database lama
 * 2. Recreate database dengan schema baru
 * 3. Generate hash yang betul
 * 4. Insert data dengan hash yang betul
 * 5. Verify password bekerja
 */

header('Content-Type: text/html; charset=utf-8');

// Start output buffering untuk debug
ob_start();

$messages = [];
$password = '12345678';

try {
    // STEP 1: Connect ke MySQL (tanpa database)
    $conn = @mysqli_connect('localhost', 'root', '');
    
    if (!$conn) {
        throw new Exception("Sambungan MySQL gagal: " . mysqli_connect_error());
    }
    
    $messages[] = ['type' => 'success', 'text' => "✅ Sambungan MySQL berjaya"];
    
    // STEP 2: Drop database lama
    $db_name = 'jtdis_asset';
    mysqli_query($conn, "DROP DATABASE IF EXISTS `$db_name`");
    $messages[] = ['type' => 'info', 'text' => "ℹ️ Database lama dihapuskan"];
    
    // STEP 3: Buat database baru
    if (!mysqli_query($conn, "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        throw new Exception("Gagal membuat database: " . mysqli_error($conn));
    }
    $messages[] = ['type' => 'success', 'text' => "✅ Database baru dibuat"];
    
    // STEP 4: Pilih database
    if (!mysqli_select_db($conn, $db_name)) {
        throw new Exception("Gagal memilih database: " . mysqli_error($conn));
    }
    mysqli_set_charset($conn, "utf8mb4");
    $messages[] = ['type' => 'success', 'text' => "✅ Database dipilih"];
    
    // STEP 5: Buat jadual
    $create_tables = [
        "CREATE TABLE `status_pengguna` (
            `status_pengguna_id` tinyint(4) NOT NULL,
            `status` varchar(20) NOT NULL,
            PRIMARY KEY (`status_pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `wilayah` (
            `wilayah_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_wilayah` varchar(100) NOT NULL,
            `jenis` varchar(50),
            `kod_wilayah` varchar(20),
            PRIMARY KEY (`wilayah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `daerah` (
            `daerah_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_daerah` varchar(100) NOT NULL,
            `wilayah_id` int(11) NOT NULL,
            `kod_daerah` varchar(20),
            PRIMARY KEY (`daerah_id`),
            FOREIGN KEY (`wilayah_id`) REFERENCES `wilayah`(`wilayah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `agensi` (
            `agensi_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_agensi` varchar(200) NOT NULL,
            `daerah_id` int(11) NOT NULL,
            `jenis_agensi` varchar(50),
            PRIMARY KEY (`agensi_id`),
            FOREIGN KEY (`daerah_id`) REFERENCES `daerah`(`daerah_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `peranan` (
            `peranan_id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_peranan` varchar(50) NOT NULL,
            `tahap_hierarki` tinyint(4) NOT NULL,
            `keterangan` text,
            PRIMARY KEY (`peranan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        "CREATE TABLE `pengguna` (
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
            PRIMARY KEY (`pengguna_id`),
            FOREIGN KEY (`peranan_id`) REFERENCES `peranan`(`peranan_id`),
            FOREIGN KEY (`status_pengguna_id`) REFERENCES `status_pengguna`(`status_pengguna_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($create_tables as $query) {
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal membuat jadual: " . mysqli_error($conn));
        }
    }
    $messages[] = ['type' => 'success', 'text' => "✅ Semua jadual dibuat (" . count($create_tables) . " jadual)"];
    
    // STEP 6: Insert data asas
    $inserts = [
        "INSERT INTO `status_pengguna` VALUES (1, 'Aktif'), (2, 'Tidak Aktif'), (3, 'Suspend')",
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
    
    foreach ($inserts as $query) {
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal insert data: " . mysqli_error($conn));
        }
    }
    $messages[] = ['type' => 'success', 'text' => "✅ Data asas dimasukkan"];
    
    // STEP 7: Generate hash - SANGAT PENTING!
    $correct_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $messages[] = ['type' => 'info', 'text' => "📝 Hash dijana: " . substr($correct_hash, 0, 40) . "..."];
    
    // STEP 8: Insert pengguna dengan hash yang betul
    $pengguna_query = "INSERT INTO `pengguna` (pengguna_id, nama_penuh, emel, no_telefon, kata_laluan_hash, peranan_id, wilayah_id, agensi_id, status_pengguna_id) 
                       VALUES (1, 'Pentadbir Sistem', 'admin@jtdis.gov.my', '01234567890', ?, 1, 1, 1, 1)";
    
    $stmt = mysqli_prepare($conn, $pengguna_query);
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $correct_hash);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Gagal insert pengguna: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    $messages[] = ['type' => 'success', 'text' => "✅ Pengguna admin dimasukkan dengan hash"];
    
    // STEP 9: Verify - PALING PENTING!
    $verify_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = 'admin@jtdis.gov.my'";
    $result = mysqli_query($conn, $verify_query);
    
    if (!$result) {
        throw new Exception("Gagal query verify: " . mysqli_error($conn));
    }
    
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        throw new Exception("Pengguna tidak dijumpai!");
    }
    
    $stored_hash = $row['kata_laluan_hash'];
    $test_password = password_verify($password, $stored_hash);
    
    if ($test_password) {
        $messages[] = ['type' => 'success', 'text' => "✅ ✅ ✅ VERIFIKASI BERJAYA! Password '12345678' BETUL!"];
    } else {
        throw new Exception("❌ Password verify gagal!");
    }
    
    // STEP 10: Final test - login test
    $login_query = "SELECT p.*, r.nama_peranan, r.tahap_hierarki FROM pengguna p 
                    JOIN peranan r ON p.peranan_id = r.peranan_id 
                    WHERE p.emel = ? AND p.status_pengguna_id = 1";
    
    $stmt = mysqli_prepare($conn, $login_query);
    mysqli_stmt_bind_param($stmt, "s", $email = 'admin@jtdis.gov.my');
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user) {
        if (password_verify($password, $user['kata_laluan_hash'])) {
            $messages[] = ['type' => 'success', 'text' => "✅ LOGIN TEST BERJAYA! Pengguna: " . $user['nama_penuh'] . " (" . $user['nama_peranan'] . ")"];
        }
    }
    
    mysqli_close($conn);

} catch (Exception $e) {
    $messages[] = ['type' => 'error', 'text' => "❌ RALAT: " . $e->getMessage()];
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Fix - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 30px 0; }
        .container { max-width: 700px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-success text-white p-3">
                <h5 class="mb-0">🚀 Comprehensive Password Fix & Setup</h5>
            </div>
            <div class="card-body p-4">
                <div id="progress">
                    <?php foreach ($messages as $msg): ?>
                        <?php
                        $alert_class = '';
                        if ($msg['type'] === 'success') $alert_class = 'success';
                        elseif ($msg['type'] === 'error') $alert_class = 'danger';
                        else $alert_class = 'info';
                        ?>
                        <div class="alert alert-<?php echo $alert_class; ?>" role="alert">
                            <?php echo $msg['text']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <hr>
                
                <div class="card bg-light">
                    <div class="card-body">
                        <h6>📋 Login Details:</h6>
                        <p class="mb-1"><strong>Emel:</strong> <code>admin@jtdis.gov.my</code></p>
                        <p class="mb-0"><strong>Kata Laluan:</strong> <code>12345678</code></p>
                    </div>
                </div>
                
                <div class="mt-3 d-grid gap-2">
                    <a href="pages/login.php" class="btn btn-primary btn-lg">
                        ✅ Pergi ke Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
