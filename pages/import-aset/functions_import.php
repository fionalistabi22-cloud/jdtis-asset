<?php
/**
 * JTDIS - Fungsi Sokongan Import Aset Pukal
 */

function importProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function importAutoloadPath(): string
{
    return importProjectRoot() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

function importPhpSpreadsheetAvailable(): bool
{
    $autoload = importAutoloadPath();

    if (!file_exists($autoload)) {
        return false;
    }

    require_once $autoload;

    return class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
}

function importTablesAvailable(mysqli $conn): bool
{
    foreach (['import_batch', 'import_batch_item'] as $table) {
        $escaped = mysqli_real_escape_string($conn, $table);
        $result = mysqli_query($conn, "SHOW TABLES LIKE '{$escaped}'");

        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }
    }

    return true;
}

function importGetUserContext(mysqli $conn): ?array
{
    $pengguna_id = (int) ($_SESSION['pengguna_id'] ?? 0);

    if ($pengguna_id <= 0) {
        return null;
    }

    $sql = "
        SELECT
            p.pengguna_id,
            p.nama_penuh,
            p.emel,
            p.wilayah_id,
            p.agensi_id,
            r.nama_peranan AS peranan,
            w.nama_wilayah,
            a.nama_agensi
        FROM pengguna p
        INNER JOIN peranan r
            ON r.peranan_id = p.peranan_id
        LEFT JOIN wilayah w
            ON w.wilayah_id = p.wilayah_id
        LEFT JOIN agensi a
            ON a.agensi_id = p.agensi_id
        WHERE p.pengguna_id = ?
          AND p.status_pengguna_id = 1
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $pengguna_id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function importRoleRoutes(string $role): array
{
    return match ($role) {
        'Agen IT' => [
            'dashboard' => '../agen-it/dashboard.php',
            'assets' => '../agen-it/aset/index.php',
            'create' => '../agen-it/aset/tambah.php',
            'asset_view_prefix' => '../agen-it/aset/lihat.php?id=',
            'logout' => '../logout.php',
            'label' => 'Agen IT',
        ],
        'PID' => [
            'dashboard' => '../pid/dashboard.php',
            'assets' => '../pid/senarai_aset.php',
            'create' => '../pid/tambah.php',
            'asset_view_prefix' => '../pid/lihat.php?id=',
            'logout' => '../logout.php',
            'label' => 'PID',
        ],
        default => [
            'dashboard' => '../dashboard.php',
            'assets' => '../aset/index.php',
            'create' => '../aset/tambah.php',
            'asset_view_prefix' => '../aset/lihat.php?id=',
            'logout' => '../logout.php',
            'label' => 'Juruteknik',
        ],
    };
}

function importModeLabels(): array
{
    return [
        'draf' => [
            'title' => 'Simpan sebagai Draf',
            'description' => 'Rekod dimasukkan dengan status Draf dan belum dihantar untuk semakan.',
        ],
        'hantar' => [
            'title' => 'Import dan Hantar untuk Semakan',
            'description' => 'Juruteknik/Agen IT dihantar ke PPTM; PID dihantar terus ke Ketua Bahagian.',
        ],
        'legacy_lulus' => [
            'title' => 'Data Lama Telah Disahkan',
            'description' => 'Rekod sejarah dimasukkan terus sebagai Lulus. Gunakan hanya untuk data rasmi yang telah disahkan.',
        ],
    ];
}

function importTargetWorkflow(string $role, string $mode): int
{
    if ($mode === 'legacy_lulus') {
        return 8;
    }

    if ($mode === 'hantar') {
        return $role === 'PID' ? 6 : 2;
    }

    return 1;
}

function importNormalizeKey($value): string
{
    $text = trim((string) $value);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['/', '\\', '-', '.', '(', ')'], ' ', $text);
    $text = preg_replace('/[^a-z0-9]+/u', '_', $text);
    return trim((string) $text, '_');
}

