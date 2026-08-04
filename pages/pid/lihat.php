<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Lihat Aset
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['PID']);

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$agensi_id = (int) ($_SESSION['agensi_id'] ?? 0);
$aset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$flash_success = (string) ($_SESSION['flash_success'] ?? '');
$flash_error = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$aset = null;
$workflow_logs = [];
$maintenance_logs = [];
$ralat_log = '';
$csrf_token = generateCSRFToken();

function pidWorkflowBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-secondary',
        6 => 'bg-warning text-dark',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
}

function pidStatusAsetBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-success',
        2 => 'bg-danger',
        3 => 'bg-warning text-dark',
        4 => 'bg-dark',
        5 => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function pidFormatTarikh($nilai): string
{
    if (empty($nilai)) {
        return '-';
    }

    $masa = strtotime((string) $nilai);

    return $masa !== false ? date('d/m/Y H:i', $masa) : '-';
}

function pidTimelineClass(string $tindakan): string
{
    if (stripos($tindakan, 'Lulus') !== false) {
        return 'timeline-success';
    }

    if (stripos($tindakan, 'Tolak') !== false) {
        return 'timeline-danger';
    }

    if (
        stripos($tindakan, 'Daftar') !== false
        || stripos($tindakan, 'Semak') !== false
        || stripos($tindakan, 'Hantar') !== false
    ) {
        return 'timeline-primary';
    }

    return 'timeline-secondary';
}

function pidTimelineIcon(string $tindakan): string
{
    if (stripos($tindakan, 'Lulus') !== false) {
        return 'bi-check-lg';
    }

    if (stripos($tindakan, 'Tolak') !== false) {
        return 'bi-x-lg';
    }

    if (
        stripos($tindakan, 'Daftar') !== false
        || stripos($tindakan, 'Semak') !== false
        || stripos($tindakan, 'Hantar') !== false
    ) {
        return 'bi-arrow-up';
    }

    return 'bi-gear';
}

/*
 * Skop keselamatan wajib:
 * - aset mesti milik agensi PID sendiri
 * - wilayah_id mesti 1
 */
try {
    $sql_aset = "
        SELECT
            a.*,
            ag.nama_agensi,
            sw.status AS status_workflow,
            sa.status AS status_aset,
            p.nama_penuh AS pendaftar,
            ps.nama_penuh AS pengguna_semasa,
            (
                SELECT lw.catatan
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.tindakan = 'Tolak Bahagian'
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS nota_tolak_terkini
        FROM aset a
        INNER JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa
            ON sa.status_aset_id = a.status_aset_id
        LEFT JOIN pengguna p
            ON p.pengguna_id = a.pengguna_id_daftar
        LEFT JOIN pengguna ps
            ON ps.pengguna_id = a.pengguna_semasa_id
        WHERE a.aset_id = ?
          AND a.agensi_id = ?
          AND a.wilayah_id = 1
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);

    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param($stmt_aset, 'ii', $aset_id, $agensi_id);

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $aset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_aset));
    mysqli_stmt_close($stmt_aset);

    if (!$aset) {
        $_SESSION['flash_error'] =
            'Aset tidak ditemui atau anda tidak mempunyai akses kepada aset tersebut.';
        header('Location: senarai_aset.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('Lihat Aset PID - Fetch: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}

try {
    $sql_workflow = "
        SELECT
            lw.log_id,
            lw.tindakan,
            lw.status_workflow_dari,
            lw.status_workflow_ke,
            lw.catatan,
            lw.tarikh,
            p.nama_penuh
        FROM log_workflow lw
        LEFT JOIN pengguna p
            ON p.pengguna_id = lw.oleh_pengguna_id
        WHERE lw.aset_id = ?
        ORDER BY lw.tarikh DESC, lw.log_id DESC
    ";

    $stmt_workflow = mysqli_prepare($conn, $sql_workflow);

    if (!$stmt_workflow) {
        throw new RuntimeException('Gagal menyediakan query log workflow.');
    }

    mysqli_stmt_bind_param($stmt_workflow, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_workflow)) {
        throw new RuntimeException('Gagal mendapatkan log workflow.');
    }

    $workflow_logs = mysqli_fetch_all(
        mysqli_stmt_get_result($stmt_workflow),
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt_workflow);

    $sql_maintenance = "
        SELECT
            ls.log_id,
            ls.jenis_selenggara,
            ls.komponen_ditukar,
            ls.kos,
            ls.catatan,
            ls.status_selepas,
            ls.tarikh,
            p.nama_penuh
        FROM log_selenggara ls
        LEFT JOIN pengguna p
            ON p.pengguna_id = ls.dibuat_oleh
        WHERE ls.aset_id = ?
        ORDER BY ls.tarikh DESC, ls.log_id DESC
    ";

    $stmt_maintenance = mysqli_prepare($conn, $sql_maintenance);

    if (!$stmt_maintenance) {
        throw new RuntimeException('Gagal menyediakan query log selenggara.');
    }

    mysqli_stmt_bind_param($stmt_maintenance, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_maintenance)) {
        throw new RuntimeException('Gagal mendapatkan log selenggara.');
    }

    $maintenance_logs = mysqli_fetch_all(
        mysqli_stmt_get_result($stmt_maintenance),
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt_maintenance);
} catch (Throwable $e) {
    error_log('Lihat Aset PID - Log: ' . $e->getMessage());
    $ralat_log = 'Sebahagian log tidak dapat dimuatkan.';
}

