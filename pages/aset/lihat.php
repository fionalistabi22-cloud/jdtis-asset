<?php
/**
 * JTDIS ASSET MANAGEMENT
 * Halaman maklumat aset bagi Juruteknik.
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Pastikan pengguna sudah log masuk.
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// Halaman ini khusus untuk Juruteknik.
if (($_SESSION['peranan'] ?? '') !== 'Juruteknik') {
    http_response_code(403);
    die('Anda tidak mempunyai akses ke halaman ini.');
}

$pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);
$aset_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($pengguna_id <= 0 || $aset_id <= 0) {
    header('Location: index.php');
    exit;
}

/*
 * Ambil maklumat aset.
 * Keselamatan: Juruteknik hanya boleh melihat aset yang didaftarkan olehnya.
 */
$aset_query = "
    SELECT
        a.*,
        s.status,
        s.warna,
        ag.nama_agensi,
        d.nama_daerah
    FROM aset a
    LEFT JOIN status_aset s
        ON a.status_aset_id = s.status_aset_id
    LEFT JOIN agensi ag
        ON a.agensi_id = ag.agensi_id
    LEFT JOIN daerah d
        ON ag.daerah_id = d.daerah_id
    WHERE a.aset_id = ?
      AND a.pengguna_id_daftar = ?
    LIMIT 1
";

$aset_stmt = mysqli_prepare($conn, $aset_query);

if (!$aset_stmt) {
    http_response_code(500);
    die('Ralat penyediaan query: ' . escapeOutput(mysqli_error($conn)));
}

mysqli_stmt_bind_param(
    $aset_stmt,
    'ii',
    $aset_id,
    $pengguna_id
);

mysqli_stmt_execute($aset_stmt);
$aset_result = mysqli_stmt_get_result($aset_stmt);
$aset = mysqli_fetch_assoc($aset_result);
mysqli_stmt_close($aset_stmt);

if (!$aset) {
    $_SESSION['flash_error'] = 'Aset tidak ditemui atau anda tidak mempunyai akses.';
    header('Location: index.php');
    exit;
}

/**
 * Paparkan tanda "-" untuk nilai kosong.
 */
function nilaiAset($nilai): string
{
    if ($nilai === null || trim((string) $nilai) === '') {
        return '-';
    }

    return escapeOutput((string) $nilai);
}

/**
 * Dapatkan warna badge yang selamat.
 */
function warnaStatus(?string $warna): string
{
    $warna = trim((string) $warna);

    if (
        preg_match('/^#[0-9a-fA-F]{3,8}$/', $warna)
        || preg_match('/^(rgb|rgba|hsl|hsla)\([^)]+\)$/', $warna)
    ) {
        return $warna;
    }

    $peta = [
        'primary' => '#2563eb',
        'secondary' => '#64748b',
        'success' => '#16a34a',
        'danger' => '#dc2626',
        'warning' => '#d97706',
        'info' => '#0891b2',
        'dark' => '#1e293b',
    ];

    return $peta[strtolower($warna)] ?? '#64748b';
}

$status_warna = warnaStatus($aset['warna'] ?? null);
$status_nama = $aset['status'] ?? 'Tidak diketahui';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lihat Aset - Juruteknik JTDIS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="aset.css">
</head>

