<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/dashboard_analytics.php';
require_once 'pengarah_common.php';

pengarahRequireAccess();

$role = 'Pengarah';
$raw_filters = pengarahGetFilters();

$location = analyticsResolveLocation(
    $conn,
    $role,
    0,
    (int) $raw_filters['wilayah_id'],
    (int) $raw_filters['daerah_id'],
    (int) $raw_filters['agensi_id']
);

$filters = $raw_filters;
$filters['wilayah_id'] = (int) $location['wilayah_id'];
$filters['daerah_id'] = (int) $location['daerah_id'];
$filters['agensi_id'] = (int) $location['agensi_id'];
$location_errors = $location['errors'];

$dropdown = pengarahDropdownData($conn, $filters);
$where = pengarahBuildWhere($filters);

$stats = [
    'jumlah' => 0,
    'lulus' => 0,
    'proses' => 0,
    'ditolak' => 0,
    'rosak' => 0,
    'selenggara' => 0,
];
$region_labels = [];
$region_values = [];
$district_labels = [];
$district_values = [];
$type_labels = [];
$type_values = [];
$latest = [];
$data_error = '';

try {
    $row = pengarahFetchOne(
        $conn,
        "SELECT
            COUNT(a.aset_id) AS jumlah,
            COALESCE(SUM(a.status_workflow_id = 8), 0) AS lulus,
            COALESCE(SUM(a.status_workflow_id IN (2, 4, 6)), 0) AS proses,
            COALESCE(SUM(a.status_workflow_id IN (3, 5, 7)), 0) AS ditolak,
            COALESCE(SUM(a.status_aset_id = 2), 0) AS rosak,
            COALESCE(SUM(a.status_aset_id = 3), 0) AS selenggara
         FROM aset a
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         WHERE {$where['sql']}",
        $where['types'],
        $where['params']
    ) ?? [];

    foreach ($stats as $key => $value) {
        $stats[$key] = (int) ($row[$key] ?? 0);
    }

    $region_rows = pengarahFetchAll(
        $conn,
        "SELECT COALESCE(w.nama_wilayah, 'Tidak Ditetapkan') AS label,
                COUNT(a.aset_id) AS jumlah
         FROM aset a
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         WHERE {$where['sql']}
         GROUP BY a.wilayah_id, w.nama_wilayah
         ORDER BY jumlah DESC, label",
        $where['types'],
        $where['params']
    );

    foreach ($region_rows as $chart_row) {
        $region_labels[] = (string) $chart_row['label'];
        $region_values[] = (int) $chart_row['jumlah'];
    }

    $district_rows = pengarahFetchAll(
        $conn,
        "SELECT
            CASE
                WHEN a.wilayah_id = 1 THEN 'Ibu Pejabat — Tiada Daerah'
                ELSE COALESCE(d.nama_daerah, 'Tanpa Daerah')
            END AS label,
            COUNT(a.aset_id) AS jumlah
         FROM aset a
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN daerah d ON d.daerah_id = ag.daerah_id
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         WHERE {$where['sql']}
         GROUP BY a.wilayah_id, ag.daerah_id, d.nama_daerah
         ORDER BY jumlah DESC, label",
        $where['types'],
        $where['params']
    );

    foreach ($district_rows as $chart_row) {
        $district_labels[] = (string) $chart_row['label'];
        $district_values[] = (int) $chart_row['jumlah'];
    }

    $type_rows = pengarahFetchAll(
        $conn,
        "SELECT a.jenis_aset AS label, COUNT(a.aset_id) AS jumlah
         FROM aset a
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         WHERE {$where['sql']}
         GROUP BY a.jenis_aset
         ORDER BY jumlah DESC",
        $where['types'],
        $where['params']
    );

    foreach ($type_rows as $chart_row) {
        $type_labels[] = (string) $chart_row['label'];
        $type_values[] = (int) $chart_row['jumlah'];
    }

    $latest = pengarahFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.status_workflow_id,
            ag.nama_agensi,
            d.nama_daerah,
            w.nama_wilayah,
            sw.status AS status_workflow,
            sw.warna AS warna_workflow
         FROM aset a
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN daerah d ON d.daerah_id = ag.daerah_id
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         LEFT JOIN status_workflow sw ON sw.status_workflow_id = a.status_workflow_id
         WHERE {$where['sql']}
         ORDER BY COALESCE(a.tarikh_kemaskini, a.tarikh_input) DESC, a.aset_id DESC
         LIMIT 12",
        $where['types'],
        $where['params']
    );
} catch (Throwable $e) {
    error_log('Dashboard Pengarah Cascading: ' . $e->getMessage());
    $data_error = 'Sebahagian data analitik tidak dapat dimuatkan.';
}

