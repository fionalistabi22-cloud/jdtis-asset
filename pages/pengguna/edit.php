<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if (!in_array($_SESSION['peranan'], ['Super Admin', 'Admin Wilayah'])) {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$error = '';
$success = '';
$current_role = $_SESSION['peranan'];
$current_wilayah = $_SESSION['wilayah_id'] ?? 0;

// Get user ID
$user_id = intval($_GET['id'] ?? 0);
if ($user_id === 0) {
    die("ID pengguna tidak sah");
}

// Get user details
$user_query = "SELECT * FROM pengguna WHERE pengguna_id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    die("Pengguna tidak dijumpai");
}

// Admin Wilayah can only edit users in their wilayah
if ($current_role === 'Admin Wilayah' && $user['wilayah_id'] !== $current_wilayah && $user['peranan_id'] !== 1) {
    die("Anda tidak mempunyai akses untuk edit pengguna ini");
}

// Get all roles
$roles_query = "SELECT * FROM peranan ORDER BY tahap_hierarki ASC";
$roles_result = mysqli_query($conn, $roles_query);
$roles = mysqli_fetch_all($roles_result, MYSQLI_ASSOC);

// Get wilayah
$wilayah_query = "SELECT * FROM wilayah ORDER BY nama_wilayah ASC";
$wilayah_result = mysqli_query($conn, $wilayah_query);
$wilayah_list = mysqli_fetch_all($wilayah_result, MYSQLI_ASSOC);

// Get agensi
$agensi_query = "SELECT * FROM agensi ORDER BY nama_agensi ASC";
$agensi_result = mysqli_query($conn, $agensi_query);
$agensi_list = mysqli_fetch_all($agensi_result, MYSQLI_ASSOC);

// Get daerah
$daerah_query = "SELECT * FROM daerah ORDER BY nama_daerah ASC";
$daerah_result = mysqli_query($conn, $daerah_query);
$daerah_list = mysqli_fetch_all($daerah_result, MYSQLI_ASSOC);

// Get status
$status_query = "SELECT * FROM status_pengguna";
$status_result = mysqli_query($conn, $status_query);
$status_list = mysqli_fetch_all($status_result, MYSQLI_ASSOC);

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
    $emel = sanitize($_POST['emel'] ?? '');
    $no_telefon = sanitize($_POST['no_telefon'] ?? '');
    $peranan_id = intval($_POST['peranan_id'] ?? 0);
    $wilayah_id = intval($_POST['wilayah_id'] ?? 0);
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
            // Update user
            $update_query = "UPDATE pengguna SET nama_penuh = ?, emel = ?, no_telefon = ?, peranan_id = ?, wilayah_id = ?, daerah_id = ?, agensi_id = ?, status_pengguna_id = ?
                            WHERE pengguna_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sssiiiiii", $nama_penuh, $emel, $no_telefon, $peranan_id, $wilayah_id, $daerah_id, $agensi_id, $status_pengguna_id, $user_id);

            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Pengguna berjaya dikemaskini";
                // Log activity
                logActivity($conn, 'Kemaskini Pengguna', "Maklumat pengguna dikemaskini: $emel ($nama_penuh)");
                
                // Update local user array for display
                $user['nama_penuh'] = $nama_penuh;
                $user['emel'] = $emel;
                $user['no_telefon'] = $no_telefon;
                $user['peranan_id'] = $peranan_id;
                $user['wilayah_id'] = $wilayah_id;
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
    <title>Edit Pengguna - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
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
                                        <label for="no_telefon" class="form-label">No Telefon</label>
                                        <input type="tel" id="no_telefon" name="no_telefon" class="form-control" value="<?php echo escapeOutput($user['no_telefon'] ?? ''); ?>">
                                    </div>
                                </div>
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
                                        <label for="wilayah_id" class="form-label">Wilayah</label>
                                        <select id="wilayah_id" name="wilayah_id" class="form-select">
                                            <option value="0">-- Tidak Ditugaskan --</option>
                                            <?php foreach ($wilayah_list as $w): ?>
                                                <option value="<?php echo $w['wilayah_id']; ?>" <?php echo ($user['wilayah_id'] === $w['wilayah_id']) ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($w['nama_wilayah']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
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
                            </div>

                            <div class="row">
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
        // Daerah and Agensi data from server
        const daerahData = <?php echo json_encode($daerah_list); ?>;
        const agensiData = <?php echo json_encode($agensi_list); ?>;
        const currentDaerahId = <?php echo intval($user['daerah_id'] ?? 0); ?>;
        const currentAgensiId = <?php echo intval($user['agensi_id'] ?? 0); ?>;
        
        function updateDaerahOptions() {
            const wilayahId = document.getElementById('wilayah_id').value;
            const daerahSelect = document.getElementById('daerah_id');
            
            // Clear existing options except the first one
            daerahSelect.innerHTML = '<option value="0">-- Tidak Ditugaskan --</option>';
            
            // Clear agensi as well
            document.getElementById('agensi_id').innerHTML = '<option value="0">-- Tidak Ditugaskan --</option>';
            
            // If a wilayah is selected, add matching daerah
            if (wilayahId !== '0') {
                const filteredDaerah = daerahData.filter(d => d.wilayah_id == wilayahId);
                filteredDaerah.forEach(d => {
                    const option = document.createElement('option');
                    option.value = d.daerah_id;
                    option.textContent = d.nama_daerah;
                    if (d.daerah_id === currentDaerahId) {
                        option.selected = true;
                    }
                    daerahSelect.appendChild(option);
                });
            }
            
            // Update agensi options if a daerah is selected
            if (currentDaerahId !== 0) {
                updateAgensiOptions();
            }
        }
        
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
        updateDaerahOptions();
        
        // Update when wilayah or daerah changes
        document.getElementById('wilayah_id').addEventListener('change', updateDaerahOptions);
        document.getElementById('daerah_id').addEventListener('change', updateAgensiOptions);
    </script>
