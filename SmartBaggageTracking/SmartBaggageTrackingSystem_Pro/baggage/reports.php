<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// التحقق من صلاحيات المستخدم
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin', 'airline_manager', 'supervisor', 'counter_staff', 'handling_staff']);
$pdo = $db->getConnection();

$reportData = [];
$reportType = '';
$dateFrom = '';
$dateTo = '';

// معالجة طلب التقرير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'];
    $dateFrom = $_POST['date_from'];
    $dateTo = $_POST['date_to'];
    
    try {
        switch ($reportType) {
            case 'baggage_summary':
                $stmt = $pdo->prepare("SELECT 
                    DATE(created_at) as report_date,
                    COUNT(*) as total_baggage,
                    COUNT(CASE WHEN status = 'claimed' THEN 1 END) as delivered,
                    COUNT(CASE WHEN status = 'delayed' THEN 1 END) as delayed,
                    COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost,
                    AVG(weight) as avg_weight,
                    SUM(total_fee) as total_revenue
                    FROM baggage 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY report_date DESC");
                $stmt->execute([$dateFrom, $dateTo]);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'status_analysis':
                $stmt = $pdo->prepare("SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM baggage WHERE DATE(created_at) BETWEEN ? AND ?), 2) as percentage,
                    AVG(weight) as avg_weight,
                    SUM(total_fee) as total_revenue
                    FROM baggage 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY status
                    ORDER BY count DESC");
                $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'flight_analysis':
                $stmt = $pdo->prepare("SELECT 
                    f.flight_number,
                    a.airline_name,
                    ap1.airport_name as origin,
                    ap2.airport_name as destination,
                    COUNT(b.id) as total_baggage,
                    COUNT(CASE WHEN b.status = 'claimed' THEN 1 END) as delivered,
                    COUNT(CASE WHEN b.status = 'delayed' THEN 1 END) as delayed,
                    COUNT(CASE WHEN b.status = 'lost' THEN 1 END) as lost,
                    AVG(b.weight) as avg_weight,
                    SUM(b.total_fee) as total_revenue
                    FROM flights f
                    JOIN airlines a ON f.airline_id = a.id
                    JOIN airports ap1 ON f.origin_airport_id = ap1.id
                    JOIN airports ap2 ON f.destination_airport_id = ap2.id
                    LEFT JOIN baggage b ON f.id = b.flight_id
                    WHERE DATE(f.departure_time) BETWEEN ? AND ?
                    GROUP BY f.id, f.flight_number, a.airline_name, ap1.airport_name, ap2.airport_name
                    ORDER BY total_baggage DESC");
                $stmt->execute([$dateFrom, $dateTo]);
                $reportData = $stmt->fetchAll();
                break;
                
            case 'airline_performance':
                $stmt = $pdo->prepare("SELECT 
                    a.airline_name,
                    COUNT(b.id) as total_baggage,
                    COUNT(CASE WHEN b.status = 'claimed' THEN 1 END) as delivered,
                    COUNT(CASE WHEN b.status = 'delayed' THEN 1 END) as delayed,
                    COUNT(CASE WHEN b.status = 'lost' THEN 1 END) as lost,
                    ROUND(COUNT(CASE WHEN b.status = 'claimed' THEN 1 END) * 100.0 / COUNT(b.id), 2) as success_rate,
                    AVG(b.weight) as avg_weight,
                    SUM(b.total_fee) as total_revenue
                    FROM airlines a
                    LEFT JOIN flights f ON a.id = f.airline_id
                    LEFT JOIN baggage b ON f.id = b.flight_id
                    WHERE DATE(b.created_at) BETWEEN ? AND ? OR b.created_at IS NULL
                    GROUP BY a.id, a.airline_name
                    ORDER BY total_baggage DESC");
                $stmt->execute([$dateFrom, $dateTo]);
                $reportData = $stmt->fetchAll();
                break;
        }
        
    } catch (PDOException $e) {
        error_log("Baggage Reports error: " . $e->getMessage());
        $reportData = [];
    }
}

// إحصائيات سريعة
try {
    // إجمالي الأمتعة اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as today_total FROM baggage WHERE DATE(created_at) = CURDATE()");
    $todayStats['total'] = $stmt->fetch()['today_total'];
    
    // الأمتعة المسلمة اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as today_delivered FROM baggage WHERE status = 'claimed' AND DATE(updated_at) = CURDATE()");
    $todayStats['delivered'] = $stmt->fetch()['today_delivered'];
    
    // الأمتعة المتأخرة
    $stmt = $pdo->query("SELECT COUNT(*) as delayed FROM baggage WHERE status = 'delayed'");
    $todayStats['delayed'] = $stmt->fetch()['delayed'];
    
    // الأمتعة المفقودة
    $stmt = $pdo->query("SELECT COUNT(*) as lost FROM baggage WHERE status = 'lost'");
    $todayStats['lost'] = $stmt->fetch()['lost'];
    
} catch (PDOException $e) {
    $todayStats = ['total' => 0, 'delivered' => 0, 'delayed' => 0, 'lost' => 0];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير الأمتعة - نظام تتبع الأمتعة الذكي</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'tajawal': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        primary: '#2c3e50',
                        secondary: '#3498db',
                        accent: '#e74c3c',
                        success: '#27ae60',
                        warning: '#f39c12',
                        light: '#ecf0f1',
                        dark: '#2c3e50',
                    }
                }
            }
        }
    </script>
