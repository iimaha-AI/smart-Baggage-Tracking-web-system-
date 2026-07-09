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
$message = '';
$messageType = '';

// الحصول على معرف الأمتعة
$baggageId = $_GET['baggage_id'] ?? null;

if ($baggageId) {
    try {
        // جلب معلومات الأمتعة
        $stmt = $pdo->prepare("
            SELECT b.*, u.full_name as passenger_name, u.email as passenger_email, u.phone_number,
                   f.flight_number, f.departure_time, f.arrival_time, f.status as flight_status,
                   a.airline_name, a.airline_code,
                   oa.airport_name as origin_airport, oa.airport_code as origin_code,
                   da.airport_name as destination_airport, da.airport_code as destination_code,
                   bt.type_name as baggage_type_name, bt.max_weight, bt.fee_per_kg,
                   cs.full_name as checkin_staff_name
            FROM baggage b
            JOIN users u ON b.passenger_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports oa ON f.origin_airport_id = oa.id
            JOIN airports da ON f.destination_airport_id = da.id
            JOIN baggage_types bt ON b.baggage_type_id = bt.id
            LEFT JOIN users cs ON b.checkin_staff_id = cs.id
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
        }
        
    } catch (Exception $e) {
        $message = 'خطأ في جلب معلومات الأمتعة: ' . $e->getMessage();
        $messageType = 'error';
    }
} else {
    $message = 'معرف الأمتعة مطلوب';
    $messageType = 'error';
}

