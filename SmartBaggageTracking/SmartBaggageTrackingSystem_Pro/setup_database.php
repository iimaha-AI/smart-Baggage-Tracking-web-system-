<?php
// تفعيل عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>إعداد شامل لقاعدة البيانات</h2>";

// إعدادات الاتصال
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'smart_baggage_pro';

echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>خطوات الإعداد:</h3>";
echo "<ol>";
echo "<li>تأكد من تشغيل XAMPP</li>";
echo "<li>ابدأ خدمة MySQL</li>";
echo "<li>إنشاء قاعدة البيانات</li>";
echo "<li>إنشاء الجداول</li>";
echo "<li>إضافة البيانات التجريبية</li>";
echo "</ol>";
echo "</div>";

// الخطوة 1: اختبار الاتصال بـ MySQL
echo "<h3>الخطوة 1: اختبار الاتصال بـ MySQL</h3>";
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<p style='color: green;'>✓ تم الاتصال بـ MySQL بنجاح</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ فشل الاتصال بـ MySQL</p>";
    echo "<p><strong>الخطأ:</strong> " . $e->getMessage() . "</p>";
    echo "<div style='background: #ffe6e6; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>الحل:</h4>";
    echo "<ol>";
    echo "<li>افتح XAMPP Control Panel</li>";
    echo "<li>اضغط على 'Start' بجانب MySQL</li>";
    echo "<li>انتظر حتى يصبح لونه أخضر</li>";
    echo "<li>أعد تحميل هذه الصفحة</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

// الخطوة 2: إنشاء قاعدة البيانات
echo "<h3>الخطوة 2: إنشاء قاعدة البيانات</h3>";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ تم إنشاء قاعدة البيانات '$dbname'</p>";
    $pdo->exec("USE `$dbname`");
    echo "<p style='color: green;'>✓ تم اختيار قاعدة البيانات</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ خطأ في إنشاء قاعدة البيانات: " . $e->getMessage() . "</p>";
    exit;
}

// الخطوة 3: إنشاء الجداول
echo "<h3>الخطوة 3: إنشاء الجداول</h3>";
$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `full_name` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `role` enum('passenger','counter_staff','handling_staff','supervisor','airline_manager','system_admin') NOT NULL,
        `passport_number` varchar(50) DEFAULT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `status` enum('active','inactive','suspended') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `last_login` timestamp NULL DEFAULT NULL,
        `login_attempts` int(11) DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'airlines' => "CREATE TABLE IF NOT EXISTS `airlines` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(10) NOT NULL UNIQUE,
        `airline_name` varchar(255) NOT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'airports' => "CREATE TABLE IF NOT EXISTS `airports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(10) NOT NULL UNIQUE,
        `name` varchar(255) NOT NULL,
        `city` varchar(100) NOT NULL,
        `country` varchar(100) NOT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'flights' => "CREATE TABLE IF NOT EXISTS `flights` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `flight_number` varchar(20) NOT NULL,
        `airline_id` int(11) NOT NULL,
        `origin_airport_id` int(11) NOT NULL,
        `destination_airport_id` int(11) NOT NULL,
        `departure_time` datetime NOT NULL,
        `arrival_time` datetime NOT NULL,
        `status` enum('scheduled','boarding','departed','in_air','arrived','delayed','cancelled') DEFAULT 'scheduled',
        `gate` varchar(10) DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `airline_id` (`airline_id`),
        KEY `origin_airport_id` (`origin_airport_id`),
        KEY `destination_airport_id` (`destination_airport_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'baggage_types' => "CREATE TABLE IF NOT EXISTS `baggage_types` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `type_name` varchar(100) NOT NULL,
        `max_weight` decimal(5,2) NOT NULL,
        `fee_per_kg` decimal(8,2) NOT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'baggage' => "CREATE TABLE IF NOT EXISTS `baggage` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `baggage_code` varchar(50) NOT NULL UNIQUE,
        `passenger_id` int(11) NOT NULL,
        `flight_id` int(11) NOT NULL,
        `baggage_type_id` int(11) NOT NULL,
        `weight` decimal(5,2) NOT NULL,
        `length` decimal(5,2) DEFAULT NULL,
        `width` decimal(5,2) DEFAULT NULL,
        `height` decimal(5,2) DEFAULT NULL,
        `color` varchar(50) DEFAULT NULL,
        `brand` varchar(100) DEFAULT NULL,
        `special_features` text DEFAULT NULL,
        `checkin_counter` varchar(20) DEFAULT NULL,
        `checkin_staff_id` int(11) DEFAULT NULL,
        `checkin_time` timestamp NULL DEFAULT NULL,
        `priority_level` enum('normal','priority','vip') DEFAULT 'normal',
        `fragile` tinyint(1) DEFAULT 0,
        `special_handling` text DEFAULT NULL,
        `total_fee` decimal(10,2) DEFAULT 0.00,
        `status` enum('checked_in','security_check','sorting_facility','loaded_to_aircraft','in_transit','unloaded_from_aircraft','at_carousel','claimed','delayed','lost') DEFAULT 'checked_in',
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `passenger_id` (`passenger_id`),
        KEY `flight_id` (`flight_id`),
        KEY `baggage_type_id` (`baggage_type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'tracking_logs' => "CREATE TABLE IF NOT EXISTS `tracking_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `baggage_id` int(11) NOT NULL,
        `location_id` int(11) DEFAULT NULL,
        `status` varchar(50) NOT NULL,
        `remarks` text DEFAULT NULL,
        `scanned_by` int(11) DEFAULT NULL,
        `scan_type` enum('manual','barcode','rfid') DEFAULT 'manual',
        `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `baggage_id` (`baggage_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$created_tables = 0;
foreach ($tables as $table_name => $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ تم إنشاء جدول '$table_name'</p>";
        $created_tables++;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ خطأ في إنشاء جدول '$table_name': " . $e->getMessage() . "</p>";
    }
}
echo "<p><strong>تم إنشاء $created_tables جدول من " . count($tables) . "</strong></p>";

