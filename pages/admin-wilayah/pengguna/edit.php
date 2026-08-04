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

// Get user ID
$user_id = intval($_GET['id'] ?? 0);
if ($user_id === 0) {
    die("ID pengguna tidak sah");
}

// Get user details (only from this wilayah)
$user_query = "SELECT * FROM pengguna WHERE pengguna_id = ? AND (wilayah_id = ? OR peranan_id = 1)";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "ii", $user_id, $wilayah_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    die("Pengguna tidak dijumpai atau bukan dari wilayah anda");
}

// Cannot edit Super Admin
if ($user['peranan_id'] === 1) {
    die("Anda tidak boleh mengedit Super Admin");
}

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

// Get status
$status_query = "SELECT * FROM status_pengguna";
$status_result = mysqli_query($conn, $status_query);
$status_list = mysqli_fetch_all($status_result, MYSQLI_ASSOC);

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
    $emel = sanitize($_POST['emel'] ?? '');
    $peranan_id = intval($_POST['peranan_id'] ?? 0);
    $daerah_id = intval($_POST['daerah_id'] ?? 0);
    $agensi_id = intval($_POST['agensi_id'] ?? 0);
    $status_pengguna_id = intval($_POST['status_pengguna_id'] ?? 1);

    // Validation
    if (empty($nama_penuh)) {
        $error = "Nama penuh diperlukan";
    } elseif (empty($emel)) {
        $error = "Emel diperlukan";
    } elseif (!isValidEmail($emel)) {
        $error = "Format emel tidak sah";
    } elseif ($peranan_id === 0) {
        $error = "Pilih peranan";
    } else {
        // Check if email already exists (different from current)
        $check_query = "SELECT COUNT(*) as count FROM pengguna WHERE emel = ? AND pengguna_id != ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $emel, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);

        if ($check_row['count'] > 0) {
            $error = "Emel ini sudah digunakan oleh pengguna lain";
        } else {
            if ($daerah_id === 0) {
                $daerah_id = null;
            }
            if ($agensi_id === 0) {
                $agensi_id = null;
            }

            // Update user
            $update_query = "UPDATE pengguna SET nama_penuh = ?, emel = ?, peranan_id = ?, daerah_id = ?, agensi_id = ?, status_pengguna_id = ?
                            WHERE pengguna_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ssiiiii", $nama_penuh, $emel, $peranan_id, $daerah_id, $agensi_id, $status_pengguna_id, $user_id);

            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Pengguna berjaya dikemaskini";
                logActivity($conn, 'Kemaskini Pengguna', "Maklumat pengguna dikemaskini: $emel ($nama_penuh)");
                
                // Update local user array for display
                $user['nama_penuh'] = $nama_penuh;
                $user['emel'] = $emel;
                $user['peranan_id'] = $peranan_id;
                $user['daerah_id'] = $daerah_id;
                $user['agensi_id'] = $agensi_id;
                $user['status_pengguna_id'] = $status_pengguna_id;
            } else {
                $error = "Ralat: " . mysqli_error($conn);
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
    <title>Edit Pengguna - Admin Wilayah JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../admin-wilayah.css">
</head>
<body class="admin-wilayah page-pengguna-edit">
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
                    <h2><i class="bi bi-person-badge"></i> Edit Pengguna</h2>
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
                                        <input type="text" id="nama_penuh" name="nama_penuh" class="form-control" value="<?php echo escapeOutput($user['nama_penuh']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emel" class="form-label">Emel <span class="text-danger">*</span></label>
                                        <input type="email" id="emel" name="emel" class="form-control" value="<?php echo escapeOutput($user['emel']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="peranan_id" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select id="peranan_id" name="peranan_id" class="form-select" required>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['peranan_id']; ?>" <?php echo ($user['peranan_id'] === $role['peranan_id']) ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($role['nama_peranan']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
                                                <option value="<?php echo $d['daerah_id']; ?>" <?php echo ($user['daerah_id'] === $d['daerah_id']) ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($d['nama_daerah']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="agensi_id" class="form-label">Agensi</label>
                                        <select id="agensi_id" name="agensi_id" class="form-select">
                                            <option value="0">-- Tidak Ditugaskan --</option>
                                            <?php foreach ($agensi_list as $a): ?>
                                                <option value="<?php echo $a['agensi_id']; ?>" <?php echo ($user['agensi_id'] === $a['agensi_id']) ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($a['nama_agensi']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status_pengguna_id" class="form-label">Status</label>
                                        <select id="status_pengguna_id" name="status_pengguna_id" class="form-select">
                                            <?php foreach ($status_list as $s): ?>
                                                <option value="<?php echo $s['status_pengguna_id']; ?>" <?php echo ($user['status_pengguna_id'] === $s['status_pengguna_id']) ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($s['status']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Simpan Perubahan
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-lg"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">⚙️ Tindakan Lanjutan</h6>
                    </div>
                    <div class="card-body p-4">
                        <a href="reset_password.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-warning">
                            <i class="bi bi-key"></i> Reset Kata Laluan
                        </a>
                        <a href="hapus.php?id=<?php echo $user['pengguna_id']; ?>" class="btn btn-danger" onclick="return confirm('Pasti untuk hapus pengguna ini?');">
                            <i class="bi bi-trash"></i> Hapus Pengguna
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Agency data from server
        const agensiData = <?php echo json_encode($agensi_list); ?>;
        const currentAgensiId = <?php echo intval($user['agensi_id'] ?? 0); ?>;
        
        function updateAgensiOptions() {
            const daerahId = document.getElementById('daerah_id').value;
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
                        if (a.agensi_id === currentAgensiId) {
                            option.selected = true;
                        }
                        agensiSelect.appendChild(option);
                    });
                } else {
                    agensiSelect.innerHTML += '<option value="0" disabled>Tiada agensi untuk daerah ini</option>';
                }
            }
        }
        
        // Initialize on page load
        updateAgensiOptions();
        
        // Update when daerah changes
        document.getElementById('daerah_id').addEventListener('change', updateAgensiOptions);
    </script>
