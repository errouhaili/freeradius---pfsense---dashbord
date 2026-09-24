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
function save_user($username, $password, $groupname = null, $expiration = null,
                    $sessionTimeout = null, $bandwidthDown = null, $bandwidthUp = null) {
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

        // حصة الوقت لكل جلسة (Session-Timeout بالثواني) - مطبقة فعليا من طرف NAS
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Session-Timeout'");
        $stmt->execute([$username]);
        if (!empty($sessionTimeout)) {
            $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
            $stmt->execute([$username, (int)$sessionTimeout]);
        }

        // حدود التدفق (Bandwidth) - سمات ترسل للـ NAS عبر radreply
        $stmt = $pdo->prepare("DELETE FROM radreply WHERE username = ? AND attribute IN ('WISPr-Bandwidth-Max-Down','WISPr-Bandwidth-Max-Up')");
        $stmt->execute([$username]);
        if (!empty($bandwidthDown)) {
            $stmt = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'WISPr-Bandwidth-Max-Down', ':=', ?)");
            $stmt->execute([$username, (int)$bandwidthDown * 1000]);
        }
        if (!empty($bandwidthUp)) {
            $stmt = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'WISPr-Bandwidth-Max-Up', ':=', ?)");
            $stmt->execute([$username, (int)$bandwidthUp * 1000]);
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

/** جلب حصة الوقت الحالية لمستخدم (Session-Timeout بالثواني) */
function get_user_session_timeout($username) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT value FROM radcheck WHERE username = ? AND attribute = 'Session-Timeout' LIMIT 1");
    $stmt->execute([$username]);
    $v = $stmt->fetchColumn();
    return $v !== false ? (int)$v : null;
}

/** جلب حدود التدفق الحالية لمستخدم بالـ kbps (من radreply) */
function get_user_bandwidth($username) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT attribute, value FROM radreply WHERE username = ? AND attribute IN ('WISPr-Bandwidth-Max-Down','WISPr-Bandwidth-Max-Up')");
    $stmt->execute([$username]);
    $out = ['down' => null, 'up' => null];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['attribute'] === 'WISPr-Bandwidth-Max-Down') $out['down'] = (int)round($row['value'] / 1000);
        if ($row['attribute'] === 'WISPr-Bandwidth-Max-Up')   $out['up']   = (int)round($row['value'] / 1000);
    }
    return $out;
}

/** جلب سر NAS انطلاقا من عنوان IP (يُستعمل لإرسال أوامر CoA/Disconnect) */
function get_nas_secret_by_ip($ip) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT secret FROM nas WHERE nasname = ? LIMIT 1");
    $stmt->execute([$ip]);
    $v = $stmt->fetchColumn();
    return $v !== false ? $v : null;
}

/** عدد الجلسات النشطة مجمّعة حسب كل NAS (للرسم البياني في لوحة القيادة) */
function get_active_sessions_by_nas() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT nasipaddress, COUNT(*) c FROM radacct WHERE acctstoptime IS NULL GROUP BY nasipaddress ORDER BY c DESC");
    return $stmt->fetchAll();
}

/** عدد الجلسات (وليس حجم البيانات) في كل يوم ضمن فترة زمنية (للرسم البياني في التقارير) */
function get_sessions_count_per_day($from, $to) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT DATE(acctstarttime) d, COUNT(*) c
                            FROM radacct
                            WHERE DATE(acctstarttime) BETWEEN ? AND ?
                            GROUP BY DATE(acctstarttime) ORDER BY d");
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
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
