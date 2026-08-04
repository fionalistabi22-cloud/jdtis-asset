<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Bahagian - Lihat Maklumat Aset
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

requireRoleWhitelist(['Ketua Bahagian']);

$nama_penuh = $_SESSION['nama_penuh'] ?? 'Ketua Bahagian';
$peranan = $_SESSION['peranan'] ?? 'Ketua Bahagian';

$aset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$aset = null;
$log_workflow = [];
$log_selenggara = [];
$ralat_log = '';

$formatTarikh = static function ($nilai): string {
    if (empty($nilai)) {
        return '-';
    }

    $masa = strtotime((string) $nilai);
    return $masa !== false ? date('d/m/Y', $masa) : '-';
};

$formatTarikhMasa = static function ($nilai): string {
    if (empty($nilai)) {
        return '-';
    }

    $masa = strtotime((string) $nilai);
    return $masa !== false ? date('d/m/Y H:i', $masa) : '-';
};

$paparNilai = static function ($nilai): string {
    if ($nilai === null || trim((string) $nilai) === '') {
        return '-';
    }

    return (string) $nilai;
};

$getWorkflowBadgeClass = static function (int $status_id): string {
    return match ($status_id) {
        1 => 'bg-secondary',
        2 => 'bg-warning text-dark',
        3 => 'bg-danger',
        4 => 'bg-info text-dark',
        5 => 'bg-danger',
        6 => 'bg-warning text-dark',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
};

$getAssetBadgeClass = static function (int $status_id): string {
    return match ($status_id) {
        1 => 'bg-success',
        2 => 'bg-danger',
        3 => 'bg-warning text-dark',
        4 => 'bg-dark',
        5 => 'bg-secondary',
        default => 'bg-secondary',
    };
};

$getTimelineMeta = static function (string $tindakan): array {
    if (stripos($tindakan, 'Tolak') !== false) {
        return [
            'class' => 'timeline-danger',
            'icon' => 'bi-x-lg',
            'label' => 'Penolakan',
        ];
    }

    if (stripos($tindakan, 'Lulus') !== false) {
        return [
            'class' => 'timeline-success',
            'icon' => 'bi-check-lg',
            'label' => 'Kelulusan',
        ];
    }

    if (stripos($tindakan, 'Semak') !== false) {
        return [
            'class' => 'timeline-warning',
            'icon' => 'bi-search',
            'label' => 'Semakan',
        ];
    }

    return [
        'class' => 'timeline-primary',
        'icon' => 'bi-send',
        'label' => 'Pendaftaran / Penghantaran',
    ];
};

/*
 * Dapatkan maklumat aset tanpa sekatan wilayah.
 * Ketua Bahagian dibenarkan melihat semua aset di seluruh sistem.
 */
try {
    $sql_aset = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.jenis_perolehan,
            a.tahun_beli,
            a.jenama,
            a.model,
            a.processor,
            a.ram,
            a.cakera_keras,
            a.sistem_operasi,
            a.spesifikasi_pencetak,
            a.jenis_pencetak,
            a.bil_pencetak_laser,
            a.bil_pencetak_inkjet,
            a.bil_pencetak_matrik,
            a.no_siri_pencetak,
            a.pegawai_nama,
            a.pegawai_jawatan,
            a.pegawai_gred,
            a.agensi_id,
            a.wilayah_id,
            a.status_workflow_id,
            a.status_aset_id,
            a.pengguna_id_daftar,
            a.catatan,
            a.tarikh_input,
            a.tarikh_kemaskini,
            ag.nama_agensi,
            w.nama_wilayah,
            w.jenis AS jenis_wilayah,
            p.nama_penuh AS pendaftar,
            sw.status AS status_workflow,
            sa.status AS status_aset
        FROM aset a
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN wilayah w
            ON w.wilayah_id = a.wilayah_id
        LEFT JOIN pengguna p
            ON p.pengguna_id = a.pengguna_id_daftar
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa
            ON sa.status_aset_id = a.status_aset_id
        WHERE a.aset_id = ?
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);
    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query maklumat aset.');
    }

    mysqli_stmt_bind_param($stmt_aset, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $result_aset = mysqli_stmt_get_result($stmt_aset);
    $aset = mysqli_fetch_assoc($result_aset);
    mysqli_stmt_close($stmt_aset);
} catch (Throwable $e) {
    error_log('Lihat aset Ketua Bahagian: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan. Sila cuba semula.';
    header('Location: senarai_aset.php');
    exit;
}

if (!$aset) {
    $_SESSION['flash_error'] = 'Rekod aset tidak ditemui.';
    header('Location: senarai_aset.php');
    exit;
}

/*
 * Log workflow dan log penyelenggaraan dipisahkan daripada query utama.
 * Kegagalan mendapatkan log tidak menghalang maklumat aset daripada dipaparkan.
 */
try {
    $sql_workflow = "
        SELECT
            lw.log_id,
            lw.tindakan,
            lw.status_workflow_dari,
            lw.status_workflow_ke,
            lw.catatan,
            lw.tarikh,
            pg.nama_penuh AS oleh,
            sw_dari.status AS status_dari,
            sw_ke.status AS status_ke
        FROM log_workflow lw
        LEFT JOIN pengguna pg
            ON pg.pengguna_id = lw.oleh_pengguna_id
        LEFT JOIN status_workflow sw_dari
            ON sw_dari.status_workflow_id = lw.status_workflow_dari
        LEFT JOIN status_workflow sw_ke
            ON sw_ke.status_workflow_id = lw.status_workflow_ke
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

    $result_workflow = mysqli_stmt_get_result($stmt_workflow);
    while ($row = mysqli_fetch_assoc($result_workflow)) {
        $log_workflow[] = $row;
    }
    mysqli_stmt_close($stmt_workflow);

    $sql_selenggara = "
        SELECT
            ls.log_id,
            ls.jenis_selenggara,
            ls.komponen_ditukar,
            ls.status_selepas,
            ls.tarikh,
            pg.nama_penuh AS oleh
        FROM log_selenggara ls
        LEFT JOIN pengguna pg
            ON pg.pengguna_id = ls.dibuat_oleh
        WHERE ls.aset_id = ?
        ORDER BY ls.tarikh DESC, ls.log_id DESC
    ";

    $stmt_selenggara = mysqli_prepare($conn, $sql_selenggara);
    if (!$stmt_selenggara) {
        throw new RuntimeException('Gagal menyediakan query log penyelenggaraan.');
    }

    mysqli_stmt_bind_param($stmt_selenggara, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_selenggara)) {
        throw new RuntimeException('Gagal mendapatkan log penyelenggaraan.');
    }

    $result_selenggara = mysqli_stmt_get_result($stmt_selenggara);
    while ($row = mysqli_fetch_assoc($result_selenggara)) {
        $log_selenggara[] = $row;
    }
    mysqli_stmt_close($stmt_selenggara);
} catch (Throwable $e) {
    error_log('Log aset Ketua Bahagian: ' . $e->getMessage());
    $ralat_log = 'Sebahagian rekod log tidak dapat dimuatkan pada masa ini.';
}

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$is_pid = (int) $aset['wilayah_id'] === 1;
$boleh_tindakan = $is_pid && (int) $aset['status_workflow_id'] === 6;
$sumber = $is_pid ? 'PID' : 'Wilayah';

