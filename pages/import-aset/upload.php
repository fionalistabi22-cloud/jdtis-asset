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

if (!importPhpSpreadsheetAvailable()) {
    $_SESSION['flash_error'] =
        'PhpSpreadsheet belum dipasang. Jalankan composer require phpoffice/phpspreadsheet.';
    header('Location: index.php');
    exit;
}

$modes = importModeLabels();
$mode = trim((string) ($_POST['mod_import'] ?? 'draf'));

if (!isset($modes[$mode])) {
    $mode = 'draf';
}

$file = $_FILES['fail_aset'] ?? null;

if (
    !$file
    || !isset($file['error'])
    || (int) $file['error'] !== UPLOAD_ERR_OK
) {
    $_SESSION['flash_error'] = 'Fail tidak berjaya dimuat naik.';
    header('Location: index.php');
    exit;
}

if ((int) $file['size'] > 10 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'Saiz fail melebihi 10 MB.';
    header('Location: index.php');
    exit;
}

$originalName = basename((string) $file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['xlsx', 'xls', 'csv'];

if (!in_array($extension, $allowedExtensions, true)) {
    $_SESSION['flash_error'] = 'Format fail tidak dibenarkan.';
    header('Location: index.php');
    exit;
}

$temporaryPath = (string) $file['tmp_name'];
$userId = (int) $import_context['pengguna_id'];
$role = (string) $import_context['peranan'];
$wilayahId = $role === 'PID'
    ? 1
    : (int) ($import_context['wilayah_id'] ?? 0);
$agensiId = (int) ($import_context['agensi_id'] ?? 0);

mysqli_begin_transaction($conn);

try {
    $sqlBatch = "
        INSERT INTO import_batch (
            nama_fail,
            format_fail,
            mod_import,
            pengguna_id,
            peranan,
            wilayah_id,
            agensi_id,
            status_batch,
            tarikh_mula
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, 0),
            'dipreview',
            NOW()
        )
    ";

    $stmtBatch = mysqli_prepare($conn, $sqlBatch);

    if (!$stmtBatch) {
        throw new RuntimeException('Gagal menyediakan rekod batch.');
    }

    mysqli_stmt_bind_param(
        $stmtBatch,
        'sssisii',
        $originalName,
        $extension,
        $mode,
        $userId,
        $role,
        $wilayahId,
        $agensiId
    );

    if (!mysqli_stmt_execute($stmtBatch)) {
        throw new RuntimeException('Gagal mencipta rekod batch import.');
    }

    $batchId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmtBatch);

    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($temporaryPath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($temporaryPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, false, true, false);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    if (count($rows) < 2) {
        throw new RuntimeException('Fail tidak mempunyai data aset.');
    }

    if (count($rows) - 1 > 5000) {
        throw new RuntimeException('Maksimum 5,000 rekod bagi satu fail.');
    }

    $headerMap = importBuildHeaderMap((array) $rows[0]);

    foreach (['no_pendaftaran', 'jenis_aset', 'jenis_perolehan'] as $requiredHeader) {
        if (!isset($headerMap[$requiredHeader])) {
            throw new RuntimeException(
                'Kolum wajib tidak ditemui: ' . $requiredHeader
            );
        }
    }

    if (
        $role === 'Juruteknik'
        && !isset($headerMap['agensi_id'])
        && !isset($headerMap['nama_agensi'])
    ) {
        throw new RuntimeException(
            'Fail Juruteknik mesti mempunyai kolum nama_agensi atau agensi_id.'
        );
    }

    $sqlItem = "
        INSERT INTO import_batch_item (
            import_batch_id,
            nombor_baris,
            no_pendaftaran,
            nama_agensi,
            status_item,
            mesej,
            data_json,
            tarikh
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ";

    $stmtItem = mysqli_prepare($conn, $sqlItem);

    if (!$stmtItem) {
        throw new RuntimeException('Gagal menyediakan rekod item import.');
    }

    $seen = [];
    $total = 0;
    $validCount = 0;
    $invalidCount = 0;

    foreach (array_slice($rows, 1) as $offset => $row) {
        $rowNumber = $offset + 2;
        $row = (array) $row;

        if (importIsBlankRow($row)) {
            continue;
        }

        $total++;

        $validation = importValidateRow(
            $conn,
            $row,
            $headerMap,
            $import_context,
            $seen
        );

        $data = $validation['data'];
        $status = $validation['valid'] ? 'sah' : 'gagal';

        if ($validation['valid']) {
            $validCount++;
        } else {
            $invalidCount++;
        }

        $messages = [];

        if ($validation['errors'] !== []) {
            $messages[] = implode(' ', $validation['errors']);
        }

        if ($validation['warnings'] !== []) {
            $messages[] = 'Amaran: ' . implode(' ', $validation['warnings']);
        }

        $message = implode(' ', $messages);
        $registration = (string) ($data['no_pendaftaran'] ?? '');
        $agencyName = (string) ($data['nama_agensi'] ?? '');
        $dataJson = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($dataJson === false) {
            throw new RuntimeException('Gagal menukar data baris kepada JSON.');
        }

        mysqli_stmt_bind_param(
            $stmtItem,
            'iisssss',
            $batchId,
            $rowNumber,
            $registration,
            $agencyName,
            $status,
            $message,
            $dataJson
        );

        if (!mysqli_stmt_execute($stmtItem)) {
            throw new RuntimeException('Gagal menyimpan preview baris.');
        }
    }

    mysqli_stmt_close($stmtItem);

    if ($total === 0) {
        throw new RuntimeException('Tiada baris data yang boleh diproses.');
    }

    $sqlUpdateBatch = "
        UPDATE import_batch
        SET
            jumlah_baris = ?,
            jumlah_sah = ?,
            jumlah_gagal = ?
        WHERE import_batch_id = ?
    ";

    $stmtUpdateBatch = mysqli_prepare($conn, $sqlUpdateBatch);
    mysqli_stmt_bind_param(
        $stmtUpdateBatch,
        'iiii',
        $total,
        $validCount,
        $invalidCount,
        $batchId
    );
    mysqli_stmt_execute($stmtUpdateBatch);
    mysqli_stmt_close($stmtUpdateBatch);

    mysqli_commit($conn);

    logActivity(
        $conn,
        'Preview Import Aset',
        'Fail ' . $originalName .
        ' dipreview. Sah: ' . $validCount .
        ', gagal: ' . $invalidCount . '.'
    );

    header('Location: preview.php?id=' . $batchId);
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('Import Aset - Upload: ' . $e->getMessage());

    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
