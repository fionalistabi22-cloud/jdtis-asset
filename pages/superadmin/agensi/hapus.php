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

$agensi_id = (int) ($_GET['id'] ?? 0);
if ($agensi_id === 0) {
    die('ID agensi tidak sah');
}

$query = "SELECT a.*, w.nama_wilayah, d.nama_daerah FROM agensi a LEFT JOIN wilayah w ON a.wilayah_id = w.wilayah_id LEFT JOIN daerah d ON a.daerah_id = d.daerah_id WHERE a.agensi_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $agensi_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$agensi = mysqli_fetch_assoc($result);

if (!$agensi) {
    die('Agensi tidak dijumpai');
}

$depend_query = "SELECT
    (SELECT COUNT(*) FROM aset WHERE agensi_id = ?) AS jumlah_aset,
    (SELECT COUNT(*) FROM pengguna WHERE agensi_id = ?) AS jumlah_pengguna";
$depend_stmt = mysqli_prepare($conn, $depend_query);
mysqli_stmt_bind_param($depend_stmt, 'ii', $agensi_id, $agensi_id);
mysqli_stmt_execute($depend_stmt);
$depend_result = mysqli_stmt_get_result($depend_stmt);
$depend = mysqli_fetch_assoc($depend_result);

$can_delete = ((int) ($depend['jumlah_aset'] ?? 0) === 0)
    && ((int) ($depend['jumlah_pengguna'] ?? 0) === 0);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } elseif (!$can_delete) {
        $error = 'Agensi ini tidak boleh dihapus kerana masih digunakan oleh aset atau pengguna.';
    } else {
        $delete_query = "DELETE FROM agensi WHERE agensi_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, 'i', $agensi_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            logActivity($conn, 'Hapus Agensi', "Agensi dihapus: {$agensi['nama_agensi']}");
            header('Location: index.php?success=' . urlencode('Agensi berjaya dihapus'));
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
    <title>Hapus Agensi - Super Admin JTDIS</title>
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
                <a class="nav-link" href="../daerah/index.php"><i class="bi bi-building"></i> Daerah</a>
                <a class="nav-link active" href="index.php"><i class="bi bi-buildings"></i> Agensi / Jabatan</a>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </div>

        <div class="col-md-9 p-4">
            <div class="mb-4">
                <a href="index.php" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>
                <h2>Hapus Agensi / Jabatan</h2>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card border-danger" style="max-width: 800px;">
                <div class="card-header bg-danger text-white"><strong>Pengesahan Penghapusan</strong></div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">Tindakan ini tidak dapat dibatalkan.</div>
                    <p><strong>Nama Agensi:</strong> <?php echo escapeOutput($agensi['nama_agensi']); ?></p>
                    <p><strong>Jenis:</strong> <?php echo escapeOutput($agensi['jenis_agensi']); ?></p>
                    <p><strong>Wilayah:</strong> <?php echo escapeOutput($agensi['nama_wilayah'] ?? '-'); ?></p>
                    <p><strong>Daerah:</strong> <?php echo escapeOutput($agensi['nama_daerah'] ?? '-'); ?></p>

                    <?php if (!$can_delete): ?>
                        <div class="alert alert-danger">
                            Agensi ini masih digunakan oleh <?php echo (int) ($depend['jumlah_aset'] ?? 0); ?> aset dan
                            <?php echo (int) ($depend['jumlah_pengguna'] ?? 0); ?> pengguna.
                            Kosongkan data berkaitan dahulu sebelum menghapus.
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" class="btn btn-danger" <?php echo $can_delete ? '' : 'disabled'; ?> onclick="return confirm('Pasti untuk hapus agensi ini?');"><i class="bi bi-trash"></i> Ya, Hapus Agensi</button>
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
