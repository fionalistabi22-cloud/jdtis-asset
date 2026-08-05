<?php
/**
 * JTDIS - Endpoint JSON berperanan untuk kaskad Wilayah → Daerah → Agensi
 * pada borang Tambah Pengguna.
 *
 * Akses: Super Admin dan Admin Wilayah sahaja.
 *
 * Parameter:
 *   type        = "daerah" | "agensi"
 *   wilayah_id  = ID wilayah (diabaikan untuk Admin Wilayah, guna sesi)
 *   daerah_id   = ID daerah (diperlukan untuk type=agensi)
 *
 * Keselamatan:
 *   - Admin Wilayah: wilayah_id SENTIASA diambil dari $_SESSION['wilayah_id'],
 *     nilai yang dihantar diabaikan.
 *   - Super Admin: wilayah_id boleh dipilih bebas.
 *   - Setiap hubungan wilayah→daerah→agensi disahkan dengan prepared statement.
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

function cascadingApiFail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(
        ['success' => false, 'message' => $message, 'data' => []],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (!isLoggedIn() || isSessionExpired()) {
    cascadingApiFail('Sesi tidak sah.', 401);
}

$role = (string) ($_SESSION['peranan'] ?? '');
if (!in_array($role, ['Super Admin', 'Admin Wilayah'], true)) {
    cascadingApiFail('Akses tidak dibenarkan.', 403);
}

$type = (string) ($_GET['type'] ?? '');
if (!in_array($type, ['daerah', 'agensi'], true)) {
    cascadingApiFail('Jenis permintaan tidak sah.');
}

// Admin Wilayah DIIKUNCI kepada wilayah sesi. Nilai yang dihantar diabaikan.
if ($role === 'Admin Wilayah') {
    $regionId = (int) ($_SESSION['wilayah_id'] ?? 0);
} else {
    $regionId = (int) filter_input(INPUT_GET, 'wilayah_id', FILTER_VALIDATE_INT);
}

if ($regionId <= 0) {
    cascadingApiFail('Wilayah tidak sah.');
}

$region = mysqli_prepare($conn, 'SELECT wilayah_id FROM wilayah WHERE wilayah_id = ? LIMIT 1');
mysqli_stmt_bind_param($region, 'i', $regionId);
mysqli_stmt_execute($region);
$regionResult = mysqli_stmt_get_result($region);
$regionRow = mysqli_fetch_assoc($regionResult);
mysqli_stmt_close($region);

if (!$regionRow) {
    cascadingApiFail('Wilayah tidak ditemui.', 404);
}

if ($type === 'daerah') {
    $query = 'SELECT daerah_id, nama_daerah FROM daerah WHERE wilayah_id = ? ORDER BY nama_daerah';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $regionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    echo json_encode(
        ['success' => true, 'data' => $rows],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// type === 'agensi'
$districtId = (int) filter_input(INPUT_GET, 'daerah_id', FILTER_VALIDATE_INT);

if ($districtId <= 0) {
    cascadingApiFail('Pilih daerah terlebih dahulu.');
}

// Sahkan daerah berada dalam wilayah yang berkesan.
$district = mysqli_prepare(
    $conn,
    'SELECT daerah_id FROM daerah WHERE daerah_id = ? AND wilayah_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($district, 'ii', $districtId, $regionId);
mysqli_stmt_execute($district);
$districtResult = mysqli_stmt_get_result($district);
$districtRow = mysqli_fetch_assoc($districtResult);
mysqli_stmt_close($district);

if (!$districtRow) {
    cascadingApiFail('Daerah tidak berada dalam wilayah tersebut.');
}

// Agensi mesti berada dalam daerah DAN wilayah yang berkesan.
// Wilayah agensi diterbitkan melalui hubungan daerah.
$query = "SELECT a.agensi_id, a.nama_agensi
          FROM agensi a
          INNER JOIN daerah d
              ON d.daerah_id = a.daerah_id
          WHERE a.daerah_id = ?
            AND d.wilayah_id = ?
          ORDER BY a.nama_agensi ASC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $districtId, $regionId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

echo json_encode(
    ['success' => true, 'data' => $rows],
    JSON_UNESCAPED_UNICODE
);
exit;
