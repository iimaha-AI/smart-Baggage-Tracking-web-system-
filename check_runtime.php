<?php
// Read-only prerequisite check; this does not initialize a database or authentication.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = __DIR__ . '/SmartBaggageTracking/SmartBaggageTrackingSystem_Pro';
$missing = [];
foreach (['config.php', 'database.php', 'auth.php', 'functions.php', 'security/role_guard.php'] as $file) {
    if (!is_file($root . '/includes/' . $file)) {
        $missing[] = 'includes/' . $file;
    }
}
foreach (['pdo', 'pdo_mysql'] as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = 'PHP extension: ' . $extension;
    }
}
if ($missing) {
    fwrite(STDERR, "Runtime prerequisites missing:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}
echo "Files and extensions present. Database schema, credentials and role behavior still require integration testing.\n";
