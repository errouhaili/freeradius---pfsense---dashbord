<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';

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
 * محاولة تسجيل الدخول للوحة التحكم (جدول admins منفصل عن مستخدمي الراديوس).
 * القيم الممكنة للإرجاع:
 *  - 'ok'      : تم تسجيل الدخول بنجاح (بدون 2FA)
 *  - 'need_2fa': اسم المستخدم/كلمة السر صحيحان، لكن التحقق بخطوتين مفعّل
 *  - 'fail'    : بيانات الدخول خاطئة
 */
function attempt_login($username, $password) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return 'fail';
    }

    if (!empty($admin['totp_enabled'])) {
        // بيانات الدخول صحيحة، لكن يجب إدخال رمز التحقق بخطوتين أولا.
        $_SESSION['pending_2fa_admin_id'] = $admin['id'];
        return 'need_2fa';
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    return 'ok';
}

/** التحقق من رمز TOTP لإكمال تسجيل الدخول بعد مرحلة اسم المستخدم/كلمة السر */
function attempt_2fa_verify($code) {
    if (empty($_SESSION['pending_2fa_admin_id'])) {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['pending_2fa_admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || empty($admin['totp_secret'])) {
        return false;
    }

    if (TOTP::verify($admin['totp_secret'], $code)) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        unset($_SESSION['pending_2fa_admin_id']);
        return true;
    }
    return false;
}

/** جلب بيانات المشرف الحالي (يشمل حالة 2FA) */
function get_current_admin_row() {
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
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
