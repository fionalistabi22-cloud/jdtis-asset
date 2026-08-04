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

$current_role = $_SESSION['peranan'];
$current_wilayah = $_SESSION['wilayah_id'] ?? 0;

// Get user ID
$user_id = intval($_GET['id'] ?? 0);
if ($user_id === 0) {
    die("ID pengguna tidak sah");
}

// Prevent deletion of self
if ($user_id === $_SESSION['pengguna_id']) {
    die("Anda tidak boleh menghapus akaun sendiri");
}

// Prevent deletion of Super Admin if not Super Admin
if ($current_role !== 'Super Admin') {
    $check_query = "SELECT peranan_id FROM pengguna WHERE pengguna_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    
    if ($check_row['peranan_id'] === 1) { // Super Admin
        die("Anda tidak boleh menghapus Super Admin");
    }
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

// Admin Wilayah can only delete users in their wilayah
if ($current_role === 'Admin Wilayah' && $user['wilayah_id'] !== $current_wilayah && $user['peranan_id'] !== 1) {
    die("Anda tidak mempunyai akses untuk hapus pengguna ini");
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete user
    $delete_query = "DELETE FROM pengguna WHERE pengguna_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "i", $user_id);

    if (mysqli_stmt_execute($delete_stmt)) {
        // Log activity
        logActivity($conn, 'Hapus Pengguna', "Pengguna dihapus: " . $user['emel'] . " (" . $user['nama_penuh'] . ")");
        
        // Redirect
        header("Location: index.php?success=Pengguna berjaya dihapus");
        exit;
    } else {
        $error = "Ralat: " . mysqli_error($conn);
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Pengguna - JTDIS</title>
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
                    <h2><i class="bi bi-trash"></i> Hapus Pengguna</h2>
                </div>

                <div class="card border-danger" style="max-width: 600px;">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">⚠️ Pengesahan Penghapusan</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-warning mb-4">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Amaran!</strong> Tindakan ini tidak dapat dibatalkan. Semua data pengguna ini akan dihapus.
                        </div>

                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <p><strong>Nama Penuh:</strong> <?php echo escapeOutput($user['nama_penuh']); ?></p>
                                <p><strong>Emel:</strong> <?php echo escapeOutput($user['emel']); ?></p>
                                <p><strong>No Telefon:</strong> <?php echo escapeOutput($user['no_telefon'] ?? '-'); ?></p>
                                <p class="mb-0"><strong>Status:</strong> 
                                    <?php 
                                    $status = $user['status_pengguna_id'] == 1 ? 'Aktif' : ($user['status_pengguna_id'] == 2 ? 'Tidak Aktif' : 'Suspend');
                                    echo escapeOutput($status);
                                    ?>
                                </p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Pasti untuk hapus pengguna ini? Tindakan ini tidak dapat dibatalkan.');">
                                    <i class="bi bi-trash"></i> Ya, Hapus Pengguna
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-x-lg"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
