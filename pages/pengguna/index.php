<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page - only Super Admin and Admin Wilayah
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if (!in_array($_SESSION['peranan'], ['Super Admin', 'Admin Wilayah'])) {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$current_role = $_SESSION['peranan'];
$current_wilayah = $_SESSION['wilayah_id'] ?? 0;

// Get all users or by wilayah if Admin Wilayah
if ($current_role === 'Super Admin') {
    $query = "SELECT p.*, r.nama_peranan, s.status FROM pengguna p
              JOIN peranan r ON p.peranan_id = r.peranan_id
              JOIN status_pengguna s ON p.status_pengguna_id = s.status_pengguna_id
              ORDER BY p.tarikh_daftar DESC";
} else {
    // Admin Wilayah - only their region
    $query = "SELECT p.*, r.nama_peranan, s.status FROM pengguna p
              JOIN peranan r ON p.peranan_id = r.peranan_id
              JOIN status_pengguna s ON p.status_pengguna_id = s.status_pengguna_id
              WHERE p.wilayah_id = ? OR p.peranan_id = 1
              ORDER BY p.tarikh_daftar DESC";
}

$stmt = mysqli_prepare($conn, $query);

if ($current_role !== 'Super Admin') {
    mysqli_stmt_bind_param($stmt, "i", $current_wilayah);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Pengguna - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .table-hover tbody tr:hover { background-color: #f5f5f5; }
        .badge-success { background-color: #28a745; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: black; }
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-people-fill"></i> Pengurusan Pengguna</h2>
                        <p class="text-muted">Senarai semua pengguna sistem</p>
                    </div>
                    <a href="tambah.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Pengguna Baru
                    </a>
                </div>

                <!-- USERS TABLE -->
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
                                        <td>#<?php echo escapeOutput($user['pengguna_id']); ?></td>
                                        <td><strong><?php echo escapeOutput($user['nama_penuh']); ?></strong></td>
                                        <td><?php echo escapeOutput($user['emel']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo escapeOutput($user['nama_peranan']); ?></span>
                                        </td>
                                        <td><?php echo escapeOutput($user['no_telefon'] ?? '-'); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = $user['status_pengguna_id'] == 1 ? 'bg-success' : ($user['status_pengguna_id'] == 3 ? 'bg-danger' : 'bg-warning');
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo escapeOutput($user['status']); ?></span>
                                        </td>
                                        <td><?php echo formatDate(substr($user['tarikh_daftar'], 0, 10)); ?></td>
                                        <td>
                                            <a href="edit.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="reset_password.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-info" title="Reset Kata Laluan">
                                                <i class="bi bi-key"></i>
                                            </a>
                                            <a href="hapus.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Pasti untuk hapus pengguna ini?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted p-4">
                                        Tiada pengguna dijumpai
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STATS -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center p-3">
                            <h6 class="text-muted">Jumlah Pengguna</h6>
                            <h3><?php echo count($users); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center p-3">
                            <h6 class="text-muted">Aktif</h6>
                            <h3 class="text-success"><?php echo count(array_filter($users, fn($u) => $u['status_pengguna_id'] == 1)); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center p-3">
                            <h6 class="text-muted">Suspend</h6>
                            <h3 class="text-danger"><?php echo count(array_filter($users, fn($u) => $u['status_pengguna_id'] == 3)); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center p-3">
                            <h6 class="text-muted">Tidak Aktif</h6>
                            <h3 class="text-warning"><?php echo count(array_filter($users, fn($u) => $u['status_pengguna_id'] == 2)); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
