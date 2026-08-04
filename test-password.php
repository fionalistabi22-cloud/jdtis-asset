<?php
/**
 * SIMPLE PASSWORD TEST - NO DATABASE
 * This tests PHP's password functions directly
 */

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Password Test</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .test { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        code { background: #f5f5f5; padding: 2px 5px; }
    </style>
</head>
<body>
<h1>🔐 Password Function Test</h1>";

$test_password = '12345678';

echo "<div class='test'>";
echo "<h3>Test 1: Generate Hash for '12345678'</h3>";
$hash1 = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 10]);
echo "<p><strong>Generated Hash:</strong></p>";
echo "<p><code>" . htmlspecialchars($hash1) . "</code></p>";
echo "</div>";

echo "<div class='test'>";
echo "<h3>Test 2: Verify with Same Password</h3>";
$verify1 = password_verify($test_password, $hash1);
echo "<p>password_verify('12345678', hash) = ";
echo ($verify1 ? "<span class='pass'>TRUE ✓</span>" : "<span class='fail'>FALSE ✗</span>");
echo "</p>";
echo "</div>";

echo "<div class='test'>";
echo "<h3>Test 3: Verify with Wrong Password</h3>";
$verify2 = password_verify('wrong_password', $hash1);
echo "<p>password_verify('wrong_password', hash) = ";
echo ($verify2 ? "<span class='fail'>TRUE (PROBLEM!) ✗</span>" : "<span class='pass'>FALSE ✓</span>");
echo "</p>";
echo "</div>";

echo "<div class='test'>";
echo "<h3>Test 4: Test Multiple Hash Generations</h3>";
echo "<p>Generating 3 hashes for same password:</p>";
for ($i = 1; $i <= 3; $i++) {
    $hash = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 10]);
    $verify = password_verify($test_password, $hash);
    echo "<p>Hash $i: <code style='font-size: 11px;'>" . htmlspecialchars($hash) . "</code> - Verify: " . ($verify ? "<span class='pass'>✓</span>" : "<span class='fail'>✗</span>") . "</p>";
}
echo "</div>";

echo "<div class='test'>";
echo "<h3>Test 5: Database Connection</h3>";
$conn = @mysqli_connect('localhost', 'root', '', 'jtdis_asset');
if ($conn) {
    echo "<p><span class='pass'>✓ Connected</span></p>";
    
    // Get current hash from database
    $query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = 'admin@jtdis.gov.my' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $db_hash = $row['kata_laluan_hash'];
        
        echo "<p><strong>Current Hash in Database:</strong></p>";
        echo "<p><code>" . htmlspecialchars($db_hash) . "</code></p>";
        
        echo "<p><strong>Verify '12345678' against DB hash:</strong></p>";
        $db_verify = password_verify($test_password, $db_hash);
        echo "<p>Result: " . ($db_verify ? "<span class='pass'>TRUE ✓ (PASSWORD IS CORRECT)</span>" : "<span class='fail'>FALSE ✗ (PASSWORD IS WRONG)</span>") . "</p>";
        
        if (!$db_verify) {
            echo "<p><strong style='color: red;'>FIX: Updating database with new hash...</strong></p>";
            
            $new_hash = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 10]);
            $update_query = "UPDATE pengguna SET kata_laluan_hash = ? WHERE emel = 'admin@jtdis.gov.my'";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "s", $new_hash);
            
            if (mysqli_stmt_execute($update_stmt)) {
                echo "<p><span class='pass'>✓ Database updated with new hash</span></p>";
                
                // Verify the update
                $verify_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = 'admin@jtdis.gov.my'";
                $verify_result = mysqli_query($conn, $verify_query);
                $verify_row = mysqli_fetch_assoc($verify_result);
                $final_verify = password_verify($test_password, $verify_row['kata_laluan_hash']);
                
                echo "<p><strong>Final Verification:</strong> " . ($final_verify ? "<span class='pass'>✓ SUCCESS</span>" : "<span class='fail'>✗ FAILED</span>") . "</p>";
            } else {
                echo "<p><span class='fail'>✗ Failed to update database: " . mysqli_error($conn) . "</span></p>";
            }
        }
    } else {
        echo "<p><span class='fail'>✗ User not found</span></p>";
    }
    
    mysqli_close($conn);
} else {
    echo "<p><span class='fail'>✗ Connection failed: " . mysqli_connect_error() . "</span></p>";
}
echo "</div>";

echo "<div class='test' style='background: #fff3cd; border-left: 4px solid orange;'>";
echo "<h3>📝 Next Step</h3>";
echo "<p>After this test shows SUCCESS, try logging in at:</p>";
echo "<p><a href='pages/login.php' style='font-size: 16px; color: blue;'>→ Go to Login Page</a></p>";
echo "<p><strong>Credentials:</strong><br>";
echo "Email: admin@jtdis.gov.my<br>";
echo "Password: 12345678";
echo "</p>";
echo "</div>";

echo "</body></html>";
?>