$nama_status_workflow = $paparNilai($aset['status_workflow']);
$nama_status_aset = $paparNilai($aset['status_aset']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeOutput($aset['no_pendaftaran']); ?> - Ketua Bahagian JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-bahagian.css">
</head>
<body class="ketua-bahagian page-lihat">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark">
                    <i class="bi bi-shield-check fs-5"></i>
                </span>
                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Ketua Bahagian</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-fill me-2"></i> Dashboard
                </a>
                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-box-seam me-2"></i> Senarai Aset
                </a>

                <hr class="border-light opacity-25">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                <div>
                    <h1 class="page-title h3 mb-1">Maklumat Aset</h1>
                    <p class="text-muted mb-0">
                        Semakan terperinci rekod aset dan sejarah aliran kerja.
                    </p>
                </div>

                <div class="text-lg-end">
                    <div class="fw-semibold"><?php echo escapeOutput($nama_penuh); ?></div>
                    <small class="text-muted"><?php echo escapeOutput($peranan); ?></small>
                </div>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo escapeOutput($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($ralat_log !== ''): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo escapeOutput($ralat_log); ?>
                </div>
            <?php endif; ?>

            <section class="card content-card asset-header mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="asset-number">
                                    <?php echo escapeOutput($aset['no_pendaftaran']); ?>
                                </span>
                                <span class="badge bg-primary">
                                    <?php echo escapeOutput($aset['jenis_aset']); ?>
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="badge <?php echo escapeOutput($getWorkflowBadgeClass((int) $aset['status_workflow_id'])); ?>">
                                    <i class="bi bi-diagram-3 me-1"></i>
                                    <?php echo escapeOutput($nama_status_workflow); ?>
                                </span>

                                <span class="badge <?php echo escapeOutput($getAssetBadgeClass((int) $aset['status_aset_id'])); ?>">
                                    <i class="bi bi-hdd-stack me-1"></i>
                                    <?php echo escapeOutput($nama_status_aset); ?>
                                </span>

                                <span class="badge <?php echo $is_pid ? 'source-pid' : 'source-wilayah'; ?>">
                                    <i class="bi <?php echo $is_pid ? 'bi-building' : 'bi-geo-alt'; ?> me-1"></i>
                                    <?php echo escapeOutput($sumber); ?>
                                </span>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Model</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['model'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Jenama</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['jenama'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Tahun</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['tahun_beli'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Agensi</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['nama_agensi'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Wilayah</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['nama_wilayah'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-lg-4">
                                    <div class="info-grid-item">
                                        <div class="asset-meta-label">Pendaftar</div>
                                        <p class="asset-meta-value">
                                            <?php echo escapeOutput($paparNilai($aset['pendaftar'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons align-self-xl-start">
                            <a href="senarai_aset.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </a>

                            <?php if ($boleh_tindakan): ?>
                                <a
                                    href="lulus.php?id=<?php echo escapeOutput((string) $aset_id); ?>"
                                    class="btn btn-success"
                                >
                                    <i class="bi bi-check-circle me-1"></i> Lulus Aset
                                </a>

                                <a
                                    href="tolak.php?id=<?php echo escapeOutput((string) $aset_id); ?>"
                                    class="btn btn-danger"
                                >
                                    <i class="bi bi-x-circle me-1"></i> Tolak Aset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$is_pid): ?>
                        <div class="alert alert-secondary d-flex align-items-start gap-2 mt-4 mb-0" role="alert">
                            <i class="bi bi-eye-fill mt-1"></i>
                            <div>
                                <strong>Aset Wilayah — akses lihat sahaja.</strong>
                                Ketua Bahagian tidak boleh meluluskan, menolak atau mengubah workflow aset ini.
                            </div>
                        </div>
                    <?php elseif ((int) $aset['status_workflow_id'] !== 6): ?>
                        <div class="alert alert-light border d-flex align-items-start gap-2 mt-4 mb-0" role="alert">
                            <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                            <div>
                                Tindakan kelulusan atau penolakan tidak tersedia kerana aset PID ini bukan lagi
                                dalam status <strong>Menunggu KB</strong>.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card content-card tabs-card">
                <div class="card-header bg-white p-0 border-bottom">
                    <ul class="nav nav-tabs" id="assetTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link active"
                                id="maklumat-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#maklumat"
                                type="button"
                                role="tab"
                                aria-controls="maklumat"
                                aria-selected="true"
                            >
                                <i class="bi bi-card-list me-1"></i> Maklumat Penuh
                            </button>
                        </li>

                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link"
                                id="workflow-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#workflow"
                                type="button"
                                role="tab"
                                aria-controls="workflow"
                                aria-selected="false"
                            >
                                <i class="bi bi-clock-history me-1"></i>
                                Log Workflow
                                <span class="badge rounded-pill bg-light text-dark ms-1">
                                    <?php echo escapeOutput((string) count($log_workflow)); ?>
                                </span>
                            </button>
                        </li>

                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link"
                                id="selenggara-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#selenggara"
                                type="button"
                                role="tab"
                                aria-controls="selenggara"
                                aria-selected="false"
                            >
                                <i class="bi bi-tools me-1"></i>
                                Log Selenggara
                                <span class="badge rounded-pill bg-light text-dark ms-1">
                                    <?php echo escapeOutput((string) count($log_selenggara)); ?>
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-3 p-lg-4">
                    <div class="tab-content" id="assetTabsContent">
                        <div
                            class="tab-pane fade show active"
                            id="maklumat"
                            role="tabpanel"
                            aria-labelledby="maklumat-tab"
                            tabindex="0"
                        >
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="detail-section">
                                        <h2 class="section-title">
                                            <i class="bi bi-box-seam"></i>
                                            Maklumat Aset
                                        </h2>

                                        <dl class="detail-list">
                                            <div class="detail-row">
                                                <dt class="detail-term">No. Pendaftaran</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($aset['no_pendaftaran']); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Jenis Aset</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($aset['jenis_aset']); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Jenis Perolehan</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['jenis_perolehan'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Tahun Beli</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['tahun_beli'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Jenama</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['jenama'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Model</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['model'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Tarikh Input</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($formatTarikhMasa($aset['tarikh_input'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Kemaskini Terakhir</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($formatTarikhMasa($aset['tarikh_kemaskini'])); ?>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="detail-section">
                                        <h2 class="section-title">
                                            <i class="bi bi-person-badge"></i>
                                            Maklumat Pegawai
                                        </h2>

                                        <dl class="detail-list">
                                            <div class="detail-row">
                                                <dt class="detail-term">Nama Pegawai</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['pegawai_nama'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Jawatan</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['pegawai_jawatan'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Gred</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['pegawai_gred'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Agensi</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['nama_agensi'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Wilayah</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['nama_wilayah'])); ?>
                                                </dd>
                                            </div>

                                            <div class="detail-row">
                                                <dt class="detail-term">Pendaftar</dt>
                                                <dd class="detail-value">
                                                    <?php echo escapeOutput($paparNilai($aset['pendaftar'])); ?>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>

                                <?php if (in_array($aset['jenis_aset'], ['NB', 'PC'], true)): ?>
                                    <div class="col-12">
                                        <div class="detail-section">
                                            <h2 class="section-title">
                                                <i class="bi bi-pc-display"></i>
                                                Spesifikasi PC / Notebook
                                            </h2>

                                            <div class="row g-3">
                                                <div class="col-md-6 col-xl-3">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Processor</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['processor'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 col-xl-3">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">RAM</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['ram'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 col-xl-3">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Cakera Keras</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['cakera_keras'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 col-xl-3">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Sistem Operasi</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['sistem_operasi'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($aset['jenis_aset'] === 'Pencetak'): ?>
                                    <div class="col-12">
                                        <div class="detail-section">
                                            <h2 class="section-title">
                                                <i class="bi bi-printer"></i>
                                                Maklumat Pencetak
                                            </h2>

                                            <div class="row g-3">
                                                <div class="col-md-6 col-xl-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Jenis Pencetak</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['jenis_pencetak'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 col-xl-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">No. Siri</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['no_siri_pencetak'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-6 col-xl-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Spesifikasi</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput($paparNilai($aset['spesifikasi_pencetak'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Bilangan Laser</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput((string) ((int) $aset['bil_pencetak_laser'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Bilangan Inkjet</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput((string) ((int) $aset['bil_pencetak_inkjet'])); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <div class="info-grid-item">
                                                        <div class="asset-meta-label">Bilangan Matrik</div>
                                                        <p class="asset-meta-value">
                                                            <?php echo escapeOutput((string) ((int) $aset['bil_pencetak_matrik'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($aset['catatan'] !== null && trim((string) $aset['catatan']) !== ''): ?>
                                    <div class="col-12">
                                        <div class="detail-section">
                                            <h2 class="section-title">
                                                <i class="bi bi-chat-left-text"></i>
                                                Catatan
                                            </h2>

                                            <div class="note-box"><?php echo escapeOutput($aset['catatan']); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div
                            class="tab-pane fade"
                            id="workflow"
                            role="tabpanel"
                            aria-labelledby="workflow-tab"
                            tabindex="0"
                        >
                            <?php if ($log_workflow): ?>
                                <ul class="timeline">
                                    <?php foreach ($log_workflow as $log): ?>
                                        <?php $timeline = $getTimelineMeta((string) $log['tindakan']); ?>
                                        <li class="timeline-item <?php echo escapeOutput($timeline['class']); ?>">
                                            <div class="timeline-icon" aria-hidden="true">
                                                <i class="bi <?php echo escapeOutput($timeline['icon']); ?>"></i>
                                            </div>

                                            <div class="timeline-content">
                                                <div class="d-flex flex-column flex-md-row justify-content-between gap-1">
                                                    <div>
                                                        <div class="timeline-title">
                                                            <?php echo escapeOutput($log['tindakan']); ?>
                                                        </div>
                                                        <div class="timeline-meta">
                                                            <?php echo escapeOutput($timeline['label']); ?>
                                                            oleh
                                                            <strong>
                                                                <?php echo escapeOutput($paparNilai($log['oleh'])); ?>
                                                            </strong>
                                                        </div>
                                                    </div>

                                                    <time class="timeline-meta text-nowrap">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?php echo escapeOutput($formatTarikhMasa($log['tarikh'])); ?>
                                                    </time>
                                                </div>

                                                <?php if (
                                                    $log['status_workflow_dari'] !== null
                                                    || $log['status_workflow_ke'] !== null
                                                ): ?>
                                                    <div class="transition-badge">
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo escapeOutput($paparNilai($log['status_dari'])); ?>
                                                        </span>
                                                        <i class="bi bi-arrow-right"></i>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo escapeOutput($paparNilai($log['status_ke'])); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($log['catatan'] !== null && trim((string) $log['catatan']) !== ''): ?>
                                                    <div class="mt-3 pt-3 border-top text-secondary">
                                                        <?php echo nl2br(escapeOutput($log['catatan'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-clock-history"></i>
                                    <h3 class="h6">Belum ada log workflow</h3>
                                    <p class="mb-0">Sejarah aliran kerja aset ini belum direkodkan.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div
                            class="tab-pane fade"
                            id="selenggara"
                            role="tabpanel"
                            aria-labelledby="selenggara-tab"
                            tabindex="0"
                        >
                            <?php if ($log_selenggara): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Tarikh</th>
                                            <th>Jenis</th>
                                            <th>Komponen Ditukar</th>
                                            <th>Status Selepas</th>
                                            <th>Oleh</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($log_selenggara as $log): ?>
                                            <tr>
                                                <td class="text-nowrap">
                                                    <?php echo escapeOutput($formatTarikhMasa($log['tarikh'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput($paparNilai($log['jenis_selenggara'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput($paparNilai($log['komponen_ditukar'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo escapeOutput($paparNilai($log['status_selepas'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo escapeOutput($paparNilai($log['oleh'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-tools"></i>
                                    <h3 class="h6">Belum ada rekod penyelenggaraan</h3>
                                    <p class="mb-0">
                                        Tiada aktiviti penyelenggaraan direkodkan untuk aset ini.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>