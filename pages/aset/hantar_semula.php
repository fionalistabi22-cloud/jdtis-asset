<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Protect page (centralized guard: login + session expiry)
requireRoleWhitelist(['Juruteknik', 'Agen IT']);


$pengguna_id = isset($_SESSION['pengguna_id']) ? intval($_SESSION['pengguna_id']) : 0;
$aset_id = !empty($_POST['aset_id']) ? intval($_POST['aset_id']) : 0;

if ($pengguna_id <= 0 || $aset_id <= 0) {
    $_SESSION['flash_error'] = "Aset tidak boleh dihantar semula.";
    header('Location: index.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    $_SESSION['flash_error'] = "Token CSRF tidak sah";
    header('Location: index.php');
    exit;
}

// Validasi kelayakan:
// - aset wujud
// - pengguna_id_daftar = pengguna semasa
// - status_workflow_id IN (1,3)
$semak_query = "SELECT a.aset_id, a.no_pendaftaran, a.status_workflow_id, a.wilayah_id, a.pengguna_id_daftar
               FROM aset a
               WHERE a.aset_id = ?
                 AND a.pengguna_id_daftar = ?
                 AND a.status_workflow_id IN (1, 3)";

$semak_stmt = mysqli_prepare($conn, $semak_query);
if (!$semak_stmt) {
    $_SESSION['flash_error'] = "Ralat penyediaan semakan.";
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($semak_stmt, 'ii', $aset_id, $pengguna_id);
mysqli_stmt_execute($semak_stmt);
$aset = mysqli_fetch_assoc(mysqli_stmt_get_result($semak_stmt));

if (!$aset) {
    $_SESSION['flash_error'] = "Aset tidak boleh dihantar semula pada masa ini.";
    header('Location: index.php');
    exit;
}

$wilayah_id = !empty($aset['wilayah_id']) ? intval($aset['wilayah_id']) : 0;
$old_status = intval($aset['status_workflow_id']);

mysqli_begin_transaction($conn);
try {
    // OPERATION 1: UPDATE aset SET status_workflow_id = 2 (dan status_aset_id = 1 supaya PPTM lihat dalam Pengesahan)
    $update_stmt = mysqli_prepare(
        $conn,
        'UPDATE aset SET status_workflow_id = 2, status_aset_id = 1 WHERE aset_id = ?'
    );
    mysqli_stmt_bind_param($update_stmt, 'i', $aset_id);
    mysqli_stmt_execute($update_stmt);

    if (mysqli_stmt_affected_rows($update_stmt) <= 0) {
        throw new Exception("Aset tidak berjaya ditukar. Semakan pengguna/status workflow tidak sepadan.");
    }


    // OPERATION 2: INSERT into log_workflow

    $log_stmt = mysqli_prepare(
        $conn,
        "INSERT INTO log_workflow
            (aset_id, tindakan, status_workflow_dari, status_workflow_ke, catatan, oleh_pengguna_id)
         VALUES
            (?, 'Hantar Semula', ?, 2, 'Dihantar semula selepas pembetulan', ?)"
    );

    mysqli_stmt_bind_param($log_stmt, 'iii', $aset_id, $old_status, $pengguna_id);
    mysqli_stmt_execute($log_stmt);

    // OPERATION 3: Find all PPTM/PTM users with same wilayah_id AND status_pengguna_id = 1 (active)
    $pptm_stmt = mysqli_prepare(
        $conn,
        "SELECT p.pengguna_id
         FROM pengguna p
         JOIN peranan r ON p.peranan_id = r.peranan_id
         WHERE r.nama_peranan IN ('PPTM', 'PTM')
           AND p.wilayah_id = ?
           AND p.status_pengguna_id = 1"
    );


    mysqli_stmt_bind_param($pptm_stmt, 'i', $wilayah_id);
    mysqli_stmt_execute($pptm_stmt);
    $pptm_result = mysqli_stmt_get_result($pptm_stmt);

    $no_pendaftaran = $aset['no_pendaftaran'];
    $mesej = "Aset {$no_pendaftaran} telah dihantar semula untuk pengesahan.";
    $url = '/jdtis_asset/pages/pptm-ptm/lihat.php?id=' . intval($aset_id);


    while ($pptm = mysqli_fetch_assoc($pptm_result)) {
        $notif_stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifikasi
                (penerima_id, aset_id, jenis, mesej, url)
             VALUES
                (?, ?, 'aset_baru', ?, ?)"
        );

        $penerima_id = intval($pptm['pengguna_id']);
        $aset_id_for_notif = intval($aset_id);

        mysqli_stmt_bind_param($notif_stmt, 'iiss', $penerima_id, $aset_id_for_notif, $mesej, $url);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
    }

    mysqli_commit($conn);

    $_SESSION['flash_success'] = "Aset {$aset['no_pendaftaran']} berjaya dihantar semula untuk pengesahan.";
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['flash_error'] = "Ralat sistem. Sila cuba lagi.";
}

header('Location: index.php');
exit;
?>