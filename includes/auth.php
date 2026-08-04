<?php
/**
 * JTDIS ASSET MANAGEMENT - FUNGSI AUTENTIKASI DAN KAWALAN AKSES
 * 
 * Modul ini mengendalikan:
 * - Semakan sesi pengguna
 * - Kawalan peranan berdasarkan akses
 * - Perlindungan halaman
 */

// Mulakan sesi jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Semak sama ada pengguna sudah log masuk
 * Kembali: boolean
 */
function isLoggedIn() {
    return isset($_SESSION['pengguna_id']) && !empty($_SESSION['pengguna_id']);
}

/**
 * Dapatkan data pengguna semasa dari sesi
 * Kembali: array atau null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'pengguna_id' => $_SESSION['pengguna_id'],
        'nama_penuh' => $_SESSION['nama_penuh'] ?? '',
        'emel' => $_SESSION['emel'] ?? '',
        'peranan_id' => $_SESSION['peranan_id'] ?? 0,
        'peranan' => $_SESSION['peranan'] ?? '',
        'wilayah_id' => $_SESSION['wilayah_id'] ?? 0,
        'agensi_id' => $_SESSION['agensi_id'] ?? 0,
        'tahap_hierarki' => $_SESSION['tahap_hierarki'] ?? 0
    ];
}

/**
 * Semak sama ada pengguna mempunyai peranan tertentu
 * Parameter: $required_role = nama peranan atau array nama peranan
 * Kembali: boolean
 */
function hasRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['peranan'] ?? '';
    
    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    }
    
    return $user_role === $required_role;
}

/**
 * Semak sama ada pengguna mempunyai akses minimum hierarki
 * Tahap Hierarki: 1=Super Admin, 2=Admin Wilayah, 3=PPTM/PTM, 4=Ketua, 5=Pengarah, 6=Juruteknik
 * Parameter: $min_level = tahap minimum yang diperlukan
 * Kembali: boolean
 */
function hasMinimumLevel($min_level) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_level = $_SESSION['tahap_hierarki'] ?? 0;
    return $current_level <= $min_level;
}

/**
 * Hantar pengguna ke halaman log masuk memakai laluan kanonik.
 * Guna APP_URL (BASE_URL) apabila tersedia, jika tidak fallback ke laluan mutlak.
 * Kembali: void (redirect & keluar)
 */
function loginRedirect(): void {
    $base = defined('APP_URL') ? APP_URL : '/jdtis_asset';
    header('Location: ' . $base . '/pages/login.php');
    exit;
}

/**
 * Lindungi halaman - redirect ke login jika belum log masuk
 * Parameter: $required_role = peranan yang diperlukan (opsional)
 * Kembali: void (redirect atau keluar)
 *
 * Tapak sepunya untuk semua semakan autentikasi DAN tempoh sesi.
 * Sesi yang telah tamat (expiry_time) akan dimusnahkan dan dihantar
 * semula ke halaman log masuk di sini, supaya semua halaman yang
 * memakai requireLogin()/requireRoleWhitelist()/sekatAksesAset()
 * mendapat penguatkuasaan tamat sesi secara konsisten.
 */
function requireLogin($required_role = null) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        loginRedirect();
    }

    // Sesi tamat tempoh — musnahkan sesi dan hantar ke log masuk.
    if (isSessionExpired()) {
        unset($_SESSION['redirect_after_login']);
        loginRedirect();
    }

    // Jika peranan diperlukan, semak
    if ($required_role !== null && !hasRole($required_role)) {
        header("HTTP/1.0 403 Forbidden");
        die("Anda tidak mempunyai akses ke halaman ini.");
    }
}

/**
 * Lindungi berdasarkan tahap hierarki
 * Parameter: $min_level = tahap minimum
 * Kembali: void (redirect atau keluar)
 */
function requireLevel($min_level) {
    if (!isLoggedIn()) {
        header("Location: ../pages/login.php");
        exit;
    }
    
    if (!hasMinimumLevel($min_level)) {
        header("HTTP/1.0 403 Forbidden");
        die("Anda tidak mempunyai kebenaran untuk mengakses halaman ini.");
    }
}

/**
 * Dapatkan peranan pengguna dari database berdasarkan peranan_id
 * Parameter: $conn = sambungan MySQL, $peranan_id = ID peranan
 * Kembali: array atau false
 */
