<?php
require_once __DIR__ . '/_init.php';

$page_title = 'Import Aset Pukal';
$page_subtitle = 'Upload fail Excel atau CSV, semak preview dan import semua rekod yang sah.';
$active_page = 'import';

$flash_success = (string) ($_SESSION['flash_success'] ?? '');
$flash_error = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$dependency_available = importPhpSpreadsheetAvailable();
$modes = importModeLabels();

$sql_history = "
    SELECT
        import_batch_id,
        nama_fail,
        mod_import,
        jumlah_baris,
        jumlah_berjaya,
        jumlah_gagal,
        status_batch,
        tarikh_mula,
        tarikh_selesai
    FROM import_batch
    WHERE pengguna_id = ?
    ORDER BY import_batch_id DESC
    LIMIT 5
";

$stmt_history = mysqli_prepare($conn, $sql_history);
$pengguna_id = (int) $import_context['pengguna_id'];
mysqli_stmt_bind_param($stmt_history, 'i', $pengguna_id);
mysqli_stmt_execute($stmt_history);
$history = mysqli_fetch_all(mysqli_stmt_get_result($stmt_history), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_history);

$csrf_token = generateCSRFToken();

require __DIR__ . '/layout_top.php';
?>

<?php if ($flash_success !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo escapeOutput($flash_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flash_error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo escapeOutput($flash_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$dependency_available): ?>
    <div class="alert alert-danger">
        <h2 class="h5">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            Library PhpSpreadsheet belum dipasang
        </h2>
        <p class="mb-2">
            Buka terminal dalam folder utama <code>jdtis_asset</code>, kemudian jalankan:
        </p>
        <pre class="bg-dark text-white rounded p-3 mb-0">composer require phpoffice/phpspreadsheet</pre>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="card">
            <div class="card-header">
                <h2 class="h5 fw-bold mb-0">
                    <i class="bi bi-cloud-arrow-up me-2 text-primary"></i>
                    Upload Fail Aset
                </h2>
            </div>

            <div class="card-body">
                <form
                    method="POST"
                    action="upload.php"
                    enctype="multipart/form-data"
                    onsubmit="this.querySelector('button[type=submit]').disabled = true;"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escapeOutput($csrf_token); ?>"
                    >

                    <div class="upload-zone mb-4">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <h3 class="h5 mt-2">Pilih fail Excel atau CSV</h3>
                        <p class="text-muted">
                            Format diterima: .xlsx, .xls dan .csv. Saiz maksimum 10 MB.
                        </p>

                        <input
                            type="file"
                            name="fail_aset"
                            class="form-control"
                            accept=".xlsx,.xls,.csv"
                            required
                            <?php echo !$dependency_available ? 'disabled' : ''; ?>
                        >
                    </div>

                    <label class="form-label">Kaedah Import</label>

                    <div class="row g-3 mb-4">
                        <?php foreach ($modes as $modeKey => $mode): ?>
                            <div class="col-md-4">
                                <div class="mode-card">
                                    <input
                                        type="radio"
                                        id="mode_<?php echo escapeOutput($modeKey); ?>"
                                        name="mod_import"
                                        value="<?php echo escapeOutput($modeKey); ?>"
                                        <?php echo $modeKey === 'draf' ? 'checked' : ''; ?>
                                    >

                                    <label for="mode_<?php echo escapeOutput($modeKey); ?>">
                                        <strong><?php echo escapeOutput($mode['title']); ?></strong>
                                        <p class="small text-muted mb-0 mt-2">
                                            <?php echo escapeOutput($mode['description']); ?>
                                        </p>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ((string) $import_context['peranan'] === 'Juruteknik'): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Fail Juruteknik wajib mempunyai kolum
                            <strong>nama_agensi</strong> atau <strong>agensi_id</strong>.
                            Agensi mesti berada dalam wilayah anda.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-shield-lock-fill me-2"></i>
                            Sistem akan memaksa semua rekod kepada agensi akaun anda:
                            <strong>
                                <?php echo escapeOutput(
                                    (string) ($import_context['nama_agensi'] ?? 'Tidak ditetapkan')
                                ); ?>
                            </strong>.
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <a
                                href="templates/template_import_aset.xlsx"
                                class="btn btn-outline-success"
                                download
                            >
                                <i class="bi bi-file-earmark-excel me-1"></i>
                                Template Excel
                            </a>

                            <a
                                href="templates/template_import_aset.csv"
                                class="btn btn-outline-secondary"
                                download
                            >
                                <i class="bi bi-filetype-csv me-1"></i>
                                Template CSV
                            </a>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary btn-lg"
                            <?php echo !$dependency_available ? 'disabled' : ''; ?>
                        >
                            <i class="bi bi-eye me-1"></i>
                            Upload dan Preview
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="card mb-4">
            <div class="card-header">
                <h2 class="h5 fw-bold mb-0">Proses Import</h2>
            </div>

            <div class="card-body step-list">
                <div class="step-item">
                    Download template dan salin data lama daripada Google Sheets atau Excel.
                </div>
                <div class="step-item">
                    Upload fail. Sistem menyemak format, skop agensi, data pendua dan nilai tidak sah.
                </div>
                <div class="step-item">
                    Semak preview. Hanya rekod berstatus Sah akan diimport.
                </div>
                <div class="step-item">
                    Muat turun laporan ralat untuk membetulkan rekod yang gagal.
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="h5 fw-bold mb-0">Skop Keselamatan</h2>
            </div>

            <div class="card-body">
                <ul class="mb-0">
                    <li class="mb-2">
                        <strong>Juruteknik:</strong> agensi dalam wilayah sendiri sahaja.
                    </li>
                    <li class="mb-2">
                        <strong>Agen IT:</strong> agensi akaun sendiri sahaja.
                    </li>
                    <li>
                        <strong>PID:</strong> agensi sendiri dan <code>wilayah_id = 1</code>.
                    </li>
                </ul>
            </div>
        </section>
    </div>
</div>

<section class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 fw-bold mb-0">Import Terkini</h2>
        <a href="sejarah.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
    </div>

    <?php if ($history === []): ?>
        <div class="empty-state">
            <i class="bi bi-clock-history"></i>
            Tiada sejarah import.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>Fail</th>
                    <th>Mod</th>
                    <th>Jumlah</th>
                    <th>Berjaya</th>
                    <th>Gagal</th>
                    <th>Tarikh</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $batch): ?>
                    <tr>
                        <td><strong><?php echo escapeOutput($batch['nama_fail']); ?></strong></td>
                        <td><?php echo escapeOutput($modes[$batch['mod_import']]['title'] ?? $batch['mod_import']); ?></td>
                        <td><?php echo (int) $batch['jumlah_baris']; ?></td>
                        <td class="text-success fw-bold"><?php echo (int) $batch['jumlah_berjaya']; ?></td>
                        <td class="text-danger fw-bold"><?php echo (int) $batch['jumlah_gagal']; ?></td>
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
</section>

<?php require __DIR__ . '/layout_bottom.php'; ?>
