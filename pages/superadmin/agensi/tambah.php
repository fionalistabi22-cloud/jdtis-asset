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

$error = '';
$wilayah_list = mysqli_fetch_all(mysqli_query($conn, "SELECT wilayah_id, nama_wilayah FROM wilayah ORDER BY nama_wilayah ASC"), MYSQLI_ASSOC);
$daerah_list = mysqli_fetch_all(mysqli_query($conn, "SELECT daerah_id, nama_daerah, wilayah_id FROM daerah ORDER BY nama_daerah ASC"), MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } else {
        $nama_agensi = sanitize($_POST['nama_agensi'] ?? '');
        $jenis_agensi = $_POST['jenis_agensi'] ?? 'lain';
        $wilayah_id = (int) ($_POST['wilayah_id'] ?? 0);
        $daerah_id = (int) ($_POST['daerah_id'] ?? 0);
        $catatan = sanitize($_POST['catatan'] ?? '');

        if ($nama_agensi === '') {
            $error = 'Nama agensi diperlukan';
        } elseif (!in_array($jenis_agensi, ['kementerian', 'jabatan', 'pejabat_daerah', 'lain'], true)) {
            $error = 'Sila pilih jenis agensi';
        } else {
            if ($wilayah_id === 0) {
                $wilayah_id = null;
            }
            if ($daerah_id === 0) {
                $daerah_id = null;
            }
            if ($catatan === '') {
                $catatan = null;
            }

            $check_query = "SELECT COUNT(*) AS count FROM agensi WHERE nama_agensi = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 's', $nama_agensi);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ((int) ($check_row['count'] ?? 0) > 0) {
                $error = 'Nama agensi ini sudah wujud';
            } else {
                $insert_query = "INSERT INTO agensi (nama_agensi, daerah_id, wilayah_id, jenis_agensi, catatan) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, 'siiss', $nama_agensi, $daerah_id, $wilayah_id, $jenis_agensi, $catatan);

                if (mysqli_stmt_execute($insert_stmt)) {
                    logActivity($conn, 'Tambah Agensi', "Agensi baru ditambah: $nama_agensi");
                    header('Location: index.php?success=' . urlencode('Agensi berjaya ditambah'));
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
    <title>Tambah Agensi - Super Admin JTDIS</title>
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
                <h2>Tambah Agensi / Jabatan Baru</h2>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card" style="max-width: 800px;">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Agensi *</label>
                            <input type="text" name="nama_agensi" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis *</label>
                                <select name="jenis_agensi" class="form-select" required>
                                    <option value="kementerian">Kementerian</option>
                                    <option value="jabatan" selected>Jabatan</option>
                                    <option value="pejabat_daerah">Pejabat Daerah</option>
                                    <option value="lain">Lain</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Wilayah</label>
                                <select name="wilayah_id" class="form-select">
                                    <option value="0">-- Tidak Ditugaskan --</option>
                                    <?php foreach ($wilayah_list as $wilayah): ?>
                                        <option value="<?php echo (int) $wilayah['wilayah_id']; ?>"><?php echo escapeOutput($wilayah['nama_wilayah']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Daerah</label>
                            <select name="daerah_id" class="form-select">
                                <option value="0">-- Tidak Ditugaskan --</option>
                                <?php foreach ($daerah_list as $daerah): ?>
                                    <option value="<?php echo (int) $daerah['daerah_id']; ?>"><?php echo escapeOutput($daerah['nama_daerah']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Simpan Agensi</button>
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
