<?php
/**
 * JDTIS - Enjin bersama laporan aset terperinci.
 * Role: Pengarah, Ketua Bahagian, Ketua Wilayah dan PID.
 */

function reportEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function reportBindParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || $params === []) {
        return;
    }

    $arguments = [$stmt, $types];

    foreach ($params as &$value) {
        $arguments[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $arguments)) {
        throw new RuntimeException('Gagal mengikat parameter query laporan.');
    }
}

function reportFetchAll(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Gagal menyediakan query laporan: ' . mysqli_error($conn)
        );
    }

    reportBindParams($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Query laporan gagal: ' . $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    return $rows;
}

function reportFetchOne(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    return reportFetchAll($conn, $sql, $types, $params)[0] ?? [];
}

function reportRequireContext(mysqli $conn): array
{
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }

    if (isSessionExpired()) {
        header('Location: ../login.php');
        exit;
    }

    requireRoleWhitelist([
        'Pengarah',
        'Ketua Bahagian',
        'Ketua Wilayah',
        'PID',
    ]);

    $userId = (int) ($_SESSION['pengguna_id'] ?? 0);

    if ($userId <= 0) {
        header('Location: ../logout.php');
        exit;
    }

    $context = reportFetchOne(
        $conn,
        "SELECT
            p.pengguna_id,
            p.nama_penuh,
            p.emel,
            p.wilayah_id,
            p.daerah_id,
            p.agensi_id,
            r.nama_peranan AS peranan,
            w.nama_wilayah,
            ag.nama_agensi
         FROM pengguna p
         INNER JOIN peranan r ON r.peranan_id = p.peranan_id
         LEFT JOIN wilayah w ON w.wilayah_id = p.wilayah_id
         LEFT JOIN agensi ag ON ag.agensi_id = p.agensi_id
         WHERE p.pengguna_id = ?
           AND p.status_pengguna_id = 1
         LIMIT 1",
        'i',
        [$userId]
    );

    if ($context === []) {
        header('Location: ../logout.php');
        exit;
    }

    return $context;
}

function reportRoleConfig(string $role): array
{
    return match ($role) {
        'Pengarah' => [
            'title' => 'Laporan Aset Seluruh Sabah',
            'scope' => 'Semua wilayah, daerah dan agensi',
            'dashboard' => '../pengarah/dashboard.php',
            'assets' => '../pengarah/senarai_aset.php',
            'sidebar_class' => 'role-director',
            'icon' => 'bi-bar-chart-fill',
        ],
        'Ketua Bahagian' => [
            'title' => 'Laporan Aset Ketua Bahagian',
            'scope' => 'Semua aset wilayah dan PID',
            'dashboard' => '../ketua-bahagian/dashboard.php',
            'assets' => '../ketua-bahagian/senarai_aset.php',
            'sidebar_class' => 'role-kb',
            'icon' => 'bi-diagram-3-fill',
        ],
        'Ketua Wilayah' => [
            'title' => 'Laporan Aset Wilayah',
            'scope' => 'Wilayah sendiri sahaja',
            'dashboard' => '../ketua-wilayah/dashboard.php',
            'assets' => '../ketua-wilayah/senarai_aset.php',
            'sidebar_class' => 'role-kw',
            'icon' => 'bi-geo-alt-fill',
        ],
        'PID' => [
            'title' => 'Laporan Aset PID',
            'scope' => 'Agensi PID sendiri sahaja',
            'dashboard' => '../pid/dashboard.php',
            'assets' => '../pid/senarai_aset.php',
            'sidebar_class' => 'role-pid',
            'icon' => 'bi-building-check',
        ],
        default => throw new RuntimeException('Role laporan tidak disokong.'),
    };
}

function reportInputInt(string $key): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($value === false || $value === null) ? 0 : max(0, (int) $value);
}

