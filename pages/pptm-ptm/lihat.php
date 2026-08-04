<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Only PPTM/PTM can access this
if (!in_array($_SESSION['peranan'], ['PPTM', 'PTM'])) {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

// Get asset ID from URL
$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;

if ($aset_id === 0) {
    header("Location: pengesahan.php");
    exit;
}

// Fetch asset details
// FIX: turut JOIN status_workflow supaya kita boleh papar & semak status
// peringkat kelulusan sebenar (bukan hanya status_aset yang tak berkaitan
// dengan kelulusan — status_aset sentiasa "Aktif" secara default walaupun
// aset masih Menunggu PPTM).
$aset_query = "SELECT a.*,
               sa.status AS status_aset, sa.warna AS warna_aset,
               sw.status AS status_workflow, sw.warna AS warna_workflow,
               ag.nama_agensi, d.nama_daerah, p.nama_penuh as pendaftar
               FROM aset a
               LEFT JOIN status_aset sa ON a.status_aset_id = sa.status_aset_id
               LEFT JOIN status_workflow sw ON a.status_workflow_id = sw.status_workflow_id
               LEFT JOIN agensi ag ON a.agensi_id = ag.agensi_id
               LEFT JOIN daerah d ON ag.daerah_id = d.daerah_id
               LEFT JOIN pengguna p ON a.pengguna_id_daftar = p.pengguna_id
               WHERE a.aset_id = ?";
$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "i", $aset_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);

if (!$aset) {
    header("Location: pengesahan.php");
    exit;
}

// FIX: kelayakan Luluskan/Tolak ditentukan oleh status_workflow_id == 2
// (Menunggu PPTM), BUKAN status_aset_id == 1 (Aktif). status_aset_id
// ialah status fizikal aset dan hampir selalu "Aktif" secara default,
// jadi menyemak nilai itu akan memaparkan butang untuk hampir semua aset
// tanpa mengira peringkat kelulusan sebenar.
$boleh_disemak = ((int)($aset['status_workflow_id'] ?? 0) === 2);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Aset - PPTM/PTM JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pptm-ptm.css">

</head>
<body class="pptm-ptm page-lihat">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="brand-wrap">
                <div class="brand-mark"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h4 class="brand-title">JTDIS</h4>
                    <p class="brand-subtitle">Pengesahan Aset ICT</p>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-check"></i>
                <?php echo escapeOutput($_SESSION['peranan']); ?>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link " href="dashboard.php">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>

                <a class="nav-link active" href="pengesahan.php">
                    <i class="bi bi-clipboard-check"></i> Pengesahan Aset
                    
                </a>

                <hr class="nav-separator sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
            <main class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <a href="pengesahan.php" class="btn btn-outline-secondary mb-3">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Senarai
                        </a>
                        <h1 class="page-title">Maklumat Aset</h1>
                        <p class="page-subtitle">Semakan terperinci sebelum keputusan pengesahan dibuat.</p>
                    </div>

                    <div class="page-chip">
                        <i class="bi bi-clipboard-check"></i>
                        Semakan PPTM/PTM
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-4">
                        <!-- Header -->
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-md-8">
                                <h4><?php echo escapeOutput($aset['no_pendaftaran']); ?></h4>
                                <p class="text-muted mb-0">
                                    <span class="badge <?php $warna_status = strtolower((string) ($aset['warna_workflow'] ?? 'secondary')); $kelas_warna = in_array($warna_status, ['secondary','success','danger','warning','info','primary','dark'], true) ? 'status-color-' . $warna_status : 'status-color-secondary'; echo $kelas_warna; ?>">
                                        <?php echo escapeOutput($aset['status_workflow'] ?? '-'); ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        Status Fizikal: <?php echo escapeOutput($aset['status_aset'] ?? '-'); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($boleh_disemak): ?>
                                <a href="setujui.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-circle"></i> Luluskan
                                </a>
                                <a href="tolak.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-danger btn-sm">
                                    <i class="bi bi-x-circle"></i> Tolak
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Pendaftar Information -->
                        <div class="row soft-section">
                            <h5 class="mb-3"><i class="bi bi-person"></i> Maklumat Pendaftar</h5>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Nama Pendaftar:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['pendaftar']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Tarikh Pendaftaran:</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($aset['tarikh_input'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="detail-label">Jenis Aset:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenis_aset']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Jenama:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenama']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Model:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['model']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Jenis Perolehan:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenis_perolehan']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Tahun Beli:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['tahun_beli'] ?? 'Tidak dinyatakan'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="detail-label">Daerah:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['nama_daerah']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Agensi:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['nama_agensi']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Officer Information -->
                        <?php if (!empty($aset['pegawai_nama'])): ?>
                        <div class="row soft-section">
                            <h5 class="mb-3"><i class="bi bi-person"></i> Maklumat Pegawai</h5>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Nama Pegawai:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['pegawai_nama']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Jawatan:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['pegawai_jawatan']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Gred:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['pegawai_gred']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PC/NB Specifications -->
                        <?php if (in_array($aset['jenis_aset'], ['PC', 'NB'])): ?>
                        <div class="row soft-section">
                            <h5 class="mb-3"><i class="bi bi-laptop"></i> Spesifikasi Komputer</h5>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Processor:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['processor'] ?? '-'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">RAM:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['ram'] ?? '-'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Cakera Keras:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['cakera_keras'] ?? '-'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Printer Specifications -->
                        <?php if ($aset['jenis_aset'] === 'Pencetak'): ?>
                        <div class="row soft-section">
                            <h5 class="mb-3"><i class="bi bi-printer"></i> Spesifikasi Pencetak</h5>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Jenis Pencetak:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenis_pencetak'] ?? 'Tiada'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">No. Siri Pencetak:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['no_siri_pencetak'] ?? '-'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Additional Notes -->
                        <?php if (!empty($aset['catatan'])): ?>
                        <div class="row soft-section">
                            <h5 class="mb-3"><i class="bi bi-file-text"></i> Catatan</h5>
                            <p class="detail-value"><?php echo nl2br(escapeOutput($aset['catatan'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                            <?php if ($boleh_disemak): ?>
                            <a href="setujui.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle"></i> Luluskan Aset
                            </a>
                            <a href="tolak.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-danger btn-lg">
                                <i class="bi bi-x-circle"></i> Tolak Aset
                            </a>
                            <?php endif; ?>
                            <a href="pengesahan.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>