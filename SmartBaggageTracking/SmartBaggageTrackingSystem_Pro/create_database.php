<?php
// تفعيل عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>إنشاء قاعدة البيانات</h2>";

// إعدادات الاتصال
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // الاتصال بـ MySQL بدون تحديد قاعدة بيانات
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "<p style='color: green;'>✓ تم الاتصال بـ MySQL بنجاح</p>";
    
    // إنشاء قاعدة البيانات
    $dbname = 'smart_baggage_pro';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ تم إنشاء قاعدة البيانات '$dbname' بنجاح</p>";
    
    // اختيار قاعدة البيانات
    $pdo->exec("USE `$dbname`");
    echo "<p style='color: green;'>✓ تم اختيار قاعدة البيانات '$dbname'</p>";
    
    // قراءة ملف schema.sql
    $schema_file = 'schema.sql';
    if (file_exists($schema_file)) {
        echo "<p>تم العثور على ملف schema.sql</p>";
        
        $sql = file_get_contents($schema_file);
        
        // تقسيم SQL إلى استعلامات منفصلة
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                try {
                    $pdo->exec($statement);
                    $success_count++;
                } catch (PDOException $e) {
                    $error_count++;
                    echo "<p style='color: orange;'>⚠ تحذير في الاستعلام: " . substr($statement, 0, 50) . "...</p>";
                    echo "<p style='color: gray;'>الخطأ: " . $e->getMessage() . "</p>";
                }
            }
        }
        
        echo "<p style='color: green;'>✓ تم تنفيذ $success_count استعلام بنجاح</p>";
        if ($error_count > 0) {
            echo "<p style='color: orange;'>⚠ تم تجاهل $error_count استعلام بسبب أخطاء</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ لم يتم العثور على ملف schema.sql</p>";
        echo "<p>سيتم إنشاء الجداول الأساسية يدوياً...</p>";
        
        // إنشاء الجداول الأساسية
        $tables = [
            "CREATE TABLE IF NOT EXISTS `users` (
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
            
            "CREATE TABLE IF NOT EXISTS `airlines` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `code` varchar(10) NOT NULL UNIQUE,
                `airline_name` varchar(255) NOT NULL,
                `status` enum('active','inactive') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS `airports` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `code` varchar(10) NOT NULL UNIQUE,
                `name` varchar(255) NOT NULL,
                `city` varchar(100) NOT NULL,
                `country` varchar(100) NOT NULL,
                `status` enum('active','inactive') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS `flights` (
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
            
            "CREATE TABLE IF NOT EXISTS `baggage_types` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type_name` varchar(100) NOT NULL,
                `max_weight` decimal(5,2) NOT NULL,
                `fee_per_kg` decimal(8,2) NOT NULL,
                `status` enum('active','inactive') DEFAULT 'active',
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            "CREATE TABLE IF NOT EXISTS `baggage` (
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
            
            "CREATE TABLE IF NOT EXISTS `tracking_logs` (
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
        
        foreach ($tables as $table_sql) {
            try {
                $pdo->exec($table_sql);
                echo "<p style='color: green;'>✓ تم إنشاء جدول بنجاح</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ خطأ في إنشاء الجدول: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // إضافة بيانات تجريبية
    echo "<h3>إضافة بيانات تجريبية...</h3>";
    
    // إضافة مطارات
    $airports = [
        ['JED', 'مطار الملك عبدالعزيز الدولي', 'جدة', 'السعودية'],
        ['RUH', 'مطار الملك خالد الدولي', 'الرياض', 'السعودية'],
        ['DMM', 'مطار الملك فهد الدولي', 'الدمام', 'السعودية'],
        ['MED', 'مطار الأمير محمد بن عبدالعزيز', 'المدينة المنورة', 'السعودية']
    ];
    
    foreach ($airports as $airport) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airports (code, name, city, country) VALUES (?, ?, ?, ?)");
        $stmt->execute($airport);
    }
    echo "<p style='color: green;'>✓ تم إضافة المطارات</p>";
    
    // إضافة شركات طيران
    $airlines = [
        ['SV', 'الخطوط السعودية'],
        ['XY', 'طيران ناس'],
        ['FZ', 'فلاي دبي'],
        ['EK', 'الإمارات']
    ];
    
    foreach ($airlines as $airline) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airlines (code, airline_name) VALUES (?, ?)");
        $stmt->execute($airline);
    }
    echo "<p style='color: green;'>✓ تم إضافة شركات الطيران</p>";
    
    // إضافة أنواع أمتعة
    $baggage_types = [
        ['حقيبة يد', 7, 50],
        ['حقيبة سفر', 23, 100],
        ['حقيبة كبيرة', 32, 150],
        ['أمتعة رياضية', 25, 200]
    ];
    
    foreach ($baggage_types as $type) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO baggage_types (type_name, max_weight, fee_per_kg) VALUES (?, ?, ?)");
        $stmt->execute($type);
    }
    echo "<p style='color: green;'>✓ تم إضافة أنواع الأمتعة</p>";
    
    // إضافة مستخدمين تجريبيين
    $users = [
        ['أحمد محمد', 'ahmed@test.com', '123456', 'passenger', 'A1234567', '0501234567'],
        ['فاطمة علي', 'fatima@test.com', '123456', 'passenger', 'B2345678', '0502345678'],
        ['محمد أحمد', 'mohammed@test.com', '123456', 'counter_staff', 'C3456789', '0503456789'],
        ['نورا سعد', 'nora@test.com', '123456', 'handling_staff', 'D4567890', '0504567890'],
        ['سعد محمد', 'saad@test.com', '123456', 'supervisor', 'E5678901', '0505678901'],
        ['مدير النظام', 'admin@test.com', 'admin123', 'system_admin', 'F6789012', '0506789012']
    ];
    
    foreach ($users as $user_data) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user_data[1]]);
        
        if (!$stmt->fetch()) {
            $hashed_password = password_hash($user_data[2], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, passport_number, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_data[0], $user_data[1], $hashed_password, $user_data[3], $user_data[4], $user_data[5]]);
        }
    }
    echo "<p style='color: green;'>✓ تم إضافة المستخدمين التجريبيين</p>";
    
    echo "<h3 style='color: green;'>🎉 تم إعداد قاعدة البيانات بنجاح!</h3>";
    
    echo "<h4>بيانات تسجيل الدخول:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>الدور</th><th>البريد الإلكتروني</th><th>كلمة المرور</th></tr>";
    foreach ($users as $user_data) {
        echo "<tr>";
        echo "<td>" . $user_data[3] . "</td>";
        echo "<td>" . $user_data[1] . "</td>";
        echo "<td>" . $user_data[2] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<p><a href='test_database_connection.php'>اختبار الاتصال</a></p>";
    echo "<p><a href='pages/login.php'>تسجيل الدخول</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ خطأ في إنشاء قاعدة البيانات!</p>";
    echo "<p><strong>الخطأ:</strong> " . $e->getMessage() . "</p>";
    
    if ($e->getCode() == 2002) {
        echo "<h3>المشكلة: لا يمكن الاتصال بـ MySQL</h3>";
        echo "<h4>الحلول:</h4>";
        echo "<ol>";
        echo "<li>افتح XAMPP Control Panel</li>";
        echo "<li>اضغط على 'Start' بجانب MySQL</li>";
        echo "<li>تأكد من أن MySQL يعمل (أخضر)</li>";
        echo "<li>أعد تحميل هذه الصفحة</li>";
        echo "</ol>";
    }
}
?>
