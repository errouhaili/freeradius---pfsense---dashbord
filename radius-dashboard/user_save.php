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
$password = $_POST['password'] ?? '';
$groupname = trim($_POST['groupname'] ?? '');
$expiration = trim($_POST['expiration'] ?? '');
$original = trim($_POST['original_username'] ?? '');

if ($username === '') {
    die('اسم المستخدم مطلوب');
}

// في حالة التعديل بدون كلمة سر جديدة: احتفظ بالقديمة
if ($original !== '' && $password === '') {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT value FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password' LIMIT 1");
    $stmt->execute([$original]);
    $password = $stmt->fetchColumn() ?: '';
}

save_user($username, $password, $groupname ?: null, $expiration ?: null);

header('Location: users.php?msg=' . urlencode(t('msg_user_saved')));
exit;
