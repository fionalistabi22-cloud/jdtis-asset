<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

requireRoleWhitelist(['Ketua Wilayah']);

if (isSessionExpired()) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Confirmation view
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$wilayah_id = (int)($_SESSION['wilayah_id'] ?? 0);
$pengguna_id = (int)($_SESSION['pengguna_id'] ?? 0);

$aset_id = 0;
$error = '';
$catatan_penolakan = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $aset_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
} else {
    $aset_id = !empty($_POST['aset_id']) ? (int)$_POST['aset_id'] : 0;
}


if ($aset_id <= 0) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: dashboard.php');
    exit;
}

// Validation: asset exists in wilayah and status_workflow_id = 4 only
$aset_query = "SELECT
    a.*,
    sw.status AS status_workflow,
    ag.nama_agensi,
    d.nama_daerah,
    p.nama_penuh AS pendaftar
FROM aset a
LEFT JOIN status_workflow sw ON a.status_workflow_id = sw.status_workflow_id
LEFT JOIN agensi ag ON a.agensi_id = ag.agensi_id
LEFT JOIN daerah d ON ag.daerah_id = d.daerah_id
LEFT JOIN pengguna p ON a.pengguna_id_daftar = p.pengguna_id
WHERE a.aset_id = ? AND a.wilayah_id = ? AND a.status_workflow_id = 4";

$aset_stmt = mysqli_prepare($conn, $aset_query);
mysqli_stmt_bind_param($aset_stmt, 'ii', $aset_id, $wilayah_id);
mysqli_stmt_execute($aset_stmt);
$aset = mysqli_fetch_assoc(mysqli_stmt_get_result($aset_stmt));

