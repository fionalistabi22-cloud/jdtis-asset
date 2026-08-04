<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Only Juruteknik can access this
if ($_SESSION['peranan'] !== 'Juruteknik') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$pengguna_id = $_SESSION['pengguna_id'];
$error = '';
$success = '';

// Get asset ID from URL
$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;

if ($aset_id === 0) {
    header("Location: index.php");
    exit;
}

// Fetch asset details
$aset_query = "SELECT a.* FROM aset a 
               WHERE a.aset_id = ? AND a.pengguna_id_daftar = ?";
$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "ii", $aset_id, $pengguna_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);

if (!$aset) {
    header("Location: index.php");
    exit;
}

// Get daerah and agensi lists
$wilayah_query = "SELECT p.wilayah_id, w.nama_wilayah FROM pengguna p LEFT JOIN wilayah w ON p.wilayah_id = w.wilayah_id WHERE p.pengguna_id = ?";
$wilayah_stmt = mysqli_prepare($conn, $wilayah_query);
mysqli_stmt_bind_param($wilayah_stmt, "i", $pengguna_id);
mysqli_stmt_execute($wilayah_stmt);
$wilayah_result = mysqli_stmt_get_result($wilayah_stmt);
$wilayah_row = mysqli_fetch_assoc($wilayah_result);
$wilayah_id = $wilayah_row['wilayah_id'];
$nama_wilayah = $wilayah_row['nama_wilayah'];

