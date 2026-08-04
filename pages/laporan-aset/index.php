<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/report_aset.php';

$context = reportRequireContext($conn);
$config = reportRoleConfig((string) $context['peranan']);
$resolved = reportResolveFilters($conn, $context, reportGetFilters());
$filters = $resolved['filters'];
$filterErrors = $resolved['errors'];
$dropdown = reportDropdowns($conn, $context, $filters);
$where = reportBuildWhere($context, $filters);
$summary = reportSummary($conn, $where);
$preview = reportFetchAssets($conn, $where, 50, 0);
$labels = reportFilterLabels($conn, $context, $filters);
$query = reportQueryString($filters);
$exportQuery = $query !== '' ? '?' . $query : '';
$role = (string) $context['peranan'];
$fixedRegion = $role === 'Ketua Wilayah' ? (int) $context['wilayah_id'] : 0;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo reportEscape($config['title']); ?> - JDTIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="report.css">
</head>
<body>
<div class="container-fluid">
<div class="row">
    <aside class="col-md-3 col-xl-2 report-sidebar <?php echo reportEscape($config['sidebar_class']); ?> p-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="brand-mark"><i class="bi <?php echo reportEscape($config['icon']); ?>"></i></span>
            <div><div class="fw-bold fs-4">JDTIS</div><small class="text-white-50">Laporan Aset ICT</small></div>
        </div>
        <div class="role-pill"><i class="bi bi-person-badge"></i><?php echo reportEscape($role); ?></div>
        <nav class="nav flex-column">
            <a class="nav-link" href="<?php echo reportEscape($config['dashboard']); ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a class="nav-link" href="<?php echo reportEscape($config['assets']); ?>"><i class="bi bi-box-seam"></i> Senarai Aset</a>
            <a class="nav-link active" href="index.php"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a>
            <hr style="border-color:rgba(255,255,255,.22)">
            <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
        </nav>
    </aside>

    <main class="col-md-9 col-xl-10 report-main">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
            <div>
                <h1 class="page-title"><?php echo reportEscape($config['title']); ?></h1>
                <p class="page-subtitle">Laporan ringkas berbentuk jadual seperti rekod Excel aset sedia ada.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-start">
                <a href="export_excel.php<?php echo reportEscape($exportQuery); ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i> Jana Excel</a>
                <a href="export_pdf.php<?php echo reportEscape($exportQuery); ?>" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i> Jana PDF</a>
            </div>
        </div>

        <?php foreach ($filterErrors as $message): ?>
            <div class="alert alert-warning"><?php echo reportEscape($message); ?></div>
        <?php endforeach; ?>

        <div class="alert scope-banner">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Skop:</strong> <?php echo reportEscape($config['scope']); ?>.
            Penjanaan laporan hanya berada pada halaman Laporan, bukan pada dashboard analitik.
        </div>

        <section class="card mb-4" data-report-filter data-fixed-wilayah="<?php echo $fixedRegion; ?>" data-api-base="<?php echo reportEscape(APP_URL . '/pages/laporan-aset'); ?>">
            <div class="card-header d-flex justify-content-between align-items-start">
                <div><h2 class="h5 fw-bold mb-1"><i class="bi bi-funnel me-2"></i>Penapis Laporan</h2><div class="filter-path"><span>Wilayah</span><i class="bi bi-chevron-right"></i><span>Daerah</span><i class="bi bi-chevron-right"></i><span>Agensi</span></div></div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label">Carian Aset</label>
                        <input type="search" name="q" class="form-control" value="<?php echo reportEscape($filters['q']); ?>" placeholder="No. daftar, model, pegawai...">
                    </div>

                    <?php if (in_array($role, ['Pengarah','Ketua Bahagian'], true)): ?>
                    <div class="col-md-6 col-xl-2"><label class="form-label">Wilayah</label><select id="wilayah_id" name="wilayah_id" class="form-select"><option value="0">Semua Wilayah</option><?php foreach ($dropdown['wilayah'] as $item): ?><option value="<?php echo (int)$item['wilayah_id']; ?>" <?php echo (int)$filters['wilayah_id']===(int)$item['wilayah_id']?'selected':''; ?>><?php echo reportEscape($item['nama_wilayah']); ?></option><?php endforeach; ?></select></div>
                    <?php elseif ($role === 'Ketua Wilayah'): ?>
                    <input type="hidden" name="wilayah_id" value="<?php echo (int)$filters['wilayah_id']; ?>">
                    <div class="col-md-6 col-xl-2"><label class="form-label">Wilayah</label><input class="form-control" value="<?php echo reportEscape($context['nama_wilayah']); ?>" disabled></div>
                    <?php else: ?>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Agensi PID</label><input class="form-control" value="<?php echo reportEscape($context['nama_agensi']); ?>" disabled></div>
                    <?php endif; ?>

                    <?php if ($role !== 'PID'): ?>
                    <div class="col-md-6 col-xl-2"><label class="form-label">Daerah</label><select id="daerah_id" name="daerah_id" class="form-select" <?php echo (int)$filters['wilayah_id']<=0 || (int)$filters['wilayah_id']===1?'disabled':''; ?>><option value="0"><?php echo (int)$filters['wilayah_id']===1?'Tidak berkenaan - Ibu Pejabat':((int)$filters['wilayah_id']>1?'Semua Daerah':'Pilih wilayah dahulu'); ?></option><?php foreach ($dropdown['daerah'] as $item): ?><option value="<?php echo (int)$item['daerah_id']; ?>" <?php echo (int)$filters['daerah_id']===(int)$item['daerah_id']?'selected':''; ?>><?php echo reportEscape($item['nama_daerah']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Agensi</label><select id="agensi_id" name="agensi_id" class="form-select" <?php echo (int)$filters['wilayah_id']<=0 || ((int)$filters['wilayah_id']>1 && (int)$filters['daerah_id']<=0)?'disabled':''; ?>><option value="0">Semua Agensi</option><?php foreach ($dropdown['agensi'] as $item): ?><option value="<?php echo (int)$item['agensi_id']; ?>" <?php echo (int)$filters['agensi_id']===(int)$item['agensi_id']?'selected':''; ?>><?php echo reportEscape($item['nama_agensi']); ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>

                    <div class="col-md-6 col-xl-2"><label class="form-label">Tahun Beli</label><select name="tahun_beli" class="form-select"><option value="0">Semua Tahun</option><?php foreach ($dropdown['tahun'] as $item): ?><option value="<?php echo (int)$item['tahun_beli']; ?>" <?php echo (int)$filters['tahun_beli']===(int)$item['tahun_beli']?'selected':''; ?>><?php echo (int)$item['tahun_beli']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 col-xl-2"><label class="form-label">Jenis Aset</label><select name="jenis_aset" class="form-select"><option value="">Semua Jenis</option><?php foreach (['NB','PC','Pencetak','Monitor','Lain'] as $item): ?><option value="<?php echo $item; ?>" <?php echo $filters['jenis_aset']===$item?'selected':''; ?>><?php echo $item; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Jenis Perolehan</label><select name="jenis_perolehan" class="form-select"><option value="">Semua Perolehan</option><?php foreach (['Kerajaan Negeri','Kerajaan Persekutuan','Sewa','Pinjaman','Lain'] as $item): ?><option value="<?php echo reportEscape($item); ?>" <?php echo $filters['jenis_perolehan']===$item?'selected':''; ?>><?php echo reportEscape($item); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Status Workflow</label><select name="status_workflow_id" class="form-select"><option value="0">Semua Status</option><?php foreach ($dropdown['workflow'] as $item): ?><option value="<?php echo (int)$item['status_workflow_id']; ?>" <?php echo (int)$filters['status_workflow_id']===(int)$item['status_workflow_id']?'selected':''; ?>><?php echo reportEscape($item['status']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Status Fizikal</label><select name="status_aset_id" class="form-select"><option value="0">Semua Status</option><?php foreach ($dropdown['status_aset'] as $item): ?><option value="<?php echo (int)$item['status_aset_id']; ?>" <?php echo (int)$filters['status_aset_id']===(int)$item['status_aset_id']?'selected':''; ?>><?php echo reportEscape($item['status']); ?></option><?php endforeach; ?></select></div>
                    <?php if ($role !== 'PID'): ?><div class="col-md-6 col-xl-2"><label class="form-label">Sumber</label><select name="sumber" class="form-select"><option value="">Semua Sumber</option><?php foreach (($role==='Ketua Wilayah'?['Juruteknik','Agen IT']:['Juruteknik','Agen IT','PID']) as $item): ?><option value="<?php echo $item; ?>" <?php echo $filters['sumber']===$item?'selected':''; ?>><?php echo $item; ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <div class="col-md-6 col-xl-2"><label class="form-label">Tarikh Dari</label><input type="date" name="tarikh_dari" class="form-control" value="<?php echo reportEscape($filters['tarikh_dari']); ?>"></div>
                    <div class="col-md-6 col-xl-2"><label class="form-label">Tarikh Hingga</label><input type="date" name="tarikh_hingga" class="form-control" value="<?php echo reportEscape($filters['tarikh_hingga']); ?>"></div>
                    <div class="col-md-6 col-xl-2 ms-xl-auto"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tapis</button></div>
                </form>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <?php
            $reportCards = [
                ['Jumlah Aset', 'jumlah', 'text-primary'],
                ['Lulus', 'lulus', 'text-success'],
                ['Dalam Proses', 'dalam_proses', 'text-warning'],
                ['Ditolak', 'ditolak', 'text-danger'],
                ['Rosak', 'rosak', 'text-danger'],
            ];
            ?>
            <?php foreach ($reportCards as [$label, $key, $class]): ?>
                <div class="col-sm-6 col-xl">
                    <div class="stat-card">
                        <div class="stat-label"><?php echo reportEscape($label); ?></div>
                        <div class="stat-value <?php echo reportEscape($class); ?>">
                            <?php echo number_format((int) $summary[$key]); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="card">
            <div class="card-header">
                <h2 class="h5 fw-bold mb-0">Preview Laporan Aset Ringkas</h2>
                <small class="text-muted">
                    Menunjukkan 50 rekod pertama daripada
                    <?php echo number_format((int) $summary['jumlah']); ?>
                    aset.
                </small>
            </div>

            <?php if ($preview === []): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Tiada rekod menepati penapis.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                        <tr>
                            <th>Bil.</th>
                            <th>Bahagian / Agensi</th>
                            <th>Nama Pegawai / Pengguna</th>
                            <th>Jawatan / Gred</th>
                            <th>Jenis</th>
                            <th>No. Pendaftaran</th>
                            <th>Perolehan / Tahun</th>
                            <th>Jenama / Model</th>
                            <th>Spesifikasi</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview as $index => $asset): ?>
                            <?php
                            $pegawai = trim((string) ($asset['pegawai_nama'] ?? ''));

                            if ($pegawai === '') {
                                $pegawai = trim(
                                    (string) ($asset['pengguna_semasa'] ?? '')
                                );
                            }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <?php echo reportEscape($asset['nama_agensi']); ?>
                                    <div class="small text-muted">
                                        <?php echo reportEscape($asset['nama_daerah'] ?: 'Tiada Daerah'); ?>
                                    </div>
                                </td>
                                <td><?php echo reportEscape(reportValue($pegawai)); ?></td>
                                <td>
                                    <?php echo reportEscape(reportValue($asset['pegawai_jawatan'])); ?>
                                    <div class="small text-muted">
                                        <?php echo reportEscape(reportValue($asset['pegawai_gred'])); ?>
                                    </div>
                                </td>
                                <td><?php echo reportEscape($asset['jenis_aset']); ?></td>
                                <td><strong><?php echo reportEscape($asset['no_pendaftaran']); ?></strong></td>
                                <td>
                                    <?php echo reportEscape($asset['jenis_perolehan']); ?>
                                    <div class="small text-muted">
                                        <?php echo reportEscape(reportValue($asset['tahun_beli'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo reportEscape(reportValue($asset['jenama'])); ?>
                                    <?php echo reportEscape(reportValue($asset['model'])); ?>
                                </td>
                                <td>
                                    <?php echo reportEscape(reportValue($asset['processor'])); ?>
                                    <div class="small text-muted">
                                        RAM: <?php echo reportEscape(reportValue($asset['ram'])); ?>
                                        · Storage:
                                        <?php echo reportEscape(reportValue($asset['cakera_keras'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo reportEscape($asset['status_aset']); ?>
                                    <div class="small text-muted">
                                        <?php echo reportEscape($asset['status_workflow']); ?>
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
<script src="report.js"></script>
</body>
</html>
