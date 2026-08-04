<?php
require_once '../../includes/config.php';
$target = APP_URL . '/pages/laporan-aset/index.php';
$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '') $target .= '?' . $query;
header('Location: ' . $target);
exit;
