<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Protect page - Admin Wilayah only
if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit;
}

if ($_SESSION['peranan'] !== 'Admin Wilayah') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$wilayah_id = $_SESSION['wilayah_id'];

// Get statistics for this wilayah
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_pengguna_id = 1 THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN status_pengguna_id = 3 THEN 1 ELSE 0 END) as suspend,
    SUM(CASE WHEN status_pengguna_id = 2 THEN 1 ELSE 0 END) as tidak_aktif
FROM pengguna 
WHERE wilayah_id = ? OR peranan_id = 1";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $wilayah_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get all users in this wilayah (including Super Admin)
$query = "SELECT p.*, r.nama_peranan, s.status FROM pengguna p
          JOIN peranan r ON p.peranan_id = r.peranan_id
          JOIN status_pengguna s ON p.status_pengguna_id = s.status_pengguna_id
          WHERE p.wilayah_id = ? OR p.peranan_id = 1
          ORDER BY p.tarikh_daftar DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $wilayah_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get wilayah name
$wilayah_query = "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ?";
$wilayah_stmt = mysqli_prepare($conn, $wilayah_query);
mysqli_stmt_bind_param($wilayah_stmt, "i", $wilayah_id);
mysqli_stmt_execute($wilayah_stmt);
$wilayah_result = mysqli_stmt_get_result($wilayah_stmt);
$wilayah = mysqli_fetch_assoc($wilayah_result);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengguna - Admin Wilayah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../admin-wilayah.css">
</head>
<body class="admin-wilayah page-pengguna-index">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 sidebar p-4">
                <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
                <p class="text-warning mb-3"><small>Admin Wilayah</small></p>
                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-people"></i> Pengguna</a>
                    <hr class="sidebar-divider">
                    <a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 main-content p-4">
                <div class="mb-4">
                    <h2><i class="bi bi-people"></i> Pengguna Wilayah</h2>
                    <p class="text-muted">Wilayah: <strong><?php echo escapeOutput($wilayah['nama_wilayah']); ?></strong></p>
                </div>

                <!-- STATISTICS CARDS -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h6 class="card-title">Jumlah Pengguna</h6>
                                <h2 class="mb-0"><?php echo $stats['total'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-accent-success">
                            <div class="card-body">
                                <h6 class="card-title">Aktif</h6>
                                <h2 class="mb-0 text-success"><?php echo $stats['aktif'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-accent-warning">
                            <div class="card-body">
                                <h6 class="card-title">Tidak Aktif</h6>
                                <h2 class="mb-0 text-warning"><?php echo $stats['tidak_aktif'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-accent-danger">
                            <div class="card-body">
                                <h6 class="card-title">Suspend</h6>
                                <h2 class="mb-0 text-danger"><?php echo $stats['suspend'] ?? 0; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ADD USER BUTTON -->
                <div class="mb-3">
                    <a href="tambah.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Pengguna Baru
                    </a>
                </div>

                <!-- USER TABLE -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Penuh</th>
                                    <th>Emel</th>
                                    <th>Peranan</th>
                                    <th>No Telefon</th>
                                    <th>Status</th>
                                    <th>Tarikh Daftar</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><small class="text-muted"><?php echo $user['pengguna_id']; ?></small></td>
                                            <td><strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></td>
                                            <td><?php echo escapeOutput($user['emel']); ?></td>
                                            <td><span class="badge bg-info"><?php echo escapeOutput($user['nama_peranan']); ?></span></td>
                                            <td><?php echo escapeOutput($user['no_telefon'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'Aktif'): ?>
                                                    <span class="badge badge-aktif">Aktif</span>
                                                <?php elseif ($user['status'] === 'Suspend'): ?>
                                                    <span class="badge badge-suspend">Suspend</span>
                                                <?php else: ?>
                                                    <span class="badge badge-tidak-aktif text-dark">Tidak Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($user['tarikh_daftar']); ?></td>
                                            <td>
                                                <!-- Super Admin cannot be edited by Admin Wilayah -->
                                                <?php if ($user['peranan_id'] !== 1): ?>
                                                    <a href="edit.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <a href="reset_password.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-key"></i> Reset
                                                    </a>
                                                    <a href="hapus.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Pasti untuk hapus?');">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </a>
                                                <?php else: ?>
                                                    <small class="text-muted">Super Admin</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Tiada pengguna dijumpai</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
