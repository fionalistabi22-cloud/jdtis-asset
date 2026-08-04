<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

requireRoleWhitelist(['Juruteknik']);

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);

if ($pengguna_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun tidak lengkap.';
    header('Location: ../logout.php');
    exit;
}

// Get user info
$user_query = "SELECT p.nama_penuh, p.emel, w.nama_wilayah
              FROM pengguna p
              LEFT JOIN wilayah w ON p.wilayah_id = w.wilayah_id
              WHERE p.pengguna_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $pengguna_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Get statistics
$aset_count_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ?";
$aset_count_stmt = mysqli_prepare($conn, $aset_count_query);
mysqli_stmt_bind_param($aset_count_stmt, "i", $pengguna_id);
mysqli_stmt_execute($aset_count_stmt);
$aset_count_result = mysqli_stmt_get_result($aset_count_stmt);
$aset_count = mysqli_fetch_assoc($aset_count_result)['total'];

// Status workflow laluan wilayah:
// 1 = Draf
// 2 = Menunggu PPTM
// 3 = Ditolak PPTM
// 4 = Menunggu KW
// 5 = Ditolak KW
// 8 = Lulus
//
// Kad dashboard:
// pending = 2
// dalam proses / diluluskan = 4 atau 8
// ditolak = 3 atau 5
$pending_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id = 2";
$pending_stmt = mysqli_prepare($conn, $pending_query);
mysqli_stmt_bind_param($pending_stmt, "i", $pengguna_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_count = (int)mysqli_fetch_assoc($pending_result)['total'];

$lulus_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id IN (4, 8)";
$lulus_stmt = mysqli_prepare($conn, $lulus_query);
mysqli_stmt_bind_param($lulus_stmt, "i", $pengguna_id);
mysqli_stmt_execute($lulus_stmt);
$lulus_result = mysqli_stmt_get_result($lulus_stmt);
$lulus_count = (int)mysqli_fetch_assoc($lulus_result)['total'];

// Ditolak pada laluan wilayah: Ditolak PPTM (3) atau Ditolak KW (5)
$ditolak_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id IN (3, 5)";
$ditolak_stmt = mysqli_prepare($conn, $ditolak_query);
mysqli_stmt_bind_param($ditolak_stmt, "i", $pengguna_id);
mysqli_stmt_execute($ditolak_stmt);
$ditolak_result = mysqli_stmt_get_result($ditolak_stmt);
$ditolak_count = (int)mysqli_fetch_assoc($ditolak_result)['total'];

$csrf_token = generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Juruteknik JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="aset.css">

</head>
<body class="page-dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <aside class="col-md-3 col-xl-2 sidebar p-4">
                <div class="brand-wrap">
                    <div class="brand-mark"><i class="bi bi-pc-display-horizontal"></i></div>
                    <div>
                        <h4 class="brand-title">JTDIS</h4>
                        <p class="brand-subtitle">Modul Aset ICT</p>
                    </div>
                </div>
                <div class="role-pill"><i class="bi bi-person-badge"></i> Juruteknik</div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link" href="tambah.php"><i class="bi bi-plus-circle"></i> Daftar Aset</a>
                    <a class="nav-link" href="index.php"><i class="bi bi-boxes"></i> Aset Saya</a>
                    <a class="nav-link" href="/jdtis_asset/pages/import-aset/index.php">
                        <i class="bi bi-cloud-arrow-up"></i> Import Aset
                    </a>
                    <hr class="nav-separator">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <h1 class="page-title">Dashboard Juruteknik</h1>
                        <p class="page-subtitle">Selamat datang, <?php echo escapeOutput($user['nama_penuh']); ?></p>
                    </div>
                    <div class="page-chip"><i class="bi bi-geo-alt"></i> Juruteknik - <?php echo escapeOutput($user['nama_wilayah'] ?? 'Tidak Ditugaskan'); ?></div>
                </div>
                <div class="alert hero-note mb-4"><i class="bi bi-info-circle-fill me-2"></i>Gunakan modul ini untuk mendaftar aset baharu, melihat status pengesahan dan mengurus aset yang telah anda daftarkan.</div>

                <!-- User Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Maklumat Pengguna</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nama:</strong> <?php echo escapeOutput($user['nama_penuh']); ?></p>
                                <p><strong>Emel:</strong> <?php echo escapeOutput($user['emel']); ?></p>
                                <p><strong>Peranan:</strong> <span class="badge bg-primary">Juruteknik</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Wilayah:</strong> <?php echo escapeOutput($user['nama_wilayah'] ?? 'Tidak Ditugaskan'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics & Register Button -->
                <div class="row g-3 mb-4 align-items-stretch">
                    <div class="col-lg-9">
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3">
                                <div class="stat-card stat-blue">
                                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                                    <div class="stat-value"><?php echo $aset_count; ?></div>
                                    <div class="stat-label">Jumlah Aset</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="stat-card stat-orange">
                                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                                    <div class="stat-value"><?php echo $pending_count; ?></div>
                                    <div class="stat-label">Menunggu PPTM</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="stat-card stat-green">
                                    <div class="stat-icon"><i class="bi bi-patch-check"></i></div>
                                    <div class="stat-value"><?php echo $lulus_count; ?></div>
                                    <div class="stat-label">Dalam Proses / Diluluskan</div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="stat-card stat-red">
                                    <div class="stat-icon"><i class="bi bi-x-octagon"></i></div>
                                    <div class="stat-value"><?php echo $ditolak_count; ?></div>
                                    <div class="stat-label">Ditolak</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <h5 class="fw-bold mb-2">Tindakan Utama</h5>
                                <p class="text-muted mb-4">Daftarkan aset baharu dan hantar untuk pengesahan.</p>
                                <a href="tambah.php" class="btn btn-primary btn-lg w-100"><i class="bi bi-plus-circle me-2"></i>Daftar Aset</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3"><i class="bi bi-lightning-charge-fill me-2"></i>Akses Pantas</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <a href="index.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-eye"></i> Lihat Semua Aset
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="tambah.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-plus-circle"></i> Daftar Aset Baru
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="../logout.php" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                                </a>
                            </div>
                            <div class="col-md-4 mt-3">
                                <a href="\jdtis_asset/pages/import-aset/index.php"
                                   class="btn btn-outline-success w-100">
                                    <i class="bi bi-cloud-arrow-up"></i> Import Aset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="alert hero-note">
                    <h6><i class="bi bi-info-circle"></i> <strong>Bantuan</strong></h6>
                    <ul class="mb-0" style="margin-left: 20px;">
                        <li>Gunakan menu "Aset" untuk melihat senarai aset yang telah didaftarkan</li>
                        <li>Klik "Daftar Aset Baru" untuk menambah aset baru ke dalam sistem</li>
                        <li>Aset yang baru didaftarkan akan mempunyai status "Menunggu Pengesahan" sehingga diluluskan oleh PPTM/PTM</li>
                        <li>Hubungi pentadbir sistem sekiranya menghadapi masalah</li>
                    </ul>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>