function importHeaderAliases(): array
{
    return [
        'no_pendaftaran' => [
            'no_pendaftaran', 'nombor_pendaftaran', 'no_daftar', 'nombor_daftar',
            'asset_no', 'asset_number', 'registration_no',
        ],
        'jenis_aset' => ['jenis_aset', 'kategori_aset', 'asset_type', 'type'],
        'jenis_perolehan' => [
            'jenis_perolehan', 'perolehan', 'kaedah_perolehan', 'acquisition_type',
        ],
        'tahun_beli' => ['tahun_beli', 'tahun_perolehan', 'tahun', 'purchase_year'],
        'jenama' => ['jenama', 'brand', 'manufacturer'],
        'model' => ['model', 'model_aset'],
        'processor' => ['processor', 'pemproses', 'cpu'],
        'ram' => ['ram', 'memori'],
        'cakera_keras' => ['cakera_keras', 'storan', 'storage', 'hard_disk', 'harddisk'],
        'sistem_operasi' => ['sistem_operasi', 'os', 'operating_system'],
        'spesifikasi_pencetak' => [
            'spesifikasi_pencetak', 'printer_spec', 'spesifikasi_printer',
        ],
        'jenis_pencetak' => ['jenis_pencetak', 'printer_type'],
        'bil_pencetak_laser' => ['bil_pencetak_laser', 'bil_laser', 'jumlah_laser'],
        'bil_pencetak_inkjet' => ['bil_pencetak_inkjet', 'bil_inkjet', 'jumlah_inkjet'],
        'bil_pencetak_matrik' => ['bil_pencetak_matrik', 'bil_matrik', 'jumlah_matrik'],
        'no_siri_pencetak' => ['no_siri_pencetak', 'no_siri', 'serial_no', 'serial_number'],
        'pegawai_nama' => ['pegawai_nama', 'nama_pegawai', 'pengguna_aset', 'officer_name'],
        'pegawai_jawatan' => ['pegawai_jawatan', 'jawatan', 'officer_position'],
        'pegawai_gred' => ['pegawai_gred', 'gred', 'officer_grade'],
        'agensi_id' => ['agensi_id', 'agency_id'],
        'nama_agensi' => ['nama_agensi', 'agensi', 'jabatan', 'kementerian', 'agency'],
        'status_aset' => ['status_aset', 'status_fizikal', 'physical_status'],
        'catatan' => ['catatan', 'nota', 'remarks', 'notes'],
    ];
}

function importBuildHeaderMap(array $headerRow): array
{
    $aliases = importHeaderAliases();
    $reverse = [];

    foreach ($aliases as $canonical => $items) {
        foreach ($items as $alias) {
            $reverse[importNormalizeKey($alias)] = $canonical;
        }
    }

    $map = [];

    foreach ($headerRow as $index => $rawHeader) {
        $normalized = importNormalizeKey($rawHeader);

        if ($normalized === '' || !isset($reverse[$normalized])) {
            continue;
        }

        $canonical = $reverse[$normalized];

        if (!array_key_exists($canonical, $map)) {
            $map[$canonical] = (int) $index;
        }
    }

    return $map;
}

function importValue(array $row, array $headerMap, string $key)
{
    if (!isset($headerMap[$key])) {
        return '';
    }

    return $row[$headerMap[$key]] ?? '';
}

