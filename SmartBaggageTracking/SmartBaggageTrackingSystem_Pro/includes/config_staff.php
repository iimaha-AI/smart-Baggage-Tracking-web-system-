<?php
// إعدادات خاصة بنظام الموظفين
// Smart Baggage Tracking System - Staff Configuration

// إعدادات الأداء
define('STAFF_CACHE_ENABLED', true);
define('STAFF_CACHE_DURATION', 300); // 5 دقائق
define('STAFF_AUTO_REFRESH_INTERVAL', 30); // 30 ثانية
define('STAFF_NOTIFICATION_REFRESH', 60); // دقيقة واحدة

// إعدادات البحث
define('STAFF_SEARCH_MIN_LENGTH', 3);
define('STAFF_SEARCH_DELAY', 500); // ميلي ثانية
define('STAFF_SEARCH_RESULTS_LIMIT', 20);
define('STAFF_QUICK_SEARCH_LIMIT', 5);

// إعدادات التقارير
define('STAFF_REPORT_MAX_RECORDS', 1000);
define('STAFF_REPORT_DEFAULT_PERIOD', 'week');
define('STAFF_CHART_UPDATE_INTERVAL', 30); // ثانية

// إعدادات التتبع
define('STAFF_TRACKING_HISTORY_LIMIT', 50);
define('STAFF_REALTIME_UPDATE_INTERVAL', 10); // ثانية
define('STAFF_LOCATION_UPDATE_ENABLED', true);

// إعدادات الإشعارات
define('STAFF_NOTIFICATIONS_ENABLED', true);
define('STAFF_NOTIFICATION_SOUND', true);
define('STAFF_NOTIFICATION_POPUP', true);
define('STAFF_NOTIFICATION_DURATION', 5000); // ميلي ثانية

// إعدادات الأمان
define('STAFF_SESSION_TIMEOUT', 3600); // ساعة واحدة
define('STAFF_MAX_LOGIN_ATTEMPTS', 5);
define('STAFF_LOCKOUT_DURATION', 900); // 15 دقيقة
define('STAFF_PASSWORD_MIN_LENGTH', 8);

// إعدادات الواجهة
define('STAFF_THEME_DEFAULT', 'light');
define('STAFF_LANGUAGE_DEFAULT', 'ar');
define('STAFF_RTL_ENABLED', true);
define('STAFF_ANIMATIONS_ENABLED', true);

// إعدادات قاعدة البيانات
define('STAFF_DB_CONNECTION_POOL', 10);
define('STAFF_DB_QUERY_TIMEOUT', 30);
define('STAFF_DB_RETRY_ATTEMPTS', 3);

// إعدادات الملفات
define('STAFF_UPLOAD_MAX_SIZE', 5242880); // 5MB
define('STAFF_UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
define('STAFF_EXPORT_MAX_RECORDS', 10000);

// إعدادات التطوير
define('STAFF_DEBUG_MODE', false);
define('STAFF_ERROR_REPORTING', E_ALL);
define('STAFF_LOG_LEVEL', 'INFO');
define('STAFF_PROFILING_ENABLED', false);

// إعدادات API
define('STAFF_API_RATE_LIMIT', 100); // طلبات في الدقيقة
define('STAFF_API_TIMEOUT', 30); // ثانية
define('STAFF_API_CORS_ENABLED', true);

// إعدادات التخزين المؤقت
define('STAFF_CACHE_DRIVER', 'file'); // file, redis, memcached
define('STAFF_CACHE_PREFIX', 'staff_');
define('STAFF_CACHE_COMPRESSION', true);

// إعدادات المراقبة
define('STAFF_MONITORING_ENABLED', true);
define('STAFF_PERFORMANCE_MONITORING', true);
define('STAFF_ERROR_TRACKING', true);
define('STAFF_USAGE_ANALYTICS', true);

// إعدادات النسخ الاحتياطي
define('STAFF_BACKUP_ENABLED', true);
define('STAFF_BACKUP_FREQUENCY', 'daily');
define('STAFF_BACKUP_RETENTION', 30); // يوم
define('STAFF_BACKUP_COMPRESSION', true);

// إعدادات التحديثات
define('STAFF_AUTO_UPDATE_ENABLED', false);
define('STAFF_UPDATE_CHECK_INTERVAL', 86400); // يوم واحد
define('STAFF_MAINTENANCE_MODE', false);

// إعدادات الشبكة
define('STAFF_CDN_ENABLED', false);
define('STAFF_CDN_URL', '');
define('STAFF_COMPRESSION_ENABLED', true);
define('STAFF_MINIFICATION_ENABLED', true);

// إعدادات الأجهزة المحمولة
define('STAFF_MOBILE_OPTIMIZED', true);
define('STAFF_TOUCH_ENABLED', true);
define('STAFF_RESPONSIVE_DESIGN', true);

// إعدادات الوصول
define('STAFF_IP_WHITELIST_ENABLED', false);
define('STAFF_IP_WHITELIST', []);
define('STAFF_IP_BLACKLIST_ENABLED', false);
define('STAFF_IP_BLACKLIST', []);

// إعدادات التخصيص
define('STAFF_CUSTOM_BRANDING', false);
define('STAFF_CUSTOM_LOGO', '');
define('STAFF_CUSTOM_COLORS', []);
define('STAFF_CUSTOM_FONTS', []);

// دوال مساعدة للإعدادات
function getStaffConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

function setStaffConfig($key, $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}

function isStaffFeatureEnabled($feature) {
    $featureKey = 'STAFF_' . strtoupper($feature) . '_ENABLED';
    return getStaffConfig($featureKey, false);
}

function getStaffCacheKey($suffix = '') {
    return STAFF_CACHE_PREFIX . $suffix;
}

function getStaffApiEndpoint($action) {
    return '/includes/api/' . $action . '.php';
}

function getStaffAssetUrl($path) {
    $baseUrl = getStaffConfig('STAFF_CDN_URL', '');
    if ($baseUrl && getStaffConfig('STAFF_CDN_ENABLED', false)) {
        return $baseUrl . '/includes/assets/' . $path;
    }
    return '../includes/assets/' . $path;
}

// إعدادات خاصة بالبيئة
if (getStaffConfig('STAFF_DEBUG_MODE', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// إعدادات الجلسة
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', getStaffConfig('STAFF_SESSION_TIMEOUT', 3600));

// إعدادات الذاكرة والوقت
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 60);

// إعدادات التخزين المؤقت
if (getStaffConfig('STAFF_CACHE_ENABLED', true)) {
    if (getStaffConfig('STAFF_CACHE_DRIVER', 'file') === 'file') {
        $cacheDir = __DIR__ . '/../cache/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }
}

// تسجيل بداية التحميل
if (getStaffConfig('STAFF_PROFILING_ENABLED', false)) {
    $GLOBALS['staff_start_time'] = microtime(true);
    $GLOBALS['staff_start_memory'] = memory_get_usage();
}

// دالة لإنهاء المراقبة
function endStaffProfiling() {
    if (getStaffConfig('STAFF_PROFILING_ENABLED', false)) {
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $executionTime = $endTime - $GLOBALS['staff_start_time'];
        $memoryUsage = $endMemory - $GLOBALS['staff_start_memory'];
        
        error_log("Staff System Performance - Execution Time: {$executionTime}s, Memory Usage: " . 
                 formatBytes($memoryUsage));
    }
}

// دالة لتنسيق البايتات
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// تسجيل نهاية التحميل
register_shutdown_function('endStaffProfiling');
?>
