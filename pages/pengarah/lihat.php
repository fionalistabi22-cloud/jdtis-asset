<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Pengarah - Lihat Aset READ-ONLY
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once 'pengarah_common.php';

pengarahRequireAccess();

$aset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$aset = null;
$workflow_logs = [];
$maintenance_logs = [];

try {
    $aset = pengarahFetchOne(
        $conn,
        "SELECT
            a.*,
            w.nama_wilayah,
            ag.nama_agensi,
            d.nama_daerah,
            sw.status AS status_workflow,
            sa.status AS status_aset,
            p.nama_penuh AS pendaftar,
            ps.nama_penuh AS pengguna_semasa
         FROM aset a
         LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
         LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
         LEFT JOIN daerah d ON d.daerah_id = ag.daerah_id
         LEFT JOIN status_workflow sw ON sw.status_workflow_id = a.status_workflow_id
         LEFT JOIN status_aset sa ON sa.status_aset_id = a.status_aset_id
         LEFT JOIN pengguna p ON p.pengguna_id = a.pengguna_id_daftar
         LEFT JOIN pengguna ps ON ps.pengguna_id = a.pengguna_semasa_id
         WHERE a.aset_id = ?
         LIMIT 1",
        'i',
        [$aset_id]
    );

    if (!$aset) {
        throw new RuntimeException('Aset tidak ditemui.');
    }

    $workflow_logs = pengarahFetchAll(
        $conn,
        "SELECT
            lw.tindakan,
            lw.status_workflow_dari,
            lw.status_workflow_ke,
            lw.catatan,
            lw.tarikh,
            p.nama_penuh
         FROM log_workflow lw
         LEFT JOIN pengguna p ON p.pengguna_id = lw.oleh_pengguna_id
         WHERE lw.aset_id = ?
         ORDER BY lw.tarikh DESC, lw.log_id DESC",
        'i',
        [$aset_id]
    );

    $maintenance_logs = pengarahFetchAll(
        $conn,
        "SELECT
            ls.jenis_selenggara,
            ls.komponen_ditukar,
            ls.kos,
            ls.catatan,
            ls.status_selepas,
            ls.tarikh,
            p.nama_penuh
         FROM log_selenggara ls
         LEFT JOIN pengguna p ON p.pengguna_id = ls.dibuat_oleh
         WHERE ls.aset_id = ?
         ORDER BY ls.tarikh DESC, ls.log_id DESC",
        'i',
        [$aset_id]
    );
} catch (Throwable $e) {
    error_log('Lihat Pengarah: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Aset - Pengarah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pengarah.css">
</head>
<body class="pengarah page-lihat">
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
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <a href="senarai_aset.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                    <h1 class="h2 fw-bold mb-1">Maklumat Aset</h1>
                </div>
                <span class="badge read-only-badge px-3 py-2">
                    <i class="bi bi-eye me-1"></i> READ-ONLY
                </span>
            </div>

            <div class="alert read-only-banner">
                Pengarah hanya boleh melihat maklumat, log workflow dan rekod selenggara.
            </div>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                        <div>
                            <h2 class="h4 fw-bold mb-2"><?php echo escapeOutput($aset['no_pendaftaran']); ?></h2>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-secondary"><?php echo escapeOutput($aset['jenis_aset']); ?></span>
                                <span class="badge <?php echo pengarahWorkflowBadge((int) $aset['status_workflow_id']); ?>">
                                    <?php echo escapeOutput($aset['status_workflow'] ?? '-'); ?>
                                </span>
                                <span class="badge <?php echo pengarahAssetBadge((int) $aset['status_aset_id']); ?>">
                                    <?php echo escapeOutput($aset['status_aset'] ?? '-'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?php
                        $summary = [
                            'Wilayah' => $aset['nama_wilayah'] ?? '-',
                            'Agensi' => $aset['nama_agensi'] ?? '-',
                            'Daerah' => $aset['nama_daerah'] ?? '-',
                            'Jenis Perolehan' => $aset['jenis_perolehan'] ?? '-',
                            'Tahun Beli' => $aset['tahun_beli'] ?? '-',
                            'Pendaftar' => $aset['pendaftar'] ?? '-',
                        ];
                        ?>
                        <?php foreach ($summary as $label => $value): ?>
                            <div class="col-sm-6 col-lg-4">
                                <div class="detail-box">
                                    <span class="detail-label"><?php echo escapeOutput($label); ?></span>
                                    <div class="detail-value"><?php echo escapeOutput((string) $value); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <ul class="nav nav-pills mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#detail" type="button">
                        Maklumat Penuh
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#workflow" type="button">
                        Log Workflow
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#maintenance" type="button">
                        Log Selenggara
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="detail">
                    <section class="card content-card mb-3">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Butiran Aset</h2>
                            <div class="row g-3">
                                <div class="col-md-6"><strong>Jenama:</strong> <?php echo escapeOutput($aset['jenama'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Model:</strong> <?php echo escapeOutput($aset['model'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Processor:</strong> <?php echo escapeOutput($aset['processor'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>RAM:</strong> <?php echo escapeOutput($aset['ram'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Cakera Keras:</strong> <?php echo escapeOutput($aset['cakera_keras'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Sistem Operasi:</strong> <?php echo escapeOutput($aset['sistem_operasi'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Jenis Pencetak:</strong> <?php echo escapeOutput($aset['jenis_pencetak'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>No. Siri Pencetak:</strong> <?php echo escapeOutput($aset['no_siri_pencetak'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </section>

                    <section class="card content-card mb-3">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Pegawai / Pengguna Semasa</h2>
                            <div class="row g-3">
                                <div class="col-md-6"><strong>Nama:</strong> <?php echo escapeOutput($aset['pegawai_nama'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Jawatan:</strong> <?php echo escapeOutput($aset['pegawai_jawatan'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Gred:</strong> <?php echo escapeOutput($aset['pegawai_gred'] ?? '-'); ?></div>
                                <div class="col-md-6"><strong>Akaun Pengguna:</strong> <?php echo escapeOutput($aset['pengguna_semasa'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($aset['catatan'])): ?>
                        <section class="card content-card">
                            <div class="card-body">
                                <h2 class="h5">Catatan</h2>
                                <p class="mb-0"><?php echo nl2br(escapeOutput($aset['catatan'])); ?></p>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade" id="workflow">
                    <section class="card content-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Log Workflow</h2>
                            <?php if ($workflow_logs === []): ?>
                                <div class="text-center text-muted py-5">Tiada log workflow.</div>
                            <?php else: ?>
                                <?php foreach ($workflow_logs as $log): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-circle"><i class="bi bi-diagram-3"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between gap-2">
                                                <strong><?php echo escapeOutput($log['tindakan'] ?? '-'); ?></strong>
                                                <small class="text-muted">
                                                    <?php echo escapeOutput(pengarahFormatDate($log['tarikh'] ?? null)); ?>
                                                </small>
                                            </div>
                                            <div class="small text-muted">
                                                Oleh: <?php echo escapeOutput($log['nama_penuh'] ?? '-'); ?>
                                            </div>
                                            <?php if (!empty($log['catatan'])): ?>
                                                <div class="small mt-1"><?php echo nl2br(escapeOutput($log['catatan'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="tab-pane fade" id="maintenance">
                    <section class="card content-card">
                        <div class="card-body">
                            <h2 class="h5 mb-3">Log Selenggara</h2>
                            <?php if ($maintenance_logs === []): ?>
                                <div class="text-center text-muted py-5">Tiada rekod selenggara.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>Tarikh</th>
                                            <th>Jenis</th>
                                            <th>Komponen</th>
                                            <th>Kos</th>
                                            <th>Status Selepas</th>
                                            <th>Oleh</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($maintenance_logs as $log): ?>
                                            <tr>
                                                <td><?php echo escapeOutput(pengarahFormatDate($log['tarikh'] ?? null)); ?></td>
                                                <td><?php echo escapeOutput($log['jenis_selenggara'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($log['komponen_ditukar'] ?? '-'); ?></td>
                                                <td>RM <?php echo number_format((float) ($log['kos'] ?? 0), 2); ?></td>
                                                <td><?php echo escapeOutput($log['status_selepas'] ?? '-'); ?></td>
                                                <td><?php echo escapeOutput($log['nama_penuh'] ?? '-'); ?></td>
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
