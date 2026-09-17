<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy cookie file if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header("Location: ../pages/login.php");
exit;
?>
