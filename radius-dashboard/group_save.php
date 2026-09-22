<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: groups.php'); exit; }
csrf_check();

$groupname = trim($_POST['groupname'] ?? '');
$original = trim($_POST['original_groupname'] ?? '');
if ($groupname === '') die('اسم المجموعة مطلوب');

$pdo = get_db();
$pdo->beginTransaction();
try {
    $target = $original !== '' ? $original : $groupname;

    $stmt = $pdo->prepare("DELETE FROM radgroupcheck WHERE groupname = ?");
    $stmt->execute([$target]);
    $stmt = $pdo->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
    $stmt->execute([$target]);

    $checkAttrs = $_POST['check_attr'] ?? [];
    $checkOps = $_POST['check_op'] ?? [];
    $checkVals = $_POST['check_value'] ?? [];
    $insCheck = $pdo->prepare("INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES (?,?,?,?)");
    foreach ($checkAttrs as $i => $attr) {
        $attr = trim($attr);
        if ($attr === '') continue;
        $insCheck->execute([$groupname, $attr, $checkOps[$i] ?? ':=', $checkVals[$i] ?? '']);
    }

    $replyAttrs = $_POST['reply_attr'] ?? [];
    $replyOps = $_POST['reply_op'] ?? [];
    $replyVals = $_POST['reply_value'] ?? [];
    $insReply = $pdo->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?,?,?,?)");
    foreach ($replyAttrs as $i => $attr) {
        $attr = trim($attr);
        if ($attr === '') continue;
        $insReply->execute([$groupname, $attr, $replyOps[$i] ?? '=', $replyVals[$i] ?? '']);
    }

    // لو تم تغيير الاسم، حدّث ارتباط المستخدمين بالمجموعة أيضاً
    if ($original !== '' && $original !== $groupname) {
        $stmt = $pdo->prepare("UPDATE radusergroup SET groupname = ? WHERE groupname = ?");
        $stmt->execute([$groupname, $original]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('خطأ أثناء الحفظ: ' . htmlspecialchars($e->getMessage()));
}

header('Location: groups.php?msg=' . urlencode(t('msg_group_saved')));
exit;
