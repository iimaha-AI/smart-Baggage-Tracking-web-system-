<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// التحقق من صلاحيات موظف المعالجة
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['handling_staff', 'supervisor', 'system_admin', 'airline_manager']);
$pdo = $db->getConnection();

$userInfo = $auth->getUserInfo();

// جلب مواقع الأمتعة
$baggage_locations = [];
try {
    $stmt = $pdo->query("
        SELECT b.*, u.full_name, f.flight_number, f.departure_time
        FROM baggage b
        JOIN users u ON b.passenger_id = u.id
        JOIN flights f ON b.flight_id = f.id
        WHERE b.status IN ('checked_in', 'security_check', 'sorting_facility', 'loaded_to_aircraft')
        ORDER BY b.updated_at DESC
    ");
    $baggage_locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Baggage location error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مواقع الأمتعة - نظام تتبع الأمتعة الذكي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .location-card { transition: all 0.3s ease; }
        .location-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <nav class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="../staff/dashboard.php" class="text-blue-600 hover:text-blue-800 mr-4"><i class="fas fa-arrow-right"></i> العودة للوحة التحكم</a>
                    <h1 class="text-2xl font-bold text-gray-900">مواقع الأمتعة</h1>
                </div>
                <div class="flex items-center space-x-4"><span class="text-gray-700">مرحباً، <?php echo htmlspecialchars($userInfo['full_name']); ?></span></div>
            </div>
        </nav>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-600">مسجلة</p><p class="text-3xl font-bold text-blue-600"><?php echo count(array_filter($baggage_locations, fn($b) => $b['status'] === 'checked_in')); ?></p></div><div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"><i class="fas fa-check-circle text-blue-600 text-xl"></i></div></div></div>
                <div class="bg-white rounded-xl shadow-lg p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-600">فحص أمني</p><p class="text-3xl font-bold text-yellow-600"><?php echo count(array_filter($baggage_locations, fn($b) => $b['status'] === 'security_check')); ?></p></div><div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center"><i class="fas fa-shield-alt text-yellow-600 text-xl"></i></div></div></div>
                <div class="bg-white rounded-xl shadow-lg p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-600">مركز الفرز</p><p class="text-3xl font-bold text-purple-600"><?php echo count(array_filter($baggage_locations, fn($b) => $b['status'] === 'sorting_facility')); ?></p></div><div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fas fa-sort text-purple-600 text-xl"></i></div></div></div>
                <div class="bg-white rounded-xl shadow-lg p-6"><div class="flex items-center justify-between"><div><p class="text-sm font-medium text-gray-600">محملة</p><p class="text-3xl font-bold text-green-600"><?php echo count(array_filter($baggage_locations, fn($b) => $b['status'] === 'loaded_to_aircraft')); ?></p></div><div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center"><i class="fas fa-plane text-green-600 text-xl"></i></div></div></div>
            </div>
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200"><h2 class="text-xl font-semibold text-gray-900 flex items-center"><i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>مواقع الأمتعة الحالية</h2></div>
                <div class="p-6">
                    <?php if (empty($baggage_locations)): ?>
                    <div class="text-center py-12"><i class="fas fa-suitcase text-gray-400 text-6xl mb-4"></i><h3 class="text-xl font-semibold text-gray-500 mb-2">لا توجد أمتعة في النظام</h3><p class="text-gray-400">ستظهر مواقع الأمتعة هنا عند تسجيلها</p></div>
                    <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($baggage_locations as $baggage): ?>
                        <div class="location-card bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3"><div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"><i class="fas fa-suitcase text-blue-600"></i></div><div><h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($baggage['baggage_code']); ?></h4><p class="text-sm text-gray-500"><?php echo htmlspecialchars($baggage['full_name']); ?></p></div></div>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php $statusColors=['checked_in'=>'bg-blue-100 text-blue-800','security_check'=>'bg-yellow-100 text-yellow-800','sorting_facility'=>'bg-purple-100 text-purple-800','loaded_to_aircraft'=>'bg-green-100 text-green-800']; echo $statusColors[$baggage['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php $statusTexts=['checked_in'=>'مسجلة','security_check'=>'فحص أمني','sorting_facility'=>'مركز الفرز','loaded_to_aircraft'=>'محملة']; echo $statusTexts[$baggage['status']] ?? $baggage['status']; ?></span>
                            </div>
                            <div class="space-y-3"><div class="flex justify-between text-sm"><span class="text-gray-500">الرحلة:</span><span class="font-medium"><?php echo htmlspecialchars($baggage['flight_number']); ?></span></div><div class="flex justify-between text-sm"><span class="text-gray-500">الوزن:</span><span class="font-medium"><?php echo $baggage['weight']; ?> كيلو</span></div><div class="flex justify-between text-sm"><span class="text-gray-500">آخر تحديث:</span><span class="font-medium"><?php echo date('H:i', strtotime($baggage['updated_at'])); ?></span></div></div>
                            <div class="mt-4 flex gap-2"><button class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition-colors"><i class="fas fa-eye mr-1"></i>عرض التفاصيل</button><button class="flex-1 bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition-colors"><i class="fas fa-map-marker-alt mr-1"></i>تحديث الموقع</button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>setInterval(function(){if(document.visibilityState==='visible'){location.reload();}},30000);</script>
</body>
</html>
