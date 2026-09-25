<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: nas.php'); exit; }
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$nasname = trim($_POST['nasname'] ?? '');
$shortname = trim($_POST['shortname'] ?? '');
$type = trim($_POST['type'] ?? 'other');
$ports = trim($_POST['ports'] ?? '');
$secret = trim($_POST['secret'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($nasname === '' || $shortname === '' || $secret === '') {
    die('الحقول المطلوبة (IP، الاسم المختصر، السر المشترك) إلزامية');
}

$pdo = get_db();
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE nas SET nasname=?, shortname=?, type=?, ports=?, secret=?, description=? WHERE id=?");
    $stmt->execute([$nasname, $shortname, $type, $ports ?: null, $secret, $description, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO nas (nasname, shortname, type, ports, secret, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$nasname, $shortname, $type, $ports ?: null, $secret, $description]);
}

header('Location: nas.php?msg=' . urlencode(t('msg_nas_saved')));
exit;
