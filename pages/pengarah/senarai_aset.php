<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Pengarah - Senarai Semua Aset Seluruh Sabah
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'pengarah_common.php';

pengarahRequireAccess();

$filters = pengarahGetFilters();
$dropdown = pengarahDropdownData($conn);
$where = pengarahBuildWhere($filters);

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page !== false && $page !== null && $page > 0) ? (int) $page : 1;
$per_page = 25;

$rows = [];
$total_records = 0;
$total_pages = 1;
$ralat_data = '';

try {
    $sql_count = "
        SELECT COUNT(a.aset_id) AS jumlah
        FROM aset a
        LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
        LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
        WHERE {$where['sql']}
    ";

    $count = pengarahFetchOne($conn, $sql_count, $where['types'], $where['params']);
    $total_records = (int) ($count['jumlah'] ?? 0);
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
            a.jenis_perolehan,
            a.jenama,
            a.model,
            a.tahun_beli,
            a.pegawai_nama,
            a.status_workflow_id,
            a.status_aset_id,
            w.nama_wilayah,
            ag.nama_agensi,
            sw.status AS status_workflow,
            sa.status AS status_aset
        FROM aset a
        LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
        LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
        LEFT JOIN status_workflow sw ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa ON sa.status_aset_id = a.status_aset_id
        WHERE {$where['sql']}
        ORDER BY
            COALESCE(a.tarikh_kemaskini, a.tarikh_input) DESC,
            a.aset_id DESC
        LIMIT ? OFFSET ?
    ";

    $types = $where['types'] . 'ii';
    $params = $where['params'];
    $params[] = $per_page;
    $params[] = $offset;

    $rows = pengarahFetchAll($conn, $sql_data, $types, $params);
} catch (Throwable $e) {
    error_log('Senarai Pengarah: ' . $e->getMessage());
    $ralat_data = 'Senarai aset tidak dapat dimuatkan.';
}

$base_query = pengarahQueryString($filters);
$export_query = $base_query !== '' ? '?' . $base_query : '';
$current_count = count($rows);
$rekod_mula = $total_records > 0 ? (($page - 1) * $per_page) + 1 : 0;
$rekod_akhir = $total_records > 0 ? $rekod_mula + $current_count - 1 : 0;

