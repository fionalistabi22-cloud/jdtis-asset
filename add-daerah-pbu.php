<?php
require_once 'includes/config.php';

echo "Adding Daerah (Districts) for Wilayah Pantai Barat Utara...\n\n";

// Wilayah Pantai Barat Utara has wilayah_id = 2
$wilayah_id = 2;

$daerah_data = [
    ['nama_daerah' => 'Daerah Kecil Banggi', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'DKB'],
    ['nama_daerah' => 'Kudat', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'KDT'],
    ['nama_daerah' => 'Matunggung', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'MTG'],
    ['nama_daerah' => 'Paitan', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'PTN'],
    ['nama_daerah' => 'Kota Marudu', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'KMD'],
    ['nama_daerah' => 'Kota Belud', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'KBD'],
    ['nama_daerah' => 'Pitas', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'PTS'],
    ['nama_daerah' => 'Ranau', 'wilayah_id' => $wilayah_id, 'kod_daerah' => 'RNU']
];

$insert_query = "INSERT INTO daerah (nama_daerah, wilayah_id, kod_daerah) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

$count = 0;
foreach ($daerah_data as $data) {
    mysqli_stmt_bind_param($stmt, "sis", $data['nama_daerah'], $data['wilayah_id'], $data['kod_daerah']);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "✓ Added: " . $data['nama_daerah'] . " (" . $data['kod_daerah'] . ")\n";
        $count++;
    } else {
        echo "✗ Failed: " . $data['nama_daerah'] . " - " . mysqli_error($conn) . "\n";
    }
}

echo "\n" . $count . " daerah records added successfully!\n";

// Display all daerah for Wilayah Pantai Barat Utara
echo "\n--- All Daerah in Wilayah Pantai Barat Utara ---\n";
$select_query = "SELECT d.*, w.nama_wilayah FROM daerah d
                 JOIN wilayah w ON d.wilayah_id = w.wilayah_id
                 WHERE d.wilayah_id = ?
                 ORDER BY d.daerah_id";
$stmt_select = mysqli_prepare($conn, $select_query);
mysqli_stmt_bind_param($stmt_select, "i", $wilayah_id);
mysqli_stmt_execute($stmt_select);
$result = mysqli_stmt_get_result($stmt_select);

while ($row = mysqli_fetch_assoc($result)) {
    echo $row['daerah_id'] . ". " . $row['nama_daerah'] . " (" . $row['kod_daerah'] . ") - " . $row['nama_wilayah'] . "\n";
}

mysqli_close($conn);
?>
