-- =============================================
-- Smart Baggage Tracking System
-- Advanced Database
-- =============================================

SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS smart_baggage_pro;
CREATE DATABASE smart_baggage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_baggage_pro;

-- =============================================
-- Basic Tables
-- =============================================

-- 👥 Airlines Table
CREATE TABLE airlines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airline_code VARCHAR(3) UNIQUE NOT NULL,
    airline_name VARCHAR(100) NOT NULL,
    iata_code VARCHAR(2),
    icao_code VARCHAR(3),
    logo VARCHAR(255),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    headquarters VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 👤 Advanced Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    passport_number VARCHAR(20),
    nationality VARCHAR(50),
    date_of_birth DATE,
    phone_number VARCHAR(20),
    avatar VARCHAR(255),
    
    -- Role and permissions information
    role ENUM('passenger', 'counter_staff', 'handling_staff', 'supervisor', 'airline_manager', 'system_admin') DEFAULT 'passenger',
    airline_id INT,
    department VARCHAR(50),
    position VARCHAR(50),
    
    -- User settings
    language ENUM('ar', 'en') DEFAULT 'ar',
    theme ENUM('light', 'dark') DEFAULT 'light',
    notifications_enabled BOOLEAN DEFAULT true,
    
    -- Account status
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT false,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (airline_id) REFERENCES airlines(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- 🏢 Airports Table
CREATE TABLE airports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airport_code VARCHAR(3) UNIQUE NOT NULL,
    airport_name VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    country VARCHAR(50) NOT NULL,
    timezone VARCHAR(50),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    contact_phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🏗️ جدول الصالات والمباني
CREATE TABLE terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    airport_id INT NOT NULL,
    terminal_code VARCHAR(5) NOT NULL,
    terminal_name VARCHAR(50) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive', 'under_maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (airport_id) REFERENCES airports(id) ON DELETE CASCADE,
    UNIQUE KEY unique_terminal (airport_id, terminal_code)
);

-- 🚪 جدول البوابات
CREATE TABLE gates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    gate_number VARCHAR(10) NOT NULL,
    gate_type ENUM('domestic', 'international', 'both') DEFAULT 'domestic',
    status ENUM('active', 'inactive', 'under_maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_gate (terminal_id, gate_number)
);

-- 🛬 جدول الرحلات المتطور
CREATE TABLE flights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(10) NOT NULL,
    airline_id INT NOT NULL,
    
    -- معلومات الرحلة
    origin_airport_id INT NOT NULL,
    destination_airport_id INT NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    duration_minutes INT,
    
    -- معلومات البوابة
    departure_gate_id INT,
    arrival_gate_id INT,
    
    -- حالة الرحلة
    status ENUM('scheduled', 'boarding', 'departed', 'in_air', 'landed', 'arrived', 'canceled', 'delayed') DEFAULT 'scheduled',
    delay_minutes INT DEFAULT 0,
    
    -- معلومات الطائرة
    aircraft_type VARCHAR(50),
    aircraft_registration VARCHAR(20),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (airline_id) REFERENCES airlines(id) ON DELETE CASCADE,
    FOREIGN KEY (origin_airport_id) REFERENCES airports(id),
    FOREIGN KEY (destination_airport_id) REFERENCES airports(id),
    FOREIGN KEY (departure_gate_id) REFERENCES gates(id),
    FOREIGN KEY (arrival_gate_id) REFERENCES gates(id),
    
    INDEX idx_flight_number (flight_number),
    INDEX idx_departure_time (departure_time),
    INDEX idx_status (status)
);

-- 🛄 جدول أنواع الأمتعة
CREATE TABLE baggage_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_code VARCHAR(10) UNIQUE NOT NULL,
    type_name VARCHAR(50) NOT NULL,
    description TEXT,
    max_weight DECIMAL(5,2),
    max_length INT,
    max_width INT,
    max_height INT,
    fee_per_kg DECIMAL(8,2) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 🎒 جدول الأمتعة المتطور
