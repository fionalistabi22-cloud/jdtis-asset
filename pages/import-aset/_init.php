<?php
/**
 * JTDIS - Bootstrap Modul Import Aset Pukal
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/functions_import.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (isSessionExpired()) {
    header('Location: ../login.php');
    exit;
}

requireRoleWhitelist(['Juruteknik', 'Agen IT', 'PID']);

$import_context = importGetUserContext($conn);

if (!$import_context) {
    $_SESSION['flash_error'] = 'Maklumat pengguna tidak dapat disahkan.';
    header('Location: ../dashboard.php');
    exit;
}

$import_routes = importRoleRoutes((string) $import_context['peranan']);

if (!importTablesAvailable($conn)) {
    header('Content-Type: text/html; charset=UTF-8');
    die(
        '<div style="font-family:Arial;padding:30px">' .
        '<h2>Jadual import belum dipasang</h2>' .
        '<p>Jalankan fail <code>sql/import_aset_pukal.sql</code> melalui phpMyAdmin terlebih dahulu.</p>' .
        '<a href="../dashboard.php">Kembali</a>' .
        '</div>'
    );
}
