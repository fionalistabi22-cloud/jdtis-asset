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

// Get all roles
$roles_query = "SELECT * FROM peranan ORDER BY tahap_hierarki ASC";
$roles_result = mysqli_query($conn, $roles_query);
$roles = mysqli_fetch_all($roles_result, MYSQLI_ASSOC);

// Get wilayah
$wilayah_query = "SELECT * FROM wilayah ORDER BY nama_wilayah ASC";
$wilayah_result = mysqli_query($conn, $wilayah_query);
$wilayah_list = mysqli_fetch_all($wilayah_result, MYSQLI_ASSOC);

// Get daerah
$daerah_query = "SELECT * FROM daerah ORDER BY nama_daerah ASC";
$daerah_result = mysqli_query($conn, $daerah_query);
$daerah_list = mysqli_fetch_all($daerah_result, MYSQLI_ASSOC);

// Get agensi
$agensi_query = "SELECT * FROM agensi ORDER BY nama_agensi ASC";
$agensi_result = mysqli_query($conn, $agensi_query);
$agensi_list = mysqli_fetch_all($agensi_result, MYSQLI_ASSOC);

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
    $emel = sanitize($_POST['emel'] ?? '');
    $no_telefon = sanitize($_POST['no_telefon'] ?? '');
    $peranan_id = intval($_POST['peranan_id'] ?? 0);
    $wilayah_id = intval($_POST['wilayah_id'] ?? 0);
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
    } elseif (empty($password)) {
        $error = "Kata laluan diperlukan";
    } elseif (strlen($password) < 6) {
        $error = "Kata laluan mesti sekurang-kurangnya 6 karakter";
    } elseif ($password !== $password_confirm) {
        $error = "Kata laluan tidak sepadan";
    } elseif ($peranan_id === 0) {
        $error = "Pilih peranan";
    } else {
        // Check if email already exists
        $check_query = "SELECT COUNT(*) as count FROM pengguna WHERE emel = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $emel);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_row = mysqli_fetch_assoc($check_result);

        if ($check_row['count'] > 0) {
            $error = "Emel ini sudah didaftar";
        } else {
            // Hash password
            $password_hash = hashPassword($password);

            // Insert user
            $insert_query = "INSERT INTO pengguna (nama_penuh, emel, no_telefon, kata_laluan_hash, peranan_id, wilayah_id, daerah_id, agensi_id, status_pengguna_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "ssssiiii", $nama_penuh, $emel, $no_telefon, $password_hash, $peranan_id, $wilayah_id, $daerah_id, $agensi_id);
            if (mysqli_stmt_execute($insert_stmt)) {
                $success = "Pengguna berjaya ditambah";                logActivity($conn, 'Tambah Pengguna', "Pengguna baru ditambah: $emel ($nama_penuh)");
                
                // Redirect after 2 seconds
                header("Refresh: 2; url=index.php");
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
    <title>Tambah Pengguna - JTDIS</title>
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
                    <h2><i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru</h2>
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
                                        <label for="no_telefon" class="form-label">No Telefon</label>
                                        <input type="tel" id="no_telefon" name="no_telefon" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="peranan_id" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select id="peranan_id" name="peranan_id" class="form-select" required>
                                            <option value="">-- Pilih Peranan --</option>
                                            <?php foreach ($roles as $role): ?>
                                                <?php 
                                                // Admin Wilayah can only assign roles level 2 and below
                                                if ($current_role === 'Admin Wilayah' && $role['tahap_hierarki'] < 2) {
                                                    continue;
                                                }
                                                ?>
                                                <option value="<?php echo $role['peranan_id']; ?>">
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
                                                <option value="<?php echo $w['wilayah_id']; ?>">
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
                                                <option value="<?php echo $a['agensi_id']; ?>">
                                                    <?php echo escapeOutput($a['nama_agensi']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
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
        // Daerah and Agensi data from server
        const daerahData = <?php echo json_encode($daerah_list); ?>;
        const agensiData = <?php echo json_encode($agensi_list); ?>;
        
        document.getElementById('wilayah_id').addEventListener('change', function() {
            const wilayahId = this.value;
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
                    daerahSelect.appendChild(option);
                });
            }
        });
        
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
