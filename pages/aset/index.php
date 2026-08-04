<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page (centralized guard: login + session expiry)
requireRoleWhitelist(['Juruteknik', 'Agen IT']);


$pengguna_id = $_SESSION['pengguna_id'];

// Flash messages (STAKEHOLDER UI)
$error = '';
$success = '';
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Get assets registered by this Juruteknik/Agen IT
$aset_query = "SELECT 
                a.aset_id,
                a.no_pendaftaran,
                a.jenis_aset,
                a.jenama,
                a.model,
                a.tahun_beli,
                a.jenis_perolehan,
                a.no_siri_pencetak,
                a.tarikh_input,
                a.processor,
                a.ram,
                a.cakera_keras,
                a.spesifikasi_pencetak,
                a.jenis_pencetak,
                a.pegawai_nama,

                -- Workflow status
                sw.status AS nama_status_workflow,
                sw.warna AS warna_workflow,
                a.status_workflow_id,

                -- Latest rejection note
                (
                    SELECT lw.catatan
                    FROM log_workflow lw
                    WHERE lw.aset_id = a.aset_id
                      AND lw.tindakan IN ('Tolak PPTM','Tolak Wilayah','Tolak Bahagian')
                    ORDER BY lw.tarikh DESC
                    LIMIT 1
                ) AS nota_tolak_terkini
              FROM aset a
              JOIN status_workflow sw ON a.status_workflow_id = sw.status_workflow_id
              WHERE a.pengguna_id_daftar = ?
              ORDER BY a.tarikh_input DESC";

