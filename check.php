<?php
// Simple health check page
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pemeriksaan Sistem - JTDIS Aset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Status Sistem JTDIS Aset</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $checks = [];
                        
                        // Check PHP version
                        $checks[] = [
                            'title' => 'Versi PHP',
                            'status' => version_compare(PHP_VERSION, '7.0', '>='),
                            'message' => 'PHP ' . PHP_VERSION
                        ];
                        
                        // Check MySQLi extension
                        $checks[] = [
                            'title' => 'Sambungan MySQLi',
                            'status' => extension_loaded('mysqli'),
                            'message' => extension_loaded('mysqli') ? 'Tersedia' : 'Tidak Tersedia'
                        ];
                        
                        // Check database connection
                        $db_status = false;
                        $db_message = 'Tidak Berjaya Bersambung';
                        $conn = @mysqli_connect('localhost', 'root', '', 'jtdis_asset');
                        if ($conn) {
                            $db_status = true;
                            $db_message = 'Berjaya Bersambung';
                            mysqli_close($conn);
                        }
                        $checks[] = [
                            'title' => 'Database jtdis_asset',
                            'status' => $db_status,
                            'message' => $db_message
                        ];
                        
                        // Check writable directories
                        $session_dir = session_save_path();
                        $checks[] = [
                            'title' => 'Session Directory',
                            'status' => is_writable($session_dir),
                            'message' => $session_dir . (is_writable($session_dir) ? ' ✓' : ' ✗')
                        ];
                        
                        // Display checks
                        foreach ($checks as $check) {
                            $badge = $check['status'] ? 'success' : 'danger';
                            $icon = $check['status'] ? '✓' : '✗';
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <span><?php echo $check['title']; ?>:</span>
                                <span class="badge bg-<?php echo $badge; ?>"><?php echo $icon; ?> <?php echo $check['message']; ?></span>
                            </div>
                            <?php
                        }
                        ?>
                        
                        <div class="mt-4">
                            <a href="setup.html" class="btn btn-primary">Panduan Persediaan</a>
                            <a href="pages/login.php" class="btn btn-success">Log Masuk</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
