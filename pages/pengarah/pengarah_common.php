<?php
/**
 * Helper bersama untuk modul Pengarah.
 * Semua halaman Pengarah adalah READ-ONLY.
 */

function pengarahRequireAccess(): void
{
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }

    if (isSessionExpired()) {
        header('Location: ../login.php');
        exit;
    }

    requireRoleWhitelist(['Pengarah']);
}

function pengarahBindDynamic(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $args = [$stmt, $types];

    foreach ($params as &$value) {
        $args[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $args)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
}

function pengarahFetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('Gagal menyediakan query: ' . mysqli_error($conn));
    }

    pengarahBindDynamic($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal melaksanakan query: ' . $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

function pengarahFetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = pengarahFetchAll($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function pengarahGetFilters(): array
{
    $wilayah_id = filter_input(INPUT_GET, 'wilayah_id', FILTER_VALIDATE_INT);
    $daerah_id = filter_input(INPUT_GET, 'daerah_id', FILTER_VALIDATE_INT);
    $agensi_id = filter_input(INPUT_GET, 'agensi_id', FILTER_VALIDATE_INT);
    $tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
    $status_workflow_id = filter_input(INPUT_GET, 'status_workflow_id', FILTER_VALIDATE_INT);
    $status_aset_id = filter_input(INPUT_GET, 'status_aset_id', FILTER_VALIDATE_INT);

    $jenis_aset = trim((string) ($_GET['jenis_aset'] ?? ''));
    $jenis_perolehan = trim((string) ($_GET['jenis_perolehan'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));

    $jenis_aset_dibenarkan = ['', 'NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
    $perolehan_dibenarkan = [
        '',
        'Kerajaan Negeri',
        'Kerajaan Persekutuan',
        'Sewa',
        'Pinjaman',
        'Lain',
    ];

    if (!in_array($jenis_aset, $jenis_aset_dibenarkan, true)) {
        $jenis_aset = '';
    }

    if (!in_array($jenis_perolehan, $perolehan_dibenarkan, true)) {
        $jenis_perolehan = '';
    }

    return [
        'q' => $q,
        'wilayah_id' => ($wilayah_id !== false && $wilayah_id !== null && $wilayah_id > 0)
            ? (int) $wilayah_id : 0,
        'daerah_id' => ($daerah_id !== false && $daerah_id !== null && $daerah_id > 0)
            ? (int) $daerah_id : 0,
        'agensi_id' => ($agensi_id !== false && $agensi_id !== null && $agensi_id > 0)
            ? (int) $agensi_id : 0,
        'tahun' => ($tahun !== false && $tahun !== null && $tahun >= 1990 && $tahun <= ((int) date('Y') + 1))
            ? (int) $tahun : 0,
        'jenis_aset' => $jenis_aset,
        'jenis_perolehan' => $jenis_perolehan,
        'status_workflow_id' => (
            $status_workflow_id !== false
            && $status_workflow_id !== null
            && $status_workflow_id >= 1
            && $status_workflow_id <= 8
        ) ? (int) $status_workflow_id : 0,
        'status_aset_id' => (
            $status_aset_id !== false
            && $status_aset_id !== null
            && $status_aset_id >= 1
            && $status_aset_id <= 5
        ) ? (int) $status_aset_id : 0,
    ];
}

function pengarahBuildWhere(array $filters): array
{
    $clauses = ['1 = 1'];
    $types = '';
    $params = [];

    if ($filters['q'] !== '') {
        $clauses[] = "(
            a.no_pendaftaran LIKE ?
            OR a.jenama LIKE ?
            OR a.model LIKE ?
            OR a.pegawai_nama LIKE ?
            OR ag.nama_agensi LIKE ?
            OR w.nama_wilayah LIKE ?
        )";

        $like = '%' . $filters['q'] . '%';

        for ($i = 0; $i < 6; $i++) {
            $types .= 's';
            $params[] = $like;
        }
    }

    if ($filters['wilayah_id'] > 0) {
        $clauses[] = 'a.wilayah_id = ?';
        $types .= 'i';
        $params[] = $filters['wilayah_id'];
    }

    if ($filters['daerah_id'] > 0) {
        $clauses[] = 'ag.daerah_id = ?';
        $types .= 'i';
        $params[] = $filters['daerah_id'];
    }

    if ($filters['agensi_id'] > 0) {
        $clauses[] = 'a.agensi_id = ?';
        $types .= 'i';
        $params[] = $filters['agensi_id'];
    }

    if ($filters['tahun'] > 0) {
        $clauses[] = 'a.tahun_beli = ?';
        $types .= 'i';
        $params[] = $filters['tahun'];
    }

    if ($filters['jenis_aset'] !== '') {
        $clauses[] = 'a.jenis_aset = ?';
        $types .= 's';
        $params[] = $filters['jenis_aset'];
    }

    if ($filters['jenis_perolehan'] !== '') {
        $clauses[] = 'a.jenis_perolehan = ?';
        $types .= 's';
        $params[] = $filters['jenis_perolehan'];
    }

    if ($filters['status_workflow_id'] > 0) {
        $clauses[] = 'a.status_workflow_id = ?';
        $types .= 'i';
        $params[] = $filters['status_workflow_id'];
    }

    if ($filters['status_aset_id'] > 0) {
        $clauses[] = 'a.status_aset_id = ?';
        $types .= 'i';
        $params[] = $filters['status_aset_id'];
    }

    return [
        'sql' => implode("\n AND ", $clauses),
        'types' => $types,
        'params' => $params,
    ];
}

function pengarahQueryString(array $filters, array $extra = []): string
{
    $params = [];

    foreach ($filters as $key => $value) {
        if ($value !== '' && $value !== 0 && $value !== null) {
            $params[$key] = $value;
        }
    }

    foreach ($extra as $key => $value) {
        if ($value !== '' && $value !== null) {
            $params[$key] = $value;
        }
    }

    return http_build_query($params);
}

function pengarahWorkflowBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-secondary',
        2 => 'bg-warning text-dark',
        3 => 'bg-danger',
        4 => 'bg-info text-dark',
        5 => 'bg-danger',
        6 => 'bg-primary',
        7 => 'bg-danger',
        8 => 'bg-success',
        default => 'bg-secondary',
    };
}

function pengarahAssetBadge(int $status_id): string
{
    return match ($status_id) {
        1 => 'bg-success',
        2 => 'bg-danger',
        3 => 'bg-warning text-dark',
        4 => 'bg-dark',
        5 => 'bg-secondary',
        default => 'bg-secondary',
    };
}

function pengarahFormatDate($value, string $format = 'd/m/Y H:i'): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp !== false ? date($format, $timestamp) : '-';
}

