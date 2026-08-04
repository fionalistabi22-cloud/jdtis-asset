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

$search = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['tindakan'] ?? '');
$table_filter = trim($_GET['jadual'] ?? '');
$date_from = trim($_GET['tarikh_dari'] ?? '');
$date_to = trim($_GET['tarikh_hingga'] ?? '');

$base_where = [];
$params = [];
$types = '';

$bindParams = function ($stmt, $types, $params) {
    $bindArgs = [$types];
    foreach ($params as $index => $value) {
        $bindArgs[] = &$params[$index];
    }
    array_unshift($bindArgs, $stmt);
    return call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
};

if ($search !== '') {
    $base_where[] = "(nama_pengguna LIKE ? OR jadual LIKE ? OR tindakan LIKE ? OR data_baru LIKE ? OR ip_address LIKE ?)";
    $wildcard = '%' . $search . '%';
    array_push($params, $wildcard, $wildcard, $wildcard, $wildcard, $wildcard);
    $types .= 'sssss';
}

if ($action_filter !== '') {
    $base_where[] = "tindakan = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if ($table_filter !== '') {
    $base_where[] = "jadual = ?";
    $params[] = $table_filter;
    $types .= 's';
}

if ($date_from !== '') {
    $base_where[] = "DATE(tarikh) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '') {
    $base_where[] = "DATE(tarikh) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = $base_where ? ('WHERE ' . implode(' AND ', $base_where)) : '';

$count_query = "SELECT 
    COUNT(*) AS total_log,
    SUM(CASE WHEN tindakan = 'LOGIN' THEN 1 ELSE 0 END) AS jumlah_login,
    SUM(CASE WHEN tindakan = 'INSERT' THEN 1 ELSE 0 END) AS jumlah_insert,
    SUM(CASE WHEN tindakan = 'UPDATE' THEN 1 ELSE 0 END) AS jumlah_update,
    SUM(CASE WHEN tindakan = 'DELETE' THEN 1 ELSE 0 END) AS jumlah_delete
    FROM log_audit";
$count_result = mysqli_query($conn, $count_query);
$counts = mysqli_fetch_assoc($count_result);

$query = "SELECT * FROM log_audit $where_sql ORDER BY tarikh DESC LIMIT 250";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die('Ralat menyediakan query log audit: ' . mysqli_error($conn));
}

if (!empty($params)) {
    $bindParams($stmt, $types, $params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$logs = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

$actionBadge = function ($action) {
    return match ($action) {
        'INSERT' => 'bg-success',
        'UPDATE' => 'bg-warning text-dark',
        'DELETE' => 'bg-danger',
        'LOGIN' => 'bg-primary',
        'LOGOUT' => 'bg-secondary',
        'AKSES_DITOLAK' => 'bg-dark',
        default => 'bg-info text-dark'
    };
};

$tables = ['pengguna' => 'Pengguna', 'wilayah' => 'Wilayah', 'daerah' => 'Daerah', 'agensi' => 'Agensi', 'aset' => 'Aset', 'sistem' => 'Sistem'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Audit Sistem - Super Admin JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .table td { vertical-align: middle; }
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
                <a class="nav-link" href="../agensi/index.php"><i class="bi bi-buildings"></i> Agensi / Jabatan</a>
                <a class="nav-link active" href="index.php"><i class="bi bi-shield-lock"></i> Log Audit Sistem</a>
                <hr style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="../../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </div>

        <div class="col-md-9 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Log Audit Sistem</h2>
                    <p class="text-muted mb-0">Jejak aktiviti pengguna, keselamatan, dan perubahan data</p>
                </div>
                <div class="text-end">
                    <p class="mb-0"><strong><?php echo escapeOutput($user['peranan']); ?></strong></p>
                    <small class="text-muted"><?php echo escapeOutput($user['emel']); ?></small>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-3"><div class="card p-3"><small class="text-muted">Jumlah Log</small><h3 class="mb-0"><?php echo (int) ($counts['total_log'] ?? 0); ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card p-3"><small class="text-muted">Login</small><h3 class="mb-0 text-primary"><?php echo (int) ($counts['jumlah_login'] ?? 0); ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card p-3"><small class="text-muted">Kemaskini</small><h3 class="mb-0 text-warning"><?php echo (int) ($counts['jumlah_update'] ?? 0); ?></h3></div></div>
                <div class="col-md-3 mb-3"><div class="card p-3"><small class="text-muted">Hapus</small><h3 class="mb-0 text-danger"><?php echo (int) ($counts['jumlah_delete'] ?? 0); ?></h3></div></div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Carian</label>
                            <input type="text" name="search" class="form-control" value="<?php echo escapeOutput($search); ?>" placeholder="Nama, jadual, tindakan, IP">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tindakan</label>
                            <select name="tindakan" class="form-select">
                                <option value="">Semua</option>
                                <option value="LOGIN" <?php echo $action_filter === 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                                <option value="LOGOUT" <?php echo $action_filter === 'LOGOUT' ? 'selected' : ''; ?>>LOGOUT</option>
                                <option value="INSERT" <?php echo $action_filter === 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                                <option value="UPDATE" <?php echo $action_filter === 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                                <option value="DELETE" <?php echo $action_filter === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                                <option value="AKSES_DITOLAK" <?php echo $action_filter === 'AKSES_DITOLAK' ? 'selected' : ''; ?>>AKSES_DITOLAK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Jadual</label>
                            <select name="jadual" class="form-select">
                                <option value="">Semua</option>
                                <?php foreach ($tables as $key => $label): ?>
                                    <option value="<?php echo escapeOutput($key); ?>" <?php echo $table_filter === $key ? 'selected' : ''; ?>><?php echo escapeOutput($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tarikh Dari</label>
                            <input type="date" name="tarikh_dari" class="form-control" value="<?php echo escapeOutput($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tarikh Hingga</label>
                            <input type="date" name="tarikh_hingga" class="form-control" value="<?php echo escapeOutput($date_to); ?>">
                        </div>
                        <div class="col-md-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Tapis</button>
                            <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Tarikh</th>
                            <th>Pengguna</th>
                            <th>Jadual</th>
                            <th>Tindakan</th>
                            <th>Rekod</th>
                            <th>IP</th>
                            <th>Butiran</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo formatDateTime($log['tarikh']); ?></td>
                                    <td>
                                        <strong><?php echo escapeOutput($log['nama_pengguna'] ?? '-'); ?></strong><br>
                                        <small class="text-muted">ID: <?php echo (int) ($log['pengguna_id'] ?? 0); ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo escapeOutput($tables[$log['jadual']] ?? $log['jadual']); ?></span></td>
                                    <td><span class="badge <?php echo $actionBadge($log['tindakan']); ?>"><?php echo escapeOutput($log['tindakan']); ?></span></td>
                                    <td><?php echo $log['rekod_id'] !== null ? (int) $log['rekod_id'] : '-'; ?></td>
                                    <td><?php echo escapeOutput($log['ip_address'] ?? '-'); ?></td>
                                    <td style="max-width: 360px; word-break: break-word;">
                                        <?php echo escapeOutput($log['data_baru'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Tiada log audit dijumpai.</td></tr>
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
