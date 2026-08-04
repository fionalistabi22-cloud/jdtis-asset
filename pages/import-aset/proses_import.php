<?php
require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Token keselamatan tidak sah.';
    header('Location: index.php');
    exit;
}

$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$userId = (int) $import_context['pengguna_id'];

if ($batchId === false || $batchId === null || $batchId < 1) {
    $_SESSION['flash_error'] = 'ID batch tidak sah.';
    header('Location: index.php');
    exit;
}

$batch = importGetBatch($conn, (int) $batchId, $userId);

if (!$batch || $batch['status_batch'] !== 'dipreview') {
    $_SESSION['flash_error'] = 'Batch tidak tersedia untuk diproses.';
    header('Location: index.php');
    exit;
}

$role = (string) $batch['peranan'];
$workflowId = importTargetWorkflow($role, (string) $batch['mod_import']);

$sqlItems = "
    SELECT
        import_item_id,
        nombor_baris,
        no_pendaftaran,
        data_json
    FROM import_batch_item
    WHERE import_batch_id = ?
      AND status_item = 'sah'
    ORDER BY nombor_baris ASC
";

$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, 'i', $batchId);
mysqli_stmt_execute($stmtItems);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmtItems), MYSQLI_ASSOC);
mysqli_stmt_close($stmtItems);

if ($items === []) {
    $_SESSION['flash_error'] = 'Tiada rekod sah untuk diimport.';
    header('Location: preview.php?id=' . (int) $batchId);
    exit;
}

mysqli_begin_transaction($conn);

$successCount = 0;
$failedCount = 0;
$skippedCount = 0;

