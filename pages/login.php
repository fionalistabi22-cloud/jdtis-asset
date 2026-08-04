<?php
/**
 * JTDIS ASSET MANAGEMENT - HALAMAN LOG MASUK
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    if (isSessionExpired()) {
        session_destroy();
    } else {
        header("Location: dashboard.php");
        exit;
    }
}

$error = '';
$emel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Token tidak sah. Sila cuba semula.";
    } else {
        $emel = isset($_POST['emel']) ? sanitize($_POST['emel']) : '';
        $password = $_POST['password'] ?? '';

        if (empty($emel)) {
            $error = "Sila masukkan alamat emel";
        } elseif (empty($password)) {
            $error = "Sila masukkan kata laluan";
        } elseif (!isValidEmail($emel)) {
            $error = "Format emel tidak sah";
        } else {
            $query = "SELECT p.*, r.nama_peranan, r.tahap_hierarki
                      FROM pengguna p
                      JOIN peranan r ON p.peranan_id = r.peranan_id
                      WHERE p.emel = ? AND p.status_pengguna_id = 1";

            $stmt = mysqli_prepare($conn, $query);

            if (!$stmt) {
                $error = "Ralat sistem: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($stmt, "s", $emel);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) === 1) {
                    $user = mysqli_fetch_assoc($result);

                    if (verifyPassword($password, $user['kata_laluan_hash'])) {
                        session_regenerate_id(true);

                        setUserSession($conn, $user);
                        logActivity($conn, 'Log Masuk', 'Pengguna log masuk ke sistem');

                        $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                        unset($_SESSION['redirect_after_login']);

                        header("Location: $redirect");
                        exit;
                    } else {
                        $error = "Kata laluan tidak tepat";
                    }
                } else {
                    $error = "Emel tidak wujud atau akaun tidak aktif";
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
    <title>Log Masuk - Sistem Pengurusan Aset JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #5b5ce2;
            --secondary: #7c3aed;
            --accent: #22d3ee;
            --dark: #0f172a;
            --muted: #64748b;
            --surface: rgba(255, 255, 255, .92);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark);
            background:
                radial-gradient(circle at 8% 12%, rgba(34, 211, 238, .22), transparent 27%),
                radial-gradient(circle at 90% 88%, rgba(124, 58, 237, .28), transparent 31%),
                linear-gradient(135deg, #0f172a 0%, #1e1b4b 48%, #312e81 100%);
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(420px, .92fr);
        }

        /* PANEL KIRI */
        .visual-panel {
            position: relative;
            isolation: isolate;
            min-height: 100vh;
            padding: 42px 54px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            color: #fff;
        }

        .visual-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -3;
            opacity: .17;
            background-image:
                linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px);
            background-size: 34px 34px;
            mask-image: linear-gradient(to bottom, black, transparent 90%);
        }

        .visual-panel::after {
            content: "";
            position: absolute;
            width: 480px;
            height: 480px;
            right: -140px;
            top: 18%;
            z-index: -2;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(34,211,238,.25), transparent 68%);
            filter: blur(8px);
        }

        .brand-logo-desktop {
            display: block;
            width: min(150px, 50%);
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 14px 30px rgba(0, 0, 0, .28));
        }

        .visual-content {
            width: min(690px, 100%);
            margin: 22px auto;
        }

        .hero-title {
            max-width: 620px;
            margin: 0 auto 14px;
            font-size: clamp(2.1rem, 4vw, 4.1rem);
            line-height: 1.03;
            font-weight: 850;
            letter-spacing: -2px;
            text-align: center;
        }

        .hero-title span {
            color: var(--accent);
        }

        .hero-subtitle {
            margin: 0 auto 28px;
            color: #cbd5e1;
            text-align: center;
            font-size: 1rem;
        }

        /* GRAFIK CSS */
        .asset-graphic {
            position: relative;
            width: min(560px, 92%);
            aspect-ratio: 1.35 / 1;
            margin: 0 auto;
            display: grid;
            place-items: center;
        }

        .graphic-glow {
            position: absolute;
            inset: 15% 18%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(34,211,238,.28), rgba(91,92,226,.08) 60%, transparent 72%);
            filter: blur(10px);
        }

        .orbit {
            position: absolute;
            width: 76%;
            aspect-ratio: 1;
            border: 1px solid rgba(199,210,254,.25);
            border-radius: 50%;
            transform: rotate(-13deg);
            box-shadow:
                inset 0 0 28px rgba(34,211,238,.05),
                0 0 40px rgba(34,211,238,.05);
        }

        .orbit::before,
        .orbit::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 18px rgba(34,211,238,.85);
        }

        .orbit::before {
            width: 10px;
            height: 10px;
            top: 13%;
            left: 15%;
        }

        .orbit::after {
            width: 8px;
            height: 8px;
            right: 13%;
            bottom: 18%;
        }

        .device-card {
            position: relative;
            z-index: 3;
            width: 230px;
            padding: 24px 22px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 28px;
            background: linear-gradient(145deg, rgba(255,255,255,.14), rgba(255,255,255,.06));
            backdrop-filter: blur(18px);
            box-shadow:
                0 28px 70px rgba(0,0,0,.28),
                inset 0 1px 0 rgba(255,255,255,.18);
            text-align: center;
        }

        .device-icon {
            width: 78px;
            height: 78px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            border-radius: 24px;
            color: #fff;
            font-size: 2rem;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            box-shadow: 0 16px 36px rgba(34,211,238,.25);
        }

        .device-title {
            font-size: 1.25rem;
            font-weight: 800;
        }

        .device-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 10px;
            padding: 7px 11px;
            border-radius: 999px;
            color: #bbf7d0;
            background: rgba(34,197,94,.13);
            border: 1px solid rgba(74,222,128,.2);
            font-size: .78rem;
            font-weight: 700;
        }

        .device-status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 12px rgba(74,222,128,.9);
        }

        .graphic-node {
            position: absolute;
            z-index: 4;
            min-width: 112px;
            padding: 11px 13px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 16px;
            background: rgba(15,23,42,.72);
            backdrop-filter: blur(14px);
            box-shadow: 0 16px 34px rgba(0,0,0,.22);
            color: #e2e8f0;
            font-size: .78rem;
            font-weight: 700;
            animation: float 4.2s ease-in-out infinite;
        }

        .graphic-node i {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            flex: 0 0 32px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .node-register {
            top: 13%;
            left: 0;
        }

        .node-review {
            top: 12%;
            right: 0;
            animation-delay: .7s;
        }

        .node-report {
            bottom: 6%;
            left: 9%;
            animation-delay: 1.3s;
        }

        .node-secure {
            bottom: 4%;
            right: 8%;
            animation-delay: 2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .graphic-node {
                animation: none;
            }
        }

        .visual-footer {
            color: #94a3b8;
            font-size: .8rem;
        }

        /* PANEL KANAN */
        .form-panel {
            min-height: 100vh;
            padding: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.03);
        }

        .login-card {
            width: 100%;
            max-width: 500px;
            padding: 38px;
            border-radius: 28px;
            background: var(--surface);
            border: 1px solid rgba(255,255,255,.5);
            backdrop-filter: blur(24px);
            box-shadow: 0 30px 80px rgba(15,23,42,.35);
        }

        .mobile-brand {
            display: none;
            justify-content: center;
            width: 100%;
            margin-bottom: 25px;
            padding: 14px 18px;
            border-radius: 18px;
            background: linear-gradient(135deg, #172554, #0f172a);
        }

        .brand-logo-mobile {
            display: block;
            width: min(240px, 100%);
            height: auto;
            object-fit: contain;
        }

        .login-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 13px;
            padding: 7px 10px;
            border-radius: 999px;
            color: #4f46e5;
            background: #eef2ff;
            font-size: .78rem;
            font-weight: 800;
        }

        .login-title {
            margin-bottom: 7px;
            font-size: 2rem;
            font-weight: 850;
            letter-spacing: -.035em;
        }

        .login-subtitle {
            margin-bottom: 27px;
            color: var(--muted);
            line-height: 1.55;
        }

        .form-label {
            margin-bottom: 8px;
            color: #374151;
            font-weight: 700;
        }

        .input-shell {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            z-index: 2;
            transform: translateY(-50%);
            color: var(--secondary);
        }

        .form-control {
            min-height: 54px;
            padding: 13px 48px 13px 46px;
            border: 1px solid #dbe3ef;
            border-radius: 15px;
            background: rgba(255,255,255,.96);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(91,92,226,.12);
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            z-index: 2;
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            transform: translateY(-50%);
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #64748b;
        }

        .toggle-password:hover {
            color: var(--primary);
            background: #eef2ff;
        }

        .btn-login {
            width: 100%;
            min-height: 55px;
            border: 0;
            border-radius: 15px;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 15px 32px rgba(91,92,226,.28);
            transition: .2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 19px 38px rgba(91,92,226,.35);
        }

        .security-note {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            color: var(--muted);
            font-size: .82rem;
        }

        .alert {
            border: 0;
            border-radius: 14px;
        }

        @media (max-width: 1100px) {
            .visual-panel {
                padding-inline: 34px;
            }

            .graphic-node {
                min-width: 104px;
            }
        }

        @media (max-width: 992px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .visual-panel {
                display: none;
            }

            .form-panel {
                min-height: 100vh;
                padding: 22px;
            }

            .mobile-brand {
                display: flex;
            }
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 28px 22px;
                border-radius: 22px;
            }

            .login-title {
                font-size: 1.7rem;
            }
        }
    </style>