</head>
<body class="font-tajawal bg-gray-50">
    <div class="flex h-screen">
        <!-- الشريط الجانبي -->
        <nav class="w-64 bg-primary text-white shadow-lg sidebar-custom">
            <div class="p-6">
                <div class="text-center mb-8 animate-fade-in">
                    <h4 class="text-white text-xl font-bold">
                        <i class="fas fa-suitcase mr-2"></i>
                        تقارير الأمتعة
                    </h4>
                    <small class="text-gray-300">نظام التتبع الذكي</small>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../admin/dashboard.php">
                            <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                            لوحة التحكم
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="search.php">
                            <i class="fas fa-search w-5 mr-3"></i>
                            البحث عن أمتعة
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="tracking.php">
                            <i class="fas fa-route w-5 mr-3"></i>
                            تتبع الأمتعة
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-white bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all duration-200" href="reports.php">
                            <i class="fas fa-chart-bar w-5 mr-3"></i>
                            التقارير
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="status.php">
                            <i class="fas fa-info-circle w-5 mr-3"></i>
                            حالة الأمتعة
                        </a>
                    </li>
                    <li class="mt-8">
                        <a class="flex items-center px-4 py-3 text-yellow-300 hover:text-yellow-200 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../includes/logout.php">
                            <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                            تسجيل الخروج
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 overflow-y-auto">
            <!-- شريط التنقل العلوي -->
            <nav class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <button class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900 mr-4">تقارير الأمتعة</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700">مرحباً، <?php echo $_SESSION['user_name']; ?></span>
                        <div class="relative">
                            <button class="flex items-center p-2 rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- إحصائيات سريعة -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-suitcase text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">أمتعة مسجلة اليوم</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $todayStats['total']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-check-circle text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">أمتعة مسلمة اليوم</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $todayStats['delivered']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">أمتعة متأخرة</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $todayStats['delayed']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">أمتعة مفقودة</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $todayStats['lost']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فلاتر التقرير -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h3 class="text-xl font-bold text-gray-900 mb-6">إنشاء تقرير</h3>
                    
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <label for="report_type" class="block text-sm font-semibold text-gray-700 mb-2">نوع التقرير</label>
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="report_type" name="report_type" required>
                                <option value="">اختر نوع التقرير</option>
                                <option value="baggage_summary" <?php echo $reportType === 'baggage_summary' ? 'selected' : ''; ?>>ملخص الأمتعة</option>
                                <option value="status_analysis" <?php echo $reportType === 'status_analysis' ? 'selected' : ''; ?>>تحليل الحالات</option>
                                <option value="flight_analysis" <?php echo $reportType === 'flight_analysis' ? 'selected' : ''; ?>>تحليل الرحلات</option>
                                <option value="airline_performance" <?php echo $reportType === 'airline_performance' ? 'selected' : ''; ?>>أداء شركات الطيران</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="date_from" class="block text-sm font-semibold text-gray-700 mb-2">من تاريخ</label>
                            <input type="date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" required>
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-semibold text-gray-700 mb-2">إلى تاريخ</label>
                            <input type="date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" required>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" name="generate_report" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                                <i class="fas fa-chart-line mr-2"></i>
                                إنشاء التقرير
                            </button>
                        </div>
                    </form>
                </div>

                <!-- عرض التقرير -->
                <?php if (!empty($reportData)): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900"><?php echo getReportTitle($reportType); ?></h3>
                        <p class="text-gray-600">من <?php echo $dateFrom; ?> إلى <?php echo $dateTo; ?></p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <?php echo getReportTableHeader($reportType); ?>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php echo getReportTableBody($reportData, $reportType); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // تعيين التاريخ الافتراضي
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            if (!document.getElementById('date_from').value) {
                document.getElementById('date_from').value = weekAgo;
            }
            if (!document.getElementById('date_to').value) {
                document.getElementById('date_to').value = today;
            }
        });
    </script>
