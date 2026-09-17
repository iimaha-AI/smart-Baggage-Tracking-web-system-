<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();
$pdo = $db->getConnection();

echo "<h2>Adding Sample Data</h2>";

try {
    // Add airports
    $airports = [
        ['JED', 'King Abdulaziz International Airport', 'Jeddah', 'Saudi Arabia'],
        ['RUH', 'King Khalid International Airport', 'Riyadh', 'Saudi Arabia'],
        ['DMM', 'مطار الملك فهد الدولي', 'الدمام', 'السعودية'],
        ['MED', 'مطار الأمير محمد بن عبدالعزيز', 'المدينة المنورة', 'السعودية']
    ];
    
    foreach ($airports as $airport) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airports (airport_code, airport_name, city, country) VALUES (?, ?, ?, ?)");
        $stmt->execute($airport);
    }
    echo "<p style='color: green;'>تم إضافة المطارات</p>";
    
    // إضافة شركات طيران
    $airlines = [
        ['SV', 'الخطوط السعودية'],
        ['XY', 'طيران ناس'],
        ['FZ', 'فلاي دبي'],
        ['EK', 'الإمارات']
    ];
    
    foreach ($airlines as $airline) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO airlines (airline_code, airline_name) VALUES (?, ?)");
        $stmt->execute($airline);
    }
    echo "<p style='color: green;'>تم إضافة شركات الطيران</p>";
    
    // إضافة رحلات
    $flights = [
        ['SV1001', 1, 1, 2, '2024-01-15 08:00:00', '2024-01-15 10:30:00', 'scheduled'],
        ['SV1002', 1, 2, 1, '2024-01-15 14:00:00', '2024-01-15 16:30:00', 'scheduled'],
        ['XY2001', 2, 1, 3, '2024-01-15 10:00:00', '2024-01-15 12:00:00', 'scheduled'],
        ['FZ3001', 3, 2, 4, '2024-01-15 16:00:00', '2024-01-15 18:00:00', 'scheduled']
    ];
    
    foreach ($flights as $flight) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO flights (flight_number, airline_id, origin_airport_id, destination_airport_id, departure_time, arrival_time, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($flight);
    }
    echo "<p style='color: green;'>تم إضافة الرحلات</p>";
    
    // إضافة أنواع أمتعة
    $baggage_types = [
        ['حقيبة يد', 7, 50, 'active'],
        ['حقيبة سفر', 23, 100, 'active'],
        ['حقيبة كبيرة', 32, 150, 'active'],
        ['أمتعة رياضية', 25, 200, 'active']
    ];
    
    foreach ($baggage_types as $type) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO baggage_types (type_name, max_weight, fee_per_kg, status) VALUES (?, ?, ?, ?)");
        $stmt->execute($type);
    }
    echo "<p style='color: green;'>تم إضافة أنواع الأمتعة</p>";
    
    // إضافة مسافرين تجريبيين
    $passengers = [
        ['أحمد محمد', 'A1234567', 'ahmed@example.com', 'passenger', 'active'],
        ['فاطمة علي', 'B2345678', 'fatima@example.com', 'passenger', 'active'],
        ['محمد أحمد', 'C3456789', 'mohammed@example.com', 'passenger', 'active'],
        ['نورا سعد', 'D4567890', 'nora@example.com', 'passenger', 'active']
    ];
    
    foreach ($passengers as $passenger) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (full_name, passport_number, email, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($passenger);
    }
    echo "<p style='color: green;'>تم إضافة المسافرين التجريبيين</p>";
    
    echo "<h3 style='color: green;'>تم إضافة جميع البيانات التجريبية بنجاح!</h3>";
    echo "<p><a href='test_data.php'>عرض البيانات</a></p>";
    echo "<p><a href='staff/baggage_register.php'>العودة لصفحة تسجيل الأمتعة</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>خطأ: " . $e->getMessage() . "</p>";
}
?>