function getRoleById($conn, $peranan_id) {
    $query = "SELECT * FROM peranan WHERE peranan_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $peranan_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Set data sesi pengguna selepas log masuk berjaya
 * Parameter: $conn = sambungan MySQL, $user_data = data pengguna dari DB
 * Kembali: void
 */
function setUserSession($conn, $user_data) {
    $_SESSION['pengguna_id'] = $user_data['pengguna_id'];
    $_SESSION['nama_penuh'] = $user_data['nama_penuh'];
    $_SESSION['emel'] = $user_data['emel'];
    $_SESSION['peranan_id'] = $user_data['peranan_id'];
    $_SESSION['wilayah_id'] = $user_data['wilayah_id'];
    $_SESSION['agensi_id'] = $user_data['agensi_id'];
    
    // Ambil nama peranan dan tahap hierarki
    $role = getRoleById($conn, $user_data['peranan_id']);
    if ($role) {
        $_SESSION['peranan'] = $role['nama_peranan'];
        $_SESSION['tahap_hierarki'] = $role['tahap_hierarki'];
    }
    
    // Set masa tamat sesi (1 jam)
    $_SESSION['expiry_time'] = time() + (3600);
}

/**
 * Semak masa tamat sesi
 * Kembali: boolean
 */
function isSessionExpired() {
    if (!isset($_SESSION['expiry_time'])) {
        return false;
    }
    
    if (time() > $_SESSION['expiry_time']) {
        session_destroy();
        return true;
    }
    
    // Lanjutkan masa tamat
    $_SESSION['expiry_time'] = time() + 3600;
    return false;
}

/**
 * Catat aktiviti pengguna ke dalam log
 * Parameter: $conn = sambungan MySQL, $jenis_aktiviti, $keterangan, $aset_id (opsional)
 * Kembali: boolean
 */
function logActivity($conn, $jenis_aktiviti, $keterangan = '', $aset_id = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $pengguna_id = $_SESSION['pengguna_id'];
    $nama_pengguna = $_SESSION['nama_penuh'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $tindakan = 'INSERT';
    $jadual = 'sistem';
    $rekod_id = $aset_id;

    if (stripos($jenis_aktiviti, 'Kemaskini') !== false || stripos($jenis_aktiviti, 'Edit') !== false) {
        $tindakan = 'UPDATE';
    } elseif (stripos($jenis_aktiviti, 'Hapus') !== false || stripos($jenis_aktiviti, 'Hapuskan') !== false) {
        $tindakan = 'DELETE';
    } elseif (stripos($jenis_aktiviti, 'Log Masuk') !== false) {
        $tindakan = 'LOGIN';
    } elseif (stripos($jenis_aktiviti, 'Log Keluar') !== false) {
        $tindakan = 'LOGOUT';
    }

    if (stripos($jenis_aktiviti, 'Pengguna') !== false) {
        $jadual = 'pengguna';
    } elseif (stripos($jenis_aktiviti, 'Wilayah') !== false) {
        $jadual = 'wilayah';
    } elseif (stripos($jenis_aktiviti, 'Daerah') !== false) {
        $jadual = 'daerah';
    } elseif (stripos($jenis_aktiviti, 'Agensi') !== false) {
        $jadual = 'agensi';
    } elseif (stripos($jenis_aktiviti, 'Aset') !== false) {
        $jadual = 'aset';
    } elseif (stripos($jenis_aktiviti, 'Kata Laluan') !== false) {
        $jadual = 'pengguna';
    }

    $query = "INSERT INTO log_audit (pengguna_id, nama_pengguna, jadual, tindakan, rekod_id, data_lama, data_baru, ip_address, tarikh) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    try {
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            return false;
        }

        $data_lama = null;
        $data_baru = $keterangan;

        // Always bind the audit metadata; if a record id is not available it stays null.
        mysqli_stmt_bind_param($stmt, "isssisss", $pengguna_id, $nama_pengguna, $jadual, $tindakan, $rekod_id, $data_lama, $data_baru, $ip_address);

        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    } catch (mysqli_sql_exception $e) {
        // Log table is optional; never block authentication if it is missing.
        return false;
    }
}

/**
 * Hash kata laluan menggunakan bcrypt
 * Parameter: $password = kata laluan biasa
 * Kembali: string (hash)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Sahkan kata laluan
 * Parameter: $password = kata laluan biasa, $hash = hash tersimpan
 * Kembali: boolean
 */
function verifyPassword($password, $hash) {
    if (password_verify($password, $hash)) {
        return true;
    }

    $hash_info = password_get_info($hash);
    if (($hash_info['algo'] ?? 0) === 0) {
        return hash_equals((string) $hash, (string) $password);
    }

    return false;
}

