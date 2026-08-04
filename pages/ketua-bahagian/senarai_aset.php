<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Bahagian - Senarai Aset
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

$carian = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$sumber = strtolower(trim((string) ($_GET['sumber'] ?? '')));
$wilayah_id = filter_input(INPUT_GET, 'wilayah_id', FILTER_VALIDATE_INT);
$jenis_aset = trim((string) ($_GET['jenis_aset'] ?? ''));
$filter_ringkas = strtolower(trim((string) ($_GET['filter'] ?? '')));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? $page : 1;
$per_page = 20;

$status_dibenarkan = ['', '1', '2', '3', '4', '5', '6', '7', '8', 'all'];
$sumber_dibenarkan = ['', 'pid', 'wilayah'];
$jenis_dibenarkan = ['', 'NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
$filter_dibenarkan = ['', 'pending', 'approved', 'rejected'];

if (!in_array($status, $status_dibenarkan, true)) {
    $status = '';
}

if (!in_array($sumber, $sumber_dibenarkan, true)) {
    $sumber = '';
}

if (!in_array($jenis_aset, $jenis_dibenarkan, true)) {
    $jenis_aset = '';
}

if (!in_array($filter_ringkas, $filter_dibenarkan, true)) {
    $filter_ringkas = '';
}

if ($wilayah_id === false || $wilayah_id === null || $wilayah_id < 1) {
    $wilayah_id = 0;
}

/*
 * Pautan kad dashboard menggunakan parameter filter ringkas.
 * Parameter status yang dipilih secara terus mempunyai keutamaan.
 */
if ($status === '' && $filter_ringkas !== '') {
    $peta_filter_status = [
        'pending' => '6',
        'approved' => '8',
        'rejected' => '7',
    ];
    $status = $peta_filter_status[$filter_ringkas] ?? '';
}

$senarai_wilayah = [];
$senarai_aset = [];
$total_records = 0;
$total_pages = 1;
$ralat_data = '';

/**
 * Bind parameter dinamik kepada mysqli_stmt menggunakan rujukan.
 */
$bindDynamicParams = static function (mysqli_stmt $stmt, string $types, array &$params): void {
    if ($types === '' || $params === []) {
        return;
    }

    $bind_args = [$stmt, $types];
    foreach ($params as $index => &$value) {
        $bind_args[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $bind_args)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
};

/**
 * Hasilkan kelas badge bagi status workflow.
 */
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

/**
 * Fallback nama status jika data status_workflow tidak lengkap.
 */
$getWorkflowName = static function (int $status_id, ?string $status_db): string {
    if ($status_db !== null && trim($status_db) !== '') {
        return $status_db;
    }

    return match ($status_id) {
        1 => 'Draf',
        2 => 'Menunggu PPTM',
        3 => 'Ditolak PPTM',
        4 => 'Menunggu KW',
        5 => 'Ditolak KW',
        6 => 'Menunggu KB',
        7 => 'Ditolak KB',
        8 => 'Lulus',
        default => 'Tidak diketahui',
    };
};

try {
    // Dropdown wilayah: semua wilayah tersedia kerana Ketua Bahagian tiada sekatan wilayah.
    $sql_wilayah = "
        SELECT wilayah_id, nama_wilayah, jenis
        FROM wilayah
        ORDER BY
            CASE WHEN jenis = 'ibu_pejabat' THEN 0 ELSE 1 END,
            nama_wilayah ASC
    ";

    $stmt_wilayah = mysqli_prepare($conn, $sql_wilayah);
    if (!$stmt_wilayah) {
        throw new RuntimeException('Gagal menyediakan query wilayah.');
    }

    if (!mysqli_stmt_execute($stmt_wilayah)) {
        throw new RuntimeException('Gagal mendapatkan senarai wilayah.');
    }

    $result_wilayah = mysqli_stmt_get_result($stmt_wilayah);
    while ($row = mysqli_fetch_assoc($result_wilayah)) {
        $senarai_wilayah[] = $row;
    }
    mysqli_stmt_close($stmt_wilayah);

    /*
     * WHERE dibina hanya daripada fragmen SQL statik yang telah ditentukan.
     * Semua nilai pengguna tetap dihantar melalui prepared-statement binding.
     */
    $where_clauses = ['1 = 1'];
    $where_types = '';
    $where_params = [];

    if ($carian !== '') {
        $where_clauses[] = "(
            a.no_pendaftaran LIKE ?
            OR a.model LIKE ?
            OR a.pegawai_nama LIKE ?
            OR ag.nama_agensi LIKE ?
            OR w.nama_wilayah LIKE ?
        )";

        $kata_carian = '%' . $carian . '%';
        for ($i = 0; $i < 5; $i++) {
            $where_types .= 's';
            $where_params[] = $kata_carian;
        }
    }

    if (in_array($status, ['1', '2', '3', '4', '5', '6', '7', '8'], true)) {
        $where_clauses[] = 'a.status_workflow_id = ?';
        $where_types .= 'i';
        $where_params[] = (int) $status;
    }

    if ($sumber === 'pid') {
        $where_clauses[] = 'a.wilayah_id = ?';
        $where_types .= 'i';
        $where_params[] = 1;
    } elseif ($sumber === 'wilayah') {
        $where_clauses[] = 'a.wilayah_id > ?';
        $where_types .= 'i';
        $where_params[] = 1;
    }

    if ($wilayah_id > 0) {
        $where_clauses[] = 'a.wilayah_id = ?';
        $where_types .= 'i';
        $where_params[] = $wilayah_id;
    }

    if ($jenis_aset !== '') {
        $where_clauses[] = 'a.jenis_aset = ?';
        $where_types .= 's';
        $where_params[] = $jenis_aset;
    }

    $where_sql = implode("\n AND ", $where_clauses);

    $sql_count = "
        SELECT COUNT(*) AS jumlah
        FROM aset a
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN wilayah w
            ON w.wilayah_id = a.wilayah_id
        WHERE {$where_sql}
    ";

    $stmt_count = mysqli_prepare($conn, $sql_count);
    if (!$stmt_count) {
        throw new RuntimeException('Gagal menyediakan query kiraan aset.');
    }

    $count_params = $where_params;
    $bindDynamicParams($stmt_count, $where_types, $count_params);

    if (!mysqli_stmt_execute($stmt_count)) {
        throw new RuntimeException('Gagal mendapatkan jumlah aset.');
    }

    $result_count = mysqli_stmt_get_result($stmt_count);
    $row_count = mysqli_fetch_assoc($result_count);
    $total_records = (int) ($row_count['jumlah'] ?? 0);
    mysqli_stmt_close($stmt_count);

    $total_pages = max(1, (int) getTotalPages($total_records, $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = getPaginationOffset($page, $per_page);

    $sql_data = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.wilayah_id,
            a.status_workflow_id,
            a.tarikh_input,
            w.nama_wilayah,
            ag.nama_agensi,
            sw.status AS status_workflow
        FROM aset a
        LEFT JOIN wilayah w
            ON w.wilayah_id = a.wilayah_id
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        WHERE {$where_sql}
        ORDER BY
            CASE WHEN a.status_workflow_id = 6 AND a.wilayah_id = 1 THEN 0 ELSE 1 END,
            COALESCE(a.tarikh_kemaskini, a.tarikh_input) DESC,
            a.aset_id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt_data = mysqli_prepare($conn, $sql_data);
    if (!$stmt_data) {
        throw new RuntimeException('Gagal menyediakan query senarai aset.');
    }

    $data_params = $where_params;
    $data_types = $where_types . 'ii';
    $data_params[] = $per_page;
    $data_params[] = $offset;
    $bindDynamicParams($stmt_data, $data_types, $data_params);

    if (!mysqli_stmt_execute($stmt_data)) {
        throw new RuntimeException('Gagal mendapatkan senarai aset.');
    }

    $result_data = mysqli_stmt_get_result($stmt_data);
    while ($row = mysqli_fetch_assoc($result_data)) {
        $senarai_aset[] = $row;
    }
    mysqli_stmt_close($stmt_data);
} catch (Throwable $e) {
    error_log('Senarai Aset Ketua Bahagian: ' . $e->getMessage());
    $ralat_data = 'Senarai aset tidak dapat dimuatkan. Sila cuba semula atau hubungi pentadbir sistem.';
}

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$current_count = count($senarai_aset);
$rekod_mula = $total_records > 0 ? (($page - 1) * $per_page) + 1 : 0;
$rekod_akhir = $total_records > 0 ? $rekod_mula + $current_count - 1 : 0;

$query_asas = [];
if ($carian !== '') {
    $query_asas['q'] = $carian;
}
if ($status !== '') {
    $query_asas['status'] = $status;
}
if ($sumber !== '') {
    $query_asas['sumber'] = $sumber;
}
if ($wilayah_id > 0) {
    $query_asas['wilayah_id'] = $wilayah_id;
}
if ($jenis_aset !== '') {
    $query_asas['jenis_aset'] = $jenis_aset;
}

$buildPageUrl = static function (int $target_page) use ($query_asas): string {
    $params = $query_asas;
    $params['page'] = $target_page;
    return 'senarai_aset.php?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Aset Ketua Bahagian - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-bahagian.css">
</head>
<body class="ketua-bahagian page-senarai-aset">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-shield-check"></i></span>
                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Ketua Bahagian</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-box-seam"></i> Senarai Aset
                </a>
                <hr class="w-100 nav-separator">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                <div>
                    <h1 class="page-title h2 mb-1">Senarai Aset</h1>
                    <p class="text-muted mb-0">
                        Lihat semua aset sistem. Tindakan workflow hanya tersedia untuk aset PID yang menunggu Ketua Bahagian.
                    </p>
                </div>
                <div class="text-lg-end">
                    <span class="badge rounded-pill badge-soft-primary px-3 py-2">
                        <i class="bi bi-person-badge me-1"></i>
                        <?php echo escapeOutput((string) $peranan); ?>
                    </span>
                </div>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo escapeOutput((string) $flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput((string) $flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($ralat_data !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i>
                    <?php echo escapeOutput((string) $ralat_data); ?>
                </div>
            <?php endif; ?>

            <section class="card content-card filter-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-funnel me-2 text-primary"></i>Carian dan Penapis
                        </h2>
                    </div>

                    <form method="GET" action="senarai_aset.php">
                        <div class="row g-3">
                            <div class="col-12 col-xl-4">
                                <label for="q" class="form-label">Carian</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-search text-muted"></i>
                                    </span>
                                    <input
                                        type="search"
                                        class="form-control border-start-0"
                                        id="q"
                                        name="q"
                                        value="<?php echo escapeOutput($carian); ?>"
                                        placeholder="No. pendaftaran, model, pegawai, agensi atau wilayah"
                                    >
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label for="status" class="form-label">Status Workflow</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Semua Status</option>
                                    <option value="6" <?php echo $status === '6' ? 'selected' : ''; ?>>Menunggu KB</option>
                                    <option value="8" <?php echo $status === '8' ? 'selected' : ''; ?>>Diluluskan</option>
                                    <option value="7" <?php echo $status === '7' ? 'selected' : ''; ?>>Ditolak KB</option>
                                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Semua Status Sistem</option>
                                </select>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label for="sumber" class="form-label">Sumber</label>
                                <select class="form-select" id="sumber" name="sumber">
                                    <option value="" <?php echo $sumber === '' ? 'selected' : ''; ?>>Semua Sumber</option>
                                    <option value="wilayah" <?php echo $sumber === 'wilayah' ? 'selected' : ''; ?>>Wilayah</option>
                                    <option value="pid" <?php echo $sumber === 'pid' ? 'selected' : ''; ?>>PID</option>
                                </select>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label for="wilayah_id" class="form-label">Wilayah</label>
                                <select class="form-select" id="wilayah_id" name="wilayah_id">
                                    <option value="">Semua Wilayah</option>
                                    <?php foreach ($senarai_wilayah as $wilayah): ?>
                                        <option
                                            value="<?php echo escapeOutput((string) $wilayah['wilayah_id']); ?>"
                                            <?php echo $wilayah_id === (int) $wilayah['wilayah_id'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo escapeOutput((string) $wilayah['nama_wilayah']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label for="jenis_aset" class="form-label">Jenis Aset</label>
                                <select class="form-select" id="jenis_aset" name="jenis_aset">
                                    <option value="" <?php echo $jenis_aset === '' ? 'selected' : ''; ?>>Semua Jenis</option>
                                    <?php foreach (['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'] as $jenis): ?>
                                        <option value="<?php echo escapeOutput($jenis); ?>" <?php echo $jenis_aset === $jenis ? 'selected' : ''; ?>>
                                            <?php echo escapeOutput($jenis); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-search me-1"></i> Cari
                                </button>
                                <a href="senarai_aset.php" class="btn btn-outline-secondary px-4">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card content-card">
                <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <h2 class="h5 mb-1">Rekod Aset</h2>
                            <div class="result-summary">
                                Menunjukkan <strong><?php echo escapeOutput(number_format($current_count)); ?></strong>
                                daripada <strong><?php echo escapeOutput(number_format($total_records)); ?></strong> rekod
                                <?php if ($total_records > 0): ?>
                                    <span class="ms-1">(rekod <?php echo escapeOutput(number_format($rekod_mula)); ?>–<?php echo escapeOutput(number_format($rekod_akhir)); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge text-bg-light border px-3 py-2">
                            <i class="bi bi-eye me-1"></i> Aset Wilayah: paparan sahaja
                        </span>
                    </div>
                </div>

                <?php if ($senarai_aset === []): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3 class="h5">Tiada rekod ditemui</h3>
                        <p class="mb-3">Cuba ubah kata carian atau pilihan penapis.</p>
                        <a href="senarai_aset.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Penapis
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Model</th>
                                <th>Wilayah</th>
                                <th>Agensi</th>
                                <th>Sumber</th>
                                <th>Status Workflow</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($senarai_aset as $aset): ?>
                                <?php
                                $aset_id = (int) $aset['aset_id'];
                                $aset_wilayah_id = (int) $aset['wilayah_id'];
                                $aset_status_id = (int) $aset['status_workflow_id'];
                                $ialah_pid = $aset_wilayah_id === 1;
                                $boleh_tindakan = $ialah_pid && $aset_status_id === 6;
                                $status_name = $getWorkflowName($aset_status_id, $aset['status_workflow']);
                                ?>
                                <tr>
                                    <td>
                                        <a class="asset-number" href="lihat.php?id=<?php echo escapeOutput((string) $aset_id); ?>">
                                            <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-light border">
                                            <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escapeOutput((string) ($aset['model'] ?: '-')); ?></td>
                                    <td><?php echo escapeOutput((string) ($aset['nama_wilayah'] ?: '-')); ?></td>
                                    <td><?php echo escapeOutput((string) ($aset['nama_agensi'] ?: '-')); ?></td>
                                    <td>
                                        <?php if ($ialah_pid): ?>
                                            <span class="badge bg-info text-dark">PID</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Wilayah</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo escapeOutput($getWorkflowBadgeClass($aset_status_id)); ?>">
                                            <?php echo escapeOutput($status_name); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="lihat.php?id=<?php echo escapeOutput((string) $aset_id); ?>" class="btn btn-info btn-sm text-dark" title="Lihat aset">
                                                <i class="bi bi-eye"></i> Lihat
                                            </a>

                                            <?php if ($boleh_tindakan): ?>
                                                <a href="lulus.php?id=<?php echo escapeOutput((string) $aset_id); ?>" class="btn btn-success btn-sm" title="Lulus aset PID">
                                                    <i class="bi bi-check-lg"></i> Lulus
                                                </a>
                                                <a href="tolak.php?id=<?php echo escapeOutput((string) $aset_id); ?>" class="btn btn-danger btn-sm" title="Tolak aset PID">
                                                    <i class="bi bi-x-lg"></i> Tolak
                                                </a>
                                            <?php else: ?>
                                                <span class="read-only-note align-self-center">
                                                    <i class="bi bi-lock me-1"></i>
                                                    <?php echo $ialah_pid ? 'Tiada tindakan' : 'Read-only'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        ?>
                        <div class="card-footer bg-white border-0 p-3 p-lg-4">
                            <nav aria-label="Paginasi senarai aset">
                                <ul class="pagination justify-content-center justify-content-md-end mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page > 1 ? escapeOutput($buildPageUrl($page - 1)) : '#'; ?>"
                                            aria-label="Halaman sebelumnya"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo escapeOutput($buildPageUrl(1)); ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($page_number = $start_page; $page_number <= $end_page; $page_number++): ?>
                                        <li class="page-item <?php echo $page_number === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo escapeOutput($buildPageUrl($page_number)); ?>">
                                                <?php echo escapeOutput((string) $page_number); ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo escapeOutput($buildPageUrl($total_pages)); ?>">
                                                <?php echo escapeOutput((string) $total_pages); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page < $total_pages ? escapeOutput($buildPageUrl($page + 1)) : '#'; ?>"
                                            aria-label="Halaman seterusnya"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