CREATE TABLE baggage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- المعلومات الأساسية
    baggage_code VARCHAR(20) UNIQUE NOT NULL,
    passenger_id INT NOT NULL,
    flight_id INT NOT NULL,
    
    -- معلومات الحقيبة
    baggage_type_id INT NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    length INT,
    width INT,
    height INT,
    color VARCHAR(30),
    brand VARCHAR(50),
    special_features TEXT,
    
    -- معلومات المعالجة
    checkin_counter VARCHAR(10),
    checkin_staff_id INT,
    checkin_time DATETIME,
    
    -- حالة الأمتعة
    status ENUM(
        'checked_in',
        'security_check',
        'sorting_facility',
        'loaded_to_aircraft',
        'in_transit',
        'unloaded_from_aircraft',
        'at_carousel',
        'claimed',
        'delayed',
        'lost'
    ) DEFAULT 'checked_in',
    
    -- معلومات الأولوية
    priority_level ENUM('normal', 'priority', 'vip') DEFAULT 'normal',
    fragile BOOLEAN DEFAULT false,
    special_handling TEXT,
    
    -- التكاليف
    excess_fee DECIMAL(8,2) DEFAULT 0,
    total_fee DECIMAL(8,2) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (flight_id) REFERENCES flights(id) ON DELETE CASCADE,
    FOREIGN KEY (baggage_type_id) REFERENCES baggage_types(id),
    FOREIGN KEY (checkin_staff_id) REFERENCES users(id),
    
    INDEX idx_baggage_code (baggage_code),
    INDEX idx_status (status),
    INDEX idx_passenger (passenger_id),
    INDEX idx_flight (flight_id)
);

-- 📍 جدول مواقع التتبع
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_code VARCHAR(20) UNIQUE NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    location_type ENUM(
        'checkin_counter',
        'security_checkpoint',
        'sorting_facility',
        'loading_ramp',
        'aircraft_hold',
        'unloading_ramp',
        'baggage_carousel',
        'storage_room',
        'customs'
    ) NOT NULL,
    terminal_id INT,
    gate_id INT,
    conveyor_belt VARCHAR(20),
    x_coordinate DECIMAL(10, 2),
    y_coordinate DECIMAL(10, 2),
    floor INT,
    status ENUM('active', 'inactive', 'under_maintenance') DEFAULT 'active',
    
    FOREIGN KEY (terminal_id) REFERENCES terminals(id),
    FOREIGN KEY (gate_id) REFERENCES gates(id),
    
    INDEX idx_location_type (location_type)
);

-- 📱 جدول الأجهزة
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) UNIQUE NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    device_type ENUM('scanner', 'tablet', 'mobile', 'desktop', 'kiosk') NOT NULL,
    mac_address VARCHAR(17),
    ip_address VARCHAR(45),
    location_id INT,
    status ENUM('online', 'offline', 'maintenance') DEFAULT 'offline',
    last_seen TIMESTAMP NULL,
    battery_level INT,
    software_version VARCHAR(20),
    
    FOREIGN KEY (location_id) REFERENCES locations(id),
    
    INDEX idx_device_type (device_type),
    INDEX idx_status (status)
);

-- 🔄 جدول سجل التتبع المتطور
CREATE TABLE tracking_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    baggage_id INT NOT NULL,
    
    -- معلومات الموقع
    location_id INT NOT NULL,
    scan_type ENUM('manual', 'barcode', 'rfid', 'qr_code') DEFAULT 'barcode',
    
    -- معلومات المسح
    scanned_by INT,
    device_id INT,
    gps_latitude DECIMAL(10, 8),
    gps_longitude DECIMAL(11, 8),
    
    -- حالة الأمتعة
    status VARCHAR(100) NOT NULL,
    sub_status VARCHAR(100),
    remarks TEXT,
    
    -- التوقيتات
    expected_next_scan DATETIME,
    actual_scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- بيانات الأداء
    processing_time_seconds INT,
    is_delayed BOOLEAN DEFAULT false,
    delay_reason TEXT,
    
    FOREIGN KEY (baggage_id) REFERENCES baggage(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (scanned_by) REFERENCES users(id),
    FOREIGN KEY (device_id) REFERENCES devices(id),
    
    INDEX idx_baggage_id (baggage_id),
    INDEX idx_scan_time (actual_scan_time),
    INDEX idx_status (status)
);

