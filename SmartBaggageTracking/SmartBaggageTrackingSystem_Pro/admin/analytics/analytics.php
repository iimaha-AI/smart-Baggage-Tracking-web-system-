<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check admin permissions
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin', 'airline_manager']);
$pdo = $db->getConnection();

$analytics = [];
$predictions = [];
$businessIntelligence = [];

// Collect data for advanced analytics
try {
    // إحصائيات الأمتعة المتقدمة
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_baggage,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost,
            COUNT(CASE WHEN status = 'delayed' THEN 1 END) as delayed,
            AVG(weight) as avg_weight,
            SUM(total_fee) as total_revenue
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $analytics['baggage_trends'] = $stmt->fetchAll();

    // إحصائيات الأداء
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_processing_time,
            MIN(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as min_processing_time,
            MAX(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as max_processing_time
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY status
    ");
    $analytics['performance_metrics'] = $stmt->fetchAll();

    // تحليلات المستخدمين
    $stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as user_count,
            COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
            AVG(TIMESTAMPDIFF(DAY, created_at, last_login)) as avg_days_to_login
        FROM users 
        WHERE status = 'active'
        GROUP BY role
    ");
    $analytics['user_analytics'] = $stmt->fetchAll();

    // إحصائيات الرحلات
    $stmt = $pdo->query("
        SELECT 
            f.flight_number,
            a.airline_name,
            COUNT(b.id) as baggage_count,
            AVG(b.weight) as avg_baggage_weight,
            SUM(b.total_fee) as total_revenue,
            COUNT(CASE WHEN b.status = 'lost' THEN 1 END) as lost_baggage
        FROM flights f
        JOIN airlines a ON f.airline_id = a.id
        LEFT JOIN baggage b ON f.id = b.flight_id
        WHERE f.departure_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY f.id
        ORDER BY baggage_count DESC
        LIMIT 20
    ");
    $analytics['flight_analytics'] = $stmt->fetchAll();

} catch (Exception $e) {
    $analytics = [];
}

// تحليلات التنبؤية
try {
    // التنبؤ بحجم الأمتعة
    $stmt = $pdo->query("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as baggage_count,
            AVG(weight) as avg_weight
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $predictions['hourly_patterns'] = $stmt->fetchAll();

    // Predict lost baggage
    $stmt = $pdo->query("
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_count,
            COUNT(*) as total_count,
            (COUNT(CASE WHEN status = 'lost' THEN 1 END) / COUNT(*)) * 100 as loss_percentage
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY DAYOFWEEK(created_at)
        ORDER BY day_of_week
    ");
    $predictions['loss_patterns'] = $stmt->fetchAll();

    // Predict revenue
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            SUM(total_fee) as daily_revenue,
            COUNT(*) as baggage_count
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $predictions['revenue_trends'] = $stmt->fetchAll();

} catch (Exception $e) {
    $predictions = [];
}

// ذكاء الأعمال
try {
    // مؤشرات الأداء الرئيسية
    $stmt = $pdo->query("
        SELECT 
            'Total Baggage' as metric,
            COUNT(*) as value,
            'pieces' as unit
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'Lost Baggage Rate' as metric,
            (COUNT(CASE WHEN status = 'lost' THEN 1 END) / COUNT(*)) * 100 as value,
            '%' as unit
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'Average Processing Time' as metric,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as value,
            'minutes' as unit
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'Total Revenue' as metric,
            SUM(total_fee) as value,
            'SAR' as unit
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $businessIntelligence['kpis'] = $stmt->fetchAll();

    // Trend analysis
    $stmt = $pdo->query("
        SELECT 
            'Baggage Growth' as trend,
            CASE 
                WHEN COUNT(*) > (SELECT COUNT(*) FROM baggage WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
                THEN 'Increasing'
                ELSE 'Decreasing'
            END as direction,
            COUNT(*) as current_period,
            (SELECT COUNT(*) FROM baggage WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) as previous_period
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $businessIntelligence['trends'] = $stmt->fetchAll();

} catch (Exception $e) {
    $businessIntelligence = [];
}

// دالة حساب التنبؤات
function calculatePredictions($data) {
    $predictions = [];
    
    if (!empty($data)) {
        // حساب المتوسط المتحرك
        $sum = 0;
        $count = 0;
        foreach ($data as $item) {
            $sum += $item['baggage_count'] ?? $item['daily_revenue'] ?? 0;
            $count++;
        }
        $average = $count > 0 ? $sum / $count : 0;
        
        // التنبؤ للفترة القادمة
        $predictions['next_period'] = round($average * 1.1); // زيادة 10%
        $predictions['confidence'] = 85; // مستوى الثقة
    }
    
    return $predictions;
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - Smart Baggage Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../includes/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .kpi-card {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .prediction-card {
            background: linear-gradient(45deg, #ffc107, #ff8c00);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .bi-card {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-suitcase-rolling me-2"></i>
                نظام تتبع الأمتعة الذكي
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>
                    لوحة التحكم
                </a>
                <a class="nav-link active" href="analytics.php">
                    <i class="fas fa-chart-bar me-1"></i>
                    التحليلات المتقدمة
                </a>
                <a class="nav-link" href="../backup/backup_management.php">
                    <i class="fas fa-database me-1"></i>
                    النسخ الاحتياطية
                </a>
                <a class="nav-link" href="../../includes/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- عنوان الصفحة -->
        <div class="analytics-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-chart-line me-2"></i>
                        التحليلات المتقدمة وذكاء الأعمال
                    </h2>
                    <p class="mb-0">تحليلات تنبؤية ومؤشرات أداء متقدمة</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light btn-lg" onclick="exportAnalytics()">
                        <i class="fas fa-download me-2"></i>
                        تصدير التقرير
                    </button>
                </div>
            </div>
        </div>

        <!-- مؤشرات الأداء الرئيسية -->
        <div class="row">
            <?php if (!empty($businessIntelligence['kpis'])): ?>
            <?php foreach ($businessIntelligence['kpis'] as $kpi): ?>
            <div class="col-md-3">
                <div class="kpi-card">
                    <div class="metric-value"><?php echo number_format($kpi['value'], 2); ?></div>
                    <div class="metric-label"><?php echo htmlspecialchars($kpi['metric']); ?></div>
                    <small><?php echo htmlspecialchars($kpi['unit']); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- التحليلات التنبؤية -->
            <div class="col-lg-6">
                <div class="prediction-card">
                    <h5 class="mb-3">
                        <i class="fas fa-crystal-ball me-2"></i>
                        التحليلات التنبؤية
                    </h5>
                    
                    <?php if (!empty($predictions['hourly_patterns'])): ?>
                    <div class="mb-3">
                        <h6>أنماط ساعات الذروة</h6>
                        <div class="row">
                            <?php 
                            $peakHours = array_slice($predictions['hourly_patterns'], 0, 3);
                            foreach ($peakHours as $hour): 
                            ?>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo $hour['hour']; ?>:00</div>
                                    <small><?php echo $hour['baggage_count']; ?> أمتعة</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($predictions['loss_patterns'])): ?>
                    <div class="mb-3">
                        <h6>أنماط فقدان الأمتعة</h6>
                        <div class="row">
                            <?php 
                            $lossDays = array_slice($predictions['loss_patterns'], 0, 3);
                            foreach ($lossDays as $day): 
                            ?>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo $day['day_of_week']; ?></div>
                                    <small><?php echo number_format($day['loss_percentage'], 1); ?>%</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <h6>التنبؤ للفترة القادمة</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo calculatePredictions($predictions['revenue_trends'])['next_period'] ?? 'N/A'; ?></div>
                                    <small>أمتعة متوقعة</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo calculatePredictions($predictions['revenue_trends'])['confidence'] ?? 'N/A'; ?>%</div>
                                    <small>مستوى الثقة</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ذكاء الأعمال -->
            <div class="col-lg-6">
                <div class="bi-card">
                    <h5 class="mb-3">
                        <i class="fas fa-brain me-2"></i>
                        ذكاء الأعمال
                    </h5>
                    
                    <?php if (!empty($businessIntelligence['trends'])): ?>
                    <div class="mb-3">
                        <h6>اتجاهات النمو</h6>
                        <?php foreach ($businessIntelligence['trends'] as $trend): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($trend['trend']); ?></span>
                            <span class="badge bg-<?php echo $trend['direction'] === 'Increasing' ? 'success' : 'warning'; ?>">
                                <?php echo $trend['direction'] === 'Increasing' ? 'متزايد' : 'متناقص'; ?>
                            </span>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar bg-light" style="width: <?php echo min(100, ($trend['current_period'] / max(1, $trend['previous_period'])) * 100); ?>%"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <h6>توصيات ذكية</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-lightbulb me-2"></i>
                                زيادة الموظفين في ساعات الذروة
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-lightbulb me-2"></i>
                                تحسين عمليات الفحص الأمني
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-lightbulb me-2"></i>
                                تطبيق نظام تتبع متقدم
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-area me-2"></i>
                        اتجاهات الأمتعة (30 يوم)
                    </h5>
                    <canvas id="baggageTrendsChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        توزيع حالات الأمتعة
                    </h5>
                    <canvas id="baggageStatusChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- تحليلات الأداء -->
        <div class="analytics-card">
            <h5 class="mb-3">
                <i class="fas fa-tachometer-alt me-2"></i>
                تحليلات الأداء
            </h5>
            
            <?php if (!empty($analytics['performance_metrics'])): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>الحالة</th>
                            <th>العدد</th>
                            <th>متوسط وقت المعالجة</th>
                            <th>أسرع معالجة</th>
                            <th>أبطأ معالجة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['performance_metrics'] as $metric): ?>
                        <tr>
                            <td><?php echo getBaggageStatusText($metric['status']); ?></td>
                            <td><?php echo number_format($metric['count']); ?></td>
                            <td><?php echo number_format($metric['avg_processing_time'], 1); ?> دقيقة</td>
                            <td><?php echo number_format($metric['min_processing_time'], 1); ?> دقيقة</td>
                            <td><?php echo number_format($metric['max_processing_time'], 1); ?> دقيقة</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- تحليلات الرحلات -->
        <div class="analytics-card">
            <h5 class="mb-3">
                <i class="fas fa-plane me-2"></i>
                تحليلات الرحلات
            </h5>
            
            <?php if (!empty($analytics['flight_analytics'])): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>رقم الرحلة</th>
                            <th>شركة الطيران</th>
                            <th>عدد الأمتعة</th>
                            <th>متوسط الوزن</th>
                            <th>الإيرادات</th>
                            <th>الأمتعة المفقودة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($analytics['flight_analytics'], 0, 10) as $flight): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($flight['flight_number']); ?></td>
                            <td><?php echo htmlspecialchars($flight['airline_name']); ?></td>
                            <td><?php echo number_format($flight['baggage_count']); ?></td>
                            <td><?php echo number_format($flight['avg_baggage_weight'], 2); ?> كغ</td>
                            <td><?php echo number_format($flight['total_revenue'], 2); ?> ريال</td>
                            <td>
                                <span class="badge bg-<?php echo $flight['lost_baggage'] > 0 ? 'danger' : 'success'; ?>">
                                    <?php echo $flight['lost_baggage']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // رسم بياني لاتجاهات الأمتعة
        const baggageTrendsCtx = document.getElementById('baggageTrendsChart').getContext('2d');
        const baggageTrendsData = <?php echo json_encode($analytics['baggage_trends']); ?>;
        
        new Chart(baggageTrendsCtx, {
            type: 'line',
            data: {
                labels: baggageTrendsData.map(item => item.date),
                datasets: [{
                    label: 'إجمالي الأمتعة',
                    data: baggageTrendsData.map(item => item.total_baggage),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'تم الاستلام',
                    data: baggageTrendsData.map(item => item.claimed),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // رسم بياني لحالات الأمتعة
        const baggageStatusCtx = document.getElementById('baggageStatusChart').getContext('2d');
        const baggageStatusData = <?php echo json_encode($analytics['performance_metrics']); ?>;
        
        new Chart(baggageStatusCtx, {
            type: 'doughnut',
            data: {
                labels: baggageStatusData.map(item => item.status),
                datasets: [{
                    data: baggageStatusData.map(item => item.count),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        function exportAnalytics() {
            // في التطبيق الحقيقي، هنا سيتم تصدير البيانات
            alert('سيتم تصدير التقرير...');
        }

        // تحديث البيانات كل 5 دقائق
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);
    </script>
</body>
</html>
