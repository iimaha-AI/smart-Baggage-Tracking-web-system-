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
$auth->requireAuth();
$pdo = $db->getConnection();

$searchResults = [];
$searchQuery = '';
$searchType = 'baggage_code';

// معالجة البحث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchQuery = trim($_POST['search_query']);
    $searchType = $_POST['search_type'];
    
    if (!empty($searchQuery)) {
        try {
            $sql = "";
            $params = [];
            
            switch ($searchType) {
                case 'baggage_code':
                    $sql = "
                        SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                               f.flight_number, a.airline_name, 
                               oa.airport_name as origin_airport, da.airport_name as destination_airport,
                               bt.type_name as baggage_type_name
                        FROM baggage b
                        JOIN users u ON b.passenger_id = u.id
                        JOIN flights f ON b.flight_id = f.id
                        JOIN airlines a ON f.airline_id = a.id
                        JOIN airports oa ON f.origin_airport_id = oa.id
                        JOIN airports da ON f.destination_airport_id = da.id
                        JOIN baggage_types bt ON b.baggage_type_id = bt.id
                        WHERE b.baggage_code LIKE ?
                        ORDER BY b.created_at DESC
                    ";
                    $params = ["%$searchQuery%"];
                    break;
                    
                case 'passenger_name':
                    $sql = "
                        SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                               f.flight_number, a.airline_name, 
                               oa.airport_name as origin_airport, da.airport_name as destination_airport,
                               bt.type_name as baggage_type_name
                        FROM baggage b
                        JOIN users u ON b.passenger_id = u.id
                        JOIN flights f ON b.flight_id = f.id
                        JOIN airlines a ON f.airline_id = a.id
                        JOIN airports oa ON f.origin_airport_id = oa.id
                        JOIN airports da ON f.destination_airport_id = da.id
                        JOIN baggage_types bt ON b.baggage_type_id = bt.id
                        WHERE u.full_name LIKE ?
                        ORDER BY b.created_at DESC
                    ";
                    $params = ["%$searchQuery%"];
                    break;
                    
                case 'flight_number':
                    $sql = "
                        SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                               f.flight_number, a.airline_name, 
                               oa.airport_name as origin_airport, da.airport_name as destination_airport,
                               bt.type_name as baggage_type_name
                        FROM baggage b
                        JOIN users u ON b.passenger_id = u.id
                        JOIN flights f ON b.flight_id = f.id
                        JOIN airlines a ON f.airline_id = a.id
                        JOIN airports oa ON f.origin_airport_id = oa.id
                        JOIN airports da ON f.destination_airport_id = da.id
                        JOIN baggage_types bt ON b.baggage_type_id = bt.id
                        WHERE f.flight_number LIKE ?
                        ORDER BY b.created_at DESC
                    ";
                    $params = ["%$searchQuery%"];
                    break;
                    
                case 'passenger_email':
                    $sql = "
                        SELECT b.*, u.full_name as passenger_name, u.email as passenger_email,
                               f.flight_number, a.airline_name, 
                               oa.airport_name as origin_airport, da.airport_name as destination_airport,
                               bt.type_name as baggage_type_name
                        FROM baggage b
                        JOIN users u ON b.passenger_id = u.id
                        JOIN flights f ON b.flight_id = f.id
                        JOIN airlines a ON f.airline_id = a.id
                        JOIN airports oa ON f.origin_airport_id = oa.id
                        JOIN airports da ON f.destination_airport_id = da.id
                        JOIN baggage_types bt ON b.baggage_type_id = bt.id
                        WHERE u.email LIKE ?
                        ORDER BY b.created_at DESC
                    ";
                    $params = ["%$searchQuery%"];
                    break;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $searchResults = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $error = "خطأ في البحث: " . $e->getMessage();
        }
    }
}

