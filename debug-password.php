<?php
/**
 * DEBUG PASSWORD - DIAGNOSIS LENGKAP
 */

header('Content-Type: text/html; charset=utf-8');

$password_to_test = '12345678';
$email = 'admin@jtdis.gov.my';

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Password - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 30px 0; }
        .container { max-width: 900px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .code-block { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 12px; }
        .success-bg { background-color: #d4edda; }
        .error-bg { background-color: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mb-3">
            <div class="card-header bg-info text-white p-3">
                <h5 class="mb-0">🔍 Debug Password Diagnosis</h5>
            </div>
            <div class="card-body p-4">
                
                <!-- STEP 1: Database Connection -->
                <h6 class="mb-3">Step 1: Database Connection</h6>
                <?php
                $conn = @mysqli_connect('localhost', 'root', '', 'jtdis_asset');
                
                if ($conn) {
                    echo '<div class="alert alert-success">✅ Connected to database</div>';
                    
                    // STEP 2: Check current hash
                    echo '<h6 class="mb-3">Step 2: Check Current Hash in Database</h6>';
                    
                    $query = "SELECT pengguna_id, nama_penuh, emel, kata_laluan_hash FROM pengguna WHERE emel = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "s", $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $user = mysqli_fetch_assoc($result);
                        echo '<div class="card border-info mb-3">';
                        echo '<div class="card-body">';
                        echo '<p><strong>Pengguna ID:</strong> ' . $user['pengguna_id'] . '</p>';
                        echo '<p><strong>Nama:</strong> ' . $user['nama_penuh'] . '</p>';
                        echo '<p><strong>Emel:</strong> ' . $user['emel'] . '</p>';
                        echo '<p><strong>Hash Tersimpan:</strong><br>';
                        echo '<code>' . htmlspecialchars($user['kata_laluan_hash']) . '</code></p>';
                        echo '</div></div>';
                        
                        // STEP 3: Test password with current hash
                        echo '<h6 class="mb-3">Step 3: Test Password with Current Hash</h6>';
                        
                        $test1 = password_verify($password_to_test, $user['kata_laluan_hash']);
                        
                        if ($test1) {
                            echo '<div class="alert alert-success">✅ Password "' . $password_to_test . '" MATCHES dengan hash yang tersimpan</div>';
                        } else {
                            echo '<div class="alert alert-danger">❌ Password "' . $password_to_test . '" TIDAK MATCH dengan hash yang tersimpan</div>';
                            
                            // STEP 4: Generate correct hash
                            echo '<h6 class="mb-3">Step 4: Generate Correct Hash</h6>';
                            
                            $correct_hash = password_hash($password_to_test, PASSWORD_BCRYPT, ['cost' => 10]);
                            
                            echo '<div class="card border-warning mb-3">';
                            echo '<div class="card-body">';
                            echo '<p><strong>New Hash untuk password "' . $password_to_test . '":</strong><br>';
                            echo '<code>' . htmlspecialchars($correct_hash) . '</code></p>';
                            echo '<p><strong>Verify Test:</strong> ';
                            echo (password_verify($password_to_test, $correct_hash) ? '✅ BERJAYA' : '❌ GAGAL');
                            echo '</p>';
                            echo '</div></div>';
                            
                            // STEP 5: Update database
                            echo '<h6 class="mb-3">Step 5: Update Database</h6>';
                            
                            $update_query = "UPDATE pengguna SET kata_laluan_hash = ? WHERE emel = ?";
                            $update_stmt = mysqli_prepare($conn, $update_query);
                            mysqli_stmt_bind_param($update_stmt, "ss", $correct_hash, $email);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                echo '<div class="alert alert-success">✅ Database updated successfully</div>';
                                
                                // STEP 6: Verify update
                                echo '<h6 class="mb-3">Step 6: Verify Update</h6>';
                                
                                $verify_query = "SELECT kata_laluan_hash FROM pengguna WHERE emel = ?";
                                $verify_stmt = mysqli_prepare($conn, $verify_query);
                                mysqli_stmt_bind_param($verify_stmt, "s", $email);
                                mysqli_stmt_execute($verify_stmt);
                                $verify_result = mysqli_stmt_get_result($verify_stmt);
                                $verify_row = mysqli_fetch_assoc($verify_result);
                                
                                $final_test = password_verify($password_to_test, $verify_row['kata_laluan_hash']);
                                
                                if ($final_test) {
                                    echo '<div class="alert alert-success"><strong>✅✅✅ SUCCESS!</strong><br>Password "' . $password_to_test . '" sekarang BETUL di database!</div>';
                                } else {
                                    echo '<div class="alert alert-danger">❌ Verification failed after update</div>';
                                }
                            } else {
                                echo '<div class="alert alert-danger">❌ Database update failed: ' . mysqli_error($conn) . '</div>';
                            }
                        }
                    } else {
                        echo '<div class="alert alert-danger">❌ Pengguna tidak dijumpai</div>';
                    }
                    
                    mysqli_close($conn);
                } else {
                    echo '<div class="alert alert-danger">❌ Database connection failed: ' . mysqli_connect_error() . '</div>';
                }
                ?>
                
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white p-3">
                <h5 class="mb-0">📋 Next Steps</h5>
            </div>
            <div class="card-body p-4">
                <ol>
                    <li>Kalau Step 6 menunjukkan ✅ SUCCESS, pergi ke login: <a href="pages/login.php">Login Page</a></li>
                    <li>Kalau masih gagal, screenshot hasil di atas dan share dengan developer</li>
                    <li>Jangan refresh halaman ini berkali-kali (hash akan berubah setiap kali)</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
