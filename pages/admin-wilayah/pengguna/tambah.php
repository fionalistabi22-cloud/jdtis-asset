<?php
/**
 * Borang tambah pengguna untuk Admin Wilayah kini dihala ke
 * borang kanonik pages/pengguna/tambah.php supaya satu salinan
 * logik kaskad (Wilayah → Daerah → Agensi) dan validasi digunakan.
 *
 * Fail ini kekal sebagai titik masuk yang aktif (dirujuk oleh
 * pages/admin-wilayah/pengguna/index.php) tetapi hanya mengalihkan
 * ke borang kanonik selepas semakan autentikasi dan peranan.
 */

require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

if (!isLoggedIn() || isSessionExpired()) {
    header("Location: ../../login.php");
    exit;
}

if (($_SESSION['peranan'] ?? '') !== 'Admin Wilayah') {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

header("Location: ../../pengguna/tambah.php");
exit;
