<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Edit Aset
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['PID']);

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$agensi_id = (int) ($_SESSION['agensi_id'] ?? 0);

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

$aset_id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_input(INPUT_POST, 'aset_id', FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$current_year = (int) date('Y');
$errors = [];
$error_umum = '';
$aset_asal = null;
$nota_penolakan = '';
$nama_agensi = 'Agensi Tidak Ditetapkan';

function pilihan($db_val, $option_val): string
{
    return (string) $db_val === (string) $option_val ? 'selected' : '';
}

function checked_radio($db_val, $opt): string
{
    return (string) $db_val === (string) $opt ? 'checked' : '';
}

function kelasTidakSah(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function nilaiBukanNegatif($nilai): int
{
    $integer = filter_var(
        $nilai,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    return $integer === false ? 0 : (int) $integer;
}

function bindDinamik(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $args = [$stmt, $types];

    foreach ($params as &$value) {
        $args[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $args)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
}

/*
 * Dapatkan rekod asal dengan skop keselamatan lengkap.
 * Edit hanya dibenarkan bagi status Draf (1) atau Ditolak KB (7).
 */
try {
    $sql_aset = "
        SELECT
            a.*,
            ag.nama_agensi,
            sw.status AS status_workflow,
            (
                SELECT lw.catatan
                FROM log_workflow lw
                WHERE lw.aset_id = a.aset_id
                  AND lw.tindakan = 'Tolak Bahagian'
                ORDER BY lw.tarikh DESC, lw.log_id DESC
                LIMIT 1
            ) AS nota_tolak_terkini
        FROM aset a
        INNER JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        WHERE a.aset_id = ?
          AND a.agensi_id = ?
          AND a.wilayah_id = 1
          AND a.status_workflow_id IN (1, 7)
        LIMIT 1
    ";

    $stmt_aset = mysqli_prepare($conn, $sql_aset);

    if (!$stmt_aset) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param($stmt_aset, 'ii', $aset_id, $agensi_id);

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $aset_asal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_aset));
    mysqli_stmt_close($stmt_aset);

    if (!$aset_asal) {
        $_SESSION['flash_error'] =
            'Aset tidak ditemui, bukan milik agensi anda, atau tidak boleh diedit pada status semasa.';
        header('Location: senarai_aset.php');
        exit;
    }

    $nama_agensi = (string) $aset_asal['nama_agensi'];
    $nota_penolakan = trim((string) ($aset_asal['nota_tolak_terkini'] ?? ''));
} catch (Throwable $e) {
    error_log('Edit Aset PID - Fetch: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}

$form = [
    'no_pendaftaran' => (string) ($aset_asal['no_pendaftaran'] ?? ''),
    'jenis_aset' => (string) ($aset_asal['jenis_aset'] ?? ''),
    'jenis_perolehan' => (string) ($aset_asal['jenis_perolehan'] ?? ''),
    'tahun_beli' => (string) ($aset_asal['tahun_beli'] ?? ''),
    'jenama' => (string) ($aset_asal['jenama'] ?? ''),
    'model' => (string) ($aset_asal['model'] ?? ''),
    'processor' => (string) ($aset_asal['processor'] ?? ''),
    'ram' => (string) ($aset_asal['ram'] ?? ''),
    'cakera_keras' => (string) ($aset_asal['cakera_keras'] ?? ''),
    'sistem_operasi' => (string) ($aset_asal['sistem_operasi'] ?? ''),
    'spesifikasi_pencetak' => (string) ($aset_asal['spesifikasi_pencetak'] ?? ''),
    'jenis_pencetak' => (string) ($aset_asal['jenis_pencetak'] ?? 'Tiada'),
    'bil_pencetak_laser' => (string) ($aset_asal['bil_pencetak_laser'] ?? '0'),
    'bil_pencetak_inkjet' => (string) ($aset_asal['bil_pencetak_inkjet'] ?? '0'),
    'bil_pencetak_matrik' => (string) ($aset_asal['bil_pencetak_matrik'] ?? '0'),
    'no_siri_pencetak' => (string) ($aset_asal['no_siri_pencetak'] ?? ''),
    'pegawai_nama' => (string) ($aset_asal['pegawai_nama'] ?? ''),
    'pegawai_jawatan' => (string) ($aset_asal['pegawai_jawatan'] ?? ''),
    'pegawai_gred' => (string) ($aset_asal['pegawai_gred'] ?? ''),
    'catatan' => (string) ($aset_asal['catatan'] ?? ''),
];

$status_asal = (int) $aset_asal['status_workflow_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim((string) ($_POST[$key] ?? $default));
    }

    $action = trim((string) ($_POST['action'] ?? 'simpan'));

    if (!in_array($action, ['simpan', 'simpan_hantar'], true)) {
        $action = 'simpan';
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_umum = 'Token keselamatan tidak sah. Sila muat semula halaman dan cuba lagi.';
    }

    $jenis_aset_dibenarkan = ['NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
    $jenis_perolehan_dibenarkan = [
        'Kerajaan Negeri',
        'Kerajaan Persekutuan',
        'Sewa',
        'Pinjaman',
        'Lain',
    ];
    $ram_dibenarkan = ['', '4GB', '8GB', '16GB', '32GB', 'Lain-lain'];
    $os_dibenarkan = ['', 'Windows 10', 'Windows 11', 'Lain-lain'];
    $jenis_pencetak_dibenarkan = ['Tiada', 'Laser', 'Inkjet', 'Matrik'];

    if ($form['no_pendaftaran'] === '') {
        $errors['no_pendaftaran'] = 'Sila masukkan nombor pendaftaran aset.';
    } elseif (strlen($form['no_pendaftaran']) > 150) {
        $errors['no_pendaftaran'] = 'Nombor pendaftaran terlalu panjang.';
    }

    if (!in_array($form['jenis_aset'], $jenis_aset_dibenarkan, true)) {
        $errors['jenis_aset'] = 'Sila pilih jenis aset.';
    }

    if (!in_array($form['jenis_perolehan'], $jenis_perolehan_dibenarkan, true)) {
        $errors['jenis_perolehan'] = 'Sila pilih jenis perolehan.';
    }

    $tahun_beli = filter_var(
        $form['tahun_beli'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 2000, 'max_range' => $current_year]]
    );

    if ($tahun_beli === false) {
        $errors['tahun_beli'] =
            'Tahun beli mesti antara 2000 hingga ' . $current_year . '.';
    }

    if ($form['jenama'] === '') {
        $errors['jenama'] = 'Sila masukkan jenama aset.';
    }

    if ($form['model'] === '') {
        $errors['model'] = 'Sila masukkan model aset.';
    }

    if (!in_array($form['ram'], $ram_dibenarkan, true)) {
        $form['ram'] = '';
    }

    if (!in_array($form['sistem_operasi'], $os_dibenarkan, true)) {
        $form['sistem_operasi'] = '';
    }

    if (!in_array($form['jenis_pencetak'], $jenis_pencetak_dibenarkan, true)) {
        $form['jenis_pencetak'] = 'Tiada';
    }

    if (!in_array($form['jenis_aset'], ['NB', 'PC'], true)) {
        $form['processor'] = '';
        $form['ram'] = '';
        $form['cakera_keras'] = '';
        $form['sistem_operasi'] = '';
    }

    if ($form['jenis_aset'] !== 'Pencetak') {
        $form['spesifikasi_pencetak'] = '';
        $form['jenis_pencetak'] = 'Tiada';
        $form['bil_pencetak_laser'] = '0';
        $form['bil_pencetak_inkjet'] = '0';
        $form['bil_pencetak_matrik'] = '0';
        $form['no_siri_pencetak'] = '';
    }

    $bil_laser = nilaiBukanNegatif($form['bil_pencetak_laser']);
    $bil_inkjet = nilaiBukanNegatif($form['bil_pencetak_inkjet']);
    $bil_matrik = nilaiBukanNegatif($form['bil_pencetak_matrik']);

    /*
     * Butang simpan dan hantar semula hanya relevan untuk status 7.
     */
    if ($action === 'simpan_hantar' && $status_asal !== 7) {
        $error_umum = 'Aset ini tidak berada dalam status Ditolak KB.';
    }

    /*
     * Semak keunikan nombor pendaftaran.
     */
    if ($error_umum === '' && $errors === []) {
        $sql_unik = "
            SELECT aset_id
            FROM aset
            WHERE no_pendaftaran = ?
              AND aset_id <> ?
            LIMIT 1
        ";

        $stmt_unik = mysqli_prepare($conn, $sql_unik);

        if (!$stmt_unik) {
            $error_umum = 'Ralat semasa menyemak nombor pendaftaran.';
        } else {
            mysqli_stmt_bind_param(
                $stmt_unik,
                'si',
                $form['no_pendaftaran'],
                $aset_id
            );

            if (!mysqli_stmt_execute($stmt_unik)) {
                $error_umum = 'Ralat semasa menyemak nombor pendaftaran.';
            } else {
                $rekod_dua = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_unik));

                if ($rekod_dua) {
                    $errors['no_pendaftaran'] =
                        'Nombor pendaftaran ini sudah digunakan oleh aset lain.';
                }
            }

            mysqli_stmt_close($stmt_unik);
        }
    }

    if ($error_umum === '' && $errors === []) {
        $sql_update_asas = "
            UPDATE aset SET
                no_pendaftaran = ?,
                jenis_aset = ?,
                jenis_perolehan = ?,
                tahun_beli = ?,
                jenama = ?,
                model = ?,
                processor = ?,
                ram = ?,
                cakera_keras = ?,
                sistem_operasi = ?,
                spesifikasi_pencetak = ?,
                jenis_pencetak = ?,
                bil_pencetak_laser = ?,
                bil_pencetak_inkjet = ?,
                bil_pencetak_matrik = ?,
                no_siri_pencetak = ?,
                pegawai_nama = ?,
                pegawai_jawatan = ?,
                pegawai_gred = ?,
                catatan = ?,
                tarikh_kemaskini = NOW()
        ";

        $update_types = 'sssissssssssiiisssssii';
        $update_params = [
            $form['no_pendaftaran'],
            $form['jenis_aset'],
            $form['jenis_perolehan'],
            (int) $tahun_beli,
            $form['jenama'],
            $form['model'],
            $form['processor'],
            $form['ram'],
            $form['cakera_keras'],
            $form['sistem_operasi'],
            $form['spesifikasi_pencetak'],
            $form['jenis_pencetak'],
            $bil_laser,
            $bil_inkjet,
            $bil_matrik,
            $form['no_siri_pencetak'],
            $form['pegawai_nama'],
            $form['pegawai_jawatan'],
            $form['pegawai_gred'],
            $form['catatan'],
        ];

        if ($action === 'simpan_hantar') {
            mysqli_begin_transaction($conn);

            try {
                $sql_update = $sql_update_asas . ",
                    status_workflow_id = 6
                    WHERE aset_id = ?
                      AND agensi_id = ?
                      AND wilayah_id = 1
                      AND status_workflow_id = 7
                ";

                $params = $update_params;
                $params[] = $aset_id;
                $params[] = $agensi_id;

                $stmt_update = mysqli_prepare($conn, $sql_update);

                if (!$stmt_update) {
                    throw new RuntimeException('Gagal menyediakan query kemas kini.');
                }

                bindDinamik($stmt_update, $update_types, $params);

                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new RuntimeException(
                        'Gagal mengemas kini aset: ' . mysqli_stmt_error($stmt_update)
                    );
                }

                if (mysqli_stmt_affected_rows($stmt_update) <= 0) {
                    throw new RuntimeException(
                        'Aset tidak berjaya dikemas kini. Status mungkin sudah berubah.'
                    );
                }

                mysqli_stmt_close($stmt_update);

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
                        'Semak',
                        ?,
                        6,
                        'Dihantar semula ke Ketua Bahagian selepas pembetulan',
                        ?
                    )
                ";

                $stmt_log = mysqli_prepare($conn, $sql_log);

                if (!$stmt_log) {
                    throw new RuntimeException('Gagal menyediakan query log workflow.');
                }

                mysqli_stmt_bind_param(
                    $stmt_log,
                    'iii',
                    $aset_id,
                    $status_asal,
                    $pengguna_id
                );

                if (!mysqli_stmt_execute($stmt_log)) {
                    throw new RuntimeException('Gagal merekod log workflow.');
                }

                mysqli_stmt_close($stmt_log);

                $sql_kb = "
                    SELECT p.pengguna_id
                    FROM pengguna p
                    INNER JOIN peranan r
                        ON r.peranan_id = p.peranan_id
                    WHERE r.nama_peranan = 'Ketua Bahagian'
                      AND p.status_pengguna_id = 1
                ";

                $stmt_kb = mysqli_prepare($conn, $sql_kb);

                if (!$stmt_kb) {
                    throw new RuntimeException('Gagal menyediakan query Ketua Bahagian.');
                }

                if (!mysqli_stmt_execute($stmt_kb)) {
                    throw new RuntimeException('Gagal mendapatkan Ketua Bahagian.');
                }

                $senarai_kb = mysqli_fetch_all(
                    mysqli_stmt_get_result($stmt_kb),
                    MYSQLI_ASSOC
                );

                mysqli_stmt_close($stmt_kb);

                $mesej =
                    'Aset ' . $form['no_pendaftaran'] .
                    ' dari ' . $nama_agensi .
                    ' telah dihantar semula untuk semakan KB.';

                $url =
                    '/jtdis_asset/pages/ketua-bahagian/lihat.php?id=' .
                    $aset_id;

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
                        'aset_baru',
                        ?,
                        ?
                    )
                ";

                $stmt_notifikasi = mysqli_prepare($conn, $sql_notifikasi);

                if (!$stmt_notifikasi) {
                    throw new RuntimeException('Gagal menyediakan query notifikasi.');
                }

                foreach ($senarai_kb as $kb) {
                    $penerima_id = (int) $kb['pengguna_id'];

                    mysqli_stmt_bind_param(
                        $stmt_notifikasi,
                        'iiss',
                        $penerima_id,
                        $aset_id,
                        $mesej,
                        $url
                    );

                    if (!mysqli_stmt_execute($stmt_notifikasi)) {
                        throw new RuntimeException(
                            'Gagal menghantar notifikasi kepada Ketua Bahagian.'
                        );
                    }
                }

                mysqli_stmt_close($stmt_notifikasi);
                mysqli_commit($conn);

                logActivity(
                    $conn,
                    'Edit dan Hantar Semula Aset PID',
                    'Aset ' . $form['no_pendaftaran'] .
                    ' dikemas kini dan dihantar semula ke Ketua Bahagian.'
                );

                $_SESSION['flash_success'] =
                    'Aset berjaya dikemas kini dan dihantar semula ke Ketua Bahagian.';

                header('Location: senarai_aset.php');
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                error_log('Edit dan Hantar Semula PID: ' . $e->getMessage());

                if (
                    stripos($e->getMessage(), 'Duplicate entry') !== false
                    || stripos($e->getMessage(), 'duplicate') !== false
                ) {
                    $errors['no_pendaftaran'] =
                        'Nombor pendaftaran ini sudah digunakan.';
                } else {
                    $error_umum =
                        'Aset tidak berjaya dikemas kini dan dihantar semula.';
                }
            }
        } else {
            try {
                $sql_update = $sql_update_asas . "
                    WHERE aset_id = ?
                      AND agensi_id = ?
                      AND wilayah_id = 1
                      AND status_workflow_id IN (1, 7)
                ";

                $params = $update_params;
                $params[] = $aset_id;
                $params[] = $agensi_id;

                $stmt_update = mysqli_prepare($conn, $sql_update);

                if (!$stmt_update) {
                    throw new RuntimeException('Gagal menyediakan query kemas kini.');
                }

                bindDinamik($stmt_update, $update_types, $params);

                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new RuntimeException(
                        'Gagal mengemas kini aset: ' . mysqli_stmt_error($stmt_update)
                    );
                }

                mysqli_stmt_close($stmt_update);

                logActivity(
                    $conn,
                    'Edit Aset PID',
                    'Maklumat aset ' . $form['no_pendaftaran'] . ' dikemas kini.'
                );

                $_SESSION['flash_success'] =
                    'Maklumat aset berjaya dikemas kini.';

                header('Location: lihat.php?id=' . $aset_id);
                exit;
            } catch (Throwable $e) {
                error_log('Edit Aset PID: ' . $e->getMessage());

                if (
                    stripos($e->getMessage(), 'Duplicate entry') !== false
                    || stripos($e->getMessage(), 'duplicate') !== false
                ) {
                    $errors['no_pendaftaran'] =
                        'Nombor pendaftaran ini sudah digunakan.';
                } else {
                    $error_umum = 'Maklumat aset tidak berjaya dikemas kini.';
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
    <title>Edit Aset PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-edit">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark"><i class="bi bi-building-check"></i></span>
                <div>
                    <div class="fw-bold fs-5">JTDIS</div>
                    <small class="text-white-50">Pasukan Inovasi Digital</small>
                </div>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link active" href="senarai_aset.php">
                    <i class="bi bi-boxes me-2"></i> Senarai Aset
                </a>
                <a class="nav-link" href="tambah.php">
                    <i class="bi bi-plus-circle me-2"></i> Daftar Aset Baru
                </a>
                <hr class="sidebar-divider">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="mb-4">
                <a href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                   class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>

                <h1 class="h2 fw-bold mb-1">Edit Aset PID</h1>
                <p class="text-muted mb-0">
                    <?php echo escapeOutput((string) $aset_asal['no_pendaftaran']); ?>
                    |
                    <?php echo escapeOutput($nama_agensi); ?>
                </p>
            </div>

            <?php if ($status_asal === 7): ?>
                <div class="alert alert-danger">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                        <div>
                            <strong>Aset ini ditolak oleh Ketua Bahagian.</strong><br>
                            Sebab:
                            <?php echo nl2br(
                                escapeOutput(
                                    $nota_penolakan !== ''
                                        ? $nota_penolakan
                                        : 'Tiada sebab penolakan diberikan.'
                                )
                            ); ?>
                            <div class="mt-2">
                                Sila betulkan maklumat di bawah dan hantar semula.
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_umum !== ''): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($error_umum); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit.php" novalidate>
                <input type="hidden" name="csrf_token"
                       value="<?php echo escapeOutput($csrf_token); ?>">
                <input type="hidden" name="aset_id"
                       value="<?php echo (int) $aset_id; ?>">

                <section class="card form-card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-box-seam me-2 text-primary"></i>
                            A. Maklumat Aset
                        </h2>
                    </div>

                    <div class="card-body p-3 p-lg-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="no_pendaftaran" class="form-label required">
                                    No. Pendaftaran
                                </label>

                                <input type="text"
                                       id="no_pendaftaran"
                                       name="no_pendaftaran"
                                       class="form-control<?php echo kelasTidakSah($errors, 'no_pendaftaran'); ?>"
                                       value="<?php echo escapeOutput($form['no_pendaftaran']); ?>"
                                       maxlength="150"
                                       required>

                                <?php if (isset($errors['no_pendaftaran'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['no_pendaftaran']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label class="form-label required">Jenis Aset</label>

                                <div class="asset-type-grid">
                                    <?php
                                    $jenis_icons = [
                                        'NB' => 'bi-laptop',
                                        'PC' => 'bi-pc-display',
                                        'Pencetak' => 'bi-printer',
                                        'Monitor' => 'bi-display',
                                        'Lain' => 'bi-box',
                                    ];
                                    ?>

                                    <?php foreach ($jenis_icons as $jenis => $icon): ?>
                                        <?php $radio_id = 'jenis_' . strtolower($jenis); ?>

                                        <div class="asset-type-option">
                                            <input type="radio"
                                                   id="<?php echo escapeOutput($radio_id); ?>"
                                                   name="jenis_aset"
                                                   value="<?php echo escapeOutput($jenis); ?>"
                                                   <?php echo checked_radio($form['jenis_aset'], $jenis); ?>>

                                            <label for="<?php echo escapeOutput($radio_id); ?>"
                                                   class="asset-type-label">
                                                <i class="bi <?php echo escapeOutput($icon); ?>"></i>
                                                <strong><?php echo escapeOutput($jenis); ?></strong>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (isset($errors['jenis_aset'])): ?>
                                    <div class="text-danger small mt-2">
                                        <?php echo escapeOutput($errors['jenis_aset']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="jenis_perolehan" class="form-label required">
                                    Jenis Perolehan
                                </label>

                                <select id="jenis_perolehan"
                                        name="jenis_perolehan"
                                        class="form-select<?php echo kelasTidakSah($errors, 'jenis_perolehan'); ?>"
                                        required>
                                    <option value="">Pilih jenis perolehan</option>

                                    <?php foreach ([
                                        'Kerajaan Negeri',
                                        'Kerajaan Persekutuan',
                                        'Sewa',
                                        'Pinjaman',
                                        'Lain',
                                    ] as $item): ?>
                                        <option value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihan($form['jenis_perolehan'], $item); ?>>
                                            <?php echo escapeOutput($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if (isset($errors['jenis_perolehan'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['jenis_perolehan']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="tahun_beli" class="form-label required">Tahun Beli</label>

                                <input type="number"
                                       id="tahun_beli"
                                       name="tahun_beli"
                                       class="form-control<?php echo kelasTidakSah($errors, 'tahun_beli'); ?>"
                                       value="<?php echo escapeOutput($form['tahun_beli']); ?>"
                                       min="2000"
                                       max="<?php echo $current_year; ?>"
                                       required>

                                <?php if (isset($errors['tahun_beli'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['tahun_beli']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="jenama" class="form-label required">Jenama</label>
                                <input type="text"
                                       id="jenama"
                                       name="jenama"
                                       class="form-control<?php echo kelasTidakSah($errors, 'jenama'); ?>"
                                       value="<?php echo escapeOutput($form['jenama']); ?>"
                                       required>

                                <?php if (isset($errors['jenama'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['jenama']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="model" class="form-label required">Model</label>
                                <input type="text"
                                       id="model"
                                       name="model"
                                       class="form-control<?php echo kelasTidakSah($errors, 'model'); ?>"
                                       value="<?php echo escapeOutput($form['model']); ?>"
                                       required>

                                <?php if (isset($errors['model'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['model']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="seksyenKomputer" class="card form-card mb-4 section-hidden">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-cpu me-2 text-primary"></i>
                            B. Spesifikasi PC/Notebook
                        </h2>
                    </div>

                    <div class="card-body p-3 p-lg-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="processor" class="form-label">Processor</label>
                                <input type="text"
                                       id="processor"
                                       name="processor"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['processor']); ?>"
                                       placeholder="cth. Intel Core i5-1135G7">
                            </div>

                            <div class="col-md-6">
                                <label for="ram" class="form-label">RAM</label>
                                <select id="ram" name="ram" class="form-select">
                                    <option value="">Pilih RAM</option>

                                    <?php foreach (['4GB', '8GB', '16GB', '32GB', 'Lain-lain'] as $item): ?>
                                        <option value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihan($form['ram'], $item); ?>>
                                            <?php echo escapeOutput($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="cakera_keras" class="form-label">Cakera Keras</label>
                                <input type="text"
                                       id="cakera_keras"
                                       name="cakera_keras"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['cakera_keras']); ?>"
                                       placeholder="cth. 256GB SSD">
                            </div>

                            <div class="col-md-6">
                                <label for="sistem_operasi" class="form-label">Sistem Operasi</label>
                                <select id="sistem_operasi" name="sistem_operasi" class="form-select">
                                    <option value="">Pilih sistem operasi</option>

                                    <?php foreach (['Windows 10', 'Windows 11', 'Lain-lain'] as $item): ?>
                                        <option value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihan($form['sistem_operasi'], $item); ?>>
                                            <?php echo escapeOutput($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="seksyenPencetak" class="card form-card mb-4 section-hidden">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-printer me-2 text-primary"></i>
                            C. Maklumat Pencetak
                        </h2>
                    </div>

                    <div class="card-body p-3 p-lg-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Jenis Pencetak</label>

                                <div class="printer-type-grid">
                                    <?php foreach (['Laser', 'Inkjet', 'Matrik'] as $item): ?>
                                        <?php $printer_id = 'printer_' . strtolower($item); ?>

                                        <div class="printer-type-option">
                                            <input type="radio"
                                                   id="<?php echo escapeOutput($printer_id); ?>"
                                                   name="jenis_pencetak"
                                                   value="<?php echo escapeOutput($item); ?>"
                                                   <?php echo checked_radio($form['jenis_pencetak'], $item); ?>>

                                            <label class="printer-type-label"
                                                   for="<?php echo escapeOutput($printer_id); ?>">
                                                <i class="bi bi-printer"></i>
                                                <?php echo escapeOutput($item); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="spesifikasi_pencetak" class="form-label">
                                    Spesifikasi Pencetak
                                </label>

                                <input type="text"
                                       id="spesifikasi_pencetak"
                                       name="spesifikasi_pencetak"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['spesifikasi_pencetak']); ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_laser" class="form-label">
                                    Bil. Pencetak Laser
                                </label>
                                <input type="number"
                                       id="bil_pencetak_laser"
                                       name="bil_pencetak_laser"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['bil_pencetak_laser']); ?>"
                                       min="0">
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_inkjet" class="form-label">
                                    Bil. Pencetak Inkjet
                                </label>
                                <input type="number"
                                       id="bil_pencetak_inkjet"
                                       name="bil_pencetak_inkjet"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['bil_pencetak_inkjet']); ?>"
                                       min="0">
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_matrik" class="form-label">
                                    Bil. Pencetak Matrik
                                </label>
                                <input type="number"
                                       id="bil_pencetak_matrik"
                                       name="bil_pencetak_matrik"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['bil_pencetak_matrik']); ?>"
                                       min="0">
                            </div>

                            <div class="col-12">
                                <label for="no_siri_pencetak" class="form-label">
                                    No. Siri Pencetak
                                </label>
                                <input type="text"
                                       id="no_siri_pencetak"
                                       name="no_siri_pencetak"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['no_siri_pencetak']); ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card form-card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-person-badge me-2 text-primary"></i>
                            D. Maklumat Pegawai
                        </h2>
                    </div>

                    <div class="card-body p-3 p-lg-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="pegawai_nama" class="form-label">Nama Pegawai</label>
                                <input type="text"
                                       id="pegawai_nama"
                                       name="pegawai_nama"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['pegawai_nama']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="pegawai_jawatan" class="form-label">Jawatan</label>
                                <input type="text"
                                       id="pegawai_jawatan"
                                       name="pegawai_jawatan"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['pegawai_jawatan']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="pegawai_gred" class="form-label">Gred</label>
                                <input type="text"
                                       id="pegawai_gred"
                                       name="pegawai_gred"
                                       class="form-control"
                                       value="<?php echo escapeOutput($form['pegawai_gred']); ?>"
                                       placeholder="cth. N1, NA12, F41">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card form-card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="bi bi-chat-left-text me-2 text-primary"></i>
                            E. Catatan
                        </h2>
                    </div>

                    <div class="card-body p-3 p-lg-4">
                        <label for="catatan" class="form-label">Catatan</label>
                        <textarea id="catatan"
                                  name="catatan"
                                  class="form-control"
                                  rows="4"><?php echo escapeOutput($form['catatan']); ?></textarea>
                    </div>
                </section>

                <div class="sticky-submit">
                    <div class="sticky-inner">
                        <div class="small text-muted">
                            <span class="text-danger">*</span> Medan wajib diisi
                        </div>

                        <div class="sticky-actions d-flex gap-2">
                            <a href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                               class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-x-lg me-1"></i> Batal
                            </a>

                            <button type="submit"
                                    name="action"
                                    value="simpan"
                                    class="btn btn-primary btn-lg">
                                <i class="bi bi-save me-1"></i> Simpan Perubahan
                            </button>

                            <?php if ($status_asal === 7): ?>
                                <button type="submit"
                                        name="action"
                                        value="simpan_hantar"
                                        class="btn btn-success btn-lg"
                                        onclick="return confirm('Simpan perubahan dan hantar semula ke Ketua Bahagian?');">
                                    <i class="bi bi-send-check me-1"></i>
                                    Simpan &amp; Hantar Semula ke KB
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeInputs = document.querySelectorAll('input[name="jenis_aset"]');
    const computerSection = document.getElementById('seksyenKomputer');
    const printerSection = document.getElementById('seksyenPencetak');

    function updateSections() {
        const selected = document.querySelector('input[name="jenis_aset"]:checked');
        const type = selected ? selected.value : '';

        computerSection.classList.toggle(
            'section-hidden',
            type !== 'NB' && type !== 'PC'
        );

        printerSection.classList.toggle(
            'section-hidden',
            type !== 'Pencetak'
        );
    }

    typeInputs.forEach(function (input) {
        input.addEventListener('change', updateSections);
    });

    updateSections();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