function reportInputDate(string $key): string
{
    $value = trim((string) ($_GET[$key] ?? ''));

    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function reportGetFilters(): array
{
    $types = ['', 'NB', 'PC', 'Pencetak', 'Monitor', 'Lain'];
    $procurements = [
        '', 'Kerajaan Negeri', 'Kerajaan Persekutuan',
        'Sewa', 'Pinjaman', 'Lain',
    ];
    $sources = ['', 'Juruteknik', 'Agen IT', 'PID'];

    $assetType = trim((string) ($_GET['jenis_aset'] ?? ''));
    $procurement = trim((string) ($_GET['jenis_perolehan'] ?? ''));
    $source = trim((string) ($_GET['sumber'] ?? ''));

    return [
        'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 150),
        'wilayah_id' => reportInputInt('wilayah_id'),
        'daerah_id' => reportInputInt('daerah_id'),
        'agensi_id' => reportInputInt('agensi_id'),
        'tahun_beli' => reportInputInt('tahun_beli'),
        'jenis_aset' => in_array($assetType, $types, true) ? $assetType : '',
        'jenis_perolehan' => in_array($procurement, $procurements, true)
            ? $procurement : '',
        'status_workflow_id' => reportInputInt('status_workflow_id'),
        'status_aset_id' => reportInputInt('status_aset_id'),
        'sumber' => in_array($source, $sources, true) ? $source : '',
        'tarikh_dari' => reportInputDate('tarikh_dari'),
        'tarikh_hingga' => reportInputDate('tarikh_hingga'),
    ];
}

function reportResolveFilters(
    mysqli $conn,
    array $context,
    array $filters
): array {
    $role = (string) $context['peranan'];
    $errors = [];

    if ($role === 'Ketua Wilayah') {
        $regionId = (int) ($context['wilayah_id'] ?? 0);
        $filters['wilayah_id'] = $regionId;

        if ($regionId <= 1) {
            $errors[] = 'Wilayah Ketua Wilayah tidak sah.';
        }
    } elseif ($role === 'PID') {
        $filters['wilayah_id'] = 1;
        $filters['daerah_id'] = 0;
        $filters['agensi_id'] = (int) ($context['agensi_id'] ?? 0);
        $filters['sumber'] = 'PID';

        if ($filters['agensi_id'] <= 0) {
            $errors[] = 'Akaun PID belum dikaitkan dengan agensi.';
        }
    }

    $regionId = (int) $filters['wilayah_id'];
    $districtId = (int) $filters['daerah_id'];
    $agencyId = (int) $filters['agensi_id'];

    if ($regionId > 0) {
        $region = reportFetchOne(
            $conn,
            'SELECT wilayah_id FROM wilayah WHERE wilayah_id = ? LIMIT 1',
            'i',
            [$regionId]
        );

        if ($region === []) {
            $filters['wilayah_id'] = 0;
            $filters['daerah_id'] = 0;
            $filters['agensi_id'] = 0;
            $errors[] = 'Wilayah yang dipilih tidak sah.';
            return ['filters' => $filters, 'errors' => $errors];
        }
    }

    if ($role !== 'PID' && $regionId === 1) {
        $filters['daerah_id'] = 0;

        if ($agencyId > 0) {
            $agency = reportFetchOne(
                $conn,
                "SELECT agensi_id FROM agensi
                 WHERE agensi_id = ? AND wilayah_id = 1 LIMIT 1",
                'i',
                [$agencyId]
            );

            if ($agency === []) {
                $filters['agensi_id'] = 0;
                $errors[] = 'Agensi Ibu Pejabat tidak sah.';
            }
        }
    } elseif ($role !== 'PID' && $regionId > 1) {
        if ($districtId > 0) {
            $district = reportFetchOne(
                $conn,
                "SELECT daerah_id FROM daerah
                 WHERE daerah_id = ? AND wilayah_id = ? LIMIT 1",
                'ii',
                [$districtId, $regionId]
            );

            if ($district === []) {
                $filters['daerah_id'] = 0;
                $filters['agensi_id'] = 0;
                $errors[] = 'Daerah tidak berada dalam wilayah yang dipilih.';
                return ['filters' => $filters, 'errors' => $errors];
            }
        } else {
            $filters['agensi_id'] = 0;
        }

        if ((int) $filters['agensi_id'] > 0) {
            $agency = reportFetchOne(
                $conn,
                "SELECT agensi_id FROM agensi
                 WHERE agensi_id = ?
                   AND wilayah_id = ?
                   AND daerah_id = ?
                 LIMIT 1",
                'iii',
                [(int) $filters['agensi_id'], $regionId, (int) $filters['daerah_id']]
            );

            if ($agency === []) {
                $filters['agensi_id'] = 0;
                $errors[] = 'Agensi tidak berada dalam daerah yang dipilih.';
            }
        }
    } elseif ($role !== 'PID' && $regionId === 0) {
        $filters['daerah_id'] = 0;
        $filters['agensi_id'] = 0;
    }

    if (!in_array((int) $filters['status_workflow_id'], range(1, 8), true)) {
        $filters['status_workflow_id'] = 0;
    }

    if (!in_array((int) $filters['status_aset_id'], range(1, 5), true)) {
        $filters['status_aset_id'] = 0;
    }

    if ($role === 'Ketua Wilayah' && $filters['sumber'] === 'PID') {
        $filters['sumber'] = '';
    }

    return ['filters' => $filters, 'errors' => $errors];
}

function reportDropdowns(
    mysqli $conn,
    array $context,
    array $filters
): array {
    $role = (string) $context['peranan'];
    $regionId = (int) $filters['wilayah_id'];
    $districtId = (int) $filters['daerah_id'];

    $regions = [];

    if (in_array($role, ['Pengarah', 'Ketua Bahagian'], true)) {
        $regions = reportFetchAll(
            $conn,
            "SELECT wilayah_id, nama_wilayah
             FROM wilayah
             ORDER BY CASE WHEN wilayah_id = 1 THEN 0 ELSE 1 END, nama_wilayah"
        );
    } elseif ($role === 'Ketua Wilayah') {
        $regions = reportFetchAll(
            $conn,
            "SELECT wilayah_id, nama_wilayah
             FROM wilayah WHERE wilayah_id = ?",
            'i',
            [$regionId]
        );
    }

    $districts = [];
    if ($role !== 'PID' && $regionId > 1) {
        $districts = reportFetchAll(
            $conn,
            "SELECT daerah_id, nama_daerah
             FROM daerah WHERE wilayah_id = ? ORDER BY nama_daerah",
            'i',
            [$regionId]
        );
    }

    $agencies = [];
    if ($role === 'PID') {
        $agencies = reportFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi
             FROM agensi WHERE agensi_id = ? LIMIT 1",
            'i',
            [(int) $filters['agensi_id']]
        );
    } elseif ($regionId === 1) {
        $agencies = reportFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi
             FROM agensi WHERE wilayah_id = 1 ORDER BY nama_agensi"
        );
    } elseif ($regionId > 1 && $districtId > 0) {
        $agencies = reportFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi
             FROM agensi
             WHERE wilayah_id = ? AND daerah_id = ?
             ORDER BY nama_agensi",
            'ii',
            [$regionId, $districtId]
        );
    }

    return [
        'wilayah' => $regions,
        'daerah' => $districts,
        'agensi' => $agencies,
        'tahun' => reportFetchAll(
            $conn,
            "SELECT DISTINCT tahun_beli FROM aset
             WHERE tahun_beli IS NOT NULL ORDER BY tahun_beli DESC"
        ),
        'workflow' => reportFetchAll(
            $conn,
            "SELECT status_workflow_id, status
             FROM status_workflow ORDER BY status_workflow_id"
        ),
        'status_aset' => reportFetchAll(
            $conn,
            "SELECT status_aset_id, status
             FROM status_aset ORDER BY status_aset_id"
        ),
    ];
}

