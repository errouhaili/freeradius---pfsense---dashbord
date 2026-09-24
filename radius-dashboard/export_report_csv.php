<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'daily_usage';

$pdo = get_db();

header('Content-Type: text/csv; charset=utf-8');

if ($type === 'top_users') {
    header('Content-Disposition: attachment; filename="top_users_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['username', 'sessions', 'total_bytes', 'total_readable']);

    $stmt = $pdo->prepare("SELECT username, SUM(acctinputoctets+acctoutputoctets) total, COUNT(*) sessions
                            FROM radacct
                            WHERE DATE(acctstarttime) BETWEEN ? AND ?
                            GROUP BY username ORDER BY total DESC");
    $stmt->execute([$from, $to]);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['username'],
            (int)$row['sessions'],
            (int)$row['total'],
            format_bytes($row['total']),
        ]);
    }
    fclose($out);
    exit;
}

// type = daily_usage (par défaut)
header('Content-Disposition: attachment; filename="daily_usage_' . $from . '_' . $to . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['date', 'total_mb', 'sessions_count']);

$stmt = $pdo->prepare("SELECT DATE(acctstarttime) d, SUM(acctinputoctets+acctoutputoctets) total
                        FROM radacct
                        WHERE DATE(acctstarttime) BETWEEN ? AND ?
                        GROUP BY DATE(acctstarttime) ORDER BY d");
$stmt->execute([$from, $to]);
$daily = $stmt->fetchAll();

$sessionsPerDay = get_sessions_count_per_day($from, $to);
$sessionsByDate = [];
foreach ($sessionsPerDay as $r) {
    $sessionsByDate[$r['d']] = (int)$r['c'];
}

foreach ($daily as $row) {
    fputcsv($out, [
        $row['d'],
        round(($row['total'] ?? 0) / (1024 * 1024), 2),
        $sessionsByDate[$row['d']] ?? 0,
    ]);
}
fclose($out);
exit;
