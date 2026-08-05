<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Protect page
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

if (!in_array($_SESSION['peranan'], ['Super Admin', 'Admin Wilayah'])) {
    die("Anda tidak mempunyai akses ke halaman ini.");
}

$error = '';
$success = '';
$current_role = $_SESSION['peranan'];
$current_wilayah = (int) ($_SESSION['wilayah_id'] ?? 0);
$current_tahap = (int) ($_SESSION['tahap_hierarki'] ?? 99);

// Skop lokasi peranan. Nilai peranan_id adalah dari jadual peranan.
// - GLOBAL: lokasi boleh NULL (Super Admin, Pengarah, Ketua Bahagian).
// - WILAYAH: mesti ada wilayah yang sah.
// - AGENSI: mesti ada wilayah, daerah dan agensi yang sah (PID).
$GLOBAL_ROLES = [1, 7, 8]; // Super Admin, Ketua Bahagian, Pengarah
$AGENCY_ROLES = [10];      // PID

// Get all roles
$roles_query = "SELECT * FROM peranan ORDER BY tahap_hierarki ASC";
$roles_result = mysqli_query($conn, $roles_query);
$roles = mysqli_fetch_all($roles_result, MYSQLI_ASSOC);

// Get wilayah (Super Admin sees full list; Admin Wilayah is locked to session)
if ($current_role === 'Admin Wilayah') {
    $wilayah_query = "SELECT * FROM wilayah WHERE wilayah_id = ? ORDER BY nama_wilayah ASC";
    $wilayah_stmt = mysqli_prepare($conn, $wilayah_query);
    mysqli_stmt_bind_param($wilayah_stmt, 'i', $current_wilayah);
    mysqli_stmt_execute($wilayah_stmt);
    $wilayah_result = mysqli_stmt_get_result($wilayah_stmt);
    $wilayah_list = mysqli_fetch_all($wilayah_result, MYSQLI_ASSOC);
} else {
    $wilayah_query = "SELECT * FROM wilayah ORDER BY nama_wilayah ASC";
    $wilayah_result = mysqli_query($conn, $wilayah_query);
    $wilayah_list = mysqli_fetch_all($wilayah_result, MYSQLI_ASSOC);
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak sah";
    } else {
$nama_penuh = sanitize($_POST['nama_penuh'] ?? '');
        $emel = sanitize($_POST['emel'] ?? '');
        $peranan_id = intval($_POST['peranan_id'] ?? 0);
        $daerah_id = intval($_POST['daerah_id'] ?? 0);
        $agensi_id = intval($_POST['agensi_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Admin Wilayah DIIKUNCI kepada wilayah sesi. Nilai dihantar diabaikan.
        if ($current_role === 'Admin Wilayah') {
            $wilayah_id = $current_wilayah;
        } else {
            $wilayah_id = intval($_POST['wilayah_id'] ?? 0);
        }

        // Basic validation
        if (empty($nama_penuh)) {
            $error = "Nama penuh diperlukan";
        } elseif (empty($emel)) {
            $error = "Emel diperlukan";
        } elseif (!isValidEmail($emel)) {
            $error = "Format emel tidak sah";
        } elseif (empty($password)) {
            $error = "Kata laluan diperlukan";
        } elseif (strlen($password) < 6) {
            $error = "Kata laluan mesti sekurang-kurangnya 6 karakter";
        } elseif ($password !== $password_confirm) {
            $error = "Kata laluan tidak sepadan";
        } elseif ($peranan_id === 0) {
            $error = "Pilih peranan";
        } else {
            // ── Penguatkuasaan peranan di sisi pelayan (bukan hanya HTML) ──
            $role_query = "SELECT peranan_id, nama_peranan, tahap_hierarki FROM peranan WHERE peranan_id = ? LIMIT 1";
            $role_stmt = mysqli_prepare($conn, $role_query);
            mysqli_stmt_bind_param($role_stmt, 'i', $peranan_id);
            mysqli_stmt_execute($role_stmt);
            $role_result = mysqli_stmt_get_result($role_stmt);
            $role_row = mysqli_fetch_assoc($role_result);
            mysqli_stmt_close($role_stmt);

if (!$role_row) {
                $error = "Peranan tidak sah";
            } elseif ($current_role === 'Admin Wilayah' && in_array($peranan_id, $GLOBAL_ROLES, true)) {
                // Admin Wilayah tidak boleh menetapkan peranan global/kurator
                // (Super Admin, Ketua Bahagian, Pengarah).
                $error = "Anda tidak dibenarkan menetapkan peranan ini";
            } else {
                // ── Validasi lokasi mengikut skop peranan ──
                $is_global = in_array($peranan_id, $GLOBAL_ROLES, true);
                $is_agency = in_array($peranan_id, $AGENCY_ROLES, true);

                if (!$is_global) {
                    // Peranan skop wilayah mesti ada wilayah yang sah.
                    if ($wilayah_id <= 0) {
                        $error = "Wilayah diperlukan untuk peranan ini";
                    } elseif ($is_agency && $daerah_id <= 0) {
                        $error = "Daerah diperlukan untuk peranan agensi";
                    } elseif ($is_agency && $agensi_id <= 0) {
                        $error = "Agensi diperlukan untuk peranan agensi";
                    }
                }

// Validasi silang: agensi memerlukan daerah & wilayah yang sah.
                if ($error === '' && $agensi_id > 0) {
                    if ($daerah_id <= 0) {
                        $error = "Agensi memerlukan daerah dipilih";
                    } elseif ($wilayah_id <= 0) {
                        $error = "Agensi memerlukan wilayah dipilih";
                    } else {
                        // Wilayah agensi diterbitkan melalui hubungan daerah.
                        $agensi_query = "SELECT a.agensi_id
                                         FROM agensi a
                                         INNER JOIN daerah d
                                             ON d.daerah_id = a.daerah_id
                                         WHERE a.agensi_id = ?
                                           AND a.daerah_id = ?
                                           AND d.wilayah_id = ?
                                         LIMIT 1";
                        $agensi_stmt = mysqli_prepare($conn, $agensi_query);
                        mysqli_stmt_bind_param($agensi_stmt, 'iii', $agensi_id, $daerah_id, $wilayah_id);
                        mysqli_stmt_execute($agensi_stmt);
                        $agensi_result = mysqli_stmt_get_result($agensi_stmt);
                        $agensi_row = mysqli_fetch_assoc($agensi_result);
                        mysqli_stmt_close($agensi_stmt);

                        if (!$agensi_row) {
                            $error = "Agensi tidak berada dalam daerah yang dipilih";
                        }
                    }
                }

                // Validasi silang: daerah memerlukan wilayah yang sah.
                if ($error === '' && $daerah_id > 0) {
                    if ($wilayah_id <= 0) {
                        $error = "Daerah memerlukan wilayah dipilih";
                    } else {
                        $daerah_query = "SELECT daerah_id FROM daerah WHERE daerah_id = ? AND wilayah_id = ? LIMIT 1";
                        $daerah_stmt = mysqli_prepare($conn, $daerah_query);
                        mysqli_stmt_bind_param($daerah_stmt, 'ii', $daerah_id, $wilayah_id);
                        mysqli_stmt_execute($daerah_stmt);
                        $daerah_result = mysqli_stmt_get_result($daerah_stmt);
                        $daerah_row = mysqli_fetch_assoc($daerah_result);
                        mysqli_stmt_close($daerah_stmt);

                        if (!$daerah_row) {
                            $error = "Daerah tidak berada dalam wilayah yang dipilih";
                        }
                    }
                }

                if ($error === '') {
                    // Check if email already exists
                    $check_query = "SELECT COUNT(*) as count FROM pengguna WHERE emel = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "s", $emel);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);

                    if ($check_row['count'] > 0) {
                        $error = "Emel ini sudah didaftar";
                    } else {
                        if ($wilayah_id === 0) {
                            $wilayah_id = null;
                        }
                        if ($daerah_id === 0) {
                            $daerah_id = null;
                        }
                        if ($agensi_id === 0) {
                            $agensi_id = null;
                        }

                        // Hash password
                        $password_hash = hashPassword($password);

// Insert user
                        $insert_query = "INSERT INTO pengguna (nama_penuh, emel, kata_laluan_hash, peranan_id, wilayah_id, daerah_id, agensi_id, status_pengguna_id)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "sssiiii", $nama_penuh, $emel, $password_hash, $peranan_id, $wilayah_id, $daerah_id, $agensi_id);
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $success = "Pengguna berjaya ditambah";
                            logActivity($conn, 'Tambah Pengguna', "Pengguna baru ditambah: $emel ($nama_penuh)");

                            // Redirect after 2 seconds
                            header("Refresh: 2; url=index.php");
                        } else {
                            $error = "Ralat: " . mysqli_error($conn);
                        }
                    }
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
    <title>Tambah Pengguna - JTDIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; color: white; }
        .nav-link { color: rgba(255,255,255,0.8); }
        .nav-link:hover, .nav-link.active { color: white; background: rgba(0,0,0,0.2); border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 sidebar p-4">
                <h4 class="mb-4"><i class="bi bi-shield-check"></i> JTDIS</h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
                    <a class="nav-link active" href="index.php"><i class="bi bi-people"></i> Pengguna</a>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Log Keluar</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 p-4">
                <div class="mb-4">
                    <a href="index.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <h2><i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru</h2>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo escapeOutput($success); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nama_penuh" class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                                        <input type="text" id="nama_penuh" name="nama_penuh" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="emel" class="form-label">Emel <span class="text-danger">*</span></label>
                                        <input type="email" id="emel" name="emel" class="form-control" required>
                                    </div>
                                </div>
                            </div>

<div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="peranan_id" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select id="peranan_id" name="peranan_id" class="form-select" required>
                                            <option value="">-- Pilih Peranan --</option>
<?php foreach ($roles as $role): ?>
                                                <?php
                                                // Paparan: Admin Wilayah tidak boleh menetapkan peranan global
                                                // (Super Admin, Ketua Bahagian, Pengarah).
                                                if ($current_role === 'Admin Wilayah' && in_array((int) $role['peranan_id'], $GLOBAL_ROLES, true)) {
                                                    continue;
                                                }
                                                ?>
                                                <option value="<?php echo $role['peranan_id']; ?>">
                                                    <?php echo escapeOutput($role['nama_peranan']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="wilayah_id" class="form-label">Wilayah</label>
                                        <?php if ($current_role === 'Admin Wilayah'): ?>
                                            <!-- Paparan sahaja (disabled). Backend guna $_SESSION['wilayah_id']. -->
                                            <select id="wilayah_id" name="wilayah_id" class="form-select" disabled>
                                                <?php foreach ($wilayah_list as $w): ?>
                                                    <option value="<?php echo $w['wilayah_id']; ?>" selected>
                                                        <?php echo escapeOutput($w['nama_wilayah']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Wilayah dikunci untuk Admin Wilayah</small>
                                        <?php else: ?>
                                            <select id="wilayah_id" name="wilayah_id" class="form-select">
                                                <option value="0">-- Tidak Ditugaskan --</option>
                                                <?php foreach ($wilayah_list as $w): ?>
                                                    <option value="<?php echo $w['wilayah_id']; ?>">
                                                        <?php echo escapeOutput($w['nama_wilayah']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="daerah_id" class="form-label">Daerah</label>
                                        <select id="daerah_id" name="daerah_id" class="form-select">
                                            <option value="0">-- Tidak Ditugaskan --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="agensi_id" class="form-label">Agensi</label>
                                        <select id="agensi_id" name="agensi_id" class="form-select" disabled>
                                            <option value="0">-- Sila Pilih Daerah Dahulu --</option>
                                        </select>
                                        <small class="text-muted">Agensi dipaparkan selepas daerah dipilih</small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Kata Laluan <span class="text-danger">*</span></label>
                                        <input type="password" id="password" name="password" class="form-control" required placeholder="Min 6 karakter">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password_confirm" class="form-label">Sahkan Kata Laluan <span class="text-danger">*</span></label>
                                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> Simpan Pengguna
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-lg"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Kaskad dinamik Wilayah → Daerah → Agensi melalui endpoint JSON.
        const apiBase = 'api_cascading.php';
        const isAdminWilayah = <?php echo $current_role === 'Admin Wilayah' ? 'true' : 'false'; ?>;
        const fixedWilayahId = <?php echo (int) $current_wilayah; ?>;

        const wilayahSelect = document.getElementById('wilayah_id');
        const daerahSelect = document.getElementById('daerah_id');
        const agensiSelect = document.getElementById('agensi_id');

        const NO_DAERAH = '-- Tidak Ditugaskan --';
        const NO_AGENSI = '-- Tidak Ditugaskan --';
        const PICK_DAERAH = '-- Sila Pilih Daerah Dahulu --';
        const EMPTY_DAERAH = 'Tiada Agensi Dalam Daerah Ini';

        function currentWilayahId() {
            if (isAdminWilayah) {
                return fixedWilayahId;
            }
            return Number(wilayahSelect.value || 0);
        }

        function setOptions(select, rows, valueKey, labelKey, emptyLabel) {
            select.innerHTML = '<option value="0">' + emptyLabel + '</option>';
            rows.forEach(function (row) {
                const option = document.createElement('option');
                option.value = row[valueKey];
                option.textContent = row[labelKey];
                select.appendChild(option);
            });
        }

        function setAgensiEmpty() {
            agensiSelect.innerHTML = '<option value="0">' + EMPTY_DAERAH + '</option>';
            agensiSelect.disabled = true;
        }

        function loadDaerah(wilayahId) {
            daerahSelect.innerHTML = '<option value="0">' + NO_DAERAH + '</option>';
            agensiSelect.innerHTML = '<option value="0">' + PICK_DAERAH + '</option>';
            agensiSelect.disabled = true;

            if (wilayahId <= 0) {
                return;
            }

            fetch(apiBase + '?type=daerah&wilayah_id=' + wilayahId)
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Gagal memuatkan daerah');
                    }
                    setOptions(daerahSelect, payload.data || [], 'daerah_id', 'nama_daerah', NO_DAERAH);
                })
                .catch(function () {
                    daerahSelect.innerHTML = '<option value="0">' + NO_DAERAH + '</option>';
                });
        }

        function loadAgensi(wilayahId, daerahId) {
            agensiSelect.innerHTML = '<option value="0">' + PICK_DAERAH + '</option>';
            agensiSelect.disabled = true;

            if (wilayahId <= 0 || daerahId <= 0) {
                return;
            }

            fetch(apiBase + '?type=agensi&wilayah_id=' + wilayahId + '&daerah_id=' + daerahId)
                .then(function (res) { return res.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Gagal memuatkan agensi');
                    }
                    const rows = payload.data || [];
                    if (rows.length === 0) {
                        setAgensiEmpty();
                        return;
                    }
                    setOptions(agensiSelect, rows, 'agensi_id', 'nama_agensi', NO_AGENSI);
                    agensiSelect.disabled = false;
                })
                .catch(function () {
                    setAgensiEmpty();
                });
        }

        if (!isAdminWilayah) {
            wilayahSelect.addEventListener('change', function () {
                loadDaerah(Number(this.value || 0));
            });
        }

        daerahSelect.addEventListener('change', function () {
            loadAgensi(currentWilayahId(), Number(this.value || 0));
        });

        // Pada muatan permulaan, muat daerah untuk wilayah tetap/dipilih.
        loadDaerah(currentWilayahId());
    </script>
</body>
</html>
