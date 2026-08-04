<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul Ketua Bahagian - Kelulusan Aset PID
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

requireRoleWhitelist(['Ketua Bahagian']);

$nama_penuh = $_SESSION['nama_penuh'] ?? 'Ketua Bahagian';
$peranan = $_SESSION['peranan'] ?? 'Ketua Bahagian';
$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$kaedah = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$aset_id_input = $kaedah === 'POST'
    ? ($_POST['aset_id'] ?? null)
    : ($_GET['id'] ?? null);

$aset_id = filter_var($aset_id_input, FILTER_VALIDATE_INT);

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: dashboard.php');
    exit;
}

if ($kaedah === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrf_token)) {
        $_SESSION['flash_error'] = 'Token keselamatan tidak sah atau telah tamat. Sila cuba semula.';
        header('Location: dashboard.php');
        exit;
    }
}

/*
 * Dapatkan dan sahkan aset.
 *
 * Ketua Bahagian hanya boleh meluluskan:
 * - aset PID (wilayah_id = 1)
 * - aset yang masih berstatus Menunggu KB (status_workflow_id = 6)
 */
$aset = null;

try {
    $sql_aset = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.model,
            a.jenama,
            a.tahun_beli,
            a.wilayah_id,
            a.agensi_id,
            a.pengguna_id_daftar,
            a.status_workflow_id,
            a.tarikh_input,
            w.nama_wilayah,
            ag.nama_agensi,
            p.nama_penuh AS pendaftar,
            sw.status AS status_workflow
        FROM aset a
        LEFT JOIN wilayah w
            ON w.wilayah_id = a.wilayah_id
        LEFT JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN pengguna p
            ON p.pengguna_id = a.pengguna_id_daftar
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        WHERE a.aset_id = ?
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);

    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param($stmt_aset, 'i', $aset_id);

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $result_aset = mysqli_stmt_get_result($stmt_aset);
    $aset = mysqli_fetch_assoc($result_aset);
    mysqli_stmt_close($stmt_aset);
} catch (Throwable $e) {
    error_log('Kelulusan Ketua Bahagian - semakan aset: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat disahkan. Sila cuba semula.';
    header('Location: dashboard.php');
    exit;
}

if (!$aset) {
    $_SESSION['flash_error'] = 'Rekod aset tidak ditemui.';
    header('Location: dashboard.php');
    exit;
}

if ((int) $aset['wilayah_id'] !== 1) {
    $_SESSION['flash_error'] = 'Ketua Bahagian hanya boleh meluluskan aset PID. Aset Wilayah adalah untuk paparan sahaja.';
    header('Location: dashboard.php');
    exit;
}

if ((int) $aset['status_workflow_id'] !== 6) {
    $_SESSION['flash_error'] = 'Aset ini tidak lagi berada dalam status Menunggu Ketua Bahagian.';
    header('Location: dashboard.php');
    exit;
}

/*
 * Proses kelulusan.
 *
 * Transaksi meliputi:
 * 1. Kemaskini status workflow 6 -> 8
 * 2. Rekod log workflow
 * 3. Notifikasi semua Pengarah aktif
 * 4. Notifikasi pendaftar asal
 */
if ($kaedah === 'POST') {
    mysqli_begin_transaction($conn);

    try {
        $status_asal = 6;
        $status_baru = 8;

        $sql_update = "
            UPDATE aset
            SET status_workflow_id = ?
            WHERE aset_id = ?
              AND status_workflow_id = ?
              AND wilayah_id = 1
        ";

        $stmt_update = mysqli_prepare($conn, $sql_update);

        if (!$stmt_update) {
            throw new RuntimeException('Gagal menyediakan kemaskini status aset.');
        }

        mysqli_stmt_bind_param(
            $stmt_update,
            'iii',
            $status_baru,
            $aset_id,
            $status_asal
        );

        if (!mysqli_stmt_execute($stmt_update)) {
            throw new RuntimeException('Gagal mengemaskini status aset.');
        }

        if (mysqli_stmt_affected_rows($stmt_update) !== 1) {
            throw new RuntimeException(
                'Status aset telah berubah atau aset tidak lagi layak diluluskan.'
            );
        }

        mysqli_stmt_close($stmt_update);

        $tindakan = 'Lulus Bahagian';
        $catatan_log = 'Diluluskan oleh Ketua Bahagian — aset sah dan lengkap';

        $sql_log = "
            INSERT INTO log_workflow (
                aset_id,
                tindakan,
                status_workflow_dari,
                status_workflow_ke,
                catatan,
                oleh_pengguna_id
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $stmt_log = mysqli_prepare($conn, $sql_log);

        if (!$stmt_log) {
            throw new RuntimeException('Gagal menyediakan rekod log workflow.');
        }

        mysqli_stmt_bind_param(
            $stmt_log,
            'isiisi',
            $aset_id,
            $tindakan,
            $status_asal,
            $status_baru,
            $catatan_log,
            $pengguna_id
        );

        if (!mysqli_stmt_execute($stmt_log)) {
            throw new RuntimeException('Gagal merekodkan log kelulusan.');
        }

        mysqli_stmt_close($stmt_log);

        /*
         * Dapatkan semua pengguna aktif dengan peranan Pengarah.
         */
        $nama_peranan_pengarah = 'Pengarah';
        $status_pengguna_aktif = 1;

        $sql_pengarah = "
            SELECT p.pengguna_id
            FROM pengguna p
            INNER JOIN peranan r
                ON r.peranan_id = p.peranan_id
            WHERE r.nama_peranan = ?
              AND p.status_pengguna_id = ?
        ";

        $stmt_pengarah = mysqli_prepare($conn, $sql_pengarah);

        if (!$stmt_pengarah) {
            throw new RuntimeException('Gagal menyediakan senarai penerima Pengarah.');
        }

        mysqli_stmt_bind_param(
            $stmt_pengarah,
            'si',
            $nama_peranan_pengarah,
            $status_pengguna_aktif
        );

        if (!mysqli_stmt_execute($stmt_pengarah)) {
            throw new RuntimeException('Gagal mendapatkan senarai Pengarah.');
        }

        $result_pengarah = mysqli_stmt_get_result($stmt_pengarah);
        $senarai_pengarah = [];

        while ($row_pengarah = mysqli_fetch_assoc($result_pengarah)) {
            $senarai_pengarah[] = (int) $row_pengarah['pengguna_id'];
        }

        mysqli_stmt_close($stmt_pengarah);

        $jenis_notifikasi_pengarah = 'aset_baru';
        $mesej_pengarah = sprintf(
            'Aset %s dari %s telah diluluskan sepenuhnya oleh Ketua Bahagian.',
            $aset['no_pendaftaran'],
            $aset['nama_wilayah'] ?: 'Ibu Pejabat JTDIS'
        );
        $url_pengarah = '/jdtis_asset/pages/pengarah/lihat.php?id=' . $aset_id;

        $sql_notifikasi = "
            INSERT INTO notifikasi (
                penerima_id,
                aset_id,
                jenis,
                mesej,
                url
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt_notifikasi = mysqli_prepare($conn, $sql_notifikasi);

        if (!$stmt_notifikasi) {
            throw new RuntimeException('Gagal menyediakan rekod notifikasi.');
        }

        foreach ($senarai_pengarah as $penerima_pengarah_id) {
            mysqli_stmt_bind_param(
                $stmt_notifikasi,
                'iisss',
                $penerima_pengarah_id,
                $aset_id,
                $jenis_notifikasi_pengarah,
                $mesej_pengarah,
                $url_pengarah
            );

            if (!mysqli_stmt_execute($stmt_notifikasi)) {
                throw new RuntimeException('Gagal menghantar notifikasi kepada Pengarah.');
            }
        }

        /*
         * Notifikasi kepada pendaftar asal.
         */
        $pendaftar_id = (int) $aset['pengguna_id_daftar'];

        if ($pendaftar_id > 0) {
            $jenis_notifikasi_pendaftar = 'diluluskan';
            $mesej_pendaftar = sprintf(
                'Tahniah! Aset %s anda telah LULUS sepenuhnya.',
                $aset['no_pendaftaran']
            );
            $url_pendaftar = '/jdtis_asset/pages/aset/lihat.php?id=' . $aset_id;

            mysqli_stmt_bind_param(
                $stmt_notifikasi,
                'iisss',
                $pendaftar_id,
                $aset_id,
                $jenis_notifikasi_pendaftar,
                $mesej_pendaftar,
                $url_pendaftar
            );

            if (!mysqli_stmt_execute($stmt_notifikasi)) {
                throw new RuntimeException('Gagal menghantar notifikasi kepada pendaftar.');
            }
        }

        mysqli_stmt_close($stmt_notifikasi);

        mysqli_commit($conn);

        logActivity(
            $conn,
            'Lulus Aset',
            'Ketua Bahagian meluluskan aset PID ' . $aset['no_pendaftaran']
        );

        $_SESSION['flash_success'] = sprintf(
            'Aset %s berjaya diluluskan sepenuhnya.',
            $aset['no_pendaftaran']
        );

        header('Location: dashboard.php');
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('Kelulusan Ketua Bahagian - transaksi: ' . $e->getMessage());

        $_SESSION['flash_error'] = 'Kelulusan aset gagal diproses. Tiada perubahan disimpan. Sila cuba semula.';
        header('Location: dashboard.php');
        exit;
    }
}

$csrf_token = generateCSRFToken();

$papar_nilai = static function ($nilai): string {
    if ($nilai === null || trim((string) $nilai) === '') {
        return '-';
    }

    return (string) $nilai;
};

$format_tarikh = static function ($nilai): string {
    if (empty($nilai)) {
        return '-';
    }

    $timestamp = strtotime((string) $nilai);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lulus Aset <?php echo escapeOutput($aset['no_pendaftaran']); ?> - JTDIS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="ketua-bahagian.css">
</head>
<body class="ketua-bahagian page-lulus">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark">
                    <i class="bi bi-shield-check fs-5"></i>
                </span>

                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Ketua Bahagian</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-fill me-2"></i>
                    Dashboard
                </a>

                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-box-seam me-2"></i>
                    Senarai Aset
                </a>

                <hr class="border-light opacity-25">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i>
                    Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                <div>
                    <h1 class="page-title h3 mb-1">Pengesahan Kelulusan Aset</h1>
                    <p class="text-muted mb-0">
                        Semak maklumat aset PID sebelum memberikan kelulusan akhir.
                    </p>
                </div>

                <div class="text-lg-end">
                    <div class="fw-semibold">
                        <?php echo escapeOutput($nama_penuh); ?>
                    </div>
                    <small class="text-muted">
                        <?php echo escapeOutput($peranan); ?>
                    </small>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 col-xxl-10">
                    <section class="card content-card">
                        <div class="confirmation-header">
                            <div class="d-flex align-items-start gap-3">
                                <div class="confirmation-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>

                                <div>
                                    <span class="badge bg-success mb-2">
                                        Kelulusan Akhir
                                    </span>

                                    <h2 class="h4 mb-2">
                                        Luluskan aset ini?
                                    </h2>

                                    <p class="mb-0">
                                        Pastikan maklumat aset telah disemak dan tepat
                                        sebelum meneruskan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-3 p-lg-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                                <div>
                                    <div class="asset-number">
                                        <?php echo escapeOutput($aset['no_pendaftaran']); ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <span class="badge bg-primary">
                                            <?php echo escapeOutput($aset['jenis_aset']); ?>
                                        </span>

                                        <span class="badge source-badge">
                                            <i class="bi bi-building me-1"></i>
                                            PID
                                        </span>

                                        <span class="badge status-badge">
                                            <i class="bi bi-hourglass-split me-1"></i>
                                            <?php echo escapeOutput(
                                                $papar_nilai($aset['status_workflow'])
                                            ); ?>
                                        </span>
                                    </div>
                                </div>

                                <a
                                    href="lihat.php?id=<?php echo escapeOutput((string) $aset_id); ?>"
                                    class="btn btn-outline-primary"
                                >
                                    <i class="bi bi-eye me-1"></i>
                                    Lihat Maklumat Penuh
                                </a>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Jenis Aset</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput($aset['jenis_aset']); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Jenama / Model</div>
                                        <p class="summary-value">
                                            <?php
                                            $jenama_model = trim(
                                                $papar_nilai($aset['jenama'])
                                                . ' '
                                                . $papar_nilai($aset['model'])
                                            );
                                            echo escapeOutput($jenama_model);
                                            ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Tahun Beli</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput(
                                                $papar_nilai($aset['tahun_beli'])
                                            ); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Wilayah</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput(
                                                $papar_nilai($aset['nama_wilayah'])
                                            ); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Agensi</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput(
                                                $papar_nilai($aset['nama_agensi'])
                                            ); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Pendaftar</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput(
                                                $papar_nilai($aset['pendaftar'])
                                            ); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Tarikh Didaftar</div>
                                        <p class="summary-value">
                                            <?php echo escapeOutput(
                                                $format_tarikh($aset['tarikh_input'])
                                            ); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <div class="summary-item">
                                        <div class="summary-label">Sumber</div>
                                        <p class="summary-value">PID / Ibu Pejabat</p>
                                    </div>
                                </div>
                            </div>

                            <div class="approval-flow mb-4">
                                <span class="flow-state flow-current">
                                    Menunggu Ketua Bahagian
                                </span>

                                <i class="bi bi-arrow-right text-muted"></i>

                                <span class="flow-state flow-final">
                                    Lulus Sepenuhnya
                                </span>
                            </div>

                            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-info-circle-fill mt-1"></i>
                                <div>
                                    Selepas diluluskan, aset ini akan ditanda sebagai
                                    <strong>LULUS</strong> dan Pengarah akan dimaklumkan.
                                </div>
                            </div>

                            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                                <div>
                                    <strong>Perhatian:</strong>
                                    Ini ialah kelulusan terakhir dalam rantaian workflow.
                                    Pastikan semua maklumat aset adalah tepat dan lengkap.
                                </div>
                            </div>

                            <div class="action-panel mt-4">
                                <form
                                    method="POST"
                                    action="lulus.php"
                                    onsubmit="return confirm(
                                        'Adakah anda pasti mahu meluluskan aset ini? Tindakan ini akan menukar status aset kepada LULUS.'
                                    );"
                                >
                                    <input
                                        type="hidden"
                                        name="aset_id"
                                        value="<?php echo escapeOutput((string) $aset_id); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo escapeOutput($csrf_token); ?>"
                                    >

                                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                                        <a
                                            href="lihat.php?id=<?php echo escapeOutput((string) $aset_id); ?>"
                                            class="btn btn-secondary btn-lg"
                                        >
                                            <i class="bi bi-x-lg me-1"></i>
                                            Batal
                                        </a>

                                        <button
                                            type="submit"
                                            class="btn btn-success btn-lg"
                                        >
                                            <i class="bi bi-check-circle-fill me-1"></i>
                                            Lulus
                                        </button>
                                    </div>
                                </form>

                                <div class="security-note text-end mt-3">
                                    <i class="bi bi-shield-lock me-1"></i>
                                    Tindakan ini dilindungi oleh token CSRF dan direkodkan
                                    dalam log sistem.
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