function reportBuildWhere(array $context, array $filters): array
{
    $clauses = ['1 = 1'];
    $types = '';
    $params = [];
    $role = (string) $context['peranan'];

    if ($role === 'Ketua Wilayah') {
        $clauses[] = 'a.wilayah_id = ?';
        $types .= 'i';
        $params[] = (int) $context['wilayah_id'];
    } elseif ($role === 'PID') {
        $clauses[] = 'a.wilayah_id = 1';
        $clauses[] = 'a.agensi_id = ?';
        $types .= 'i';
        $params[] = (int) $context['agensi_id'];
    }

    if ($role !== 'PID' && $filters['wilayah_id'] > 0) {
        $clauses[] = 'a.wilayah_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['wilayah_id'];
    }

    if ($filters['daerah_id'] > 0) {
        $clauses[] = 'ag.daerah_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['daerah_id'];
    }

    if ($role !== 'PID' && $filters['agensi_id'] > 0) {
        $clauses[] = 'a.agensi_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['agensi_id'];
    }

    if ($filters['q'] !== '') {
        $clauses[] = "(
            a.no_pendaftaran LIKE ? OR a.jenama LIKE ? OR a.model LIKE ?
            OR a.processor LIKE ? OR a.pegawai_nama LIKE ?
            OR ag.nama_agensi LIKE ? OR d.nama_daerah LIKE ?
            OR w.nama_wilayah LIKE ?
        )";
        $like = '%' . $filters['q'] . '%';
        for ($i = 0; $i < 8; $i++) {
            $types .= 's';
            $params[] = $like;
        }
    }

    if ($filters['tahun_beli'] > 0) {
        $clauses[] = 'a.tahun_beli = ?';
        $types .= 'i';
        $params[] = (int) $filters['tahun_beli'];
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
        $params[] = (int) $filters['status_workflow_id'];
    }

    if ($filters['status_aset_id'] > 0) {
        $clauses[] = 'a.status_aset_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['status_aset_id'];
    }

    if ($filters['sumber'] !== '') {
        $clauses[] = 'pr.nama_peranan = ?';
        $types .= 's';
        $params[] = $filters['sumber'];
    }

    if ($filters['tarikh_dari'] !== '') {
        $clauses[] = 'DATE(a.tarikh_input) >= ?';
        $types .= 's';
        $params[] = $filters['tarikh_dari'];
    }

    if ($filters['tarikh_hingga'] !== '') {
        $clauses[] = 'DATE(a.tarikh_input) <= ?';
        $types .= 's';
        $params[] = $filters['tarikh_hingga'];
    }

    return [
        'sql' => implode("\n AND ", $clauses),
        'types' => $types,
        'params' => $params,
    ];
}