if (!$aset) {
    $_SESSION['flash_error'] = 'Aset tidak boleh ditolak pada masa ini (mungkin sudah diproses oleh pengguna lain).';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak sah.';
    } else {
        $catatan_penolakan = trim((string)($_POST['catatan_penolakan'] ?? ''));
        if ($catatan_penolakan === '') {
            $error = 'Sila masukkan sebab penolakan.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $update_stmt = mysqli_prepare($conn, "UPDATE aset SET status_workflow_id = 5 WHERE aset_id = ?");
                mysqli_stmt_bind_param($update_stmt, 'i', $aset_id);
                mysqli_stmt_execute($update_stmt);

                if (mysqli_stmt_affected_rows($update_stmt) <= 0) {
                    throw new Exception('Aset tidak berjaya dikemaskini.');
                }

                $old_status = 4;

                $log_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO log_workflow
                        (aset_id, tindakan, status_workflow_dari, status_workflow_ke, catatan, oleh_pengguna_id)
                     VALUES
                        (?, 'Tolak Wilayah', ?, 5, ?, ?)"
                );
                mysqli_stmt_bind_param($log_stmt, 'iisi', $aset_id, $old_status, $catatan_penolakan, $pengguna_id);
                mysqli_stmt_execute($log_stmt);

                // Notify all PPTM/PTM users in same wilayah
                $pptm_stmt = mysqli_prepare(
                    $conn,
                    "SELECT p.pengguna_id
                     FROM pengguna p
                     JOIN peranan r ON p.peranan_id = r.peranan_id
                     WHERE r.nama_peranan IN ('PPTM','PTM')
                       AND p.wilayah_id = ?
                       AND p.status_pengguna_id = 1"
                );
                mysqli_stmt_bind_param($pptm_stmt, 'i', $wilayah_id);
                mysqli_stmt_execute($pptm_stmt);
                $pptm_list = mysqli_fetch_all(mysqli_stmt_get_result($pptm_stmt), MYSQLI_ASSOC);

                $no_pendaftaran = $aset['no_pendaftaran'];
                $mesej_base = "Aset {$no_pendaftaran} ditolak Ketua Wilayah. Sebab: {$catatan_penolakan}";
                $url = "/jdtis_asset/pages/pptm-ptm/lihat.php?id=" . (int)$aset_id;

                $notif_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO notifikasi (penerima_id, aset_id, jenis, mesej, url)
                     VALUES (?, ?, 'ditolak', ?, ?)"
                );

                foreach ($pptm_list as $u) {
                    $penerima_id = (int)$u['pengguna_id'];
                    mysqli_stmt_bind_param($notif_stmt, 'iiss', $penerima_id, $aset_id, $mesej_base, $url);
                    mysqli_stmt_execute($notif_stmt);
                }

                // Notify original pendaftar
                $pendaftar_id = (int)($aset['pengguna_id_daftar'] ?? 0);
                if ($pendaftar_id > 0) {
                    $notif2_stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO notifikasi (penerima_id, aset_id, jenis, mesej, url)
                         VALUES (?, ?, 'ditolak', ?, NULL)"
                    );
                    $mesej2 = "Aset {$no_pendaftaran} ditolak Ketua Wilayah. Sebab: {$catatan_penolakan}";
                    mysqli_stmt_bind_param($notif2_stmt, 'iis', $pendaftar_id, $aset_id, $mesej2);
                    mysqli_stmt_execute($notif2_stmt);
                }

                mysqli_commit($conn);

                logActivity($conn, 'Tolak Aset KW', "Aset {$no_pendaftaran} ditolak KW. Sebab: {$catatan_penolakan}");

                $_SESSION['flash_success'] = 'Aset berjaya ditolak. Notifikasi telah dihantar.';
                header('Location: dashboard.php');
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Ralat semasa menolak aset. Sila cuba lagi.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$catatan_escape = escapeOutput($catatan_penolakan);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tolak Aset - Ketua Wilayah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-wilayah.css">
</head>
<body class="ketua-wilayah page-tolak">
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 sidebar p-4">
            <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
            <p class="text-warning mb-3"><small><?php echo escapeOutput($_SESSION['peranan'] ?? 'Ketua Wilayah'); ?></small></p>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                <a class="nav-link active" href="senarai_aset.php"><i class="bi bi-hdd-stack"></i> Senarai Aset</a>
                <hr class="sidebar-divider">
                <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
            </nav>
        </div>

        <main class="col-md-9 main-content p-4">
            <a href="lihat.php?id=<?php echo (int)$aset_id; ?>" class="btn btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>

            <div class="card">
                <div class="card-body p-4">
                    <h3 class="mb-2"><i class="bi bi-x-circle text-danger"></i> Tolak Aset</h3>
                    <div class="alert alert-warning mb-4">
                        <i class="bi bi-info-circle"></i> Selepas ditolak, PPTM akan dimaklumkan untuk menyemak semula.
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4 rejection-summary">
                        <div class="col-md-6">
                            <div class="mb-2"><strong>No. Pendaftaran:</strong> <?php echo escapeOutput($aset['no_pendaftaran']); ?></div>
                            <div class="mb-2"><strong>Jenis:</strong> <?php echo escapeOutput($aset['jenis_aset']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Model:</strong> <?php echo escapeOutput($aset['model'] ?? '-'); ?></div>
                            <div class="mb-2"><strong>Agensi:</strong> <?php echo escapeOutput($aset['nama_agensi'] ?? '-'); ?></div>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="aset_id" value="<?php echo (int)$aset_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">

                        <div class="mb-3">
                            <label for="catatan_penolakan" class="form-label">Sebab Penolakan <span class="text-danger">*</span></label>
                            <textarea id="catatan_penolakan" name="catatan_penolakan" class="form-control" rows="5" required
                                      placeholder="Jelaskan sebab penolakan dengan jelas supaya PPTM boleh membuat pembetulan yang sesuai"><?php echo $catatan_escape; ?></textarea>
                            <div class="form-text">Sila tulis sebab yang jelas dan membantu pembetulan.</div>
                        </div>

                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('Adakah anda pasti ingin menolak aset ini?');">
                                <i class="bi bi-x-circle"></i> Tolak Aset
                            </button>
                            <a href="lihat.php?id=<?php echo (int)$aset_id; ?>" class="btn btn-secondary btn-lg">
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