// معالجة تحديث الحالة (للموظفين فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && 
    in_array($auth->getUserRole(), ['counter_staff', 'handling_staff', 'supervisor', 'system_admin'])) {
    
    $newStatus = $_POST['new_status'];
    $remarks = $_POST['remarks'] ?? '';
    // Get location ID (use provided location_id or get/create default check-in location)
    $locationId = $_POST['location_id'] ?? getCheckInLocationId($pdo);
    if (!$locationId) {
        throw new Exception('تعذر الحصول على موقع. يرجى الاتصال بمدير النظام.');
    }
    
    try {
        // تحديث حالة الأمتعة
        $stmt = $pdo->prepare("UPDATE baggage SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $baggageId]);
        
        // إضافة سجل التتبع
        $stmt = $pdo->prepare("
            INSERT INTO tracking_logs (baggage_id, location_id, status, scan_type, scanned_by, remarks, actual_scan_time)
            VALUES (?, ?, ?, 'manual', ?, ?, NOW())
        ");
        $stmt->execute([$baggageId, $locationId, $newStatus, $auth->getUserId(), $remarks]);
        
        // إرسال إشعار للمسافر
        sendNotification(
            $baggage['passenger_id'],
            'تحديث حالة الأمتعة',
            "تم تحديث حالة أمتعتك إلى: " . getBaggageStatusText($newStatus),
            'baggage_status'
        );
        
        $message = 'تم تحديث حالة الأمتعة بنجاح';
        $messageType = 'success';
        
        // إعادة جلب البيانات المحدثة
        $stmt = $pdo->prepare("
            SELECT b.*, u.full_name as passenger_name, u.email as passenger_email, u.phone_number,
                   f.flight_number, f.departure_time, f.arrival_time, f.status as flight_status,
                   a.airline_name, a.airline_code,
                   oa.airport_name as origin_airport, oa.airport_code as origin_code,
                   da.airport_name as destination_airport, da.airport_code as destination_code,
                   bt.type_name as baggage_type_name, bt.max_weight, bt.fee_per_kg,
                   cs.full_name as checkin_staff_name
            FROM baggage b
            JOIN users u ON b.passenger_id = u.id
            JOIN flights f ON b.flight_id = f.id
            JOIN airlines a ON f.airline_id = a.id
            JOIN airports oa ON f.origin_airport_id = oa.id
            JOIN airports da ON f.destination_airport_id = da.id
            JOIN baggage_types bt ON b.baggage_type_id = bt.id
            LEFT JOIN users cs ON b.checkin_staff_id = cs.id
            WHERE b.id = ?
        ");
        $stmt->execute([$baggageId]);
        $baggage = $stmt->fetch();
        
        $trackingHistory = getTrackingHistory($baggageId);
        
    } catch (Exception $e) {
        $message = 'خطأ في تحديث الحالة: ' . $e->getMessage();
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
    <title>حالة الأمتعة - نظام تتبع الأمتعة الذكي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/assets/css/style.css" rel="stylesheet">
    <style>
        .status-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .timeline {
            position: relative;
            padding: 0;
            list-style: none;
        }
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 40px;
            width: 2px;
            margin-left: -1.5px;
            background-color: #e9ecef;
        }
        .timeline > li {
            position: relative;
            margin-bottom: 50px;
            min-height: 50px;
        }
        .timeline > li:before,
        .timeline > li:after {
            content: "";
            display: table;
        }
        .timeline > li:after {
            clear: both;
        }
        .timeline > li .timeline-panel {
            position: relative;
            width: calc(100% - 90px);
            float: right;
            border: 1px solid #d4edda;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.175);
        }
        .timeline > li .timeline-badge {
            color: white;
            width: 50px;
            height: 50px;
            line-height: 50px;
            font-size: 1.4em;
            text-align: center;
            position: absolute;
            top: 16px;
            left: 50%;
            margin-left: -25px;
            background-color: #999999;
            z-index: 100;
            border-radius: 50%;
        }
        .timeline > li.timeline-inverted > .timeline-panel {
            float: left;
        }
        .timeline > li.timeline-inverted > .timeline-panel:before {
            border-left-width: 0;
            border-right-width: 15px;
            left: -15px;
            right: auto;
        }
        .timeline > li.timeline-inverted > .timeline-panel:after {
            border-left-width: 0;
            border-right-width: 14px;
            left: -14px;
            right: auto;
        }
        .timeline-badge.primary { background-color: #007bff !important; }
        .timeline-badge.success { background-color: #28a745 !important; }
        .timeline-badge.warning { background-color: #ffc107 !important; }
        .timeline-badge.danger { background-color: #dc3545 !important; }
        .timeline-badge.info { background-color: #17a2b8 !important; }
        
        .btn-update {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
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
        <div class="status-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-suitcase me-2"></i>
                        <?php echo htmlspecialchars($baggage['baggage_code']); ?>
                    </h2>
                    <p class="mb-0">تفاصيل وحالة الأمتعة</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="status-badge <?php echo getStatusBadge($baggage['status']); ?> text-white fs-5">
                        <?php echo getBaggageStatusText($baggage['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- معلومات الأمتعة -->
            <div class="col-lg-8">
                <!-- معلومات المسافر -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user text-primary me-2"></i>
                        معلومات المسافر
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>الاسم:</strong> <?php echo htmlspecialchars($baggage['passenger_name']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>البريد الإلكتروني:</strong> 
                                <a href="mailto:<?php echo htmlspecialchars($baggage['passenger_email']); ?>">
                                    <?php echo htmlspecialchars($baggage['passenger_email']); ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($baggage['phone_number']): ?>
                            <p class="mb-2">
                                <strong>رقم الهاتف:</strong> 
                                <a href="tel:<?php echo htmlspecialchars($baggage['phone_number']); ?>">
                                    <?php echo htmlspecialchars($baggage['phone_number']); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- معلومات الرحلة -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-plane text-primary me-2"></i>
                        معلومات الرحلة
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>رقم الرحلة:</strong> <?php echo htmlspecialchars($baggage['flight_number']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>شركة الطيران:</strong> <?php echo htmlspecialchars($baggage['airline_name']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>المسار:</strong> 
                                <?php echo htmlspecialchars($baggage['origin_airport'] . ' (' . $baggage['origin_code'] . ') → ' . $baggage['destination_airport'] . ' (' . $baggage['destination_code'] . ')'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>وقت المغادرة:</strong> <?php echo formatDate($baggage['departure_time']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>وقت الوصول:</strong> <?php echo formatDate($baggage['arrival_time']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>حالة الرحلة:</strong> 
                                <span class="badge bg-info"><?php echo $baggage['flight_status']; ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- تفاصيل الأمتعة -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-suitcase text-primary me-2"></i>
                        تفاصيل الأمتعة
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>نوع الأمتعة:</strong> <?php echo htmlspecialchars($baggage['baggage_type_name']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>الوزن:</strong> <?php echo $baggage['weight']; ?> كغ
                            </p>
                            <?php if ($baggage['length'] && $baggage['width'] && $baggage['height']): ?>
                            <p class="mb-2">
                                <strong>الأبعاد:</strong> 
                                <?php echo $baggage['length'] . ' × ' . $baggage['width'] . ' × ' . $baggage['height']; ?> سم
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($baggage['color']): ?>
                            <p class="mb-2">
                                <strong>اللون:</strong> <?php echo htmlspecialchars($baggage['color']); ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($baggage['brand']): ?>
                            <p class="mb-2">
                                <strong>الماركة:</strong> <?php echo htmlspecialchars($baggage['brand']); ?>
                            </p>
                            <?php endif; ?>
                            <p class="mb-2">
                                <strong>تاريخ التسجيل:</strong> <?php echo formatDate($baggage['created_at']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($baggage['fragile'] || $baggage['priority_level'] !== 'normal' || $baggage['special_handling']): ?>
                    <hr>
                    <h6>معلومات خاصة:</h6>
                    <div class="row">
                        <div class="col-12">
                            <?php if ($baggage['fragile']): ?>
                            <span class="badge bg-warning text-dark me-2 mb-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                قابل للكسر
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($baggage['priority_level'] !== 'normal'): ?>
                            <span class="badge bg-info me-2 mb-2">
                                <i class="fas fa-star me-1"></i>
                                <?php echo $baggage['priority_level'] === 'priority' ? 'أولوية' : 'VIP'; ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($baggage['special_handling']): ?>
                            <p class="mb-0">
                                <strong>معالجة خاصة:</strong> <?php echo htmlspecialchars($baggage['special_handling']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- تاريخ التتبع -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history text-primary me-2"></i>
                        تاريخ التتبع
                    </h5>
                    
                    <?php if (!empty($trackingHistory)): ?>
                    <ul class="timeline">
                        <?php foreach (array_reverse($trackingHistory) as $index => $log): ?>
                        <li class="<?php echo $index % 2 === 0 ? '' : 'timeline-inverted'; ?>">
                            <div class="timeline-badge <?php echo getStatusBadge($log['status']); ?>">
                                <i class="fas fa-<?php echo $log['status'] === 'claimed' ? 'check' : 'suitcase'; ?>"></i>
                            </div>
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title">
                                        <?php echo getBaggageStatusText($log['status']); ?>
                                    </h6>
                                    <p class="timeline-time">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDate($log['actual_scan_time']); ?>
                                        </small>
                                    </p>
                                </div>
                                <div class="timeline-body">
                                    <?php if ($log['location_name']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                        <strong>الموقع:</strong> <?php echo htmlspecialchars($log['location_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['scanned_by_name']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <strong>المسؤول:</strong> <?php echo htmlspecialchars($log['scanned_by_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['remarks']): ?>
                                    <p class="mb-0">
                                        <i class="fas fa-comment text-muted me-2"></i>
                                        <strong>ملاحظات:</strong> <?php echo htmlspecialchars($log['remarks']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
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
                <!-- تحديث الحالة (للموظفين فقط) -->
                <?php if (in_array($auth->getUserRole(), ['counter_staff', 'handling_staff', 'supervisor', 'system_admin'])): ?>
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-edit text-primary me-2"></i>
                        تحديث الحالة
                    </h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="new_status" class="form-label">الحالة الجديدة</label>
                            <select class="form-select" id="new_status" name="new_status" required>
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
                            <label for="location_id" class="form-label">الموقع</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">اختر الموقع</option>
                                <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>">
                                    <?php echo htmlspecialchars($location['location_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="أضف ملاحظات حول التحديث..."></textarea>
                        </div>
                        
                        <button type="submit" name="update_status" class="btn btn-update text-white w-100">
                            <i class="fas fa-save me-2"></i>
                            تحديث الحالة
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- معلومات الرسوم -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-money-bill text-primary me-2"></i>
                        معلومات الرسوم
                    </h5>
                    
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-2">
                                <strong>الرسوم الأساسية:</strong>
                            </p>
                            <p class="mb-2">
                                <strong>رسوم الزيادة:</strong>
                            </p>
                            <hr>
                            <p class="mb-0">
                                <strong>المجموع:</strong>
                            </p>
                        </div>
                        <div class="col-6 text-end">
                            <p class="mb-2"><?php echo number_format($baggage['total_fee'] - $baggage['excess_fee'], 2); ?> ريال</p>
                            <p class="mb-2"><?php echo number_format($baggage['excess_fee'], 2); ?> ريال</p>
                            <hr>
                            <p class="mb-0 fw-bold text-success"><?php echo number_format($baggage['total_fee'], 2); ?> ريال</p>
                        </div>
                    </div>
                </div>

                <!-- إجراءات سريعة -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt text-primary me-2"></i>
                        إجراءات سريعة
                    </h5>
                    
                    <div class="d-grid gap-2">
                        <a href="tracking.php?baggage_id=<?php echo $baggage['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            عرض التتبع
                        </a>
                        <a href="search.php" class="btn btn-outline-secondary">
                            <i class="fas fa-search me-2"></i>
                            البحث عن أمتعة أخرى
                        </a>
                        <?php if (in_array($auth->getUserRole(), ['counter_staff', 'handling_staff', 'supervisor', 'system_admin'])): ?>
                        <a href="register.php" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>
                            تسجيل أمتعة جديدة
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- رسالة خطأ -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h5>خطأ</h5>
                    <p><?php echo htmlspecialchars($message); ?></p>
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>
                        البحث عن الأمتعة
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحديث الحالة الحالية في القائمة
        document.addEventListener('DOMContentLoaded', function() {
            const currentStatus = '<?php echo $baggage['status'] ?? ''; ?>';
            const statusSelect = document.getElementById('new_status');
            
            if (statusSelect) {
                // إزالة الحالة الحالية من الخيارات
                const currentOption = statusSelect.querySelector(`option[value="${currentStatus}"]`);
                if (currentOption) {
                    currentOption.disabled = true;
                    currentOption.textContent += ' (الحالة الحالية)';
                }
            }
        });
    </script>
</body>
</html>