function reportBaseJoins(): string
{
    return "
        LEFT JOIN agensi ag ON ag.agensi_id = a.agensi_id
        LEFT JOIN daerah d ON d.daerah_id = ag.daerah_id
        LEFT JOIN wilayah w ON w.wilayah_id = a.wilayah_id
        LEFT JOIN status_workflow sw ON sw.status_workflow_id = a.status_workflow_id
        LEFT JOIN status_aset sa ON sa.status_aset_id = a.status_aset_id
        LEFT JOIN pengguna pd ON pd.pengguna_id = a.pengguna_id_daftar
        LEFT JOIN peranan pr ON pr.peranan_id = pd.peranan_id
        LEFT JOIN pengguna ps ON ps.pengguna_id = a.pengguna_semasa_id
    ";
}

function reportSummary(mysqli $conn, array $where): array
{
    $joins = reportBaseJoins();
    $row = reportFetchOne(
        $conn,
        "SELECT
            COUNT(DISTINCT a.aset_id) AS jumlah,
            COALESCE(SUM(a.status_workflow_id = 8), 0) AS lulus,
            COALESCE(SUM(a.status_workflow_id IN (2,4,6)), 0) AS dalam_proses,
            COALESCE(SUM(a.status_workflow_id IN (3,5,7)), 0) AS ditolak,
            COALESCE(SUM(a.status_aset_id = 1), 0) AS aktif,
            COALESCE(SUM(a.status_aset_id = 2), 0) AS rosak,
            COALESCE(SUM(a.status_aset_id = 3), 0) AS selenggara,
            COALESCE(SUM(a.status_aset_id = 4), 0) AS hilang,
            COALESCE(SUM(a.status_aset_id = 5), 0) AS dilupuskan
         FROM aset a
         {$joins}
         WHERE {$where['sql']}",
        $where['types'],
        $where['params']
    );

    $defaults = [
        'jumlah' => 0, 'lulus' => 0, 'dalam_proses' => 0, 'ditolak' => 0,
        'aktif' => 0, 'rosak' => 0, 'selenggara' => 0,
        'hilang' => 0, 'dilupuskan' => 0,
    ];

    foreach ($defaults as $key => $value) {
        $defaults[$key] = (int) ($row[$key] ?? 0);
    }

    $cost = reportFetchOne(
        $conn,
        "SELECT
            COUNT(ls.log_id) AS jumlah_rekod_selenggara,
            COALESCE(SUM(ls.kos), 0) AS jumlah_kos_selenggara
         FROM log_selenggara ls
         INNER JOIN aset a ON a.aset_id = ls.aset_id
         {$joins}
         WHERE {$where['sql']}",
        $where['types'],
        $where['params']
    );

    $defaults['jumlah_rekod_selenggara'] = (int) ($cost['jumlah_rekod_selenggara'] ?? 0);
    $defaults['jumlah_kos_selenggara'] = (float) ($cost['jumlah_kos_selenggara'] ?? 0);

    return $defaults;
}

