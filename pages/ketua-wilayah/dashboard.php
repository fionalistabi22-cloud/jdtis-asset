<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/dashboard_analytics.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (isSessionExpired()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['Ketua Wilayah']);

$role = (string) ($_SESSION['peranan'] ?? '');
$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$session_wilayah_id = (int) ($_SESSION['wilayah_id'] ?? 0);
$nama_penuh = (string) ($_SESSION['nama_penuh'] ?? 'Ketua Wilayah');

if ($pengguna_id <= 0 || $session_wilayah_id <= 1) {
    $_SESSION['flash_error'] = 'Maklumat akaun tidak lengkap.';
    header('Location: ../logout.php');
    exit;
}


$jenis_aset_dibenarkan = ['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
$sumber_dibenarkan = ['Juruteknik', 'Agen IT'];

$requested_daerah = analyticsInputInt('daerah_id');
$requested_agensi = analyticsInputInt('agensi_id');

$location = analyticsResolveLocation(
    $conn,
    $role,
    $session_wilayah_id,
    0,
    $requested_daerah,
    $requested_agensi
);

$filter_wilayah = (int) $location['wilayah_id'];
$filter_daerah = (int) $location['daerah_id'];
$filter_agensi = (int) $location['agensi_id'];
$location_errors = $location['errors'];

$filter_tahun = analyticsInputInt('tahun_beli');
$filter_jenis = analyticsInputString(
    'jenis_aset',
    $jenis_aset_dibenarkan
);
$filter_sumber = analyticsInputString(
    'sumber',
    $sumber_dibenarkan
);
$filter_status = analyticsInputInt('status_workflow_id');
$filter_status_aset = analyticsInputInt('status_aset_id');

$options_location = analyticsLocationOptions(
    $conn,
    $filter_wilayah,
    $filter_daerah
);
$options_common = analyticsCommonOptions($conn);


$where = [
    'a.wilayah_id = ?',
    'a.status_workflow_id IN (2, 3, 4, 5, 8)',
];
$types = 'i';
$params = [$filter_wilayah];

if ($filter_daerah > 0) {
    $where[] = 'ag.daerah_id = ?';
    $types .= 'i';
    $params[] = $filter_daerah;
}

if ($filter_agensi > 0) {
    $where[] = 'a.agensi_id = ?';
    $types .= 'i';
    $params[] = $filter_agensi;
}

if ($filter_tahun > 0) {
    $where[] = 'a.tahun_beli = ?';
    $types .= 'i';
    $params[] = $filter_tahun;
}

if ($filter_jenis !== '') {
    $where[] = 'a.jenis_aset = ?';
    $types .= 's';
    $params[] = $filter_jenis;
}

if ($filter_sumber !== '') {
    $where[] = 'r.nama_peranan = ?';
    $types .= 's';
    $params[] = $filter_sumber;
}

if ($filter_status > 0) {
    $where[] = 'a.status_workflow_id = ?';
    $types .= 'i';
    $params[] = $filter_status;
}

if ($filter_status_aset > 0) {
    $where[] = 'a.status_aset_id = ?';
    $types .= 'i';
    $params[] = $filter_status_aset;
}

$where_sql = implode(' AND ', $where);

$joins = "
    INNER JOIN pengguna p
        ON p.pengguna_id = a.pengguna_id_daftar
    INNER JOIN peranan r
        ON r.peranan_id = p.peranan_id
    LEFT JOIN agensi ag
        ON ag.agensi_id = a.agensi_id
    LEFT JOIN daerah d
        ON d.daerah_id = ag.daerah_id
    LEFT JOIN wilayah w
        ON w.wilayah_id = a.wilayah_id
    LEFT JOIN status_workflow sw
        ON sw.status_workflow_id = a.status_workflow_id
    LEFT JOIN status_aset sa
        ON sa.status_aset_id = a.status_aset_id
";

$stats = ['jumlah' => 0, 'utama' => 0, 'lulus' => 0, 'ditolak' => 0];
$source_counts = ['Juruteknik' => 0, 'Agen IT' => 0];
$district_labels = [];
$district_values = [];
$latest = [];
$nama_wilayah = 'Wilayah';
$data_error = '';

try {
    $region = analyticsFetchOne(
        $conn,
        "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ? LIMIT 1",
        'i',
        [$filter_wilayah]
    );
    $nama_wilayah = (string) ($region['nama_wilayah'] ?? 'Wilayah');

    $row = analyticsFetchOne(
        $conn,
        "SELECT
            COUNT(a.aset_id) AS jumlah,
            
            COALESCE(SUM(a.status_workflow_id = 4), 0) AS utama,
            COALESCE(SUM(a.status_workflow_id = 8), 0) AS lulus,
            COALESCE(SUM(a.status_workflow_id = 5), 0) AS ditolak
        
         FROM aset a
         {$joins}
         WHERE {$where_sql}",
        $types,
        $params
    );

    foreach ($stats as $key => $value) {
        $stats[$key] = (int) ($row[$key] ?? 0);
    }

    $source_rows = analyticsFetchAll(
        $conn,
        "SELECT
            r.nama_peranan AS sumber,
            COUNT(a.aset_id) AS jumlah
         FROM aset a
         {$joins}
         WHERE {$where_sql}
           AND r.nama_peranan IN ('Juruteknik', 'Agen IT')
         GROUP BY r.nama_peranan",
        $types,
        $params
    );

    foreach ($source_rows as $row_source) {
        $key = (string) $row_source['sumber'];
        if (isset($source_counts[$key])) {
            $source_counts[$key] = (int) $row_source['jumlah'];
        }
    }

    $district_rows = analyticsFetchAll(
        $conn,
        "SELECT
            COALESCE(d.nama_daerah, 'Tanpa Daerah') AS label,
            COUNT(a.aset_id) AS jumlah
         FROM aset a
         {$joins}
         WHERE {$where_sql}
         GROUP BY ag.daerah_id, d.nama_daerah
         ORDER BY jumlah DESC, label",
        $types,
        $params
    );

    foreach ($district_rows as $district_row) {
        $district_labels[] = (string) $district_row['label'];
        $district_values[] = (int) $district_row['jumlah'];
    }

    $latest = analyticsFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.status_workflow_id,
            ag.nama_agensi,
            d.nama_daerah,
            r.nama_peranan AS sumber,
            p.nama_penuh AS pendaftar,
            sw.status AS status_workflow,
            sw.warna AS warna_workflow
         FROM aset a
         {$joins}
         WHERE {$where_sql}
         ORDER BY
            COALESCE(a.tarikh_kemaskini, a.tarikh_input) DESC,
            a.aset_id DESC
         LIMIT 12",
        $types,
        $params
    );
} catch (Throwable $e) {
    error_log('Dashboard Analitik Ketua Wilayah: ' . $e->getMessage());
    $data_error = 'Sebahagian data analitik tidak dapat dimuatkan.';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analitik Ketua Wilayah - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-wilayah.css">
</head>
<body class="ketua-wilayah page-dashboard">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 analytics-sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <div class="fw-bold fs-4">JTDIS</div>
                    <small class="text-white-50">Kelulusan Aset Wilayah</small>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-badge"></i>Ketua Wilayah
            </div>

            <nav class="nav flex-column">
                <a class="nav-link active" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
<a class="nav-link" href="senarai_aset.php"><i class="bi bi-box-seam"></i> Senarai Aset</a>
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
                    <h1 class="page-title">Dashboard Analitik Ketua Wilayah</h1>
                    <p class="page-subtitle">Kelulusan aset mengikut daerah kelolaan dan agensi dalam wilayah anda.</p>
                </div>
                <div class="page-chip"><i class="bi bi-geo-alt"></i><?php echo escapeOutput($nama_wilayah); ?></div>
            </div>

            <?php foreach ($location_errors as $message): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($message); ?>
                </div>
            <?php endforeach; ?>

            <?php if ($data_error !== ''): ?>
                <div class="alert alert-danger">
                    <?php echo escapeOutput($data_error); ?>
                </div>
            <?php endif; ?>

            <div class="alert scope-note">
                <i class="bi bi-shield-check me-2"></i>Dashboard dikunci kepada wilayah Ketua Wilayah. Daerah yang dipaparkan hanya daerah di bawah kelolaan wilayah tersebut. Lulus/Tolak hanya bagi status Menunggu KW.
            </div>

            
<section
    class="card filter-card mb-4"
    data-cascading-filter
    data-api-base="/jdtis_asset/pages/analytics-api"
    data-fixed-wilayah="<?php echo (int) $filter_wilayah; ?>"
    data-selected-daerah="<?php echo (int) $filter_daerah; ?>"
    data-selected-agensi="<?php echo (int) $filter_agensi; ?>"
>
    <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2">
        <div>
            <h2 class="h5 fw-bold mb-1">
                <i class="bi bi-funnel me-2"></i>Penapis Analitik
            </h2>
            <div class="location-sequence">
                <span>Wilayah: <?php echo escapeOutput($nama_wilayah); ?></span>
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
            <div class="col-md-6 col-xl-3">
                <label for="daerah_id" class="form-label">Daerah Kelolaan</label>
                <select id="daerah_id" name="daerah_id" class="form-select">
                    <option value="0">Semua Daerah</option>
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
                <label for="agensi_id" class="form-label">Agensi Dalam Daerah</label>
                <select
                    id="agensi_id"
                    name="agensi_id"
                    class="form-select"
                    <?php echo $filter_daerah <= 0 ? 'disabled' : ''; ?>
                >
                    <option value="0">
                        <?php echo $filter_daerah > 0 ? 'Semua Agensi' : 'Pilih daerah dahulu'; ?>
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
                <label for="sumber" class="form-label">Sumber Pendaftaran</label>
                <select id="sumber" name="sumber" class="form-select">
                    <option value="">Semua Sumber</option>
                    <option value="Juruteknik" <?php echo $filter_sumber === 'Juruteknik' ? 'selected' : ''; ?>>
                        Juruteknik
                    </option>
                    <option value="Agen IT" <?php echo $filter_sumber === 'Agen IT' ? 'selected' : ''; ?>>
                        Agen IT
                    </option>
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
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-orange">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-value"><?php echo $stats['utama']; ?></div>
                        <div class="stat-label">Menunggu Keputusan KW</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-green">
                        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-value"><?php echo $stats['lulus']; ?></div>
                        <div class="stat-label">Diluluskan</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-red">
                        <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                        <div class="stat-value"><?php echo $stats['ditolak']; ?></div>
                        <div class="stat-label">Ditolak Ketua Wilayah</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card stat-blue">
                        <div class="stat-icon"><i class="bi bi-database"></i></div>
                        <div class="stat-value"><?php echo $stats['jumlah']; ?></div>
                        <div class="stat-label">Jumlah Aset Ditapis</div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-5">
                    <section class="card h-100">
                        <div class="card-header">
                            <h2 class="h5 fw-bold mb-0">Sumber Pendaftaran</h2>
                        </div>
                        <div class="card-body chart-wrap">
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </section>
                </div>
                <div class="col-xl-7">
                    <section class="card h-100">
                        <div class="card-header">
                            <h2 class="h5 fw-bold mb-0">Aset Mengikut Daerah Kelolaan</h2>
                        </div>
                        <div class="card-body chart-wrap">
                            <canvas id="districtChart"></canvas>
                        </div>
                    </section>
                </div>
            </div>

            <section class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 fw-bold mb-0">Rekod Mengikut Penapis</h2>
                    <a href="senarai_aset.php" class="btn btn-sm btn-outline-primary">Senarai Penuh</a>
                </div>

                <?php if ($latest === []): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>Tiada rekod menepati penapis.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Sumber</th>
                                <th>Daerah / Agensi</th>
                                <th>Status</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($latest as $aset): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo escapeOutput($aset['no_pendaftaran']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($aset['jenis_aset']); ?>
                                            ·
                                            <?php echo escapeOutput($aset['model'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $aset['sumber'] === 'Agen IT' ? 'bg-success' : 'bg-primary'; ?>">
                                            <?php echo escapeOutput($aset['sumber']); ?>
                                        </span>
                                        <div class="small text-muted mt-1">
                                            <?php echo escapeOutput($aset['pendaftar']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo escapeOutput($aset['nama_daerah'] ?: '-'); ?>
                                        <div class="small text-muted">
                                            <?php echo escapeOutput($aset['nama_agensi'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo analyticsBootstrapColor($aset['warna_workflow']); ?>">
                                            <?php echo escapeOutput($aset['status_workflow']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="lihat.php?id=<?php echo (int) $aset['aset_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Lihat
                                            </a>
                                            <?php if ((int) $aset['status_workflow_id'] === 4): ?>
                                                <a href="lulus.php?id=<?php echo (int) $aset['aset_id']; ?>" class="btn btn-sm btn-success">Lulus</a>
                                                <a href="tolak.php?id=<?php echo (int) $aset['aset_id']; ?>" class="btn btn-sm btn-danger">Tolak</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
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
new Chart(document.getElementById('sourceChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($source_counts)); ?>,
        datasets: [{ data: <?php echo json_encode(array_values($source_counts)); ?> }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

new Chart(document.getElementById('districtChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($district_labels); ?>,
        datasets: [{
            label: 'Jumlah Aset',
            data: <?php echo json_encode($district_values); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } }
        },
        plugins: { legend: { display: false } }
    }
});
</script>
</body>
</html>
