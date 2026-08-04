<?php
/**
 * JTDIS ASSET - RESET DATABASE
 * Skrip ini akan menghapus database dan membuat yang baru (untuk development sahaja!)
 */

header('Content-Type: text/html; charset=utf-8');

// Hubungi MySQL tanpa memilih database
$conn = @mysqli_connect('localhost', 'root', '');

if (!$conn) {
    die("Ralat Sambungan MySQL: " . mysqli_connect_error());
}

$messages = [];
$db_name = 'jtdis_asset';

// Pastikan ia setup sahaja (tidak produksi)
$allowed_hosts = ['localhost', '127.0.0.1'];
$current_host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (!in_array($current_host, $allowed_hosts) && strpos($current_host, 'localhost') === false) {
    die("Skrip ini hanya boleh dijalankan di localhost untuk keselamatan!");
}

// Semak jika ada parameter 'confirm=yes'
$confirm = $_GET['confirm'] ?? '';

if ($confirm === 'yes') {
    // 1. Drop database yang lama
    $drop_query = "DROP DATABASE IF EXISTS `$db_name`";
    if (mysqli_query($conn, $drop_query)) {
        $messages[] = ['type' => 'success', 'text' => "✅ Database lama dihapuskan"];
    } else {
        $messages[] = ['type' => 'error', 'text' => "❌ Ralat menghapus database: " . mysqli_error($conn)];
    }
    
    // 2. Buat database baru
    $create_db = "CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    if (mysqli_query($conn, $create_db)) {
        $messages[] = ['type' => 'success', 'text' => "✅ Database baru dibuat"];
    } else {
        $messages[] = ['type' => 'error', 'text' => "❌ Ralat membuat database: " . mysqli_error($conn)];
    }
    
    // 3. Redirect ke setup.php untuk membuat jadual dan data
    $messages[] = ['type' => 'info', 'text' => "ℹ️ Silakan ke setup.php untuk membuat jadual dan data"];
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Database - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 500px; padding-top: 50px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-danger text-white p-3">
                <h5 class="mb-0">⚠️ Reset Database - DEVELOPMENT SAHAJA</h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($messages)): ?>
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
                    
                    <?php if ($confirm === 'yes'): ?>
                        <hr>
                        <p>Database telah direset. Sekarang jalankan setup.php untuk membuat jadual dan data:</p>
                        <a href="setup.php" class="btn btn-primary w-100">Pergi ke Setup</a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Amaran!</strong><br>
                        Skrip ini akan menghapuskan semua data dalam database `jtdis_asset`.
                    </div>
                    
                    <p class="text-muted small mb-3">
                        Gunakan skrip ini hanya ketika:<br>
                        • Anda dalam fasa development<br>
                        • Anda mahu memulai semula dari awal
                    </p>
                    
                    <a href="?confirm=yes" class="btn btn-danger w-100" onclick="return confirm('Pasti untuk reset database? Data akan HILANG!')">
                        Reset Database
                    </a>
                    
                    <hr>
                    
                    <p><strong>Atau, guna pendekatan manual:</strong></p>
                    <ol class="small">
                        <li>Buka phpMyAdmin - http://localhost/phpmyadmin</li>
                        <li>Drop database `jtdis_asset`</li>
                        <li>Buka http://localhost/jdtis_asset/setup.php</li>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
