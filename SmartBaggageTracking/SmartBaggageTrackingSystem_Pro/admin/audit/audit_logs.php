<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security/role_guard.php';

// Check system admin permissions only
requireRole(['system_admin']);

$db = new Database();
$pdo = $db->getConnection();

// Handle filtering and search
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$log_type = $_GET['log_type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(user_email LIKE ? OR action LIKE ? OR details LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($date_from)) {
    $where_conditions[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $where_conditions[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($log_type)) {
    $where_conditions[] = "log_type = ?";
    $params[] = $log_type;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// إحصائيات سريعة
$stats = [];
try {
    // إجمالي السجلات
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
    $stats['total'] = $stmt->fetch()['total'];
    
    // سجلات اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = $stmt->fetch()['today'];
    
    // أكثر المستخدمين نشاطاً
    $stmt = $pdo->query("
        SELECT user_email, COUNT(*) as activity_count 
        FROM audit_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY user_email 
        ORDER BY activity_count DESC 
        LIMIT 5
    ");
    $stats['top_users'] = $stmt->fetchAll();
    
    // أنواع السجلات
    $stmt = $pdo->query("
        SELECT log_type, COUNT(*) as count 
        FROM audit_logs 
        GROUP BY log_type 
        ORDER BY count DESC
    ");
    $stats['log_types'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Audit logs error: " . $e->getMessage());
    $stats = ['total' => 0, 'today' => 0, 'top_users' => [], 'log_types' => []];
}

// جلب السجلات
try {
    $count_sql = "SELECT COUNT(*) as total FROM audit_logs {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    
    $sql = "
        SELECT * FROM audit_logs 
        {$where_clause}
        ORDER BY created_at DESC 
        LIMIT {$per_page} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    $total_pages = ceil($total_records / $per_page);
    
} catch (PDOException $e) {
    error_log("Audit logs query error: " . $e->getMessage());
    $logs = [];
    $total_pages = 0;
}

// Log types
$log_types = [
    'login' => 'Login',
    'logout' => 'Logout',
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
    'view' => 'View',
    'export' => 'Export',
    'security' => 'Security',
    'error' => 'Error'
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Smart Baggage Tracking System</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'tajawal': ['Tajawal', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-tajawal bg-gray-50">
    <div class="flex h-screen">
        <!-- الشريط الجانبي -->
        <nav class="w-64 bg-gray-800 text-white shadow-lg">
            <div class="p-6">
                <div class="text-center mb-8">
                    <h4 class="text-white text-xl font-bold">
                        <i class="fas fa-shield-alt mr-2"></i>
                        سجلات التدقيق
                    </h4>
                    <small class="text-gray-300">مراقبة النشاط</small>
                </div>
                    
                <ul class="space-y-2">
                    <li>
                        <a class="flex items-center px-4 py-3 text-white bg-white bg-opacity-20 rounded-lg" href="audit_logs.php">
                            <i class="fas fa-list w-5 mr-3"></i>
                            جميع السجلات
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="change_history.php">
                            <i class="fas fa-history w-5 mr-3"></i>
                            تاريخ التغييرات
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="user_activity.php">
                            <i class="fas fa-user-activity w-5 mr-3"></i>
                            نشاط المستخدمين
                        </a>
                    </li>
                    <li class="mt-8">
                        <a class="flex items-center px-4 py-3 text-yellow-300 hover:text-yellow-200 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../dashboard.php">
                            <i class="fas fa-arrow-right w-5 mr-3"></i>
                            العودة للوحة التحكم
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
                        <h1 class="text-2xl font-bold text-gray-900 mr-4">سجلات التدقيق</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700">مرحباً، <?php echo $_SESSION['user_name']; ?></span>
                    </div>
                </div>
            </nav>

            <!-- إحصائيات سريعة -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-list text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">إجمالي السجلات</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-calendar-day text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">سجلات اليوم</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['today']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">مستخدمين نشطين</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($stats['top_users']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">أخطاء أمنية</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php 
                                    $security_errors = array_filter($stats['log_types'], function($type) {
                                        return $type['log_type'] === 'security';
                                    });
                                    echo count($security_errors) > 0 ? $security_errors[0]['count'] : '0';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- فلاتر البحث -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">فلاتر البحث</h3>
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">البحث</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="البحث في السجلات...">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">من تاريخ</label>
                            <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">إلى تاريخ</label>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">نوع السجل</label>
                            <select name="log_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">جميع الأنواع</option>
                                <?php foreach ($log_types as $type => $label): ?>
                                <option value="<?php echo $type; ?>" <?php echo $log_type === $type ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-4 flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-search mr-2"></i>بحث
                            </button>
                            <a href="audit_logs.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                                <i class="fas fa-times mr-2"></i>مسح
                            </a>
                        </div>
                    </form>
                </div>

                <!-- جدول السجلات -->
                <div class="bg-white rounded-xl shadow-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">سجلات التدقيق</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ والوقت</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المستخدم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراء</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">النوع</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التفاصيل</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-4"></i>
                                        <p>لا توجد سجلات متاحة</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['user_email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php 
                                            $type_colors = [
                                                'login' => 'bg-green-100 text-green-800',
                                                'logout' => 'bg-gray-100 text-gray-800',
                                                'create' => 'bg-blue-100 text-blue-800',
                                                'update' => 'bg-yellow-100 text-yellow-800',
                                                'delete' => 'bg-red-100 text-red-800',
                                                'security' => 'bg-red-100 text-red-800',
                                                'error' => 'bg-red-100 text-red-800'
                                            ];
                                            echo $type_colors[$log['log_type']] ?? 'bg-gray-100 text-gray-800';
                                            ?>">
                                            <?php echo $log_types[$log['log_type']] ?? $log['log_type']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- ترقيم الصفحات -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                عرض <?php echo $offset + 1; ?> إلى <?php echo min($offset + $per_page, $total_records); ?> من <?php echo $total_records; ?> سجل
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    السابق
                                </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="px-3 py-2 text-sm rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    التالي
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
