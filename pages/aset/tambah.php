<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page (centralized guard: login + session expiry)
requireRoleWhitelist(['Juruteknik']);

$pengguna_id = $_SESSION['pengguna_id'];
$error = '';
$success = '';

// Get user wilayah_id and wilayah name
$user_stmt = mysqli_prepare($conn, "SELECT p.wilayah_id, w.nama_wilayah FROM pengguna p LEFT JOIN wilayah w ON p.wilayah_id = w.wilayah_id WHERE p.pengguna_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $pengguna_id);
mysqli_stmt_execute($user_stmt);
$user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
$wilayah_id = $user_row['wilayah_id'] ?? 0;
$nama_wilayah = $user_row['nama_wilayah'] ?? 'N/A';

// Senarai agensi dalam wilayah ini (directly, without daerah filter)
$agensi_stmt = mysqli_prepare($conn, 
    "SELECT a.agensi_id, a.nama_agensi 
     FROM agensi a 
     JOIN daerah d ON a.daerah_id = d.daerah_id 
     WHERE d.wilayah_id = ? 
     ORDER BY a.nama_agensi");
mysqli_stmt_bind_param($agensi_stmt, "i", $wilayah_id);
mysqli_stmt_execute($agensi_stmt);
$agensi_list = mysqli_fetch_all(mysqli_stmt_get_result($agensi_stmt), MYSQLI_ASSOC);

// ── Proses borang ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
        // Maklumat asas
        $no_pendaftaran  = sanitize($_POST['no_pendaftaran'] ?? '');
        $jenis_aset      = sanitize($_POST['jenis_aset'] ?? '');
        $jenis_perolehan = sanitize($_POST['jenis_perolehan'] ?? '');
        $tahun_beli      = !empty($_POST['tahun_beli']) ? intval($_POST['tahun_beli']) : null;
        $jenama          = sanitize($_POST['jenama'] ?? '');
        $model           = sanitize($_POST['model'] ?? '');
        $catatan         = sanitize($_POST['catatan'] ?? '');

        // Agensi dipilih
        $agensi_id = !empty($_POST['agensi_id']) ? intval($_POST['agensi_id']) : 0;

        // Spesifikasi PC/Notebook
        $processor       = sanitize($_POST['processor'] ?? '');
        $ram             = sanitize($_POST['ram'] ?? '');
        $cakera_keras    = sanitize($_POST['cakera_keras'] ?? '');

        // Spesifikasi Pencetak — jenis_pencetak & no_siri_pencetak ada dalam jadual aset
        $printer_type    = sanitize($_POST['printer_type'] ?? '');
        $printer_serial  = sanitize($_POST['printer_serial'] ?? '');
        $no_siri_umum    = sanitize($_POST['no_siri'] ?? '');

        // jenis_pencetak mesti sepadan dengan ENUM('Laser','Inkjet','Matrik','Tiada')
        $jenis_pencetak  = in_array($printer_type, ['Laser', 'Inkjet', 'Matrik'], true)
            ? $printer_type
            : 'Tiada';

        // Guna no. siri pencetak jika diisi, jika tidak guna no. siri am
        $no_siri_pencetak = !empty($printer_serial) ? $printer_serial : $no_siri_umum;

        // Maklumat Pegawai
        $pegawai_nama    = sanitize($_POST['pegawai_nama'] ?? '');
        $pegawai_jawatan = sanitize($_POST['pegawai_jawatan'] ?? '');
        $pegawai_gred    = sanitize($_POST['pegawai_gred'] ?? '');

        // ── Validasi ─────────────────────────────────────────────────────────
        if (empty($no_pendaftaran)) {
            $error = "No. Pendaftaran Aset diperlukan";
        } elseif (empty($jenis_aset)) {
            $error = "Jenis Aset diperlukan";
        } elseif (empty($jenis_perolehan)) {
            $error = "Jenis Perolehan diperlukan";
        } elseif ($agensi_id === 0) {
            $error = "Sila pilih agensi";
        } else {
            $agensi_stmt = mysqli_prepare(
                $conn,
                "SELECT agensi_id FROM agensi WHERE agensi_id = ? AND wilayah_id = ? LIMIT 1"
            );

            if (!$agensi_stmt) {
                $error = "Ralat penyediaan query agensi: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($agensi_stmt, "ii", $agensi_id, $wilayah_id);
                mysqli_stmt_execute($agensi_stmt);
                $agensi_row = mysqli_fetch_assoc(mysqli_stmt_get_result($agensi_stmt));
                mysqli_stmt_close($agensi_stmt);

                if (!$agensi_row) {
                    $error = "Agensi tidak sah untuk wilayah anda.";
                }
            }

            if (empty($error)) {
                // Semak no. pendaftaran unik
                $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM aset WHERE no_pendaftaran = ?");
                mysqli_stmt_bind_param($check_stmt, "s", $no_pendaftaran);
                mysqli_stmt_execute($check_stmt);
                $check_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
                mysqli_stmt_close($check_stmt);

                if ($check_row['count'] > 0) {
                    $error = "No. Pendaftaran Aset ini sudah terdaftar. Sila gunakan no. yang berlainan.";
                } else {
                    // ── INSERT — hanya kolum yang WUJUD dalam jadual aset ────────
                    // 18 placeholder (?) mesti sepadan dengan 18 pembolehubah bind_param.
                    // status_aset_id (=1) dan tarikh_input (=NOW()) adalah literal, bukan placeholder.
                    $insert_query = "INSERT INTO aset (
                        no_pendaftaran, jenis_aset, jenis_perolehan,
                        tahun_beli, jenama, model,
                        processor, ram, cakera_keras,
                        jenis_pencetak, no_siri_pencetak,
                        pegawai_nama, pegawai_jawatan, pegawai_gred,
                        agensi_id, wilayah_id, pengguna_id_daftar, catatan,
                        status_aset_id, tarikh_input
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        1, NOW()
                    )";

                    $insert_stmt = mysqli_prepare($conn, $insert_query);

                    if (!$insert_stmt) {
                        $error = "Ralat penyediaan query: " . mysqli_error($conn);
                    } else {
                        // 19 parameter:
                        // s no_pendaftaran, s jenis_aset, s jenis_perolehan,
                        // i tahun_beli, s jenama, s model,
                        // s processor, s ram, s cakera_keras, s sistem_operasi,
                        // s no_siri_pencetak, s jenis_pencetak,
                        // s pegawai_nama, s pegawai_jawatan, s pegawai_gred,
                        // i agensi_id, i pengguna_id, s catatan,
                        // i wilayah_id
                        mysqli_stmt_bind_param(
                            $insert_stmt,
                            "sssissssssssssiiis",
                            $no_pendaftaran, $jenis_aset, $jenis_perolehan,
                            $tahun_beli, $jenama, $model,
                            $processor, $ram, $cakera_keras,
                            $jenis_pencetak, $no_siri_pencetak,
                            $pegawai_nama, $pegawai_jawatan, $pegawai_gred,
                            $agensi_id, $wilayah_id, $pengguna_id, $catatan
                        );

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $aset_id = mysqli_insert_id($conn);
                            logActivity($conn, 'Daftar Aset', "Aset baru didaftarkan: $no_pendaftaran ($jenis_aset) oleh Juruteknik");

                            // Notifikasi dihantar kepada PPTM (peranan_id 4) & PTM (peranan_id 5)
                            // dalam wilayah yang sama, kerana mereka yang mengesahkan aset.
                            $mesej_notif = "Permohonan pengesahan aset: $no_pendaftaran (Juruteknik)";
                            $url_notif   = "pages/aset/semak.php?id=$aset_id";

                            $penerima_stmt = mysqli_prepare($conn,
                                "SELECT p.pengguna_id
                                 FROM pengguna p
                                 JOIN peranan r ON p.peranan_id = r.peranan_id
                                 WHERE r.nama_peranan IN ('PPTM', 'PTM')
                                   AND p.wilayah_id = ?
                                   AND p.status_pengguna_id = 1");
                            mysqli_stmt_bind_param($penerima_stmt, "i", $wilayah_id);
                            mysqli_stmt_execute($penerima_stmt);
                            $penerima_list = mysqli_fetch_all(mysqli_stmt_get_result($penerima_stmt), MYSQLI_ASSOC);
                            mysqli_stmt_close($penerima_stmt);

                            $notif_stmt = mysqli_prepare($conn,
                                "INSERT INTO notifikasi (penerima_id, aset_id, jenis, mesej, url, dibaca, tarikh)
                                 VALUES (?, ?, 'aset_baru', ?, ?, 0, NOW())");
                            foreach ($penerima_list as $penerima) {
                                $penerima_id = $penerima['pengguna_id'];
                                mysqli_stmt_bind_param($notif_stmt, "iiss", $penerima_id, $aset_id, $mesej_notif, $url_notif);
                                mysqli_stmt_execute($notif_stmt);
                            }
                            mysqli_stmt_close($notif_stmt);

                            $success = "Aset berjaya didaftarkan! No. pendaftaran: <strong>$no_pendaftaran</strong><br>Status: Menunggu Pengesahan";
                            header("Refresh: 2; url=index.php");
                        } else {
                            $error = "Ralat semasa menyimpan: " . mysqli_error($conn);
                        }

                        mysqli_stmt_close($insert_stmt);
                    }
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
    <title>Daftar Aset Baru - Juruteknik JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="aset.css">

</head>
<body class="page-tambah">
<div class="container-fluid">
    <div class="row">

        <!-- ── SIDEBAR ───────────────────────────────────────────────────── -->
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
                <a class="nav-link active" href="index.php"><i class="bi bi-hdd"></i> Aset</a>
                <hr style="border-color:rgba(255,255,255,0.2);">
                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </aside>

        <!-- ── KANDUNGAN UTAMA ────────────────────────────────────────────── -->
        <main class="col-md-9 col-xl-10 main-content">
            <div class="page-hero">
                <div>
                    <a href="index.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <h1 class="page-title">Daftar Aset Baharu</h1>
                    <p class="page-subtitle">Lengkapkan maklumat aset dan pegawai sebelum dihantar untuk pengesahan.</p>
                </div>
                <div class="page-chip"><i class="bi bi-geo-alt"></i> <?php echo escapeOutput($nama_wilayah); ?></div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- ── Maklumat Asas ───────────────────────────────── -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. Pendaftaran Aset <span class="text-danger">*</span></label>
                                <input type="text" name="no_pendaftaran" class="form-control"
                                       placeholder="cth: ASET-2026-001" required
                                       value="<?php echo escapeOutput($_POST['no_pendaftaran'] ?? ''); ?>">
                                <small class="text-muted">No. mesti unik dan tidak boleh diulang</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Aset <span class="text-danger">*</span></label>
                                <select id="jenis_aset" name="jenis_aset" class="form-select" required>
                                    <option value="">-- Pilih Jenis Aset --</option>
                                    <option value="NB"       <?php if(($_POST['jenis_aset']??'')==='NB') echo 'selected'; ?>>Notebook (NB)</option>
                                    <option value="PC"       <?php if(($_POST['jenis_aset']??'')==='PC') echo 'selected'; ?>>Personal Computer (PC)</option>
                                    <option value="Pencetak" <?php if(($_POST['jenis_aset']??'')==='Pencetak') echo 'selected'; ?>>Pencetak</option>
                                    <option value="Monitor"  <?php if(($_POST['jenis_aset']??'')==='Monitor') echo 'selected'; ?>>Monitor</option>
                                    <option value="Lain"     <?php if(($_POST['jenis_aset']??'')==='Lain') echo 'selected'; ?>>Lain-lain</option>
                                </select>
                            </div>
                        </div>

                        <!-- ── Pilih Wilayah & Agensi ──────────────────────── -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Wilayah</label>
                                <div class="form-control" style="background-color: #f0f0f0; cursor: default;">
                                    <strong><?php echo escapeOutput($nama_wilayah); ?></strong>
                                </div>
                                <small class="text-muted">Wilayah anda (tetap)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Agensi <span class="text-danger">*</span></label>
                                <select id="agensi_id" name="agensi_id" class="form-select" required>
                                    <option value="">-- Pilih Agensi --</option>
                                    <?php foreach ($agensi_list as $a): ?>
                                        <option value="<?php echo $a['agensi_id']; ?>"
                                                <?php if(($_POST['agensi_id']??'')==$a['agensi_id']) echo 'selected'; ?>>
                                            <?php echo escapeOutput($a['nama_agensi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih agensi di mana aset ini akan disimpan</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Perolehan <span class="text-danger">*</span></label>
                                <select name="jenis_perolehan" class="form-select" required>
                                    <option value="">-- Pilih Jenis Perolehan --</option>
                                    <option value="Kerajaan Negeri"      <?php if(($_POST['jenis_perolehan']??'')==='Kerajaan Negeri') echo 'selected'; ?>>Kerajaan Negeri</option>
                                    <option value="Kerajaan Persekutuan" <?php if(($_POST['jenis_perolehan']??'')==='Kerajaan Persekutuan') echo 'selected'; ?>>Kerajaan Persekutuan</option>
                                    <option value="Sewa Guna"                 <?php if(($_POST['jenis_perolehan']??'')==='Sewa Guna') echo 'selected'; ?>>Sewa Guna</option>
                                    <option value="Guna Sama"             <?php if(($_POST['jenis_perolehan']??'')==='Guna Sama') echo 'selected'; ?>>Guna Sama</option>
                                    <option value="Lain"                 <?php if(($_POST['jenis_perolehan']??'')==='Lain') echo 'selected'; ?>>Lain-lain</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tahun Beli</label>
                                <input type="number" name="tahun_beli" class="form-control"
                                       placeholder="2026" min="2000" max="2100"
                                       value="<?php echo escapeOutput($_POST['tahun_beli'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenama</label>
                                <input type="text" name="jenama" class="form-control"
                                       placeholder="cth: Dell, HP, Lenovo"
                                       value="<?php echo escapeOutput($_POST['jenama'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control"
                                       placeholder="cth: Inspiron 15"
                                       value="<?php echo escapeOutput($_POST['model'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">No. Siri</label>
                            <input type="text" name="no_siri" class="form-control"
                                   placeholder="cth: SN1234567890"
                                   value="<?php echo escapeOutput($_POST['no_siri'] ?? ''); ?>">
                            <small class="text-muted">Digunakan jika aset bukan pencetak, atau tiada no. siri pencetak khusus diisi di bawah</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" class="form-control" rows="3"
                                      placeholder="Maklumat tambahan tentang aset..."><?php echo escapeOutput($_POST['catatan'] ?? ''); ?></textarea>
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
                                        <input type="text" id="processor" name="processor" class="form-control"
                                               placeholder="cth: Intel i7-11700K"
                                               value="<?php echo escapeOutput($_POST['processor'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="ram" class="form-label">RAM</label>
                                        <input type="text" id="ram" name="ram" class="form-control"
                                               placeholder="cth: 16GB DDR4"
                                               value="<?php echo escapeOutput($_POST['ram'] ?? ''); ?>">
                                         </div>
                                         <div class="col-md-6 mb-3">
                                        <label for="sistem_operasi" class="form-label">Sistem Operasi</label>
                                        <input type="text" id="sistem_operasi" name="sistem_operasi" class="form-control"
                                               placeholder="cth: Windows 11 Pro"
                                               value="<?php echo escapeOutput($_POST['sistem_operasi'] ?? ''); ?>">
                                    </div>
                                    
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cakera_keras" class="form-label">Cakera Keras</label>
                                        <input type="text" id="cakera_keras" name="cakera_keras" class="form-control"
                                               placeholder="cth: 512GB SSD"
                                               value="<?php echo escapeOutput($_POST['cakera_keras'] ?? ''); ?>">
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
                                        <option value="Laser"  <?php if(($_POST['printer_type']??'')==='Laser') echo 'selected'; ?>>Laser</option>
                                        <option value="Inkjet" <?php if(($_POST['printer_type']??'')==='Inkjet') echo 'selected'; ?>>Inkjet</option>
                                        <option value="Matrik" <?php if(($_POST['printer_type']??'')==='Matrik') echo 'selected'; ?>>Matrik</option>
                                    </select>
                                    <small class="text-muted">Nilai mesti sepadan dengan ENUM dalam jadual aset (Laser/Inkjet/Matrik/Tiada)</small>
                                </div>
                                <div class="mb-3">
                                    <label for="printer_serial" class="form-label">No. Siri Pencetak</label>
                                    <input type="text" id="printer_serial" name="printer_serial" class="form-control"
                                           placeholder="cth: SN12345678"
                                           value="<?php echo escapeOutput($_POST['printer_serial'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- ── Maklumat Pegawai/Pengguna ───────────────────── -->
                        <div class="card mb-3 border-secondary">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-person-vcard"></i> Maklumat Pegawai/Pengguna
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="pegawai_nama" class="form-label">Nama Pegawai/Pengguna</label>
                                        <input type="text" id="pegawai_nama" name="pegawai_nama" class="form-control"
                                               value="<?php echo escapeOutput($_POST['pegawai_nama'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="pegawai_jawatan" class="form-label">Jawatan</label>
                                        <input type="text" id="pegawai_jawatan" name="pegawai_jawatan" class="form-control"
                                               value="<?php echo escapeOutput($_POST['pegawai_jawatan'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="pegawai_gred" class="form-label">Gred</label>
                                    <input type="text" id="pegawai_gred" name="pegawai_gred" class="form-control"
                                           value="<?php echo escapeOutput($_POST['pegawai_gred'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> <strong>Nota:</strong>
                            Aset yang didaftarkan akan mempunyai status <em>"Menunggu Pengesahan"</em> sehingga diluluskan oleh PPTM/PTM.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg"></i> Daftar Aset
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-lg">
                                <i class="bi bi-x-lg"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Conditional display for specifications - more robust approach
(function() {
    function initSpecs() {
        const jenisSelect = document.querySelector('#jenis_aset');
        const pcSpecs = document.querySelector('#pc_specs');
        const printerSpecs = document.querySelector('#printer_specs');
        
        if (!jenisSelect || !pcSpecs || !printerSpecs) {
            console.error('Missing elements for specs initialization');
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
    
    // Multiple initialization strategies
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSpecs);
    } else {
        initSpecs();
    }
    
    // Also try after a small delay to ensure elements are ready
    setTimeout(initSpecs, 100);
})();
</script>
</body>
</html>