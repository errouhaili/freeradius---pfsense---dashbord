<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sessions.php');
    exit;
}
csrf_check();

$radacctid = (int)($_POST['radacctid'] ?? 0);
if (!$radacctid) {
    header('Location: sessions.php?err=' . urlencode(t('err_invalid_session')));
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT username, nasipaddress, acctsessionid
                        FROM radacct WHERE radacctid = ? AND acctstoptime IS NULL LIMIT 1");
$stmt->execute([$radacctid]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: sessions.php?err=' . urlencode(t('err_session_not_found')));
    exit;
}

$secret = get_nas_secret_by_ip($session['nasipaddress']);
if (!$secret) {
    header('Location: sessions.php?err=' . urlencode(t('err_nas_secret_missing')));
    exit;
}

// Construction du paquet Disconnect-Request (RFC 3576/5176) via radclient
// (fourni par le paquet freeradius-utils, déjà installé avec FreeRADIUS).
$attrs = sprintf(
    "User-Name = \"%s\"\nAcct-Session-Id = \"%s\"\nNAS-IP-Address = %s\n",
    str_replace('"', '', $session['username']),
    str_replace('"', '', $session['acctsessionid']),
    $session['nasipaddress']
);

$cmd = sprintf(
    'echo %s | timeout 5 radclient -x %s:3799 disconnect %s 2>&1',
    escapeshellarg($attrs),
    escapeshellarg($session['nasipaddress']),
    escapeshellarg($secret)
);

$result = shell_exec($cmd) ?? '';
$success = (stripos($result, 'Disconnect-ACK') !== false);

if ($success) {
    header('Location: sessions.php?msg=' . urlencode(t('msg_disconnect_sent', ['username' => $session['username']])));
} else {
    header('Location: sessions.php?err=' . urlencode(t('err_disconnect_failed', ['username' => $session['username']])));
}
exit;
