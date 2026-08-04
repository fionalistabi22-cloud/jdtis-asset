<?php
/**
 * JTDIS - DIRECT PASSWORD FIX
 * This script will:
 * 1. Connect to database
 * 2. Generate correct hash for password "12345678"
 * 3. Update database directly
 * 4. Test the update
 * 5. Provide clear success/failure message
 */

header('Content-Type: text/html; charset=utf-8');

// Test parameters
$test_password = '12345678';
$test_email = 'admin@jtdis.gov.my';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Direct Fix - Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 30px 0; }
        .card { border: none; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
<div class="container" style="max-width: 800px;">
    <div class="card">
        <div class="card-header bg-danger text-white p-4">
            <h4 class="mb-0">⚠️ Direct Password Database Fix</h4>
        </div>
        <div class="card-body p-4">

<?php

// Step 1: Generate correct hash
echo "<h5>Step 1: Generate Correct Hash</h5>";
echo "<p>Password to hash: <code>" . htmlspecialchars($test_password) . "</code></p>";

$correct_hash = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 10]);
echo "<p>Generated Hash:</p>";
echo "<p><code style='word-break: break-all; font-size: 11px;'>" . htmlspecialchars($correct_hash) . "</code></p>";

// Verify it works
$test_verify = password_verify($test_password, $correct_hash);
echo "<p><strong>Verify Test:</strong> " . ($test_verify ? "✅ PASS" : "❌ FAIL") . "</p>";

if (!$test_verify) {
    echo "<div class='alert alert-danger'>Password verification failed! Something is wrong with PHP configuration.</div>";
    exit;
}

echo "<hr>";

// Step 2: Connect to database
echo "<h5>Step 2: Connect to Database</h5>";

$conn = @mysqli_connect('localhost', 'root', '', 'jtdis_asset');

if (!$conn) {
    echo "<div class='alert alert-danger'>❌ Database connection failed: " . mysqli_connect_error() . "</div>";
    exit;
}

echo "<p>✅ Connected to database</p>";

// Step 3: Check current state
echo "<h5>Step 3: Check Current Password State</h5>";

$check_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = ?";
$check_stmt = mysqli_prepare($conn, $check_query);

if (!$check_stmt) {
    echo "<div class='alert alert-danger'>❌ Prepare failed: " . mysqli_error($conn) . "</div>";
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param($check_stmt, "s", $test_email);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    echo "<div class='alert alert-danger'>❌ User admin@jtdis.gov.my not found!</div>";
    mysqli_close($conn);
    exit;
}

$current_row = mysqli_fetch_assoc($check_result);
$current_hash = $current_row['kata_laluan_hash'];

echo "<p><strong>Current hash in database:</strong></p>";
echo "<p><code style='word-break: break-all; font-size: 11px;'>" . htmlspecialchars($current_hash) . "</code></p>";

$current_verify = password_verify($test_password, $current_hash);
echo "<p><strong>Does password '12345678' match current hash?</strong> ";
echo ($current_verify ? "✅ YES (Already correct!)" : "❌ NO (Needs fixing)");
echo "</p>";

echo "<hr>";

// Step 4: Update if needed
echo "<h5>Step 4: Update Database</h5>";

if (!$current_verify) {
    echo "<p>🔄 Updating database with correct hash...</p>";
    
    $update_query = "UPDATE pengguna SET kata_laluan_hash = ? WHERE emel = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    
    if (!$update_stmt) {
        echo "<div class='alert alert-danger'>❌ Prepare failed: " . mysqli_error($conn) . "</div>";
        mysqli_close($conn);
        exit;
    }
    
    mysqli_stmt_bind_param($update_stmt, "ss", $correct_hash, $test_email);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        echo "<div class='alert alert-danger'>❌ Update failed: " . mysqli_error($conn) . "</div>";
        mysqli_close($conn);
        exit;
    }
    
    $affected = mysqli_stmt_affected_rows($update_stmt);
    echo "<p>✅ Database updated. Rows affected: " . $affected . "</p>";
} else {
    echo "<p>✅ Hash already correct, no update needed.</p>";
}

echo "<hr>";

// Step 5: Verify update
echo "<h5>Step 5: Verify Update</h5>";

$verify_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = ?";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "s", $test_email);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);
$verify_row = mysqli_fetch_assoc($verify_result);

$final_verify = password_verify($test_password, $verify_row['kata_laluan_hash']);

if ($final_verify) {
    echo "<div class='alert alert-success' style='font-size: 16px;'>";
    echo "<h5>✅✅✅ SUCCESS!</h5>";
    echo "<p>Password '12345678' is now CORRECT in the database.</p>";
    echo "<p><strong>You can now log in with:</strong></p>";
    echo "<p>📧 Email: " . htmlspecialchars($test_email) . "</p>";
    echo "<p>🔐 Password: " . htmlspecialchars($test_password) . "</p>";
    echo "</div>";
    
    // Redirect button
    echo "<p class='text-center mt-4'>";
    echo "<a href='pages/login.php' class='btn btn-primary btn-lg'>Go to Login Page →</a>";
    echo "</p>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ VERIFICATION FAILED</h5>";
    echo "<p>The password is still not matching. There may be a system issue.</p>";
    echo "</div>";
}

mysqli_close($conn);

?>

        </div>
    </div>
</div>
</body>
</html>