-- 🔔 جدول الإشعارات المتطور
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- محتوى الإشعار
    type ENUM('baggage_status', 'flight_update', 'security_alert', 'system_announcement', 'promotional') DEFAULT 'baggage_status',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    action_url VARCHAR(255),
    
    -- إعدادات الإرسال
    channels JSON, -- ['email', 'sms', 'push', 'in_app']
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    
    -- حالة الإشعار
    status ENUM('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    
    -- محتوى ديناميكي
    metadata JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 📧 جدول إعدادات البريد الإلكتروني
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) UNIQUE NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body_content TEXT NOT NULL,
    variables JSON, -- القائمة المتغيرة في القالب
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 📊 جدول التقارير والإحصائيات
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    report_name VARCHAR(100) NOT NULL,
    generated_by INT NOT NULL,
    
    -- محتوى التقرير
    parameters JSON,
    data_summary JSON,
    
    -- معلومات الملف
    file_path VARCHAR(255),
    file_format ENUM('pdf', 'excel', 'csv', 'html') DEFAULT 'pdf',
    file_size BIGINT,
    
    -- حالة التقرير
    status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
    generation_time_seconds INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (generated_by) REFERENCES users(id),
    
    INDEX idx_report_type (report_type),
    INDEX idx_created_at (created_at)
);

-- 📈 جدول التحليلات في الوقت الحقيقي
CREATE TABLE analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    metric_hour INT,
    
    -- المقاييس الرئيسية
    metric_category VARCHAR(50) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15, 4) NOT NULL,
    
    -- الأبعاد
    airline_id INT,
    airport_id INT,
    terminal_id INT,
    flight_id INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (airline_id) REFERENCES airlines(id),
    FOREIGN KEY (airport_id) REFERENCES airports(id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id),
    FOREIGN KEY (flight_id) REFERENCES flights(id),
    
    UNIQUE KEY unique_metric (metric_date, metric_hour, metric_category, metric_name, airline_id, airport_id),
    INDEX idx_metric_date (metric_date),
    INDEX idx_category (metric_category)
);

-- 🛡️ جدول سجل الأمان
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    
    -- معلومات النشاط
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- المورد المتأثر
    resource_type VARCHAR(50),
    resource_id INT,
    
    -- النتيجة
    status ENUM('success', 'failure', 'warning') DEFAULT 'success',
    error_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- 🔍 جدول تقارير التفتيش والجودة
CREATE TABLE inspection_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- معلومات التفتيش الأساسية
    area VARCHAR(100) NOT NULL,
    inspector_name VARCHAR(100) NOT NULL,
    inspection_date DATE NOT NULL,
    inspection_time TIME,
    
    -- تفاصيل التفتيش
    inspection_type ENUM('routine', 'scheduled', 'emergency', 'follow_up') DEFAULT 'routine',
    department VARCHAR(50),
    location VARCHAR(100),
    
    -- نتائج التفتيش
    overall_score INT CHECK (overall_score >= 0 AND overall_score <= 100),
    cleanliness_score INT CHECK (cleanliness_score >= 0 AND cleanliness_score <= 100),
    safety_score INT CHECK (safety_score >= 0 AND safety_score <= 100),
    efficiency_score INT CHECK (efficiency_score >= 0 AND efficiency_score <= 100),
    
    -- الحالة والإجراءات
    status ENUM('pending', 'in_progress', 'completed', 'action_required') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- الملاحظات والتوصيات
    notes TEXT,
    recommendations TEXT,
    corrective_actions TEXT,
    follow_up_required BOOLEAN DEFAULT false,
    follow_up_date DATE,
    
    -- معلومات المتابعة
    assigned_to VARCHAR(100),
    completion_date DATE,
    verified_by VARCHAR(100),
    verification_date DATE,
    
    -- المرفقات
    attachments JSON,
    
    -- التوقيتات
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_inspection_date (inspection_date),
    INDEX idx_status (status),
    INDEX idx_inspector (inspector_name),
    INDEX idx_area (area)
);

