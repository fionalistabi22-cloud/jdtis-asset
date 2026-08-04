<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page - Admin Wilayah only
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['peranan'] !== 'Admin Wilayah') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

// Check session expiry
if (isSessionExpired()) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$user = getCurrentUser();
$wilayah_id = $user['wilayah_id'];

// Get wilayah name
$wilayah_query = "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ?";
$wilayah_stmt = mysqli_prepare($conn, $wilayah_query);
mysqli_stmt_bind_param($wilayah_stmt, "i", $wilayah_id);
mysqli_stmt_execute($wilayah_stmt);
$wilayah_result = mysqli_stmt_get_result($wilayah_stmt);
$wilayah = mysqli_fetch_assoc($wilayah_result);

/*
 * ===============================================================
 * PEMBETULAN: Statistik PENGGUNA sahaja - TIADA query aset.
 * Admin Wilayah hanya urus pengguna dalam wilayah sendiri.
 * ===============================================================
 */
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM pengguna WHERE wilayah_id = ?) as total_pengguna,
    (SELECT COUNT(*) FROM pengguna WHERE wilayah_id = ? AND status_pengguna_id = 1) as pengguna_aktif,
    (SELECT COUNT(*) FROM pengguna WHERE wilayah_id = ? AND status_pengguna_id != 1) as pengguna_tidak_aktif,
    (SELECT COUNT(*) FROM pengguna p JOIN peranan r ON p.peranan_id = r.peranan_id WHERE p.wilayah_id = ? AND r.nama_peranan = 'Juruteknik') as jumlah_juruteknik,
    (SELECT COUNT(*) FROM pengguna p JOIN peranan r ON p.peranan_id = r.peranan_id WHERE p.wilayah_id = ? AND r.nama_peranan = 'Agen IT') as jumlah_agen_it";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iiiii", $wilayah_id, $wilayah_id, $wilayah_id, $wilayah_id, $wilayah_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent users in this wilayah (untuk senarai "pengguna terbaru")
$recent_query = "SELECT p.nama_penuh, p.emel, r.nama_peranan, p.tarikh_daftar 
                  FROM pengguna p 
                  JOIN peranan r ON p.peranan_id = r.peranan_id 
                  WHERE p.wilayah_id = ? 
                  ORDER BY p.tarikh_daftar DESC LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_query);
mysqli_stmt_bind_param($recent_stmt, "i", $wilayah_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Wilayah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin-wilayah.css">
</head>
<body class="admin-wilayah page-dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 sidebar p-4">
                <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
                <p class="text-warning mb-3"><small>Admin Wilayah</small></p>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link" href="pengguna/index.php"><i class="bi bi-people"></i> Pengguna</a>
                    <a class="nav-link" href="pengguna/tambah.php"><i class="bi bi-person-plus"></i> Daftar Pengguna</a>
                    <!--
                        PEMBETULAN: Tiada menu "Aset" atau "Senarai Aset" di sini.
                        Admin Wilayah TIDAK mempunyai akses kepada modul aset.
                    -->
                    <hr class="sidebar-divider">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Dashboard Admin Wilayah</h2>
                        <p class="text-muted">Selamat datang, <strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></p>
                    </div>
                    <div class="text-right">
                        <p class="mb-0"><strong><?php echo escapeOutput($user['peranan']); ?></strong></p>
                        <small class="text-muted"><?php echo escapeOutput($wilayah['nama_wilayah']); ?></small>
                    </div>
                </div>

                <!-- REGION INFO CARD -->
                <div class="card region-card mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title"><i class="bi bi-map"></i> Wilayah Anda</h5>
                        <p class="card-text mb-0"><?php echo escapeOutput($wilayah['nama_wilayah']); ?></p>
                    </div>
                </div>

                <!--
                    ===============================================================
                    PEMBETULAN: Statistik PENGGUNA sahaja.
                    Kad "Jumlah Aset" dan "Aset Aktif" dibuang sepenuhnya.
                    ===============================================================
                -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Jumlah Pengguna</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_pengguna'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-people" class="stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Pengguna Aktif</h6>
                                        <h2 class="mb-0"><?php echo $stats['pengguna_aktif'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-person-check" class="stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Juruteknik</h6>
                                        <h2 class="mb-0"><?php echo $stats['jumlah_juruteknik'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-tools" class="stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Agen IT</h6>
                                        <h2 class="mb-0"><?php echo $stats['jumlah_agen_it'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-person-badge" class="stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QUICK ACTIONS -->
                <div class="card mb-4">
                    <div class="card-header section-card-header">
                        <h6 class="mb-0"><i class="bi bi-lightning"></i> Tindakan Cepat</h6>
                    </div>
                    <div class="card-body p-4">
                        <a href="pengguna/index.php" class="btn btn-primary btn-lg me-2 mb-2">
                            <i class="bi bi-people"></i> Urus Pengguna
                        </a>
                        <a href="pengguna/tambah.php" class="btn btn-success btn-lg mb-2">
                            <i class="bi bi-plus-circle"></i> Pengguna Baru
                        </a>
                    </div>
                </div>

                <!-- RECENT USERS TABLE -->
                <div class="card">
                    <div class="card-header section-card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Pengguna Terbaru Didaftarkan</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Nama</th>
                                    <th>Emel</th>
                                    <th>Peranan</th>
                                    <th class="pe-4">Tarikh Daftar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recent_result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                                        <tr>
                                            <td class="ps-4"><?php echo escapeOutput($row['nama_penuh']); ?></td>
                                            <td><?php echo escapeOutput($row['emel']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo escapeOutput($row['nama_peranan']); ?></span></td>
                                            <td class="pe-4"><?php echo date('d/m/Y', strtotime($row['tarikh_daftar'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tiada pengguna didaftarkan setakat ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SYSTEM INFO -->
                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle"></i> <strong>Maklumat:</strong> Anda hanya boleh menguruskan pengguna dari wilayah <?php echo escapeOutput($wilayah['nama_wilayah']); ?> sahaja. Hubungi Super Admin untuk perubahan di wilayah lain.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>