</body>
</html>

<?php
// دوال مساعدة للتقارير
function getReportTitle($reportType) {
    $titles = [
        'baggage_summary' => 'ملخص الأمتعة',
        'status_analysis' => 'تحليل حالات الأمتعة',
        'flight_analysis' => 'تحليل الرحلات',
        'airline_performance' => 'أداء شركات الطيران'
    ];
    return $titles[$reportType] ?? 'تقرير';
}

function getReportTableHeader($reportType) {
    switch ($reportType) {
        case 'baggage_summary':
            return '<tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">إجمالي الأمتعة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مسلمة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متأخرة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مفقودة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متوسط الوزن</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإيرادات</th></tr>';
        case 'status_analysis':
            return '<tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">العدد</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">النسبة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متوسط الوزن</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإيرادات</th></tr>';
        case 'flight_analysis':
            return '<tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">رقم الرحلة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">شركة الطيران</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المسار</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">إجمالي الأمتعة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مسلمة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متأخرة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مفقودة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متوسط الوزن</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإيرادات</th></tr>';
        case 'airline_performance':
            return '<tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">شركة الطيران</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">إجمالي الأمتعة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مسلمة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متأخرة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">مفقودة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">معدل النجاح</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">متوسط الوزن</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإيرادات</th></tr>';
        default:
            return '<tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">البيانات</th></tr>';
    }
}

function getReportTableBody($data, $reportType) {
    $html = '';
    foreach ($data as $index => $row) {
        $html .= '<tr class="hover:bg-gray-50 transition-colors duration-200 ' . ($index % 2 === 0 ? 'bg-white' : 'bg-gray-50') . '">';
        switch ($reportType) {
            case 'baggage_summary':
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . $row['report_date'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">' . $row['total_baggage'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . $row['delivered'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-semibold">' . $row['delayed'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">' . $row['lost'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . round($row['avg_weight'], 2) . ' كغ</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . number_format($row['total_revenue'], 2) . ' ريال</td>';
                break;
            case 'status_analysis':
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . getBaggageStatusText($row['status']) . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">' . $row['count'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . $row['percentage'] . '%</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . round($row['avg_weight'], 2) . ' كغ</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . number_format($row['total_revenue'], 2) . ' ريال</td>';
                break;
            case 'flight_analysis':
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . htmlspecialchars($row['flight_number']) . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($row['airline_name']) . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($row['origin'] . ' → ' . $row['destination']) . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">' . $row['total_baggage'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . $row['delivered'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-semibold">' . $row['delayed'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">' . $row['lost'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . round($row['avg_weight'], 2) . ' كغ</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . number_format($row['total_revenue'], 2) . ' ريال</td>';
                break;
            case 'airline_performance':
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' . htmlspecialchars($row['airline_name']) . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">' . $row['total_baggage'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . $row['delivered'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-semibold">' . $row['delayed'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">' . $row['lost'] . '</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . $row['success_rate'] . '%</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' . round($row['avg_weight'], 2) . ' كغ</td>';
                $html .= '<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">' . number_format($row['total_revenue'], 2) . ' ريال</td>';
                break;
        }
        $html .= '</tr>';
    }
    return $html;
}
?>

