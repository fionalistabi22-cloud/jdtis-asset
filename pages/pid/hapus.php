<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Pelupusan / Soft Delete Aset
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

$aset = null;
$error_umum = '';
$sebab_pelupusan = trim((string) ($_POST['sebab_pelupusan'] ?? ''));
$csrf_token = generateCSRFToken();

function pidHapusWorkflowBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-secondary',
        6 => 'bg-warning text-dark',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
}

function pidHapusStatusBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-success',
        2 => 'bg-danger',
        3 => 'bg-warning text-dark',
        4 => 'bg-dark',
        5 => 'bg-secondary',
        default => 'bg-secondary',
    };
}

/**
 * Dapatkan aset dengan skop keselamatan penuh.
 * Pelupusan hanya dibenarkan jika workflow bukan 6 atau 8.
 */
function dapatkanAsetPidUntukPelupusan(
    mysqli $conn,
    int $aset_id,
    int $agensi_id
): ?array {
    $sql = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.jenis_perolehan,
            a.jenama,
            a.model,
            a.tahun_beli,
            a.pegawai_nama,
            a.pegawai_jawatan,
            a.pegawai_gred,
            a.status_workflow_id,
            a.status_aset_id,
            a.maklumat_pelupusan_aset,
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
          AND a.status_workflow_id NOT IN (6, 8)
        LIMIT 1
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
    $aset = dapatkanAsetPidUntukPelupusan($conn, (int) $aset_id, $agensi_id);

    if (!$aset) {
        $_SESSION['flash_error'] =
            'Aset tidak ditemui, bukan milik agensi anda, sedang disemak Ketua Bahagian, atau sudah diluluskan.';

        header('Location: senarai_aset.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('Hapus Aset PID - Fetch: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Maklumat aset tidak dapat dimuatkan.';
    header('Location: senarai_aset.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_umum = 'Token keselamatan tidak sah. Sila muat semula halaman dan cuba lagi.';
    }

    if ($sebab_pelupusan === '') {
        $error_umum = 'Sila nyatakan sebab pelupusan aset.';
    } elseif (strlen($sebab_pelupusan) > 5000) {
        $error_umum = 'Sebab pelupusan terlalu panjang.';
    }

    if ($error_umum === '') {
        $status_workflow_lama = (int) $aset['status_workflow_id'];
        $no_pendaftaran = (string) $aset['no_pendaftaran'];

        mysqli_begin_transaction($conn);

        try {
            /*
             * Soft delete:
             * - status_aset_id menjadi 5 (Dilupuskan)
             * - status_workflow_id tidak berubah
             * - skop dan status workflow disemak semula dalam WHERE
             */
            $sql_update = "
                UPDATE aset
                SET
                    status_aset_id = 5,
                    maklumat_pelupusan_aset = ?,
                    tarikh_kemaskini = NOW()
                WHERE aset_id = ?
                  AND agensi_id = ?
                  AND wilayah_id = 1
                  AND status_workflow_id NOT IN (6, 8)
            ";

            $stmt_update = mysqli_prepare($conn, $sql_update);

            if (!$stmt_update) {
                throw new RuntimeException('Gagal menyediakan query pelupusan aset.');
            }

            mysqli_stmt_bind_param(
                $stmt_update,
                'sii',
                $sebab_pelupusan,
                $aset_id,
                $agensi_id
            );

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new RuntimeException(
                    'Gagal melupuskan aset: ' . mysqli_stmt_error($stmt_update)
                );
            }

            if (mysqli_stmt_affected_rows($stmt_update) !== 1) {
                throw new RuntimeException(
                    'Aset tidak berjaya dilupuskan. Status workflow mungkin telah berubah.'
                );
            }

            mysqli_stmt_close($stmt_update);

            /*
             * Enum tindakan tidak mempunyai nilai "Lupus".
             * Gunakan "Daftar" sebagai nilai terdekat dan jelaskan tindakan
             * sebenar melalui catatan audit.
             */
            $catatan_log = 'Aset dilupuskan: ' . $sebab_pelupusan;

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
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt_log = mysqli_prepare($conn, $sql_log);

            if (!$stmt_log) {
                throw new RuntimeException('Gagal menyediakan query log pelupusan.');
            }

            mysqli_stmt_bind_param(
                $stmt_log,
                'iiisi',
                $aset_id,
                $status_workflow_lama,
                $status_workflow_lama,
                $catatan_log,
                $pengguna_id
            );

            if (!mysqli_stmt_execute($stmt_log)) {
                throw new RuntimeException(
                    'Gagal merekod log pelupusan: ' . mysqli_stmt_error($stmt_log)
                );
            }

            mysqli_stmt_close($stmt_log);
            mysqli_commit($conn);

            logActivity(
                $conn,
                'Pelupusan Aset PID',
                'Aset ' . $no_pendaftaran .
                ' dilupuskan oleh PID. Sebab: ' . $sebab_pelupusan
            );

            $_SESSION['flash_success'] =
                'Aset ' . $no_pendaftaran . ' berjaya dilupuskan.';

            header('Location: senarai_aset.php');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('Hapus Aset PID: ' . $e->getMessage());

            $error_umum =
                'Aset tidak berjaya dilupuskan. Sila semak status aset dan cuba lagi.';

            /*
             * Muat semula rekod untuk memastikan paparan menggunakan data terkini
             * selepas rollback atau perubahan serentak.
             */
            try {
                $aset_terkini = dapatkanAsetPidUntukPelupusan(
                    $conn,
                    (int) $aset_id,
                    $agensi_id
                );

                if ($aset_terkini) {
                    $aset = $aset_terkini;
                }
            } catch (Throwable $reload_error) {
                error_log(
                    'Hapus Aset PID - Reload: ' .
                    $reload_error->getMessage()
                );
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
    <title>Lupuskan Aset PID - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="pid.css">
</head>
<body class="pid page-hapus">
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <span class="brand-mark">
                    <i class="bi bi-building-check"></i>
                </span>

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
                <a
                    href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                    class="btn btn-outline-secondary mb-3"
                >
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>

                <h1 class="page-title h2 mb-1">Lupuskan Aset</h1>
                <p class="text-muted mb-0">
                    Pelupusan menggunakan kaedah soft delete untuk mengekalkan rekod audit.
                </p>
            </div>

            <?php if ($error_umum !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo escapeOutput($error_umum); ?>
                </div>
            <?php endif; ?>

            <div class="alert warning-card p-3 p-lg-4 mb-4">
                <div class="d-flex gap-3 align-items-start">
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-3"></i>

                    <div>
                        <h2 class="h5 text-danger mb-2">Amaran Pelupusan</h2>

                        <p class="mb-1">
                            <strong>Aset yang dihapus tidak boleh dipulihkan semula.</strong>
                        </p>

                        <p class="mb-0">
                            Rekod aset akan kekal dalam pangkalan data untuk tujuan audit,
                            tetapi status fizikalnya akan ditukar kepada
                            <strong>Dilupuskan</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <section class="card content-card mb-4">
                <div class="card-header bg-white border-0 p-3 p-lg-4 pb-2">
                    <h2 class="h5 mb-1">Ringkasan Aset</h2>
                    <small class="text-muted">
                        Sahkan aset yang hendak dilupuskan sebelum meneruskan.
                    </small>
                </div>

                <div class="card-body p-3 p-lg-4">
                    <div class="asset-summary p-3 p-lg-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                            <div>
                                <h3 class="h4 fw-bold mb-2">
                                    <?php echo escapeOutput((string) $aset['no_pendaftaran']); ?>
                                </h3>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-secondary">
                                        <?php echo escapeOutput((string) $aset['jenis_aset']); ?>
                                    </span>

                                    <span class="badge <?php
                                        echo escapeOutput(
                                            pidHapusWorkflowBadge(
                                                (int) $aset['status_workflow_id']
                                            )
                                        );
                                    ?>">
                                        <?php echo escapeOutput(
                                            (string) ($aset['status_workflow'] ?? '-')
                                        ); ?>
                                    </span>

                                    <span class="badge <?php
                                        echo escapeOutput(
                                            pidHapusStatusBadge(
                                                (int) $aset['status_aset_id']
                                            )
                                        );
                                    ?>">
                                        Status Aset:
                                        <?php echo escapeOutput(
                                            (string) ($aset['status_aset'] ?? '-')
                                        ); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <?php
                            $ringkasan = [
                                'Agensi' => $aset['nama_agensi'] ?? '-',
                                'Jenis Perolehan' => $aset['jenis_perolehan'] ?? '-',
                                'Jenama' => $aset['jenama'] ?? '-',
                                'Model' => $aset['model'] ?? '-',
                                'Tahun Beli' => $aset['tahun_beli'] ?? '-',
                                'Pegawai' => $aset['pegawai_nama'] ?? '-',
                                'Jawatan' => $aset['pegawai_jawatan'] ?? '-',
                                'Gred' => $aset['pegawai_gred'] ?? '-',
                            ];
                            ?>

                            <?php foreach ($ringkasan as $label => $nilai): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <span class="summary-label">
                                        <?php echo escapeOutput($label); ?>
                                    </span>

                                    <div class="summary-value">
                                        <?php echo escapeOutput((string) $nilai); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card content-card">
                <div class="card-body p-3 p-lg-4">
                    <h2 class="h5 mb-3">Pengesahan Pelupusan</h2>

                    <form
                        method="POST"
                        action="hapus.php"
                        onsubmit="return confirm('Anda pasti mahu melupuskan aset ini? Tindakan ini tidak boleh dipulihkan.');"
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

                        <div class="mb-4">
                            <label for="sebab_pelupusan" class="form-label">
                                Sebab Pelupusan
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                id="sebab_pelupusan"
                                name="sebab_pelupusan"
                                class="form-control<?php echo $error_umum !== '' && $sebab_pelupusan === '' ? ' is-invalid' : ''; ?>"
                                rows="5"
                                maxlength="5000"
                                placeholder="Nyatakan sebab aset dilupuskan, nombor rujukan atau maklumat berkaitan..."
                                required
                            ><?php echo escapeOutput($sebab_pelupusan); ?></textarea>

                            <div class="form-text">
                                Maklumat ini akan disimpan dalam rekod pelupusan dan log audit.
                            </div>

                            <?php if ($error_umum !== '' && $sebab_pelupusan === ''): ?>
                                <div class="invalid-feedback">
                                    Sila nyatakan sebab pelupusan aset.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                            <a
                                href="lihat.php?id=<?php echo (int) $aset_id; ?>"
                                class="btn btn-outline-secondary btn-lg"
                            >
                                <i class="bi bi-x-lg me-1"></i> Batal
                            </a>

                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-trash3-fill me-1"></i>
                                Hapus / Lupuskan
                            </button>
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
