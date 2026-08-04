<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Daftar Aset Baharu
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
$wilayah_id = 1;
$current_year = (int) date('Y');

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

$errors = [];
$error_umum = '';
$nama_agensi = 'Agensi Tidak Ditetapkan';
$jenis_agensi = '-';
$kod_agensi = 'PID';
$cadangan_nombor = '';

$form = [
    'no_pendaftaran' => '',
    'jenis_aset' => '',
    'jenis_perolehan' => '',
    'tahun_beli' => (string) $current_year,
    'jenama' => '',
    'model' => '',
    'processor' => '',
    'ram' => '',
    'cakera_keras' => '',
    'sistem_operasi' => '',
    'spesifikasi_pencetak' => '',
    'jenis_pencetak' => 'Tiada',
    'bil_pencetak_laser' => '0',
    'bil_pencetak_inkjet' => '0',
    'bil_pencetak_matrik' => '0',
    'no_siri_pencetak' => '',
    'pegawai_nama' => '',
    'pegawai_jawatan' => '',
    'pegawai_gred' => '',
    'catatan' => '',
];

function binaKodAgensi(string $nama): string
{
    $perkataan = preg_split('/[^A-Za-z0-9]+/', trim($nama));
    $kod = '';

    if (is_array($perkataan)) {
        foreach ($perkataan as $item) {
            if ($item === '') {
                continue;
            }

            $kod .= strtoupper(substr($item, 0, 1));
        }
    }

    if ($kod === '') {
        return 'PID';
    }

    return substr($kod, 0, 8);
}

