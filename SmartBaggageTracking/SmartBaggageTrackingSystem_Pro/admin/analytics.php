<?php
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin', 'airline_manager', 'supervisor']);
$pdo = $db->getConnection();

// Collect data for analytics
$analytics = [];
try {
    // Baggage statistics by status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM baggage 
        GROUP BY status
    ");
    $baggage_stats = $stmt->fetchAll();

    // User statistics by role
    $stmt = $pdo->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE status = 'active'
        GROUP BY role
    ");
    $user_stats = $stmt->fetchAll();

    // Baggage registered in the last 7 days
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $weekly_baggage = $stmt->fetchAll();

    // Most active airlines
    $stmt = $pdo->query("
        SELECT a.airline_name, COUNT(b.id) as baggage_count
        FROM baggage b
        JOIN flights f ON b.flight_id = f.id
        JOIN airlines a ON f.airline_id = a.id
        GROUP BY a.id, a.airline_name
        ORDER BY baggage_count DESC
        LIMIT 5
    ");
    $airline_stats = $stmt->fetchAll();

    // Airport statistics
    $stmt = $pdo->query("
        SELECT ap.airport_name, ap.city, COUNT(b.id) as baggage_count
        FROM baggage b
        JOIN flights f ON b.flight_id = f.id
        JOIN airports ap ON f.destination_airport_id = ap.id
        GROUP BY ap.id, ap.airport_name, ap.city
        ORDER BY baggage_count DESC
        LIMIT 10
    ");
    $airport_stats = $stmt->fetchAll();

    // Peak hours statistics
    $stmt = $pdo->query("
        SELECT HOUR(created_at) as hour, COUNT(*) as count
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $peak_hours = $stmt->fetchAll();

    // Performance statistics
    $stmt = $pdo->query("
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_processing_time,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as delivered_count,
            COUNT(CASE WHEN status = 'lost' THEN 1 END) as lost_count,
            COUNT(*) as total_count
        FROM baggage 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $performance_stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Analytics error: " . $e->getMessage());
    $baggage_stats = $user_stats = $weekly_baggage = $airline_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Statistics - Smart Baggage Tracking System</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/assets/css/tailwind-custom.css">
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
        <!-- Sidebar -->
        <nav class="w-64 bg-primary text-white shadow-lg sidebar-custom">
            <div class="p-6">
                <div class="text-center mb-8 animate-fade-in">
                    <h4 class="text-white text-xl font-bold">
                        <i class="fas fa-plane-departure mr-2"></i>
                            Tracking System
                        </h4>
                    <small class="text-gray-300">Admin Dashboard</small>
                    </div>
                    
                <ul class="space-y-2">
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="dashboard.php">
                            <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                                Dashboard
                            </a>
                        </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-white bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all duration-200" href="analytics.php">
                            <i class="fas fa-chart-bar w-5 mr-3"></i>
                                Analytics
                            </a>
                        </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="user_management.php">
                            <i class="fas fa-users w-5 mr-3"></i>
                            User Management
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../flights/management.php">
                            <i class="fas fa-plane w-5 mr-3"></i>
                            Flight Management
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../baggage/reports.php">
                            <i class="fas fa-suitcase w-5 mr-3"></i>
                            Baggage Reports
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="system_settings.php">
                            <i class="fas fa-cog w-5 mr-3"></i>
                            System Settings
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="backup.php">
                            <i class="fas fa-database w-5 mr-3"></i>
                            Backups
                        </a>
                    </li>
                    <li class="mt-8">
                        <a class="flex items-center px-4 py-3 text-yellow-300 hover:text-yellow-200 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../includes/logout.php">
                            <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                            Logout
                        </a>
                    </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
                <!-- Top Navigation Bar -->
            <nav class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <button class="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                                <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900 mr-4">Analytics & Statistics</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700">Welcome, <?php echo $_SESSION['user_name']; ?></span>
                        <div class="relative">
                            <button class="flex items-center p-2 rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                                <a href="../pages/profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                                <a href="../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                        </div>
                        </div>
                        </div>
                    </div>
                </nav>

                <!-- Statistical Charts -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Baggage Distribution by Status -->
                    <div class="bg-white rounded-xl shadow-lg card-custom">
                        <div class="p-6 border-b border-gray-200 card-header-custom">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-chart-pie mr-2 text-blue-600"></i>Baggage Distribution by Status
                            </h5>
                        </div>
                        <div class="p-6 card-body-custom">
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- User Distribution -->
                    <div class="bg-white rounded-xl shadow-lg card-custom">
                        <div class="p-6 border-b border-gray-200 card-header-custom">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-users mr-2 text-green-600"></i>User Distribution by Role
                            </h5>
                        </div>
                        <div class="p-6 card-body-custom">
                            <canvas id="userChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Baggage Registered in Last 7 Days -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-lg card-custom">
                            <div class="p-6 border-b border-gray-200 card-header-custom">
                                <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-chart-line mr-2 text-purple-600"></i>Baggage Registered in Last 7 Days
                                </h5>
                            </div>
                            <div class="p-6 card-body-custom">
                                <canvas id="weeklyChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Most Active Airlines -->
                    <div>
                        <div class="bg-white rounded-xl shadow-lg card-custom">
                            <div class="p-6 border-b border-gray-200 card-header-custom">
                                <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-plane mr-2 text-indigo-600"></i>Most Active Airlines
                                </h5>
                            </div>
                            <div class="p-6 card-body-custom">
                                <div class="space-y-4">
                                    <?php foreach ($airline_stats as $index => $airline): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900"><?php echo $index + 1; ?>. <?php echo htmlspecialchars($airline['airline_name']); ?></span>
                                        </div>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo $airline['baggage_count']; ?>
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($airline['baggage_count'] / max(1, $airline_stats[0]['baggage_count'])) * 100); ?>%"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">Average Processing Time</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($performance_stats['avg_processing_time'], 1); ?> hours</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-percentage text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">Delivery Rate</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $performance_stats['total_count'] > 0 ? number_format(($performance_stats['delivered_count'] / $performance_stats['total_count']) * 100, 1) : 0; ?>%</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <i class="fas fa-exclamation-triangle text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">Loss Rate</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $performance_stats['total_count'] > 0 ? number_format(($performance_stats['lost_count'] / $performance_stats['total_count']) * 100, 1) : 0; ?>%</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-cyan-100 text-cyan-600">
                                <i class="fas fa-chart-line text-2xl"></i>
                            </div>
                            <div class="mr-4">
                                <p class="text-sm font-medium text-gray-600">Total Processed (30 days)</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $performance_stats['total_count']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Peak Hours and Airports -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Peak Hours -->
                    <div class="bg-white rounded-xl shadow-lg card-custom">
                        <div class="p-6 border-b border-gray-200 card-header-custom">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-clock mr-2 text-orange-600"></i>Peak Hours
                            </h5>
                        </div>
                        <div class="p-6 card-body-custom">
                            <canvas id="peakHoursChart" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Most Active Airports -->
                    <div class="bg-white rounded-xl shadow-lg card-custom">
                        <div class="p-6 border-b border-gray-200 card-header-custom">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-plane-departure mr-2 text-cyan-600"></i>Most Active Airports
                            </h5>
                        </div>
                        <div class="p-6 card-body-custom">
                            <div class="space-y-4">
                                <?php foreach ($airport_stats as $index => $airport): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900"><?php echo $index + 1; ?>. <?php echo htmlspecialchars($airport['airport_name']); ?></span>
                                        <br><small class="text-gray-500"><?php echo htmlspecialchars($airport['city']); ?></small>
                                    </div>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo $airport['baggage_count']; ?>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($airport['baggage_count'] / max(1, $airport_stats[0]['baggage_count'])) * 100); ?>%"></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics -->
                <div class="bg-white rounded-xl shadow-lg card-custom">
                    <div class="p-6 border-b border-gray-200 card-header-custom">
                        <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-table mr-2 text-gray-600"></i>Detailed Statistics
                        </h5>
                    </div>
                    <div class="p-6 card-body-custom">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 text-center">
                            <?php
                            $total_baggage = array_sum(array_column($baggage_stats, 'count'));
                            foreach ($baggage_stats as $stat):
                                $percentage = $total_baggage > 0 ? ($stat['count'] / $total_baggage) * 100 : 0;
                            ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $stat['count']; ?></div>
                                <div class="text-sm font-medium text-gray-700 mb-2"><?php echo getBaggageStatusText($stat['status']); ?></div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <small class="text-gray-500"><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Baggage distribution by status chart
        const statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($baggage_stats as $stat): ?>
                    '<?php echo getBaggageStatusText($stat['status']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($baggage_stats as $stat): ?>
                        <?php echo $stat['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6',
                        '#1abc9c', '#34495e', '#95a5a6'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // User distribution chart
        const userChart = new Chart(document.getElementById('userChart'), {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($user_stats as $stat): ?>
                    '<?php echo getUserRoleText($stat['role']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($user_stats as $stat): ?>
                        <?php echo $stat['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Weekly baggage chart
        const weeklyChart = new Chart(document.getElementById('weeklyChart'), {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($weekly_baggage as $data): ?>
                    '<?php echo date('m/d', strtotime($data['date'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Baggage',
                    data: [
                        <?php foreach ($weekly_baggage as $data): ?>
                        <?php echo $data['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Peak hours chart
        const peakHoursChart = new Chart(document.getElementById('peakHoursChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($peak_hours as $hour): ?>
                    '<?php echo $hour['hour']; ?>:00',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Number of Baggage',
                    data: [
                        <?php foreach ($peak_hours as $hour): ?>
                        <?php echo $hour['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(52, 152, 219, 0.8)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>
