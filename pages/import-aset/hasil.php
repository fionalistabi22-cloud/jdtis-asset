<?php
require_once __DIR__ . '/_init.php';

$page_title = 'Keputusan Import';
$page_subtitle = 'Ringkasan rekod yang berjaya, gagal atau dilangkau.';
$active_page = 'history';

$batchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int) $import_context['pengguna_id'];

if ($batchId === false || $batchId === null || $batchId < 1) {
    header('Location: sejarah.php');
    exit;
}

$batch = importGetBatch($conn, (int) $batchId, $userId);

if (!$batch) {
    $_SESSION['flash_error'] = 'Batch tidak ditemui.';
    header('Location: sejarah.php');
    exit;
}

$counts = importBatchCounts($conn, (int) $batchId);

$sqlSuccess = "
    SELECT
        import_item_id,
        nombor_baris,
        no_pendaftaran,
        nama_agensi,
        aset_id
    FROM import_batch_item
    WHERE import_batch_id = ?
      AND status_item = 'berjaya'
    ORDER BY nombor_baris ASC
    LIMIT 100
";

$stmtSuccess = mysqli_prepare($conn, $sqlSuccess);
mysqli_stmt_bind_param($stmtSuccess, 'i', $batchId);
mysqli_stmt_execute($stmtSuccess);
$successItems = mysqli_fetch_all(mysqli_stmt_get_result($stmtSuccess), MYSQLI_ASSOC);
mysqli_stmt_close($stmtSuccess);

require __DIR__ . '/layout_top.php';
?>

<div class="alert <?php echo $counts['berjaya'] > 0 ? 'alert-success' : 'alert-warning'; ?>">
    <h2 class="h5 fw-bold">
        <i class="bi bi-check-circle-fill me-2"></i>
        Import Selesai
    </h2>
    <p class="mb-0">
        Fail <strong><?php echo escapeOutput($batch['nama_fail']); ?></strong>
        telah selesai diproses.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Jumlah</div>
            <div class="kpi-value text-primary"><?php echo $counts['jumlah']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Berjaya</div>
            <div class="kpi-value text-success"><?php echo $counts['berjaya']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Gagal</div>
            <div class="kpi-value text-danger"><?php echo $counts['gagal']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Dilangkau</div>
            <div class="kpi-value text-warning"><?php echo $counts['dilangkau']; ?></div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="index.php" class="btn btn-primary">
        <i class="bi bi-cloud-arrow-up me-1"></i> Import Fail Lain
    </a>

    <a href="<?php echo escapeOutput($import_routes['assets']); ?>" class="btn btn-outline-primary">
        <i class="bi bi-box-seam me-1"></i> Lihat Senarai Aset
    </a>

    <?php if ($counts['gagal'] > 0 || $counts['dilangkau'] > 0): ?>
        <a
            href="muat_turun_ralat.php?id=<?php echo (int) $batchId; ?>"
            class="btn btn-outline-danger"
        >
            <i class="bi bi-download me-1"></i> Download Laporan Ralat
        </a>
    <?php endif; ?>
</div>

<section class="card">
    <div class="card-header">
        <h2 class="h5 fw-bold mb-0">Rekod Berjaya Diimport</h2>
    </div>

    <?php if ($successItems === []): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            Tiada rekod berjaya diimport.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>Baris</th>
                    <th>No. Pendaftaran</th>
                    <th>Agensi</th>
                    <th>Tindakan</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($successItems as $item): ?>
                    <tr>
                        <td><?php echo (int) $item['nombor_baris']; ?></td>
                        <td><strong><?php echo escapeOutput($item['no_pendaftaran']); ?></strong></td>
                        <td><?php echo escapeOutput($item['nama_agensi']); ?></td>
                        <td>
                            <a
                                href="<?php
                                    echo escapeOutput(
                                        $import_routes['asset_view_prefix'] .
                                        (int) $item['aset_id']
                                    );
                                ?>"
                                class="btn btn-sm btn-outline-primary"
                            >
                                <i class="bi bi-eye me-1"></i> Lihat
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>
