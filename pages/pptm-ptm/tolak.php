<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page (centralized guard: login + session expiry)
requireRoleWhitelist(['PPTM', 'PTM']);

$pengguna_id = $_SESSION['pengguna_id'];
$wilayah_id = (int)($_SESSION['wilayah_id'] ?? 0);
$error = '';
$success = '';

// Get asset ID from URL
$aset_id = !empty($_GET['id']) ? intval($_GET['id']) : 0;

if ($aset_id === 0) {
    header("Location: pengesahan.php");
    exit;
}

// ── FIX: Fetch guna status_workflow_id (bukan status_aset_id) ──
// status_workflow_id = 2 bermaksud "Menunggu PPTM". status_aset_id
// (Aktif/Rosak/dll) ialah status fizikal aset, tidak berkaitan
// dengan peringkat kelulusan. Turut skop kepada wilayah PPTM/PTM.
$aset_query = "SELECT * FROM aset WHERE aset_id = ? AND status_workflow_id = 2 AND wilayah_id = ?";
$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, "ii", $aset_id, $wilayah_id);
mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);

if (!$aset) {
    $_SESSION['flash_error'] = "Aset tidak boleh ditolak pada masa ini (mungkin sudah diproses oleh pengguna lain).";
    header("Location: pengesahan.php");
    exit;
}

// Process rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
        $catatan_penolakan = sanitize($_POST['catatan_penolakan'] ?? '');

        if (empty($catatan_penolakan)) {
            $error = "Sila masukkan nota penolakan";
        } else {
            $old_status = intval($aset['status_workflow_id']); // = 2 (Menunggu PPTM)

            mysqli_begin_transaction($conn);
            try {
                // ── FIX: Update status_workflow_id (3 = Ditolak PPTM), BUKAN status_aset_id ──
                // ── FIX: catatan asal (Juruteknik/Agen IT) TIDAK disentuh/overwrite.
                //         Nota penolakan disimpan dalam log_workflow.catatan sahaja —
                //         inilah sumber data yang dibaca oleh pengesahan.php
                //         (subquery nota_tolak_terkini).
                $update_stmt = mysqli_prepare($conn, "UPDATE aset SET status_workflow_id = 3 WHERE aset_id = ?");
                mysqli_stmt_bind_param($update_stmt, "i", $aset_id);
                mysqli_stmt_execute($update_stmt);

                if (mysqli_stmt_affected_rows($update_stmt) <= 0) {
                    throw new Exception("Aset tidak berjaya dikemaskini.");
                }

                // ── Log audit trail + nota penolakan ke log_workflow ──
                $log_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO log_workflow
                        (aset_id, tindakan, status_workflow_dari, status_workflow_ke, catatan, oleh_pengguna_id)
                     VALUES
                        (?, 'Tolak PPTM', ?, 3, ?, ?)"
                );
                mysqli_stmt_bind_param($log_stmt, "iisi", $aset_id, $old_status, $catatan_penolakan, $pengguna_id);
                mysqli_stmt_execute($log_stmt);

                // ── FIX: Notifikasi kepada pendaftar asal supaya dia tahu untuk
                //         perbetulkan & hantar semula (UI asal dah janji ini
                //         berlaku, tapi kod asal tak buat langsung) ──
                $no_pendaftaran = $aset['no_pendaftaran'];
                $penerima_id = intval($aset['pengguna_id_daftar']);
                $mesej = "Aset {$no_pendaftaran} ditolak oleh " . $_SESSION['peranan'] . ". Sila semak nota penolakan dan hantar semula.";
                $url = "/jdtis_asset/pages/aset/lihat.php?id=" . intval($aset_id);

                $notif_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO notifikasi (penerima_id, aset_id, jenis, mesej, url)
                     VALUES (?, ?, 'ditolak', ?, ?)"
                );
                mysqli_stmt_bind_param($notif_stmt, "iiss", $penerima_id, $aset_id, $mesej, $url);
                mysqli_stmt_execute($notif_stmt);
                mysqli_stmt_close($notif_stmt);

                mysqli_commit($conn);

                logActivity($conn, 'Penolakan Aset', "Aset " . $no_pendaftaran . " ditolak oleh " . $_SESSION['peranan']);

                header("Location: pengesahan.php?success=rejected");
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Ralat semasa menolak aset. Sila cuba lagi.";
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
    <title>Tolak Aset - PPTM/PTM JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pptm-ptm.css">

</head>
<body class="pptm-ptm page-tolak">
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="brand-wrap">
                <div class="brand-mark"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h4 class="brand-title">JTDIS</h4>
                    <p class="brand-subtitle">Pengesahan Aset ICT</p>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-check"></i>
                <?php echo escapeOutput($_SESSION['peranan']); ?>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link " href="dashboard.php">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>

                <a class="nav-link active" href="pengesahan.php">
                    <i class="bi bi-clipboard-check"></i> Pengesahan Aset
                    
                </a>

                <hr class="nav-separator sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside><main class="col-md-9 col-xl-10 main-content">
                <div class="page-hero"><div><a href="lihat.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a><h1 class="page-title">Tolak Aset</h1><p class="page-subtitle">Nyatakan sebab penolakan yang jelas untuk tindakan pendaftar.</p></div><div class="page-chip"><i class="bi bi-x-circle"></i> Penolakan PPTM</div></div>

                <div class="card decision-card">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-4"><i class="bi bi-x-circle-fill text-danger me-2"></i>Pengesahan Penolakan</h3>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-warning mb-4">
                            <i class="bi bi-info-circle"></i> Anda akan menolak aset berikut. Aset ini akan ditetapkan ke status <strong>"Ditolak PPTM"</strong> dan pendaftar akan menerima notifikasi.
                        </div>

                        <div class="row asset-summary mb-4">
                            <div class="col-md-6">
                                <p><strong>No. Pendaftaran:</strong> <?php echo escapeOutput($aset['no_pendaftaran']); ?></p>
                                <p><strong>Jenis Aset:</strong> <?php echo escapeOutput($aset['jenis_aset']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Jenama:</strong> <?php echo escapeOutput($aset['jenama']); ?></p>
                                <p><strong>Model:</strong> <?php echo escapeOutput($aset['model']); ?></p>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-4">
                                <label for="catatan_penolakan" class="form-label">Nota Penolakan <span class="text-danger">*</span></label>
                                <textarea id="catatan_penolakan" name="catatan_penolakan" class="form-control" rows="5" placeholder="Jelaskan sebab-sebab penolakan aset ini (cth: maklumat pegawai tidak lengkap, spesifikasi tidak jelas, dll)" required></textarea>
                                <small class="text-muted">Nota ini akan dihantar kepada pendaftar aset untuk mereka perbaiki dan kemudian serahkan semula.</small>
                            </div>

                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <strong>Perhatian:</strong> Sila pastikan nota penolakan jelas dan membantu pendaftar memperbaiki maklumat aset. Tindakan ini tidak boleh dibatalkan.
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Adakah anda pasti ingin menolak aset ini?');">
                                    <i class="bi bi-x-circle"></i> Tolak Aset
                                </button>
                                <a href="lihat.php?id=<?php echo $aset['aset_id']; ?>" class="btn btn-secondary btn-lg">
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
</body>
</html>