</head>

<body>
<main class="login-page">
    <section class="visual-panel">
        <img
            src="/jdtis_asset/assets/images/jtdi-logo.png"
            alt="Logo Jabatan Teknologi Digital dan Inovasi Negeri Sabah"
            class="brand-logo-desktop"
        >

        <div class="visual-content">
            <h1 class="hero-title">
                Urus aset. Jejak status. <span>Satu sistem.</span>
            </h1>

            <p class="hero-subtitle">
                Pendaftaran, pengesahan dan laporan aset ICT.
            </p>

            <div class="asset-graphic" aria-label="Grafik aliran pengurusan aset ICT">
                <div class="graphic-glow"></div>
                <div class="orbit"></div>

                <div class="graphic-node node-register">
                    <i class="bi bi-plus-square"></i>
                    <span>Daftar</span>
                </div>

                <div class="graphic-node node-review">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Semak</span>
                </div>

                <div class="graphic-node node-report">
                    <i class="bi bi-bar-chart"></i>
                    <span>Laporan</span>
                </div>

                <div class="graphic-node node-secure">
                    <i class="bi bi-shield-lock"></i>
                    <span>Selamat</span>
                </div>

                <div class="device-card">
                    <div class="device-icon">
                        <i class="bi bi-pc-display-horizontal"></i>
                    </div>

                    <div class="device-title">Aset ICT</div>
                    <div class="device-status">Sistem aktif</div>
                </div>
            </div>
        </div>

        <div class="visual-footer">
            © <?php echo date('Y'); ?> JTDIS Asset Management System
        </div>
    </section>

    <section class="form-panel">
        <div class="login-card">
            <div class="mobile-brand">
                <img
                    src="/jdtis_asset/assets/images/jtdi-logo.png"
                    alt="Logo Jabatan Teknologi Digital dan Inovasi Negeri Sabah"
                    class="brand-logo-mobile"
                >
            </div>

            <div class="login-kicker">
                <i class="bi bi-shield-check"></i>
                Akses rasmi
            </div>

            <h2 class="login-title">Log masuk</h2>
            <p class="login-subtitle">
                Gunakan akaun anda untuk meneruskan.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <?php echo escapeOutput($error); ?>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo $csrf_token; ?>"
                >

                <div class="mb-4">
                    <label for="emel" class="form-label">Alamat Emel</label>

                    <div class="input-shell">
                        <i class="bi bi-envelope input-icon"></i>

                        <input
                            type="email"
                            id="emel"
                            name="emel"
                            class="form-control"
                            value="<?php echo escapeOutput($emel); ?>"
                            placeholder="nama@jtdi.my"
                            autocomplete="email"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Kata Laluan</label>

                    <div class="input-shell">
                        <i class="bi bi-key input-icon"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Masukkan kata laluan"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            class="toggle-password"
                            type="button"
                            onclick="togglePassword()"
                            id="toggleBtn"
                            aria-label="Tunjuk atau sembunyikan kata laluan"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Log Masuk
                </button>
            </form>

            <div class="security-note">
                <i class="bi bi-lock-fill"></i>
                <span>Akses dilindungi dan direkodkan.</span>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const icon = document.querySelector('#toggleBtn i');
        const isHidden = passwordField.type === 'password';

        passwordField.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !isHidden);
        icon.classList.toggle('bi-eye-slash', isHidden);
    }
</script>
</body>
</html>