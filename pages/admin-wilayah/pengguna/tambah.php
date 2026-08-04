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
$error = '';
$success = '';

// Get wilayah name
$wilayah_query = "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ?";
$wilayah_stmt = mysqli_prepare($conn, $wilayah_query);
mysqli_stmt_bind_param($wilayah_stmt, "i", $wilayah_id);
mysqli_stmt_execute($wilayah_stmt);
$wilayah_result = mysqli_stmt_get_result($wilayah_stmt);
$wilayah = mysqli_fetch_assoc($wilayah_result);

// Get roles (excluding Super Admin)
$roles_query = "SELECT * FROM peranan WHERE peranan_id != 1 ORDER BY tahap_hierarki ASC";
$roles_result = mysqli_query($conn, $roles_query);
$roles = mysqli_fetch_all($roles_result, MYSQLI_ASSOC);

// Get daerah in this wilayah
$daerah_query = "SELECT * FROM daerah WHERE wilayah_id = ? ORDER BY nama_daerah ASC";
$daerah_stmt = mysqli_prepare($conn, $daerah_query);
mysqli_stmt_bind_param($daerah_stmt, "i", $wilayah_id);
mysqli_stmt_execute($daerah_stmt);
$daerah_result = mysqli_stmt_get_result($daerah_stmt);
$daerah_list = mysqli_fetch_all($daerah_result, MYSQLI_ASSOC);

// Get agencies in this wilayah
$agensi_query = "SELECT a.* FROM agensi a
                 JOIN daerah d ON a.daerah_id = d.daerah_id
                 WHERE d.wilayah_id = ?
                 ORDER BY a.nama_agensi ASC";
$agensi_stmt = mysqli_prepare($conn, $agensi_query);
mysqli_stmt_bind_param($agensi_stmt, "i", $wilayah_id);
mysqli_stmt_execute($agensi_stmt);
$agensi_result = mysqli_stmt_get_result($agensi_stmt);
$agensi_list = mysqli_fetch_all($agensi_result, MYSQLI_ASSOC);

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
        $nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
        $emel = sanitize($_POST['emel'] ?? '');
        $peranan_id = intval($_POST['peranan_id'] ?? 0);
        $daerah_id = intval($_POST['daerah_id'] ?? 0);
        $agensi_id = intval($_POST['agensi_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (empty($nama_penuh)) {
            $error = "Nama penuh diperlukan";
        } elseif (empty($emel)) {
            $error = "Emel diperlukan";
        } elseif (!isValidEmail($emel)) {
            $error = "Format emel tidak sah";
        } elseif ($peranan_id === 0) {
            $error = "Pilih peranan";
        } elseif (empty($password)) {
            $error = "Kata laluan diperlukan";
        } elseif (strlen($password) < 6) {
            $error = "Kata laluan mesti sekurang-kurangnya 6 karakter";
        } elseif ($password !== $password_confirm) {
            $error = "Kata laluan tidak sepadan";
        } else {
            // Check if email already exists
            $check_query = "SELECT COUNT(*) as count FROM pengguna WHERE emel = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "s", $emel);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ($check_row['count'] > 0) {
                $error = "Emel ini sudah digunakan";
            } else {
                if ($daerah_id === 0) {
                    $daerah_id = null;
                }
                if ($agensi_id === 0) {
                    $agensi_id = null;
                }

                $password_hash = hashPassword($password);

                // Insert user
                $insert_query = "INSERT INTO pengguna (nama_penuh, emel, kata_laluan_hash, peranan_id, wilayah_id, daerah_id, agensi_id, status_pengguna_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "sssiiii", $nama_penuh, $emel, $password_hash, $peranan_id, $wilayah_id, $daerah_id, $agensi_id);

                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = "Pengguna berjaya ditambah";
                    logActivity($conn, 'Tambah Pengguna', "Pengguna baru ditambah: $emel ($nama_penuh) di wilayah $wilayah_id");
                    
                    // Clear form
                    header("Refresh: 1.5; url=index.php");
                } else {
                    $error = "Ralat: " . mysqli_error($conn);
                }
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
    <title>Pengguna Baru - Admin Wilayah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../admin-wilayah.css">
</head>
<body class="admin-wilayah page-pengguna-tambah">
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
                    <a href="index.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <h2><i class="bi bi-person-plus"></i> Pengguna Baru</h2>
                    <p class="text-muted">Wilayah: <strong><?php echo escapeOutput($wilayah['nama_wilayah']); ?></strong></p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo escapeOutput($success); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nama_penuh" class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                                        <input type="text" id="nama_penuh" name="nama_penuh" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emel" class="form-label">Emel <span class="text-danger">*</span></label>
                                        <input type="email" id="emel" name="emel" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="peranan_id" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select id="peranan_id" name="peranan_id" class="form-select" required>
                                            <option value="0">-- Pilih Peranan --</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['peranan_id']; ?>">
                                                    <?php echo escapeOutput($role['nama_peranan']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Super Admin tidak boleh ditambah oleh Admin Wilayah</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="daerah_id" class="form-label">Daerah</label>
                                        <select id="daerah_id" name="daerah_id" class="form-select">
                                            <option value="0">-- Tidak Ditugaskan --</option>
                                            <?php foreach ($daerah_list as $d): ?>
                                                <option value="<?php echo $d['daerah_id']; ?>">
                                                    <?php echo escapeOutput($d['nama_daerah']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hanya daerah di wilayah ini</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="agensi_id" class="form-label">Agensi</label>
                                        <select id="agensi_id" name="agensi_id" class="form-select">
                                            <option value="0">-- Tidak Ditugaskan --</option>
                                            <?php foreach ($agensi_list as $a): ?>
                                                <option value="<?php echo $a['agensi_id']; ?>">
                                                    <?php echo escapeOutput($a['nama_agensi']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hanya agensi di wilayah ini</small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Kata Laluan <span class="text-danger">*</span></label>
                                        <input type="password" id="password" name="password" class="form-control" required placeholder="Min 6 karakter">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password_confirm" class="form-label">Sahkan Kata Laluan <span class="text-danger">*</span></label>
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required placeholder="Ulangi kata laluan">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Simpan Pengguna
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-lg"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Agency data from server
        const agensiData = <?php echo json_encode($agensi_list); ?>;
        
        document.getElementById('daerah_id').addEventListener('change', function() {
            const daerahId = this.value;
            const agensiSelect = document.getElementById('agensi_id');
            
            // Clear existing options except the first one
            agensiSelect.innerHTML = '<option value="0">-- Tidak Ditugaskan --</option>';
            
            // If a daerah is selected, add matching agensi
            if (daerahId !== '0') {
                const filteredAgensi = agensiData.filter(a => a.daerah_id == daerahId);
                if (filteredAgensi.length > 0) {
                    filteredAgensi.forEach(a => {
                        const option = document.createElement('option');
                        option.value = a.agensi_id;
                        option.textContent = a.nama_agensi;
                        agensiSelect.appendChild(option);
                    });
                } else {
                    agensiSelect.innerHTML += '<option value="0" disabled>Tiada agensi untuk daerah ini</option>';
                }
            }
        });
    </script>
