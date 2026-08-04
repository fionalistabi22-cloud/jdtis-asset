<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page (centralized guard: login + session expiry)
requireRoleWhitelist(['Juruteknik']);

$pengguna_id = $_SESSION['pengguna_id'];
$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;

if ($aset_id === 0) {
    header("Location: index.php");
    exit;
}

// Verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Token CSRF tidak sah");
    }
    
    // Verify asset ownership
    $check_query = "SELECT a.no_pendaftaran FROM aset a WHERE a.aset_id = ? AND a.pengguna_id_daftar = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ii", $aset_id, $pengguna_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $aset = mysqli_fetch_assoc($check_result);
    
    if (!$aset) {
        header("Location: index.php");
        exit;
    }
    
    // Delete asset
    $delete_query = "DELETE FROM aset WHERE aset_id = ? AND pengguna_id_daftar = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, "ii", $aset_id, $pengguna_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        logActivity($conn, 'Hapus Aset', "Aset {$aset['no_pendaftaran']} dihapuskan oleh Juruteknik");
        header("Location: index.php?success=Aset berjaya dihapuskan");
        exit;
    } else {
        die("Ralat: " . mysqli_error($conn));
    }
} else {
    // GET request - show confirmation page
    $query = "SELECT a.* FROM aset a WHERE a.aset_id = ? AND a.pengguna_id_daftar = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $aset_id, $pengguna_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $aset = mysqli_fetch_assoc($result);
    
    if (!$aset) {
        header("Location: index.php");
        exit;
    }
    
    $csrf_token = generateCSRFToken();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Aset - Juruteknik JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="aset.css">

</head>
<body class="page-hapus">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <aside class="col-md-3 col-xl-2 sidebar p-4">
                <div class="brand-wrap">
                    <div class="brand-mark"><i class="bi bi-pc-display-horizontal"></i></div>
                    <div>
                        <h4 class="brand-title">JTDIS</h4>
                        <p class="brand-subtitle">Modul Aset ICT</p>
                    </div>
                </div>
                <div class="role-pill"><i class="bi bi-person-badge"></i> Juruteknik</div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-boxes"></i> Aset Saya</a></div>
                    <hr class="nav-separator">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div><a href="index.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <div><h1 class="page-title">Hapus Aset</h1><p class="page-subtitle">Semak semula maklumat aset sebelum pemadaman diteruskan.</p></div>
                </div>

                <div class="card border-0">
                    <div class="card-header">
                        <i class="bi bi-exclamation-triangle-fill"></i> Pengesahan Pemadaman
                    </div>
                    <div class="card-body">
                        <p class="alert alert-warning">
                            <i class="bi bi-exclamation-circle"></i> Anda akan menghapuskan aset berikut. Tindakan ini tidak boleh dibatalkan!
                        </p>

                        <table class="table table-borderless">
                            <tr>
                                <td><strong>No. Pendaftaran:</strong></td>
                                <td><?php echo escapeOutput($aset['no_pendaftaran']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Jenis Aset:</strong></td>
                                <td><?php echo escapeOutput($aset['jenis_aset']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Jenama:</strong></td>
                                <td><?php echo escapeOutput($aset['jenama']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Model:</strong></td>
                                <td><?php echo escapeOutput($aset['model']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>No. Siri:</strong></td>
                                <td><?php echo escapeOutput($aset['no_siri_pencetak'] ?? '-'); ?></td>
                            </tr>
                        </table>

                        <form method="POST" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-trash"></i> Ya, Hapuskan Aset Ini
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-lg"></i> Batal
                            </a>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>