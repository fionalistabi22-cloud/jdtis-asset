<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit;
}

// Only Agen IT can access this
if ($_SESSION['peranan'] !== 'Agen IT') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$pengguna_id = $_SESSION['pengguna_id'];
$error = '';
$success = '';

// Get user agensi
$agensi_query = "SELECT pengguna.agensi_id, pengguna.wilayah_id, agensi.nama_agensi, daerah.nama_daerah
                FROM pengguna 
                LEFT JOIN agensi ON pengguna.agensi_id = agensi.agensi_id
                LEFT JOIN daerah ON agensi.daerah_id = daerah.daerah_id
                WHERE pengguna.pengguna_id = ?";
$agensi_stmt = mysqli_prepare($conn, $agensi_query);
mysqli_stmt_bind_param($agensi_stmt, "i", $pengguna_id);
mysqli_stmt_execute($agensi_stmt);
$agensi_result = mysqli_stmt_get_result($agensi_stmt);
$user_agensi = mysqli_fetch_assoc($agensi_result);
$agensi_id = $user_agensi['agensi_id'] ?? 0;
$wilayah_id = $user_agensi['wilayah_id'] ?? null;

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
        // ── Maklumat Asas Aset ──────────────────────────────────────────────
        $no_pendaftaran  = sanitize($_POST['no_pendaftaran'] ?? '');
        $jenis_aset      = sanitize($_POST['jenis_aset'] ?? '');
        $jenis_perolehan = sanitize($_POST['jenis_perolehan'] ?? '');
        $tahun_beli      = !empty($_POST['tahun_beli']) ? intval($_POST['tahun_beli']) : null;
        $jenama          = sanitize($_POST['jenama'] ?? '');
        $model           = sanitize($_POST['model'] ?? '');
        $catatan         = sanitize($_POST['catatan'] ?? '');

        // ── Spesifikasi PC / Notebook (selaras dengan column DB) ─────────────
        $processor    = sanitize($_POST['processor'] ?? '');
        $ram          = sanitize($_POST['ram'] ?? '');
        $cakera_keras = sanitize($_POST['cakera_keras'] ?? '');   // FIX: column wujud dlm DB
        $sistem_operasi = sanitize($_POST['sistem_operasi'] ?? ''); // FIX: column wujud dlm DB

        // ── Spesifikasi Pencetak ─────────────────────────────────────────────
        $printer_type_input = sanitize($_POST['printer_type'] ?? '');
        $jenis_pencetak = in_array($printer_type_input, ['Laser', 'Inkjet', 'Matrik', 'Tiada'], true) ? $printer_type_input : 'Tiada';
        // FIX: guna satu nama field konsisten = no_siri_pencetak (column DB)
        $no_siri_pencetak = sanitize($_POST['no_siri_pencetak'] ?? '');

        // ── Maklumat Pegawai/Pengguna ────────────────────────────────────────
        $pegawai_nama    = sanitize($_POST['pegawai_nama'] ?? '');
        $pegawai_jawatan = sanitize($_POST['pegawai_jawatan'] ?? '');
        $pegawai_gred    = sanitize($_POST['pegawai_gred'] ?? '');

        // ── Validasi ─────────────────────────────────────────────────────────
        $jenis_aset_sah      = ['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
        $perolehan_sah       = ['Kerajaan Negeri', 'Kerajaan Persekutuan', 'Sewa Guna', 'Guna Sama', 'Lain'];

        if (empty($no_pendaftaran)) {
            $error = "No. Pendaftaran Aset diperlukan";
        } elseif (!in_array($jenis_aset, $jenis_aset_sah, true)) {
            $error = "Jenis Aset tidak sah";
        } elseif (!in_array($jenis_perolehan, $perolehan_sah, true)) {
            $error = "Jenis Perolehan tidak sah";
        } elseif ($agensi_id === 0) {
            $error = "Anda tidak ditugaskan ke agensi manapun";
        } else {
            // Semak no. pendaftaran unik
            $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS count FROM aset WHERE no_pendaftaran = ?");
            mysqli_stmt_bind_param($check_stmt, "s", $no_pendaftaran);
            mysqli_stmt_execute($check_stmt);
            $check_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
            mysqli_stmt_close($check_stmt);

            if ($check_row['count'] > 0) {
                $error = "No. Pendaftaran Aset ini sudah terdaftar. Sila gunakan no. yang berlainan.";
            } else {
                // ── INSERT – kolum berpadanan dengan struktur jadual sebenar ──
                $insert_query = "INSERT INTO aset (
                    no_pendaftaran, jenis_aset, jenis_perolehan,
                    tahun_beli, jenama, model,
                    processor, ram, cakera_keras, sistem_operasi,
                    no_siri_pencetak, jenis_pencetak,
                    pegawai_nama, pegawai_jawatan, pegawai_gred,
                    agensi_id, pengguna_id_daftar, catatan,
                    wilayah_id, status_workflow_id, status_aset_id
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, 1, 1
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
                        "sssisssssssssssiisi",
                        $no_pendaftaran, $jenis_aset, $jenis_perolehan,
                        $tahun_beli, $jenama, $model,
                        $processor, $ram, $cakera_keras, $sistem_operasi,
                        $no_siri_pencetak, $jenis_pencetak,
                        $pegawai_nama, $pegawai_jawatan, $pegawai_gred,
                        $agensi_id, $pengguna_id, $catatan,
                        $wilayah_id
                    );

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $aset_baru_id = mysqli_insert_id($conn);

                        // Log aktiviti
                        logActivity($conn, 'Daftar Aset', "Aset baru didaftarkan: $no_pendaftaran ($jenis_aset) oleh Agen IT");

                        // FIX: Notifikasi selaras dengan struktur table `notifikasi`
                        // Column: penerima_id, aset_id, jenis(ENUM), mesej, url, dibaca, tarikh
                        // Hantar kepada semua PPTM/PTM (peranan_id 4 & 5)
                        $penerima_stmt = mysqli_prepare(
                            $conn,
                            "SELECT pengguna_id FROM pengguna WHERE peranan_id IN (4,5) AND status_pengguna_id = 1"
                        );
                        mysqli_stmt_execute($penerima_stmt);
                        $penerima_result = mysqli_stmt_get_result($penerima_stmt);

                        $notif_stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO notifikasi (penerima_id, aset_id, jenis, mesej, url, dibaca, tarikh)
                             VALUES (?, ?, 'aset_baru', ?, ?, 0, NOW())"
                        );
                        $notif_mesej = "Permohonan pengesahan aset: $no_pendaftaran (Agen IT)";
                        $notif_url   = "lihat.php?id=" . $aset_baru_id;

                        while ($p = mysqli_fetch_assoc($penerima_result)) {
                            mysqli_stmt_bind_param($notif_stmt, "iiss", $p['pengguna_id'], $aset_baru_id, $notif_mesej, $notif_url);
                            mysqli_stmt_execute($notif_stmt);
                        }
                        mysqli_stmt_close($notif_stmt);
                        mysqli_stmt_close($penerima_stmt);

                        $success = "Aset berjaya didaftarkan! No. pendaftaran: <strong>$no_pendaftaran</strong><br>Status: Draf (belum dihantar untuk pengesahan)";
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

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Aset Baru - Agen IT JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../agen-it.css">
</head>
<body class="agen-it page-aset-tambah">
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
                        <h1 class="page-title">Daftar Aset Baharu</h1>
                        <p class="page-subtitle">Lengkapkan maklumat aset sebelum dihantar untuk pengesahan.</p>
                    </div>
                    <div class="page-chip"><i class="bi bi-plus-circle"></i> Agen IT</div>
                </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- Maklumat Agensi (baca sahaja) -->
                        <div class="alert hero-note mb-4">
                            <i class="bi bi-info-circle"></i> <strong>Agensi Anda:</strong>
                            <?php echo escapeOutput($user_agensi['nama_agensi'] ?? 'Tidak Ditugaskan'); ?>
                            <?php if (!empty($user_agensi['nama_daerah'])): ?>
                                (<?php echo escapeOutput($user_agensi['nama_daerah']); ?>)
                            <?php endif; ?>
                        </div>

                        <!-- ── Maklumat Asas ───────────────────────────────── -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="no_pendaftaran" class="form-label">No. Pendaftaran Aset <span class="text-danger">*</span></label>
                                <input type="text" id="no_pendaftaran" name="no_pendaftaran" class="form-control"
                                       placeholder="cth: ASET-2026-001" required
                                       value="<?php echo escapeOutput($_POST['no_pendaftaran'] ?? ''); ?>">
                                <small class="text-muted">No. mesti unik dan tidak boleh diulang</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jenis_aset" class="form-label">Jenis Aset <span class="text-danger">*</span></label>
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

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jenis_perolehan" class="form-label">Jenis Perolehan <span class="text-danger">*</span></label>
                                <select id="jenis_perolehan" name="jenis_perolehan" class="form-select" required>
                                    <option value="">-- Pilih Jenis Perolehan --</option>
                                    <option value="Kerajaan Negeri" <?php if(($_POST['jenis_perolehan']??'')==='Kerajaan Negeri') echo 'selected'; ?>>Kerajaan Negeri</option>
                                    <option value="Kerajaan Persekutuan" <?php if(($_POST['jenis_perolehan']??'')==='Kerajaan Persekutuan') echo 'selected'; ?>>Kerajaan Persekutuan</option>
                                    <option value="Sewa Guna" <?php if(($_POST['jenis_perolehan']??'')==='Sewa Guna') echo 'selected'; ?>>Sewa Guna</option>
                                    <option value="Guna Sama" <?php if(($_POST['jenis_perolehan']??'')==='Guna Sama') echo 'selected'; ?>>Guna Sama</option>
                                    <option value="Lain" <?php if(($_POST['jenis_perolehan']??'')==='Lain') echo 'selected'; ?>>Lain-lain</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tahun_beli" class="form-label">Tahun Beli</label>
                                <input type="number" id="tahun_beli" name="tahun_beli" class="form-control"
                                       placeholder="2026" min="2000" max="<?php echo date('Y'); ?>"
                                       value="<?php echo escapeOutput($_POST['tahun_beli'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jenama" class="form-label">Jenama</label>
                                <input type="text" id="jenama" name="jenama" class="form-control"
                                       placeholder="cth: Dell, HP, Lenovo"
                                       value="<?php echo escapeOutput($_POST['jenama'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" id="model" name="model" class="form-control"
                                       placeholder="cth: Inspiron 15"
                                       value="<?php echo escapeOutput($_POST['model'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan</label>
                            <textarea id="catatan" name="catatan" class="form-control" rows="3"
                                      placeholder="Maklumat tambahan tentang aset..."><?php echo escapeOutput($_POST['catatan'] ?? ''); ?></textarea>
                        </div>

                        <!-- ── Spesifikasi PC / Notebook (FIX: field selaras DB) ── -->
                        <div id="pc_specs" class="card mb-3 border-info asset-spec-section">
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
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cakera_keras" class="form-label">Cakera Keras / Storan</label>
                                        <input type="text" id="cakera_keras" name="cakera_keras" class="form-control"
                                               placeholder="cth: 512GB SSD"
                                               value="<?php echo escapeOutput($_POST['cakera_keras'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="sistem_operasi" class="form-label">Sistem Operasi</label>
                                        <input type="text" id="sistem_operasi" name="sistem_operasi" class="form-control"
                                               placeholder="cth: Windows 11 Pro"
                                               value="<?php echo escapeOutput($_POST['sistem_operasi'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Spesifikasi Pencetak (FIX: name=no_siri_pencetak) ── -->
                        <div id="printer_specs" class="card mb-3 border-danger asset-spec-section">
                            <div class="card-header bg-danger text-white">
                                <i class="bi bi-printer"></i> Spesifikasi Pencetak
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="printer_type" class="form-label">Jenis Pencetak</label>
                                    <select id="printer_type" name="printer_type" class="form-select">
                                        <option value="Tiada"  <?php if(($_POST['printer_type']??'Tiada')==='Tiada') echo 'selected'; ?>>-- Pilih Jenis --</option>
                                        <option value="Laser"   <?php if(($_POST['printer_type']??'')==='Laser') echo 'selected'; ?>>Laser</option>
                                        <option value="Inkjet"  <?php if(($_POST['printer_type']??'')==='Inkjet') echo 'selected'; ?>>Inkjet</option>
                                        <option value="Matrik"  <?php if(($_POST['printer_type']??'')==='Matrik') echo 'selected'; ?>>Matrik</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="no_siri_pencetak" class="form-label">No. Siri Pencetak</label>
                                    <input type="text" id="no_siri_pencetak" name="no_siri_pencetak" class="form-control"
                                           placeholder="cth: SN12345678"
                                           value="<?php echo escapeOutput($_POST['no_siri_pencetak'] ?? ''); ?>">
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
                            Aset yang didaftarkan akan mempunyai status <em>"Draf"</em>. Sila hantar semula untuk pengesahan PPTM/PTM.
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
        </div><!-- /.col-md-9 -->
    </div><!-- /.row -->
</div><!-- /.container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const jenis = document.getElementById('jenis_aset');
const pcSpecs      = document.getElementById('pc_specs');
const printerSpecs = document.getElementById('printer_specs');

function toggleSpecs() {
    const val = jenis.value;
    pcSpecs.style.display      = (val === 'PC' || val === 'NB') ? 'block' : 'none';
    printerSpecs.style.display = (val === 'Pencetak')           ? 'block' : 'none';
}
jenis.addEventListener('change', toggleSpecs);
toggleSpecs();
</script>
</body>
</html>