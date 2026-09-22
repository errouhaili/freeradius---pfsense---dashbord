<?php
require_once __DIR__ . '/db.php';

/** تنسيق حجم البيانات (بايت -> KB/MB/GB) */
function format_bytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes, 1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/** تنسيق المدة الزمنية بالثواني إلى صيغة قابلة للقراءة */
function format_duration($seconds) {
    $seconds = (int)$seconds;
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

/** جلب كل المستخدمين مع كلمة السر وحالة التفعيل والمجموعة */
function get_all_users($search = '') {
    $pdo = get_db();
    $sql = "SELECT DISTINCT rc.username,
                   (SELECT value FROM radcheck WHERE username = rc.username AND attribute = 'Cleartext-Password' LIMIT 1) AS password,
                   (SELECT groupname FROM radusergroup WHERE username = rc.username ORDER BY priority LIMIT 1) AS groupname,
                   (SELECT value FROM radcheck WHERE username = rc.username AND attribute = 'Expiration' LIMIT 1) AS expiration
            FROM radcheck rc";
    $params = [];
    if ($search !== '') {
        $sql .= " WHERE rc.username LIKE ?";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY rc.username ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** التحقق هل المستخدم نشط الآن (جلسة بدون وقت انتهاء) */
function get_active_username_set() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT DISTINCT username FROM radacct WHERE acctstoptime IS NULL");
    return array_column($stmt->fetchAll(), 'username');
}

/** إضافة أو تحديث مستخدم راديوس */
function save_user($username, $password, $groupname = null, $expiration = null) {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        // كلمة السر (Cleartext-Password)
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'");
        $stmt->execute([$username]);
        if ($password !== null && $password !== '') {
            $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $stmt->execute([$username, $password]);
        }

        // تاريخ الانتهاء (اختياري)
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'");
        $stmt->execute([$username]);
        if (!empty($expiration)) {
            $formatted = date('d M Y', strtotime($expiration));
            $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
            $stmt->execute([$username, $formatted]);
        }

        // المجموعة
        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username = ?");
        $stmt->execute([$username]);
        if (!empty($groupname)) {
            $stmt = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
            $stmt->execute([$username, $groupname]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** حذف مستخدم راديوس بالكامل من كل الجداول المرتبطة */
function delete_user($username) {
    $pdo = get_db();
    foreach (['radcheck', 'radreply', 'radusergroup'] as $table) {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE username = ?");
        $stmt->execute([$username]);
    }
}

/** جلب كل المجموعات الموجودة */
function get_all_groups() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT DISTINCT groupname FROM radgroupcheck
                          UNION SELECT DISTINCT groupname FROM radgroupreply
                          UNION SELECT DISTINCT groupname FROM radusergroup
                          ORDER BY groupname");
    return array_column($stmt->fetchAll(), 'groupname');
}
