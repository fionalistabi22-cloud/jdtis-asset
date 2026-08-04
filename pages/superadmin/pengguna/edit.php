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

$roles = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM peranan ORDER BY tahap_hierarki ASC"), MYSQLI_ASSOC);
$wilayah_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM wilayah ORDER BY nama_wilayah ASC"), MYSQLI_ASSOC);
$daerah_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM daerah ORDER BY nama_daerah ASC"), MYSQLI_ASSOC);
$agensi_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM agensi ORDER BY nama_agensi ASC"), MYSQLI_ASSOC);
$status_list = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM status_pengguna ORDER BY status_pengguna_id ASC"), MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah';
    } else {
        $nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
        $emel = sanitize($_POST['emel'] ?? '');
        $peranan_id = (int) ($_POST['peranan_id'] ?? 0);
        $wilayah_id = (int) ($_POST['wilayah_id'] ?? 0);
        $daerah_id = (int) ($_POST['daerah_id'] ?? 0);
        $agensi_id = (int) ($_POST['agensi_id'] ?? 0);
        $status_pengguna_id = (int) ($_POST['status_pengguna_id'] ?? 1);

        if ($nama_penuh === '') {
            $error = 'Nama penuh diperlukan';
        } elseif ($emel === '') {
            $error = 'Emel diperlukan';
        } elseif (!isValidEmail($emel)) {
            $error = 'Format emel tidak sah';
        } elseif ($peranan_id === 0) {
            $error = 'Pilih peranan';
        } else {
            $check_query = "SELECT COUNT(*) AS count FROM pengguna WHERE emel = ? AND pengguna_id != ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'si', $emel, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ((int) ($check_row['count'] ?? 0) > 0) {
                $error = 'Emel ini sudah digunakan oleh pengguna lain';
            } else {
                if ($wilayah_id === 0) {
                    $wilayah_id = null;
                }
                if ($daerah_id === 0) {
                    $daerah_id = null;
                }
                if ($agensi_id === 0) {
                    $agensi_id = null;
                }

                $update_query = "UPDATE pengguna SET nama_penuh = ?, emel = ?, peranan_id = ?, wilayah_id = ?, daerah_id = ?, agensi_id = ?, status_pengguna_id = ? WHERE pengguna_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'ssiiiiii', $nama_penuh, $emel, $peranan_id, $wilayah_id, $daerah_id, $agensi_id, $status_pengguna_id, $user_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    logActivity($conn, 'Kemaskini Pengguna', "Maklumat pengguna dikemaskini: $emel ($nama_penuh)");
                    header('Location: index.php?success=' . urlencode('Pengguna berjaya dikemaskini'));
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
    <title>Edit Pengguna - Super Admin JTDIS</title>
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
                <h2>Edit Pengguna</h2>
                <p class="text-muted mb-0"><?php echo escapeOutput($user['nama_penuh']); ?> (<?php echo escapeOutput($user['emel']); ?>)</p>
            </div>

            <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo escapeOutput($error); ?></div><?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Penuh *</label>
                                <input type="text" name="nama_penuh" class="form-control" value="<?php echo escapeOutput($user['nama_penuh']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emel *</label>
                                <input type="email" name="emel" class="form-control" value="<?php echo escapeOutput($user['emel']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peranan *</label>
                                <select name="peranan_id" class="form-select" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int) $role['peranan_id']; ?>" <?php echo ((int) $user['peranan_id'] === (int) $role['peranan_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($role['nama_peranan']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Wilayah</label>
                                <select name="wilayah_id" class="form-select">
                                    <option value="0">-- Tidak Ditugaskan --</option>
                                    <?php foreach ($wilayah_list as $w): ?>
                                        <option value="<?php echo (int) $w['wilayah_id']; ?>" <?php echo ((int) ($user['wilayah_id'] ?? 0) === (int) $w['wilayah_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($w['nama_wilayah']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Daerah</label>
                                <select name="daerah_id" class="form-select">
                                    <option value="0">-- Tidak Ditugaskan --</option>
                                    <?php foreach ($daerah_list as $d): ?>
                                        <option value="<?php echo (int) $d['daerah_id']; ?>" <?php echo ((int) ($user['daerah_id'] ?? 0) === (int) $d['daerah_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($d['nama_daerah']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agensi</label>
                                <select name="agensi_id" class="form-select">
                                    <option value="0">-- Tidak Ditugaskan --</option>
                                    <?php foreach ($agensi_list as $a): ?>
                                        <option value="<?php echo (int) $a['agensi_id']; ?>" <?php echo ((int) ($user['agensi_id'] ?? 0) === (int) $a['agensi_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($a['nama_agensi']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status_pengguna_id" class="form-select">
                                    <?php foreach ($status_list as $status): ?>
                                        <option value="<?php echo (int) $status['status_pengguna_id']; ?>" <?php echo ((int) $user['status_pengguna_id'] === (int) $status['status_pengguna_id']) ? 'selected' : ''; ?>><?php echo escapeOutput($status['status']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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
