<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit;
}

if (($_SESSION['peranan'] ?? '') !== 'Super Admin') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$user_id = (int) ($_GET['id'] ?? 0);
if ($user_id === 0) {
    die('ID pengguna tidak sah');
}

if ($user_id === (int) ($_SESSION['pengguna_id'] ?? 0)) {
    die('Anda tidak boleh menghapus akaun sendiri');
}

$user_query = "SELECT * FROM pengguna WHERE pengguna_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    die('Pengguna tidak dijumpai');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } else {
        $delete_query = "DELETE FROM pengguna WHERE pengguna_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, 'i', $user_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            logActivity($conn, 'Hapus Pengguna', "Pengguna dihapus: {$user['emel']} ({$user['nama_penuh']})");
            header('Location: index.php?success=' . urlencode('Pengguna berjaya dihapus')); 
            exit;
        }

        $error = 'Ralat: ' . mysqli_error($conn);
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Pengguna - Super Admin JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 sidebar p-4">
            <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
            <p class="text-warning mb-3"><small>Super Admin</small></p>
            <nav class="nav flex-column">
                <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                <a class="nav-link active" href="index.php"><i class="bi bi-people"></i> Pengguna</a>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </div>

        <div class="col-md-9 p-4">
            <div class="mb-4">
                <a href="index.php" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                <h2>Hapus Pengguna</h2>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card border-danger" style="max-width: 700px;">
                <div class="card-header bg-danger text-white">
                    <strong>Pengesahan Penghapusan</strong>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">Tindakan ini tidak dapat dibatalkan.</div>
                    <p><strong>Nama:</strong> <?php echo escapeOutput($user['nama_penuh']); ?></p>
                    <p><strong>Emel:</strong> <?php echo escapeOutput($user['emel']); ?></p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Pasti untuk hapus pengguna ini?');"><i class="bi bi-trash"></i> Ya, Hapus Pengguna</button>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