/**
 * Log keluar pengguna
 * Kembali: void
 */
function logout() {
    session_destroy();
    header("Location: login.php");
    exit;
}


/* ═══════════════════════════════════════════════════════════
 * FUNGSI TAMBAHAN — kawalan akses modul aset & skop wilayah
 * ═══════════════════════════════════════════════════════════ */

/**
 * Senarai peranan yang TIDAK boleh akses modul aset.
 * Super Admin dan Admin Wilayah hanya urus pengguna & data induk.
 */
function getRolesTanpaAksesAset() {
    return ['Super Admin', 'Admin Wilayah'];
}

/**
 * Sekat akses ke modul aset untuk Super Admin & Admin Wilayah.
 * Letak fungsi ini di ATAS setiap halaman dalam folder pages/aset/
 * 
 * Kembali: void (redirect jika role tidak dibenarkan)
 */
function sekatAksesAset() {
    requireLogin();

    $peranan = $_SESSION['peranan'] ?? '';

    if (in_array($peranan, getRolesTanpaAksesAset())) {
        header("Location: /jdtis_asset/pages/dashboard.php");
        exit;
    }
}

/**
 * Semakan role eksplisit (whitelist) — lebih selamat berbanding
 * hasMinimumLevel() kerana tidak bergantung kepada logik nombor songsang.
 * 
 * Parameter: $roles_dibenarkan = array nama peranan yang dibenarkan
 * Contoh: requireRoleWhitelist(['Juruteknik', 'Agen IT', 'PID']);
 * 
 * Kembali: void (redirect/403 jika tidak dibenarkan)
 */
function requireRoleWhitelist($roles_dibenarkan) {
    requireLogin();

    $peranan = $_SESSION['peranan'] ?? '';

    if (!in_array($peranan, $roles_dibenarkan)) {
        header("HTTP/1.0 403 Forbidden");
        die("Anda tidak mempunyai akses ke halaman ini.");
    }
}

/**
 * Semak sama ada pengguna boleh akses data dalam wilayah tertentu.
 * Guna ini SEBELUM papar atau proses sebarang data aset yang 
 * terikat kepada wilayah_id.
 * 
 * Parameter: $wilayah_id_data = wilayah_id rekod yang cuba diakses
 * Kembali: boolean
 */
function bolehAksesWilayah($wilayah_id_data) {
    $peranan = $_SESSION['peranan'] ?? '';
    $wilayah_pengguna = $_SESSION['wilayah_id'] ?? 0;

    // Pengarah dan Ketua Bahagian boleh akses semua wilayah
    if (in_array($peranan, ['Pengarah', 'Ketua Bahagian'])) {
        return true;
    }

    // Role lain (Juruteknik, Agen IT, PPTM, Ketua Wilayah) 
    // hanya boleh akses wilayah sendiri
    return (int)$wilayah_pengguna === (int)$wilayah_id_data;
}

/**
 * Sekat akses jika wilayah data tidak sepadan dengan wilayah pengguna.
 * Letak ini selepas anda fetch data aset/rekod dari DB, sebelum 
 * memaparkannya kepada pengguna.
 * 
 * Parameter: $wilayah_id_data = wilayah_id rekod yang cuba diakses
 * Kembali: void (403 jika tidak dibenarkan)
 */
function semakSkopWilayah($wilayah_id_data) {
    if (!bolehAksesWilayah($wilayah_id_data)) {
        header("HTTP/1.0 403 Forbidden");
        die("Anda tidak mempunyai akses ke data wilayah ini.");
    }
}

/**
 * Tambah clause WHERE wilayah_id ke dalam query SQL secara automatik
 * berdasarkan peranan pengguna. Guna ini dalam halaman senarai/dashboard
 * supaya setiap query secara konsisten disekat mengikut skop.
 * 
 * Parameter: $alias_jadual = alias jadual dalam query (contoh: "a" untuk "aset a")
 * Kembali: string (klausa SQL kosong jika tiada sekatan, atau "AND a.wilayah_id = X")
 */
function klausaSkopWilayah($alias_jadual = '') {
    $peranan = $_SESSION['peranan'] ?? '';
    $wilayah_pengguna = (int)($_SESSION['wilayah_id'] ?? 0);

    // Pengarah & Ketua Bahagian — tiada sekatan, nampak semua
    if (in_array($peranan, ['Pengarah', 'Ketua Bahagian'])) {
        return '';
    }

    $prefix = $alias_jadual ? "{$alias_jadual}." : '';
    return " AND {$prefix}wilayah_id = {$wilayah_pengguna} ";
}