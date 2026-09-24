<?php
/**
 * نظام تعدد اللغات: عربي (ar) / فرنسي (fr) / إنجليزي (en)
 */

define('SUPPORTED_LANGS', ['ar', 'fr', 'en']);

function load_language() {
    if (!empty($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['lang'] ?? 'ar';
    if (!in_array($lang, SUPPORTED_LANGS, true)) {
        $lang = 'ar';
    }
    $_SESSION['lang'] = $lang;

    $file = __DIR__ . '/lang/' . $lang . '.php';
    $GLOBALS['__translations'] = file_exists($file) ? include $file : [];
    $GLOBALS['__current_lang'] = $lang;
}

/** إرجاع النص المترجم مع دعم متغيرات مثل {username} */
function t($key, $params = []) {
    $translations = $GLOBALS['__translations'] ?? [];
    $text = $translations[$key] ?? $key;
    foreach ($params as $k => $v) {
        $text = str_replace('{' . $k . '}', $v, $text);
    }
    return $text;
}

function current_lang() {
    return $GLOBALS['__current_lang'] ?? 'ar';
}

function is_rtl() {
    return current_lang() === 'ar';
}

/** بناء رابط تبديل اللغة مع الحفاظ على باقي معاملات GET الحالية */
function lang_switch_url($lang) {
    $params = $_GET;
    $params['lang'] = $lang;
    $page = basename($_SERVER['PHP_SELF']);
    return $page . '?' . http_build_query($params);
}
