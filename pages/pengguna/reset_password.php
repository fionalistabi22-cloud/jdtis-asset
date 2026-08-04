<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if (!in_array($_SESSION['peranan'], ['Super Admin', 'Admin Wilayah'])) {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$error = '';
$success = '';
$current_role = $_SESSION['peranan'];
$current_wilayah = $_SESSION['wilayah_id'] ?? 0;

// Get user ID
$user_id = intval($_GET['id'] ?? 0);
if ($user_id === 0) {
    die("ID pengguna tidak sah");
}

// Get user details
$user_query = "SELECT * FROM pengguna WHERE pengguna_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    die("Pengguna tidak dijumpai");
}

// Admin Wilayah can only reset for their wilayah users
if ($current_role === 'Admin Wilayah' && $user['wilayah_id'] !== $current_wilayah && $user['peranan_id'] !== 1) {
    die("Anda tidak mempunyai akses untuk reset kata laluan pengguna ini");
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password)) {
        $error = "Kata laluan diperlukan";
    } elseif (strlen($password) < 6) {
        $error = "Kata laluan mesti sekurang-kurangnya 6 karakter";
    } elseif ($password !== $password_confirm) {
        $error = "Kata laluan tidak sepadan";
    } else {
        $password_hash = hashPassword($password);

        // Update password
        $update_query = "UPDATE pengguna SET kata_laluan_hash = ? WHERE pengguna_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $password_hash, $user_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Kata laluan berjaya direset";
            // Log activity
            logActivity($conn, 'Reset Kata Laluan', "Kata laluan pengguna direset: " . $user['emel']);
            
            // Redirect after 2 seconds
            header("Refresh: 2; url=index.php");
        } else {
            $error = "Ralat: " . mysqli_error($conn);
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Laluan - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 sidebar p-4">
                <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-people"></i> Pengguna</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 p-4">
                <div class="mb-4">
                    <a href="index.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <h2><i class="bi bi-key"></i> Reset Kata Laluan</h2>
                    <p class="text-muted">Reset kata laluan untuk: <strong><?php echo escapeOutput($user['nama_penuh']); ?></strong> (<?php echo escapeOutput($user['emel']); ?>)</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo escapeOutput($success); ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="max-width: 500px;">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">Kata Laluan Baru <span class="text-danger">*</span></label>
                                <input type="password" id="password" name="password" class="form-control form-control-lg" required placeholder="Min 6 karakter" autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Sahkan Kata Laluan <span class="text-danger">*</span></label>
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control form-control-lg" required placeholder="Ulangi kata laluan">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="bi bi-check-lg"></i> Reset Kata Laluan
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-x-lg"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle"></i> <strong>Nota:</strong> Pengguna akan dapat log masuk dengan kata laluan baru ini sahaja selepas reset dijalankan.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