<body class="page-lihat">
<div class="container-fluid p-0">
    <div class="app-shell">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand-wrap">
                <img
                    src="/jdtis_asset/assets/images/jtdi-logo.png"
                    alt="Logo Jabatan Teknologi Digital dan Inovasi Negeri Sabah"
                    class="brand-logo"
                    onerror="
                        this.style.display='none';
                        document.getElementById('brandFallback').style.display='flex';
                    "
                >

                <div class="brand-fallback" id="brandFallback">
                    <div class="brand-mark">
                        <i class="bi bi-pc-display-horizontal"></i>
                    </div>

                    <div>
                        <h2 class="brand-title">JTDIS</h2>
                        <p class="brand-subtitle">Modul Aset ICT</p>
                    </div>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-badge"></i>
                Juruteknik
            </div>

            <nav class="sidebar-nav" aria-label="Menu Juruteknik">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-fill"></i>
                    Dashboard
                </a>

                <a class="nav-link active" href="index.php">
                    <i class="bi bi-boxes"></i>
                    Aset Saya
                </a>

                <hr class="nav-separator">

                <a class="nav-link logout-link" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i>
                    Log Keluar
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="content-container">

                <div class="page-hero">
                    <div>
                        <a
                            href="index.php"
                            class="btn btn-outline-secondary mb-3"
                        >
                            <i class="bi bi-arrow-left"></i>
                            Kembali ke Senarai
                        </a>

                        <h1 class="page-title">Maklumat Aset</h1>

                        <p class="page-subtitle">
                            Semakan terperinci aset yang telah didaftarkan.
                        </p>
                    </div>
                </div>

                <section class="card">
                    <div class="asset-card-header">
                        <div>
                            <h2 class="asset-number">
                                <?php
                                echo escapeOutput(
                                    $aset['no_pendaftaran']
                                );
                                ?>
                            </h2>

                            <span
                                class="status-badge"
                                style="background-color:
                                    <?php echo escapeOutput($status_warna); ?>;"
                            >
                                <i class="bi bi-circle-fill small"></i>

                                <?php
                                echo escapeOutput($status_nama);
                                ?>
                            </span>
                        </div>

                        <div class="action-buttons">
                            <a
                                href="edit.php?id=<?php
                                    echo (int) $aset['aset_id'];
                                ?>"
                                class="btn btn-warning"
                            >
                                <i class="bi bi-pencil"></i>
                                Edit
                            </a>

                            <a
                                href="hapus.php?id=<?php
                                    echo (int) $aset['aset_id'];
                                ?>"
                                class="btn btn-danger"
                            >
                                <i class="bi bi-trash"></i>
                                Hapus
                            </a>
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- MAKLUMAT ASAS -->
                        <section class="detail-section">
                            <h3 class="section-title">
                                <i class="bi bi-info-circle"></i>
                                Maklumat Asas
                            </h3>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        Jenis Aset
                                    </span>

                                    <span class="detail-value">
                                        <?php echo nilaiAset($aset['jenis_aset']); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Jenis Perolehan
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo nilaiAset(
                                            $aset['jenis_perolehan']
                                        );
                                        ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Tahun Beli
                                    </span>

                                    <span class="detail-value">
                                        <?php echo nilaiAset($aset['tahun_beli']); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Jenama
                                    </span>

                                    <span class="detail-value">
                                        <?php echo nilaiAset($aset['jenama']); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Model
                                    </span>

                                    <span class="detail-value">
                                        <?php echo nilaiAset($aset['model']); ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Tarikh Pendaftaran
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo !empty($aset['tarikh_input'])
                                            ? escapeOutput(
                                                date(
                                                    'd/m/Y H:i',
                                                    strtotime(
                                                        $aset['tarikh_input']
                                                    )
                                                )
                                            )
                                            : '-';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </section>

                        <!-- LOKASI -->
                        <section class="detail-section">
                            <h3 class="section-title">
                                <i class="bi bi-geo-alt"></i>
                                Lokasi Aset
                            </h3>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        Daerah
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo nilaiAset(
                                            $aset['nama_daerah']
                                        );
                                        ?>
                                    </span>
                                </div>

                                <div class="detail-item">
                                    <span class="detail-label">
                                        Agensi
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo nilaiAset(
                                            $aset['nama_agensi']
                                        );
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </section>

                        <!-- MAKLUMAT PEGAWAI -->
                        <?php if (
                            !empty($aset['pegawai_nama'])
                            || !empty($aset['pegawai_jawatan'])
                            || !empty($aset['pegawai_gred'])
                        ): ?>
                            <section class="detail-section">
                                <h3 class="section-title">
                                    <i class="bi bi-person"></i>
                                    Maklumat Pegawai
                                </h3>

                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Nama Pegawai
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['pegawai_nama']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Jawatan
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['pegawai_jawatan']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Gred
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['pegawai_gred']
                                            );
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- SPESIFIKASI PC / NOTEBOOK -->
                        <?php if (
                            in_array(
                                $aset['jenis_aset'],
                                ['PC', 'NB'],
                                true
                            )
                        ): ?>
                            <section class="detail-section">
                                <h3 class="section-title">
                                    <i class="bi bi-laptop"></i>
                                    Spesifikasi Komputer
                                </h3>

                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Processor
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['processor']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            RAM
                                        </span>

                                        <span class="detail-value">
                                            <?php echo nilaiAset($aset['ram']); ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Cakera Keras
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['cakera_keras']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Sistem Operasi
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['sistem_operasi']
                                            );
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- SPESIFIKASI PENCETAK -->
                        <?php if ($aset['jenis_aset'] === 'Pencetak'): ?>
                            <section class="detail-section">
                                <h3 class="section-title">
                                    <i class="bi bi-printer"></i>
                                    Spesifikasi Pencetak
                                </h3>

                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            Jenis Pencetak
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['jenis_pencetak']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <div class="detail-item">
                                        <span class="detail-label">
                                            No. Siri Pencetak
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo nilaiAset(
                                                $aset['no_siri_pencetak']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                    <?php if (
                                        !empty(
                                            $aset['spesifikasi_pencetak']
                                        )
                                    ): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">
                                                Spesifikasi
                                            </span>

                                            <span class="detail-value">
                                                <?php
                                                echo nilaiAset(
                                                    $aset[
                                                        'spesifikasi_pencetak'
                                                    ]
                                                );
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <!-- CATATAN -->
                        <?php if (!empty($aset['catatan'])): ?>
                            <section class="detail-section">
                                <h3 class="section-title">
                                    <i class="bi bi-file-text"></i>
                                    Catatan
                                </h3>

                                <p class="notes-box">
                                    <?php
                                    echo nl2br(
                                        escapeOutput($aset['catatan'])
                                    );
                                    ?>
                                </p>
                            </section>
                        <?php endif; ?>

                        <!-- PELUPUSAN -->
                        <?php if (
                            !empty($aset['maklumat_pelupusan_aset'])
                        ): ?>
                            <section class="detail-section">
                                <h3 class="section-title">
                                    <i class="bi bi-recycle"></i>
                                    Maklumat Pelupusan Aset
                                </h3>

                                <p class="notes-box">
                                    <?php
                                    echo nl2br(
                                        escapeOutput(
                                            $aset[
                                                'maklumat_pelupusan_aset'
                                            ]
                                        )
                                    );
                                    ?>
                                </p>
                            </section>
                        <?php endif; ?>

                        <div class="bottom-actions">
                            <a
                                href="edit.php?id=<?php
                                    echo (int) $aset['aset_id'];
                                ?>"
                                class="btn btn-warning"
                            >
                                <i class="bi bi-pencil"></i>
                                Edit Aset
                            </a>

                            <a
                                href="hapus.php?id=<?php
                                    echo (int) $aset['aset_id'];
                                ?>"
                                class="btn btn-danger"
                            >
                                <i class="bi bi-trash"></i>
                                Hapus Aset
                            </a>

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >
                                <i class="bi bi-arrow-left"></i>
                                Kembali
                            </a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>