<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// التحقق من الصلاحيات
$db = new Database();
$auth = new Auth($db);
$auth->requireAuth();
$pdo = $db->getConnection();

$baggage = null;
$trackingHistory = [];
$currentLocation = null;
$message = '';
$messageType = '';

// الحصول على معرف الأمتعة
$baggageId = $_GET['baggage_id'] ?? null;

if ($baggageId) {
    try {
        // جلب معلومات الأمتعة
        $stmt = $pdo->prepare("
            SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                   f.flight_number, f.departure_time, f.arrival_time, f.status as flight_status,
                   a.airline_name, a.airline_code,
                   oa.airport_name as origin_airport, oa.airport_code as origin_code,
                   da.airport_name as destination_airport, da.airport_code as destination_code,
                   bt.type_name as baggage_type_name
            FROM baggage b
            JOIN users u ON b.passenger_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports oa ON f.origin_airport_id = oa.id
            JOIN airports da ON f.destination_airport_id = da.id
            JOIN baggage_types bt ON b.baggage_type_id = bt.id
            WHERE b.id = ?
        ");
        $stmt->execute([$baggageId]);
        $baggage = $stmt->fetch();
        
        if (!$baggage) {
            $message = 'الأمتعة غير موجودة';
            $messageType = 'error';
        } else {
            // جلب تاريخ التتبع
            $trackingHistory = getTrackingHistory($baggageId);
            
            // جلب الموقع الحالي
            if (!empty($trackingHistory)) {
                $latestLog = end($trackingHistory);
                $currentLocation = $latestLog;
            }
        }
        
    } catch (Exception $e) {
        $message = 'خطأ في جلب معلومات الأمتعة: ' . $e->getMessage();
        $messageType = 'error';
    }
} else {
    // إذا لم يتم توفير معرف الأمتعة، إعادة توجيه إلى صفحة البحث
    header('Location: search.php');
    exit();
}

// معالجة إضافة سجل تتبع جديد (للموظفين فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking']) && 
    in_array($auth->getUserRole(), ['handling_staff', 'supervisor', 'system_admin'])) {
    
    $locationId = $_POST['location_id'];
    $status = $_POST['status'];
    $scanType = $_POST['scan_type'];
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        // إضافة سجل التتبع
        $stmt = $pdo->prepare("
            INSERT INTO tracking_logs (baggage_id, location_id, status, scan_type, scanned_by, remarks, actual_scan_time)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$baggageId, $locationId, $status, $scanType, $auth->getUserId(), $remarks]);
        
        // تحديث حالة الأمتعة
        $stmt = $pdo->prepare("UPDATE baggage SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $baggageId]);
        
        // إرسال إشعار للمسافر
        sendNotification(
            $baggage['passenger_id'],
            'تحديث موقع الأمتعة',
            "تم تحديث موقع أمتعتك إلى: " . getBaggageStatusText($status),
            'baggage_status'
        );
        
        $message = 'تم إضافة سجل التتبع بنجاح';
        $messageType = 'success';
        
        // إعادة جلب البيانات المحدثة
        $stmt = $pdo->prepare("
            SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                   f.flight_number, f.departure_time, f.arrival_time, f.status as flight_status,
                   a.airline_name, a.airline_code,
                   oa.airport_name as origin_airport, oa.airport_code as origin_code,
                   da.airport_name as destination_airport, da.airport_code as destination_code,
                   bt.type_name as baggage_type_name
            FROM baggage b
            JOIN users u ON b.passenger_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports oa ON f.origin_airport_id = oa.id
            JOIN airports da ON f.destination_airport_id = da.id
            JOIN baggage_types bt ON b.baggage_type_id = bt.id
            WHERE b.id = ?
        ");
        $stmt->execute([$baggageId]);
        $baggage = $stmt->fetch();
        
        $trackingHistory = getTrackingHistory($baggageId);
        
        if (!empty($trackingHistory)) {
            $latestLog = end($trackingHistory);
            $currentLocation = $latestLog;
        }
        
    } catch (Exception $e) {
        $message = 'خطأ في إضافة سجل التتبع: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// جلب المواقع المتاحة
$locations = $pdo->query("SELECT * FROM locations WHERE status = 'active' ORDER BY location_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع الأمتعة - نظام تتبع الأمتعة الذكي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/assets/css/style.css" rel="stylesheet">
    <style>
        .tracking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .current-location {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .progress-track {
            position: relative;
            padding: 2rem 0;
        }
        .progress-track:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }
        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        .progress-step .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-icon {
            background: #007bff;
            color: white;
        }
        .progress-step.completed .step-icon {
            background: #28a745;
            color: white;
        }
        .progress-step .step-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6c757d;
        }
        .progress-step.active .step-label {
            color: #007bff;
        }
        .progress-step.completed .step-label {
            color: #28a745;
        }
        .timeline-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            position: relative;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -8px;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
        }
        .timeline-item.latest:before {
            background: #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .btn-add-tracking {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-add-tracking:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .map-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
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
                <?php if ($auth->getUserRole() === 'passenger'): ?>
                <a class="nav-link" href="../passenger/dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>
                    لوحة التحكم
                </a>
                <?php else: ?>
                <a class="nav-link" href="../staff/dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>
                    لوحة التحكم
                </a>
                <?php endif; ?>
                <a class="nav-link" href="search.php">
                    <i class="fas fa-search me-1"></i>
                    البحث
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

        <?php if ($baggage): ?>
        <!-- عنوان الصفحة -->
        <div class="tracking-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        تتبع الأمتعة: <?php echo htmlspecialchars($baggage['baggage_code']); ?>
                    </h2>
                    <p class="mb-0">مراقبة موقع وحالة الأمتعة في الوقت الفعلي</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-5 px-3 py-2">
                        <i class="fas fa-plane me-2"></i>
                        <?php echo htmlspecialchars($baggage['flight_number']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- الموقع الحالي -->
        <?php if ($currentLocation): ?>
        <div class="current-location">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <i class="fas fa-location-dot me-2"></i>
                        الموقع الحالي
                    </h4>
                    <h5 class="mb-1"><?php echo getBaggageStatusText($currentLocation['status']); ?></h5>
                    <?php if ($currentLocation['location_name']): ?>
                    <p class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo htmlspecialchars($currentLocation['location_name']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-end">
                        <p class="mb-1">
                            <i class="fas fa-clock me-2"></i>
                            آخر تحديث
                        </p>
                        <h6 class="mb-0"><?php echo formatDate($currentLocation['actual_scan_time']); ?></h6>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- مسار التتبع -->
            <div class="col-lg-8">
                <!-- خريطة التتبع -->
                <div class="map-container">
                    <h5 class="mb-3">
                        <i class="fas fa-route me-2"></i>
                        مسار الرحلة
                    </h5>
                    <div class="progress-track">
                        <div class="row">
                            <div class="col-3">
                                <div class="progress-step completed">
                                    <div class="step-icon">
                                        <i class="fas fa-plane-departure"></i>
                                    </div>
                                    <div class="step-label">المغادرة</div>
                                    <small class="text-muted"><?php echo htmlspecialchars($baggage['origin_airport']); ?></small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="progress-step <?php echo in_array($baggage['status'], ['in_transit', 'unloaded_from_aircraft', 'at_carousel', 'claimed']) ? 'completed' : ($baggage['status'] === 'loaded_to_aircraft' ? 'active' : ''); ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-plane"></i>
                                    </div>
                                    <div class="step-label">في الطيران</div>
                                    <small class="text-muted">في الطريق</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="progress-step <?php echo in_array($baggage['status'], ['at_carousel', 'claimed']) ? 'completed' : ($baggage['status'] === 'unloaded_from_aircraft' ? 'active' : ''); ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-plane-arrival"></i>
                                    </div>
                                    <div class="step-label">الوصول</div>
                                    <small class="text-muted"><?php echo htmlspecialchars($baggage['destination_airport']); ?></small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="progress-step <?php echo $baggage['status'] === 'claimed' ? 'completed' : ($baggage['status'] === 'at_carousel' ? 'active' : ''); ?>">
                                    <div class="step-icon">
                                        <i class="fas fa-suitcase"></i>
                                    </div>
                                    <div class="step-label">الاستلام</div>
                                    <small class="text-muted">تم الاستلام</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تاريخ التتبع التفصيلي -->
                <div class="status-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history me-2"></i>
                        تاريخ التتبع التفصيلي
                    </h5>
                    
                    <?php if (!empty($trackingHistory)): ?>
                    <?php foreach (array_reverse($trackingHistory) as $index => $log): ?>
                    <div class="timeline-item <?php echo $index === 0 ? 'latest' : ''; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0 me-3">
                                        <?php echo getBaggageStatusText($log['status']); ?>
                                    </h6>
                                    <?php if ($index === 0): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-circle me-1"></i>
                                        أحدث
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($log['location_name']): ?>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($log['location_name']); ?>
                                </p>
                                <?php endif; ?>
                                
                                <?php if ($log['scanned_by_name']): ?>
                                <p class="mb-1 text-muted">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($log['scanned_by_name']); ?>
                                </p>
                                <?php endif; ?>
                                
                                <?php if ($log['remarks']): ?>
                                <p class="mb-0 text-muted">
                                    <i class="fas fa-comment me-2"></i>
                                    <?php echo htmlspecialchars($log['remarks']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 text-end">
                                <p class="mb-1">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo formatDate($log['actual_scan_time']); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-qrcode me-2"></i>
                                    <?php echo ucfirst($log['scan_type']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-history fa-2x mb-3"></i>
                        <p>لا يوجد تاريخ تتبع متاح</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات الرحلة -->
                <div class="status-card">
                    <h5 class="mb-3">
                        <i class="fas fa-plane text-primary me-2"></i>
                        معلومات الرحلة
                    </h5>
                    
                    <div class="row">
                        <div class="col-12">
                            <p class="mb-2">
                                <strong>رقم الرحلة:</strong> <?php echo htmlspecialchars($baggage['flight_number']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>شركة الطيران:</strong> <?php echo htmlspecialchars($baggage['airline_name']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>المغادرة:</strong> <?php echo formatDate($baggage['departure_time']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>الوصول:</strong> <?php echo formatDate($baggage['arrival_time']); ?>
                            </p>
                            <p class="mb-0">
                                <strong>المسار:</strong> 
                                <?php echo htmlspecialchars($baggage['origin_airport'] . ' → ' . $baggage['destination_airport']); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- معلومات الأمتعة -->
                <div class="status-card">
                    <h5 class="mb-3">
                        <i class="fas fa-suitcase text-primary me-2"></i>
                        معلومات الأمتعة
                    </h5>
                    
                    <div class="row">
                        <div class="col-12">
                            <p class="mb-2">
                                <strong>كود الأمتعة:</strong> <?php echo htmlspecialchars($baggage['baggage_code']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>النوع:</strong> <?php echo htmlspecialchars($baggage['baggage_type_name']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>الوزن:</strong> <?php echo $baggage['weight']; ?> كغ
                            </p>
                            <p class="mb-2">
                                <strong>المسافر:</strong> <?php echo htmlspecialchars($baggage['passenger_name']); ?>
                            </p>
                            <p class="mb-0">
                                <strong>تاريخ التسجيل:</strong> <?php echo formatDate($baggage['created_at']); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- إضافة سجل تتبع (للموظفين فقط) -->
                <?php if (in_array($auth->getUserRole(), ['handling_staff', 'supervisor', 'system_admin'])): ?>
                <div class="status-card">
                    <h5 class="mb-3">
                        <i class="fas fa-plus text-primary me-2"></i>
                        إضافة سجل تتبع
                    </h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="location_id" class="form-label">الموقع</label>
                            <select class="form-select" id="location_id" name="location_id" required>
                                <option value="">اختر الموقع</option>
                                <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>">
                                    <?php echo htmlspecialchars($location['location_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">الحالة</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">اختر الحالة</option>
                                <option value="checked_in">تم التسجيل</option>
                                <option value="security_check">فحص أمني</option>
                                <option value="sorting_facility">في منطقة الفرز</option>
                                <option value="loaded_to_aircraft">تم التحميل على الطائرة</option>
                                <option value="in_transit">في الطريق</option>
                                <option value="unloaded_from_aircraft">تم التفريغ</option>
                                <option value="at_carousel">على الحزام</option>
                                <option value="claimed">تم الاستلام</option>
                                <option value="delayed">متأخرة</option>
                                <option value="lost">مفقودة</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="scan_type" class="form-label">نوع المسح</label>
                            <select class="form-select" id="scan_type" name="scan_type" required>
                                <option value="manual">يدوي</option>
                                <option value="barcode">باركود</option>
                                <option value="rfid">RFID</option>
                                <option value="qr_code">QR Code</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="أضف ملاحظات حول التتبع..."></textarea>
                        </div>
                        
                        <button type="submit" name="add_tracking" class="btn btn-add-tracking text-white w-100">
                            <i class="fas fa-plus me-2"></i>
                            إضافة سجل التتبع
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- إجراءات سريعة -->
                <div class="status-card">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt text-primary me-2"></i>
                        إجراءات سريعة
                    </h5>
                    
                    <div class="d-grid gap-2">
                        <a href="status.php?baggage_id=<?php echo $baggage['id']; ?>" class="btn btn-outline-info">
                            <i class="fas fa-info-circle me-2"></i>
                            عرض التفاصيل
                        </a>
                        <a href="search.php" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i>
                            البحث عن أمتعة أخرى
                        </a>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>
                            طباعة التقرير
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث الصفحة كل 30 ثانية
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);

        // تحديث الحالة الحالية في القائمة
        document.addEventListener('DOMContentLoaded', function() {
            const currentStatus = '<?php echo $baggage['status'] ?? ''; ?>';
            const statusSelect = document.getElementById('status');
            
            if (statusSelect) {
                // تحديد الحالة الحالية
                const currentOption = statusSelect.querySelector(`option[value="${currentStatus}"]`);
                if (currentOption) {
                    currentOption.selected = true;
                }
            }
        });
    </script>
</body>
</html>