// الخطوة 4: إضافة البيانات التجريبية
echo "<h3>الخطوة 4: إضافة البيانات التجريبية</h3>";
$airports = [
    ['JED', 'مطار الملك عبدالعزيز الدولي', 'جدة', 'السعودية'],
    ['RUH', 'مطار الملك خالد الدولي', 'الرياض', 'السعودية'],
    ['DMM', 'مطار الملك فهد الدولي', 'الدمام', 'السعودية'],
    ['MED', 'مطار الأمير محمد بن عبدالعزيز', 'المدينة المنورة', 'السعودية']
];
$added_airports = 0;
foreach ($airports as $airport) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airports (code, name, city, country) VALUES (?, ?, ?, ?)");
        $stmt->execute($airport);
        $added_airports++;
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ خطأ في إضافة مطار: " . $e->getMessage() . "</p>";
    }
}
echo "<p style='color: green;'>✓ تم إضافة $added_airports مطار</p>";
$airlines = [
    ['SV', 'الخطوط السعودية'],
    ['XY', 'طيران ناس'],
    ['FZ', 'فلاي دبي'],
    ['EK', 'الإمارات']
];
$added_airlines = 0;
foreach ($airlines as $airline) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airlines (code, airline_name) VALUES (?, ?)");
        $stmt->execute($airline);
        $added_airlines++;
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ خطأ في إضافة شركة طيران: " . $e->getMessage() . "</p>";
    }
}
echo "<p style='color: green;'>✓ تم إضافة $added_airlines شركة طيران</p>";
$baggage_types = [
    ['حقيبة يد', 7, 50],
    ['حقيبة سفر', 23, 100],
    ['حقيبة كبيرة', 32, 150],
    ['أمتعة رياضية', 25, 200]
];
$added_baggage_types = 0;
foreach ($baggage_types as $type) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO baggage_types (type_name, max_weight, fee_per_kg) VALUES (?, ?, ?)");
        $stmt->execute($type);
        $added_baggage_types++;
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ خطأ في إضافة نوع أمتعة: " . $e->getMessage() . "</p>";
    }
}
echo "<p style='color: green;'>✓ تم إضافة $added_baggage_types نوع أمتعة</p>";
$users = [
    ['أحمد محمد', 'ahmed@test.com', '123456', 'passenger', 'A1234567', '0501234567'],
    ['فاطمة علي', 'fatima@test.com', '123456', 'passenger', 'B2345678', '0502345678'],
    ['محمد أحمد', 'mohammed@test.com', '123456', 'counter_staff', 'C3456789', '0503456789'],
    ['نورا سعد', 'nora@test.com', '123456', 'handling_staff', 'D4567890', '0504567890'],
    ['سعد محمد', 'saad@test.com', '123456', 'supervisor', 'E5678901', '0505678901'],
    ['مدير النظام', 'admin@test.com', 'admin123', 'system_admin', 'F6789012', '0506789012']
];
$added_users = 0;
foreach ($users as $user_data) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user_data[1]]);
        if (!$stmt->fetch()) {
            $hashed_password = password_hash($user_data[2], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, passport_number, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_data[0], $user_data[1], $hashed_password, $user_data[3], $user_data[4], $user_data[5]]);
            $added_users++;
        }
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ خطأ في إضافة مستخدم: " . $e->getMessage() . "</p>";
    }
}
echo "<p style='color: green;'>✓ تم إضافة $added_users مستخدم</p>";

// الخطوة 5: التحقق النهائي
echo "<h3>الخطوة 5: التحقق النهائي</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM airports");
    $airport_count = $stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM airlines");
    $airline_count = $stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM baggage_types");
    $baggage_type_count = $stmt->fetch()['count'];
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h4>إحصائيات قاعدة البيانات:</h4><ul>";
    echo "<li>المستخدمين: $user_count</li>";
    echo "<li>المطارات: $airport_count</li>";
    echo "<li>شركات الطيران: $airline_count</li>";
    echo "<li>أنواع الأمتعة: $baggage_type_count</li></ul></div>";
    if ($user_count > 0) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
        echo "<h4 style='color: green;'>🎉 تم إعداد قاعدة البيانات بنجاح!</h4>";
        echo "<p>يمكنك الآن تسجيل الدخول باستخدام البيانات التالية:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f8f9fa;'><th>الدور</th><th>البريد الإلكتروني</th><th>كلمة المرور</th></tr>";
        foreach ($users as $user_data) {
            echo "<tr><td>" . $user_data[3] . "</td><td>" . $user_data[1] . "</td><td>" . $user_data[2] . "</td></tr>";
        }
        echo "</table></div>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ خطأ في التحقق النهائي: " . $e->getMessage() . "</p>";
}
echo "<hr><h3>الخطوات التالية:</h3><ol>";
echo "<li><a href='test_database_connection.php'>اختبار الاتصال بقاعدة البيانات</a></li>";
echo "<li><a href='pages/login.php'>تسجيل الدخول</a></li>";
echo "<li><a href='http://localhost/phpmyadmin' target='_blank'>فتح phpMyAdmin</a></li></ol>";
?>