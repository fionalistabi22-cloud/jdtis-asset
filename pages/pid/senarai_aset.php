<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Senarai Aset
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

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

$carian = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$jenis_aset = trim((string) ($_GET['jenis_aset'] ?? ''));
$filter_ringkas = strtolower(trim((string) ($_GET['filter'] ?? '')));

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? (int) $page : 1;

$per_page = 20;

$status_dibenarkan = ['', '1', '6', '7', '8'];
$jenis_dibenarkan = ['', 'NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
$filter_dibenarkan = ['', 'pending', 'approved', 'rejected', 'draft'];

if (!in_array($status, $status_dibenarkan, true)) {
    $status = '';
}

if (!in_array($jenis_aset, $jenis_dibenarkan, true)) {
    $jenis_aset = '';
}

if (!in_array($filter_ringkas, $filter_dibenarkan, true)) {
    $filter_ringkas = '';
}

/*
 * Pautan kad dashboard menggunakan parameter filter ringkas.
 * Parameter status yang dipilih secara terus mempunyai keutamaan.
 */
if ($status === '' && $filter_ringkas !== '') {
    $peta_filter = [
        'pending' => '6',
        'approved' => '8',
        'rejected' => '7',
        'draft' => '1',
    ];

    $status = $peta_filter[$filter_ringkas] ?? '';
}

$flash_success = (string) ($_SESSION['flash_success'] ?? '');
$flash_error = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$senarai_aset = [];
$total_records = 0;
$total_pages = 1;
$ralat_data = '';
$nama_agensi = 'Agensi Tidak Ditetapkan';
$csrf_token = generateCSRFToken();

/**
 * Bind parameter dinamik kepada mysqli_stmt menggunakan rujukan.
 */
$bindDynamicParams = static function (
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {
    if ($types === '' || $params === []) {
        return;
    }

    $bind_args = [$stmt, $types];

    foreach ($params as &$value) {
        $bind_args[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $bind_args)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
};

$getWorkflowBadgeClass = static function (int $status_id): string {
    return match ($status_id) {
        1 => 'bg-secondary',
        6 => 'bg-warning text-dark',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
};

$getWorkflowName = static function (int $status_id, ?string $status_db): string {
    if ($status_db !== null && trim($status_db) !== '') {
        return $status_db;
    }

    return match ($status_id) {
        1 => 'Draf',
        6 => 'Menunggu KB',
        7 => 'Ditolak KB',
        8 => 'Lulus',
        default => 'Tidak diketahui',
    };
};

try {
    /*
     * Nama agensi PID.
     */
   $sql_agensi = "
    SELECT nama_agensi
    FROM agensi
    WHERE agensi_id = ?
    LIMIT 1
";

    $stmt_agensi = mysqli_prepare($conn, $sql_agensi);

    if (!$stmt_agensi) {
        throw new RuntimeException('Gagal menyediakan query agensi.');
    }

    mysqli_stmt_bind_param($stmt_agensi, 'i', $agensi_id);

    if (!mysqli_stmt_execute($stmt_agensi)) {
        throw new RuntimeException('Gagal mendapatkan maklumat agensi.');
    }

    $result_agensi = mysqli_stmt_get_result($stmt_agensi);
    $row_agensi = mysqli_fetch_assoc($result_agensi);

    if ($row_agensi) {
        $nama_agensi = (string) $row_agensi['nama_agensi'];
    } else {
       throw new RuntimeException('Agensi PID tidak ditemui.');
    }

    mysqli_stmt_close($stmt_agensi);

    /*
     * Skop keselamatan wajib:
     * - agensi PID sendiri
     * - wilayah_id = 1
     */
    $where_clauses = [
        'a.agensi_id = ?',
        'a.wilayah_id = 1',
    ];

    $where_types = 'i';
    $where_params = [$agensi_id];

    if ($carian !== '') {
        $where_clauses[] = "(
            a.no_pendaftaran LIKE ?
            OR a.model LIKE ?
            OR a.pegawai_nama LIKE ?
        )";

        $kata_carian = '%' . $carian . '%';

        for ($i = 0; $i < 3; $i++) {
            $where_types .= 's';
            $where_params[] = $kata_carian;
        }
    }

    if (in_array($status, ['1', '6', '7', '8'], true)) {
        $where_clauses[] = 'a.status_workflow_id = ?';
        $where_types .= 'i';
        $where_params[] = (int) $status;
    }

    if ($jenis_aset !== '') {
        $where_clauses[] = 'a.jenis_aset = ?';
        $where_types .= 's';
        $where_params[] = $jenis_aset;
    }

    $where_sql = implode("\n AND ", $where_clauses);

    /*
     * Kiraan rekod.
     */
    $sql_count = "
        SELECT COUNT(a.aset_id) AS jumlah
        FROM aset a
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

    /*
     * Senarai aset PID.
     */
    $sql_data = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.pegawai_nama,
            a.tahun_beli,
            a.status_workflow_id,
            a.status_aset_id,
            a.tarikh_input,
            a.tarikh_kemaskini,
            sw.status AS status_workflow,
            sa.status AS status_aset,
            (
                SELECT lw.catatan
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.tindakan = 'Tolak Bahagian'
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS nota_tolak
        FROM aset a
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa
            ON sa.status_aset_id = a.status_aset_id
        WHERE {$where_sql}
        ORDER BY
            CASE
                WHEN a.status_workflow_id = 7 THEN 0
                WHEN a.status_workflow_id = 6 THEN 1
                WHEN a.status_workflow_id = 1 THEN 2
                ELSE 3
            END,
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
    $senarai_aset = mysqli_fetch_all($result_data, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_data);
} catch (Throwable $e) {
    error_log('Senarai Aset PID: ' . $e->getMessage());
    $ralat_data = 'Senarai aset tidak dapat dimuatkan. Sila cuba semula atau hubungi pentadbir sistem.';
}

$current_count = count($senarai_aset);
$rekod_mula = $total_records > 0
    ? (($page - 1) * $per_page) + 1
    : 0;
$rekod_akhir = $total_records > 0
    ? $rekod_mula + $current_count - 1
    : 0;

$query_asas = [];

if ($carian !== '') {
    $query_asas['q'] = $carian;
}

if ($status !== '') {
    $query_asas['status'] = $status;
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
    <title>Senarai Aset PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-senarai-aset">
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

                <hr class="w-100 sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                <div>
                    <h1 class="page-title h2 mb-1">Senarai Aset PID</h1>
                    <p class="text-muted mb-0">
                        <?php echo escapeOutput($nama_agensi); ?> — hanya aset agensi anda dipaparkan.
                    </p>
                </div>

                <a href="tambah.php" class="btn btn-success btn-lg">
                    <i class="bi bi-plus-circle me-1"></i> Daftar Aset Baru
                </a>
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

            <?php if ($ralat_data !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-database-exclamation me-2"></i>
                    <?php echo escapeOutput($ralat_data); ?>
                </div>
            <?php endif; ?>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <h2 class="h5 mb-3">
                        <i class="bi bi-funnel me-2 text-primary"></i>Carian dan Penapis
                    </h2>

                    <form method="GET" action="senarai_aset.php">
                        <div class="row g-3">
                            <div class="col-12 col-xl-5">
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
                                        placeholder="No. pendaftaran, model atau nama pegawai"
                                    >
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <label for="status" class="form-label">Status Workflow</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>
                                        Semua Status
                                    </option>
                                    <option value="6" <?php echo $status === '6' ? 'selected' : ''; ?>>
                                        Menunggu KB
                                    </option>
                                    <option value="8" <?php echo $status === '8' ? 'selected' : ''; ?>>
                                        Diluluskan
                                    </option>
                                    <option value="7" <?php echo $status === '7' ? 'selected' : ''; ?>>
                                        Ditolak KB
                                    </option>
                                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>
                                        Draf
                                    </option>
                                </select>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label for="jenis_aset" class="form-label">Jenis Aset</label>
                                <select class="form-select" id="jenis_aset" name="jenis_aset">
                                    <option value="" <?php echo $jenis_aset === '' ? 'selected' : ''; ?>>
                                        Semua Jenis
                                    </option>

                                    <?php foreach (['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'] as $jenis): ?>
                                        <option
                                            value="<?php echo escapeOutput($jenis); ?>"
                                            <?php echo $jenis_aset === $jenis ? 'selected' : ''; ?>
                                        >
                                            <?php echo escapeOutput($jenis); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-sm-6 col-xl-2 d-grid align-self-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Cari
                                </button>
                            </div>

                            <div class="col-12">
                                <a href="senarai_aset.php" class="btn btn-outline-secondary">
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
                                Menunjukkan
                                <strong><?php echo number_format($current_count); ?></strong>
                                daripada
                                <strong><?php echo number_format($total_records); ?></strong>
                                rekod

                                <?php if ($total_records > 0): ?>
                                    <span class="ms-1">
                                        (rekod <?php echo number_format($rekod_mula); ?>–<?php echo number_format($rekod_akhir); ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <span class="badge bg-light text-dark border px-3 py-2">
                            <i class="bi bi-shield-check me-1"></i>
                            Skop: <?php echo escapeOutput($nama_agensi); ?>
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
                    <div class="table-responsive horizontal-scroll">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Model</th>
                                <th>Pegawai</th>
                                <th>Tahun</th>
                                <th>Status Workflow</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($senarai_aset as $aset): ?>
                                <?php
                                $aset_id = (int) $aset['aset_id'];
                                $status_id = (int) $aset['status_workflow_id'];
                                $status_aset_id = (int) $aset['status_aset_id'];
                                $boleh_edit = in_array($status_id, [1, 7], true);
                                $boleh_hantar = in_array($status_id, [1, 7], true);
                                $boleh_hapus = !in_array($status_id, [6, 8], true);
                                $status_name = $getWorkflowName(
                                    $status_id,
                                    $aset['status_workflow'] ?? null
                                );
                                ?>

                                <tr>
                                    <td>
                                        <a class="asset-number" href="lihat.php?id=<?php echo $aset_id; ?>">
                                            <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                        </a>

                                        <?php if ($status_aset_id === 5): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-dark">
                                                    <i class="bi bi-trash3 me-1"></i> Dilupuskan
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo escapeOutput((string) ($aset['model'] ?: '-')); ?></td>
                                    <td><?php echo escapeOutput((string) ($aset['pegawai_nama'] ?: '-')); ?></td>
                                    <td><?php echo escapeOutput((string) ($aset['tahun_beli'] ?: '-')); ?></td>

                                    <td>
                                        <span class="badge <?php echo escapeOutput($getWorkflowBadgeClass($status_id)); ?>">
                                            <?php echo escapeOutput($status_name); ?>
                                        </span>

                                        <?php if ($status_id === 7): ?>
                                            <small class="rejection-note">
                                                <i class="bi bi-exclamation-circle me-1"></i>
                                                <?php echo nl2br(
                                                    escapeOutput(
                                                        (string) (
                                                            $aset['nota_tolak']
                                                            ?: 'Tiada catatan penolakan diberikan.'
                                                        )
                                                    )
                                                ); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="action-group">
                                            <a
                                                href="lihat.php?id=<?php echo $aset_id; ?>"
                                                class="btn btn-info btn-sm"
                                                title="Lihat aset"
                                            >
                                                <i class="bi bi-eye me-1"></i> Lihat
                                            </a>

                                            <?php if ($boleh_edit): ?>
                                                <a
                                                    href="edit.php?id=<?php echo $aset_id; ?>"
                                                    class="btn btn-warning btn-sm"
                                                    title="Edit aset"
                                                >
                                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($boleh_hantar): ?>
                                                <form
                                                    method="POST"
                                                    action="hantar_semula.php"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Hantar aset ini kepada Ketua Bahagian?');"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php echo escapeOutput($csrf_token); ?>"
                                                    >
                                                    <input
                                                        type="hidden"
                                                        name="aset_id"
                                                        value="<?php echo $aset_id; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary btn-sm"
                                                        title="Hantar ke Ketua Bahagian"
                                                    >
                                                        <i class="bi bi-send me-1"></i> Hantar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($boleh_hapus): ?>
                                                <a
                                                    href="hapus.php?id=<?php echo $aset_id; ?>"
                                                    class="btn btn-danger btn-sm"
                                                    title="Lupuskan aset"
                                                    onclick="return confirm('Teruskan ke halaman pelupusan aset ini?');"
                                                >
                                                    <i class="bi bi-trash3 me-1"></i> Hapus
                                                </a>
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
                            <nav aria-label="Paginasi senarai aset PID">
                                <ul class="pagination justify-content-center justify-content-md-end mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page > 1
                                                ? escapeOutput($buildPageUrl($page - 1))
                                                : '#'; ?>"
                                            aria-label="Halaman sebelumnya"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo escapeOutput($buildPageUrl(1)); ?>">
                                                1
                                            </a>
                                        </li>

                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">…</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($page_number = $start_page; $page_number <= $end_page; $page_number++): ?>
                                        <li class="page-item <?php echo $page_number === $page ? 'active' : ''; ?>">
                                            <a
                                                class="page-link"
                                                href="<?php echo escapeOutput($buildPageUrl($page_number)); ?>"
                                            >
                                                <?php echo $page_number; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">…</span>
                                            </li>
                                        <?php endif; ?>

                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                href="<?php echo escapeOutput($buildPageUrl($total_pages)); ?>"
                                            >
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page < $total_pages
                                                ? escapeOutput($buildPageUrl($page + 1))
                                                : '#'; ?>"
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
