<?php
require_once 'includes/config.php';

echo "Checking Agen IT role...\n\n";

// Check if Agen IT role exists
$check_query = "SELECT * FROM peranan WHERE nama_peranan = 'Agen IT'";
$check_result = mysqli_query($conn, $check_query);
$existing_role = mysqli_fetch_assoc($check_result);

if ($existing_role) {
    echo "✓ Agen IT role already exists (ID: " . $existing_role['peranan_id'] . ")\n";
} else {
    echo "Agen IT role not found. Adding it...\n\n";
    
    // Insert Agen IT role
    $insert_query = "INSERT INTO peranan (nama_peranan, tahap_hierarki, keterangan) VALUES (?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    
    if (!$insert_stmt) {
        echo "❌ Error preparing statement: " . mysqli_error($conn) . "\n";
        exit;
    }
    
    $nama_peranan = 'Agen IT';
    $tahap_hierarki = 3;
    $keterangan = 'Agen IT untuk pengurusan teknologi maklumat';
    
    mysqli_stmt_bind_param($insert_stmt, "sis", $nama_peranan, $tahap_hierarki, $keterangan);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $role_id = mysqli_insert_id($conn);
        echo "✓ Agen IT role successfully added (ID: $role_id)\n";
        echo "  - Hierarki Level: 3\n";
        echo "  - Keterangan: $keterangan\n";
    } else {
        echo "❌ Failed to add Agen IT role: " . mysqli_error($conn) . "\n";
    }
    
    mysqli_stmt_close($insert_stmt);
}

echo "\n";

// Display all peranan
echo "All Peranan (Roles) in system:\n";
echo str_repeat("=", 70) . "\n";

$all_roles_query = "SELECT * FROM peranan ORDER BY tahap_hierarki ASC";
$all_roles_result = mysqli_query($conn, $all_roles_query);
$all_roles = mysqli_fetch_all($all_roles_result, MYSQLI_ASSOC);

foreach ($all_roles as $role) {
    echo "ID: {$role['peranan_id']} | Hierarki: {$role['tahap_hierarki']} | {$role['nama_peranan']}\n";
}

echo str_repeat("=", 70) . "\n";
echo "Total roles: " . count($all_roles) . "\n";

mysqli_close($conn);
?>
