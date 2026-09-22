<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: groups.php'); exit; }
csrf_check();

$groupname = trim($_POST['groupname'] ?? '');
if ($groupname !== '') {
    $pdo = get_db();
    foreach (['radgroupcheck', 'radgroupreply', 'radusergroup'] as $table) {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE groupname = ?");
        $stmt->execute([$groupname]);
    }
}
header('Location: groups.php?msg=' . urlencode(t('msg_group_deleted')));
exit;
