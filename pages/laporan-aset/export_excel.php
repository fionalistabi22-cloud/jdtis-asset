<?php
/**
 * JDTIS - Export laporan aset ringkas ke Excel.
 * Pembetulan: jana XLSX ke fail sementara, sahkan ZIP, kemudian stream
 * tanpa sebarang output PHP/HTML yang boleh merosakkan fail.
 */

declare(strict_types=1);

// Tangkap semua output daripada fail include, warning atau whitespace.
ob_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '1024M');

$tempFile = '';
$spreadsheet = null;

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/report_aset.php';

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

    reportLoadComposer();

    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        throw new RuntimeException('PhpSpreadsheet belum dipasang.');
    }

    if (!class_exists(\ZipArchive::class)) {
        throw new RuntimeException(
            'Extension ZIP PHP belum aktif. Aktifkan extension=zip dalam php.ini.'
        );
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $spreadsheet->getProperties()
        ->setCreator('JTDIS')
        ->setTitle((string) $config['title'])
        ->setSubject('Laporan aset ICT ringkas');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Aset');

    $lastColumn = 'Q';

    $sheet->mergeCells("A1:{$lastColumn}1");
    $sheet->setCellValue('A1', strtoupper((string) $config['title']));

    $sheet->mergeCells("A2:{$lastColumn}2");
    $sheet->setCellValue(
        'A2',
        'Skop: ' . (string) $config['scope']
        . ' | Dijana oleh: ' . (string) $context['nama_penuh']
        . ' (' . (string) $context['peranan'] . ')'
    );

    $sheet->mergeCells("A3:{$lastColumn}3");
    $sheet->setCellValue(
        'A3',
        'Wilayah: ' . (string) ($labels['Wilayah'] ?? 'Semua')
        . ' | Daerah: ' . (string) ($labels['Daerah'] ?? 'Semua')
        . ' | Agensi: ' . (string) ($labels['Agensi'] ?? 'Semua')
        . ' | Tahun: ' . (string) ($labels['Tahun Beli'] ?? 'Semua')
        . ' | Tarikh jana: ' . $generatedAtDisplay
    );

    $headers = [
        'Bil.',
        'Bahagian / Agensi',
        'Wilayah / Daerah',
        'Nama Pegawai / Pengguna',
        'Jawatan Pegawai',
        'Gred',
        'Jenis Aset',
        'No. Pendaftaran',
        'Jenis Perolehan',
        'Tahun Belian',
        'Jenama / Model',
        'Sistem Operasi',
        'Processor',
        'RAM',
        'Cakera Keras',
        'Status Fizikal',
        'Status Workflow',
    ];

    $sheet->fromArray($headers, null, 'A5');

    $rows = [];
    $number = 1;

    foreach ($assets as $asset) {
        $pegawai = trim((string) ($asset['pegawai_nama'] ?? ''));

        if ($pegawai === '') {
            $pegawai = trim((string) ($asset['pengguna_semasa'] ?? ''));
        }

        $jenamaModel = trim(
            (string) ($asset['jenama'] ?? '')
            . ' '
            . (string) ($asset['model'] ?? '')
        );

        $namaWilayah = trim((string) ($asset['nama_wilayah'] ?? ''));
        $namaDaerah = trim((string) ($asset['nama_daerah'] ?? ''));

        $wilayahDaerah = reportValue($namaWilayah)
            . ' / '
            . ($namaDaerah !== '' ? $namaDaerah : 'Tiada Daerah');

        $rows[] = [
            $number++,
            reportValue($asset['nama_agensi'] ?? null),
            $wilayahDaerah,
            reportValue($pegawai),
            reportValue($asset['pegawai_jawatan'] ?? null),
            reportValue($asset['pegawai_gred'] ?? null),
            reportValue($asset['jenis_aset'] ?? null),
            reportValue($asset['no_pendaftaran'] ?? null),
            reportValue($asset['jenis_perolehan'] ?? null),
            reportValue($asset['tahun_beli'] ?? null),
            reportValue($jenamaModel),
            reportValue($asset['sistem_operasi'] ?? null),
            reportValue($asset['processor'] ?? null),
            reportValue($asset['ram'] ?? null),
            reportValue($asset['cakera_keras'] ?? null),
            reportValue($asset['status_aset'] ?? null),
            reportValue($asset['status_workflow'] ?? null),
        ];
    }

    if ($rows !== []) {
        $sheet->fromArray($rows, null, 'A6');
    }

    $lastRow = max(6, count($rows) + 5);

    $titleStyle = [
        'font' => [
            'bold' => true,
            'size' => 15,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '176A56'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];

    $subTitleStyle = [
        'font' => [
            'size' => 10,
            'color' => ['rgb' => '334155'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'EAF5F1'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
    ];

    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '3157D5'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '94A3B8'],
            ],
        ],
    ];

    $bodyStyle = [
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'],
            ],
        ],
    ];

    $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray($titleStyle);
    $sheet->getStyle("A2:{$lastColumn}3")->applyFromArray($subTitleStyle);
    $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray($headerStyle);
    $sheet->getStyle("A6:{$lastColumn}{$lastRow}")->applyFromArray($bodyStyle);

    $sheet->getRowDimension(1)->setRowHeight(27);
    $sheet->getRowDimension(2)->setRowHeight(22);
    $sheet->getRowDimension(3)->setRowHeight(28);
    $sheet->getRowDimension(5)->setRowHeight(42);

    $widths = [
        'A' => 7,
        'B' => 28,
        'C' => 25,
        'D' => 25,
        'E' => 24,
        'F' => 10,
        'G' => 12,
        'H' => 24,
        'I' => 22,
        'J' => 12,
        'K' => 25,
        'L' => 21,
        'M' => 29,
        'N' => 13,
        'O' => 18,
        'P' => 16,
        'Q' => 20,
    ];

    foreach ($widths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $sheet->freezePane('A6');
    $sheet->setAutoFilter("A5:{$lastColumn}{$lastRow}");

    $sheet->getPageSetup()
        ->setOrientation(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
        )
        ->setPaperSize(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3
        )
        ->setFitToWidth(1)
        ->setFitToHeight(0);

    $sheet->getPageMargins()
        ->setTop(0.3)
        ->setRight(0.25)
        ->setBottom(0.3)
        ->setLeft(0.25);

    $sheet->getHeaderFooter()->setOddFooter(
        '&LJDTIS - ' . (string) $context['peranan']
        . '&RHalaman &P daripada &N'
    );

    $filename = reportSafeFilename(
        'laporan_aset_ringkas_'
        . (string) $context['peranan']
        . '_'
        . $generatedAtFilename
    ) . '.xlsx';

    // Simpan dahulu ke fail sementara. Ini mengelakkan warning/HTML
    // bercampur dengan binary XLSX dalam response browser.
    $tempFile = tempnam(sys_get_temp_dir(), 'jdtis_xlsx_');

    if ($tempFile === false) {
        throw new RuntimeException('Gagal mencipta fail sementara Excel.');
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($tempFile);

    clearstatcache(true, $tempFile);
    $fileSize = filesize($tempFile);

    if ($fileSize === false || $fileSize < 1000) {
        throw new RuntimeException('Fail Excel yang dijana tidak lengkap.');
    }

    $handle = fopen($tempFile, 'rb');

    if ($handle === false) {
        throw new RuntimeException('Fail Excel sementara tidak dapat dibaca.');
    }

    $signature = fread($handle, 2);
    fclose($handle);

    if ($signature !== 'PK') {
        throw new RuntimeException('Fail yang dijana bukan format XLSX yang sah.');
    }

    $zip = new \ZipArchive();
    $zipResult = $zip->open($tempFile, \ZipArchive::CHECKCONS);

    if ($zipResult !== true) {
        throw new RuntimeException(
            'Struktur dalaman XLSX rosak. Kod ZIP: ' . (string) $zipResult
        );
    }

    $requiredEntries = [
        '[Content_Types].xml',
        'xl/workbook.xml',
        'xl/worksheets/sheet1.xml',
    ];

    foreach ($requiredEntries as $entry) {
        if ($zip->locateName($entry) === false) {
            $zip->close();
            throw new RuntimeException(
                'Komponen Excel tidak lengkap: ' . $entry
            );
        }
    }

    $zip->close();

    // Semua output daripada include/warning dibuang sebelum binary dihantar.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) $fileSize);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    readfile($tempFile);
    unlink($tempFile);
    $tempFile = '';

    $spreadsheet->disconnectWorksheets();
    exit;
} catch (Throwable $e) {
    if ($tempFile !== '' && is_file($tempFile)) {
        @unlink($tempFile);
    }

    if ($spreadsheet instanceof \PhpOffice\PhpSpreadsheet\Spreadsheet) {
        $spreadsheet->disconnectWorksheets();
    }

    error_log('Export Excel Ringkas: ' . $e->getMessage());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html><html lang="ms"><head><meta charset="UTF-8">';
    echo '<title>Excel gagal dijana</title></head><body style="font-family:Arial;padding:30px">';
    echo '<h2>Laporan Excel tidak dapat dijana</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Semak juga fail <code>C:\\xampp\\apache\\logs\\error.log</code>.</p>';
    echo '</body></html>';
    exit;
}