-- 📊 جدول مؤشرات الجودة
CREATE TABLE quality_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    
    -- نوع المؤشر
    metric_type ENUM('baggage_handling', 'customer_satisfaction', 'safety_compliance', 'efficiency') NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    
    -- القيم
    target_value DECIMAL(10, 2),
    actual_value DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20),
    
    -- التحليل
    performance_percentage DECIMAL(5, 2),
    trend ENUM('improving', 'stable', 'declining') DEFAULT 'stable',
    
    -- السياق
    department VARCHAR(50),
    shift VARCHAR(20),
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_date (metric_date),
    INDEX idx_metric_type (metric_type),
    UNIQUE KEY unique_metric (metric_date, metric_type, metric_name, department)
);

-- 🔧 جدول الإجراءات التصحيحية
CREATE TABLE corrective_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_report_id INT,
    
    -- تفاصيل الإجراء
    action_title VARCHAR(200) NOT NULL,
    action_description TEXT NOT NULL,
    action_type ENUM('immediate', 'short_term', 'long_term', 'preventive') DEFAULT 'immediate',
    
    -- المسؤولية
    assigned_to VARCHAR(100) NOT NULL,
    assigned_by VARCHAR(100) NOT NULL,
    assigned_date DATE NOT NULL,
    
    -- التوقيتات
    due_date DATE,
    completion_date DATE,
    
    -- الحالة
    status ENUM('pending', 'in_progress', 'completed', 'overdue', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- المتابعة
    progress_notes TEXT,
    verification_required BOOLEAN DEFAULT true,
    verified_by VARCHAR(100),
    verification_date DATE,
    
    -- التقييم
    effectiveness_rating INT CHECK (effectiveness_rating >= 1 AND effectiveness_rating <= 5),
    cost_estimate DECIMAL(10, 2),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inspection_report_id) REFERENCES inspection_reports(id) ON DELETE CASCADE,
    
    INDEX idx_status (status),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_due_date (due_date)
);

-- ⚙️ جدول إعدادات النظام
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT false,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
);

-- =============================================
-- إدخال البيانات الأساسية
-- =============================================

-- إضافة شركات الطيران
INSERT INTO airlines (airline_code, airline_name, iata_code, icao_code, contact_email, contact_phone, headquarters) VALUES
('SAU', 'الخطوط السعودية', 'SV', 'SVA', 'info@saudia.com', '+966920022222', 'جدة, السعودية'),
('UAE', 'الإمارات', 'EK', 'UAE', 'contact@emirates.com', '+971600555555', 'دبي, الإمارات'),
('QR', 'القطرية', 'QR', 'QTR', 'info@qatarairways.com', '+97440230000', 'الدوحة, قطر'),
('EY', 'الاتحاد للطيران', 'EY', 'ETD', 'customercare@etihad.ae', '+971600511115', 'أبوظبي, الإمارات');

-- إضافة المطارات
INSERT INTO airports (airport_code, airport_name, city, country, timezone) VALUES
('RUH', 'مطار الملك خالد الدولي', 'الرياض', 'السعودية', 'Asia/Riyadh'),
('JED', 'مطار الملك عبدالعزيز الدولي', 'جدة', 'السعودية', 'Asia/Riyadh'),
('DXB', 'مطار دبي الدولي', 'دبي', 'الإمارات', 'Asia/Dubai'),
('AUH', 'مطار أبوظبي الدولي', 'أبوظبي', 'الإمارات', 'Asia/Dubai'),
('DOH', 'مطار حمد الدولي', 'الدوحة', 'قطر', 'Asia/Qatar');

