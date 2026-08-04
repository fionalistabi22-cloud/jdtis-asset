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

$query = "SELECT * FROM daerah WHERE daerah_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $daerah_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$daerah = mysqli_fetch_assoc($result);

if (!$daerah) {
    die('Daerah tidak dijumpai');
}

$wilayah_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM wilayah ORDER BY nama_wilayah ASC"), MYSQLI_ASSOC);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } else {
        $nama_daerah = sanitize($_POST['nama_daerah'] ?? '');
        $wilayah_id = (int) ($_POST['wilayah_id'] ?? 0);
        $kod_daerah = sanitize($_POST['kod_daerah'] ?? '');

        if ($nama_daerah === '') {
            $error = 'Nama daerah diperlukan';
        } elseif ($wilayah_id === 0) {
            $error = 'Pilih wilayah';
        } else {
            $check_query = "SELECT COUNT(*) AS count FROM daerah WHERE nama_daerah = ? AND daerah_id != ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'si', $nama_daerah, $daerah_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ((int) ($check_row['count'] ?? 0) > 0) {
                $error = 'Nama daerah ini sudah wujud';
            } else {
                if ($kod_daerah === '') {
                    $kod_daerah = null;
                }

                $update_query = "UPDATE daerah SET nama_daerah = ?, wilayah_id = ?, kod_daerah = ? WHERE daerah_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'sisi', $nama_daerah, $wilayah_id, $kod_daerah, $daerah_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    logActivity($conn, 'Kemaskini Daerah', "Daerah dikemaskini: $nama_daerah");
                    header('Location: index.php?success=' . urlencode('Daerah berjaya dikemaskini'));
                    exit;
                }

                $error = 'Ralat: ' . mysqli_error($conn);
            }
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
    <title>Edit Daerah - Super Admin JTDIS</title>
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
                <h2>Edit Daerah</h2>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card" style="max-width: 700px;">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Daerah *</label>
                            <input type="text" name="nama_daerah" class="form-control" value="<?php echo escapeOutput($daerah['nama_daerah']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wilayah *</label>
                            <select name="wilayah_id" class="form-select" required>
                                <?php foreach ($wilayah_list as $w): ?>
                                    <option value="<?php echo (int) $w['wilayah_id']; ?>" <?php echo ((int) $daerah['wilayah_id'] === (int) $w['wilayah_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($w['nama_wilayah']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kod Daerah</label>
                            <input type="text" name="kod_daerah" class="form-control" value="<?php echo escapeOutput($daerah['kod_daerah'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
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