function pengarahDropdownData(
    mysqli $conn,
    array $filters = []
): array {
    $wilayahId = (int) ($filters['wilayah_id'] ?? 0);
    $daerahId = (int) ($filters['daerah_id'] ?? 0);

    $daerah = [];
    $agensi = [];

    if ($wilayahId > 1) {
        $daerah = pengarahFetchAll(
            $conn,
            "SELECT daerah_id, nama_daerah
             FROM daerah
             WHERE wilayah_id = ?
             ORDER BY nama_daerah",
            'i',
            [$wilayahId]
        );
    }

    if ($wilayahId === 1) {
        $agensi = pengarahFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi, wilayah_id, daerah_id, jenis_agensi
             FROM agensi
             WHERE wilayah_id = 1
             ORDER BY nama_agensi"
        );
    } elseif ($wilayahId > 1 && $daerahId > 0) {
        $agensi = pengarahFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi, wilayah_id, daerah_id, jenis_agensi
             FROM agensi
             WHERE wilayah_id = ?
               AND daerah_id = ?
             ORDER BY nama_agensi",
            'ii',
            [$wilayahId, $daerahId]
        );
    }

    return [
        'wilayah' => pengarahFetchAll(
            $conn,
            "SELECT wilayah_id, nama_wilayah, jenis
             FROM wilayah
             ORDER BY CASE WHEN jenis = 'ibu_pejabat' THEN 0 ELSE 1 END, nama_wilayah"
        ),
        'daerah' => $daerah,
        'agensi' => $agensi,
        'tahun' => pengarahFetchAll(
            $conn,
            "SELECT DISTINCT tahun_beli
             FROM aset
             WHERE tahun_beli IS NOT NULL
             ORDER BY tahun_beli DESC"
        ),
        'workflow' => pengarahFetchAll(
            $conn,
            "SELECT status_workflow_id, status
             FROM status_workflow
             ORDER BY status_workflow_id"
        ),
        'status_aset' => pengarahFetchAll(
            $conn,
            "SELECT status_aset_id, status
             FROM status_aset
             ORDER BY status_aset_id"
        ),
    ];
}