$buildPageUrl = static function (int $target) use ($filters): string {
    $query = pengarahQueryString($filters, ['page' => $target]);
    return 'senarai_aset.php?' . $query;
};
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Aset - Pengarah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pengarah.css">
</head>
<body class="pengarah page-senarai-aset">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-bar-chart-fill"></i></span>
                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Pengarah</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                <a class="nav-link active" href="senarai_aset.php"><i class="bi bi-boxes me-2"></i> Semua Aset</a>
                <a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Laporan</a>
                <hr class="w-100 sidebar-divider">
                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left me-2"></i> Log Keluar</a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1">Semua Aset Seluruh Sabah</h1>
                    <p class="text-muted mb-0">Semua status, wilayah dan jabatan. Paparan sahaja.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-self-lg-start">
                    <a href="export_excel.php<?php echo escapeOutput($export_query); ?>" class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                    </a>
                    <a href="export_pdf.php<?php echo escapeOutput($export_query); ?>" class="btn btn-outline-danger">
                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                    </a>
                </div>
            </div>

            <?php if ($ralat_data !== ''): ?>
                <div class="alert alert-danger"><?php echo escapeOutput($ralat_data); ?></div>
            <?php endif; ?>

            <div class="alert read-only-banner">
                <i class="bi bi-eye-fill me-1"></i>
                Pengarah mempunyai akses READ-ONLY. Tiada butang lulus, tolak, edit atau daftar disediakan.
            </div>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <h2 class="h5 mb-3"><i class="bi bi-funnel me-1"></i> Carian dan Penapis</h2>

                    <form method="GET" class="row g-3">
                        <div class="col-12 col-xl-4">
                            <label class="form-label">Carian</label>
                            <input type="search" name="q" class="form-control"
                                   value="<?php echo escapeOutput($filters['q']); ?>"
                                   placeholder="No. pendaftaran, model, pegawai, agensi atau wilayah">
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label class="form-label">Wilayah</label>
                            <select name="wilayah_id" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($dropdown['wilayah'] as $item): ?>
                                    <option value="<?php echo (int) $item['wilayah_id']; ?>"
                                        <?php echo $filters['wilayah_id'] === (int) $item['wilayah_id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($item['nama_wilayah']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label">Jabatan / Agensi</label>
                            <select name="agensi_id" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($dropdown['agensi'] as $item): ?>
                                    <option value="<?php echo (int) $item['agensi_id']; ?>"
                                        <?php echo $filters['agensi_id'] === (int) $item['agensi_id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($item['nama_agensi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label class="form-label">Tahun</label>
                            <select name="tahun" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($dropdown['tahun'] as $item): ?>
                                    <option value="<?php echo (int) $item['tahun_beli']; ?>"
                                        <?php echo $filters['tahun'] === (int) $item['tahun_beli'] ? 'selected' : ''; ?>>
                                        <?php echo (int) $item['tahun_beli']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-1">
                            <label class="form-label">Jenis</label>
                            <select name="jenis_aset" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach (['NB','PC','Pencetak','Monitor','Lain'] as $jenis): ?>
                                    <option value="<?php echo escapeOutput($jenis); ?>"
                                        <?php echo $filters['jenis_aset'] === $jenis ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($jenis); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label">Status Workflow</label>
                            <select name="status_workflow_id" class="form-select">
                                <option value="">Semua Status</option>
                                <?php foreach ($dropdown['workflow'] as $item): ?>
                                    <option value="<?php echo (int) $item['status_workflow_id']; ?>"
                                        <?php echo $filters['status_workflow_id'] === (int) $item['status_workflow_id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($item['status']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label">Status Fizikal</label>
                            <select name="status_aset_id" class="form-select">
                                <option value="">Semua Status</option>
                                <?php foreach ($dropdown['status_aset'] as $item): ?>
                                    <option value="<?php echo (int) $item['status_aset_id']; ?>"
                                        <?php echo $filters['status_aset_id'] === (int) $item['status_aset_id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($item['status']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label">Jenis Perolehan</label>
                            <select name="jenis_perolehan" class="form-select">
                                <option value="">Semua Jenis</option>
                                <?php foreach (['Kerajaan Negeri','Kerajaan Persekutuan','Sewa','Pinjaman','Lain'] as $item): ?>
                                    <option value="<?php echo escapeOutput($item); ?>"
                                        <?php echo $filters['jenis_perolehan'] === $item ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($item); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-director px-4" type="submit">
                                <i class="bi bi-search me-1"></i> Cari
                            </button>
                            <a href="senarai_aset.php" class="btn btn-outline-secondary px-4">Reset</a>
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
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Model</th>
                                <th>Wilayah</th>
                                <th>Agensi</th>
                                <th>Tahun</th>
                                <th>Workflow</th>
                                <th>Status Fizikal</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <a class="asset-number" href="lihat.php?id=<?php echo (int) $row['aset_id']; ?>">
                                            <?php echo escapeOutput($row['no_pendaftaran']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo escapeOutput($row['jenis_aset']); ?></td>
                                    <td><?php echo escapeOutput($row['model'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['nama_wilayah'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['nama_agensi'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['tahun_beli'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo pengarahWorkflowBadge((int) $row['status_workflow_id']); ?>">
                                            <?php echo escapeOutput($row['status_workflow'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo pengarahAssetBadge((int) $row['status_aset_id']); ?>">
                                            <?php echo escapeOutput($row['status_aset'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="lihat.php?id=<?php echo (int) $row['aset_id']; ?>"
                                           class="btn btn-info btn-sm">
                                            <i class="bi bi-eye me-1"></i> Lihat
                                        </a>
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
                        <div class="card-footer bg-white border-0">
                            <nav>
                                <ul class="pagination justify-content-end mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $page > 1 ? escapeOutput($buildPageUrl($page - 1)) : '#'; ?>">
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
                                        <a class="page-link" href="<?php echo $page < $total_pages ? escapeOutput($buildPageUrl($page + 1)) : '#'; ?>">
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
</body>
</html>
