<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Wilayah - Lihat Aset Paparan Sahaja
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (isSessionExpired()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['Ketua Wilayah']);

$wilayah_id = (int) ($_SESSION['wilayah_id'] ?? 0);
$aset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($wilayah_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat wilayah pengguna tidak sah.';
    header('Location: dashboard.php');
    exit;
}

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$aset = null;
$log_rows = [];
$maint_rows = [];
$ralat_log = '';

function kwDetailWorkflowBadge(int $status_id): string
{
    return match ($status_id) {
        2 => 'bg-warning text-dark',
        3 => 'bg-danger',
        4 => 'bg-info text-dark',
        5 => 'bg-danger',
        6 => 'bg-primary',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
}

function kwDetailTimelineClass(string $tindakan): string
{
    if (stripos($tindakan, 'Lulus') !== false) {
        return 'timeline-success';
    }

    if (stripos($tindakan, 'Tolak') !== false) {
        return 'timeline-danger';
    }

    if (
        stripos($tindakan, 'Daftar') !== false
        || stripos($tindakan, 'Hantar') !== false
    ) {
        return 'timeline-primary';
    }

    if (stripos($tindakan, 'Semak') !== false) {
        return 'timeline-warning';
    }

    return 'timeline-secondary';
}

function kwDetailTimelineIcon(string $tindakan): string
{
    if (stripos($tindakan, 'Lulus') !== false) {
        return 'bi-check';
    }

    if (stripos($tindakan, 'Tolak') !== false) {
        return 'bi-x';
    }

    if (
        stripos($tindakan, 'Daftar') !== false
        || stripos($tindakan, 'Hantar') !== false
    ) {
        return 'bi-arrow-up';
    }

    if (stripos($tindakan, 'Semak') !== false) {
        return 'bi-clock';
    }

    return 'bi-gear';
}

try {
    /*
     * Perlindungan akses terus:
     * - mesti aset wilayah sendiri
     * - mesti status workflow 2 hingga 8
     */
    $sql_aset = "
        SELECT
            a.*,
            sw.status AS status_workflow,
            sa.status AS status_aset,
            ag.nama_agensi,
            d.nama_daerah,
            p.nama_penuh AS pendaftar
        FROM aset a
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa
            ON sa.status_aset_id = a.status_aset_id
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN daerah d
            ON d.daerah_id = ag.daerah_id
        LEFT JOIN pengguna p
            ON p.pengguna_id = a.pengguna_id_daftar
        WHERE a.aset_id = ?
          AND a.wilayah_id = ?
          AND a.status_workflow_id IN (2, 3, 4, 5, 6, 7, 8)
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);
    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param($stmt_aset, 'ii', $aset_id, $wilayah_id);

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $aset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_aset));
    mysqli_stmt_close($stmt_aset);
} catch (Throwable $e) {
    error_log('Lihat Aset Ketua Wilayah: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}

if (!$aset) {
    $_SESSION['flash_error'] = 'Aset tidak ditemui, bukan dalam wilayah anda, atau bukan dalam status yang dibenarkan.';
    header('Location: senarai_aset.php');
    exit;
}

try {
    $sql_log = "
        SELECT
            lw.tindakan,
            lw.catatan,
            lw.tarikh,
            lw.status_workflow_dari,
            lw.status_workflow_ke,
            u.nama_penuh
        FROM log_workflow lw
        LEFT JOIN pengguna u
            ON u.pengguna_id = lw.oleh_pengguna_id
        WHERE lw.aset_id = ?
        ORDER BY lw.tarikh DESC, lw.log_id DESC
    ";

    $stmt_log = mysqli_prepare($conn, $sql_log);
    if (!$stmt_log) {
        throw new RuntimeException('Gagal menyediakan query log workflow.');
    }

    mysqli_stmt_bind_param($stmt_log, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_log)) {
        throw new RuntimeException('Gagal mendapatkan log workflow.');
    }

    $log_rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt_log), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_log);

    $sql_maint = "
        SELECT
            ls.tarikh,
            ls.jenis_selenggara,
            ls.komponen_ditukar,
            ls.status_selepas,
            u.nama_penuh
        FROM log_selenggara ls
        LEFT JOIN pengguna u
            ON u.pengguna_id = ls.dibuat_oleh
        WHERE ls.aset_id = ?
        ORDER BY ls.tarikh DESC, ls.log_id DESC
    ";

    $stmt_maint = mysqli_prepare($conn, $sql_maint);
    if (!$stmt_maint) {
        throw new RuntimeException('Gagal menyediakan query log selenggara.');
    }

    mysqli_stmt_bind_param($stmt_maint, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_maint)) {
        throw new RuntimeException('Gagal mendapatkan log selenggara.');
    }

    $maint_rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt_maint), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_maint);
} catch (Throwable $e) {
    error_log('Log Ketua Wilayah: ' . $e->getMessage());
    $ralat_log = 'Sebahagian log tidak dapat dimuatkan.';
}

