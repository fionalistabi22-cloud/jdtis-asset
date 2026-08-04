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

if (isSessionExpired()) {
    session_destroy();
    header("Location: ../../login.php");
    exit;
}

$user = getCurrentUser();
$success = $_GET['success'] ?? '';

$stats_query = "SELECT 
    COUNT(*) AS total_pengguna,
    SUM(CASE WHEN status_pengguna_id = 1 THEN 1 ELSE 0 END) AS aktif,
    SUM(CASE WHEN status_pengguna_id = 2 THEN 1 ELSE 0 END) AS tidak_aktif,
    SUM(CASE WHEN status_pengguna_id = 3 THEN 1 ELSE 0 END) AS suspend
    FROM pengguna";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$query = "SELECT p.*, r.nama_peranan, s.status, w.nama_wilayah, d.nama_daerah, a.nama_agensi
          FROM pengguna p
          JOIN peranan r ON p.peranan_id = r.peranan_id
          JOIN status_pengguna s ON p.status_pengguna_id = s.status_pengguna_id
          LEFT JOIN wilayah w ON p.wilayah_id = w.wilayah_id
          LEFT JOIN daerah d ON p.daerah_id = d.daerah_id
          LEFT JOIN agensi a ON p.agensi_id = a.agensi_id
          ORDER BY p.tarikh_daftar DESC";
$result = mysqli_query($conn, $query);
$users = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

$statusClass = function ($statusId) {
    return match ((int) $statusId) {
        1 => 'bg-success',
        2 => 'bg-warning text-dark',
        3 => 'bg-danger',
        default => 'bg-secondary'
    };
};
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Pengguna - Super Admin JTDIS</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Pengurusan Pengguna</h2>
                    <p class="text-muted mb-0">Semua pengguna sistem di bawah kawalan Super Admin</p>
                </div>
                <a href="tambah.php" class="btn btn-primary btn-lg"><i class="bi bi-person-plus"></i> Pengguna Baru</a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo escapeOutput($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card p-3">
                        <small class="text-muted">Jumlah Pengguna</small>
                        <h3 class="mb-0"><?php echo (int) ($stats['total_pengguna'] ?? 0); ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card p-3">
                        <small class="text-muted">Aktif</small>
                        <h3 class="mb-0 text-success"><?php echo (int) ($stats['aktif'] ?? 0); ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card p-3">
                        <small class="text-muted">Tidak Aktif</small>
                        <h3 class="mb-0 text-warning"><?php echo (int) ($stats['tidak_aktif'] ?? 0); ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card p-3">
                        <small class="text-muted">Suspend</small>
                        <h3 class="mb-0 text-danger"><?php echo (int) ($stats['suspend'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Emel</th>
                            <th>Peranan</th>
                            <th>Wilayah</th>
                            <th>Status</th>
                            <th>Tarikh Daftar</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['pengguna_id']; ?></td>
                                    <td><strong><?php echo escapeOutput($row['nama_penuh']); ?></strong></td>
                                    <td><?php echo escapeOutput($row['emel']); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo escapeOutput($row['nama_peranan']); ?></span></td>
                                    <td><?php echo escapeOutput($row['nama_wilayah'] ?? '-'); ?></td>
                                    <td><span class="badge <?php echo $statusClass($row['status_pengguna_id']); ?>"><?php echo escapeOutput($row['status']); ?></span></td>
                                    <td><?php echo formatDateTime($row['tarikh_daftar']); ?></td>
                                    <td class="text-end">
                                        <a href="edit.php?id=<?php echo (int) $row['pengguna_id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                        <a href="reset_password.php?id=<?php echo (int) $row['pengguna_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-key"></i></a>
                                        <a href="hapus.php?id=<?php echo (int) $row['pengguna_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Pasti untuk hapus pengguna ini?');"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Tiada pengguna dijumpai.</td></tr>
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
