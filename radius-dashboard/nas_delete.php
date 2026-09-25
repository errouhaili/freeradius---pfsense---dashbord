<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: nas.php'); exit; }
csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM nas WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: nas.php?msg=' . urlencode(t('msg_nas_deleted')));
exit;
