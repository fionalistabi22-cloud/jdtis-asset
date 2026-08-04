<?php
/**
 * JTDIS ASSET - FIX PASSWORD
 * Skrip ini akan generate hash yang betul dan terus fix database
 */

header('Content-Type: text/html; charset=utf-8');

$password = '12345678';
$correct_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

// Hubungi database
$conn = @mysqli_connect('localhost', 'root', '', 'jtdis_asset');
$messages = [];

if (!$conn) {
    $messages[] = ['type' => 'error', 'text' => "❌ Ralat: " . mysqli_connect_error()];
} else {
    // Jika method POST, terus update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $update_query = "UPDATE pengguna SET kata_laluan_hash = ? WHERE emel = 'admin@jtdis.gov.my'";
        $stmt = mysqli_prepare($conn, $update_query);
        
        if (!$stmt) {
            $messages[] = ['type' => 'error', 'text' => "❌ Ralat prepare: " . mysqli_error($conn)];
        } else {
            mysqli_stmt_bind_param($stmt, "s", $correct_hash);
            
            if (mysqli_stmt_execute($stmt)) {
                $messages[] = ['type' => 'success', 'text' => "✅ Password hash berjaya dikemaskini!"];
                
                // Verify dengan select
                $verify_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = 'admin@jtdis.gov.my'";
                $result = mysqli_query($conn, $verify_query);
                $row = mysqli_fetch_assoc($result);
                
                if ($row) {
                    $stored_hash = $row['kata_laluan_hash'];
                    $is_verified = password_verify($password, $stored_hash);
                    
                    if ($is_verified) {
                        $messages[] = ['type' => 'success', 'text' => "✅ VERIFIKASI BERJAYA! Password 12345678 akan berfungsi."];
                    } else {
                        $messages[] = ['type' => 'error', 'text' => "❌ Verifikasi gagal!"];
                    }
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => "❌ Ralat execute: " . mysqli_error($conn)];
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Password - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 600px; padding-top: 30px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .hash-display { background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white p-3">
                <h5 class="mb-0">🔐 Fix Password Hash</h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php
                        $alert_class = '';
                        if ($msg['type'] === 'success') $alert_class = 'success';
                        elseif ($msg['type'] === 'error') $alert_class = 'danger';
                        else $alert_class = 'info';
                        ?>
                        <div class="alert alert-<?php echo $alert_class; ?>" role="alert">
                            <?php echo $msg['text']; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-title">📋 Maklumat</h6>
                        <p class="mb-1"><strong>Emel:</strong> admin@jtdis.gov.my</p>
                        <p class="mb-1"><strong>Password:</strong> 12345678</p>
                        <p class="mb-0"><strong>Status:</strong> <span class="badge bg-info">Siap untuk dikemaskini</span></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Hash Bcrypt yang Akan Digunakan:</label>
                    <div class="hash-display">
                        <?php echo $correct_hash; ?>
                    </div>
                </div>
                
                <form method="POST">
                    <button type="submit" class="btn btn-success w-100 btn-lg">
                        ✅ Update Password Sekarang
                    </button>
                </form>
                
                <hr>
                
                <p class="text-muted small">Selepas klik "Update Password", sistem akan:</p>
                <ol class="text-muted small">
                    <li>Generate hash bcrypt untuk "12345678"</li>
                    <li>Update ke database</li>
                    <li>Verify bahawa password betul</li>
                    <li>Paparkan status verifikasi</li>
                </ol>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="pages/login.php" class="btn btn-outline-light w-100">Pergi ke Login</a>
        </div>
    </div>
</body>
</html>
