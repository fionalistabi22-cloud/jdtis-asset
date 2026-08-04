<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit;
}

if ($_SESSION['peranan'] !== 'Agen IT') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$pengguna_id = $_SESSION['pengguna_id'];
$error = '';
$success = '';

$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;
if ($aset_id === 0) {
    header("Location: index.php");
    exit;
}

// Fetch asset details
$aset_query = "SELECT a.* FROM aset a WHERE a.aset_id = ? AND a.pengguna_id_daftar = ?";
$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "ii", $aset_id, $pengguna_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);

if (!$aset) {
    header("Location: index.php");
    exit;
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
        $no_pendaftaran  = sanitize($_POST['no_pendaftaran'] ?? '');
        $jenis_aset      = sanitize($_POST['jenis_aset'] ?? '');
        $jenis_perolehan = sanitize($_POST['jenis_perolehan'] ?? '');
        $tahun_beli      = !empty($_POST['tahun_beli']) ? intval($_POST['tahun_beli']) : NULL;
        $jenama          = sanitize($_POST['jenama'] ?? '');
        $model           = sanitize($_POST['model'] ?? '');
        $catatan         = sanitize($_POST['catatan'] ?? '');
        $processor       = sanitize($_POST['processor'] ?? '');
        $ram             = sanitize($_POST['ram'] ?? '');
        $cakera_keras    = sanitize($_POST['cakera_keras'] ?? '');   // FIX: kini disimpan
        $sistem_operasi  = sanitize($_POST['sistem_operasi'] ?? ''); // FIX: kini disimpan
        $pegawai_nama    = sanitize($_POST['pegawai_nama'] ?? '');
        $pegawai_jawatan = sanitize($_POST['pegawai_jawatan'] ?? '');
        $pegawai_gred    = sanitize($_POST['pegawai_gred'] ?? '');

        // Selaraskan nilai jenis_pencetak dengan ENUM DB
        $jenis_pencetak_input = sanitize($_POST['printer_type'] ?? '');
        $jenis_pencetak = in_array($jenis_pencetak_input, ['Laser', 'Inkjet', 'Matrik', 'Tiada'], true)
                          ? $jenis_pencetak_input : 'Tiada';

        // Guna nama field yang betul — no_siri_pencetak
        $no_siri_pencetak = sanitize($_POST['no_siri_pencetak'] ?? '');

        // Selaraskan nilai jenis_perolehan dengan ENUM DB
        $perolehan_sah = ['Kerajaan Negeri', 'Kerajaan Persekutuan', 'Sewa Guna', 'Guna Sama', 'Lain'];
        if (!in_array($jenis_perolehan, $perolehan_sah, true)) {
            $jenis_perolehan = '';
        }

        // Selaraskan nilai jenis_aset dengan ENUM DB
        $jenis_aset_sah = ['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
        if (!in_array($jenis_aset, $jenis_aset_sah, true)) {
            $jenis_aset = '';
        }

        if (empty($no_pendaftaran)) {
            $error = "No. Pendaftaran Aset diperlukan";
        } elseif (empty($jenis_aset)) {
            $error = "Jenis Aset tidak sah";
        } elseif (empty($jenis_perolehan)) {
            $error = "Jenis Perolehan tidak sah";
        } else {
            // Semak no. pendaftaran unik (kecuali aset semasa)
            $check_query = "SELECT COUNT(*) as count FROM aset WHERE no_pendaftaran = ? AND aset_id != ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "si", $no_pendaftaran, $aset_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $check_row = mysqli_fetch_assoc($check_result);

            if ($check_row['count'] > 0) {
                $error = "No. Pendaftaran Aset ini sudah digunakan aset lain.";
            } else {
                // FIX: cakera_keras & sistem_operasi kini dimasukkan dalam UPDATE
                $update_query = "UPDATE aset SET 
                    no_pendaftaran = ?, jenis_aset = ?, jenis_perolehan = ?, tahun_beli = ?,
                    jenama = ?, model = ?,
                    no_siri_pencetak = ?,
                    catatan = ?,
                    processor = ?, ram = ?, cakera_keras = ?, sistem_operasi = ?, jenis_pencetak = ?,
                    pegawai_nama = ?, pegawai_jawatan = ?, pegawai_gred = ?
                    WHERE aset_id = ? AND pengguna_id_daftar = ?";

                $update_stmt = mysqli_prepare($conn, $update_query);

                if (!$update_stmt) {
                    $error = "Ralat: " . mysqli_error($conn);
                } else {
                    // 18 param: 16 s/i data + 2 i (aset_id, pengguna_id)
                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "sssissssssssssssii",
                        $no_pendaftaran,
                        $jenis_aset,
                        $jenis_perolehan,
                        $tahun_beli,
                        $jenama,
                        $model,
                        $no_siri_pencetak,
                        $catatan,
                        $processor,
                        $ram,
                        $cakera_keras,
                        $sistem_operasi,
                        $jenis_pencetak,
                        $pegawai_nama,
                        $pegawai_jawatan,
                        $pegawai_gred,
                        $aset_id,
                        $pengguna_id
                    );

                    if (mysqli_stmt_execute($update_stmt)) {
                        $success = "Aset berjaya dikemaskini!";
                        logActivity($conn, 'Edit Aset', "Aset $no_pendaftaran dikemaskini oleh Agen IT");

                        // Refresh data aset selepas update
                        mysqli_stmt_close($update_stmt);
                        $aset_stmt2 = mysqli_prepare($conn, $aset_query);
                        mysqli_stmt_bind_param($aset_stmt2, "ii", $aset_id, $pengguna_id);
                        mysqli_stmt_execute($aset_stmt2);
                        $aset = mysqli_fetch_assoc(mysqli_stmt_get_result($aset_stmt2));
                    } else {
                        $error = "Ralat semasa menyimpan: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Helper function untuk semak 'selected'
function pilihan($nilai_db, $nilai_option) {
    return (string)$nilai_db === (string)$nilai_option ? 'selected' : '';
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Aset - Agen IT JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../agen-it.css">
</head>
<body class="agen-it page-aset-edit">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-xl-2 sidebar p-4">
                <div class="brand-wrap">
                    <div class="brand-mark"><i class="bi bi-pc-display-horizontal"></i></div>
                    <div>
                        <h4 class="brand-title">JTDIS</h4>
                        <p class="brand-subtitle">Modul Aset ICT</p>
                    </div>
                </div>

                <div class="role-pill"><i class="bi bi-person-badge"></i> Agen IT</div>

                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-box-seam-fill"></i> Aset
                    </a>
                    <hr class="nav-divider">
                    <a class="nav-link" href="../../logout.php">
                        <i class="bi bi-box-arrow-left"></i> Log Keluar
                    </a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-xl-10 main-content">
                <div class="page-hero">
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary mb-3">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <h1 class="page-title">Edit Maklumat Aset</h1>
                        <p class="page-subtitle">Kemas kini maklumat aset yang dibenarkan oleh workflow.</p>
                    </div>
                    <div class="page-chip"><i class="bi bi-pencil-square"></i> Agen IT</div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo escapeOutput($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="no_pendaftaran" class="form-label">No. Pendaftaran Aset <span class="text-danger">*</span></label>
                                    <input type="text" id="no_pendaftaran" name="no_pendaftaran" class="form-control"
                                           value="<?php echo escapeOutput($aset['no_pendaftaran']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="jenis_aset" class="form-label">Jenis Aset <span class="text-danger">*</span></label>
                                    <select id="jenis_aset" name="jenis_aset" class="form-select" required>
                                        <option value="">-- Pilih Jenis Aset --</option>
                                        <option value="NB"       <?php echo pilihan($aset['jenis_aset'], 'NB'); ?>>Notebook (NB)</option>
                                        <option value="PC"       <?php echo pilihan($aset['jenis_aset'], 'PC'); ?>>Personal Computer (PC)</option>
                                        <option value="Pencetak" <?php echo pilihan($aset['jenis_aset'], 'Pencetak'); ?>>Pencetak</option>
                                        <option value="Monitor"  <?php echo pilihan($aset['jenis_aset'], 'Monitor'); ?>>Monitor</option>
                                        <option value="Lain"     <?php echo pilihan($aset['jenis_aset'], 'Lain'); ?>>Lain-lain</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="jenis_perolehan" class="form-label">Jenis Perolehan <span class="text-danger">*</span></label>
                                    <select id="jenis_perolehan" name="jenis_perolehan" class="form-select" required>
                                        <option value="">-- Pilih Jenis Perolehan --</option>
                                        <option value="Kerajaan Negeri"      <?php echo pilihan($aset['jenis_perolehan'], 'Kerajaan Negeri'); ?>>Kerajaan Negeri</option>
                                        <option value="Kerajaan Persekutuan" <?php echo pilihan($aset['jenis_perolehan'], 'Kerajaan Persekutuan'); ?>>Kerajaan Persekutuan</option>
                                        <option value="Sewa Guna"                 <?php echo pilihan($aset['jenis_perolehan'], 'Sewa Guna'); ?>>Sewa Guna</option>
                                        <option value="Guna Sama"             <?php echo pilihan($aset['jenis_perolehan'], 'Guna Sama'); ?>>Guna Sama</option>
                                        <option value="Lain"                 <?php echo pilihan($aset['jenis_perolehan'], 'Lain'); ?>>Lain-lain</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tahun_beli" class="form-label">Tahun Beli</label>
                                    <input type="number" id="tahun_beli" name="tahun_beli" class="form-control"
                                           min="2000" max="<?php echo date('Y'); ?>"
                                           value="<?php echo escapeOutput($aset['tahun_beli'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="jenama" class="form-label">Jenama</label>
                                    <input type="text" id="jenama" name="jenama" class="form-control"
                                           value="<?php echo escapeOutput($aset['jenama'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" id="model" name="model" class="form-control"
                                           value="<?php echo escapeOutput($aset['model'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pegawai_nama" class="form-label">Nama Pegawai</label>
                                    <input type="text" id="pegawai_nama" name="pegawai_nama" class="form-control"
                                           value="<?php echo escapeOutput($aset['pegawai_nama'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pegawai_jawatan" class="form-label">Jawatan Pegawai</label>
                                    <input type="text" id="pegawai_jawatan" name="pegawai_jawatan" class="form-control"
                                           value="<?php echo escapeOutput($aset['pegawai_jawatan'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pegawai_gred" class="form-label">Gred</label>
                                    <input type="text" id="pegawai_gred" name="pegawai_gred" class="form-control"
                                           value="<?php echo escapeOutput($aset['pegawai_gred'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="no_siri_pencetak" class="form-label">No. Siri Pencetak</label>
                                    <input type="text" id="no_siri_pencetak" name="no_siri_pencetak" class="form-control"
                                           value="<?php echo escapeOutput($aset['no_siri_pencetak'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="catatan" class="form-label">Catatan</label>
                                <textarea id="catatan" name="catatan" class="form-control" rows="3"><?php echo escapeOutput($aset['catatan'] ?? ''); ?></textarea>
                            </div>

                            <!-- Spesifikasi PC / Notebook -->
                            <div id="pc_specs" class="card mb-3 border-info asset-spec-section">
                                <div class="card-header bg-info text-white">
                                    <i class="bi bi-cpu"></i> Spesifikasi Perkakasan PC/Notebook
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="processor" class="form-label">Processor</label>
                                            <input type="text" id="processor" name="processor" class="form-control"
                                                   value="<?php echo escapeOutput($aset['processor'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="ram" class="form-label">RAM</label>
                                            <input type="text" id="ram" name="ram" class="form-control"
                                                   value="<?php echo escapeOutput($aset['ram'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="cakera_keras" class="form-label">Cakera Keras / Storan</label>
                                            <input type="text" id="cakera_keras" name="cakera_keras" class="form-control"
                                                   value="<?php echo escapeOutput($aset['cakera_keras'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="sistem_operasi" class="form-label">Sistem Operasi</label>
                                            <input type="text" id="sistem_operasi" name="sistem_operasi" class="form-control"
                                                   value="<?php echo escapeOutput($aset['sistem_operasi'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Spesifikasi Pencetak -->
                            <div id="printer_specs" class="card mb-3 border-danger asset-spec-section">
                                <div class="card-header bg-danger text-white">
                                    <i class="bi bi-printer"></i> Spesifikasi Pencetak
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="printer_type" class="form-label">Jenis Pencetak</label>
                                        <select id="printer_type" name="printer_type" class="form-select">
                                            <option value="Tiada"  <?php echo pilihan($aset['jenis_pencetak'] ?? 'Tiada', 'Tiada'); ?>>Tiada / -- Pilih --</option>
                                            <option value="Laser"  <?php echo pilihan($aset['jenis_pencetak'] ?? '', 'Laser'); ?>>Laser</option>
                                            <option value="Inkjet" <?php echo pilihan($aset['jenis_pencetak'] ?? '', 'Inkjet'); ?>>Inkjet</option>
                                            <option value="Matrik" <?php echo pilihan($aset['jenis_pencetak'] ?? '', 'Matrik'); ?>>Matrik</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Simpan Perubahan
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg ms-2">
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
    (function() {
        function updateSpecs() {
            const val = document.getElementById('jenis_aset').value;
            document.getElementById('pc_specs').style.display      = (val === 'PC' || val === 'NB') ? 'block' : 'none';
            document.getElementById('printer_specs').style.display = (val === 'Pencetak') ? 'block' : 'none';
        }
        document.getElementById('jenis_aset').addEventListener('change', updateSpecs);
        updateSpecs();
    })();
    </script>
</body>
</html>