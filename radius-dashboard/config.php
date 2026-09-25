<?php
/**
 * ملف الإعدادات العامة - عدّل القيم حسب بيئتك
 */

// إعدادات قاعدة بيانات FreeRADIUS (الجداول: radcheck, radreply, radusergroup, radgroupcheck, radgroupreply, radacct, nas)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'radius');
define('DB_USER', 'radius');
define('DB_PASS', 'er123');

// اسم لوحة التحكم
define('APP_NAME', 'لوحة إدارة FreeRADIUS / pfSense');

// المنطقة الزمنية
date_default_timezone_set('Africa/Casablanca');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل نظام تعدد اللغات (عربي/فرنسي/إنجليزي)
require_once __DIR__ . '/includes/i18n.php';
load_language();