-- إضافة أنواع الأمتعة
INSERT INTO baggage_types (type_code, type_name, description, max_weight, max_length, max_width, max_height, fee_per_kg) VALUES
('CABIN', 'أمتعة المقصورة', 'أمتعة صغيرة تحمل في المقصورة', 7.00, 55, 40, 20, 0.00),
('CHECKED', 'أمتعة مسجلة', 'أمتعة عادية مسجلة', 23.00, 158, 0, 0, 50.00),
('OVERWEIGHT', 'أمتعة زائدة', 'أمتعة تتجاوز الوزن المسموح', 32.00, 158, 0, 0, 100.00),
('SPECIAL', 'أمتعة خاصة', 'أمتعة رياضية أو أدوات موسيقية', 45.00, 300, 0, 0, 150.00);

-- إضافة المستخدمين الأساسيين
INSERT INTO users (username, email, password, full_name, role, status, email_verified) VALUES
-- المسافرون
('maha2024', 'maha@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مها الحربي', 'passenger', 'active', true),
('ahmed_pass', 'ahmed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أحمد محمد', 'passenger', 'active', true),

-- الموظفون
('counter1', 'counter1@airport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'خالد العتيبي', 'counter_staff', 'active', true),
('handler1', 'handler1@airport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فهد القحطاني', 'handling_staff', 'active', true),

-- المشرفون والإداريون
('supervisor1', 'supervisor@airport.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'سعود الشمري', 'supervisor', 'active', true),
('admin', 'admin@baggage-system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'المشرف العام', 'system_admin', 'active', true);

-- إضافة إعدادات النظام
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES
('system_name', 'نظام تتبع الأمتعة الذكي', 'string', 'اسم النظام المعروض', 'general'),
('system_version', '2.0.0', 'string', 'إصدار النظام', 'general'),
('maintenance_mode', 'false', 'boolean', 'وضع الصيانة', 'general'),
('auto_backup', 'true', 'boolean', 'النسخ الاحتياطي التلقائي', 'backup'),
('backup_frequency', 'daily', 'string', 'تكرار النسخ الاحتياطي', 'backup'),
('smtp_host', 'smtp.gmail.com', 'string', 'خادم البريد', 'email'),
('smtp_port', '587', 'integer', 'منفذ البريد', 'email'),
('max_baggage_weight', '32', 'integer', 'الحد الأقصى لوزن الأمتعة', 'baggage'),
('tracking_update_interval', '5', 'integer', 'فترة تحديث التتبع (دقائق)', 'tracking');

-- =============================================
-- الفهارس والإضافات النهائية
-- =============================================

-- إضافة الفهارس لتحسين الأداء
CREATE INDEX idx_baggage_status_updated ON baggage(status, updated_at);
CREATE INDEX idx_tracking_baggage_time ON tracking_logs(baggage_id, actual_scan_time);
CREATE INDEX idx_flights_departure_arrival ON flights(departure_time, arrival_time);
CREATE INDEX idx_users_role_status ON users(role, status);

-- إضافة محفزات (Triggers) للتحديث التلقائي
DELIMITER //
CREATE TRIGGER update_baggage_status_after_tracking
AFTER INSERT ON tracking_logs
FOR EACH ROW
BEGIN
    UPDATE baggage 
    SET status = NEW.status, 
        updated_at = CURRENT_TIMESTAMP 
    WHERE id = NEW.baggage_id;
END//
DELIMITER ;

-- عرض رسالة نجاح
SELECT '✅ قاعدة البيانات المتطورة تم بناؤها بنجاح!' AS message;

-- عرض إحصائيات الجداول
SELECT 
    TABLE_NAME,
    TABLE_ROWS as 'عدد السجلات'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'smart_baggage_pro'
ORDER BY TABLE_ROWS DESC;