try {
    $sqlInsertAsset = "
        INSERT INTO aset (
            no_pendaftaran,
            jenis_aset,
            jenis_perolehan,
            tahun_beli,
            jenama,
            model,
            processor,
            ram,
            cakera_keras,
            sistem_operasi,
            spesifikasi_pencetak,
            jenis_pencetak,
            bil_pencetak_laser,
            bil_pencetak_inkjet,
            bil_pencetak_matrik,
            no_siri_pencetak,
            pegawai_nama,
            pegawai_jawatan,
            pegawai_gred,
            agensi_id,
            wilayah_id,
            status_workflow_id,
            status_aset_id,
            pengguna_id_daftar,
            catatan,
            tarikh_input,
            tarikh_kemaskini
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            NOW(), NOW()
        )
    ";

    $stmtAsset = mysqli_prepare($conn, $sqlInsertAsset);

    if (!$stmtAsset) {
        throw new RuntimeException('Gagal menyediakan query insert aset.');
    }

    $sqlLog = "
        INSERT INTO log_workflow (
            aset_id,
            tindakan,
            status_workflow_dari,
            status_workflow_ke,
            catatan,
            oleh_pengguna_id
        ) VALUES (
            ?,
            'Daftar',
            NULL,
            ?,
            ?,
            ?
        )
    ";

    $stmtLog = mysqli_prepare($conn, $sqlLog);

    if (!$stmtLog) {
        throw new RuntimeException('Gagal menyediakan query log workflow.');
    }

    $sqlUpdateItem = "
        UPDATE import_batch_item
        SET
            status_item = ?,
            aset_id = NULLIF(?, 0),
            mesej = ?
        WHERE import_item_id = ?
          AND import_batch_id = ?
    ";

    $stmtUpdateItem = mysqli_prepare($conn, $sqlUpdateItem);

    if (!$stmtUpdateItem) {
        throw new RuntimeException('Gagal menyediakan query status item.');
    }

    foreach ($items as $index => $item) {
        $itemId = (int) $item['import_item_id'];
        $savepoint = 'import_row_' . ($index + 1);
        mysqli_query($conn, 'SAVEPOINT ' . $savepoint);

        try {
            $data = json_decode((string) $item['data_json'], true, 512, JSON_THROW_ON_ERROR);
            $registration = (string) ($data['no_pendaftaran'] ?? '');

            if (importRegistrationExists($conn, $registration)) {
                $statusItem = 'dilangkau';
                $assetId = 0;
                $message = 'No. pendaftaran sudah wujud ketika import diproses.';

                mysqli_stmt_bind_param(
                    $stmtUpdateItem,
                    'sisii',
                    $statusItem,
                    $assetId,
                    $message,
                    $itemId,
                    $batchId
                );
                mysqli_stmt_execute($stmtUpdateItem);

                $skippedCount++;
                mysqli_query($conn, 'RELEASE SAVEPOINT ' . $savepoint);
                continue;
            }

            $params = [
                $registration,
                (string) $data['jenis_aset'],
                (string) $data['jenis_perolehan'],
                $data['tahun_beli'] !== null ? (int) $data['tahun_beli'] : null,
                (string) $data['jenama'],
                (string) $data['model'],
                (string) $data['processor'],
                (string) $data['ram'],
                (string) $data['cakera_keras'],
                (string) $data['sistem_operasi'],
                (string) $data['spesifikasi_pencetak'],
                (string) $data['jenis_pencetak'],
                (int) $data['bil_pencetak_laser'],
                (int) $data['bil_pencetak_inkjet'],
                (int) $data['bil_pencetak_matrik'],
                (string) $data['no_siri_pencetak'],
                (string) $data['pegawai_nama'],
                (string) $data['pegawai_jawatan'],
                (string) $data['pegawai_gred'],
                (int) $data['agensi_id'],
                (int) $data['wilayah_id'],
                $workflowId,
                (int) $data['status_aset_id'],
                $userId,
                (string) $data['catatan'],
            ];

            $types = 'sssissssssssiiissssiiiiis';
            importBindDynamic($stmtAsset, $types, $params);

            if (!mysqli_stmt_execute($stmtAsset)) {
                throw new RuntimeException(
                    'Insert aset gagal: ' . mysqli_stmt_error($stmtAsset)
                );
            }

            $assetId = (int) mysqli_insert_id($conn);

            if ($assetId <= 0) {
                throw new RuntimeException('ID aset tidak dijana.');
            }

            $logMessage =
                'Aset diimport secara pukal daripada fail ' .
                (string) $batch['nama_fail'] .
                '. Mod: ' . (string) $batch['mod_import'] . '.';

            mysqli_stmt_bind_param(
                $stmtLog,
                'iisi',
                $assetId,
                $workflowId,
                $logMessage,
                $userId
            );

            if (!mysqli_stmt_execute($stmtLog)) {
                throw new RuntimeException('Log workflow gagal disimpan.');
            }

            importSendNotifications(
                $conn,
                $assetId,
                $registration,
                $role,
                $workflowId,
                (int) $data['wilayah_id']
            );

            $statusItem = 'berjaya';
            $message = 'Rekod berjaya diimport.';

            mysqli_stmt_bind_param(
                $stmtUpdateItem,
                'sisii',
                $statusItem,
                $assetId,
                $message,
                $itemId,
                $batchId
            );

            if (!mysqli_stmt_execute($stmtUpdateItem)) {
                throw new RuntimeException('Status item tidak berjaya dikemas kini.');
            }

            $successCount++;
            mysqli_query($conn, 'RELEASE SAVEPOINT ' . $savepoint);
        } catch (Throwable $rowError) {
            mysqli_query($conn, 'ROLLBACK TO SAVEPOINT ' . $savepoint);

            $statusItem = 'gagal';
            $assetId = 0;
            $message = mb_substr($rowError->getMessage(), 0, 1000);

            mysqli_stmt_bind_param(
                $stmtUpdateItem,
                'sisii',
                $statusItem,
                $assetId,
                $message,
                $itemId,
                $batchId
            );
            mysqli_stmt_execute($stmtUpdateItem);

            $failedCount++;
            mysqli_query($conn, 'RELEASE SAVEPOINT ' . $savepoint);
        }
    }

    mysqli_stmt_close($stmtAsset);
    mysqli_stmt_close($stmtLog);
    mysqli_stmt_close($stmtUpdateItem);

    $finalCounts = importBatchCounts($conn, (int) $batchId);
    $totalFailed = $finalCounts['gagal'] + $finalCounts['dilangkau'];

    $sqlBatchUpdate = "
        UPDATE import_batch
        SET
            jumlah_berjaya = ?,
            jumlah_gagal = ?,
            status_batch = 'selesai',
            tarikh_selesai = NOW()
        WHERE import_batch_id = ?
          AND pengguna_id = ?
    ";

    $stmtBatchUpdate = mysqli_prepare($conn, $sqlBatchUpdate);
    mysqli_stmt_bind_param(
        $stmtBatchUpdate,
        'iiii',
        $finalCounts['berjaya'],
        $totalFailed,
        $batchId,
        $userId
    );
    mysqli_stmt_execute($stmtBatchUpdate);
    mysqli_stmt_close($stmtBatchUpdate);

    mysqli_commit($conn);

    logActivity(
        $conn,
        'Import Aset Pukal',
        'Batch #' . $batchId .
        ' selesai. Berjaya: ' . $finalCounts['berjaya'] .
        ', gagal/dilangkau: ' . $totalFailed . '.'
    );

    header('Location: hasil.php?id=' . (int) $batchId);
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('Import Aset - Proses: ' . $e->getMessage());

    $sqlFail = "
        UPDATE import_batch
        SET
            status_batch = 'gagal',
            tarikh_selesai = NOW()
        WHERE import_batch_id = ?
          AND pengguna_id = ?
    ";

    $stmtFail = mysqli_prepare($conn, $sqlFail);
    mysqli_stmt_bind_param($stmtFail, 'ii', $batchId, $userId);
    mysqli_stmt_execute($stmtFail);
    mysqli_stmt_close($stmtFail);

    $_SESSION['flash_error'] =
        'Import tidak dapat diselesaikan: ' . $e->getMessage();

    header('Location: preview.php?id=' . (int) $batchId);
    exit;
}