function importIsBlankRow(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

function importNormalizeAssetType($value): ?string
{
    $key = importNormalizeKey($value);

    return match ($key) {
        'nb', 'notebook', 'laptop', 'komputer_riba' => 'NB',
        'pc', 'desktop', 'personal_computer', 'komputer_desktop' => 'PC',
        'pencetak', 'printer' => 'Pencetak',
        'monitor', 'paparan', 'screen' => 'Monitor',
        'lain', 'lain_lain', 'other', 'others' => 'Lain',
        default => null,
    };
}

function importNormalizeAcquisition($value): ?string
{
    $key = importNormalizeKey($value);

    return match ($key) {
        'kerajaan_negeri', 'negeri', 'state_government' => 'Kerajaan Negeri',
        'kerajaan_persekutuan', 'persekutuan', 'federal_government' => 'Kerajaan Persekutuan',
        'sewa', 'rental', 'lease' => 'Sewa',
        'pinjaman', 'loan' => 'Pinjaman',
        'lain', 'lain_lain', 'other', 'others' => 'Lain',
        default => null,
    };
}

function importNormalizePrinterType($value): string
{
    $key = importNormalizeKey($value);

    return match ($key) {
        'laser' => 'Laser',
        'inkjet', 'dakwat' => 'Inkjet',
        'matrik', 'matrix', 'dot_matrix' => 'Matrik',
        default => 'Tiada',
    };
}

function importNormalizePhysicalStatus($value): ?int
{
    if ($value === '' || $value === null) {
        return 1;
    }

    if (is_numeric($value)) {
        $id = (int) $value;
        return in_array($id, [1, 2, 3, 4, 5], true) ? $id : null;
    }

    $key = importNormalizeKey($value);

    return match ($key) {
        'aktif', 'active', 'baik' => 1,
        'rosak', 'damaged', 'broken' => 2,
        'selenggara', 'penyelenggaraan', 'maintenance', 'dalam_selenggara' => 3,
        'hilang', 'lost', 'missing' => 4,
        'dilupuskan', 'lupus', 'disposed' => 5,
        default => null,
    };
}

function importNormalizeYear($value): ?int
{
    $text = trim((string) $value);

    if ($text === '') {
        return null;
    }

    if (preg_match('/\b(19|20)\d{2}\b/', $text, $match)) {
        $year = (int) $match[0];
    } elseif (is_numeric($value)) {
        $year = (int) $value;
    } else {
        return null;
    }

    $maximum = (int) date('Y') + 1;

    return $year >= 1900 && $year <= $maximum ? $year : null;
}

function importNonNegativeInt($value): int
{
    if ($value === '' || $value === null) {
        return 0;
    }

    $number = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    return $number === false ? 0 : (int) $number;
}

function importResolveAgency(
    mysqli $conn,
    array $context,
    $agencyIdRaw,
    $agencyNameRaw
): array {
    $role = (string) $context['peranan'];

    if (in_array($role, ['Agen IT', 'PID'], true)) {
        $agencyId = (int) ($context['agensi_id'] ?? 0);

        if ($agencyId <= 0) {
            return [
                'valid' => false,
                'message' => 'Akaun belum dikaitkan dengan agensi.',
            ];
        }

        return [
            'valid' => true,
            'agensi_id' => $agencyId,
            'nama_agensi' => (string) ($context['nama_agensi'] ?? '-'),
        ];
    }

    $wilayahId = (int) ($context['wilayah_id'] ?? 0);

    if ($wilayahId <= 1) {
        return [
            'valid' => false,
            'message' => 'Wilayah Juruteknik tidak sah.',
        ];
    }

    $agencyId = is_numeric($agencyIdRaw) ? (int) $agencyIdRaw : 0;
    $agencyName = trim((string) $agencyNameRaw);

    if ($agencyId > 0) {
        $sql = "
            SELECT agensi_id, nama_agensi
            FROM agensi
            WHERE agensi_id = ?
              AND wilayah_id = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $agencyId, $wilayahId);
    } elseif ($agencyName !== '') {
        $sql = "
            SELECT agensi_id, nama_agensi
            FROM agensi
            WHERE LOWER(TRIM(nama_agensi)) = LOWER(TRIM(?))
              AND wilayah_id = ?
            LIMIT 2
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $agencyName, $wilayahId);
    } else {
        return [
            'valid' => false,
            'message' => 'Nama agensi atau agensi_id diperlukan untuk Juruteknik.',
        ];
    }

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        return [
            'valid' => false,
            'message' => 'Agensi tidak dapat disahkan.',
        ];
    }

    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if (count($rows) !== 1) {
        return [
            'valid' => false,
            'message' => $agencyName !== ''
                ? 'Agensi tidak dijumpai secara tepat dalam wilayah pengguna.'
                : 'agensi_id tidak sah untuk wilayah pengguna.',
        ];
    }

    return [
        'valid' => true,
        'agensi_id' => (int) $rows[0]['agensi_id'],
        'nama_agensi' => (string) $rows[0]['nama_agensi'],
    ];
}

function importRegistrationExists(mysqli $conn, string $registration): bool
{
    $sql = "
        SELECT aset_id
        FROM aset
        WHERE no_pendaftaran = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Gagal menyediakan semakan nombor pendaftaran.');
    }

    mysqli_stmt_bind_param($stmt, 's', $registration);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal menyemak nombor pendaftaran.');
    }

    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
    mysqli_stmt_close($stmt);

    return $exists;
}

