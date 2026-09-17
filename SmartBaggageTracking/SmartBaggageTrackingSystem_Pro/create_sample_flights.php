<?php
/**
 * إنشاء بيانات رحلات تجريبية
 * SmartBaggageTrackingSystem_Pro
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // التحقق من وجود شركات الطيران
    $stmt = $pdo->query("SELECT COUNT(*) FROM airlines");
    $airlines_count = $stmt->fetchColumn();
    
    if ($airlines_count == 0) {
        echo "إنشاء شركات الطيران...\n";
        
        $airlines = [
            ['airline_name' => 'الخطوط السعودية', 'airline_code' => 'SV', 'status' => 'active'],
            ['airline_name' => 'طيران الإمارات', 'airline_code' => 'EK', 'status' => 'active'],
            ['airline_name' => 'الخطوط القطرية', 'airline_code' => 'QR', 'status' => 'active'],
            ['airline_name' => 'الخطوط الكويتية', 'airline_code' => 'KU', 'status' => 'active'],
            ['airline_name' => 'الخطوط العمانية', 'airline_code' => 'WY', 'status' => 'active']
        ];
        
        foreach ($airlines as $airline) {
            $stmt = $pdo->prepare("INSERT INTO airlines (airline_name, airline_code, status, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$airline['airline_name'], $airline['airline_code'], $airline['status']]);
        }
        echo "تم إنشاء " . count($airlines) . " شركة طيران\n";
    }
    
    // التحقق من وجود المطارات
    $stmt = $pdo->query("SELECT COUNT(*) FROM airports");
    $airports_count = $stmt->fetchColumn();
    
    if ($airports_count == 0) {
        echo "إنشاء المطارات...\n";
        
        $airports = [
            ['airport_name' => 'مطار الملك خالد الدولي', 'city' => 'الرياض', 'country' => 'السعودية', 'iata_code' => 'RUH', 'status' => 'active'],
            ['airport_name' => 'مطار الملك عبدالعزيز الدولي', 'city' => 'جدة', 'country' => 'السعودية', 'iata_code' => 'JED', 'status' => 'active'],
            ['airport_name' => 'مطار دبي الدولي', 'city' => 'دبي', 'country' => 'الإمارات', 'iata_code' => 'DXB', 'status' => 'active'],
            ['airport_name' => 'مطار أبوظبي الدولي', 'city' => 'أبوظبي', 'country' => 'الإمارات', 'iata_code' => 'AUH', 'status' => 'active'],
            ['airport_name' => 'مطار الدوحة الدولي', 'city' => 'الدوحة', 'country' => 'قطر', 'iata_code' => 'DOH', 'status' => 'active'],
            ['airport_name' => 'مطار الكويت الدولي', 'city' => 'الكويت', 'country' => 'الكويت', 'iata_code' => 'KWI', 'status' => 'active'],
            ['airport_name' => 'مطار مسقط الدولي', 'city' => 'مسقط', 'country' => 'عمان', 'iata_code' => 'MCT', 'status' => 'active'],
            ['airport_name' => 'مطار البحرين الدولي', 'city' => 'المنامة', 'country' => 'البحرين', 'iata_code' => 'BAH', 'status' => 'active']
        ];
        
        foreach ($airports as $airport) {
            $stmt = $pdo->prepare("INSERT INTO airports (airport_name, city, country, iata_code, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$airport['airport_name'], $airport['city'], $airport['country'], $airport['iata_code'], $airport['status']]);
        }
        echo "تم إنشاء " . count($airports) . " مطار\n";
    }
    
    // جلب شركات الطيران والمطارات
    $stmt = $pdo->query("SELECT id FROM airlines WHERE status = 'active'");
    $airline_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT id FROM airports WHERE status = 'active'");
    $airport_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // التحقق من وجود البوابات
    $stmt = $pdo->query("SELECT COUNT(*) FROM gates");
    $gates_count = $stmt->fetchColumn();
    
    if ($gates_count == 0) {
        echo "إنشاء البوابات التجريبية...\n";
        
        // إنشاء 20 بوابة تجريبية
        for ($i = 1; $i <= 20; $i++) {
            $gate_number = 'A' . $i;
            $gate_type = ['domestic', 'international', 'both'][array_rand(['domestic', 'international', 'both'])];
            $terminal_id = rand(1, 3);
            
            $stmt = $pdo->prepare("INSERT INTO gates (terminal_id, gate_number, gate_type, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
            $stmt->execute([$terminal_id, $gate_number, $gate_type]);
        }
        
        echo "تم إنشاء 20 بوابة تجريبية\n";
    }
    
    // جلب البوابات
    $stmt = $pdo->query("SELECT id FROM gates WHERE status = 'active'");
    $gate_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // التحقق من وجود الرحلات
    $stmt = $pdo->query("SELECT COUNT(*) FROM flights");
    $flights_count = $stmt->fetchColumn();
    
    if ($flights_count == 0) {
        echo "إنشاء الرحلات التجريبية...\n";
        
        $flight_statuses = ['scheduled', 'boarding', 'departed', 'delayed', 'cancelled'];
        
        // إنشاء 50 رحلة تجريبية
        for ($i = 1; $i <= 50; $i++) {
            $airline_id = $airline_ids[array_rand($airline_ids)];
            $origin_airport_id = $airport_ids[array_rand($airport_ids)];
            $destination_airport_id = $airport_ids[array_rand($airport_ids)];
            
            // التأكد من أن المطارين مختلفين
            while ($origin_airport_id == $destination_airport_id) {
                $destination_airport_id = $airport_ids[array_rand($airport_ids)];
            }
            
            $status = $flight_statuses[array_rand($flight_statuses)];
            $departure_gate_id = $gate_ids[array_rand($gate_ids)];
            $arrival_gate_id = $gate_ids[array_rand($gate_ids)];
            $aircraft_type = ['Boeing 737', 'Airbus A320', 'Boeing 777', 'Airbus A330'][array_rand(['Boeing 737', 'Airbus A320', 'Boeing 777', 'Airbus A330'])];
            $aircraft_registration = 'A' . rand(100, 999) . 'B';
            
            // إنشاء أوقات واقعية
            $departure_time = date('Y-m-d H:i:s', strtotime('+' . rand(1, 30) . ' days ' . rand(0, 23) . ' hours ' . rand(0, 59) . ' minutes'));
            $arrival_time = date('Y-m-d H:i:s', strtotime($departure_time . ' +' . rand(1, 8) . ' hours'));
            $duration_minutes = (strtotime($arrival_time) - strtotime($departure_time)) / 60;
            $delay_minutes = $status === 'delayed' ? rand(15, 180) : 0;
            
            $flight_number = 'SV' . str_pad($i, 3, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO flights (
                    flight_number, airline_id, origin_airport_id, destination_airport_id,
                    departure_time, arrival_time, duration_minutes, departure_gate_id, 
                    arrival_gate_id, status, delay_minutes, aircraft_type, aircraft_registration, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $flight_number, $airline_id, $origin_airport_id, $destination_airport_id,
                $departure_time, $arrival_time, $duration_minutes, $departure_gate_id, 
                $arrival_gate_id, $status, $delay_minutes, $aircraft_type, $aircraft_registration
            ]);
        }
        
        echo "تم إنشاء 50 رحلة تجريبية\n";
    }
    
    // إنشاء بيانات أمتعة تجريبية
    $stmt = $pdo->query("SELECT COUNT(*) FROM baggage");
    $baggage_count = $stmt->fetchColumn();
    
    if ($baggage_count == 0) {
        echo "إنشاء بيانات الأمتعة التجريبية...\n";
        
        // جلب الرحلات
        $stmt = $pdo->query("SELECT id FROM flights LIMIT 20");
        $flight_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // جلب المستخدمين
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'passenger' LIMIT 10");
        $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($user_ids)) {
            // إنشاء مستخدمين تجريبيين
            $passengers = [
                ['full_name' => 'أحمد محمد', 'email' => 'ahmed@example.com'],
                ['full_name' => 'فاطمة علي', 'email' => 'fatima@example.com'],
                ['full_name' => 'محمد عبدالله', 'email' => 'mohammed@example.com'],
                ['full_name' => 'نورا أحمد', 'email' => 'nora@example.com'],
                ['full_name' => 'خالد سعد', 'email' => 'khalid@example.com']
            ];
            
            foreach ($passengers as $passenger) {
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, created_at) VALUES (?, ?, ?, 'passenger', 'active', NOW())");
                $stmt->execute([$passenger['full_name'], $passenger['email'], password_hash('123456', PASSWORD_DEFAULT)]);
            }
            
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'passenger'");
            $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $baggage_statuses = ['checked_in', 'security_check', 'sorting_facility', 'loaded_to_aircraft', 'in_transit', 'arrived', 'claimed', 'lost'];
        
        // إنشاء 100 قطعة أمتعة
        for ($i = 1; $i <= 100; $i++) {
            $flight_id = $flight_ids[array_rand($flight_ids)];
            $passenger_id = $user_ids[array_rand($user_ids)];
            $status = $baggage_statuses[array_rand($baggage_statuses)];
            $weight = rand(15, 32);
            
            $baggage_code = 'BG' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                INSERT INTO baggage (
                    baggage_code, passenger_id, flight_id, weight, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$baggage_code, $passenger_id, $flight_id, $weight, $status]);
        }
        
        echo "تم إنشاء 100 قطعة أمتعة تجريبية\n";
    }
    
    echo "\n✅ تم إنشاء البيانات التجريبية بنجاح!\n";
    echo "يمكنك الآن زيارة صفحة إدارة الرحلات لرؤية البيانات الحقيقية.\n";
    
} catch (PDOException $e) {
    echo "خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
}
?>
