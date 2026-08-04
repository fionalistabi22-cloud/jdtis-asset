<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Wilayah - Senarai Aset Paparan Sahaja
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

if ($wilayah_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat wilayah pengguna tidak sah.';
    header('Location: dashboard.php');
    exit;
}

$filter_status = strtolower(trim((string) ($_GET['status_workflow'] ?? 'all')));
$filter_jenis_aset = trim((string) ($_GET['jenis_aset'] ?? 'all'));
$filter_agensi = trim((string) ($_GET['agensi_id'] ?? 'all'));
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

$status_dibenarkan = [
    'all',
    'menunggu_pptm',
    'ditolak_pptm',
    'menunggu_kw',
    'ditolak_kw',
    'menunggu_kb',
    'ditolak_kb',
    'lulus',
];
$jenis_dibenarkan = ['all', 'NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];

if (!in_array($filter_status, $status_dibenarkan, true)) {
    $filter_status = 'all';
}

if (!in_array($filter_jenis_aset, $jenis_dibenarkan, true)) {
    $filter_jenis_aset = 'all';
}

$flash_error = $_SESSION['flash_error'] ?? '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$senarai_agensi = [];
$rows = [];
$total_records = 0;
$total_pages = 1;
$ralat_data = '';

$bindDynamicParams = static function (mysqli_stmt $stmt, string $types, array &$params): void {
    if ($types === '' || $params === []) {
        return;
    }

    $args = [$stmt, $types];

    foreach ($params as &$value) {
        $args[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $args)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
};

$statusBadge = static function (int $status_id): string {
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
};

try {
    $sql_agensi = "
        SELECT agensi_id, nama_agensi
        FROM agensi
        WHERE wilayah_id = ?
        ORDER BY nama_agensi ASC
    ";

    $stmt_agensi = mysqli_prepare($conn, $sql_agensi);
    if (!$stmt_agensi) {
        throw new RuntimeException('Gagal menyediakan query agensi.');
    }

    mysqli_stmt_bind_param($stmt_agensi, 'i', $wilayah_id);

    if (!mysqli_stmt_execute($stmt_agensi)) {
        throw new RuntimeException('Gagal mendapatkan senarai agensi.');
    }

    $result_agensi = mysqli_stmt_get_result($stmt_agensi);
    $senarai_agensi = mysqli_fetch_all($result_agensi, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_agensi);

    /*
     * Skop wajib:
     * - wilayah pengguna sendiri
     * - semua status workflow 2 hingga 8
     */
    $conditions = [
        'a.wilayah_id = ?',
        'a.status_workflow_id IN (2, 3, 4, 5, 6, 7, 8)',
    ];
    $types = 'i';
    $params = [$wilayah_id];

    if ($search !== '') {
        $conditions[] = "(
            a.no_pendaftaran LIKE ?
            OR a.model LIKE ?
            OR a.pegawai_nama LIKE ?
            OR ag.nama_agensi LIKE ?
        )";

        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $status_map = [
        'menunggu_pptm' => 2,
        'ditolak_pptm' => 3,
        'menunggu_kw' => 4,
        'ditolak_kw' => 5,
        'menunggu_kb' => 6,
        'ditolak_kb' => 7,
        'lulus' => 8,
    ];

    if (isset($status_map[$filter_status])) {
        $conditions[] = 'a.status_workflow_id = ?';
        $types .= 'i';
        $params[] = $status_map[$filter_status];
    }

    if ($filter_jenis_aset !== 'all') {
        $conditions[] = 'a.jenis_aset = ?';
        $types .= 's';
        $params[] = $filter_jenis_aset;
    }

    if ($filter_agensi !== 'all' && ctype_digit($filter_agensi)) {
        $conditions[] = 'a.agensi_id = ?';
        $types .= 'i';
        $params[] = (int) $filter_agensi;
    } else {
        $filter_agensi = 'all';
    }

    $where_sql = implode("\n AND ", $conditions);

    $sql_count = "
        SELECT COUNT(*) AS total
        FROM aset a
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        WHERE {$where_sql}
    ";

    $stmt_count = mysqli_prepare($conn, $sql_count);
    if (!$stmt_count) {
        throw new RuntimeException('Gagal menyediakan query kiraan aset.');
    }

    $count_params = $params;
    $bindDynamicParams($stmt_count, $types, $count_params);

    if (!mysqli_stmt_execute($stmt_count)) {
        throw new RuntimeException('Gagal mendapatkan jumlah rekod aset.');
    }

    $row_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
    $total_records = (int) ($row_count['total'] ?? 0);
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
            a.pegawai_nama,
            a.tahun_beli,
            a.status_workflow_id,
            ag.nama_agensi,
            sw.status AS status_workflow
        FROM aset a
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        WHERE {$where_sql}
        ORDER BY
            COALESCE(a.tarikh_kemaskini, a.tarikh_input) DESC,
            a.aset_id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt_data = mysqli_prepare($conn, $sql_data);
    if (!$stmt_data) {
        throw new RuntimeException('Gagal menyediakan query senarai aset.');
    }

    $data_params = $params;
    $data_types = $types . 'ii';
    $data_params[] = $per_page;
    $data_params[] = $offset;
    $bindDynamicParams($stmt_data, $data_types, $data_params);

    if (!mysqli_stmt_execute($stmt_data)) {
        throw new RuntimeException('Gagal mendapatkan senarai aset.');
    }

    $result_data = mysqli_stmt_get_result($stmt_data);
    $rows = mysqli_fetch_all($result_data, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_data);
} catch (Throwable $e) {
    error_log('Senarai Aset Ketua Wilayah: ' . $e->getMessage());
    $ralat_data = 'Senarai aset tidak dapat dimuatkan. Sila cuba semula.';
}

$query_asas = [];

if ($search !== '') {
    $query_asas['search'] = $search;
}

if ($filter_status !== 'all') {
    $query_asas['status_workflow'] = $filter_status;
}

if ($filter_jenis_aset !== 'all') {
    $query_asas['jenis_aset'] = $filter_jenis_aset;
}

if ($filter_agensi !== 'all') {
    $query_asas['agensi_id'] = $filter_agensi;
}

$buildPageUrl = static function (int $target_page) use ($query_asas): string {
    $params = $query_asas;
    $params['page'] = $target_page;

    return 'senarai_aset.php?' . http_build_query($params);
};

$current_count = count($rows);
$rekod_mula = $total_records > 0 ? (($page - 1) * $per_page) + 1 : 0;
$rekod_akhir = $total_records > 0 ? $rekod_mula + $current_count - 1 : 0;
$nama_wilayah = getWilayahName($conn, $wilayah_id);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Aset Ketua Wilayah - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-wilayah.css">
</head>
<body class="ketua-wilayah page-senarai-aset">
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
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1">Senarai Aset Ketua Wilayah</h1>
                    <p class="text-muted mb-0">
                        <?php echo escapeOutput($nama_wilayah); ?> — semua status workflow 2 hingga 8 dipaparkan.
                    </p>
                </div>

                <span class="badge bg-light text-dark border align-self-lg-start px-3 py-2">
                    <i class="bi bi-shield-check me-1"></i>
                    Sahkan/Tolak untuk Menunggu KW
                </span>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo escapeOutput($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo escapeOutput($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($ralat_data !== ''): ?>
                <div class="alert alert-danger"><?php echo escapeOutput($ralat_data); ?></div>
            <?php endif; ?>

            <div class="alert alert-primary d-flex gap-2 align-items-start" role="alert">
                <i class="bi bi-shield-check mt-1"></i>
                <div>
                    Ketua Wilayah boleh menyemak semua aset dalam wilayah sendiri.
                    Butang <strong>Sahkan</strong> dan <strong>Tolak</strong> hanya tersedia untuk aset
                    berstatus <strong>Menunggu KW</strong>. Status lain adalah untuk paparan sahaja.
                </div>
            </div>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <h2 class="h5 mb-3">
                        <i class="bi bi-funnel text-primary me-1"></i> Carian dan Penapis
                    </h2>

                    <form method="GET" action="senarai_aset.php" class="row g-3 align-items-end">
                        <div class="col-12 col-xl-4">
                            <label for="search" class="form-label">Carian</label>
                            <input
                                type="search"
                                id="search"
                                name="search"
                                class="form-control"
                                value="<?php echo escapeOutput($search); ?>"
                                placeholder="No. pendaftaran, model, pegawai atau agensi"
                            >
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label for="status_workflow" class="form-label">Status Workflow</label>
                            <select id="status_workflow" name="status_workflow" class="form-select">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>
                                    Semua Status Dalam Skop
                                </option>
                                <option value="menunggu_pptm" <?php echo $filter_status === 'menunggu_pptm' ? 'selected' : ''; ?>>
                                    Menunggu PPTM
                                </option>
                                <option value="ditolak_pptm" <?php echo $filter_status === 'ditolak_pptm' ? 'selected' : ''; ?>>
                                    Ditolak PPTM
                                </option>
                                <option value="menunggu_kw" <?php echo $filter_status === 'menunggu_kw' ? 'selected' : ''; ?>>
                                    Menunggu KW
                                </option>
                                <option value="ditolak_kw" <?php echo $filter_status === 'ditolak_kw' ? 'selected' : ''; ?>>
                                    Ditolak KW
                                </option>
                                <option value="menunggu_kb" <?php echo $filter_status === 'menunggu_kb' ? 'selected' : ''; ?>>
                                    Menunggu KB
                                </option>
                                <option value="ditolak_kb" <?php echo $filter_status === 'ditolak_kb' ? 'selected' : ''; ?>>
                                    Ditolak KB
                                </option>
                                <option value="lulus" <?php echo $filter_status === 'lulus' ? 'selected' : ''; ?>>
                                    Lulus
                                </option>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label for="jenis_aset" class="form-label">Jenis Aset</label>
                            <select id="jenis_aset" name="jenis_aset" class="form-select">
                                <option value="all">Semua Jenis</option>
                                <?php foreach (['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'] as $jenis): ?>
                                    <option
                                        value="<?php echo escapeOutput($jenis); ?>"
                                        <?php echo $filter_jenis_aset === $jenis ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeOutput($jenis); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label for="agensi_id" class="form-label">Agensi</label>
                            <select id="agensi_id" name="agensi_id" class="form-select">
                                <option value="all">Semua Agensi</option>
                                <?php foreach ($senarai_agensi as $agensi): ?>
                                    <option
                                        value="<?php echo (int) $agensi['agensi_id']; ?>"
                                        <?php echo $filter_agensi === (string) $agensi['agensi_id'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeOutput($agensi['nama_agensi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-2 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Cari
                            </button>
                        </div>

                        <div class="col-12">
                            <a href="senarai_aset.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card content-card">
                <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
                    <h2 class="h5 mb-1">Rekod Aset</h2>
                    <div class="small text-muted">
                        Menunjukkan <?php echo $current_count; ?> daripada <?php echo $total_records; ?> rekod
                        <?php if ($total_records > 0): ?>
                            (rekod <?php echo $rekod_mula; ?>–<?php echo $rekod_akhir; ?>)
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($rows === []): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 opacity-25"></i>
                        <h3 class="h6 mt-3">Tiada rekod ditemui</h3>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Model</th>
                                <th>Pegawai</th>
                                <th>Agensi</th>
                                <th>Tahun</th>
                                <th>Status Workflow</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $status_id = (int) $row['status_workflow_id']; ?>
                                <tr>
                                    <td>
                                        <a
                                            class="asset-number"
                                            href="lihat.php?id=<?php echo (int) $row['aset_id']; ?>"
                                        >
                                            <?php echo escapeOutput($row['no_pendaftaran']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo escapeOutput($row['jenis_aset']); ?></td>
                                    <td><?php echo escapeOutput($row['model'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['pegawai_nama'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['nama_agensi'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['tahun_beli'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadge($status_id); ?>">
                                            <?php echo escapeOutput($row['status_workflow'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a
                                                class="btn btn-info btn-sm text-dark"
                                                href="lihat.php?id=<?php echo (int) $row['aset_id']; ?>"
                                                title="Lihat maklumat aset"
                                            >
                                                <i class="bi bi-eye me-1"></i> Lihat
                                            </a>

                                            <?php if ($status_id === 4): ?>
                                                <a
                                                    class="btn btn-success btn-sm"
                                                    href="lulus.php?id=<?php echo (int) $row['aset_id']; ?>"
                                                    title="Sahkan aset untuk dihantar kepada Ketua Bahagian"
                                                >
                                                    <i class="bi bi-check-circle me-1"></i> Sahkan
                                                </a>

                                                <a
                                                    class="btn btn-danger btn-sm"
                                                    href="tolak.php?id=<?php echo (int) $row['aset_id']; ?>"
                                                    title="Tolak aset"
                                                >
                                                    <i class="bi bi-x-circle me-1"></i> Tolak
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border align-self-center">
                                                    <i class="bi bi-eye me-1"></i> Paparan
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
                        <div class="card-footer bg-white border-0 p-3">
                            <nav aria-label="Paginasi senarai aset">
                                <ul class="pagination justify-content-end mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page > 1 ? escapeOutput($buildPageUrl($page - 1)) : '#'; ?>"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php for ($n = $start_page; $n <= $end_page; $n++): ?>
                                        <li class="page-item <?php echo $n === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo escapeOutput($buildPageUrl($n)); ?>">
                                                <?php echo $n; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a
                                            class="page-link"
                                            href="<?php echo $page < $total_pages ? escapeOutput($buildPageUrl($page + 1)) : '#'; ?>"
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