<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRoleWhitelist(['PPTM', 'PTM']);

// ================================================================
// PEMBETULAN: Guna wilayah_id dari session untuk skop data
// ================================================================
$wilayah_id  = (int)($_SESSION['wilayah_id'] ?? 0);
$pengguna_id = (int)($_SESSION['pengguna_id'] ?? 0);

$filter = strtolower(trim((string)($_GET['filter'] ?? 'pending')));
$filter_dibenarkan = ['pending', 'approved', 'rejected', 'all'];

if (!in_array($filter, $filter_dibenarkan, true)) {
    $filter = 'pending';
}

/*
 * Pending  : status 2 sahaja — perlu tindakan PPTM/PTM.
 * Approved : status 4 hingga 8 — aset sudah melepasi peringkat PPTM.
 * Rejected : status 3 sahaja — ditolak PPTM.
 * All      : semua status 2 hingga 8 dalam wilayah.
 */
if ($filter === 'approved') {
    $status_condition = 'a.status_workflow_id IN (4, 5, 6, 7, 8)';
    $title = 'Aset Yang Melepasi Pengesahan PPTM';
    $badge_class = 'success';
} elseif ($filter === 'rejected') {
    $status_condition = 'a.status_workflow_id = 3';
    $title = 'Aset Ditolak PPTM';
    $badge_class = 'danger';
} elseif ($filter === 'all') {
    $status_condition = 'a.status_workflow_id IN (2, 3, 4, 5, 6, 7, 8)';
    $title = 'Semua Aset Dalam Wilayah';
    $badge_class = 'primary';
} else {
    $status_condition = 'a.status_workflow_id = 2';
    $title = 'Aset Menunggu Pengesahan PPTM';
    $badge_class = 'warning';
}

$aset_query = "SELECT 
    a.aset_id,
    a.no_pendaftaran,
    a.jenis_aset,
    a.jenama,
    a.model,
    a.tahun_beli,
    a.tarikh_input,
    a.pegawai_nama,
    a.status_workflow_id,
    sw.status      AS nama_status_workflow,
    sw.warna       AS warna_workflow,
    sa.status      AS nama_status_aset,
    p.nama_penuh   AS pendaftar,
    ag.nama_agensi,
    -- Ambil nota tolak terbaru jika ada
    (SELECT lw.catatan 
     FROM log_workflow lw 
     WHERE lw.aset_id = a.aset_id 
       AND lw.tindakan IN ('Tolak PPTM','Tolak Wilayah','Tolak Bahagian')
     ORDER BY lw.tarikh DESC LIMIT 1
    ) AS nota_tolak_terkini
FROM aset a
LEFT JOIN pengguna p       ON a.pengguna_id_daftar = p.pengguna_id
LEFT JOIN agensi ag        ON a.agensi_id = ag.agensi_id
LEFT JOIN status_workflow sw ON a.status_workflow_id = sw.status_workflow_id
LEFT JOIN status_aset sa   ON a.status_aset_id = sa.status_aset_id
WHERE {$status_condition}
  AND a.wilayah_id = ?
ORDER BY a.tarikh_input ASC";

$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "i", $wilayah_id);
mysqli_stmt_execute($aset_stmt);
$aset_list = mysqli_fetch_all(mysqli_stmt_get_result($aset_stmt), MYSQLI_ASSOC);

// Kiraan untuk badge dalam tab
$count_query = "SELECT
    SUM(CASE WHEN status_workflow_id = 2 THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status_workflow_id = 3 THEN 1 ELSE 0 END) AS rejected,
    SUM(CASE WHEN status_workflow_id IN (4, 5, 6, 7, 8) THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status_workflow_id IN (2, 3, 4, 5, 6, 7, 8) THEN 1 ELSE 0 END) AS total
FROM aset
WHERE wilayah_id = ?";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, "i", $wilayah_id);
mysqli_stmt_execute($count_stmt);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeOutput($title); ?> - PPTM JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pptm-ptm.css">

