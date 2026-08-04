<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Catat Log Selenggara
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
$aset_id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_input(INPUT_POST, 'aset_id', FILTER_VALIDATE_INT)
    : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$form = [
    'jenis_selenggara' => '',
    'komponen_ditukar' => '',
    'kos' => '0.00',
    'catatan' => '',
    'status_aset_id' => '1',
];

$errors = [];
$error_umum = '';
$aset = null;

$status_options = [
    1 => 'Aktif',
    2 => 'Rosak',
    3 => 'Selenggara',
    4 => 'Hilang',
];

function selenggaraInvalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function selenggaraSelected(string $value, int $option): string
{
    return (int) $value === $option ? 'selected' : '';
}

function dapatkanAsetPidSelenggara(
    mysqli $conn,
    int $aset_id,
    int $agensi_id,
    bool $for_update = false
): ?array {
    $lock = $for_update ? ' FOR UPDATE' : '';

    $sql = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.jenama,
            a.model,
            a.tahun_beli,
            a.status_workflow_id,
            a.status_aset_id,
            ag.nama_agensi,
            sw.status AS status_workflow,
            sa.status AS status_aset
        FROM aset a
        INNER JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
        LEFT JOIN status_workflow sw
            ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa
            ON sa.status_aset_id = a.status_aset_id
        WHERE a.aset_id = ?
          AND a.agensi_id = ?
          AND a.wilayah_id = 1
          AND a.status_aset_id <> 5
        LIMIT 1
        {$lock}
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Gagal menyediakan query aset.');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $aset_id, $agensi_id);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal mendapatkan aset: ' . $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

try {
    $aset = dapatkanAsetPidSelenggara($conn, (int) $aset_id, $agensi_id);

    if (!$aset) {
        $_SESSION['flash_error'] =
            'Aset tidak ditemui, bukan milik agensi anda, atau telah dilupuskan.';
        header('Location: senarai_aset.php');
        exit;
    }

    $form['status_aset_id'] = (string) (
        in_array((int) $aset['status_aset_id'], [1, 2, 3, 4], true)
            ? (int) $aset['status_aset_id']
            : 1
    );
} catch (Throwable $e) {
    error_log('Selenggara PID - Fetch: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim((string) ($_POST[$key] ?? $default));
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_umum = 'Token keselamatan tidak sah. Sila muat semula halaman dan cuba lagi.';
    }

    if ($form['jenis_selenggara'] === '') {
        $errors['jenis_selenggara'] = 'Sila nyatakan jenis selenggara.';
    } elseif (strlen($form['jenis_selenggara']) > 150) {
        $errors['jenis_selenggara'] = 'Jenis selenggara terlalu panjang.';
    }

    if (strlen($form['komponen_ditukar']) > 5000) {
        $errors['komponen_ditukar'] = 'Maklumat komponen terlalu panjang.';
    }

    if (strlen($form['catatan']) > 5000) {
        $errors['catatan'] = 'Catatan terlalu panjang.';
    }

    if (
        $form['kos'] === ''
        || !is_numeric($form['kos'])
        || (float) $form['kos'] < 0
        || (float) $form['kos'] > 999999999.99
    ) {
        $errors['kos'] = 'Masukkan nilai kos yang sah dan tidak negatif.';
    }

    $status_aset_baru = filter_var(
        $form['status_aset_id'],
        FILTER_VALIDATE_INT
    );

    if (
        $status_aset_baru === false
        || !array_key_exists((int) $status_aset_baru, $status_options)
    ) {
        $errors['status_aset_id'] = 'Sila pilih status aset selepas selenggara.';
    }

    if ($error_umum === '' && $errors === []) {
        $kos = round((float) $form['kos'], 2);
        $status_aset_baru = (int) $status_aset_baru;
        $status_selepas = $status_options[$status_aset_baru];

        mysqli_begin_transaction($conn);

        try {
            $aset_terkini = dapatkanAsetPidSelenggara(
                $conn,
                (int) $aset_id,
                $agensi_id,
                true
            );

            if (!$aset_terkini) {
                throw new RuntimeException(
                    'Aset tidak lagi tersedia untuk rekod selenggara.'
                );
            }

            $sql_log = "
                INSERT INTO log_selenggara (
                    aset_id,
                    jenis_selenggara,
                    komponen_ditukar,
                    kos,
                    catatan,
                    status_selepas,
                    dibuat_oleh
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt_log = mysqli_prepare($conn, $sql_log);

            if (!$stmt_log) {
                throw new RuntimeException('Gagal menyediakan query log selenggara.');
            }

            mysqli_stmt_bind_param(
                $stmt_log,
                'issdssi',
                $aset_id,
                $form['jenis_selenggara'],
                $form['komponen_ditukar'],
                $kos,
                $form['catatan'],
                $status_selepas,
                $pengguna_id
            );

            if (!mysqli_stmt_execute($stmt_log)) {
                throw new RuntimeException(
                    'Gagal menyimpan log selenggara: ' .
                    mysqli_stmt_error($stmt_log)
                );
            }

            mysqli_stmt_close($stmt_log);

            $sql_update = "
                UPDATE aset
                SET
                    status_aset_id = ?,
                    tarikh_kemaskini = NOW()
                WHERE aset_id = ?
                  AND agensi_id = ?
                  AND wilayah_id = 1
                  AND status_aset_id <> 5
            ";

            $stmt_update = mysqli_prepare($conn, $sql_update);

            if (!$stmt_update) {
                throw new RuntimeException('Gagal menyediakan query status aset.');
            }

            mysqli_stmt_bind_param(
                $stmt_update,
                'iii',
                $status_aset_baru,
                $aset_id,
                $agensi_id
            );

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new RuntimeException(
                    'Gagal mengemas kini status aset: ' .
                    mysqli_stmt_error($stmt_update)
                );
            }

            if (mysqli_stmt_affected_rows($stmt_update) !== 1) {
                throw new RuntimeException(
                    'Status aset tidak berjaya dikemas kini.'
                );
            }

            mysqli_stmt_close($stmt_update);
            mysqli_commit($conn);

            logActivity(
                $conn,
                'Log Selenggara PID',
                'Log selenggara aset ' .
                $aset_terkini['no_pendaftaran'] .
                ' direkodkan. Status selepas: ' .
                $status_selepas . '.'
            );

            $_SESSION['flash_success'] =
                'Log selenggara aset ' .
                $aset_terkini['no_pendaftaran'] .
                ' berjaya direkodkan.';

            header('Location: lihat.php?id=' . (int) $aset_id . '&tab=selenggara');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('Selenggara PID - Simpan: ' . $e->getMessage());
            $error_umum =
                'Log selenggara tidak berjaya disimpan. Sila cuba lagi.';
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
    <title>Catat Selenggara PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-selenggara">
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

                <h1 class="h2 fw-bold mb-1">Catat Log Selenggara</h1>
                <p class="text-muted mb-0">
                    Rekod servis, komponen ditukar, kos dan status aset selepas selenggara.
                </p>
            </div>

            <?php if ($error_umum !== ''): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($error_umum); ?>
                </div>
            <?php endif; ?>

            <div class="alert info-banner mb-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                Rekod selenggara tidak mengubah status workflow aset.
                Hanya status fizikal aset akan dikemas kini.
            </div>

            <section class="card content-card mb-4">
                <div class="card-body p-3 p-lg-4">
                    <div class="asset-summary p-3 p-lg-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <h2 class="h4 fw-bold mb-2">
                                    <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                </h2>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-secondary">
                                        <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                    </span>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo escapeOutput((string) ($aset['status_workflow'] ?? '-')); ?>
                                    </span>
                                    <span class="badge bg-info text-dark">
                                        Status Semasa:
                                        <?php echo escapeOutput((string) ($aset['status_aset'] ?? '-')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-sm-6 col-lg-3">
                                <span class="summary-label">Agensi</span>
                                <div class="fw-semibold">
                                    <?php echo escapeOutput((string) $aset['nama_agensi']); ?>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="summary-label">Jenama</span>
                                <div class="fw-semibold">
                                    <?php echo escapeOutput((string) ($aset['jenama'] ?? '-')); ?>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="summary-label">Model</span>
                                <div class="fw-semibold">
                                    <?php echo escapeOutput((string) ($aset['model'] ?? '-')); ?>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <span class="summary-label">Tahun Beli</span>
                                <div class="fw-semibold">
                                    <?php echo escapeOutput((string) ($aset['tahun_beli'] ?? '-')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card content-card">
                <div class="card-body p-3 p-lg-4">
                    <form method="POST" action="selenggara.php" novalidate>
                        <input type="hidden"
                               name="csrf_token"
                               value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden"
                               name="aset_id"
                               value="<?php echo (int) $aset_id; ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="jenis_selenggara" class="form-label">
                                    Jenis Selenggara <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       id="jenis_selenggara"
                                       name="jenis_selenggara"
                                       class="form-control<?php echo selenggaraInvalid($errors, 'jenis_selenggara'); ?>"
                                       value="<?php echo escapeOutput($form['jenis_selenggara']); ?>"
                                       placeholder="cth. Servis berkala, pembaikan perkakasan"
                                       maxlength="150"
                                       required>
                                <?php if (isset($errors['jenis_selenggara'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['jenis_selenggara']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="kos" class="form-label">Kos (RM)</label>
                                <input type="number"
                                       id="kos"
                                       name="kos"
                                       class="form-control<?php echo selenggaraInvalid($errors, 'kos'); ?>"
                                       value="<?php echo escapeOutput($form['kos']); ?>"
                                       min="0"
                                       max="999999999.99"
                                       step="0.01">
                                <?php if (isset($errors['kos'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['kos']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="komponen_ditukar" class="form-label">
                                    Komponen Ditukar
                                </label>
                                <textarea id="komponen_ditukar"
                                          name="komponen_ditukar"
                                          class="form-control<?php echo selenggaraInvalid($errors, 'komponen_ditukar'); ?>"
                                          rows="3"
                                          placeholder="cth. SSD 512GB, RAM 16GB"><?php echo escapeOutput($form['komponen_ditukar']); ?></textarea>
                                <?php if (isset($errors['komponen_ditukar'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['komponen_ditukar']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="status_aset_id" class="form-label">
                                    Status Aset Selepas Selenggara
                                    <span class="text-danger">*</span>
                                </label>
                                <select id="status_aset_id"
                                        name="status_aset_id"
                                        class="form-select<?php echo selenggaraInvalid($errors, 'status_aset_id'); ?>"
                                        required>
                                    <?php foreach ($status_options as $id => $nama): ?>
                                        <option value="<?php echo (int) $id; ?>"
                                            <?php echo selenggaraSelected($form['status_aset_id'], $id); ?>>
                                            <?php echo escapeOutput($nama); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['status_aset_id'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['status_aset_id']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <label for="catatan" class="form-label">Catatan</label>
                                <textarea id="catatan"
                                          name="catatan"
                                          class="form-control<?php echo selenggaraInvalid($errors, 'catatan'); ?>"
                                          rows="4"
                                          placeholder="Maklumat tambahan tentang kerja selenggara..."><?php echo escapeOutput($form['catatan']); ?></textarea>
                                <?php if (isset($errors['catatan'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo escapeOutput($errors['catatan']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                                <a href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                                   class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-x-lg me-1"></i> Batal
                                </a>

                                <button type="submit"
                                        class="btn btn-success btn-lg"
                                        onclick="return confirm('Simpan rekod selenggara aset ini?');">
                                    <i class="bi bi-tools me-1"></i> Simpan Log Selenggara
                                </button>
                            </div>
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
