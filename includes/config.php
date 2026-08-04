<?php

date_default_timezone_set('Asia/Kuala_Lumpur');

require_once __DIR__ . '/jtdi_brand.php';


// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'jtdis_asset';

// Create connection
$conn = @mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die('Sambungan database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if (!mysqli_query($conn, "SET time_zone = '+08:00'")) {
    error_log('Gagal menetapkan zon masa MySQL: ' . mysqli_error($conn));
}
if (!$conn) {
    // If database connection fails, show setup instructions
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="ms">
    <head>
        <meta charset="UTF-8">
        <title>Ralat Sambungan Database</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">
                <h4>Sambungan Database Gagal</h4>
                <p>Ralat: <?php echo mysqli_connect_error(); ?></p>
                <hr>
                <h5>Penyelesaian:</h5>
                <ol>
                    <li>Pastikan MySQL/MariaDB telah dimulakan</li>
                    <li>Import file <code>jtdis_asset.sql</code> ke dalam phpMyAdmin</li>
                    <li>Refresh halaman ini</li>
                </ol>
                <a href="javascript:location.reload()" class="btn btn-primary">Menyegarkan</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define application URL
define('APP_URL', 'http://localhost/jdtis_asset');
?>
