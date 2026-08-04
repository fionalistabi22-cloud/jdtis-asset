<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Dashboard
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
$nama_penuh = (string) ($_SESSION['nama_penuh'] ?? 'Pegawai PID');

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

$flash_success = (string) ($_SESSION['flash_success'] ?? '');
$flash_error = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$agensi = [
    'nama_agensi' => 'Agensi Tidak Ditetapkan',
    'jenis_agensi' => '-',
];

$statistik = [
    'menunggu_kb' => 0,
    'lulus' => 0,
    'ditolak_kb' => 0,
    'jumlah_semua' => 0,
];

$aset_ditolak = [];
$aset_menunggu = [];
$ralat_data = '';
$csrf_token = generateCSRFToken();

$formatTarikh = static function ($nilai): string {
    if (empty($nilai)) {
        return '-';
    }

    $masa = strtotime((string) $nilai);
    return $masa !== false ? date('d/m/Y H:i', $masa) : '-';
};

try {
    $sql_agensi = "
        SELECT nama_agensi, jenis_agensi
        FROM agensi
        WHERE agensi_id = ?
          AND wilayah_id = 1
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
    mysqli_stmt_close($stmt_agensi);

    if (!$row_agensi) {
        throw new RuntimeException('Agensi PID tidak ditemui atau bukan agensi Ibu Pejabat.');
    }

    $agensi = $row_agensi;

    $sql_statistik = "
        SELECT
            COALESCE(SUM(CASE WHEN a.status_workflow_id = 6 THEN 1 ELSE 0 END), 0) AS menunggu_kb,
            COALESCE(SUM(CASE WHEN a.status_workflow_id = 8 THEN 1 ELSE 0 END), 0) AS lulus,
            COALESCE(SUM(CASE WHEN a.status_workflow_id = 7 THEN 1 ELSE 0 END), 0) AS ditolak_kb,
            COUNT(a.aset_id) AS jumlah_semua
        FROM aset a
        WHERE a.agensi_id = ?
          AND a.wilayah_id = 1
    ";

    $stmt_statistik = mysqli_prepare($conn, $sql_statistik);
    if (!$stmt_statistik) {
        throw new RuntimeException('Gagal menyediakan query statistik.');
    }

    mysqli_stmt_bind_param($stmt_statistik, 'i', $agensi_id);
    if (!mysqli_stmt_execute($stmt_statistik)) {
        throw new RuntimeException('Gagal mendapatkan statistik aset.');
    }

    $row_statistik = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_statistik));
    mysqli_stmt_close($stmt_statistik);

    if ($row_statistik) {
        $statistik = [
            'menunggu_kb' => (int) ($row_statistik['menunggu_kb'] ?? 0),
            'lulus' => (int) ($row_statistik['lulus'] ?? 0),
            'ditolak_kb' => (int) ($row_statistik['ditolak_kb'] ?? 0),
            'jumlah_semua' => (int) ($row_statistik['jumlah_semua'] ?? 0),
        ];
    }

    $sql_ditolak = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.tarikh_input,
            (
                SELECT lw.catatan
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.tindakan = 'Tolak Bahagian'
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS nota_tolak,
            (
                SELECT lw.tarikh
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.tindakan = 'Tolak Bahagian'
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS tarikh_tolak
        FROM aset a
        WHERE a.agensi_id = ?
          AND a.wilayah_id = 1
          AND a.status_workflow_id = 7
        ORDER BY COALESCE(tarikh_tolak, a.tarikh_kemaskini, a.tarikh_input) DESC, a.aset_id DESC
        LIMIT 5
    ";

    $stmt_ditolak = mysqli_prepare($conn, $sql_ditolak);
    if (!$stmt_ditolak) {
        throw new RuntimeException('Gagal menyediakan query aset ditolak.');
    }

    mysqli_stmt_bind_param($stmt_ditolak, 'i', $agensi_id);
    if (!mysqli_stmt_execute($stmt_ditolak)) {
        throw new RuntimeException('Gagal mendapatkan aset ditolak.');
    }

    $aset_ditolak = mysqli_fetch_all(mysqli_stmt_get_result($stmt_ditolak), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_ditolak);

    $sql_menunggu = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.tarikh_input,
            (
                SELECT lw.tarikh
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.status_workflow_ke = 6
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS tarikh_hantar
        FROM aset a
        WHERE a.agensi_id = ?
          AND a.wilayah_id = 1
          AND a.status_workflow_id = 6
        ORDER BY COALESCE(tarikh_hantar, a.tarikh_input) ASC, a.aset_id ASC
        LIMIT 5
    ";

    $stmt_menunggu = mysqli_prepare($conn, $sql_menunggu);
    if (!$stmt_menunggu) {
        throw new RuntimeException('Gagal menyediakan query aset menunggu.');
    }

    mysqli_stmt_bind_param($stmt_menunggu, 'i', $agensi_id);
    if (!mysqli_stmt_execute($stmt_menunggu)) {
        throw new RuntimeException('Gagal mendapatkan aset menunggu.');
    }

    $aset_menunggu = mysqli_fetch_all(mysqli_stmt_get_result($stmt_menunggu), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_menunggu);
} catch (Throwable $e) {
    error_log('Dashboard PID: ' . $e->getMessage());
    $ralat_data = 'Sebahagian data dashboard tidak dapat dimuatkan. Sila cuba semula atau hubungi pentadbir sistem.';
}

$baki_ditolak = max(0, $statistik['ditolak_kb'] - count($aset_ditolak));
$baki_menunggu = max(0, $statistik['menunggu_kb'] - count($aset_menunggu));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PID - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-dashboard">
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
                <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                <a class="nav-link" href="senarai_aset.php"><i class="bi bi-boxes me-2"></i> Senarai Aset</a>
                <a class="nav-link" href="tambah.php"><i class="bi bi-plus-circle me-2"></i> Daftar Aset Baru</a>
                <a class="nav-link" href="laporan.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Laporan</a>
                <hr class="w-100 sidebar-divider">
                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left me-2"></i> Log Keluar</a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                <div>
                    <h1 class="h2 fw-bold mb-1">Dashboard PID</h1>
                    <p class="text-muted mb-0">
                        Selamat datang, <strong><?php echo escapeOutput($nama_penuh); ?></strong>
                        | <strong><?php echo escapeOutput((string) $agensi['nama_agensi']); ?></strong>
                    </p>
                </div>
                <span class="badge rounded-pill bg-light text-dark border px-3 py-2">
                    <i class="bi bi-building me-1"></i>
                    <?php echo escapeOutput(ucwords(str_replace('_', ' ', (string) $agensi['jenis_agensi']))); ?>
                </span>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo escapeOutput($flash_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo escapeOutput($flash_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>

            <?php if ($ralat_data !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-database-exclamation me-2"></i><?php echo escapeOutput($ralat_data); ?></div>
            <?php endif; ?>

            <div class="agency-banner p-3 p-lg-4 mb-4">
                <div class="d-flex gap-3 align-items-start">
                    <i class="bi bi-shield-check fs-4"></i>
                    <div>
                        <strong>Skop akses PID:</strong> anda hanya boleh mengurus aset bagi
                        <strong><?php echo escapeOutput((string) $agensi['nama_agensi']); ?></strong>.
                        Aset baharu dihantar terus kepada Ketua Bahagian untuk semakan.
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <a class="stat-link" href="senarai_aset.php?filter=pending">
                        <div class="card stat-card pending"><div class="card-body d-flex justify-content-between align-items-start">
                            <div><div class="stat-number"><?php echo $statistik['menunggu_kb']; ?></div><div class="stat-label">Menunggu Kelulusan KB</div></div>
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        </div></div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="stat-link" href="senarai_aset.php?filter=approved">
                        <div class="card stat-card approved"><div class="card-body d-flex justify-content-between align-items-start">
                            <div><div class="stat-number"><?php echo $statistik['lulus']; ?></div><div class="stat-label">Diluluskan</div></div>
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        </div></div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="stat-link" href="senarai_aset.php?filter=rejected">
                        <div class="card stat-card rejected"><div class="card-body d-flex justify-content-between align-items-start">
                            <div><div class="stat-number"><?php echo $statistik['ditolak_kb']; ?></div><div class="stat-label">Ditolak KB</div></div>
                            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                        </div></div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="stat-link" href="senarai_aset.php">
                        <div class="card stat-card total"><div class="card-body d-flex justify-content-between align-items-start">
                            <div><div class="stat-number"><?php echo $statistik['jumlah_semua']; ?></div><div class="stat-label">Jumlah Aset</div></div>
                            <div class="stat-icon"><i class="bi bi-database"></i></div>
                        </div></div>
                    </a>
                </div>
            </div>
            <section class="mb-4">
                <?php if ($statistik['ditolak_kb'] > 0): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                        <div>
                            <strong><?php echo $statistik['ditolak_kb']; ?> aset memerlukan perhatian anda</strong>
                            — ditolak oleh Ketua Bahagian. Sila semak sebab penolakan, betulkan data dan hantar semula.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success d-flex gap-2 align-items-start" role="alert">
                        <i class="bi bi-check-circle-fill mt-1"></i>
                        <div><strong>Tiada aset ditolak.</strong> Semua permohonan PID berada dalam keadaan baik.</div>
                    </div>
                <?php endif; ?>

                <div class="card content-card">
                    <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h2 class="h5 mb-1">Aset Ditolak Ketua Bahagian</h2>
                                <small class="text-muted">Senarai keutamaan yang memerlukan pembetulan segera.</small>
                            </div>
                            <?php if ($statistik['ditolak_kb'] > 5): ?>
                                <a href="senarai_aset.php?filter=rejected" class="btn btn-outline-danger btn-sm">Lihat Semua</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($aset_ditolak === []): ?>
                        <div class="empty-state">
                            <i class="bi bi-check2-circle"></i>
                            <h3 class="h6">Tiada aset ditolak</h3>
                            <p class="mb-0">Tiada pembetulan diperlukan pada masa ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>No. Pendaftaran</th>
                                    <th>Jenis</th>
                                    <th>Model</th>
                                    <th>Sebab Ditolak</th>
                                    <th>Tarikh</th>
                                    <th>Tindakan</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($aset_ditolak as $aset): ?>
                                    <tr>
                                        <td>
                                            <a class="asset-number" href="lihat.php?id=<?php echo (int) $aset['aset_id']; ?>">
                                                <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                            </a>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo escapeOutput((string) $aset['jenis_aset']); ?></span></td>
                                        <td><?php echo escapeOutput((string) ($aset['model'] ?: '-')); ?></td>
                                        <td class="reject-note">
                                            <?php echo nl2br(escapeOutput((string) ($aset['nota_tolak'] ?: 'Tiada catatan diberikan.'))); ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php echo escapeOutput($formatTarikh($aset['tarikh_tolak'] ?? $aset['tarikh_input'])); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="edit.php?id=<?php echo (int) $aset['aset_id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                                </a>
                                                <form method="POST" action="hantar_semula.php" class="d-inline"
                                                      onsubmit="return confirm('Hantar semula aset ini kepada Ketua Bahagian?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                    <input type="hidden" name="aset_id" value="<?php echo (int) $aset['aset_id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-send me-1"></i> Hantar Semula
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($baki_ditolak > 0): ?>
                            <div class="card-footer bg-white border-0 text-center">
                                <a href="senarai_aset.php?filter=rejected" class="text-danger fw-semibold text-decoration-none">
                                    Lihat <?php echo $baki_ditolak; ?> lagi aset ditolak →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card content-card mb-4">
                <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <h2 class="h5 mb-1">Aset Menunggu Kelulusan KB</h2>
                            <small class="text-muted">Aset sedang disemak dan tidak boleh diedit buat sementara waktu.</small>
                        </div>
                        <?php if ($statistik['menunggu_kb'] > 5): ?>
                            <a href="senarai_aset.php?filter=pending" class="btn btn-outline-warning btn-sm">Lihat Semua</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($aset_menunggu === []): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3 class="h6">Tiada aset menunggu kelulusan</h3>
                        <p class="mb-0">Daftar aset baharu untuk dihantar terus kepada Ketua Bahagian.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>No. Pendaftaran</th>
                                <th>Jenis</th>
                                <th>Model</th>
                                <th>Tarikh Hantar</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($aset_menunggu as $aset): ?>
                                <tr>
                                    <td>
                                        <a class="asset-number" href="lihat.php?id=<?php echo (int) $aset['aset_id']; ?>">
                                            <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo escapeOutput((string) $aset['jenis_aset']); ?></span></td>
                                    <td><?php echo escapeOutput((string) ($aset['model'] ?: '-')); ?></td>
                                    <td class="text-nowrap">
                                        <?php echo escapeOutput($formatTarikh($aset['tarikh_hantar'] ?? $aset['tarikh_input'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Kelulusan KB
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($baki_menunggu > 0): ?>
                        <div class="card-footer bg-white border-0 text-center">
                            <a href="senarai_aset.php?filter=pending" class="text-warning-emphasis fw-semibold text-decoration-none">
                                Lihat <?php echo $baki_menunggu; ?> lagi aset menunggu →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <div class="text-center py-3">
                <a href="tambah.php" class="btn btn-success btn-lg quick-action">
                    <i class="bi bi-plus-circle me-2"></i> Daftar Aset Baru
                </a>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
