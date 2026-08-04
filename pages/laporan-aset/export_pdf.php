<?php
/**
 * JDTIS - Export laporan aset ringkas ke PDF.
 * Format: satu jadual, satu baris bagi setiap aset.
 */

require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/report_aset.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

// Gunakan waktu rasmi Malaysia bagi semua role dan semua export.
$reportTimezone = new DateTimeZone('Asia/Kuala_Lumpur');
$generatedAt = new DateTimeImmutable('now', $reportTimezone);
$generatedAtDisplay = $generatedAt->format('d/m/Y H:i:s');
$generatedAtFilename = $generatedAt->format('Ymd_His');

$context = reportRequireContext($conn);
$config = reportRoleConfig((string) $context['peranan']);

$resolved = reportResolveFilters(
    $conn,
    $context,
    reportGetFilters()
);

$filters = $resolved['filters'];
$where = reportBuildWhere($context, $filters);
$labels = reportFilterLabels($conn, $context, $filters);
$assets = reportFetchAssets($conn, $where);

try {
    reportLoadComposer();

    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('Dompdf belum dipasang.');
    }

    ob_start();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 8mm 7mm 10mm 7mm;
    }

    body {
        margin: 0;
        font-family: DejaVu Sans, sans-serif;
        color: #111827;
        font-size: 6.6px;
    }

    h1 {
        margin: 0;
        font-size: 14px;
        text-align: center;
        text-transform: uppercase;
    }

    .meta {
        margin: 4px 0 7px;
        text-align: center;
        color: #475569;
        font-size: 7px;
        line-height: 1.45;
    }

    .summary {
        margin-bottom: 6px;
        padding: 4px 6px;
        border: 1px solid #94a3b8;
        background: #f8fafc;
        font-size: 7px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    th,
    td {
        border: 0.45px solid #64748b;
        padding: 2.3px 2px;
        vertical-align: top;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    th {
        background: #dbeafe;
        color: #0f172a;
        font-size: 6.2px;
        font-weight: bold;
        text-align: center;
    }

    tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .center {
        text-align: center;
    }

    .no-data {
        padding: 18px;
        border: 1px solid #cbd5e1;
        text-align: center;
        color: #64748b;
    }

    .w-bil { width: 2.4%; }
    .w-agency { width: 9.5%; }
    .w-location { width: 7.5%; }
    .w-name { width: 8.2%; }
    .w-position { width: 7.2%; }
    .w-grade { width: 3.5%; }
    .w-type { width: 3.8%; }
    .w-reg { width: 8.5%; }
    .w-procure { width: 7.2%; }
    .w-year { width: 3.7%; }
    .w-model { width: 8.2%; }
    .w-os { width: 6.7%; }
    .w-cpu { width: 8.6%; }
    .w-ram { width: 4.3%; }
    .w-storage { width: 5.4%; }
    .w-status { width: 5.1%; }
    .w-workflow { width: 6.3%; }
</style>
</head>
<body>

<h1><?php echo reportEscape($config['title']); ?></h1>

<div class="meta">
    Skop: <?php echo reportEscape($config['scope']); ?>
    |
    Dijana oleh:
    <?php echo reportEscape($context['nama_penuh']); ?>
    (<?php echo reportEscape($context['peranan']); ?>)
    |
    Tarikh:
    <?php echo $generatedAtDisplay; ?>
</div>

<div class="summary">
    <strong>Penapis:</strong>
    Wilayah: <?php echo reportEscape($labels['Wilayah'] ?? 'Semua'); ?>
    |
    Daerah: <?php echo reportEscape($labels['Daerah'] ?? 'Semua'); ?>
    |
    Agensi: <?php echo reportEscape($labels['Agensi'] ?? 'Semua'); ?>
    |
    Tahun: <?php echo reportEscape($labels['Tahun Beli'] ?? 'Semua'); ?>
    |
    Jumlah aset: <?php echo number_format(count($assets)); ?>
</div>

<?php if ($assets === []): ?>
    <div class="no-data">Tiada aset menepati penapis yang dipilih.</div>
<?php else: ?>
<table>
    <thead>
    <tr>
        <th class="w-bil">Bil.</th>
        <th class="w-agency">Bahagian / Agensi</th>
        <th class="w-location">Wilayah / Daerah</th>
        <th class="w-name">Nama Pegawai / Pengguna</th>
        <th class="w-position">Jawatan</th>
        <th class="w-grade">Gred</th>
        <th class="w-type">Jenis</th>
        <th class="w-reg">No. Pendaftaran</th>
        <th class="w-procure">Jenis Perolehan</th>
        <th class="w-year">Tahun</th>
        <th class="w-model">Jenama / Model</th>
        <th class="w-os">Sistem Operasi</th>
        <th class="w-cpu">Processor</th>
        <th class="w-ram">RAM</th>
        <th class="w-storage">Cakera Keras</th>
        <th class="w-status">Status Fizikal</th>
        <th class="w-workflow">Status Workflow</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($assets as $index => $asset): ?>
        <?php
        $pegawai = trim((string) ($asset['pegawai_nama'] ?? ''));

        if ($pegawai === '') {
            $pegawai = trim((string) ($asset['pengguna_semasa'] ?? ''));
        }

        $model = trim(
            (string) ($asset['jenama'] ?? '')
            . ' '
            . (string) ($asset['model'] ?? '')
        );

        $location =
            reportValue($asset['nama_wilayah'] ?? null)
            . ' / '
            . (
                trim((string) ($asset['nama_daerah'] ?? '')) !== ''
                    ? (string) $asset['nama_daerah']
                    : 'Tiada Daerah'
            );
        ?>
        <tr>
            <td class="center"><?php echo $index + 1; ?></td>
            <td><?php echo reportEscape(reportValue($asset['nama_agensi'] ?? null)); ?></td>
            <td><?php echo reportEscape($location); ?></td>
            <td><?php echo reportEscape(reportValue($pegawai)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['pegawai_jawatan'] ?? null)); ?></td>
            <td class="center"><?php echo reportEscape(reportValue($asset['pegawai_gred'] ?? null)); ?></td>
            <td class="center"><?php echo reportEscape(reportValue($asset['jenis_aset'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['no_pendaftaran'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['jenis_perolehan'] ?? null)); ?></td>
            <td class="center"><?php echo reportEscape(reportValue($asset['tahun_beli'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($model)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['sistem_operasi'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['processor'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['ram'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['cakera_keras'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['status_aset'] ?? null)); ?></td>
            <td><?php echo reportEscape(reportValue($asset['status_workflow'] ?? null)); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
<?php
    $html = ob_get_clean();

    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');

    // A3 landscape is used so the simple wide table remains readable.
    $dompdf->setPaper('A3', 'landscape');
    $dompdf->render();

    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont(
        'DejaVu Sans',
        'normal'
    );

    $canvas->page_text(
        14,
        820,
        'JDTIS - ' . $context['peranan'],
        $font,
        7,
        [0.25, 0.3, 0.4]
    );

    $canvas->page_text(
        1050,
        820,
        'Halaman {PAGE_NUM} / {PAGE_COUNT}',
        $font,
        7,
        [0.25, 0.3, 0.4]
    );

    $filename = reportSafeFilename(
        'laporan_aset_ringkas_'
        . $context['peranan']
        . '_'
        . $generatedAtFilename
    ) . '.pdf';

    $dompdf->stream(
        $filename,
        ['Attachment' => true]
    );
    exit;
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log('Export PDF Ringkas: ' . $e->getMessage());

    http_response_code(500);
    echo '<h2>Laporan PDF tidak dapat dijana</h2>';
    echo '<p>' . reportEscape($e->getMessage()) . '</p>';
    echo '<p>Pastikan Dompdf dipasang:</p>';
    echo '<code>composer require dompdf/dompdf</code>';
}
