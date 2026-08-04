<?php
require_once __DIR__ . '/_init.php';

$page_title = 'Sejarah Import';
$page_subtitle = 'Audit semua fail aset yang pernah diimport oleh akaun anda.';
$active_page = 'history';

$userId = (int) $import_context['pengguna_id'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sqlCount = "
    SELECT COUNT(*) AS jumlah
    FROM import_batch
    WHERE pengguna_id = ?
";

$stmtCount = mysqli_prepare($conn, $sqlCount);
mysqli_stmt_bind_param($stmtCount, 'i', $userId);
mysqli_stmt_execute($stmtCount);
$total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCount))['jumlah'] ?? 0);
mysqli_stmt_close($stmtCount);

$totalPages = max(1, (int) ceil($total / $perPage));

$sql = "
    SELECT *
    FROM import_batch
    WHERE pengguna_id = ?
    ORDER BY import_batch_id DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'iii', $userId, $perPage, $offset);
mysqli_stmt_execute($stmt);
$batches = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$modes = importModeLabels();

require __DIR__ . '/layout_top.php';
?>

<section class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 fw-bold mb-0">Senarai Batch Import</h2>
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Import Baharu
        </a>
    </div>

    <?php if ($batches === []): ?>
        <div class="empty-state">
            <i class="bi bi-clock-history"></i>
            Tiada sejarah import.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Fail</th>
                    <th>Mod</th>
                    <th>Jumlah</th>
                    <th>Sah</th>
                    <th>Berjaya</th>
                    <th>Gagal</th>
                    <th>Status</th>
                    <th>Tarikh</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td>#<?php echo (int) $batch['import_batch_id']; ?></td>
                        <td><strong><?php echo escapeOutput($batch['nama_fail']); ?></strong></td>
                        <td><?php echo escapeOutput($modes[$batch['mod_import']]['title'] ?? $batch['mod_import']); ?></td>
                        <td><?php echo (int) $batch['jumlah_baris']; ?></td>
                        <td><?php echo (int) $batch['jumlah_sah']; ?></td>
                        <td class="text-success fw-bold"><?php echo (int) $batch['jumlah_berjaya']; ?></td>
                        <td class="text-danger fw-bold"><?php echo (int) $batch['jumlah_gagal']; ?></td>
                        <td>
                            <span class="badge bg-<?php
                                echo match ($batch['status_batch']) {
                                    'selesai' => 'success',
                                    'gagal' => 'danger',
                                    default => 'warning text-dark',
                                };
                            ?>">
                                <?php echo escapeOutput(ucfirst($batch['status_batch'])); ?>
                            </span>
                        </td>
                        <td><?php echo escapeOutput(formatDateTime($batch['tarikh_mula'])); ?></td>
                        <td>
                            <?php if ($batch['status_batch'] === 'selesai'): ?>
                                <a href="hasil.php?id=<?php echo (int) $batch['import_batch_id']; ?>"
                                   class="btn btn-sm btn-outline-primary">Hasil</a>
                            <?php else: ?>
                                <a href="preview.php?id=<?php echo (int) $batch['import_batch_id']; ?>"
                                   class="btn btn-sm btn-outline-primary">Preview</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="card-body border-top">
            <ul class="pagination mb-0">
                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                    <li class="page-item <?php echo $number === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $number; ?>">
                            <?php echo $number; ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>
