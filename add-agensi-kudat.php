<?php
require_once 'includes/config.php';

// Get Kudat district ID
$daerah_query = "SELECT daerah_id FROM daerah WHERE nama_daerah = 'Kudat'";
$daerah_result = mysqli_query($conn, $daerah_query);
$daerah_row = mysqli_fetch_assoc($daerah_result);

if (!$daerah_row) {
    echo "Kudat district tidak dijumpai\n";
    exit;
}

$kudat_daerah_id = $daerah_row['daerah_id'];
echo "Kudat District ID: $kudat_daerah_id\n\n";

// List of agencies for Kudat
$agencies = [
    'Jabatan Perhutanan',
    'Jabatan Air',
    'Pejabat Daerah',
    'Jabatan Pengairan dan Saliran',
    'Jabatan Perikanan',
    'Jabatan Muzium',
    'Pejabat Kebajikan AM',
    'Jabatan Ehwal Agama Islam',
    'Jabatan Perkhidmatan Veterinar',
    'Kementerian Belia dan Sukan',
    'Perpustakaan Negeri Sabah',
    'Jabatan Pertanian',
    'Taman Didikan Kanak Kanak',
    'Pelabuhan dan Dermaga Kudat',
    'Mahkamah Anak Negeri',
    'Jabatan Bendahari'
];

echo "Inserting " . count($agencies) . " agencies for Kudat district...\n\n";

$inserted = 0;
foreach ($agencies as $nama_agensi) {
    $insert_query = "INSERT INTO agensi (nama_agensi, daerah_id, jenis_agensi) VALUES (?, ?, NULL)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    
    if (!$insert_stmt) {
        echo "❌ Error preparing statement: " . mysqli_error($conn) . "\n";
        continue;
    }
    
    mysqli_stmt_bind_param($insert_stmt, "si", $nama_agensi, $kudat_daerah_id);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        echo "✓ Added: $nama_agensi\n";
        $inserted++;
    } else {
        echo "❌ Failed: $nama_agensi - " . mysqli_error($conn) . "\n";
    }
    
    mysqli_stmt_close($insert_stmt);
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Summary: $inserted agencies successfully added for Kudat district\n";
echo str_repeat("=", 50) . "\n";

mysqli_close($conn);
?>
