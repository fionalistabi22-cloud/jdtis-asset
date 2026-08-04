<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Gunakan fungsi auth untuk check login
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Check session expiry
if (isSessionExpired()) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get current user
$user = getCurrentUser();



/*
 * ===============================================================
 * PEMBETULAN: Tambah redirect untuk Super Admin.
 * Sebelum ini Super Admin tiada dashboard khusus, jadi dia
 * tersangkut di dashboard.php generik (yang ada kad "Jumlah Aset").
 * ===============================================================
 */
if ($user['peranan'] === 'Super Admin') {
    header("Location: superadmin/dashboard.php");
    exit;
}

// Redirect Agen IT to their dedicated dashboard
if ($user['peranan'] === 'Agen IT') {
    header("Location: agen-it/dashboard.php");
    exit;
}

// Redirect Juruteknik to their dedicated dashboard
if ($user['peranan'] === 'Juruteknik') {
    header("Location: aset/dashboard.php");
    exit;
}

// Redirect Admin Wilayah to their dedicated dashboard
if ($user['peranan'] === 'Admin Wilayah') {
    header("Location: admin-wilayah/dashboard.php");
    exit;
}

// Redirect PPTM/PTM to their dedicated dashboard
if (in_array($user['peranan'], ['PPTM', 'PTM'])) {
    header("Location: pptm-ptm/dashboard.php");
    exit;
}
if ($_SESSION['peranan'] === 'Ketua Wilayah') {
    header("Location: ketua-wilayah/dashboard.php");
    exit;
}

if ($_SESSION['peranan'] === 'Ketua Bahagian') {
    header("Location: ketua-bahagian/dashboard.php");
    exit;
}

if ($user['peranan'] === 'Pengarah') {
    header("Location: pengarah/dashboard.php");
    exit;
}

if ($user['peranan'] === 'PID') {
    header('Location: pid/dashboard.php');
    exit;
}

/*
 * ===============================================================
 * CATATAN: Selepas pembetulan ini, fail dashboard.php generik
 * di bawah ini hanya akan digunakan oleh role yang BELUM ada
 * dashboard khusus (Ketua Wilayah, Ketua Bahagian, Pengarah, PID).
 *
 * Untuk role-role ini, kad statistik di bawah PERLU disesuaikan
 * mengikut skop akses masing-masing - JANGAN biarkan generik
 * selama-lamanya. Buat dashboard khusus untuk setiap satu apabila
 * giliran fasa pembangunan mereka tiba.
 * ===============================================================
 */
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Pengurusan Aset JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 sidebar p-4">
                <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>

                    <?php if ($user['peranan'] === 'Ketua Wilayah'): ?>
                        <a class="nav-link" href="#"><i class="bi bi-pencil-square"></i> Catatan Tindakan</a>
                        <a class="nav-link" href="#"><i class="bi bi-list-check"></i> Riwayat</a>

                    <?php elseif ($user['peranan'] === 'Ketua Bahagian'): ?>
                        <a class="nav-link" href="#"><i class="bi bi-eye"></i> Semakan Tindakan</a>
                        <a class="nav-link" href="#"><i class="bi bi-check-lg"></i> Kelulusan</a>

                    <?php elseif ($user['peranan'] === 'Pengarah'): ?>
                        <a class="nav-link" href="#"><i class="bi bi-bar-chart"></i> Laporan Statistik</a>
                        <a class="nav-link" href="#"><i class="bi bi-download"></i> Export Data</a>

                    <?php elseif ($user['peranan'] === 'PID'): ?>
                        <a class="nav-link" href="#"><i class="bi bi-plus-circle"></i> Daftar Aset</a>
                        <a class="nav-link" href="#"><i class="bi bi-boxes"></i> Aset Kementerian/Jabatan</a>

                    <?php endif; ?>

                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Dashboard</h2>
                        <p class="text-muted">Selamat datang, <strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></p>
                    </div>
                    <div class="text-right">
                        <p class="mb-0"><strong><?php echo escapeOutput($user['peranan']); ?></strong></p>
                        <small class="text-muted"><?php echo escapeOutput($user['emel']); ?></small>
                    </div>
                </div>

                <!-- INFO BOX -->
                <div class="card p-4">
                    <h5><i class="bi bi-info-circle"></i> Maklumat Sistem</h5>
                    <p class="mb-0">Dashboard khusus untuk peranan <?php echo escapeOutput($user['peranan']); ?> sedang dalam pembangunan. Statistik akan dipaparkan mengikut skop akses peranan ini sahaja.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>