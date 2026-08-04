<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page - Super Admin only
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['peranan'] !== 'Super Admin') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

// Check session expiry
if (isSessionExpired()) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$user = getCurrentUser();

/*
 * ===============================================================
 * Super Admin: statistik PENGGUNA dan DATA INDUK sahaja.
 * TIADA query ke jadual aset di sini.
 * ===============================================================
 */
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM pengguna) as total_pengguna,
    (SELECT COUNT(*) FROM pengguna WHERE status_pengguna_id = 1) as pengguna_aktif,
    (SELECT COUNT(*) FROM wilayah) as total_wilayah,
    (SELECT COUNT(*) FROM daerah) as total_daerah,
    (SELECT COUNT(*) FROM agensi) as total_agensi";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Pecahan pengguna mengikut peranan
$breakdown_query = "SELECT r.nama_peranan, COUNT(p.pengguna_id) as jumlah
                     FROM peranan r
                     LEFT JOIN pengguna p ON p.peranan_id = r.peranan_id
                     GROUP BY r.peranan_id, r.nama_peranan
                     ORDER BY r.tahap_hierarki ASC";
$breakdown_result = mysqli_query($conn, $breakdown_query);

// Pengguna terbaru didaftarkan (semua wilayah)
$recent_query = "SELECT p.nama_penuh, p.emel, r.nama_peranan, w.nama_wilayah, p.tarikh_daftar
                  FROM pengguna p
                  JOIN peranan r ON p.peranan_id = r.peranan_id
                  LEFT JOIN wilayah w ON p.wilayah_id = w.wilayah_id
                  ORDER BY p.tarikh_daftar DESC LIMIT 8";
$recent_result = mysqli_query($conn, $recent_query);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Super Admin JTDIS</title>
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
                <p class="text-warning mb-3"><small>Super Admin</small></p>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link" href="pengguna/index.php"><i class="bi bi-people"></i> Pengguna</a>
                    <a class="nav-link" href="wilayah/index.php"><i class="bi bi-map"></i> Wilayah</a>
                    <a class="nav-link" href="daerah/index.php"><i class="bi bi-building"></i> Daerah</a>
                    <a class="nav-link" href="agensi/index.php"><i class="bi bi-buildings"></i> Agensi / Jabatan</a>
                    <a class="nav-link" href="log-audit/index.php"><i class="bi bi-shield-lock"></i> Log Audit Sistem</a>
                    <!--
                        PEMBETULAN: Tiada menu "Aset" atau "Senarai Aset" di sini.
                        Super Admin TIDAK mempunyai akses kepada modul aset.
                    -->
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Dashboard Super Admin</h2>
                        <p class="text-muted">Selamat datang, <strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></p>
                    </div>
                    <div class="text-right">
                        <p class="mb-0"><strong><?php echo escapeOutput($user['peranan']); ?></strong></p>
                        <small class="text-muted"><?php echo escapeOutput($user['emel']); ?></small>
                    </div>
                </div>

                <!--
                    ===============================================================
                    Statistik PENGGUNA & DATA INDUK sahaja.
                    Tiada kad "Jumlah Aset" atau "Menunggu Pengesahan".
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
                                        <small>Aktif: <?php echo $stats['pengguna_aktif'] ?? 0; ?></small>
                                    </div>
                                    <i class="bi bi-people" style="font-size: 2rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Wilayah</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_wilayah'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-map" style="font-size: 2rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Daerah</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_daerah'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-building" style="font-size: 2rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title">Agensi / Jabatan</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_agensi'] ?? 0; ?></h2>
                                    </div>
                                    <i class="bi bi-buildings" style="font-size: 2rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- BREAKDOWN PENGGUNA MENGIKUT PERANAN -->
                    <div class="col-md-5">
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Pengguna Mengikut Peranan</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($breakdown_result)): ?>
                                            <tr>
                                                <td class="ps-4"><?php echo escapeOutput($row['nama_peranan']); ?></td>
                                                <td class="text-end pe-4"><span class="badge bg-secondary"><?php echo $row['jumlah']; ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="col-md-7">
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                                <h6 class="mb-0"><i class="bi bi-lightning"></i> Tindakan Cepat</h6>
                            </div>
                            <div class="card-body p-4">
                                <a href="pengguna/tambah.php" class="btn btn-primary mb-2 me-2">
                                    <i class="bi bi-person-plus"></i> Daftar Pengguna
                                </a>
                                <a href="wilayah/tambah.php" class="btn btn-success mb-2 me-2">
                                    <i class="bi bi-map"></i> Tambah Wilayah
                                </a>
                                <a href="agensi/tambah.php" class="btn btn-success mb-2 me-2">
                                    <i class="bi bi-buildings"></i> Tambah Agensi
                                </a>
                                <a href="log-audit/index.php" class="btn btn-outline-secondary mb-2">
                                    <i class="bi bi-shield-lock"></i> Lihat Log Audit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECENT USERS TABLE -->
                <div class="card">
                    <div class="card-header" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Pengguna Terbaru Didaftarkan</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Nama</th>
                                    <th>Emel</th>
                                    <th>Peranan</th>
                                    <th>Wilayah</th>
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
                                            <td><?php echo escapeOutput($row['nama_wilayah'] ?? '-'); ?></td>
                                            <td class="pe-4"><?php echo date('d/m/Y', strtotime($row['tarikh_daftar'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tiada pengguna didaftarkan setakat ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>