// جلب آخر 10 أمتعة مسجلة للعرض
$recentBaggage = $pdo->query("
    SELECT b.*, u.full_name as passenger_name, f.flight_number, a.airline_name,
           bt.type_name as baggage_type_name
    FROM baggage b
    JOIN users u ON b.passenger_id = u.id
    JOIN flights f ON b.flight_id = f.id
    JOIN airlines a ON f.airline_id = a.id
    JOIN baggage_types bt ON b.baggage_type_id = bt.id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث عن الأمتعة - نظام تتبع الأمتعة الذكي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/assets/css/style.css" rel="stylesheet">
    <style>
        .search-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .search-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
        }
        .result-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .btn-search {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .recent-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .recent-item:hover {
            background: #e9ecef;
            transform: translateX(-5px);
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
                <a class="nav-link" href="../includes/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- عنوان الصفحة -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle p-3 me-3">
                        <i class="fas fa-search fa-lg"></i>
                    </div>
                    <div>
                        <h2 class="mb-0">البحث عن الأمتعة</h2>
                        <p class="text-muted mb-0">ابحث عن أمتعة المسافرين في النظام</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- نموذج البحث -->
        <div class="search-section">
            <h4 class="mb-3">
                <i class="fas fa-search me-2"></i>
                البحث المتقدم
            </h4>
            
            <form method="POST" id="searchForm">
                <div class="search-card">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="search_type" class="form-label">نوع البحث</label>
                            <select class="form-select" id="search_type" name="search_type">
                                <option value="baggage_code" <?php echo $searchType === 'baggage_code' ? 'selected' : ''; ?>>كود الأمتعة</option>
                                <option value="passenger_name" <?php echo $searchType === 'passenger_name' ? 'selected' : ''; ?>>اسم المسافر</option>
                                <option value="flight_number" <?php echo $searchType === 'flight_number' ? 'selected' : ''; ?>>رقم الرحلة</option>
                                <option value="passenger_email" <?php echo $searchType === 'passenger_email' ? 'selected' : ''; ?>>البريد الإلكتروني</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="search_query" class="form-label">كلمة البحث</label>
                            <input type="text" class="form-control" id="search_query" name="search_query" 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>" 
                                   placeholder="أدخل كلمة البحث..." required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" name="search" class="btn btn-search btn-lg text-white w-100">
                                <i class="fas fa-search me-2"></i>
                                بحث
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- نتائج البحث -->
        <?php if (!empty($searchResults)): ?>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>
                        <i class="fas fa-list me-2"></i>
                        نتائج البحث (<?php echo count($searchResults); ?> نتيجة)
                    </h4>
                    <span class="badge bg-primary fs-6"><?php echo count($searchResults); ?> نتيجة</span>
                </div>
                
                <?php foreach ($searchResults as $baggage): ?>
                <div class="result-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 me-3">
                                    <i class="fas fa-suitcase text-primary me-2"></i>
                                    <?php echo htmlspecialchars($baggage['baggage_code']); ?>
                                </h5>
                                <span class="status-badge <?php echo getStatusBadge($baggage['status']); ?> text-white">
                                    <?php echo getBaggageStatusText($baggage['status']); ?>
                                </span>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <strong>المسافر:</strong> <?php echo htmlspecialchars($baggage['passenger_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <strong>البريد:</strong> <?php echo htmlspecialchars($baggage['passenger_email']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-plane text-muted me-2"></i>
                                        <strong>الرحلة:</strong> <?php echo htmlspecialchars($baggage['flight_number'] . ' - ' . $baggage['airline_name']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-weight text-muted me-2"></i>
                                        <strong>الوزن:</strong> <?php echo $baggage['weight']; ?> كغ
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-tag text-muted me-2"></i>
                                        <strong>النوع:</strong> <?php echo htmlspecialchars($baggage['baggage_type_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-clock text-muted me-2"></i>
                                        <strong>تاريخ التسجيل:</strong> <?php echo formatDate($baggage['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <p class="mb-1">
                                        <i class="fas fa-route text-muted me-2"></i>
                                        <strong>المسار:</strong> 
                                        <?php echo htmlspecialchars($baggage['origin_airport'] . ' → ' . $baggage['destination_airport']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="d-flex flex-column h-100 justify-content-center">
                                <div class="mb-2">
                                    <?php if ($baggage['fragile']): ?>
                                    <span class="badge bg-warning text-dark me-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        قابل للكسر
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($baggage['priority_level'] !== 'normal'): ?>
                                    <span class="badge bg-info me-2">
                                        <i class="fas fa-star me-1"></i>
                                        <?php echo $baggage['priority_level'] === 'priority' ? 'أولوية' : 'VIP'; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="tracking.php?baggage_id=<?php echo $baggage['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        تتبع
                                    </a>
                                    <a href="status.php?baggage_id=<?php echo $baggage['id']; ?>" 
                                       class="btn btn-outline-info btn-sm flex-fill">
                                        <i class="fas fa-info-circle me-1"></i>
                                        تفاصيل
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>لم يتم العثور على نتائج</h5>
                    <p>لم يتم العثور على أمتعة تطابق معايير البحث الخاصة بك.</p>
                    <p>جرب البحث بكلمات مختلفة أو نوع بحث آخر.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- آخر الأمتعة المسجلة -->
        <?php if (empty($searchResults) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <div class="row">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="fas fa-clock me-2"></i>
                    آخر الأمتعة المسجلة
                </h4>
                
                <?php foreach ($recentBaggage as $baggage): ?>
                <div class="recent-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">
                                <i class="fas fa-suitcase text-primary me-2"></i>
                                <?php echo htmlspecialchars($baggage['baggage_code']); ?>
                                <span class="status-badge <?php echo getStatusBadge($baggage['status']); ?> text-white ms-2">
                                    <?php echo getBaggageStatusText($baggage['status']); ?>
                                </span>
                            </h6>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($baggage['passenger_name']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-plane me-1"></i>
                                <?php echo htmlspecialchars($baggage['flight_number']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-weight me-1"></i>
                                <?php echo $baggage['weight']; ?> كغ
                            </p>
                        </div>
                        <div>
                            <a href="tracking.php?baggage_id=<?php echo $baggage['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                تتبع
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // تحسين تجربة البحث
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            const searchQuery = document.getElementById('search_query').value.trim();
            if (searchQuery.length < 2) {
                e.preventDefault();
                alert('يرجى إدخال كلمة بحث مكونة من حرفين على الأقل');
                return false;
            }
        });

        // تحديث placeholder حسب نوع البحث
        document.getElementById('search_type').addEventListener('change', function() {
            const searchQuery = document.getElementById('search_query');
            const placeholders = {
                'baggage_code': 'أدخل كود الأمتعة...',
                'passenger_name': 'أدخل اسم المسافر...',
                'flight_number': 'أدخل رقم الرحلة...',
                'passenger_email': 'أدخل البريد الإلكتروني...'
            };
            searchQuery.placeholder = placeholders[this.value] || 'أدخل كلمة البحث...';
        });
    </script>
</body>
</html>