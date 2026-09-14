<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// التحقق من الصلاحيات
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['counter_staff', 'supervisor', 'system_admin']);
$pdo = $db->getConnection();

$message = '';
$messageType = '';

// معالجة تسجيل الأمتعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_baggage'])) {
    try {
        $passengerId = $_POST['passenger_id'] ?? '';
        $flightId = $_POST['flight_id'] ?? '';
        $baggageTypeId = $_POST['baggage_type_id'] ?? '';
        $weight = $_POST['weight'] ?? '';
        $length = $_POST['length'] ?? null;
        $width = $_POST['width'] ?? null;
        $height = $_POST['height'] ?? null;
        $color = $_POST['color'] ?? null;
        $brand = $_POST['brand'] ?? null;
        $specialFeatures = $_POST['special_features'] ?? null;
        $priorityLevel = $_POST['priority_level'] ?? 'normal';
        $fragile = isset($_POST['fragile']) ? 1 : 0;
        $specialHandling = $_POST['special_handling'] ?? null;
        
        // التحقق من صحة البيانات
        if (empty($passengerId) || empty($flightId) || empty($baggageTypeId) || empty($weight)) {
            throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
        }
        
        if ($weight <= 0 || $weight > 50) {
            throw new Exception('الوزن يجب أن يكون بين 0.1 و 50 كيلو');
        }
        
        // الحصول على معلومات الرحلة
        $flight = $pdo->query("SELECT flight_number FROM flights WHERE id = $flightId")->fetch();
        if (!$flight) {
            throw new Exception('الرحلة غير موجودة');
        }
        
        // توليد كود الأمتعة
        $baggageCode = generateBaggageCode($flight['flight_number']);
        
        // حساب الرسوم الزائدة
        $baggageType = $pdo->query("SELECT * FROM baggage_types WHERE id = $baggageTypeId")->fetch();
        $excessFee = calculateExcessFee($weight, $baggageType['type_code']);
        $totalFee = $baggageType['fee_per_kg'] + $excessFee;
        
        // إدراج الأمتعة
        $stmt = $pdo->prepare("
            INSERT INTO baggage (
                baggage_code, passenger_id, flight_id, baggage_type_id, 
                weight, length, width, height, color, brand, special_features,
                checkin_counter, checkin_staff_id, checkin_time,
                priority_level, fragile, special_handling, excess_fee, total_fee, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'checked_in')
        ");
        
        $stmt->execute([
            $baggageCode, $passengerId, $flightId, $baggageTypeId,
            $weight, $length, $width, $height, $color, $brand, $specialFeatures,
            'C' . rand(1, 20), $auth->getUserId(),
            $priorityLevel, $fragile, $specialHandling, $excessFee, $totalFee
        ]);
        
        $baggageId = $pdo->lastInsertId();
        
        // الحصول على موقع تسجيل الأمتعة (أو إنشاء موقع افتراضي إذا لم يكن موجودًا)
        $locationId = getCheckInLocationId($pdo);
        if (!$locationId) {
            throw new Exception('تعذر الحصول على موقع تسجيل الأمتعة. يرجى الاتصال بمدير النظام.');
        }
        
        // إضافة سجل التتبع الأول
        $stmt = $pdo->prepare("
            INSERT INTO tracking_logs (baggage_id, location_id, status, scan_type, scanned_by, actual_scan_time)
            VALUES (?, ?, 'checked_in', 'manual', ?, NOW())
        ");
        $stmt->execute([$baggageId, $locationId, $auth->getUserId()]);
        
        // إرسال إشعار للمسافر
        $passenger = $pdo->query("SELECT * FROM users WHERE id = $passengerId")->fetch();
        sendNotification(
            $passengerId,
            'تم تسجيل أمتعتك',
            "تم تسجيل أمتعتك بنجاح. كود الأمتعة: $baggageCode",
            'baggage_status'
        );
        
        $message = "تم تسجيل الأمتعة بنجاح! كود الأمتعة: $baggageCode";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// جلب البيانات المطلوبة للنموذج
$passengers = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'passenger' AND status = 'active' ORDER BY full_name")->fetchAll();
$flights = $pdo->query("
    SELECT f.*, a.airline_name, oa.airport_name as origin, da.airport_name as destination
    FROM flights f
    JOIN airlines a ON f.airline_id = a.id
    JOIN airports oa ON f.origin_airport_id = oa.id
    JOIN airports da ON f.destination_airport_id = da.id
    WHERE f.status IN ('scheduled', 'boarding')
    ORDER BY f.departure_time
")->fetchAll();
$baggageTypes = $pdo->query("SELECT * FROM baggage_types WHERE status = 'active' ORDER BY type_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الأمتعة - نظام تتبع الأمتعة الذكي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/assets/css/style.css" rel="stylesheet">
    <style>
        .form-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
        }
        .weight-calculator {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-suitcase-rolling me-2"></i>
                نظام تتبع الأمتعة الذكي
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../staff/dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>
                    لوحة التحكم
                </a>
                <a class="nav-link" href="../includes/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- رسائل النظام -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- عنوان الصفحة -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle p-3 me-3">
                        <i class="fas fa-plus fa-lg"></i>
                    </div>
                    <div>
                        <h2 class="mb-0">تسجيل أمتعة جديدة</h2>
                        <p class="text-muted mb-0">تسجيل أمتعة المسافرين في النظام</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" id="baggageForm">
            <div class="row">
                <!-- معلومات المسافر والرحلة -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h4 class="mb-3">
                            <i class="fas fa-user me-2"></i>
                            معلومات المسافر والرحلة
                        </h4>
                        
                        <div class="info-card">
                            <label for="passenger_id" class="form-label">المسافر *</label>
                            <select class="form-select" id="passenger_id" name="passenger_id" required>
                                <option value="">اختر المسافر</option>
                                <?php foreach ($passengers as $passenger): ?>
                                <option value="<?php echo $passenger['id']; ?>">
                                    <?php echo htmlspecialchars($passenger['full_name'] . ' - ' . $passenger['email']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="info-card">
                            <label for="flight_id" class="form-label">الرحلة *</label>
                            <select class="form-select" id="flight_id" name="flight_id" required>
                                <option value="">اختر الرحلة</option>
                                <?php foreach ($flights as $flight): ?>
                                <option value="<?php echo $flight['id']; ?>">
                                    <?php echo htmlspecialchars($flight['flight_number'] . ' - ' . $flight['airline_name'] . ' (' . $flight['origin'] . ' → ' . $flight['destination'] . ') - ' . date('Y-m-d H:i', strtotime($flight['departure_time']))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- معلومات الأمتعة -->
                <div class="col-lg-6">
                    <div class="form-section">
                        <h4 class="mb-3">
                            <i class="fas fa-suitcase me-2"></i>
                            معلومات الأمتعة
                        </h4>
                        
                        <div class="info-card">
                            <label for="baggage_type_id" class="form-label">نوع الأمتعة *</label>
                            <select class="form-select" id="baggage_type_id" name="baggage_type_id" required>
                                <option value="">اختر نوع الأمتعة</option>
                                <?php foreach ($baggageTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>" data-max-weight="<?php echo $type['max_weight']; ?>" data-fee="<?php echo $type['fee_per_kg']; ?>">
                                    <?php echo htmlspecialchars($type['type_name'] . ' (حد أقصى: ' . $type['max_weight'] . ' كغ)'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <label for="weight" class="form-label">الوزن (كغ) *</label>
                                    <input type="number" class="form-control" id="weight" name="weight" 
                                           step="0.1" min="0.1" max="50" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <label for="priority_level" class="form-label">مستوى الأولوية</label>
                                    <select class="form-select" id="priority_level" name="priority_level">
                                        <option value="normal">عادي</option>
                                        <option value="priority">أولوية</option>
                                        <option value="vip">VIP</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- حاسبة الرسوم -->
                        <div class="weight-calculator">
                            <h6><i class="fas fa-calculator me-2"></i>حاسبة الرسوم</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small>الرسوم الأساسية:</small>
                                    <div id="baseFee" class="fw-bold">0 ريال</div>
                                </div>
                                <div class="col-6">
                                    <small>رسوم الزيادة:</small>
                                    <div id="excessFee" class="fw-bold">0 ريال</div>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="row">
                                <div class="col-12">
                                    <small>المجموع:</small>
                                    <div id="totalFee" class="fw-bold text-success fs-5">0 ريال</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تفاصيل إضافية -->
            <div class="row">
                <div class="col-12">
                    <div class="form-section">
                        <h4 class="mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            تفاصيل إضافية
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-card">
                                    <label for="length" class="form-label">الطول (سم)</label>
                                    <input type="number" class="form-control" id="length" name="length" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-card">
                                    <label for="width" class="form-label">العرض (سم)</label>
                                    <input type="number" class="form-control" id="width" name="width" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-card">
                                    <label for="height" class="form-label">الارتفاع (سم)</label>
                                    <input type="number" class="form-control" id="height" name="height" min="0">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-card">
                                    <label for="color" class="form-label">اللون</label>
                                    <input type="text" class="form-control" id="color" name="color" placeholder="مثال: أسود">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <label for="brand" class="form-label">الماركة</label>
                                    <input type="text" class="form-control" id="brand" name="brand" placeholder="مثال: Samsonite">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <label for="special_features" class="form-label">مميزات خاصة</label>
                                    <input type="text" class="form-control" id="special_features" name="special_features" placeholder="مثال: عجلات، مقبض">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="fragile" name="fragile">
                                        <label class="form-check-label" for="fragile">
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                            أمتعة قابلة للكسر
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <label for="special_handling" class="form-label">معالجة خاصة</label>
                                    <textarea class="form-control" id="special_handling" name="special_handling" rows="2" placeholder="تعليمات خاصة للمعالجة"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أزرار التحكم -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="../staff/dashboard.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>
                            العودة للوحة التحكم
                        </a>
                        <button type="submit" name="register_baggage" class="btn btn-register btn-lg text-white">
                            <i class="fas fa-save me-2"></i>
                            تسجيل الأمتعة
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // حاسبة الرسوم
        function calculateFees() {
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            const baggageTypeSelect = document.getElementById('baggage_type_id');
            const selectedOption = baggageTypeSelect.options[baggageTypeSelect.selectedIndex];
            
            if (selectedOption.value) {
                const maxWeight = parseFloat(selectedOption.dataset.maxWeight);
                const baseFee = parseFloat(selectedOption.dataset.fee);
                
                let excessFee = 0;
                if (weight > maxWeight) {
                    excessFee = (weight - maxWeight) * 50; // 50 ريال للكيلو الزائد
                }
                
                const totalFee = baseFee + excessFee;
                
                document.getElementById('baseFee').textContent = baseFee + ' ريال';
                document.getElementById('excessFee').textContent = excessFee + ' ريال';
                document.getElementById('totalFee').textContent = totalFee + ' ريال';
            }
        }

        // ربط الأحداث
        document.getElementById('weight').addEventListener('input', calculateFees);
        document.getElementById('baggage_type_id').addEventListener('change', calculateFees);

        // التحقق من صحة النموذج
        document.getElementById('baggageForm').addEventListener('submit', function(e) {
            const weight = parseFloat(document.getElementById('weight').value);
            const maxWeight = 50;
            
            if (weight > maxWeight) {
                e.preventDefault();
                alert('الوزن الأقصى المسموح هو ' + maxWeight + ' كيلو');
                return false;
            }
        });
    </script>
</body>
</html>