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

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$stats_query = "SELECT COUNT(*) AS total_agensi FROM agensi";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$query = "SELECT a.*, w.nama_wilayah, d.nama_daerah,
          (SELECT COUNT(*) FROM aset s WHERE s.agensi_id = a.agensi_id) AS jumlah_aset,
          (SELECT COUNT(*) FROM pengguna p WHERE p.agensi_id = a.agensi_id) AS jumlah_pengguna
          FROM agensi a
          LEFT JOIN wilayah w ON a.wilayah_id = w.wilayah_id
          LEFT JOIN daerah d ON a.daerah_id = d.daerah_id
          ORDER BY a.nama_agensi ASC";
$result = mysqli_query($conn, $query);
$agensi_list = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

$jenisLabel = function ($jenis) {
    return match ($jenis) {
        'kementerian' => 'Kementerian',
        'jabatan' => 'Jabatan',
        'pejabat_daerah' => 'Pejabat Daerah',
        default => 'Lain'
    };
};
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Agensi / Jabatan - Super Admin JTDIS</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Pengurusan Agensi / Jabatan</h2>
                    <p class="text-muted mb-0">Semua agensi dan jabatan dalam sistem</p>
                </div>
                <a href="tambah.php" class="btn btn-primary btn-lg"><i class="bi bi-plus-circle"></i> Agensi Baru</a>
            </div>

            <?php if (!empty($success)): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo escapeOutput($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo escapeOutput($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-4 mb-3"><div class="card p-3"><small class="text-muted">Jumlah Agensi</small><h3 class="mb-0"><?php echo (int) ($stats['total_agensi'] ?? 0); ?></h3></div></div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama Agensi</th>
                            <th>Jenis</th>
                            <th>Wilayah</th>
                            <th>Daerah</th>
                            <th>Aset</th>
                            <th>Pengguna</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($agensi_list)): ?>
                            <?php foreach ($agensi_list as $row): ?>
                                <tr>
                                    <td>#<?php echo (int) $row['agensi_id']; ?></td>
                                    <td><strong><?php echo escapeOutput($row['nama_agensi']); ?></strong></td>
                                    <td><span class="badge bg-secondary"><?php echo escapeOutput($jenisLabel($row['jenis_agensi'])); ?></span></td>
                                    <td><?php echo escapeOutput($row['nama_wilayah'] ?? '-'); ?></td>
                                    <td><?php echo escapeOutput($row['nama_daerah'] ?? '-'); ?></td>
                                    <td><?php echo (int) $row['jumlah_aset']; ?></td>
                                    <td><?php echo (int) $row['jumlah_pengguna']; ?></td>
                                    <td class="text-end">
                                        <a href="edit.php?id=<?php echo (int) $row['agensi_id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                        <a href="hapus.php?id=<?php echo (int) $row['agensi_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Pasti untuk hapus agensi ini?');"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Tiada agensi dijumpai.</td></tr>
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
