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

$daerah_id = (int) ($_GET['id'] ?? 0);
if ($daerah_id === 0) {
    die('ID daerah tidak sah');
}

$query = "SELECT d.*, w.nama_wilayah FROM daerah d JOIN wilayah w ON d.wilayah_id = w.wilayah_id WHERE d.daerah_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $daerah_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$daerah = mysqli_fetch_assoc($result);

if (!$daerah) {
    die('Daerah tidak dijumpai');
}

$depend_query = "SELECT
    (SELECT COUNT(*) FROM agensi WHERE daerah_id = ?) AS jumlah_agensi,
    (SELECT COUNT(*) FROM pengguna WHERE daerah_id = ?) AS jumlah_pengguna";
$depend_stmt = mysqli_prepare($conn, $depend_query);
mysqli_stmt_bind_param($depend_stmt, 'ii', $daerah_id, $daerah_id);
mysqli_stmt_execute($depend_stmt);
$depend_result = mysqli_stmt_get_result($depend_stmt);
$depend = mysqli_fetch_assoc($depend_result);

$can_delete = ((int) ($depend['jumlah_agensi'] ?? 0) === 0)
    && ((int) ($depend['jumlah_pengguna'] ?? 0) === 0);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } elseif (!$can_delete) {
        $error = 'Daerah ini tidak boleh dihapus kerana masih digunakan oleh agensi atau pengguna.';
    } else {
        $delete_query = "DELETE FROM daerah WHERE daerah_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, 'i', $daerah_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            logActivity($conn, 'Hapus Daerah', "Daerah dihapus: {$daerah['nama_daerah']}");
            header('Location: index.php?success=' . urlencode('Daerah berjaya dihapus'));
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
    <title>Hapus Daerah - Super Admin JTDIS</title>
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
                <a class="nav-link" href="../pengguna/index.php"><i class="bi bi-people"></i> Pengguna</a>
                <a class="nav-link" href="../wilayah/index.php"><i class="bi bi-map"></i> Wilayah</a>
                <a class="nav-link active" href="index.php"><i class="bi bi-building"></i> Daerah</a>
                <a class="nav-link" href="../agensi/index.php"><i class="bi bi-buildings"></i> Agensi / Jabatan</a>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </div>

        <div class="col-md-9 p-4">
            <div class="mb-4">
                <a href="index.php" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                <h2>Hapus Daerah</h2>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card border-danger" style="max-width: 750px;">
                <div class="card-header bg-danger text-white"><strong>Pengesahan Penghapusan</strong></div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">Tindakan ini tidak dapat dibatalkan.</div>
                    <p><strong>Nama Daerah:</strong> <?php echo escapeOutput($daerah['nama_daerah']); ?></p>
                    <p><strong>Wilayah:</strong> <?php echo escapeOutput($daerah['nama_wilayah']); ?></p>
                    <p><strong>Kod:</strong> <?php echo escapeOutput($daerah['kod_daerah'] ?? '-'); ?></p>

                    <?php if (!$can_delete): ?>
                        <div class="alert alert-danger">
                            Daerah ini masih digunakan oleh <?php echo (int) ($depend['jumlah_agensi'] ?? 0); ?> agensi dan
                            <?php echo (int) ($depend['jumlah_pengguna'] ?? 0); ?> pengguna.
                            Kosongkan data berkaitan dahulu sebelum menghapus.
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger" <?php echo $can_delete ? '' : 'disabled'; ?> onclick="return confirm('Pasti untuk hapus daerah ini?');"><i class="bi bi-trash"></i> Ya, Hapus Daerah</button>
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