</head>
<body class="pptm-ptm page-pengesahan">
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
                    <?php if (($counts['pending'] ?? 0) > 0): ?>
                            <span class="badge bg-warning text-dark"><?php echo (int)$counts['pending']; ?></span>
                        <?php endif; ?>
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
                    <a href="dashboard.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <h1 class="page-title"><?php echo escapeOutput($title); ?></h1>
                    <p class="page-subtitle">Senarai permohonan aset dalam skop wilayah anda.</p>
                </div>

                <div class="page-chip">
                    <i class="bi bi-funnel"></i>
                    <?php echo ucfirst(escapeOutput($filter)); ?>
                </div>
            </div>

            <!-- Flash messages -->
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo escapeOutput($_SESSION['flash_success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($_SESSION['flash_error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <!-- FILTER TAB -->
            <div class="filter-tabs" role="group">
                <a href="pengesahan.php"
                   class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="bi bi-hourglass-split"></i> Menunggu
                    <?php if (($counts['pending'] ?? 0) > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo (int)$counts['pending']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="pengesahan.php?filter=approved"
                   class="btn btn-outline-success <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle"></i> Diluluskan
                    <?php if (($counts['approved'] ?? 0) > 0): ?>
                        <span class="badge bg-success"><?php echo (int)$counts['approved']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="pengesahan.php?filter=rejected"
                   class="btn btn-outline-danger <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="bi bi-x-circle"></i> Ditolak
                    <?php if (($counts['rejected'] ?? 0) > 0): ?>
                        <span class="badge bg-danger"><?php echo (int)$counts['rejected']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="pengesahan.php?filter=all"
                   class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-list-ul"></i> Semua Aset
                    <?php if (($counts['total'] ?? 0) > 0): ?>
                        <span class="badge bg-primary"><?php echo (int)$counts['total']; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- ASSET TABLE -->
            <?php if (!empty($aset_list)): ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Jenama / Model</th>
                                <th>Pendaftar</th>
                                <th>Agensi</th>
                                <th>Tarikh Daftar</th>
                                <th>Status Workflow</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aset_list as $aset): ?>
                            <tr>
                                <td><strong><?php echo escapeOutput($aset['no_pendaftaran']); ?></strong></td>
                                <td><?php echo escapeOutput($aset['jenis_aset']); ?></td>
                                <td>
                                    <?php echo escapeOutput($aset['jenama'] ?? '-'); ?>
                                    <?php if (!empty($aset['model'])): ?>
                                        / <?php echo escapeOutput($aset['model']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escapeOutput($aset['pendaftar'] ?? '-'); ?></td>
                                <td><?php echo escapeOutput($aset['nama_agensi'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($aset['tarikh_input'])); ?></td>
                                <td>
                                    <!--
                                        PEMBETULAN: papar status_workflow, bukan status_aset
                                    -->
                                    <span class="badge bg-<?php
                                        $wid = (int)$aset['status_workflow_id'];
                                        echo match($wid) {
                                            1 => 'secondary',
                                            2 => 'warning text-dark',
                                            3 => 'danger',
                                            4 => 'info text-dark',
                                            5 => 'danger',
                                            6 => 'primary',
                                            7 => 'danger',
                                            8 => 'success',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo escapeOutput($aset['nama_status_workflow'] ?? '-'); ?>
                                    </span>
                                    <?php if (!empty($aset['nota_tolak_terkini'])): ?>
                                        <br>
                                        <small class="text-danger">
                                            <i class="bi bi-info-circle"></i>
                                            <?php echo escapeOutput($aset['nota_tolak_terkini']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Butang lihat detail -->
                                        <a href="lihat.php?id=<?php echo (int)$aset['aset_id']; ?>"
                                           class="btn btn-sm btn-info" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <!-- Butang lulus & tolak — hanya bila status = Menunggu PPTM (2) -->
                                        <?php if ((int)$aset['status_workflow_id'] === 2): ?>
                                            <a href="setujui.php?id=<?php echo (int)$aset['aset_id']; ?>"
                                               class="btn btn-sm btn-success" title="Luluskan"
                                               onclick="return confirm('Luluskan aset <?php echo escapeOutput($aset['no_pendaftaran']); ?>?')">
                                                <i class="bi bi-check-circle"></i>
                                            </a>
                                            <a href="tolak.php?id=<?php echo (int)$aset['aset_id']; ?>"
                                               class="btn btn-sm btn-danger" title="Tolak">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: ?>
            <div class="card empty-state">
                <i class="bi bi-inbox empty-state-icon"></i>
                <p class="mt-3 mb-0">Tiada aset <?php echo strtolower(escapeOutput($title)); ?> pada masa ini.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>