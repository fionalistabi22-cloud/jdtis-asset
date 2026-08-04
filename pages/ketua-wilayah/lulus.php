<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Wilayah - Lulus Aset
 *
 * Workflow final:
 * Menunggu KW (4) -> Lulus (8)
 * Aset wilayah TIDAK melalui Ketua Bahagian.
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (isSessionExpired()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['Ketua Wilayah']);

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$wilayah_id = (int) ($_SESSION['wilayah_id'] ?? 0);
$kaedah = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($kaedah, ['GET', 'POST'], true)) {
    header('Location: dashboard.php');
    exit;
}

$aset_id = $kaedah === 'POST'
    ? filter_input(INPUT_POST, 'aset_id', FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (
    $pengguna_id <= 0
    || $wilayah_id <= 1
    || $aset_id === false
    || $aset_id === null
    || $aset_id < 1
) {
    $_SESSION['flash_error'] = 'Maklumat permintaan tidak sah.';
    header('Location: dashboard.php');
    exit;
}

$aset = null;

try {
    /*
     * Skop keselamatan:
     * - aset mesti dalam wilayah Ketua Wilayah semasa
     * - aset mesti berstatus 4 (Menunggu KW)
     */
    $sql_aset = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.jenama,
            a.model,
            a.wilayah_id,
            a.status_workflow_id,
            a.pengguna_id_daftar,
            ag.nama_agensi,
            w.nama_wilayah,
            p.nama_penuh AS pendaftar,
            r.nama_peranan AS peranan_pendaftar
        FROM aset a
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN wilayah w
            ON w.wilayah_id = a.wilayah_id
        LEFT JOIN pengguna p
            ON p.pengguna_id = a.pengguna_id_daftar
        LEFT JOIN peranan r
            ON r.peranan_id = p.peranan_id
        WHERE a.aset_id = ?
          AND a.wilayah_id = ?
          AND a.status_workflow_id = 4
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);

    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param(
        $stmt_aset,
        'ii',
        $aset_id,
        $wilayah_id
    );

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $aset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_aset));
    mysqli_stmt_close($stmt_aset);
} catch (Throwable $e) {
    error_log('Lulus Aset Ketua Wilayah - semakan: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat disahkan.';
    header('Location: dashboard.php');
    exit;
}

if (!$aset) {
    $_SESSION['flash_error'] =
        'Aset tidak boleh diluluskan. Aset mungkin sudah diproses atau bukan dalam wilayah anda.';
    header('Location: dashboard.php');
    exit;
}

/*
 * Paparan pengesahan.
 */
if ($kaedah === 'GET') {
    $csrf_token = generateCSRFToken();
    ?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luluskan Aset - Ketua Wilayah JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="ketua-wilayah.css">
</head>
<body class="ketua-wilayah page-lulus">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-geo-alt-fill"></i></span>
                <div>
                    <div class="fw-bold fs-4">JTDIS</div>
                    <small class="text-white-50">Ketua Wilayah</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>

                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-box-seam"></i> Senarai Aset
                </a>

                <hr class="sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content">
            <div class="mb-4">
                <a
                    href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                    class="btn btn-outline-secondary mb-3"
                >
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>

                <h1 class="page-title h2">Luluskan Aset</h1>
                <p class="text-muted mb-0">
                    Kelulusan Ketua Wilayah akan menyelesaikan workflow aset wilayah.
                </p>
            </div>

            <section class="card content-card">
                <div class="card-body p-3 p-lg-4">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Selepas diluluskan, status aset terus menjadi
                        <strong>Lulus (status 8)</strong>.
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Aset wilayah tidak dihantar kepada Ketua Bahagian. Pengarah dan
                        pendaftar asal akan menerima notifikasi.
                    </div>

                    <div class="asset-summary my-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <span class="detail-label">No. Pendaftaran</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <span class="detail-label">Jenis Aset</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <span class="detail-label">Jenama / Model</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) ($aset['jenama'] ?: '-')); ?>
                                    /
                                    <?php echo escapeOutput((string) ($aset['model'] ?: '-')); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <span class="detail-label">Agensi</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) ($aset['nama_agensi'] ?: '-')); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <span class="detail-label">Wilayah</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) ($aset['nama_wilayah'] ?: '-')); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <span class="detail-label">Pendaftar</span>
                                <div class="detail-value">
                                    <?php echo escapeOutput((string) ($aset['pendaftar'] ?: '-')); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Pastikan maklumat aset telah disemak dengan teliti. Tindakan ini
                        tidak boleh dibatalkan melalui halaman ini.
                    </div>

                    <form
                        method="POST"
                        action="lulus.php"
                        onsubmit="return confirm('Luluskan aset ini terus ke status 8?');"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escapeOutput($csrf_token); ?>"
                        >

                        <input
                            type="hidden"
                            name="aset_id"
                            value="<?php echo (int) $aset_id; ?>"
                        >

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-1"></i>
                                Luluskan Aset
                            </button>

                            <a
                                href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                                class="btn btn-outline-secondary btn-lg"
                            >
                                <i class="bi bi-x-lg me-1"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
    exit;
}