function reportFetchAssets(
    mysqli $conn,
    array $where,
    ?int $limit = null,
    int $offset = 0
): array {
    $joins = reportBaseJoins();
    $limitSql = '';
    $types = $where['types'];
    $params = $where['params'];

    if ($limit !== null) {
        $limitSql = ' LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = max(0, $offset);
    }

    return reportFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            a.jenis_aset,
            a.jenis_perolehan,
            a.tahun_beli,
            a.jenama,
            a.model,
            a.processor,
            a.ram,
            a.cakera_keras,
            a.sistem_operasi,
            a.spesifikasi_pencetak,
            a.jenis_pencetak,
            a.bil_pencetak_laser,
            a.bil_pencetak_inkjet,
            a.bil_pencetak_matrik,
            a.no_siri_pencetak,
            a.pegawai_nama,
            a.pegawai_jawatan,
            a.pegawai_gred,
            a.pengguna_semasa_id,
            ps.nama_penuh AS pengguna_semasa,
            a.agensi_id,
            ag.nama_agensi,
            ag.jenis_agensi,
            a.wilayah_id,
            w.nama_wilayah,
            ag.daerah_id,
            d.nama_daerah,
            a.status_workflow_id,
            sw.status AS status_workflow,
            a.status_aset_id,
            sa.status AS status_aset,
            a.pengguna_id_daftar,
            pd.nama_penuh AS pendaftar,
            pr.nama_peranan AS peranan_pendaftar,
            a.catatan,
            a.maklumat_pelupusan_aset,
            a.tarikh_input,
            a.tarikh_kemaskini,
            COALESCE(ms.jumlah_selenggara, 0) AS jumlah_selenggara,
            COALESCE(ms.jumlah_kos_selenggara, 0) AS jumlah_kos_selenggara,
            ms.tarikh_selenggara_terakhir,
            COALESCE(ws.jumlah_log_workflow, 0) AS jumlah_log_workflow,
            ws.tarikh_workflow_terakhir,
            pl.sebab AS sebab_pelupusan,
            pl.dokumen_rujukan,
            pl.catatan AS catatan_pelupusan,
            pl.tarikh_lupus,
            pp.nama_penuh AS disahkan_pelupusan_oleh
         FROM aset a
         {$joins}
         LEFT JOIN (
            SELECT
                aset_id,
                COUNT(*) AS jumlah_selenggara,
                COALESCE(SUM(kos), 0) AS jumlah_kos_selenggara,
                MAX(tarikh) AS tarikh_selenggara_terakhir
            FROM log_selenggara
            GROUP BY aset_id
         ) ms ON ms.aset_id = a.aset_id
         LEFT JOIN (
            SELECT
                aset_id,
                COUNT(*) AS jumlah_log_workflow,
                MAX(tarikh) AS tarikh_workflow_terakhir
            FROM log_workflow
            GROUP BY aset_id
         ) ws ON ws.aset_id = a.aset_id
         LEFT JOIN (
            SELECT p1.*
            FROM pelupusan p1
            INNER JOIN (
                SELECT aset_id, MAX(pelupusan_id) AS max_id
                FROM pelupusan
                GROUP BY aset_id
            ) px ON px.max_id = p1.pelupusan_id
         ) pl ON pl.aset_id = a.aset_id
         LEFT JOIN pengguna pp ON pp.pengguna_id = pl.disahkan_oleh
         WHERE {$where['sql']}
         ORDER BY
            w.nama_wilayah,
            d.nama_daerah,
            ag.nama_agensi,
            a.no_pendaftaran
         {$limitSql}",
        $types,
        $params
    );
}