$nama_wilayah = getWilayahName($conn, $wilayah_id);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Aset Ketua Wilayah - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-wilayah.css">
</head>
<body class="ketua-wilayah page-lihat">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <h4 class="mb-1">
                <i class="bi bi-shield-check me-1"></i> JTDIS
            </h4>
            <p class="text-white-50 mb-4">Ketua Wilayah</p>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-hdd-stack me-2"></i> Senarai Aset
                </a>

                <hr class="w-100 sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 p-3 p-lg-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <a href="senarai_aset.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <h1 class="h2 fw-bold mb-1">Maklumat Aset</h1>
                    <p class="text-muted mb-0"><?php echo escapeOutput($nama_wilayah); ?></p>
                </div>

                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="bi bi-eye me-1"></i> Paparan sahaja
                </span>
            </div>

            <?php if ($ralat_log !== ''): ?>
                <div class="alert alert-warning"><?php echo escapeOutput($ralat_log); ?></div>
            <?php endif; ?>

            <div class="alert alert-primary d-flex gap-2 align-items-start" role="alert">
                <i class="bi bi-lock-fill mt-1"></i>
                <div>
                    Ketua Wilayah tidak boleh meluluskan, menolak, mengedit atau mengubah status workflow aset ini.
                </div>
            </div>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h2 class="h4 fw-bold mb-0">
                                    <?php echo escapeOutput($aset['no_pendaftaran']); ?>
                                </h2>
                                <span class="badge bg-secondary">
                                    <?php echo escapeOutput($aset['jenis_aset']); ?>
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge <?php echo kwDetailWorkflowBadge((int) $aset['status_workflow_id']); ?>">
                                    <?php echo escapeOutput($aset['status_workflow'] ?? '-'); ?>
                                </span>
                                <span class="badge bg-light text-dark border">
                                    Status Fizikal: <?php echo escapeOutput($aset['status_aset'] ?? '-'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Model</span>
                                <div class="detail-value"><?php echo escapeOutput($aset['model'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Jenama</span>
                                <div class="detail-value"><?php echo escapeOutput($aset['jenama'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Tahun Beli</span>
                                <div class="detail-value"><?php echo escapeOutput($aset['tahun_beli'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Agensi</span>
                                <div class="detail-value"><?php echo escapeOutput($aset['nama_agensi'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Wilayah</span>
                                <div class="detail-value"><?php echo escapeOutput($nama_wilayah); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <div class="detail-box">
                                <span class="detail-label">Pendaftar</span>
                                <div class="detail-value"><?php echo escapeOutput($aset['pendaftar'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <ul class="nav nav-pills mb-3" id="assetTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#maklumat" type="button">
                        Maklumat Penuh
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#workflow" type="button">
                        Log Workflow
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#selenggara" type="button">
                        Log Selenggara
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="maklumat">
                    <section class="card content-card mb-3">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Maklumat Aset</h2>
                            <div class="row g-3">
                                <div class="col-md-6"><strong>No. Pendaftaran:</strong> <?php echo escapeOutput($aset['no_pendaftaran']); ?></div>
                                <div class="col-md-6"><strong>Jenis:</strong> <?php echo escapeOutput($aset['jenis_aset']); ?></div>
                                <div class="col-md-6"><strong>Jenis Perolehan:</strong> <?php echo escapeOutput($aset['jenis_perolehan'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Tahun:</strong> <?php echo escapeOutput($aset['tahun_beli'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Jenama:</strong> <?php echo escapeOutput($aset['jenama'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Model:</strong> <?php echo escapeOutput($aset['model'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </section>

                    <?php if (in_array($aset['jenis_aset'], ['PC', 'NB'], true)): ?>
                        <section class="card content-card mb-3">
                            <div class="card-body">
                                <h2 class="h5 mb-3">Spesifikasi PC / Notebook</h2>
                                <div class="row g-3">
                                    <div class="col-md-6"><strong>Processor:</strong> <?php echo escapeOutput($aset['processor'] ?? '-'); ?></div>
                                    <div class="col-md-6"><strong>RAM:</strong> <?php echo escapeOutput($aset['ram'] ?? '-'); ?></div>
                                    <div class="col-md-6"><strong>Cakera Keras:</strong> <?php echo escapeOutput($aset['cakera_keras'] ?? '-'); ?></div>
                                    <div class="col-md-6"><strong>Sistem Operasi:</strong> <?php echo escapeOutput($aset['sistem_operasi'] ?? '-'); ?></div>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (($aset['jenis_aset'] ?? '') === 'Pencetak'): ?>
                        <section class="card content-card mb-3">
                            <div class="card-body">
                                <h2 class="h5 mb-3">Maklumat Pencetak</h2>
                                <div class="row g-3">
                                    <div class="col-md-6"><strong>Jenis Pencetak:</strong> <?php echo escapeOutput($aset['jenis_pencetak'] ?? '-'); ?></div>
                                    <div class="col-md-6"><strong>No. Siri:</strong> <?php echo escapeOutput($aset['no_siri_pencetak'] ?? '-'); ?></div>
                                    <div class="col-12"><strong>Spesifikasi:</strong> <?php echo escapeOutput($aset['spesifikasi_pencetak'] ?? '-'); ?></div>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="card content-card mb-3">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Maklumat Pegawai</h2>
                            <div class="row g-3">
                                <div class="col-md-6"><strong>Nama:</strong> <?php echo escapeOutput($aset['pegawai_nama'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Jawatan:</strong> <?php echo escapeOutput($aset['pegawai_jawatan'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Gred:</strong> <?php echo escapeOutput($aset['pegawai_gred'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Daerah:</strong> <?php echo escapeOutput($aset['nama_daerah'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($aset['catatan'])): ?>
                        <section class="card content-card">
                            <div class="card-body">
                                <h2 class="h5 mb-3">Catatan</h2>
                                <p class="mb-0"><?php echo nl2br(escapeOutput($aset['catatan'])); ?></p>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade" id="workflow">
                    <section class="card content-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Log Workflow</h2>

                            <?php if ($log_rows === []): ?>
                                <div class="text-center text-muted py-5">Tiada rekod workflow.</div>
                            <?php else: ?>
                                <?php foreach ($log_rows as $log): ?>
                                    <div class="timeline-item">
                                        <div
                                            class="circle-icon <?php echo kwDetailTimelineClass((string) ($log['tindakan'] ?? '')); ?>"
                                        >
                                            <i class="bi <?php echo kwDetailTimelineIcon((string) ($log['tindakan'] ?? '')); ?>"></i>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="fw-semibold">
                                                    <?php echo escapeOutput($log['tindakan'] ?? '-'); ?>
                                                </div>
                                                <div class="small text-muted text-nowrap">
                                                    <?php
                                                    echo !empty($log['tarikh'])
                                                        ? escapeOutput(date('d/m/Y H:i', strtotime($log['tarikh'])))
                                                        : '-';
                                                    ?>
                                                </div>
                                            </div>

                                            <div class="small text-muted">
                                                Oleh: <?php echo escapeOutput($log['nama_penuh'] ?? '-'); ?>
                                            </div>

                                            <?php if (!empty($log['catatan'])): ?>
                                                <div class="small text-muted mt-1">
                                                    <?php echo nl2br(escapeOutput($log['catatan'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="tab-pane fade" id="selenggara">
                    <section class="card content-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Log Selenggara</h2>

                            <?php if ($maint_rows === []): ?>
                                <div class="text-center text-muted py-5">Tiada rekod penyelenggaraan.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Tarikh</th>
                                            <th>Jenis</th>
                                            <th>Komponen Ditukar</th>
                                            <th>Status Selepas</th>
                                            <th>Oleh</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($maint_rows as $row): ?>
                                            <tr>
                                                <td class="text-nowrap">
                                                    <?php
                                                    echo !empty($row['tarikh'])
                                                        ? escapeOutput(date('d/m/Y H:i', strtotime($row['tarikh'])))
                                                        : '-';
                                                    ?>
                                                </td>
                                                <td><?php echo escapeOutput($row['jenis_selenggara'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($row['komponen_ditukar'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($row['status_selepas'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($row['nama_penuh'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>