/*
 * POST processing.
 */
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Token CSRF tidak sah.';
    header('Location: dashboard.php');
    exit;
}

mysqli_begin_transaction($conn);

try {
    /*
     * 1. Workflow final bagi aset wilayah: 4 -> 8.
     * Semakan status dan wilayah diulang dalam UPDATE untuk elak race condition.
     */
    $sql_update = "
        UPDATE aset
        SET
            status_workflow_id = 8,
            tarikh_kemaskini = NOW()
        WHERE aset_id = ?
          AND wilayah_id = ?
          AND status_workflow_id = 4
    ";

    $stmt_update = mysqli_prepare($conn, $sql_update);

    if (!$stmt_update) {
        throw new RuntimeException('Gagal menyediakan query kelulusan.');
    }

    mysqli_stmt_bind_param(
        $stmt_update,
        'ii',
        $aset_id,
        $wilayah_id
    );

    if (!mysqli_stmt_execute($stmt_update)) {
        throw new RuntimeException('Gagal mengemas kini status aset.');
    }

    if (mysqli_stmt_affected_rows($stmt_update) !== 1) {
        throw new RuntimeException(
            'Status aset telah berubah atau aset tidak lagi layak diluluskan.'
        );
    }

    mysqli_stmt_close($stmt_update);

    /*
     * 2. Rekod audit workflow 4 -> 8.
     */
    $sql_log = "
        INSERT INTO log_workflow (
            aset_id,
            tindakan,
            status_workflow_dari,
            status_workflow_ke,
            catatan,
            oleh_pengguna_id
        ) VALUES (
            ?,
            'Lulus Wilayah',
            4,
            8,
            'Diluluskan oleh Ketua Wilayah — workflow aset wilayah selesai',
            ?
        )
    ";

    $stmt_log = mysqli_prepare($conn, $sql_log);

    if (!$stmt_log) {
        throw new RuntimeException('Gagal menyediakan query log workflow.');
    }

    mysqli_stmt_bind_param(
        $stmt_log,
        'ii',
        $aset_id,
        $pengguna_id
    );

    if (!mysqli_stmt_execute($stmt_log)) {
        throw new RuntimeException('Gagal merekod log workflow.');
    }

    mysqli_stmt_close($stmt_log);

    /*
     * 3. Notifikasi kepada semua Pengarah aktif.
     * Tiada notifikasi kepada Ketua Bahagian untuk aset wilayah.
     */
    $sql_pengarah = "
        SELECT p.pengguna_id
        FROM pengguna p
        INNER JOIN peranan r
            ON r.peranan_id = p.peranan_id
        WHERE r.nama_peranan = 'Pengarah'
          AND p.status_pengguna_id = 1
    ";

    $stmt_pengarah = mysqli_prepare($conn, $sql_pengarah);

    if (!$stmt_pengarah) {
        throw new RuntimeException('Gagal menyediakan query Pengarah.');
    }

    if (!mysqli_stmt_execute($stmt_pengarah)) {
        throw new RuntimeException('Gagal mendapatkan senarai Pengarah.');
    }

    $senarai_pengarah = mysqli_fetch_all(
        mysqli_stmt_get_result($stmt_pengarah),
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt_pengarah);

    $no_pendaftaran = (string) $aset['no_pendaftaran'];
    $nama_wilayah = (string) ($aset['nama_wilayah'] ?: 'Wilayah');
    $url_pengarah =
        '/jtdis_asset/pages/pengarah/lihat.php?id=' .
        (int) $aset_id;

    $sql_notifikasi = "
        INSERT INTO notifikasi (
            penerima_id,
            aset_id,
            jenis,
            mesej,
            url
        ) VALUES (
            ?,
            ?,
            'diluluskan',
            ?,
            ?
        )
    ";

    $stmt_notifikasi = mysqli_prepare($conn, $sql_notifikasi);

    if (!$stmt_notifikasi) {
        throw new RuntimeException('Gagal menyediakan query notifikasi Pengarah.');
    }

    foreach ($senarai_pengarah as $pengarah) {
        $penerima_id = (int) $pengarah['pengguna_id'];
        $mesej_pengarah =
            'Aset ' . $no_pendaftaran .
            ' dari ' . $nama_wilayah .
            ' telah diluluskan sepenuhnya oleh Ketua Wilayah.';

        mysqli_stmt_bind_param(
            $stmt_notifikasi,
            'iiss',
            $penerima_id,
            $aset_id,
            $mesej_pengarah,
            $url_pengarah
        );

        if (!mysqli_stmt_execute($stmt_notifikasi)) {
            throw new RuntimeException(
                'Gagal menghantar notifikasi kepada Pengarah.'
            );
        }
    }

    mysqli_stmt_close($stmt_notifikasi);

    /*
     * 4. Notifikasi kepada pendaftar asal.
     */
    $pendaftar_id = (int) ($aset['pengguna_id_daftar'] ?? 0);

    if ($pendaftar_id > 0) {
        $peranan_pendaftar = (string) ($aset['peranan_pendaftar'] ?? '');

        if ($peranan_pendaftar === 'Agen IT') {
            $url_pendaftar =
                '/jtdis_asset/pages/agen-it/aset/lihat.php?id=' .
                (int) $aset_id;
        } else {
            $url_pendaftar =
                '/jtdis_asset/pages/aset/lihat.php?id=' .
                (int) $aset_id;
        }

        $mesej_pendaftar =
            'Aset ' . $no_pendaftaran .
            ' anda telah diluluskan sepenuhnya oleh Ketua Wilayah.';

        $stmt_pendaftar = mysqli_prepare(
            $conn,
            "INSERT INTO notifikasi (
                penerima_id,
                aset_id,
                jenis,
                mesej,
                url
            ) VALUES (
                ?,
                ?,
                'diluluskan',
                ?,
                ?
            )"
        );

        if (!$stmt_pendaftar) {
            throw new RuntimeException(
                'Gagal menyediakan notifikasi pendaftar.'
            );
        }

        mysqli_stmt_bind_param(
            $stmt_pendaftar,
            'iiss',
            $pendaftar_id,
            $aset_id,
            $mesej_pendaftar,
            $url_pendaftar
        );

        if (!mysqli_stmt_execute($stmt_pendaftar)) {
            throw new RuntimeException(
                'Gagal menghantar notifikasi kepada pendaftar.'
            );
        }

        mysqli_stmt_close($stmt_pendaftar);
    }

    mysqli_commit($conn);

    logActivity(
        $conn,
        'Lulus Aset KW',
        'Aset ' . $no_pendaftaran .
        ' diluluskan Ketua Wilayah terus ke status 8.'
    );

    $_SESSION['flash_success'] =
        'Aset ' . $no_pendaftaran .
        ' berjaya diluluskan sepenuhnya.';

    header('Location: dashboard.php');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('Lulus Aset Ketua Wilayah: ' . $e->getMessage());

    $_SESSION['flash_error'] =
        'Aset tidak berjaya diluluskan. Sila cuba semula.';

    header('Location: dashboard.php');
    exit;
}
