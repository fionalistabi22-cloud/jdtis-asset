<?php
require_once __DIR__ . '/_init.php';

$page_title = 'Preview Import';
$page_subtitle = 'Semak rekod sah dan ralat sebelum data dimasukkan ke dalam sistem.';
$active_page = 'import';

$batchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int) $import_context['pengguna_id'];

if ($batchId === false || $batchId === null || $batchId < 1) {
    $_SESSION['flash_error'] = 'ID batch tidak sah.';
    header('Location: index.php');
    exit;
}

$batch = importGetBatch($conn, (int) $batchId, $userId);

if (!$batch) {
    $_SESSION['flash_error'] = 'Batch import tidak ditemui.';
    header('Location: index.php');
    exit;
}

$counts = importBatchCounts($conn, (int) $batchId);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int) ceil($counts['jumlah'] / $perPage));

$sqlItems = "
    SELECT
        import_item_id,
        nombor_baris,
        no_pendaftaran,
        nama_agensi,
        status_item,
        mesej,
        aset_id
    FROM import_batch_item
    WHERE import_batch_id = ?
    ORDER BY nombor_baris ASC
    LIMIT ? OFFSET ?
";

$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param(
    $stmtItems,
    'iii',
    $batchId,
    $perPage,
    $offset
);
mysqli_stmt_execute($stmtItems);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmtItems), MYSQLI_ASSOC);
mysqli_stmt_close($stmtItems);

$modes = importModeLabels();
$csrf_token = generateCSRFToken();

require __DIR__ . '/layout_top.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Jumlah Rekod</div>
            <div class="kpi-value text-primary"><?php echo $counts['jumlah']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Sah untuk Import</div>
            <div class="kpi-value text-success"><?php echo $counts['sah']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Bermasalah</div>
            <div class="kpi-value text-danger"><?php echo $counts['gagal']; ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="text-muted">Kaedah Import</div>
            <div class="fw-bold mt-2">
                <?php echo escapeOutput(
                    $modes[$batch['mod_import']]['title'] ?? $batch['mod_import']
                ); ?>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle-fill me-2"></i>
    Fail: <strong><?php echo escapeOutput($batch['nama_fail']); ?></strong>.
    Rekod bermasalah tidak akan dimasukkan. Anda boleh memuat turun laporan ralat.
</div>

<section class="card">
    <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2">
        <div>
            <h2 class="h5 fw-bold mb-1">Senarai Preview</h2>
            <small class="text-muted">Menunjukkan sehingga 50 rekod bagi setiap halaman.</small>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($counts['gagal'] > 0 || $counts['dilangkau'] > 0): ?>
                <a
                    href="muat_turun_ralat.php?id=<?php echo (int) $batchId; ?>"
                    class="btn btn-outline-danger"
                >
                    <i class="bi bi-download me-1"></i> Laporan Ralat CSV
                </a>
            <?php endif; ?>

            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Import Lain
            </a>

            <?php if (
                $batch['status_batch'] === 'dipreview'
                && $counts['sah'] > 0
            ): ?>
                <form
                    method="POST"
                    action="proses_import.php"
                    onsubmit="return confirm('Import semua rekod yang sah sekarang?');"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escapeOutput($csrf_token); ?>"
                    >
                    <input
                        type="hidden"
                        name="batch_id"
                        value="<?php echo (int) $batchId; ?>"
                    >

                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-database-add me-1"></i>
                        Import <?php echo $counts['sah']; ?> Rekod Sah
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
            <tr>
                <th>Baris</th>
                <th>No. Pendaftaran</th>
                <th>Agensi</th>
                <th>Status</th>
                <th>Mesej</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php [$label, $class] = importStatusLabel($item['status_item']); ?>
                <tr>
                    <td><?php echo (int) $item['nombor_baris']; ?></td>
                    <td>
                        <strong><?php echo escapeOutput($item['no_pendaftaran'] ?: '-'); ?></strong>
                    </td>
                    <td><?php echo escapeOutput($item['nama_agensi'] ?: '-'); ?></td>
                    <td><span class="badge bg-<?php echo escapeOutput($class); ?>"><?php echo escapeOutput($label); ?></span></td>
                    <td>
                        <?php echo escapeOutput($item['mesej'] ?: 'Tiada ralat.'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="card-body border-top">
            <nav>
                <ul class="pagination mb-0">
                    <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                        <li class="page-item <?php echo $number === $page ? 'active' : ''; ?>">
                            <a
                                class="page-link"
                                href="?id=<?php echo (int) $batchId; ?>&page=<?php echo $number; ?>"
                            >
                                <?php echo $number; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>
