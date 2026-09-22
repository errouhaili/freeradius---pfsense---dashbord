<?php
require_once __DIR__ . '/db.php';

/**
 * التحقق من تسجيل الدخول - إجبار المستخدم على الدخول قبل عرض أي صفحة محمية
 */
function require_login() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * محاولة تسجيل الدخول للوحة التحكم (جدول admins منفصل عن مستخدمي الراديوس)
 */
function attempt_login($username, $password) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    return false;
}

function current_admin() {
    return $_SESSION['admin_username'] ?? null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('فشل التحقق الأمني (CSRF). أعد تحميل الصفحة وحاول من جديد.');
    }
}