function importValidateRow(
    mysqli $conn,
    array $row,
    array $headerMap,
    array $context,
    array &$seenRegistrations
): array {
    $errors = [];
    $warnings = [];

    $registration = trim((string) importValue($row, $headerMap, 'no_pendaftaran'));
    $assetType = importNormalizeAssetType(importValue($row, $headerMap, 'jenis_aset'));
    $acquisition = importNormalizeAcquisition(importValue($row, $headerMap, 'jenis_perolehan'));
    $yearRaw = importValue($row, $headerMap, 'tahun_beli');
    $year = importNormalizeYear($yearRaw);
    $physicalStatus = importNormalizePhysicalStatus(
        importValue($row, $headerMap, 'status_aset')
    );

    if ($registration === '') {
        $errors[] = 'No. pendaftaran diperlukan.';
    } elseif (mb_strlen($registration) > 150) {
        $errors[] = 'No. pendaftaran melebihi 150 aksara.';
    }

    if ($assetType === null) {
        $errors[] = 'Jenis aset tidak sah.';
    }

    if ($acquisition === null) {
        $errors[] = 'Jenis perolehan tidak sah.';
    }

    if (trim((string) $yearRaw) !== '' && $year === null) {
        $errors[] = 'Tahun beli tidak sah.';
    }

    if ($physicalStatus === null) {
        $errors[] = 'Status aset tidak sah.';
    }

    $registrationKey = mb_strtolower($registration, 'UTF-8');

    if ($registration !== '') {
        if (isset($seenRegistrations[$registrationKey])) {
            $errors[] = 'No. pendaftaran berulang dalam fail.';
        } else {
            $seenRegistrations[$registrationKey] = true;
        }

        if (importRegistrationExists($conn, $registration)) {
            $errors[] = 'No. pendaftaran sudah wujud dalam sistem.';
        }
    }

    $agency = importResolveAgency(
        $conn,
        $context,
        importValue($row, $headerMap, 'agensi_id'),
        importValue($row, $headerMap, 'nama_agensi')
    );

    if (!$agency['valid']) {
        $errors[] = (string) $agency['message'];
    }

    $brand = trim((string) importValue($row, $headerMap, 'jenama'));
    $model = trim((string) importValue($row, $headerMap, 'model'));

    if ($brand === '') {
        $warnings[] = 'Jenama kosong.';
    }

    if ($model === '') {
        $warnings[] = 'Model kosong.';
    }

    $wilayahId = (string) $context['peranan'] === 'PID'
        ? 1
        : (int) ($context['wilayah_id'] ?? 0);

    $data = [
        'no_pendaftaran' => $registration,
        'jenis_aset' => $assetType ?? '',
        'jenis_perolehan' => $acquisition ?? '',
        'tahun_beli' => $year,
        'jenama' => $brand,
        'model' => $model,
        'processor' => trim((string) importValue($row, $headerMap, 'processor')),
        'ram' => trim((string) importValue($row, $headerMap, 'ram')),
        'cakera_keras' => trim((string) importValue($row, $headerMap, 'cakera_keras')),
        'sistem_operasi' => trim((string) importValue($row, $headerMap, 'sistem_operasi')),
        'spesifikasi_pencetak' => trim(
            (string) importValue($row, $headerMap, 'spesifikasi_pencetak')
        ),
        'jenis_pencetak' => importNormalizePrinterType(
            importValue($row, $headerMap, 'jenis_pencetak')
        ),
        'bil_pencetak_laser' => importNonNegativeInt(
            importValue($row, $headerMap, 'bil_pencetak_laser')
        ),
        'bil_pencetak_inkjet' => importNonNegativeInt(
            importValue($row, $headerMap, 'bil_pencetak_inkjet')
        ),
        'bil_pencetak_matrik' => importNonNegativeInt(
            importValue($row, $headerMap, 'bil_pencetak_matrik')
        ),
        'no_siri_pencetak' => trim(
            (string) importValue($row, $headerMap, 'no_siri_pencetak')
        ),
        'pegawai_nama' => trim((string) importValue($row, $headerMap, 'pegawai_nama')),
        'pegawai_jawatan' => trim(
            (string) importValue($row, $headerMap, 'pegawai_jawatan')
        ),
        'pegawai_gred' => trim((string) importValue($row, $headerMap, 'pegawai_gred')),
        'agensi_id' => (int) ($agency['agensi_id'] ?? 0),
        'nama_agensi' => (string) ($agency['nama_agensi'] ?? ''),
        'wilayah_id' => $wilayahId,
        'status_aset_id' => $physicalStatus ?? 1,
        'catatan' => trim((string) importValue($row, $headerMap, 'catatan')),
    ];

    return [
        'valid' => $errors === [],
        'errors' => $errors,
        'warnings' => $warnings,
        'data' => $data,
    ];
}

