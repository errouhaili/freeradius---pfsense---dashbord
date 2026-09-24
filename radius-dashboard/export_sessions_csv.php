<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$pdo = get_db();
$scope = $_GET['scope'] ?? 'active';

header('Content-Type: text/csv; charset=utf-8');

if ($scope === 'active') {
    header('Content-Disposition: attachment; filename="active_sessions_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['username', 'nas', 'device_ip', 'mac', 'start_time', 'duration', 'download', 'upload']);

    $rows = $pdo->query("SELECT username, nasipaddress, framedipaddress, callingstationid,
                                 acctstarttime, acctinputoctets, acctoutputoctets
                          FROM radacct WHERE acctstoptime IS NULL
                          ORDER BY acctstarttime DESC")->fetchAll();

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['username'],
            $r['nasipaddress'],
            $r['framedipaddress'],
            $r['callingstationid'],
            $r['acctstarttime'],
            format_duration(time() - strtotime($r['acctstarttime'])),
            format_bytes($r['acctinputoctets']),
            format_bytes($r['acctoutputoctets']),
        ]);
    }
    fclose($out);
    exit;
}

// scope = history (avec dates from/to)
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$to = $_GET['to'] ?? date('Y-m-d');

header('Content-Disposition: attachment; filename="sessions_history_' . $from . '_' . $to . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['username', 'nas', 'device_ip', 'start_time', 'stop_time', 'duration_sec', 'download', 'upload']);

$stmt = $pdo->prepare("SELECT username, nasipaddress, framedipaddress, acctstarttime, acctstoptime,
                               acctsessiontime, acctinputoctets, acctoutputoctets
                        FROM radacct
                        WHERE DATE(acctstarttime) BETWEEN ? AND ?
                        ORDER BY acctstarttime DESC");
$stmt->execute([$from, $to]);

foreach ($stmt->fetchAll() as $r) {
    fputcsv($out, [
        $r['username'],
        $r['nasipaddress'],
        $r['framedipaddress'],
        $r['acctstarttime'],
        $r['acctstoptime'] ?? '',
        (int)$r['acctsessiontime'],
        format_bytes($r['acctinputoctets']),
        format_bytes($r['acctoutputoctets']),
    ]);
}
fclose($out);
exit;
