<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Modul PID - Hantar Semula Aset ke Ketua Bahagian
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['PID']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Kaedah permintaan tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$agensi_id = (int) ($_SESSION['agensi_id'] ?? 0);
$aset_id = filter_input(INPUT_POST, 'aset_id', FILTER_VALIDATE_INT);
$csrf_token = (string) ($_POST['csrf_token'] ?? '');

if ($pengguna_id <= 0 || $agensi_id <= 0) {
    $_SESSION['flash_error'] = 'Maklumat akaun PID tidak lengkap. Sila log masuk semula.';
    header('Location: ../logout.php');
    exit;
}

if (!verifyCSRFToken($csrf_token)) {
    $_SESSION['flash_error'] = 'Token keselamatan tidak sah. Sila cuba semula.';
    header('Location: senarai_aset.php');
    exit;
}

if ($aset_id === false || $aset_id === null || $aset_id < 1) {
    $_SESSION['flash_error'] = 'ID aset tidak sah.';
    header('Location: senarai_aset.php');
    exit;
}

$aset = null;
$nama_agensi = 'Agensi Tidak Ditetapkan';

try {
    /*
     * Skop keselamatan wajib:
     * - aset mesti milik agensi PID sendiri
     * - wilayah_id mesti 1
     * - hanya Draf (1) atau Ditolak KB (7) boleh dihantar
     */
    $sql_aset = "
        SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.status_workflow_id,
            a.agensi_id,
            a.wilayah_id,
            ag.nama_agensi
        FROM aset a
        INNER JOIN agensi ag
            ON ag.agensi_id = a.agensi_id
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

    mysqli_stmt_bind_param(
        $stmt_aset,
        'ii',
        $aset_id,
        $agensi_id
    );

    if (!mysqli_stmt_execute($stmt_aset)) {
        throw new RuntimeException('Gagal mendapatkan maklumat aset.');
    }

    $aset = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_aset));
    mysqli_stmt_close($stmt_aset);

    if (!$aset) {
        $_SESSION['flash_error'] =
            'Aset tidak ditemui, bukan milik agensi anda, atau tidak boleh dihantar pada status semasa.';

        header('Location: senarai_aset.php');
        exit;
    }

    $nama_agensi = (string) ($aset['nama_agensi'] ?? $nama_agensi);
} catch (Throwable $e) {
    error_log('Hantar Semula PID - Semakan: ' . $e->getMessage());

    $_SESSION['flash_error'] =
        'Maklumat aset tidak dapat disahkan. Sila cuba semula.';

    header('Location: senarai_aset.php');
    exit;
}

$status_lama = (int) $aset['status_workflow_id'];
$no_pendaftaran = (string) $aset['no_pendaftaran'];

mysqli_begin_transaction($conn);

try {
    /*
     * Operasi 1:
     * Tukar status workflow kepada 6 — Menunggu Ketua Bahagian.
     * Scope dan status lama disemak semula dalam WHERE.
     */
    $sql_update = "
        UPDATE aset
        SET
            status_workflow_id = 6,
            tarikh_kemaskini = NOW()
        WHERE aset_id = ?
          AND agensi_id = ?
          AND wilayah_id = 1
          AND status_workflow_id IN (1, 7)
    ";

    $stmt_update = mysqli_prepare($conn, $sql_update);

    if (!$stmt_update) {
        throw new RuntimeException('Gagal menyediakan query kemas kini status.');
    }

    mysqli_stmt_bind_param(
        $stmt_update,
        'ii',
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
            'Status aset telah berubah atau aset tidak lagi boleh dihantar.'
        );
    }

    mysqli_stmt_close($stmt_update);

    /*
     * Operasi 2:
     * Rekod log workflow.
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
        $status_lama,
        $pengguna_id
    );

    if (!mysqli_stmt_execute($stmt_log)) {
        throw new RuntimeException(
            'Gagal merekod log workflow: ' .
            mysqli_stmt_error($stmt_log)
        );
    }

    mysqli_stmt_close($stmt_log);

    /*
     * Operasi 3:
     * Dapatkan semua Ketua Bahagian aktif.
     */
    $sql_ketua_bahagian = "
        SELECT p.pengguna_id
        FROM pengguna p
        INNER JOIN peranan r
            ON r.peranan_id = p.peranan_id
        WHERE r.nama_peranan = 'Ketua Bahagian'
          AND p.status_pengguna_id = 1
    ";

    $stmt_ketua_bahagian = mysqli_prepare(
        $conn,
        $sql_ketua_bahagian
    );

    if (!$stmt_ketua_bahagian) {
        throw new RuntimeException(
            'Gagal menyediakan query Ketua Bahagian.'
        );
    }

    if (!mysqli_stmt_execute($stmt_ketua_bahagian)) {
        throw new RuntimeException(
            'Gagal mendapatkan senarai Ketua Bahagian.'
        );
    }

    $senarai_ketua_bahagian = mysqli_fetch_all(
        mysqli_stmt_get_result($stmt_ketua_bahagian),
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt_ketua_bahagian);

    $mesej_notifikasi =
        'Aset ' . $no_pendaftaran .
        ' dari ' . $nama_agensi .
        ' telah dihantar semula untuk semakan KB.';

    $url_notifikasi =
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

    $stmt_notifikasi = mysqli_prepare(
        $conn,
        $sql_notifikasi
    );

    if (!$stmt_notifikasi) {
        throw new RuntimeException(
            'Gagal menyediakan query notifikasi.'
        );
    }

    foreach ($senarai_ketua_bahagian as $ketua_bahagian) {
        $penerima_id = (int) $ketua_bahagian['pengguna_id'];

        mysqli_stmt_bind_param(
            $stmt_notifikasi,
            'iiss',
            $penerima_id,
            $aset_id,
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
        'Hantar Semula Aset PID',
        'Aset ' . $no_pendaftaran .
        ' dihantar semula ke Ketua Bahagian.'
    );

    $_SESSION['flash_success'] =
        'Aset ' . $no_pendaftaran .
        ' berjaya dihantar semula ke Ketua Bahagian.';

    header('Location: senarai_aset.php');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);

    error_log(
        'Hantar Semula Aset PID: ' .
        $e->getMessage()
    );

    $_SESSION['flash_error'] =
        'Aset tidak berjaya dihantar semula. Sila cuba lagi.';

    header('Location: senarai_aset.php');
    exit;
}