function importBindDynamic(mysqli_stmt $stmt, string $types, array &$params): void
{
    $arguments = [$stmt, $types];

    foreach ($params as &$value) {
        $arguments[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $arguments)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
}

function importGetBatch(mysqli $conn, int $batchId, int $userId): ?array
{
    $sql = "
        SELECT *
        FROM import_batch
        WHERE import_batch_id = ?
          AND pengguna_id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $batchId, $userId);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function importBatchCounts(mysqli $conn, int $batchId): array
{
    $sql = "
        SELECT
            COUNT(*) AS jumlah,
            SUM(CASE WHEN status_item = 'sah' THEN 1 ELSE 0 END) AS sah,
            SUM(CASE WHEN status_item = 'gagal' THEN 1 ELSE 0 END) AS gagal,
            SUM(CASE WHEN status_item = 'berjaya' THEN 1 ELSE 0 END) AS berjaya,
            SUM(CASE WHEN status_item = 'dilangkau' THEN 1 ELSE 0 END) AS dilangkau
        FROM import_batch_item
        WHERE import_batch_id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $batchId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return [
        'jumlah' => (int) ($row['jumlah'] ?? 0),
        'sah' => (int) ($row['sah'] ?? 0),
        'gagal' => (int) ($row['gagal'] ?? 0),
        'berjaya' => (int) ($row['berjaya'] ?? 0),
        'dilangkau' => (int) ($row['dilangkau'] ?? 0),
    ];
}

function importStatusLabel(string $status): array
{
    return match ($status) {
        'sah' => ['Sah', 'success'],
        'berjaya' => ['Berjaya', 'success'],
        'gagal' => ['Gagal', 'danger'],
        'dilangkau' => ['Dilangkau', 'warning text-dark'],
        default => [ucfirst($status), 'secondary'],
    };
}

function importSendNotifications(
    mysqli $conn,
    int $assetId,
    string $registration,
    string $role,
    int $workflowId,
    int $wilayahId
): void {
    if (!in_array($workflowId, [2, 6], true)) {
        return;
    }

    if ($workflowId === 2) {
        $sql = "
            SELECT p.pengguna_id
            FROM pengguna p
            INNER JOIN peranan r
                ON r.peranan_id = p.peranan_id
            WHERE r.nama_peranan IN ('PPTM', 'PTM')
              AND p.wilayah_id = ?
              AND p.status_pengguna_id = 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $wilayahId);
        $url = '/jdtis_asset/pages/pptm-ptm/lihat.php?id=' . $assetId;
        $message =
            'Aset import ' . $registration .
            ' menunggu pengesahan PPTM/PTM.';
    } else {
        $sql = "
            SELECT p.pengguna_id
            FROM pengguna p
            INNER JOIN peranan r
                ON r.peranan_id = p.peranan_id
            WHERE r.nama_peranan = 'Ketua Bahagian'
              AND p.status_pengguna_id = 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        $url = '/jdtis_asset/pages/ketua-bahagian/lihat.php?id=' . $assetId;
        $message =
            'Aset import PID ' . $registration .
            ' menunggu semakan Ketua Bahagian.';
    }

    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Gagal mendapatkan penerima notifikasi.');
    }

    $recipients = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if ($recipients === []) {
        return;
    }

    $sqlNotification = "
        INSERT INTO notifikasi (
            penerima_id,
            aset_id,
            jenis,
            mesej,
            url
        ) VALUES (
            ?,
            ?,
            'aset_baru',
            ?,
            ?
        )
    ";

    $stmtNotification = mysqli_prepare($conn, $sqlNotification);

    if (!$stmtNotification) {
        throw new RuntimeException('Gagal menyediakan notifikasi.');
    }

    foreach ($recipients as $recipient) {
        $recipientId = (int) $recipient['pengguna_id'];

        mysqli_stmt_bind_param(
            $stmtNotification,
            'iiss',
            $recipientId,
            $assetId,
            $message,
            $url
        );

        if (!mysqli_stmt_execute($stmtNotification)) {
            mysqli_stmt_close($stmtNotification);
            throw new RuntimeException('Gagal menghantar notifikasi.');
        }
    }

    mysqli_stmt_close($stmtNotification);
}