$status_workflow_id = (int) $aset['status_workflow_id'];
$status_aset_id = (int) $aset['status_aset_id'];
$boleh_edit = in_array($status_workflow_id, [1, 7], true);
$boleh_hantar = in_array($status_workflow_id, [1, 7], true);
$boleh_hapus = !in_array($status_workflow_id, [6, 8], true);
$boleh_selenggara = $status_aset_id !== 5;
$nota_penolakan = trim((string) ($aset['nota_tolak_terkini'] ?? ''));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Aset PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-lihat">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-building-check"></i></span>
                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Pasukan Inovasi Digital</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-boxes me-2"></i> Senarai Aset
                </a>
                <a class="nav-link" href="tambah.php">
                    <i class="bi bi-plus-circle me-2"></i> Daftar Aset Baru
                </a>
                <hr class="sidebar-divider">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="page-title h2 mb-1">Maklumat Aset</h1>
                    <p class="text-muted mb-0">
                        <?php echo escapeOutput((string) $aset['nama_agensi']); ?>
                    </p>
                </div>

                <div class="action-group align-self-xl-start">
                    <a href="senarai_aset.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>

                    <?php if ($boleh_edit): ?>
                        <a href="edit.php?id=<?php echo (int) $aset_id; ?>"
                           class="btn btn-warning">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>
                    <?php endif; ?>

                    <?php if ($boleh_hantar): ?>
                        <form method="POST"
                              action="hantar_semula.php"
                              class="d-inline"
                              onsubmit="return confirm('Hantar aset ini kepada Ketua Bahagian?');">
                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo escapeOutput($csrf_token); ?>">
                            <input type="hidden"
                                   name="aset_id"
                                   value="<?php echo (int) $aset_id; ?>">

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Hantar ke KB
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($boleh_selenggara): ?>
                        <a href="selenggara.php?id=<?php echo (int) $aset_id; ?>"
                           class="btn btn-success">
                            <i class="bi bi-tools me-1"></i> Catat Selenggara
                        </a>
                    <?php endif; ?>

                    <?php if ($boleh_hapus): ?>
                        <a href="hapus.php?id=<?php echo (int) $aset_id; ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Teruskan ke halaman pelupusan aset ini?');">
                            <i class="bi bi-trash3 me-1"></i> Hapus Aset
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo escapeOutput($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($ralat_log !== ''): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo escapeOutput($ralat_log); ?>
                </div>
            <?php endif; ?>

            <?php if ($status_workflow_id === 7): ?>
                <div class="alert alert-danger rejection-alert p-3 p-lg-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <div class="fw-bold mb-1">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                                Aset ini ditolak oleh Ketua Bahagian
                            </div>

                            <div>
                                <strong>Sebab:</strong>
                                <?php echo nl2br(
                                    escapeOutput(
                                        $nota_penolakan !== ''
                                            ? $nota_penolakan
                                            : 'Tiada sebab penolakan diberikan.'
                                    )
                                ); ?>
                            </div>

                            <div class="mt-2">
                                Sila betulkan maklumat dan hantar semula untuk semakan.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-self-md-start">
                            <a href="edit.php?id=<?php echo (int) $aset_id; ?>"
                               class="btn btn-warning">
                                <i class="bi bi-pencil-square me-1"></i> Edit &amp; Betulkan
                            </a>

                            <form method="POST"
                                  action="hantar_semula.php"
                                  onsubmit="return confirm('Hantar semula aset ini kepada Ketua Bahagian?');">
                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?php echo escapeOutput($csrf_token); ?>">
                                <input type="hidden"
                                       name="aset_id"
                                       value="<?php echo (int) $aset_id; ?>">

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Hantar Semula
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <h2 class="h3 fw-bold mb-0">
                                    <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                </h2>

                                <span class="badge bg-secondary">
                                    <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge <?php echo escapeOutput(pidWorkflowBadge($status_workflow_id)); ?>">
                                    <?php echo escapeOutput((string) ($aset['status_workflow'] ?? '-')); ?>
                                </span>

                                <span class="badge <?php echo escapeOutput(pidStatusAsetBadge($status_aset_id)); ?>">
                                    Status Aset:
                                    <?php echo escapeOutput((string) ($aset['status_aset'] ?? '-')); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php
                        $ringkasan = [
                            'Jenis Perolehan' => $aset['jenis_perolehan'] ?? '-',
                            'Tahun Beli' => $aset['tahun_beli'] ?? '-',
                            'Jenama' => $aset['jenama'] ?? '-',
                            'Model' => $aset['model'] ?? '-',
                            'Pendaftar' => $aset['pendaftar'] ?? '-',
                            'Pengguna Semasa' => $aset['pengguna_semasa'] ?? '-',
                        ];
                        ?>

                        <?php foreach ($ringkasan as $label => $nilai): ?>
                            <div class="col-sm-6 col-lg-4">
                                <div class="detail-box">
                                    <span class="detail-label">
                                        <?php echo escapeOutput($label); ?>
                                    </span>

                                    <div class="detail-value">
                                        <?php echo escapeOutput((string) $nilai); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <ul class="nav nav-pills mb-3" id="assetTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active"
                            id="detail-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#detail-pane"
                            type="button"
                            role="tab">
                        <i class="bi bi-card-list me-1"></i> Maklumat Penuh
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button class="nav-link"
                            id="workflow-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#workflow-pane"
                            type="button"
                            role="tab">
                        <i class="bi bi-diagram-3 me-1"></i> Log Workflow
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button class="nav-link"
                            id="maintenance-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#maintenance-pane"
                            type="button"
                            role="tab">
                        <i class="bi bi-tools me-1"></i> Log Selenggara
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active"
                     id="detail-pane"
                     role="tabpanel"
                     aria-labelledby="detail-tab">

                    <section class="card content-card mb-4">
                        <div class="card-body p-3 p-lg-4">
                            <h2 class="h5 mb-3">A. Maklumat Aset</h2>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>No. Pendaftaran:</strong>
                                    <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Jenis Aset:</strong>
                                    <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Jenis Perolehan:</strong>
                                    <?php echo escapeOutput((string) ($aset['jenis_perolehan'] ?? '-')); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Tahun Beli:</strong>
                                    <?php echo escapeOutput((string) ($aset['tahun_beli'] ?? '-')); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Jenama:</strong>
                                    <?php echo escapeOutput((string) ($aset['jenama'] ?? '-')); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Model:</strong>
                                    <?php echo escapeOutput((string) ($aset['model'] ?? '-')); ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php if (in_array((string) $aset['jenis_aset'], ['NB', 'PC'], true)): ?>
                        <section class="card content-card mb-4">
                            <div class="card-body p-3 p-lg-4">
                                <h2 class="h5 mb-3">B. Spesifikasi PC/Notebook</h2>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Processor:</strong>
                                        <?php echo escapeOutput((string) ($aset['processor'] ?? '-')); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>RAM:</strong>
                                        <?php echo escapeOutput((string) ($aset['ram'] ?? '-')); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Cakera Keras:</strong>
                                        <?php echo escapeOutput((string) ($aset['cakera_keras'] ?? '-')); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Sistem Operasi:</strong>
                                        <?php echo escapeOutput((string) ($aset['sistem_operasi'] ?? '-')); ?>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ((string) $aset['jenis_aset'] === 'Pencetak'): ?>
                        <section class="card content-card mb-4">
                            <div class="card-body p-3 p-lg-4">
                                <h2 class="h5 mb-3">C. Maklumat Pencetak</h2>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Jenis Pencetak:</strong>
                                        <?php echo escapeOutput((string) ($aset['jenis_pencetak'] ?? '-')); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>No. Siri:</strong>
                                        <?php echo escapeOutput((string) ($aset['no_siri_pencetak'] ?? '-')); ?>
                                    </div>
                                    <div class="col-12">
                                        <strong>Spesifikasi:</strong>
                                        <?php echo escapeOutput((string) ($aset['spesifikasi_pencetak'] ?? '-')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Bil. Laser:</strong>
                                        <?php echo (int) ($aset['bil_pencetak_laser'] ?? 0); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Bil. Inkjet:</strong>
                                        <?php echo (int) ($aset['bil_pencetak_inkjet'] ?? 0); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Bil. Matrik:</strong>
                                        <?php echo (int) ($aset['bil_pencetak_matrik'] ?? 0); ?>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="card content-card mb-4">
                        <div class="card-body p-3 p-lg-4">
                            <h2 class="h5 mb-3">D. Maklumat Pegawai</h2>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Nama:</strong>
                                    <?php echo escapeOutput((string) ($aset['pegawai_nama'] ?? '-')); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Jawatan:</strong>
                                    <?php echo escapeOutput((string) ($aset['pegawai_jawatan'] ?? '-')); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Gred:</strong>
                                    <?php echo escapeOutput((string) ($aset['pegawai_gred'] ?? '-')); ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($aset['catatan'])): ?>
                        <section class="card content-card">
                            <div class="card-body p-3 p-lg-4">
                                <h2 class="h5 mb-3">E. Catatan</h2>
                                <p class="mb-0">
                                    <?php echo nl2br(escapeOutput((string) $aset['catatan'])); ?>
                                </p>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade"
                     id="workflow-pane"
                     role="tabpanel"
                     aria-labelledby="workflow-tab">

                    <section class="card content-card">
                        <div class="card-body p-3 p-lg-4">
                            <h2 class="h5 mb-3">Log Workflow</h2>

                            <?php if ($workflow_logs === []): ?>
                                <div class="empty-state">
                                    <i class="bi bi-diagram-3"></i>
                                    <h3 class="h6">Tiada log workflow</h3>
                                </div>
                            <?php else: ?>
                                <?php foreach ($workflow_logs as $log): ?>
                                    <?php
                                    $tindakan = (string) ($log['tindakan'] ?? '');
                                    ?>

                                    <div class="timeline-item">
                                        <div class="timeline-icon <?php echo escapeOutput(pidTimelineClass($tindakan)); ?>">
                                            <i class="bi <?php echo escapeOutput(pidTimelineIcon($tindakan)); ?>"></i>
                                        </div>

                                        <div class="flex-grow-1">
                                            <div class="d-flex flex-column flex-md-row justify-content-between gap-1">
                                                <div class="fw-semibold">
                                                    <?php echo escapeOutput($tindakan !== '' ? $tindakan : '-'); ?>
                                                </div>

                                                <small class="text-muted">
                                                    <?php echo escapeOutput(pidFormatTarikh($log['tarikh'] ?? null)); ?>
                                                </small>
                                            </div>

                                            <div class="small text-muted">
                                                Oleh:
                                                <?php echo escapeOutput((string) ($log['nama_penuh'] ?? '-')); ?>
                                            </div>

                                            <?php if (!empty($log['catatan'])): ?>
                                                <div class="small mt-2">
                                                    <?php echo nl2br(escapeOutput((string) $log['catatan'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="tab-pane fade"
                     id="maintenance-pane"
                     role="tabpanel"
                     aria-labelledby="maintenance-tab">

                    <section class="card content-card">
                        <div class="card-body p-3 p-lg-4">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                                <h2 class="h5 mb-0">Log Selenggara</h2>

                                <?php if ($boleh_selenggara): ?>
                                    <a href="selenggara.php?id=<?php echo (int) $aset_id; ?>"
                                       class="btn btn-success btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Catat Selenggara
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($maintenance_logs === []): ?>
                                <div class="empty-state">
                                    <i class="bi bi-tools"></i>
                                    <h3 class="h6">Tiada rekod penyelenggaraan</h3>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <th>Tarikh</th>
                                            <th>Jenis</th>
                                            <th>Komponen</th>
                                            <th>Kos</th>
                                            <th>Catatan</th>
                                            <th>Status Selepas</th>
                                            <th>Oleh</th>
                                        </tr>
                                        </thead>

                                        <tbody>
                                        <?php foreach ($maintenance_logs as $log): ?>
                                            <tr>
                                                <td class="text-nowrap">
                                                    <?php echo escapeOutput(pidFormatTarikh($log['tarikh'] ?? null)); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput((string) ($log['jenis_selenggara'] ?? '-')); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput((string) ($log['komponen_ditukar'] ?? '-')); ?>
                                                </td>
                                                <td class="text-nowrap">
                                                    RM <?php echo number_format((float) ($log['kos'] ?? 0), 2); ?>
                                                </td>
                                                <td>
                                                    <?php echo nl2br(escapeOutput((string) ($log['catatan'] ?? '-'))); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput((string) ($log['status_selepas'] ?? '-')); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput((string) ($log['nama_penuh'] ?? '-')); ?>
                                                </td>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);

    if (params.get('tab') === 'selenggara') {
        const trigger = document.getElementById('maintenance-tab');

        if (trigger) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }
});
</script>
</body>
</html>
