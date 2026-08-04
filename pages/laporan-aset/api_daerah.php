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

if ($regionId === 1) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}
$rows = reportFetchAll($conn, 'SELECT daerah_id, nama_daerah FROM daerah WHERE wilayah_id = ? ORDER BY nama_daerah', 'i', [$regionId]);
echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
