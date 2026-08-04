<?php
require_once __DIR__ . '/_init.php';

$batchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int) $import_context['pengguna_id'];

if ($batchId === false || $batchId === null || $batchId < 1) {
    header('Location: sejarah.php');
    exit;
}

$batch = importGetBatch($conn, (int) $batchId, $userId);

if (!$batch) {
    header('Location: sejarah.php');
    exit;
}

$sql = "
    SELECT
        nombor_baris,
        no_pendaftaran,
        nama_agensi,
        status_item,
        mesej
    FROM import_batch_item
    WHERE import_batch_id = ?
      AND status_item IN ('gagal', 'dilangkau')
    ORDER BY nombor_baris ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $batchId);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$filename =
    'laporan_ralat_import_' .
    (int) $batchId .
    '_' .
    date('Ymd_His') .
    '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");

fputcsv(
    $output,
    ['Baris', 'No. Pendaftaran', 'Agensi', 'Status', 'Mesej Ralat']
);

foreach ($rows as $row) {
    fputcsv(
        $output,
        [
            $row['nombor_baris'],
            $row['no_pendaftaran'],
            $row['nama_agensi'],
            $row['status_item'],
            $row['mesej'],
        ]
    );
}

fclose($output);
exit;
