<?php
/**
 * JTDIS - Shared helper for cascading analytical dashboards.
 */

function analyticsBindParams(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {
    if ($types === '' || $params === []) {
        return;
    }

    $arguments = [$stmt, $types];

    foreach ($params as &$value) {
        $arguments[] = &$value;
    }
    unset($value);

    if (!call_user_func_array('mysqli_stmt_bind_param', $arguments)) {
        throw new RuntimeException('Gagal mengikat parameter query.');
    }
}

function analyticsFetchAll(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Gagal menyediakan query: ' . mysqli_error($conn)
        );
    }

    analyticsBindParams($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Query gagal: ' . $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = $result
        ? mysqli_fetch_all($result, MYSQLI_ASSOC)
        : [];

    mysqli_stmt_close($stmt);
    return $rows;
}

function analyticsFetchOne(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    $rows = analyticsFetchAll($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function analyticsInputInt(string $name): int
{
    $value = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT);

    if ($value === false || $value === null) {
        return 0;
    }

    return max(0, (int) $value);
}

function analyticsInputString(
    string $name,
    array $allowed
): string {
    $value = trim((string) ($_GET[$name] ?? ''));

    return in_array($value, $allowed, true)
        ? $value
        : '';
}

function analyticsFixedRegionRole(string $role): bool
{
    return in_array(
        $role,
        ['PPTM', 'PTM', 'Ketua Wilayah'],
        true
    );
}

/**
 * Resolve and validate Wilayah -> Daerah -> Agensi.
 *
 * Rules:
 * - PPTM/PTM/Ketua Wilayah: wilayah is forced from session.
 * - Ketua Bahagian/Pengarah: wilayah is selected by user.
 * - wilayah_id=1 (Ibu Pejabat): district is not applicable.
 * - wilayah_id>1: agency requires a valid district in the same region.
 */
function analyticsResolveLocation(
    mysqli $conn,
    string $role,
    int $sessionRegionId,
    int $requestedRegionId,
    int $requestedDistrictId,
    int $requestedAgencyId
): array {
    $errors = [];
    $fixedRegion = analyticsFixedRegionRole($role);

    $regionId = $fixedRegion
        ? $sessionRegionId
        : $requestedRegionId;

    if ($fixedRegion && $regionId <= 1) {
        $errors[] = 'Wilayah dalam session pengguna tidak sah.';
        $regionId = 0;
    }

    if ($regionId > 0) {
        $region = analyticsFetchOne(
            $conn,
            "SELECT wilayah_id, nama_wilayah
             FROM wilayah
             WHERE wilayah_id = ?
             LIMIT 1",
            'i',
            [$regionId]
        );

        if ($region === []) {
            $errors[] = 'Wilayah yang dipilih tidak sah.';
            $regionId = 0;
        }
    }

    $districtId = 0;
    $agencyId = 0;

    if ($regionId === 1) {
        // Ibu Pejabat has no district layer.
        if ($requestedAgencyId > 0) {
            $agency = analyticsFetchOne(
                $conn,
                "SELECT agensi_id
                 FROM agensi
                 WHERE agensi_id = ?
                   AND wilayah_id = 1
                 LIMIT 1",
                'i',
                [$requestedAgencyId]
            );

            if ($agency !== []) {
                $agencyId = $requestedAgencyId;
            } else {
                $errors[] = 'Agensi Ibu Pejabat tidak sah.';
            }
        }
    } elseif ($regionId > 1) {
        if ($requestedDistrictId > 0) {
            $district = analyticsFetchOne(
                $conn,
                "SELECT daerah_id
                 FROM daerah
                 WHERE daerah_id = ?
                   AND wilayah_id = ?
                 LIMIT 1",
                'ii',
                [$requestedDistrictId, $regionId]
            );

            if ($district !== []) {
                $districtId = $requestedDistrictId;
            } else {
                $errors[] = 'Daerah tidak berada dalam wilayah yang dipilih.';
            }
        }

        if ($requestedAgencyId > 0) {
            if ($districtId <= 0) {
                $errors[] = 'Pilih daerah sebelum memilih agensi.';
            } else {
                $agency = analyticsFetchOne(
                    $conn,
                    "SELECT agensi_id
                     FROM agensi
                     WHERE agensi_id = ?
                       AND wilayah_id = ?
                       AND daerah_id = ?
                     LIMIT 1",
                    'iii',
                    [$requestedAgencyId, $regionId, $districtId]
                );

                if ($agency !== []) {
                    $agencyId = $requestedAgencyId;
                } else {
                    $errors[] = 'Agensi tidak berada dalam daerah yang dipilih.';
                }
            }
        }
    }

    return [
        'wilayah_id' => $regionId,
        'daerah_id' => $districtId,
        'agensi_id' => $agencyId,
        'fixed_wilayah' => $fixedRegion,
        'errors' => $errors,
    ];
}

function analyticsLocationOptions(
    mysqli $conn,
    int $regionId,
    int $districtId
): array {
    $regions = analyticsFetchAll(
        $conn,
        "SELECT wilayah_id, nama_wilayah
         FROM wilayah
         ORDER BY
            CASE WHEN wilayah_id = 1 THEN 0 ELSE 1 END,
            nama_wilayah"
    );

    $districts = [];

    if ($regionId > 1) {
        $districts = analyticsFetchAll(
            $conn,
            "SELECT daerah_id, nama_daerah
             FROM daerah
             WHERE wilayah_id = ?
             ORDER BY nama_daerah",
            'i',
            [$regionId]
        );
    }

    $agencies = [];

    if ($regionId === 1) {
        $agencies = analyticsFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi
             FROM agensi
             WHERE wilayah_id = 1
             ORDER BY nama_agensi"
        );
    } elseif ($regionId > 1 && $districtId > 0) {
        $agencies = analyticsFetchAll(
            $conn,
            "SELECT agensi_id, nama_agensi
             FROM agensi
             WHERE wilayah_id = ?
               AND daerah_id = ?
             ORDER BY nama_agensi",
            'ii',
            [$regionId, $districtId]
        );
    }

    return [
        'wilayah' => $regions,
        'daerah' => $districts,
        'agensi' => $agencies,
    ];
}

function analyticsCommonOptions(mysqli $conn): array
{
    return [
        'tahun' => analyticsFetchAll(
            $conn,
            "SELECT DISTINCT tahun_beli
             FROM aset
             WHERE tahun_beli IS NOT NULL
             ORDER BY tahun_beli DESC"
        ),
        'workflow' => analyticsFetchAll(
            $conn,
            "SELECT status_workflow_id, status
             FROM status_workflow
             ORDER BY status_workflow_id"
        ),
        'status_aset' => analyticsFetchAll(
            $conn,
            "SELECT status_aset_id, status
             FROM status_aset
             ORDER BY status_aset_id"
        ),
    ];
}

function analyticsBootstrapColor($value): string
{
    $allowed = [
        'primary',
        'secondary',
        'success',
        'danger',
        'warning',
        'info',
        'light',
        'dark',
    ];

    $value = strtolower(trim((string) $value));

    return in_array($value, $allowed, true)
        ? $value
        : 'secondary';
}

function analyticsDateTime($value): string
{
    if (empty($value)) {
        return '-';
    }

    $time = strtotime((string) $value);

    return $time === false
        ? '-'
        : date('d/m/Y H:i', $time);
}