$agensi_query = "SELECT a.agensi_id, a.nama_agensi FROM agensi a
                 JOIN daerah d ON a.daerah_id = d.daerah_id
                 WHERE d.wilayah_id = ? ORDER BY a.nama_agensi";
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
        $no_pendaftaran = sanitize($_POST['no_pendaftaran'] ?? '');
        $jenis_aset = sanitize($_POST['jenis_aset'] ?? '');
        $jenis_perolehan = sanitize($_POST['jenis_perolehan'] ?? '');
        $tahun_beli = !empty($_POST['tahun_beli']) ? intval($_POST['tahun_beli']) : null;

        // Selaraskan nilai jenis_perolehan dengan ENUM dalam DB
        // ENUM('Kerajaan Negeri','Kerajaan Persekutuan','Sewa','Pinjaman','Lain')
        $perolehan_sah = ['Kerajaan Negeri', 'Kerajaan Persekutuan', 'Sewa', 'Pinjaman', 'Lain'];
        if (!in_array($jenis_perolehan, $perolehan_sah, true)) {
            $jenis_perolehan = '';
        }

        $jenama = sanitize($_POST['jenama'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $catatan = sanitize($_POST['catatan'] ?? '');

        $agensi_id = !empty($_POST['agensi_id']) ? intval($_POST['agensi_id']) : 0;

        // PC specs
        $processor = sanitize($_POST['processor'] ?? '');
        $ram = sanitize($_POST['ram'] ?? '');
        $cakera_keras = sanitize($_POST['cakera_keras'] ?? '');

        // Printer specs
        $printer_type = sanitize($_POST['printer_type'] ?? '');
        $printer_serial = sanitize($_POST['printer_serial'] ?? '');

        // jenis_pencetak mesti sepadan dengan ENUM('Laser','Inkjet','Matrik','Tiada')
        $jenis_pencetak = in_array($printer_type, ['Laser', 'Inkjet', 'Matrik'], true)
            ? $printer_type
            : 'Tiada';

        // No. Siri
        $no_siri = trim($_POST['no_siri'] ?? '');

        // Guna no. siri pencetak jika diisi, jika tidak guna no. siri am
        $no_siri_pencetak = !empty($printer_serial) ? $printer_serial : $no_siri;

        // Officer info
        $pegawai_nama = sanitize($_POST['pegawai_nama'] ?? '');
        $pegawai_jawatan = sanitize($_POST['pegawai_jawatan'] ?? '');
        $pegawai_gred = trim($_POST['pegawai_gred'] ?? '');

        if (empty($no_pendaftaran)) {
            $error = "No. Pendaftaran Aset diperlukan";
        } elseif (empty($jenis_aset)) {
            $error = "Jenis Aset diperlukan";
        } elseif (empty($jenis_perolehan)) {
            $error = "Jenis Perolehan diperlukan";
        } elseif ($agensi_id === 0) {
            $error = "Sila pilih agensi";
        } else {
            // Check if registration number is unique (excluding current asset)
            $check_query = "SELECT COUNT(*) as count FROM aset WHERE no_pendaftaran = ? AND aset_id != ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "si", $no_pendaftaran, $aset_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ($check_row['count'] > 0) {
                $error = "No. Pendaftaran Aset ini sudah digunakan aset lain.";
            } else {
                // ── UPDATE — hanya kolum yang WUJUD dalam jadual aset, guna prepared statement ──
                $update_query = "UPDATE aset SET 
                    no_pendaftaran = ?,
                    jenis_aset = ?,
                    jenis_perolehan = ?,
                    tahun_beli = ?,
                    jenama = ?,
                    model = ?,
                    processor = ?,
                    ram = ?,
                    cakera_keras = ?,
                    jenis_pencetak = ?,
                    no_siri_pencetak = ?,
                    pegawai_nama = ?,
                    pegawai_jawatan = ?,
                    pegawai_gred = ?,
                    catatan = ?,
                    agensi_id = ?
                    WHERE aset_id = ? AND pengguna_id_daftar = ?";

                $update_stmt = mysqli_prepare($conn, $update_query);

                if (!$update_stmt) {
                    $error = "Ralat penyediaan query: " . mysqli_error($conn);
                } else {
                    // 18 placeholders => 18 characters, 18 variables
                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "sssisssssssssssiii",
                        $no_pendaftaran, $jenis_aset, $jenis_perolehan,
                        $tahun_beli, $jenama, $model,
                        $processor, $ram, $cakera_keras,
                        $jenis_pencetak, $no_siri_pencetak,
                        $pegawai_nama, $pegawai_jawatan, $pegawai_gred,
                        $catatan, $agensi_id,
                        $aset_id, $pengguna_id
                    );

                    if (mysqli_stmt_execute($update_stmt)) {
                        $success = "Aset berjaya dikemaskini!";
                        logActivity($conn, 'Edit Aset', "Aset $no_pendaftaran dikemaskini oleh Juruteknik");

                        // Refresh asset data
                        $aset_stmt = mysqli_prepare($conn, $aset_query);
                        mysqli_stmt_bind_param($aset_stmt, "ii", $aset_id, $pengguna_id);
                        mysqli_stmt_execute($aset_stmt);
                        $aset_result = mysqli_stmt_get_result($aset_stmt);
                        $aset = mysqli_fetch_assoc($aset_result);
                    } else {
                        $error = "Ralat semasa menyimpan: " . mysqli_error($conn);
                    }

                    mysqli_stmt_close($update_stmt);
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
    <title>Edit Aset - Juruteknik JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="aset.css">

</head>
<body class="page-edit">
    <div class="container-fluid">
        <div class="app-shell">
            <!-- SIDEBAR -->
            <aside class="sidebar">
                <div class="brand-wrap">
                    <img
                        src="/jdtis_asset/assets/images/jtdi-logo.png"
                        alt="Logo Jabatan Teknologi Digital dan Inovasi Negeri Sabah"
                        class="brand-logo"
                        onerror="this.style.display='none';"
                    >
                </div>
                <div class="role-pill"><i class="bi bi-person-badge"></i> Juruteknik</div>
                <nav class="nav flex-column sidebar-nav">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-boxes"></i> Aset Saya</a>
                    <hr class="nav-separator">
                    <a class="nav-link logout-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="main-content">
                <div class="content-container">
                <div class="page-hero">
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary mb-3">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>

                        <h1 class="page-title">
                            <i class="bi bi-pencil-square"></i>
                            Edit Aset
                        </h1>

                        <p class="page-subtitle">
                            Kemas kini maklumat aset yang telah didaftarkan.
                        </p>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="no_pendaftaran" class="form-label">No. Pendaftaran Aset <span class="text-danger">*</span></label>
                                        <input type="text" id="no_pendaftaran" name="no_pendaftaran" class="form-control" maxlength="100" value="<?php echo escapeOutput($aset['no_pendaftaran']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenis_aset" class="form-label">Jenis Aset <span class="text-danger">*</span></label>
                                        <select id="jenis_aset" name="jenis_aset" class="form-select" required>
                                            <option value="">-- Pilih Jenis Aset --</option>
                                            <option value="NB" <?php echo $aset['jenis_aset'] === 'NB' ? 'selected' : ''; ?>>Notebook (NB)</option>
                                            <option value="PC" <?php echo $aset['jenis_aset'] === 'PC' ? 'selected' : ''; ?>>Personal Computer (PC)</option>
                                            <option value="Pencetak" <?php echo $aset['jenis_aset'] === 'Pencetak' ? 'selected' : ''; ?>>Pencetak</option>
                                            <option value="Monitor" <?php echo $aset['jenis_aset'] === 'Monitor' ? 'selected' : ''; ?>>Monitor</option>
                                            <option value="Lain" <?php echo $aset['jenis_aset'] === 'Lain' ? 'selected' : ''; ?>>Lain-lain</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="wilayah" class="form-label">Wilayah</label>
                                        <div class="form-control" style="background-color: #f0f0f0; cursor: default;">
                                            <strong><?php echo escapeOutput($nama_wilayah); ?></strong>
                                        </div>
                                        <small class="text-muted">Wilayah anda (tetap)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="agensi_id" class="form-label">Agensi <span class="text-danger">*</span></label>
                                        <select id="agensi_id" name="agensi_id" class="form-select" required>
                                            <option value="">-- Pilih Agensi --</option>
                                            <?php foreach ($agensi_list as $agensi): ?>
                                                <option value="<?php echo $agensi['agensi_id']; ?>" <?php echo $aset['agensi_id'] == $agensi['agensi_id'] ? 'selected' : ''; ?>>
                                                    <?php echo escapeOutput($agensi['nama_agensi']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Pilih agensi di mana aset ini disimpan</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenis_perolehan" class="form-label">Jenis Perolehan <span class="text-danger">*</span></label>
                                        <select id="jenis_perolehan" name="jenis_perolehan" class="form-select" required>
                                            <option value="">-- Pilih Jenis Perolehan --</option>
                                            <option value="Kerajaan Negeri" <?php echo $aset['jenis_perolehan'] === 'Kerajaan Negeri' ? 'selected' : ''; ?>>Kerajaan Negeri</option>
                                            <option value="Kerajaan Persekutuan" <?php echo $aset['jenis_perolehan'] === 'Kerajaan Persekutuan' ? 'selected' : ''; ?>>Kerajaan Persekutuan</option>
                                            <option value="Sewa" <?php echo $aset['jenis_perolehan'] === 'Sewa' ? 'selected' : ''; ?>>Sewa</option>
                                            <option value="Pinjaman" <?php echo $aset['jenis_perolehan'] === 'Pinjaman' ? 'selected' : ''; ?>>Pinjaman</option>
                                            <option value="Lain" <?php echo $aset['jenis_perolehan'] === 'Lain' ? 'selected' : ''; ?>>Lain-lain</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tahun_beli" class="form-label">Tahun Beli</label>
                                        <input type="number" id="tahun_beli" name="tahun_beli" class="form-control" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo escapeOutput($aset['tahun_beli']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jenama" class="form-label">Jenama</label>
                                        <input type="text" id="jenama" name="jenama" class="form-control" value="<?php echo escapeOutput($aset['jenama']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="model" class="form-label">Model</label>
                                        <input type="text" id="model" name="model" class="form-control" value="<?php echo escapeOutput($aset['model']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pegawai_nama" class="form-label">Nama Pegawai</label>
                                        <input type="text" id="pegawai_nama" name="pegawai_nama" class="form-control" value="<?php echo escapeOutput($aset['pegawai_nama']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pegawai_jawatan" class="form-label">Jawatan Pegawai</label>
                                        <input type="text" id="pegawai_jawatan" name="pegawai_jawatan" class="form-control" value="<?php echo escapeOutput($aset['pegawai_jawatan']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="pegawai_gred" class="form-label">Gred</label>
                                <input type="text" id="pegawai_gred" name="pegawai_gred" class="form-control" value="<?php echo escapeOutput($aset['pegawai_gred'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="no_siri" class="form-label">No. Siri</label>
                                <input type="text" id="no_siri" name="no_siri" class="form-control" value="<?php echo escapeOutput($aset['no_siri_pencetak'] ?? ''); ?>">
                                <small class="text-muted">Digunakan jika aset bukan pencetak, atau tiada no. siri pencetak khusus diisi di bawah</small>
                            </div>

                            <div class="mb-3">
                                <label for="catatan" class="form-label">Catatan</label>
                                <textarea id="catatan" name="catatan" class="form-control" rows="3"><?php echo escapeOutput($aset['catatan']); ?></textarea>
                            </div>

                            <!-- ── Spesifikasi PC / Notebook ───────────────────── -->
                            <div id="pc_specs" style="display:none;" class="card mb-3 border-info">
                                <div class="card-header bg-info text-white">
                                    <i class="bi bi-cpu"></i> Spesifikasi Perkakasan PC/Notebook
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="processor" class="form-label">Processor</label>
                                            <input type="text" id="processor" name="processor" class="form-control" value="<?php echo escapeOutput($aset['processor'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="ram" class="form-label">RAM</label>
                                            <input type="text" id="ram" name="ram" class="form-control" value="<?php echo escapeOutput($aset['ram'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="cakera_keras" class="form-label">Cakera Keras</label>
                                            <input type="text" id="cakera_keras" name="cakera_keras" class="form-control" placeholder="cth: 512GB SSD" value="<?php echo escapeOutput($aset['cakera_keras'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Spesifikasi Pencetak ────────────────────────── -->
                            <div id="printer_specs" style="display:none;" class="card mb-3 border-danger">
                                <div class="card-header">
                                    <i class="bi bi-printer"></i> Spesifikasi Pencetak
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="printer_type" class="form-label">Jenis Pencetak</label>
                                        <select id="printer_type" name="printer_type" class="form-select">
                                            <option value="">-- Pilih Jenis --</option>
                                            <option value="Laser" <?php echo ($aset['jenis_pencetak'] === 'Laser') ? 'selected' : ''; ?>>Laser</option>
                                            <option value="Inkjet" <?php echo ($aset['jenis_pencetak'] === 'Inkjet') ? 'selected' : ''; ?>>Inkjet</option>
                                            <option value="Matrik" <?php echo ($aset['jenis_pencetak'] === 'Matrik') ? 'selected' : ''; ?>>Matrik</option>
                                        </select>
                                        <small class="text-muted">Nilai mesti sepadan dengan ENUM dalam jadual aset (Laser/Inkjet/Matrik/Tiada)</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="printer_serial" class="form-label">No. Siri Pencetak</label>
                                        <input type="text" id="printer_serial" name="printer_serial" class="form-control" value="<?php echo escapeOutput($aset['no_siri_pencetak'] ?? ''); ?>">
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
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Conditional display for specifications -->
    <script>
    (function() {
        // Wait for DOM to be ready
        function initSpecs() {
            const jenisSelect = document.querySelector('#jenis_aset');
            const pcSpecs = document.querySelector('#pc_specs');
            const printerSpecs = document.querySelector('#printer_specs');
            
            if (!jenisSelect || !pcSpecs || !printerSpecs) {
                return;
            }
            
            function updateDisplay() {
                const value = jenisSelect.value;
                
                // PC/NB specs
                if (value === 'PC' || value === 'NB') {
                    pcSpecs.style.display = 'block';
                } else {
                    pcSpecs.style.display = 'none';
                }
                
                // Printer specs
                if (value === 'Pencetak') {
                    printerSpecs.style.display = 'block';
                } else {
                    printerSpecs.style.display = 'none';
                }
            }
            
            // Update on change
            jenisSelect.addEventListener('change', updateDisplay);
            
            // Initial update
            updateDisplay();
        }
        
        // Try multiple ways to ensure initialization
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSpecs);
        } else {
            initSpecs();
        }
        
        // Also try after a small delay
        setTimeout(initSpecs, 100);
    })();
    </script>
</body>
</html>