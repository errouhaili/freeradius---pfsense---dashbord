<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}
csrf_check();

$username = trim($_POST['username'] ?? '');
if ($username !== '') {
    delete_user($username);
}

header('Location: users.php?msg=' . urlencode(t('msg_user_deleted')));
exit;
