<?php
// System settings
define('SITE_NAME', 'Smart Baggage Tracking System');
define('SITE_VERSION', '2.0.0');
define('BASE_URL', 'http://localhost/SmartBaggageTrackingSystem_Pro');

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_baggage_pro');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@airport.com');
define('SMTP_PASS', '');

// System settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600); // 1 ساعة

// دوال مساعدة
function getSetting($key) {
    $settings = [
        'system_name' => 'نظام تتبع الأمتعة الذكي',
        'maintenance_mode' => false,
        'max_baggage_weight' => 32,
        'tracking_update_interval' => 5
    ];
    return $settings[$key] ?? null;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}
?>