<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/report_aset.php';

header('Content-Type: application/json; charset=UTF-8');

function reportApiFail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $context = reportRequireContext($conn);
} catch (Throwable $e) {
    reportApiFail('Akses tidak dibenarkan.', 403);
}

$role = (string) $context['peranan'];
$regionId = reportInputInt('wilayah_id');

if ($role === 'Ketua Wilayah') {
    $regionId = (int) $context['wilayah_id'];
} elseif ($role === 'PID') {
    reportApiFail('PID menggunakan agensi tetap.', 403);
}

if ($regionId <= 0) reportApiFail('Wilayah tidak sah.');
$region = reportFetchOne($conn, 'SELECT wilayah_id FROM wilayah WHERE wilayah_id = ? LIMIT 1', 'i', [$regionId]);
if ($region === []) reportApiFail('Wilayah tidak ditemui.');

$districtId = reportInputInt('daerah_id');
if ($regionId === 1) {
    $rows = reportFetchAll($conn, 'SELECT agensi_id, nama_agensi FROM agensi WHERE wilayah_id = 1 ORDER BY nama_agensi');
} else {
    if ($districtId <= 0) reportApiFail('Pilih daerah terlebih dahulu.');
    $valid = reportFetchOne($conn, 'SELECT daerah_id FROM daerah WHERE daerah_id = ? AND wilayah_id = ? LIMIT 1', 'ii', [$districtId, $regionId]);
    if ($valid === []) reportApiFail('Daerah tidak sah.');
    $rows = reportFetchAll($conn, 'SELECT agensi_id, nama_agensi FROM agensi WHERE wilayah_id = ? AND daerah_id = ? ORDER BY nama_agensi', 'ii', [$regionId, $districtId]);
}
echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
