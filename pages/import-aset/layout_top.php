<?php
$page_title = $page_title ?? 'Import Aset Pukal';
$page_subtitle = $page_subtitle ?? 'Pindahkan data Excel atau Google Sheets ke dalam JTDIS.';
$active_page = $active_page ?? 'import';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeOutput($page_title); ?> - JTDIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/import.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-xl-2 import-sidebar p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="brand-mark"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                <div>
                    <div class="fw-bold fs-4">JTDIS</div>
                    <small class="text-white-50">Import Aset Pukal</small>
                </div>
            </div>

            <div class="role-pill">
                <i class="bi bi-person-badge"></i>
                <?php echo escapeOutput((string) $import_routes['label']); ?>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="<?php echo escapeOutput($import_routes['dashboard']); ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>

                <a class="nav-link" href="<?php echo escapeOutput($import_routes['assets']); ?>">
                    <i class="bi bi-box-seam"></i> Senarai Aset
                </a>

                <a class="nav-link <?php echo $active_page === 'import' ? 'active' : ''; ?>"
                   href="index.php">
                    <i class="bi bi-cloud-arrow-up"></i> Import Aset
                </a>

                <a class="nav-link <?php echo $active_page === 'history' ? 'active' : ''; ?>"
                   href="sejarah.php">
                    <i class="bi bi-clock-history"></i> Sejarah Import
                </a>

                <hr class="nav-separator">

                <a class="nav-link" href="<?php echo escapeOutput($import_routes['logout']); ?>">
                    <i class="bi bi-box-arrow-left"></i> Log Keluar
                </a>
            </nav>
        </aside>

        <main class="col-md-9 col-xl-10 import-main">
            <div class="page-hero">
                <div>
                    <h1 class="page-title"><?php echo escapeOutput($page_title); ?></h1>
                    <p class="page-subtitle"><?php echo escapeOutput($page_subtitle); ?></p>
                </div>

                <div class="page-chip">
                    <i class="bi bi-shield-check"></i>
                    <?php echo escapeOutput((string) $import_context['peranan']); ?>
                </div>
            </div>
