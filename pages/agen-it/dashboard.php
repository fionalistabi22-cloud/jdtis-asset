<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

requireRoleWhitelist(['Agen IT']);

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);

if ($pengguna_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun tidak lengkap.';
    header('Location: ../logout.php');
    exit;
}

// Get user info
$user_query = "SELECT p.nama_penuh, p.emel, a.nama_agensi, w.nama_wilayah, d.nama_daerah
              FROM pengguna p
              LEFT JOIN agensi a ON p.agensi_id = a.agensi_id
              LEFT JOIN daerah d ON a.daerah_id = d.daerah_id
              LEFT JOIN wilayah w
                ON w.wilayah_id = COALESCE(p.wilayah_id, a.wilayah_id, d.wilayah_id)
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

// Status workflow mapping (dokumen):
// pending     = status_workflow_id = 2 (Menunggu PPTM)
// ditolak     = status_workflow_id = 3 (Ditolak PPTM)
// diluluskan  = status_workflow_id >= 4 (Menunggu/ Lulus)
$pending_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id = 2";
$pending_stmt = mysqli_prepare($conn, $pending_query);
mysqli_stmt_bind_param($pending_stmt, "i", $pengguna_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
$pending_count = (int)mysqli_fetch_assoc($pending_result)['total'];

$rejected_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id IN (3, 5)";
$rejected_stmt = mysqli_prepare($conn, $rejected_query);
mysqli_stmt_bind_param($rejected_stmt, "i", $pengguna_id);
mysqli_stmt_execute($rejected_stmt);
$rejected_result = mysqli_stmt_get_result($rejected_stmt);
$rejected_count = (int)mysqli_fetch_assoc($rejected_result)['total'];

$lulus_query = "SELECT COUNT(*) as total FROM aset WHERE pengguna_id_daftar = ? AND status_workflow_id IN (4, 8)";
$lulus_stmt = mysqli_prepare($conn, $lulus_query);
mysqli_stmt_bind_param($lulus_stmt, "i", $pengguna_id);
mysqli_stmt_execute($lulus_stmt);
$lulus_result = mysqli_stmt_get_result($lulus_stmt);
$lulus_count = (int)mysqli_fetch_assoc($lulus_result)['total'];


$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Agen IT JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="agen-it.css">
</head>
<body class="agen-it page-dashboard">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-xl-2 sidebar p-4">
                <div class="brand-wrap">
                    <div class="brand-mark"><i class="bi bi-pc-display-horizontal"></i></div>
                    <div>
                        <h4 class="brand-title">JTDIS</h4>
                        <p class="brand-subtitle">Modul Aset ICT</p>
                    </div>
                </div>

                <div class="role-pill"><i class="bi bi-person-badge"></i> Agen IT</div>

                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                    <a class="nav-link" href="aset/index.php">
                        <i class="bi bi-box-seam-fill"></i> Aset
                    </a>
                    <a class="nav-link" href="/jdtis_asset/pages/import-aset/index.php">
                        <i class="bi bi-cloud-arrow-up"></i> Import Aset
                    </a>
                    <hr class="nav-divider">
                    <a class="nav-link" href="../logout.php">
                        <i class="bi bi-box-arrow-left"></i> Log Keluar
                    </a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <h1 class="page-title">Dashboard Agen IT</h1>
                        <p class="page-subtitle">Selamat datang, <strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></p>
                    </div>
                    <div class="page-chip"><i class="bi bi-building"></i><?php echo escapeOutput($user['nama_agensi'] ?? 'Tidak Ditugaskan'); ?></div>
                </div>

                <div class="alert hero-note mb-4">
                    <i class="bi bi-shield-check me-2"></i>
                    Anda hanya boleh mengurus aset yang didaftarkan di bawah akaun dan agensi anda.
                </div>

                <!-- User Info Card -->
                <div class="card profile-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Maklumat Penempatan</h5>
                                <p class="text-muted mb-0">Butiran akaun Agen IT yang sedang aktif.</p>
                            </div>
                            <span class="badge bg-primary">Agen IT</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 col-xl-3">
                                <div class="profile-item">
                                    <span class="profile-label">Nama</span>
                                    <div class="profile-value"><?php echo escapeOutput($user['nama_penuh']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-3">
                                <div class="profile-item">
                                    <span class="profile-label">Emel</span>
                                    <div class="profile-value"><?php echo escapeOutput($user['emel']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-2">
                                <div class="profile-item">
                                    <span class="profile-label">Daerah</span>
                                    <div class="profile-value"><?php echo escapeOutput($user['nama_daerah'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-2">
                                <div class="profile-item">
                                    <span class="profile-label">Wilayah</span>
                                    <div class="profile-value"><?php echo escapeOutput($user['nama_wilayah'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-2">
                                <div class="profile-item">
                                    <span class="profile-label">Agensi</span>
                                    <div class="profile-value"><?php echo escapeOutput($user['nama_agensi'] ?? '-'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card stat-blue">
                            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                            <div class="stat-value"><?php echo $aset_count; ?></div>
                            <div class="stat-label">Jumlah Aset</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card stat-orange">
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-value"><?php echo $pending_count; ?></div>
                            <div class="stat-label">Menunggu Pengesahan</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card stat-red">
                            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                            <div class="stat-value"><?php echo $rejected_count; ?></div>
                            <div class="stat-label">Ditolak</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="stat-card stat-green">
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-value"><?php echo $lulus_count; ?></div>
                            <div class="stat-label">Dalam Proses / Diluluskan</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-charge-fill me-2 text-primary"></i>Akses Pantas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="aset/index.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-hdd"></i> Lihat Semua Aset
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="aset/tambah.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-plus-circle"></i> Daftar Aset Baru
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="/jdtis_asset/pages/import-aset/index.php"
                                   class="btn btn-outline-success w-100">
                                    <i class="bi bi-cloud-arrow-up"></i> Import Aset
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="../logout.php" class="btn btn-outline-danger w-100">
                                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="alert hero-note mt-4">
                    <h6><i class="bi bi-info-circle"></i> Bantuan</h6>
                    <ul class="mb-0">
                        <li>Gunakan menu "Aset" untuk melihat senarai aset yang telah didaftarkan</li>
                        <li>Klik "Daftar Aset Baru" untuk menambah aset baru ke dalam sistem</li>
                        <li>Aset yang baru didaftarkan akan mempunyai status "Menunggu Pengesahan" sehingga diluluskan oleh PPTM/PTM</li>
                        <li>Hubungi pentadbir sistem sekiranya menghadapi masalah</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>