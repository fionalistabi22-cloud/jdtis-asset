<?php
require_once 'includes/config.php';

echo "Adding Wilayah records...\n\n";

$wilayah_data = [
    ['nama_wilayah' => 'Ibu Pejabat JTDIS', 'jenis' => 'ibu_pejabat'],
    ['nama_wilayah' => 'Wilayah Pantai Barat Utara', 'jenis' => 'wilayah'],
    ['nama_wilayah' => 'Wilayah Sandakan', 'jenis' => 'wilayah'],
    ['nama_wilayah' => 'Wilayah Tawau', 'jenis' => 'wilayah'],
    ['nama_wilayah' => 'Wilayah Pedalaman Bawah', 'jenis' => 'wilayah'],
    ['nama_wilayah' => 'Wilayah Pedalaman Atas', 'jenis' => 'wilayah'],
    ['nama_wilayah' => 'Wilayah Bandaraya', 'jenis' => 'wilayah']
];

$insert_query = "INSERT INTO wilayah (nama_wilayah, jenis) VALUES (?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

$count = 0;
foreach ($wilayah_data as $data) {
    mysqli_stmt_bind_param($stmt, "ss", $data['nama_wilayah'], $data['jenis']);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "✓ Added: " . $data['nama_wilayah'] . "\n";
        $count++;
    } else {
        echo "✗ Failed: " . $data['nama_wilayah'] . " - " . mysqli_error($conn) . "\n";
    }
}

echo "\n" . $count . " wilayah records added successfully!\n";

// Display all wilayah
echo "\n--- All Wilayah Records ---\n";
$select_query = "SELECT * FROM wilayah ORDER BY wilayah_id";
$result = mysqli_query($conn, $select_query);

while ($row = mysqli_fetch_assoc($result)) {
    echo $row['wilayah_id'] . ". " . $row['nama_wilayah'] . " (" . $row['jenis'] . ")\n";
}

mysqli_close($conn);
?>
