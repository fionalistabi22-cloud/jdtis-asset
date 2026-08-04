<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$phpTimezone = date_default_timezone_get();
$phpTime = date('d/m/Y H:i:s');
$mysqlTime = '-';
$mysqlTimezone = '-';

$result = mysqli_query(
    $conn,
    "SELECT NOW() AS masa_mysql, @@session.time_zone AS zon_mysql"
);

if ($result && ($row = mysqli_fetch_assoc($result))) {
    $mysqlTime = (string) ($row['masa_mysql'] ?? '-');
    $mysqlTimezone = (string) ($row['zon_mysql'] ?? '-');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semakan Masa JTDIS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; padding: 30px; }
        .card { max-width: 720px; margin: auto; background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 12px 30px rgba(0,0,0,.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dbe3ec; padding: 12px; text-align: left; }
        th { width: 42%; background: #f8fafc; }
        .ok { color: #047857; font-weight: bold; }
    </style>
</head>
<body>
<div class="card">
    <h1>Semakan Zon Masa JTDIS</h1>
    <table>
        <tr><th>Zon masa PHP</th><td><?php echo htmlspecialchars($phpTimezone); ?></td></tr>
        <tr><th>Masa PHP</th><td><?php echo htmlspecialchars($phpTime); ?></td></tr>
        <tr><th>Zon masa MySQL session</th><td><?php echo htmlspecialchars($mysqlTimezone); ?></td></tr>
        <tr><th>Masa MySQL</th><td><?php echo htmlspecialchars($mysqlTime); ?></td></tr>
    </table>
    <p class="ok">Nilai yang betul: PHP = Asia/Kuala_Lumpur dan MySQL = +08:00.</p>
</div>
</body>
</html>