function reportFetchWorkflowLogs(mysqli $conn, array $where): array
{
    $joins = reportBaseJoins();
    return reportFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            lw.log_id,
            lw.tindakan,
            sf.status AS status_dari,
            sk.status AS status_ke,
            lw.catatan,
            u.nama_penuh AS dilakukan_oleh,
            lw.tarikh
         FROM log_workflow lw
         INNER JOIN aset a ON a.aset_id = lw.aset_id
         {$joins}
         LEFT JOIN status_workflow sf ON sf.status_workflow_id = lw.status_workflow_dari
         LEFT JOIN status_workflow sk ON sk.status_workflow_id = lw.status_workflow_ke
         LEFT JOIN pengguna u ON u.pengguna_id = lw.oleh_pengguna_id
         WHERE {$where['sql']}
         ORDER BY a.no_pendaftaran, lw.tarikh, lw.log_id",
        $where['types'],
        $where['params']
    );
}

function reportFetchMaintenanceLogs(mysqli $conn, array $where): array
{
    $joins = reportBaseJoins();
    return reportFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            ls.log_id,
            ls.jenis_selenggara,
            ls.komponen_ditukar,
            ls.kos,
            ls.catatan,
            ls.status_selepas,
            u.nama_penuh AS dibuat_oleh,
            ls.tarikh
         FROM log_selenggara ls
         INNER JOIN aset a ON a.aset_id = ls.aset_id
         {$joins}
         LEFT JOIN pengguna u ON u.pengguna_id = ls.dibuat_oleh
         WHERE {$where['sql']}
         ORDER BY a.no_pendaftaran, ls.tarikh, ls.log_id",
        $where['types'],
        $where['params']
    );
}

function reportFetchTransferLogs(mysqli $conn, array $where): array
{
    $joins = reportBaseJoins();
    return reportFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            lp.log_id,
            lp.nama_pengguna_lama,
            lp.nama_pengguna_baru,
            lp.sebab,
            u.nama_penuh AS direkod_oleh,
            lp.tarikh
         FROM log_pemindahan lp
         INNER JOIN aset a ON a.aset_id = lp.aset_id
         {$joins}
         LEFT JOIN pengguna u ON u.pengguna_id = lp.direkod_oleh
         WHERE {$where['sql']}
         ORDER BY a.no_pendaftaran, lp.tarikh, lp.log_id",
        $where['types'],
        $where['params']
    );
}

