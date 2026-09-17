-- بيانات تجريبية لنظام تتبع الأمتعة الذكي

-- إدراج شركات طيران
INSERT INTO airlines (airline_name, airline_code, country, status) VALUES
('الخطوط الجوية السعودية', 'SV', 'السعودية', 'active'),
('طيران الإمارات', 'EK', 'الإمارات', 'active'),
('الخطوط الجوية القطرية', 'QR', 'قطر', 'active'),
('الخطوط الجوية الكويتية', 'KU', 'الكويت', 'active'),
('طيران ناس', 'XY', 'السعودية', 'active');

-- إدراج مطارات
INSERT INTO airports (airport_name, airport_code, city, country, status) VALUES
('مطار الملك خالد الدولي', 'RUH', 'الرياض', 'السعودية', 'active'),
('مطار الملك عبدالعزيز الدولي', 'JED', 'جدة', 'السعودية', 'active'),
('مطار الملك فهد الدولي', 'DMM', 'الدمام', 'السعودية', 'active'),
('مطار دبي الدولي', 'DXB', 'دبي', 'الإمارات', 'active'),
('مطار أبوظبي الدولي', 'AUH', 'أبوظبي', 'الإمارات', 'active'),
('مطار الدوحة الدولي', 'DOH', 'الدوحة', 'قطر', 'active'),
('مطار الكويت الدولي', 'KWI', 'الكويت', 'الكويت', 'active');

-- إدراج رحلات
INSERT INTO flights (flight_number, airline_id, origin_airport_id, destination_airport_id, departure_time, arrival_time, status) VALUES
('SV1001', 1, 1, 2, '2024-01-15 08:00:00', '2024-01-15 09:30:00', 'arrived'),
('SV1002', 1, 2, 1, '2024-01-15 10:00:00', '2024-01-15 11:30:00', 'arrived'),
('EK2001', 2, 4, 1, '2024-01-15 12:00:00', '2024-01-15 14:30:00', 'arrived'),
('QR3001', 3, 6, 1, '2024-01-15 16:00:00', '2024-01-15 18:30:00', 'arrived'),
('KU4001', 4, 7, 1, '2024-01-15 20:00:00', '2024-01-15 22:30:00', 'arrived'),
('SV1003', 1, 1, 3, '2024-01-16 06:00:00', '2024-01-16 07:30:00', 'scheduled'),
('SV1004', 1, 3, 1, '2024-01-16 08:30:00', '2024-01-16 10:00:00', 'scheduled'),
('EK2002', 2, 1, 4, '2024-01-16 14:00:00', '2024-01-16 16:30:00', 'scheduled');

-- إدراج مستخدمين
INSERT INTO users (full_name, email, password, role, status, created_at) VALUES
('أحمد محمد العلي', 'ahmed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'system_admin', 'active', NOW()),
('فاطمة أحمد السعد', 'fatima@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'airline_manager', 'active', NOW()),
('محمد عبدالله النور', 'mohammed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor', 'active', NOW()),
('نورا سعد الدوسري', 'nora@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'counter_staff', 'active', NOW()),
('خالد أحمد المطيري', 'khalid@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'handling_staff', 'active', NOW()),
('سارة محمد القحطاني', 'sara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger', 'active', NOW()),
('عبدالرحمن فهد الشمري', 'abdulrahman@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger', 'active', NOW()),
('مها الحربي', 'maha@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger', 'active', NOW());

-- إدراج أمتعة
INSERT INTO baggage (baggage_code, passenger_id, flight_id, weight, status, created_at, updated_at) VALUES
('SV001', 6, 1, 23.5, 'claimed', '2024-01-15 07:30:00', '2024-01-15 09:45:00'),
('SV002', 6, 1, 18.2, 'claimed', '2024-01-15 07:35:00', '2024-01-15 09:50:00'),
('SV003', 7, 2, 25.0, 'claimed', '2024-01-15 09:30:00', '2024-01-15 11:45:00'),
('EK001', 8, 3, 20.8, 'claimed', '2024-01-15 11:30:00', '2024-01-15 14:45:00'),
('EK002', 8, 3, 22.1, 'claimed', '2024-01-15 11:35:00', '2024-01-15 14:50:00'),
('QR001', 6, 4, 19.5, 'claimed', '2024-01-15 15:30:00', '2024-01-15 18:45:00'),
('KU001', 7, 5, 24.3, 'claimed', '2024-01-15 19:30:00', '2024-01-15 22:45:00'),
('SV004', 8, 6, 21.7, 'checked_in', '2024-01-16 05:30:00', '2024-01-16 05:30:00'),
('SV005', 6, 7, 17.9, 'security_check', '2024-01-16 07:30:00', '2024-01-16 07:45:00'),
('EK003', 7, 8, 23.2, 'loaded_to_aircraft', '2024-01-16 13:30:00', '2024-01-16 13:45:00');

-- إدراج إعدادات النظام
INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) VALUES
('system_name', 'نظام تتبع الأمتعة الذكي', 'اسم النظام', NOW(), NOW()),
('system_version', '2.0.0', 'إصدار النظام', NOW(), NOW()),
('max_baggage_weight', '32', 'الحد الأقصى لوزن الأمتعة بالكيلوجرام', NOW(), NOW()),
('tracking_update_interval', '5', 'فترة تحديث التتبع بالدقائق', NOW(), NOW()),
('smtp_host', 'smtp.gmail.com', 'خادم SMTP', NOW(), NOW()),
('smtp_port', '587', 'منفذ SMTP', NOW(), NOW()),
('auto_backup', 'true', 'النسخ الاحتياطي التلقائي', NOW(), NOW()),
('backup_frequency', 'daily', 'تكرار النسخ الاحتياطي', NOW(), NOW());