$filter_wilayah = (int) $filters['wilayah_id'];
$filter_daerah = (int) $filters['daerah_id'];
$filter_agensi = (int) $filters['agensi_id'];
$filter_tahun = (int) $filters['tahun'];
$filter_jenis = (string) $filters['jenis_aset'];
$filter_status = (int) $filters['status_workflow_id'];
$filter_status_aset = (int) $filters['status_aset_id'];

$jenis_aset_dibenarkan = ['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
$options_location = [
    'wilayah' => $dropdown['wilayah'],
    'daerah' => $dropdown['daerah'],
    'agensi' => $dropdown['agensi'],
];
$options_common = [
    'tahun' => $dropdown['tahun'],
    'workflow' => $dropdown['workflow'],
    'status_aset' => $dropdown['status_aset'],
];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analitik Pengarah - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pengarah.css">
</head>
<body class="pengarah page-dashboard">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 analytics-sidebar director p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-bar-chart-fill"></i></span>
                <div>
                    <div class="fw-bold fs-4">JTDIS</div>
                    <small class="text-white-50">Analitik Seluruh Sabah</small>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-badge"></i>Pengarah
            </div>

            <nav class="nav flex-column">
                <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
<a class="nav-link" href="senarai_aset.php"><i class="bi bi-boxes"></i> Semua Aset</a>
<a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a>

                <hr class="nav-separator">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside>
        <main class="col-md-9 col-xl-10 analytics-main">
            <div class="page-hero">
                <div>
                    <h1 class="page-title">Dashboard Pengarah</h1>
                    <p class="page-subtitle">Analitik aset ICT seluruh Sabah dengan penapis Wilayah → Daerah → Agensi.</p>
                </div>
                <div class="page-chip"><i class="bi bi-eye"></i>READ-ONLY</div>
            </div>

            <?php foreach ($location_errors as $message): ?>
                <div class="alert alert-warning"><?php echo escapeOutput($message); ?></div>
            <?php endforeach; ?>

            <?php if ($data_error !== ''): ?>
                <div class="alert alert-danger"><?php echo escapeOutput($data_error); ?></div>
            <?php endif; ?>

            <div class="alert scope-note director-note">
                <i class="bi bi-shield-lock-fill me-2"></i>
                Pengarah boleh menapis dan menganalisis semua aset tetapi kekal
                read-only. Bagi Ibu Pejabat, penapis daerah dilangkau dan agensi
                boleh dipilih terus.
            </div>

            
<section
    class="card filter-card mb-4"
    data-cascading-filter
    data-api-base="/jdtis_asset/pages/analytics-api"
    data-fixed-wilayah="0"
    data-selected-daerah="<?php echo (int) $filter_daerah; ?>"
    data-selected-agensi="<?php echo (int) $filter_agensi; ?>"
>
    <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2">
        <div>
            <h2 class="h5 fw-bold mb-1">
                <i class="bi bi-funnel me-2"></i>Penapis Analitik
            </h2>
            <div class="location-sequence">
                <span>Wilayah</span>
                <i class="bi bi-chevron-right"></i>
                <span>Daerah Kelolaan</span>
                <i class="bi bi-chevron-right"></i>
                <span>Agensi</span>
            </div>
        </div>

        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary align-self-start">
            Reset
        </a>
    </div>

    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6 col-xl-2">
                <label for="wilayah_id" class="form-label">Wilayah</label>
                <select id="wilayah_id" name="wilayah_id" class="form-select">
                    <option value="0">Semua Wilayah</option>
                    <?php foreach ($options_location['wilayah'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['wilayah_id']; ?>"
                            <?php echo $filter_wilayah === (int) $option['wilayah_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($option['nama_wilayah']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label for="daerah_id" class="form-label">Daerah Kelolaan</label>
                <select
                    id="daerah_id"
                    name="daerah_id"
                    class="form-select"
                    <?php echo $filter_wilayah <= 0 || $filter_wilayah === 1 ? 'disabled' : ''; ?>
                >
                    <option value="0">
                        <?php
                        echo $filter_wilayah === 1
                            ? 'Tidak berkenaan — Ibu Pejabat'
                            : ($filter_wilayah > 1 ? 'Semua Daerah' : 'Pilih wilayah dahulu');
                        ?>
                    </option>
                    <?php foreach ($options_location['daerah'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['daerah_id']; ?>"
                            <?php echo $filter_daerah === (int) $option['daerah_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($option['nama_daerah']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-3">
                <label for="agensi_id" class="form-label">Agensi</label>
                <select
                    id="agensi_id"
                    name="agensi_id"
                    class="form-select"
                    <?php
                    echo (
                        $filter_wilayah <= 0
                        || ($filter_wilayah > 1 && $filter_daerah <= 0)
                    ) ? 'disabled' : '';
                    ?>
                >
                    <option value="0">
                        <?php
                        echo $filter_wilayah === 1
                            ? 'Semua Agensi Ibu Pejabat'
                            : ($filter_daerah > 0 ? 'Semua Agensi' : 'Pilih daerah dahulu');
                        ?>
                    </option>
                    <?php foreach ($options_location['agensi'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['agensi_id']; ?>"
                            <?php echo $filter_agensi === (int) $option['agensi_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($option['nama_agensi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label for="tahun_beli" class="form-label">Tahun Beli</label>
                <select id="tahun_beli" name="tahun_beli" class="form-select">
                    <option value="0">Semua Tahun</option>
                    <?php foreach ($options_common['tahun'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['tahun_beli']; ?>"
                            <?php echo $filter_tahun === (int) $option['tahun_beli'] ? 'selected' : ''; ?>
                        >
                            <?php echo (int) $option['tahun_beli']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-2">
                <label for="jenis_aset" class="form-label">Jenis Aset</label>
                <select id="jenis_aset" name="jenis_aset" class="form-select">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_aset_dibenarkan as $jenis): ?>
                        <option
                            value="<?php echo escapeOutput($jenis); ?>"
                            <?php echo $filter_jenis === $jenis ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($jenis); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-3">
                <label for="status_workflow_id" class="form-label">Status Workflow</label>
                <select id="status_workflow_id" name="status_workflow_id" class="form-select">
                    <option value="0">Semua Status</option>
                    <?php foreach ($options_common['workflow'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['status_workflow_id']; ?>"
                            <?php echo $filter_status === (int) $option['status_workflow_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($option['status']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-3">
                <label for="status_aset_id" class="form-label">Status Fizikal</label>
                <select id="status_aset_id" name="status_aset_id" class="form-select">
                    <option value="0">Semua Status Fizikal</option>
                    <?php foreach ($options_common['status_aset'] as $option): ?>
                        <option
                            value="<?php echo (int) $option['status_aset_id']; ?>"
                            <?php echo $filter_status_aset === (int) $option['status_aset_id'] ? 'selected' : ''; ?>
                        >
                            <?php echo escapeOutput($option['status']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 col-xl-2 ms-xl-auto">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Tapis
                </button>
            </div>
        </form>
    </div>
</section>


            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl"><div class="stat-card stat-blue"><div class="stat-icon"><i class="bi bi-database"></i></div><div class="stat-value"><?php echo $stats['jumlah']; ?></div><div class="stat-label">Jumlah Aset</div></div></div>
                <div class="col-sm-6 col-xl"><div class="stat-card stat-green"><div class="stat-icon"><i class="bi bi-check-circle"></i></div><div class="stat-value"><?php echo $stats['lulus']; ?></div><div class="stat-label">Lulus</div></div></div>
                <div class="col-sm-6 col-xl"><div class="stat-card stat-orange"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="stat-value"><?php echo $stats['proses']; ?></div><div class="stat-label">Dalam Proses</div></div></div>
                <div class="col-sm-6 col-xl"><div class="stat-card stat-red"><div class="stat-icon"><i class="bi bi-x-circle"></i></div><div class="stat-value"><?php echo $stats['ditolak']; ?></div><div class="stat-label">Ditolak</div></div></div>
                <div class="col-sm-6 col-xl"><div class="stat-card stat-cyan"><div class="stat-icon"><i class="bi bi-tools"></i></div><div class="stat-value"><?php echo $stats['selenggara']; ?></div><div class="stat-label">Selenggara</div></div></div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-6">
                    <section class="card h-100">
                        <div class="card-header"><h2 class="h5 fw-bold mb-0">Aset Mengikut Wilayah</h2></div>
                        <div class="card-body chart-wrap"><canvas id="regionChart"></canvas></div>
                    </section>
                </div>
                <div class="col-xl-6">
                    <section class="card h-100">
                        <div class="card-header"><h2 class="h5 fw-bold mb-0">Aset Mengikut Daerah</h2></div>
                        <div class="card-body chart-wrap"><canvas id="districtChart"></canvas></div>
                    </section>
                </div>
            </div>

            <section class="card mb-4">
                <div class="card-header"><h2 class="h5 fw-bold mb-0">Aset Mengikut Jenis</h2></div>
                <div class="card-body chart-wrap"><canvas id="typeChart"></canvas></div>
            </section>

            <section class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 fw-bold mb-0">Rekod Mengikut Penapis</h2>
                    <a href="senarai_aset.php" class="btn btn-sm btn-outline-primary">Senarai Penuh</a>
                </div>
                <?php if ($latest === []): ?>
                    <div class="empty-state"><i class="bi bi-inbox"></i>Tiada rekod menepati penapis.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>No. Pendaftaran</th><th>Jenis / Model</th><th>Lokasi</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($latest as $aset): ?>
                                <tr>
                                    <td><strong><?php echo escapeOutput($aset['no_pendaftaran']); ?></strong></td>
                                    <td><?php echo escapeOutput($aset['jenis_aset']); ?> · <?php echo escapeOutput($aset['model'] ?: '-'); ?></td>
                                    <td>
                                        <?php echo escapeOutput($aset['nama_wilayah'] ?: '-'); ?>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($aset['nama_daerah'] ?: 'Tiada Daerah'); ?>
                                            ·
                                            <?php echo escapeOutput($aset['nama_agensi'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-<?php echo analyticsBootstrapColor($aset['warna_workflow']); ?>"><?php echo escapeOutput($aset['status_workflow']); ?></span></td>
                                    <td><a href="lihat.php?id=<?php echo (int) $aset['aset_id']; ?>" class="btn btn-sm btn-outline-primary">Lihat</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="/jdtis_asset/pages/analytics-api/cascading_filters.js"></script>
<script>
new Chart(document.getElementById('regionChart'), {
    type: 'bar',
    data: { labels: <?php echo json_encode($region_labels); ?>, datasets: [{ label: 'Jumlah Aset', data: <?php echo json_encode($region_values); ?> }] },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('districtChart'), {
    type: 'bar',
    data: { labels: <?php echo json_encode($district_labels); ?>, datasets: [{ label: 'Jumlah Aset', data: <?php echo json_encode($district_values); ?> }] },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: { labels: <?php echo json_encode($type_labels); ?>, datasets: [{ data: <?php echo json_encode($type_values); ?> }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
</script>
</body>
</html>