function reportFetchDisposals(mysqli $conn, array $where): array
{
    $joins = reportBaseJoins();
    return reportFetchAll(
        $conn,
        "SELECT
            a.aset_id,
            a.no_pendaftaran,
            pl.pelupusan_id,
            pl.sebab,
            pl.dokumen_rujukan,
            pl.catatan,
            u.nama_penuh AS disahkan_oleh,
            pl.tarikh_lupus
         FROM pelupusan pl
         INNER JOIN aset a ON a.aset_id = pl.aset_id
         {$joins}
         LEFT JOIN pengguna u ON u.pengguna_id = pl.disahkan_oleh
         WHERE {$where['sql']}
         ORDER BY a.no_pendaftaran, pl.tarikh_lupus, pl.pelupusan_id",
        $where['types'],
        $where['params']
    );
}

function reportGroupByAsset(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) $row['aset_id']][] = $row;
    }
    return $grouped;
}

function reportQueryString(array $filters): string
{
    $values = [];
    foreach ($filters as $key => $value) {
        if ($value !== '' && $value !== 0 && $value !== null) {
            $values[$key] = $value;
        }
    }
    return http_build_query($values);
}

function reportNameById(
    mysqli $conn,
    string $table,
    string $idColumn,
    string $nameColumn,
    int $id
): string {
    $allowed = [
        'wilayah' => ['wilayah_id', 'nama_wilayah'],
        'daerah' => ['daerah_id', 'nama_daerah'],
        'agensi' => ['agensi_id', 'nama_agensi'],
        'status_workflow' => ['status_workflow_id', 'status'],
        'status_aset' => ['status_aset_id', 'status'],
    ];

    if ($id <= 0 || !isset($allowed[$table])) {
        return 'Semua';
    }

    [$validId, $validName] = $allowed[$table];
    if ($idColumn !== $validId || $nameColumn !== $validName) {
        return 'Tidak Sah';
    }

    $row = reportFetchOne(
        $conn,
        "SELECT {$validName} AS nama FROM {$table}
         WHERE {$validId} = ? LIMIT 1",
        'i',
        [$id]
    );

    return (string) ($row['nama'] ?? 'Tidak Ditetapkan');
}

function reportFilterLabels(
    mysqli $conn,
    array $context,
    array $filters
): array {
    return [
        'Skop Role' => reportRoleConfig((string) $context['peranan'])['scope'],
        'Wilayah' => reportNameById($conn, 'wilayah', 'wilayah_id', 'nama_wilayah', (int) $filters['wilayah_id']),
        'Daerah' => reportNameById($conn, 'daerah', 'daerah_id', 'nama_daerah', (int) $filters['daerah_id']),
        'Agensi' => reportNameById($conn, 'agensi', 'agensi_id', 'nama_agensi', (int) $filters['agensi_id']),
        'Carian' => $filters['q'] ?: 'Semua',
        'Tahun Beli' => $filters['tahun_beli'] ?: 'Semua',
        'Jenis Aset' => $filters['jenis_aset'] ?: 'Semua',
        'Jenis Perolehan' => $filters['jenis_perolehan'] ?: 'Semua',
        'Status Workflow' => reportNameById($conn, 'status_workflow', 'status_workflow_id', 'status', (int) $filters['status_workflow_id']),
        'Status Fizikal' => reportNameById($conn, 'status_aset', 'status_aset_id', 'status', (int) $filters['status_aset_id']),
        'Sumber Pendaftaran' => $filters['sumber'] ?: 'Semua',
        'Tarikh Daftar' => ($filters['tarikh_dari'] ?: 'Awal') . ' hingga ' . ($filters['tarikh_hingga'] ?: 'Terkini'),
    ];
}

function reportFormatDate($value, string $format = 'd/m/Y H:i'): string
{
    if (empty($value)) {
        return '-';
    }
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '-' : date($format, $timestamp);
}

function reportValue($value): string
{
    return ($value === null || $value === '') ? '-' : (string) $value;
}

function reportSafeFilename(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value);
    return trim((string) $value, '_') ?: 'laporan';
}

function reportLoadComposer(): void
{
    $autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException(
            'Composer vendor/autoload.php tidak ditemui. Jalankan Composer dalam folder jdtis_asset.'
        );
    }
    require_once $autoload;
}
