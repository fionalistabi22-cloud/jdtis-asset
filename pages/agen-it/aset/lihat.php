<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit;
}

if ($_SESSION['peranan'] !== 'Agen IT') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$pengguna_id = $_SESSION['pengguna_id'];
$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;

if ($aset_id === 0) {
    header("Location: index.php");
    exit;
}

$aset_query = "SELECT a.*, s.status, s.warna, ag.nama_agensi, d.nama_daerah
               FROM aset a
               LEFT JOIN status_aset s ON a.status_aset_id = s.status_aset_id
               LEFT JOIN agensi ag ON a.agensi_id = ag.agensi_id
               LEFT JOIN daerah d ON ag.daerah_id = d.daerah_id
               WHERE a.aset_id = ? AND a.pengguna_id_daftar = ?";
$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "ii", $aset_id, $pengguna_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);

if (!$aset) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Aset - Agen IT JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../agen-it.css">
</head>
<body class="agen-it page-aset-lihat">
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
                    <a class="nav-link" href="../dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-box-seam-fill"></i> Aset
                    </a>
                    <hr class="nav-divider">
                    <a class="nav-link" href="../../logout.php">
                        <i class="bi bi-box-arrow-left"></i> Log Keluar
                    </a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary mb-3">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <h1 class="page-title">Maklumat Aset</h1>
                        <p class="page-subtitle">Paparan terperinci aset dan status semasa.</p>
                    </div>
                    <div class="page-chip"><i class="bi bi-eye"></i> Agen IT</div>
                </div>

                <div class="card">
                    <div class="card-body p-4">
                        <!-- Header -->
                        <div class="row mb-4 pb-3 border-bottom align-items-center">
                            <div class="col-md-8">
                                <h4><?php echo escapeOutput($aset['no_pendaftaran']); ?></h4>
                                <p class="text-muted mb-0">
                                    <span class="badge bg-<?php echo escapeOutput($aset['warna']); ?>">
                                        <?php echo escapeOutput($aset['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="edit.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="hapus.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Adakah anda pasti ingin memadamkan aset ini?');">
                                    <i class="bi bi-trash"></i> Hapus
                                </a>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <span class="detail-label">Jenis Aset:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenis_aset']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Jenama:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['jenama'] ?? '-'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Model:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['model'] ?? '-'); ?></span>
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
                                    <span class="detail-value"><?php echo escapeOutput($aset['nama_daerah'] ?? '-'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Agensi:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['nama_agensi'] ?? '-'); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Tarikh Pendaftaran:</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($aset['tarikh_input'])); ?></span>
                                </div>
                                <div class="mb-3">
                                    <span class="detail-label">Status Aset:</span>
                                    <span class="detail-value">
                                        <span class="badge bg-<?php echo escapeOutput($aset['warna']); ?>">
                                            <?php echo escapeOutput($aset['status']); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Officer Information -->
                        <?php if (!empty($aset['pegawai_nama'])): ?>
                        <div class="row detail-section">
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
                                    <span class="detail-value"><?php echo escapeOutput($aset['pegawai_jawatan'] ?? '-'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Gred:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['pegawai_gred'] ?? '-'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PC/NB Specifications -->
                        <?php if (in_array($aset['jenis_aset'], ['PC', 'NB'])): ?>
                        <div class="row detail-section">
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
                                    <span class="detail-label">Storan (Cakera Keras):</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['cakera_keras'] ?? '-'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <span class="detail-label">Sistem Operasi:</span>
                                    <span class="detail-value"><?php echo escapeOutput($aset['sistem_operasi'] ?? '-'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Printer Specifications -->
                        <?php if ($aset['jenis_aset'] === 'Pencetak'): ?>
                        <div class="row detail-section">
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
                        <div class="row detail-section">
                            <h5 class="mb-3"><i class="bi bi-file-text"></i> Catatan</h5>
                            <p class="detail-value"><?php echo nl2br(escapeOutput($aset['catatan'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Disposal Information -->
                        <?php if (!empty($aset['maklumat_pelupusan_aset'])): ?>
                        <div class="row detail-section">
                            <h5 class="mb-3"><i class="bi bi-trash"></i> Maklumat Pelupusan Aset</h5>
                            <p class="detail-value"><?php echo nl2br(escapeOutput($aset['maklumat_pelupusan_aset'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="mt-4 pt-3 border-top">
                            <a href="edit.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Edit Aset
                            </a>

                            <?php if (isset($aset['status_workflow_id']) && intval($aset['status_workflow_id']) === 3): ?>
                                <form method="POST" action="hantar_semula.php" class="inline-form">
                                    <input type="hidden" name="aset_id" value="<?php echo (int)$aset['aset_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Hantar semula untuk pengesahan?');">
                                        <i class="bi bi-arrow-up-circle"></i> Hantar Semula
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="hapus.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-danger" onclick="return confirm('Adakah anda pasti ingin memadamkan aset ini?');">
                                <i class="bi bi-trash"></i> Hapus Aset
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>