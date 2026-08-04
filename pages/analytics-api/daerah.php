<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/dashboard_analytics.php';

header('Content-Type: application/json; charset=UTF-8');

function analyticsApiFail(
    string $message,
    int $status = 400
): never {
    http_response_code($status);
    echo json_encode(
        [
            'success' => false,
            'message' => $message,
            'data' => [],
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!isLoggedIn() || isSessionExpired()) {
    analyticsApiFail('Sesi tidak sah.', 401);
}

$role = (string) ($_SESSION['peranan'] ?? '');
$allowedRoles = [
    'PPTM',
    'PTM',
    'Ketua Wilayah',
    'Ketua Bahagian',
    'Pengarah',
];

if (!in_array($role, $allowedRoles, true)) {
    analyticsApiFail('Akses tidak dibenarkan.', 403);
}

$requestedRegionId = analyticsInputInt('wilayah_id');

if (analyticsFixedRegionRole($role)) {
    $regionId = (int) ($_SESSION['wilayah_id'] ?? 0);
} else {
    $regionId = $requestedRegionId;
}

if ($regionId <= 0) {
    analyticsApiFail('Wilayah tidak sah.');
}

$region = analyticsFetchOne(
    $conn,
    "SELECT wilayah_id
     FROM wilayah
     WHERE wilayah_id = ?
     LIMIT 1",
    'i',
    [$regionId]
);

if ($region === []) {
    analyticsApiFail('Wilayah tidak ditemui.');
}

if ($regionId === 1) {
    echo json_encode(
        [
            'success' => true,
            'district_required' => false,
            'data' => [],
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

$rows = analyticsFetchAll(
    $conn,
    "SELECT daerah_id, nama_daerah
     FROM daerah
     WHERE wilayah_id = ?
     ORDER BY nama_daerah",
    'i',
    [$regionId]
);

echo json_encode(
    [
        'success' => true,
        'district_required' => true,
        'data' => $rows,
    ],
    JSON_UNESCAPED_UNICODE
);