$aset_stmt = mysqli_prepare($conn, $aset_query);
if (!$aset_stmt) {
    die("Query prepare error: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($aset_stmt, "i", $pengguna_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset_list = mysqli_fetch_all($aset_result, MYSQLI_ASSOC);

// Get agency info
$agensi_query = "SELECT pengguna.agensi_id, agensi.nama_agensi, daerah.nama_daerah 
                FROM pengguna 
                LEFT JOIN agensi ON pengguna.agensi_id = agensi.agensi_id
                LEFT JOIN daerah ON agensi.daerah_id = daerah.daerah_id
                WHERE pengguna.pengguna_id = ?";
$agensi_stmt = mysqli_prepare($conn, $agensi_query);
mysqli_stmt_bind_param($agensi_stmt, "i", $pengguna_id);
mysqli_stmt_execute($agensi_stmt);
$agensi_result = mysqli_stmt_get_result($agensi_stmt);
$user_agensi = mysqli_fetch_assoc($agensi_result);

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aset - Juruteknik JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="aset.css">

</head>
<body class="page-index">
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
                <div class="role-pill"><i class="bi bi-person-badge"></i> Juruteknik </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-hdd"></i> Aset</a>
                    <hr class="nav-separator">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <h1 class="page-title">Senarai Aset</h1>
                        <p class="page-subtitle">
                            Agensi: <strong><?php echo escapeOutput($user_agensi['nama_agensi'] ?? 'Tidak Ditugaskan'); ?></strong>
                            <?php if ($user_agensi['nama_daerah']): ?>
                                | Daerah: <strong><?php echo escapeOutput($user_agensi['nama_daerah']); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="tambah.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Daftar Aset Baru
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo escapeOutput($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted small text-uppercase fw-bold">Jumlah Aset</h5>
                                <p class="card-text display-4 text-primary"><?php echo count($aset_list); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted small text-uppercase fw-bold">Menunggu Pengesahan</h5>
                                <p class="card-text display-4 text-warning">
                                    <?php 
                                    $pending = count(array_filter($aset_list, function($a) { return intval($a['status_workflow_id'] ?? 0) === 2; }));
                                    echo $pending;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted small text-uppercase fw-bold">Dalam Proses / Diluluskan</h5>
                                <p class="card-text display-4 text-success">
                                    <?php
                                    $dalam_proses_lulus = count(array_filter(
                                        $aset_list,
                                        function ($a) {
                                            return in_array(
                                                intval($a['status_workflow_id'] ?? 0),
                                                [4, 8],
                                                true
                                            );
                                        }
                                    ));
                                    echo $dalam_proses_lulus;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h5 class="card-title text-muted small text-uppercase fw-bold">Ditolak</h5>
                                <p class="card-text display-4 text-danger">
                                    <?php
                                    $ditolak = count(array_filter(
                                        $aset_list,
                                        function ($a) {
                                            return in_array(
                                                intval($a['status_workflow_id'] ?? 0),
                                                [3, 5],
                                                true
                                            );
                                        }
                                    ));
                                    echo $ditolak;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Asset List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-bold">Senarai Aset Terdaftar</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($aset_list)): ?>
                            <div class="empty-state">
                                <i class="bi bi-inbox" ></i>
                                <p class="mt-2">Tiada aset terdaftar</p>
                                <a href="tambah.php" class="btn btn-primary">Daftar Aset Sekarang</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No. Pendaftaran</th>
                                            <th>Jenis Aset</th>
                                            <th>Jenama / Model</th>
                                            <th>Spec. (MB/CPU/RAM)</th>
                                            <th>Pegawai</th>
                                            <th>Tahun Beli</th>
                                            <th>Status</th>
                                            <th>Tarikh Daftar</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($aset_list as $aset): ?>
                                            <tr>
                                                <td><strong><?php echo escapeOutput($aset['no_pendaftaran']); ?></strong></td>
                                                <td><?php echo escapeOutput($aset['jenis_aset']); ?></td>
                                                <td>
                                                    <?php echo escapeOutput($aset['jenama'] ?? '-'); ?> 
                                                    / 
                                                    <?php echo escapeOutput($aset['model'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Paparkan maklumat aset ringkas (aligned with DB columns)
                                                    $perolehan = $aset['jenis_perolehan'] ?? '';
                                                    $noSiri = $aset['no_siri_pencetak'] ?? '';

                                                    $perolehanHtml = $perolehan !== '' ? '<div><small class="text-muted">Jenis Perolehan:</small> <strong>' . escapeOutput($perolehan) . '</strong></div>' : '';
                                                    $noSiriHtml = $noSiri !== '' ? '<div><small class="text-muted">No. Siri:</small> <strong>' . escapeOutput($noSiri) . '</strong></div>' : '';

                                                    $specs = '';
                                                    if (!empty($aset['processor'])) $specs .= '<strong>CPU:</strong> ' . escapeOutput($aset['processor']) . ' ';
                                                    if (!empty($aset['ram'])) $specs .= '<strong>RAM:</strong> ' . escapeOutput($aset['ram']) . ' ';
                                                    if (!empty($aset['cakera_keras'])) $specs .= '<strong>Storage:</strong> ' . escapeOutput($aset['cakera_keras']) . ' ';

                                                    $specs = trim($specs);
                                                    $specsHtml = $specs !== '' ? '<div>' . $specs . '</div>' : '<div><small class="text-muted">-</small></div>';

                                                    echo $perolehanHtml . $noSiriHtml . $specsHtml;
                                                    ?>
                                                </td>

                                                <td><?php echo escapeOutput($aset['pegawai_nama'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($aset['tahun_beli'] ?? '-'); ?></td>
                                                <td>
                                                    <?php
                                                    // Badge warna berdasarkan status_workflow_id
                                                    $status_workflow_id = intval($aset['status_workflow_id'] ?? 0);

                                                    $badge_map = [
                                                        1 => ['label' => 'Draf', 'class' => 'bg-secondary'],
                                                        2 => ['label' => 'Menunggu PPTM', 'class' => 'bg-warning text-dark'],
                                                        3 => ['label' => 'Ditolak PPTM', 'class' => 'bg-danger'],
                                                        4 => ['label' => 'Menunggu KW', 'class' => 'bg-info text-dark'],
                                                        5 => ['label' => 'Ditolak KW', 'class' => 'bg-danger'],
                                                        6 => ['label' => 'Menunggu KB', 'class' => 'bg-primary'],
                                                        7 => ['label' => 'Ditolak KB', 'class' => 'bg-danger'],
                                                        8 => ['label' => 'Lulus', 'class' => 'bg-success'],
                                                    ];

                                                    $badge = $badge_map[$status_workflow_id] ?? ['label' => ($aset['nama_status_workflow'] ?? '-'), 'class' => 'bg-secondary'];

                                                    echo '<span class="badge badge-status ' . escapeOutput($badge['class']) . '">' . escapeOutput($badge['label']) . '</span>';

                                                    $nota_tolak = trim((string)($aset['nota_tolak_terkini'] ?? ''));
                                                    if ($nota_tolak !== '') {
                                                        echo '<div class="mt-2"><small class="text-muted">' . escapeOutput($nota_tolak) . '</small></div>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($aset['tarikh_input'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="lihat.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-sm btn-info" title="Lihat Detail">
                                                            <i class="bi bi-eye"></i>
                                                        </a>

                                                        <?php
                                                        $status_workflow_id = intval($aset['status_workflow_id'] ?? 0);
                                                        ?>

                                                        <?php if (in_array($status_workflow_id, [1,3,5,7], true)): ?>
                                                            <a href="edit.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-sm btn-warning" title="Edit Aset">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (in_array($status_workflow_id, [1,3], true)): ?>
                                                            <form method="POST" action="hantar_semula.php" style="display:inline;">
                                                                <input type="hidden" name="aset_id" value="<?php echo intval($aset['aset_id']); ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                                <button type="submit" class="btn btn-sm btn-primary"
                                                                        onclick="return confirm('Hantar aset <?php echo escapeOutput($aset['no_pendaftaran']); ?> ini untuk pengesahan PPTM?');">
                                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                                    Hantar Semula
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <a href="hapus.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-sm btn-danger" title="Hapus Aset" onclick="return confirm('Adakah anda pasti ingin memadamkan aset ini?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>