function nilaiPositifAtauSifar($nilai): int
{
    $integer = filter_var(
        $nilai,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    return $integer === false ? 0 : (int) $integer;
}

function kelasTidakSah(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function pilihanDipilih(string $nilai, string $opsyen): string
{
    return $nilai === $opsyen ? 'selected' : '';
}

function radioDipilih(string $nilai, string $opsyen): string
{
    return $nilai === $opsyen ? 'checked' : '';
}

try {
   $sql_agensi = "
    SELECT nama_agensi, jenis_agensi
    FROM agensi
    WHERE agensi_id = ?
    LIMIT 1
";

    $stmt_agensi = mysqli_prepare($conn, $sql_agensi);

    if (!$stmt_agensi) {
        throw new RuntimeException('Gagal menyediakan query agensi.');
    }

    mysqli_stmt_bind_param($stmt_agensi, 'i', $agensi_id);

    if (!mysqli_stmt_execute($stmt_agensi)) {
        throw new RuntimeException('Gagal mendapatkan maklumat agensi.');
    }

    $agensi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_agensi));
    mysqli_stmt_close($stmt_agensi);

    if (!$agensi) {
        throw new RuntimeException('Agensi PID tidak ditemui.');
    }

    $nama_agensi = (string) $agensi['nama_agensi'];
    $jenis_agensi = (string) $agensi['jenis_agensi'];
    $kod_agensi = binaKodAgensi($nama_agensi);

    $sql_bilangan = "
        SELECT COUNT(aset_id) AS jumlah
        FROM aset
        WHERE agensi_id = ?
          AND wilayah_id = 1
    ";

    $stmt_bilangan = mysqli_prepare($conn, $sql_bilangan);

    if (!$stmt_bilangan) {
        throw new RuntimeException('Gagal menyediakan query bilangan aset.');
    }

    mysqli_stmt_bind_param($stmt_bilangan, 'i', $agensi_id);

    if (!mysqli_stmt_execute($stmt_bilangan)) {
        throw new RuntimeException('Gagal mendapatkan bilangan aset.');
    }

    $row_bilangan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bilangan));
    mysqli_stmt_close($stmt_bilangan);

    $nombor_seterusnya = ((int) ($row_bilangan['jumlah'] ?? 0)) + 1;
    $cadangan_nombor = sprintf(
        '%s/[JENIS]/%d/%02d',
        $kod_agensi,
        $current_year,
        $nombor_seterusnya
    );
} catch (Throwable $e) {
    error_log('Tambah Aset PID - Agensi: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat agensi PID tidak dapat disahkan.';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim((string) ($_POST[$key] ?? $default));
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
        $errors['tahun_beli'] = 'Tahun beli mesti antara 2000 hingga ' . $current_year . '.';
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

    $bil_laser = nilaiPositifAtauSifar($form['bil_pencetak_laser']);
    $bil_inkjet = nilaiPositifAtauSifar($form['bil_pencetak_inkjet']);
    $bil_matrik = nilaiPositifAtauSifar($form['bil_pencetak_matrik']);

    if ($error_umum === '' && $errors === []) {
        $sql_semak_nombor = "
            SELECT aset_id
            FROM aset
            WHERE no_pendaftaran = ?
            LIMIT 1
        ";

        $stmt_semak_nombor = mysqli_prepare($conn, $sql_semak_nombor);

        if (!$stmt_semak_nombor) {
            $error_umum = 'Ralat semasa menyemak nombor pendaftaran.';
        } else {
            mysqli_stmt_bind_param(
                $stmt_semak_nombor,
                's',
                $form['no_pendaftaran']
            );

            if (!mysqli_stmt_execute($stmt_semak_nombor)) {
                $error_umum = 'Ralat semasa menyemak nombor pendaftaran.';
            } else {
                $rekod_sedia_ada = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt_semak_nombor)
                );

                if ($rekod_sedia_ada) {
                    $errors['no_pendaftaran'] = 'Nombor pendaftaran ini sudah digunakan.';
                }
            }

            mysqli_stmt_close($stmt_semak_nombor);
        }
    }

    if ($error_umum === '' && $errors === []) {
        mysqli_begin_transaction($conn);

        try {
            $sql_insert = "
                INSERT INTO aset (
                    no_pendaftaran,
                    jenis_aset,
                    jenis_perolehan,
                    tahun_beli,
                    jenama,
                    model,
                    processor,
                    ram,
                    cakera_keras,
                    sistem_operasi,
                    spesifikasi_pencetak,
                    jenis_pencetak,
                    bil_pencetak_laser,
                    bil_pencetak_inkjet,
                    bil_pencetak_matrik,
                    no_siri_pencetak,
                    pegawai_nama,
                    pegawai_jawatan,
                    pegawai_gred,
                    agensi_id,
                    wilayah_id,
                    status_workflow_id,
                    status_aset_id,
                    pengguna_id_daftar,
                    catatan,
                    tarikh_input,
                    tarikh_kemaskini
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, 1, 6, 1, ?, ?, NOW(), NOW()
                )
            ";

            $stmt_insert = mysqli_prepare($conn, $sql_insert);

            if (!$stmt_insert) {
                throw new RuntimeException('Gagal menyediakan query daftar aset.');
            }

            mysqli_stmt_bind_param(
                $stmt_insert,
                'sssissssssssiiisssiiis',
                $form['no_pendaftaran'],
                $form['jenis_aset'],
                $form['jenis_perolehan'],
                $tahun_beli,
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
                $agensi_id,
                $pengguna_id,
                $form['catatan']
            );

            if (!mysqli_stmt_execute($stmt_insert)) {
                throw new RuntimeException(
                    'Gagal mendaftarkan aset: ' . mysqli_stmt_error($stmt_insert)
                );
            }

            $aset_id_baru = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_insert);

            if ($aset_id_baru <= 0) {
                throw new RuntimeException('ID aset baharu tidak berjaya dijana.');
            }

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
                    'Daftar',
                    NULL,
                    6,
                    'Aset didaftarkan oleh PID — dihantar terus ke Ketua Bahagian',
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
                $aset_id_baru,
                $pengguna_id
            );

            if (!mysqli_stmt_execute($stmt_log)) {
                throw new RuntimeException('Gagal merekod log workflow.');
            }

            mysqli_stmt_close($stmt_log);

            $sql_ketua_bahagian = "
                SELECT p.pengguna_id
                FROM pengguna p
                INNER JOIN peranan r
                    ON r.peranan_id = p.peranan_id
                WHERE r.nama_peranan = 'Ketua Bahagian'
                  AND p.status_pengguna_id = 1
            ";

            $stmt_ketua_bahagian = mysqli_prepare($conn, $sql_ketua_bahagian);

            if (!$stmt_ketua_bahagian) {
                throw new RuntimeException('Gagal menyediakan query Ketua Bahagian.');
            }

            if (!mysqli_stmt_execute($stmt_ketua_bahagian)) {
                throw new RuntimeException('Gagal mendapatkan senarai Ketua Bahagian.');
            }

            $senarai_ketua_bahagian = mysqli_fetch_all(
                mysqli_stmt_get_result($stmt_ketua_bahagian),
                MYSQLI_ASSOC
            );

            mysqli_stmt_close($stmt_ketua_bahagian);

            $mesej_notifikasi =
                'Aset baru ' . $form['no_pendaftaran'] .
                ' dari ' . $nama_agensi .
                ' telah didaftarkan oleh PID. Sila semak.';

            $url_notifikasi =
                '/jtdis_asset/pages/ketua-bahagian/lihat.php?id=' .
                $aset_id_baru;

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

            foreach ($senarai_ketua_bahagian as $ketua_bahagian) {
                $penerima_id = (int) $ketua_bahagian['pengguna_id'];

                mysqli_stmt_bind_param(
                    $stmt_notifikasi,
                    'iiss',
                    $penerima_id,
                    $aset_id_baru,
                    $mesej_notifikasi,
                    $url_notifikasi
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
                'Daftar Aset PID',
                'Aset ' . $form['no_pendaftaran'] .
                ' didaftarkan oleh PID dan dihantar ke Ketua Bahagian.'
            );

            $_SESSION['flash_success'] =
                'Aset ' . $form['no_pendaftaran'] .
                ' berjaya didaftarkan dan dihantar ke Ketua Bahagian.';

            header('Location: senarai_aset.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('Tambah Aset PID: ' . $e->getMessage());

            if (
                stripos($e->getMessage(), 'Duplicate entry') !== false
                || stripos($e->getMessage(), 'duplicate') !== false
            ) {
                $errors['no_pendaftaran'] =
                    'Nombor pendaftaran ini sudah digunakan.';
            } else {
                $error_umum =
                    'Aset tidak berjaya didaftarkan. Sila semak maklumat dan cuba lagi.';
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
    <title>Daftar Aset Baharu PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-tambah">
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
                <a class="nav-link" href="senarai_aset.php">
                    <i class="bi bi-boxes me-2"></i> Senarai Aset
                </a>
                <a class="nav-link active" href="tambah.php">
                    <i class="bi bi-plus-circle me-2"></i> Daftar Aset Baru
                </a>

                <hr class="w-100 sidebar-divider">

                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-2"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 main-content p-3 p-lg-4">
            <div class="mb-4">
                <a href="senarai_aset.php" class="btn btn-outline-secondary mb-3">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>

                <h1 class="page-title h2 mb-1">Daftar Aset Baharu</h1>
                <p class="text-muted mb-0">
                    Aset akan dihantar terus kepada Ketua Bahagian selepas disimpan.
                </p>
            </div>

            <div class="agency-note p-3 mb-4">
                <i class="bi bi-building me-2"></i>
                <strong>Agensi:</strong>
                <?php echo escapeOutput($nama_agensi); ?>
                <span class="ms-2 badge bg-light text-dark border">
                    <?php echo escapeOutput(ucwords(str_replace('_', ' ', $jenis_agensi))); ?>
                </span>
            </div>

            <?php if ($error_umum !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($error_umum); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="tambah.php" novalidate>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escapeOutput($csrf_token); ?>"
                >

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

                                <div class="input-group">
                                    <input
                                        type="text"
                                        id="no_pendaftaran"
                                        name="no_pendaftaran"
                                        class="form-control<?php echo kelasTidakSah($errors, 'no_pendaftaran'); ?>"
                                        value="<?php echo escapeOutput($form['no_pendaftaran']); ?>"
                                        placeholder="cth. JPKN(WPBU)BTD/NB/2024/01"
                                        maxlength="150"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        id="btnJanaAuto"
                                        data-kod="<?php echo escapeOutput($kod_agensi); ?>"
                                        data-tahun="<?php echo $current_year; ?>"
                                        data-nombor="<?php echo max(1, $nombor_seterusnya); ?>"
                                    >
                                        <i class="bi bi-magic me-1"></i> Jana Auto
                                    </button>

                                    <?php if (isset($errors['no_pendaftaran'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo escapeOutput($errors['no_pendaftaran']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-text">
                                    Cadangan format:
                                    <code><?php echo escapeOutput($cadangan_nombor); ?></code>
                                </div>
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
                                            <input
                                                type="radio"
                                                id="<?php echo escapeOutput($radio_id); ?>"
                                                name="jenis_aset"
                                                value="<?php echo escapeOutput($jenis); ?>"
                                                <?php echo radioDipilih($form['jenis_aset'], $jenis); ?>
                                                required
                                            >

                                            <label
                                                for="<?php echo escapeOutput($radio_id); ?>"
                                                class="asset-type-label"
                                            >
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

                                <select
                                    id="jenis_perolehan"
                                    name="jenis_perolehan"
                                    class="form-select<?php echo kelasTidakSah($errors, 'jenis_perolehan'); ?>"
                                    required
                                >
                                    <option value="">Pilih jenis perolehan</option>

                                    <?php foreach ([
                                        'Kerajaan Negeri',
                                        'Kerajaan Persekutuan',
                                        'Sewa',
                                        'Pinjaman',
                                        'Lain',
                                    ] as $item): ?>
                                        <option
                                            value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihanDipilih($form['jenis_perolehan'], $item); ?>
                                        >
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
                                <label for="tahun_beli" class="form-label required">
                                    Tahun Beli
                                </label>

                                <input
                                    type="number"
                                    id="tahun_beli"
                                    name="tahun_beli"
                                    class="form-control<?php echo kelasTidakSah($errors, 'tahun_beli'); ?>"
                                    value="<?php echo escapeOutput($form['tahun_beli']); ?>"
                                    min="2000"
                                    max="<?php echo $current_year; ?>"
                                    required
                                >

                                <?php if (isset($errors['tahun_beli'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['tahun_beli']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="jenama" class="form-label required">Jenama</label>

                                <input
                                    type="text"
                                    id="jenama"
                                    name="jenama"
                                    class="form-control<?php echo kelasTidakSah($errors, 'jenama'); ?>"
                                    value="<?php echo escapeOutput($form['jenama']); ?>"
                                    maxlength="100"
                                    required
                                >

                                <?php if (isset($errors['jenama'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['jenama']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="model" class="form-label required">Model</label>

                                <input
                                    type="text"
                                    id="model"
                                    name="model"
                                    class="form-control<?php echo kelasTidakSah($errors, 'model'); ?>"
                                    value="<?php echo escapeOutput($form['model']); ?>"
                                    maxlength="100"
                                    required
                                >

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
                                <input
                                    type="text"
                                    id="processor"
                                    name="processor"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['processor']); ?>"
                                    placeholder="cth. Intel Core i5-1135G7"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="ram" class="form-label">RAM</label>
                                <select id="ram" name="ram" class="form-select">
                                    <option value="">Pilih RAM</option>

                                    <?php foreach (['4GB', '8GB', '16GB', '32GB', 'Lain-lain'] as $item): ?>
                                        <option
                                            value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihanDipilih($form['ram'], $item); ?>
                                        >
                                            <?php echo escapeOutput($item); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="cakera_keras" class="form-label">Cakera Keras</label>
                                <input
                                    type="text"
                                    id="cakera_keras"
                                    name="cakera_keras"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['cakera_keras']); ?>"
                                    placeholder="cth. 256GB SSD"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="sistem_operasi" class="form-label">Sistem Operasi</label>
                                <select id="sistem_operasi" name="sistem_operasi" class="form-select">
                                    <option value="">Pilih sistem operasi</option>

                                    <?php foreach (['Windows 10', 'Windows 11', 'Lain-lain'] as $item): ?>
                                        <option
                                            value="<?php echo escapeOutput($item); ?>"
                                            <?php echo pilihanDipilih($form['sistem_operasi'], $item); ?>
                                        >
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
                                            <input
                                                type="radio"
                                                id="<?php echo escapeOutput($printer_id); ?>"
                                                name="jenis_pencetak"
                                                value="<?php echo escapeOutput($item); ?>"
                                                <?php echo radioDipilih($form['jenis_pencetak'], $item); ?>
                                            >

                                            <label
                                                class="printer-type-label"
                                                for="<?php echo escapeOutput($printer_id); ?>"
                                            >
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

                                <input
                                    type="text"
                                    id="spesifikasi_pencetak"
                                    name="spesifikasi_pencetak"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['spesifikasi_pencetak']); ?>"
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_laser" class="form-label">
                                    Bil. Pencetak Laser
                                </label>

                                <input
                                    type="number"
                                    id="bil_pencetak_laser"
                                    name="bil_pencetak_laser"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['bil_pencetak_laser']); ?>"
                                    min="0"
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_inkjet" class="form-label">
                                    Bil. Pencetak Inkjet
                                </label>

                                <input
                                    type="number"
                                    id="bil_pencetak_inkjet"
                                    name="bil_pencetak_inkjet"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['bil_pencetak_inkjet']); ?>"
                                    min="0"
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="bil_pencetak_matrik" class="form-label">
                                    Bil. Pencetak Matrik
                                </label>

                                <input
                                    type="number"
                                    id="bil_pencetak_matrik"
                                    name="bil_pencetak_matrik"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['bil_pencetak_matrik']); ?>"
                                    min="0"
                                >
                            </div>

                            <div class="col-12">
                                <label for="no_siri_pencetak" class="form-label">
                                    No. Siri Pencetak
                                </label>

                                <input
                                    type="text"
                                    id="no_siri_pencetak"
                                    name="no_siri_pencetak"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['no_siri_pencetak']); ?>"
                                >
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
                                <input
                                    type="text"
                                    id="pegawai_nama"
                                    name="pegawai_nama"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['pegawai_nama']); ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="pegawai_jawatan" class="form-label">Jawatan</label>
                                <input
                                    type="text"
                                    id="pegawai_jawatan"
                                    name="pegawai_jawatan"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['pegawai_jawatan']); ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label for="pegawai_gred" class="form-label">Gred</label>
                                <input
                                    type="text"
                                    id="pegawai_gred"
                                    name="pegawai_gred"
                                    class="form-control"
                                    value="<?php echo escapeOutput($form['pegawai_gred']); ?>"
                                    placeholder="cth. N1, NA12, F41"
                                >
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
                        <textarea
                            id="catatan"
                            name="catatan"
                            class="form-control"
                            rows="4"
                        ><?php echo escapeOutput($form['catatan']); ?></textarea>
                    </div>
                </section>

                <div class="sticky-submit">
                    <div class="sticky-submit-inner">
                        <div class="small text-muted">
                            <span class="text-danger">*</span> Medan wajib diisi
                        </div>

                        <div class="btn-group-area d-flex gap-2">
                            <a href="senarai_aset.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-x-lg me-1"></i> Batal
                            </a>

                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-send-check me-1"></i>
                                Simpan &amp; Hantar ke KB
                            </button>
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
    const generateButton = document.getElementById('btnJanaAuto');
    const registrationInput = document.getElementById('no_pendaftaran');
    const yearInput = document.getElementById('tahun_beli');

    function selectedType() {
        const selected = document.querySelector('input[name="jenis_aset"]:checked');
        return selected ? selected.value : '';
    }

    function updateSections() {
        const type = selectedType();
        const isComputer = type === 'NB' || type === 'PC';
        const isPrinter = type === 'Pencetak';

        computerSection.classList.toggle('section-hidden', !isComputer);
        printerSection.classList.toggle('section-hidden', !isPrinter);
    }

    typeInputs.forEach(function (input) {
        input.addEventListener('change', updateSections);
    });

    generateButton.addEventListener('click', function () {
        const type = selectedType();

        if (!type) {
            window.alert('Sila pilih jenis aset terlebih dahulu.');
            return;
        }

        const typeCode = type === 'Pencetak'
            ? 'PRN'
            : type === 'Monitor'
                ? 'MON'
                : type.toUpperCase();

        const agencyCode = generateButton.dataset.kod || 'PID';
        const currentYear = yearInput.value || generateButton.dataset.tahun;
        const sequence = String(generateButton.dataset.nombor || '1').padStart(2, '0');

        registrationInput.value =
            agencyCode + '/' + typeCode + '/' + currentYear + '/' + sequence;

        registrationInput.focus();
    });

    updateSections();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
