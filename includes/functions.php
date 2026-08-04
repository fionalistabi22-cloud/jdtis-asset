<?php
/**
 * JTDIS ASSET MANAGEMENT - FUNGSI PEMBANTU UMUM
 * 
 * Modul ini mengandungi fungsi-fungsi utiliti untuk:
 * - Sanitasi dan validasi data
 * - Format paparan
 * - Query pangkalan data yang kerap digunakan
 */

/**
 * Sanitasi input dari pengguna
 * Parameter: $input = data yang hendak disanitasi
 * Kembali: string (bersih)
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitasi untuk output HTML
 * Parameter: $text = teks yang hendak disanitasi
 * Kembali: string (bersih untuk HTML)
 */
function escapeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Validasi emel
 * Parameter: $emel = alamat emel
 * Kembali: boolean
 */
function isValidEmail($emel) {
    return filter_var($emel, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validasi no pendaftaran aset (unik)
 * Parameter: $conn = sambungan MySQL, $no_pendaftaran, $aset_id (opsional untuk edit)
 * Kembali: boolean
 */
function isValidAssetNo($conn, $no_pendaftaran, $aset_id = null) {
    $query = "SELECT COUNT(*) as count FROM aset WHERE no_pendaftaran = ?";
    
    if ($aset_id !== null) {
        $query .= " AND aset_id != ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $no_pendaftaran, $aset_id);
    } else {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $no_pendaftaran);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] == 0;
}

/**
 * Format tarikh untuk paparan (Melayu)
 * Parameter: $date = tarikh dari database (YYYY-MM-DD)
 * Kembali: string (DD/MM/YYYY)
 */
function formatDate($date) {
    if (empty($date)) return '-';
    $date = new DateTime($date);
    return $date->format('d/m/Y');
}

/**
 * Format tarikh dan masa
 * Parameter: $datetime = tarikh dan masa dari database
 * Kembali: string (DD/MM/YYYY HH:MM)
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    $date = new DateTime($datetime);
    return $date->format('d/m/Y H:i');
}

/**
 * Format mata wang (Ringgit Malaysia)
 * Parameter: $amount = jumlah wang
 * Kembali: string (RM X,XXX.XX)
 */
function formatCurrency($amount) {
    return 'RM ' . number_format($amount, 2, '.', ',');
}

/**
 * Dapatkan nama status aset dengan warna
 * Parameter: $conn = sambungan MySQL, $status_id
 * Kembali: array (status, warna) atau null
 */
function getAssetStatus($conn, $status_id) {
    $query = "SELECT status, warna FROM status_aset WHERE status_aset_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $status_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Dapatkan nama wilayah
 * Parameter: $conn = sambungan MySQL, $wilayah_id
 * Kembali: string (nama wilayah) atau null
 */
function getWilayahName($conn, $wilayah_id) {
    if (empty($wilayah_id)) return '-';
    $query = "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $wilayah_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['nama_wilayah'] : '-';
}

/**
 * Dapatkan nama agensi
 * Parameter: $conn = sambungan MySQL, $agensi_id
 * Kembali: string (nama agensi) atau null
 */
function getAgensiName($conn, $agensi_id) {
    if (empty($agensi_id)) return '-';
    $query = "SELECT nama_agensi FROM agensi WHERE agensi_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $agensi_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['nama_agensi'] : '-';
}

/**
 * Dapatkan nama pengguna
 * Parameter: $conn = sambungan MySQL, $pengguna_id
 * Kembali: string (nama penuh) atau null
 */
function getUserName($conn, $pengguna_id) {
    if (empty($pengguna_id)) return '-';
    $query = "SELECT nama_penuh FROM pengguna WHERE pengguna_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $pengguna_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['nama_penuh'] : '-';
}

/**
 * Hitung jumlah aset berdasarkan status
 * Parameter: $conn = sambungan MySQL, $status_id
 * Kembali: integer
 */
function countAssetByStatus($conn, $status_id) {
    $query = "SELECT COUNT(*) as count FROM aset WHERE status_aset_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $status_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

/**
 * Hitung jumlah aset menunggu pengesahan
 * Parameter: $conn = sambungan MySQL
 * Kembali: integer
 */
function countPendingApproval($conn) {
    $query = "SELECT COUNT(*) as count FROM pengesahan_aset WHERE status_pengesahan = 'Menunggu'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

/**
 * Hasilkan nombor pendaftaran aset secara automatik
 * Format: ASET-YYYYMMDD-XXXX (XXXX = urutan harian)
 * Parameter: $conn = sambungan MySQL
 * Kembali: string (no pendaftaran)
 */
function generateAssetNo($conn) {
    $prefix = 'ASET-' . date('Ymd');
    $query = "SELECT COUNT(*) as count FROM aset WHERE no_pendaftaran LIKE ?";
    
    $search = $prefix . '%';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $sequence = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $sequence;
}

/**
 * Hantar emel (placeholder - guna SMTP sebenar di produksi)
 * Parameter: $to, $subject, $message
 * Kembali: boolean
 */
function sendEmail($to, $subject, $message) {
    // TODO: Guna SMTP sebenar seperti PHPMailer atau SwiftMailer
    // Untuk sekarang, hanya rekodkan
    error_log("Email to: $to, Subject: $subject");
    return true;
}

/**
 * Hantar notifikasi dalam sistem
 * Parameter: $conn = sambungan MySQL, $pengguna_id_penerima, $jenis, $keterangan
 * Kembali: boolean
 */
function sendNotification($conn, $pengguna_id_penerima, $jenis, $keterangan) {
    // TODO: Buat jadual notifikasi terlebih dahulu
    // Untuk sekarang, hanya return true
    return true;
}

/**
 * Pengesahan data input borang
 * Parameter: $data = array, $rules = array peraturan validasi
 * Kembali: array (errors atau kosong jika sah)
 */
function validateForm($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Semak required
        if (strpos($rule, 'required') !== false && empty($value)) {
            $errors[$field] = "Medan ini diperlukan";
            continue;
        }
        
        // Semak email
        if (strpos($rule, 'email') !== false && !empty($value)) {
            if (!isValidEmail($value)) {
                $errors[$field] = "Format emel tidak sah";
            }
        }
        
        // Semak min length
        if (preg_match('/min:(\d+)/', $rule, $matches)) {
            if (strlen($value) < $matches[1]) {
                $errors[$field] = "Sekurang-kurangnya " . $matches[1] . " aksara";
            }
        }
        
        // Semak max length
        if (preg_match('/max:(\d+)/', $rule, $matches)) {
            if (strlen($value) > $matches[1]) {
                $errors[$field] = "Maksimum " . $matches[1] . " aksara";
            }
        }
    }
    
    return $errors;
}

/**
 * Cipta CSRF token
 * Kembali: string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Sahkan CSRF token
 * Parameter: $token = token dari borang
 * Kembali: boolean
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Paginasi - hitung offset
 * Parameter: $current_page, $per_page
 * Kembali: integer (offset)
 */
function getPaginationOffset($current_page = 1, $per_page = 20) {
    $page = max(1, intval($current_page));
    return ($page - 1) * $per_page;
}

/**
 * Paginasi - hitung jumlah halaman
 * Parameter: $total_records, $per_page
 * Kembali: integer
 */
function getTotalPages($total_records, $per_page = 20) {
    return ceil($total_records / $per_page);
}

// Backward-compatible safeguards: pastikan fungsi penting wujud walaupun ada edit/paste sebelumnya.
if (!function_exists('getPaginationOffset')) {
    function getPaginationOffset($page, $per_page) {
        return ($page - 1) * $per_page;
    }
}

if (!function_exists('getTotalPages')) {
    function getTotalPages($total, $per_page) {
        if ($per_page <= 0) return 1;
        return (int)ceil($total / $per_page);
    }
}

if (!function_exists('getWilayahName')) {
    function getWilayahName($conn, $wilayah_id) {
        $stmt = mysqli_prepare($conn, "SELECT nama_wilayah FROM wilayah WHERE wilayah_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $wilayah_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return $row['nama_wilayah'] ?? '-';
    }
}


?>


