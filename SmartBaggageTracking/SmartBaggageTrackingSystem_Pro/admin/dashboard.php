<?php
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security/role_guard.php';

// Check admin permissions
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin', 'airline_manager', 'supervisor']);
$pdo = $db->getConnection();

// Helper functions for colors
function getStatusColor($status) {
    $colors = [
        'checked_in' => 'blue',
        'security_check' => 'yellow',
        'loaded_to_aircraft' => 'purple',
        'in_transit' => 'indigo',
        'arrived' => 'green',
        'claimed' => 'green',
        'lost' => 'red',
        'delayed' => 'orange'
    ];
    return $colors[$status] ?? 'gray';
}

function getFlightStatusColor($status) {
    $colors = [
        'scheduled' => 'blue',
        'boarding' => 'yellow',
        'departed' => 'purple',
        'in_air' => 'indigo',
        'arrived' => 'green',
        'delayed' => 'orange',
        'cancelled' => 'red'
    ];
    return $colors[$status] ?? 'gray';
}

function getBaggageStatusText($status) {
    $statuses = [
        'checked_in' => 'Checked In',
        'security_check' => 'Security Check',
        'sorting_facility' => 'Sorting Facility',
        'loaded_to_aircraft' => 'Loaded to Aircraft',
        'in_transit' => 'In Transit',
        'unloaded_from_aircraft' => 'Unloaded from Aircraft',
        'at_carousel' => 'At Carousel',
        'claimed' => 'Claimed',
        'delayed' => 'Delayed',
        'lost' => 'Lost'
    ];
    return $statuses[$status] ?? $status;
}

function getFlightStatusText($status) {
    $statuses = [
        'scheduled' => 'Scheduled',
        'boarding' => 'Boarding',
        'departed' => 'Departed',
        'in_air' => 'In Air',
        'landed' => 'Landed',
        'arrived' => 'Arrived',
        'canceled' => 'Canceled',
        'delayed' => 'Delayed'
    ];
    return $statuses[$status] ?? $status;
}

// System statistics
$stats = [];
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $stats['total_users'] = $stmt->fetch()['total_users'];

    // Total flights
    $stmt = $pdo->query("SELECT COUNT(*) as total_flights FROM flights");
    $stats['total_flights'] = $stmt->fetch()['total_flights'];

    // Total baggage
    $stmt = $pdo->query("SELECT COUNT(*) as total_baggage FROM baggage");
    $stats['total_baggage'] = $stmt->fetch()['total_baggage'];

    // Total airlines
    $stmt = $pdo->query("SELECT COUNT(*) as total_airlines FROM airlines");
    $stats['total_airlines'] = $stmt->fetch()['total_airlines'];

    // Total airports
    $stmt = $pdo->query("SELECT COUNT(*) as total_airports FROM airports");
    $stats['total_airports'] = $stmt->fetch()['total_airports'];

    // Lost baggage
    $stmt = $pdo->query("SELECT COUNT(*) as lost_baggage FROM baggage WHERE status = 'lost'");
    $stats['lost_baggage'] = $stmt->fetch()['lost_baggage'];

    // Delivered baggage
    $stmt = $pdo->query("SELECT COUNT(*) as delivered_baggage FROM baggage WHERE status = 'claimed'");
    $stats['delivered_baggage'] = $stmt->fetch()['delivered_baggage'];

    // Pending baggage
    $stmt = $pdo->query("SELECT COUNT(*) as pending_baggage FROM baggage WHERE status IN ('checked_in', 'security_check', 'loaded_to_aircraft')");
    $stats['pending_baggage'] = $stmt->fetch()['pending_baggage'];

    // Lost baggage today
    $stmt = $pdo->query("SELECT COUNT(*) as lost_today FROM baggage WHERE status = 'lost' AND DATE(updated_at) = CURDATE()");
    $stats['lost_today'] = $stmt->fetch()['lost_today'];

    // Active users today
    $stmt = $pdo->query("SELECT COUNT(*) as active_today FROM users WHERE DATE(last_login) = CURDATE()");
    $stats['active_today'] = $stmt->fetch()['active_today'];

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

    // Recent baggage
    $stmt = $pdo->query("
        SELECT b.*, u.full_name, f.flight_number, a.airline_name
                         FROM baggage b 
                         JOIN users u ON b.passenger_id = u.id 
                         JOIN flights f ON b.flight_id = f.id 
        JOIN airlines a ON f.airline_id = a.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recent_baggage = $stmt->fetchAll();

    // Recent flights
    $stmt = $pdo->query("
        SELECT f.*, a.airline_name, a.airline_code,
               oa.airport_name as origin_airport, da.airport_name as destination_airport
                         FROM flights f
                         JOIN airlines a ON f.airline_id = a.id
        JOIN airports oa ON f.origin_airport_id = oa.id
        JOIN airports da ON f.destination_airport_id = da.id
        ORDER BY f.departure_time DESC
        LIMIT 10
    ");
    $recent_flights = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Admin Dashboard error: " . $e->getMessage());
    $stats = ['total_users' => 0, 'total_flights' => 0, 'total_baggage' => 0, 'total_airlines' => 0];
    $baggage_stats = [];
    $user_stats = [];
    $recent_baggage = [];
    $recent_flights = [];
}

$userInfo = $auth->getUserInfo();
$current_user_role = $auth->getUserRole();

// Determine allowed widgets based on role
$allowed_widgets = [
    'total_users' => in_array($current_user_role, ['system_admin']),
    'total_flights' => in_array($current_user_role, ['system_admin', 'airline_manager']),
    'total_baggage' => in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor']),
    'total_airports' => in_array($current_user_role, ['system_admin']),
    'delivered_baggage' => in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor']),
    'lost_baggage' => in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor']),
    'pending_baggage' => in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor']),
    'weekly_stats' => in_array($current_user_role, ['system_admin', 'airline_manager']),
    'monthly_chart' => in_array($current_user_role, ['system_admin', 'airline_manager']),
    'recent_baggage' => in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor']),
    'upcoming_flights' => in_array($current_user_role, ['system_admin', 'airline_manager']),
    'top_airlines' => in_array($current_user_role, ['system_admin', 'airline_manager'])
];
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Baggage Tracking System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#2c3e50',
                        secondary: '#3498db',
                        accent: '#e74c3c',
                        success: '#27ae60',
                        warning: '#f39c12',
                        light: '#ecf0f1',
                        dark: '#2c3e50',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'bounce-slow': 'bounce 2s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(180deg)' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .floating-shape {
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>

<body class="font-inter bg-gray-50">
    <div class="flex h-screen">
            <!-- Sidebar -->
        <nav class="w-64 bg-primary text-white shadow-lg">
            <div class="p-6">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-crown text-2xl text-white"></i>
                    </div>
                    <h4 class="text-white text-xl font-bold">Admin Panel</h4>
                    <small class="text-gray-300">Comprehensive Management System</small>
                    </div>
                    
                <ul class="space-y-2">
                    <!-- Dashboard - Available to all -->
                    <li>
                        <a class="flex items-center px-4 py-3 text-white bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all duration-200" href="dashboard.php">
                            <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                            Dashboard
                        </a>
                    </li>

                    <?php if ($current_user_role === 'system_admin'): ?>
                    <!-- User Management - System admin only -->
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="user_management.php">
                            <i class="fas fa-users w-5 mr-3"></i>
                            User Management
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Analytics - Available to system admin, airline manager, and supervisor -->
                    <?php if (in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor'])): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="analytics.php">
                            <i class="fas fa-chart-bar w-5 mr-3"></i>
                            Analytics
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Flight Management - Available to system admin and airline manager -->
                    <?php if (in_array($current_user_role, ['system_admin', 'airline_manager'])): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../flights/management.php">
                            <i class="fas fa-plane w-5 mr-3"></i>
                            Flight Management
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../flights/status.php">
                            <i class="fas fa-plane-departure w-5 mr-3"></i>
                            Flight Status
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Backups - System admin only -->
                    <?php if ($current_user_role === 'system_admin'): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="backup.php">
                            <i class="fas fa-database w-5 mr-3"></i>
                            Backups
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- System Settings - System admin only -->
                    <?php if ($current_user_role === 'system_admin'): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="system_settings.php">
                            <i class="fas fa-cog w-5 mr-3"></i>
                            System Settings
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Customer Reviews - Airline manager -->
                    <?php if ($current_user_role === 'airline_manager'): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../crm/customer_feedback.php">
                            <i class="fas fa-star w-5 mr-3"></i>
                            Customer Reviews
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Supervisor specific items -->
                    <?php if ($current_user_role === 'supervisor'): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../staff/dashboard.php">
                            <i class="fas fa-users-cog w-5 mr-3"></i>
                            Staff Panel
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../staff/baggage_search.php">
                            <i class="fas fa-search w-5 mr-3"></i>
                            Baggage Search
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../staff/tracking_update.php">
                            <i class="fas fa-map-marker-alt w-5 mr-3"></i>
                            Tracking Update
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../quality/inspection_reports.php">
                            <i class="fas fa-clipboard-check w-5 mr-3"></i>
                            Inspection Reports
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../support/ticket_dashboard.php">
                            <i class="fas fa-headset w-5 mr-3"></i>
                            Support Tickets
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Technical Support - For system admin and airline manager only -->
                    <?php if (in_array($current_user_role, ['system_admin', 'airline_manager'])): ?>
                    <li>
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="../support/supervisor_support.php">
                            <i class="fas fa-headset w-5 mr-3"></i>
                            Technical Support
                        </a>
                    </li>
                    <?php endif; ?>

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
                        <h1 class="text-2xl font-bold text-gray-900 mr-4">
                            Dashboard - 
                            <?php 
                            $role_titles = [
                                'system_admin' => 'System Administrator',
                                'airline_manager' => 'Airline Manager',
                                'supervisor' => 'Supervisor'
                            ];
                            echo $role_titles[$current_user_role] ?? 'Administrator';
                            ?>
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($userInfo['full_name']); ?></span>
                        <div class="relative">
                            <button class="flex items-center p-2 rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="p-6">
                <!-- Page Header -->
                <div class="gradient-bg rounded-2xl p-8 mb-8 text-white relative overflow-hidden">
                    <div class="floating-shape"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-3xl font-bold mb-2">
                                    <i class="fas fa-crown mr-3"></i>
                                    Welcome, <?php echo htmlspecialchars($userInfo['full_name']); ?>
                                </h2>
                                <p class="text-lg opacity-90">Comprehensive overview of the baggage tracking system</p>
                            </div>
                            <div class="text-right">
                                <div class="text-4xl mb-2">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <p class="text-sm opacity-75">Last updated: <?php echo date('H:i'); ?></p>
                            </div>
                            </div>
                        </div>
                    </div>
                    
                <!-- Main Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <?php if ($allowed_widgets['total_users'] ?? false): ?>
                    <!-- Total Users -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Users</p>
                                <p class="text-3xl font-bold text-primary"><?php echo number_format($stats['total_users']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $stats['active_today']; ?> active today</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['total_flights'] ?? false): ?>
                    <!-- Total Flights -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Flights</p>
                                <p class="text-3xl font-bold text-success"><?php echo number_format($stats['total_flights']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $stats['total_airlines']; ?> airlines</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-plane text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['total_baggage'] ?? false): ?>
                    <!-- Total Baggage -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Baggage</p>
                                <p class="text-3xl font-bold text-warning"><?php echo number_format($stats['total_baggage']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $stats['pending_baggage'] ?? 0; ?> pending</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-suitcase text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['total_airports'] ?? false): ?>
                    <!-- Airlines -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Airlines</p>
                                <p class="text-3xl font-bold text-accent"><?php echo number_format($stats['total_airlines']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $stats['total_airports']; ?> airports</p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-building text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>
                    
                <!-- Additional Baggage Widgets -->
                <?php if (($allowed_widgets['delivered_baggage'] ?? false) || ($allowed_widgets['lost_baggage'] ?? false) || ($allowed_widgets['pending_baggage'] ?? false)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php if ($allowed_widgets['delivered_baggage'] ?? false): ?>
                    <!-- Delivered Baggage -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Delivered Baggage</p>
                                <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['delivered_baggage']); ?></p>
                                <p class="text-xs text-gray-500">Successfully delivered</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['lost_baggage'] ?? false): ?>
                    <!-- Lost Baggage -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Lost Baggage</p>
                                <p class="text-3xl font-bold text-red-600"><?php echo number_format($stats['lost_baggage']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $stats['lost_today']; ?> lost today</p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['pending_baggage'] ?? false): ?>
                    <!-- Pending Baggage -->
                    <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending Baggage</p>
                                <p class="text-3xl font-bold text-yellow-600"><?php echo number_format($stats['pending_baggage'] ?? 0); ?></p>
                                <p class="text-xs text-gray-500">Awaiting processing</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Charts -->
                <?php if (($allowed_widgets['weekly_stats'] ?? false) || ($allowed_widgets['monthly_chart'] ?? false)): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <?php if ($allowed_widgets['weekly_stats'] ?? false): ?>
                    <!-- Baggage Status Distribution -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                            Baggage Status Distribution
                        </h3>
                        <canvas id="baggageStatusChart" width="400" height="200"></canvas>
                        </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['total_users'] ?? false): ?>
                    <!-- User Distribution -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-chart-bar mr-2 text-green-600"></i>
                            User Distribution by Role
                        </h3>
                        <canvas id="userRoleChart" width="400" height="200"></canvas>
                            </div>
                    <?php endif; ?>
                        </div>
                <?php endif; ?>

                <?php if (($allowed_widgets['recent_baggage'] ?? false) || ($allowed_widgets['upcoming_flights'] ?? false)): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <?php if ($allowed_widgets['recent_baggage'] ?? false): ?>
                    <!-- Recent Baggage -->
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-suitcase mr-2 text-blue-600"></i>
                                Recent Registered Baggage
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_baggage)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-suitcase text-gray-400 text-4xl mb-4"></i>
                                <h4 class="text-gray-500 text-lg">No baggage found</h4>
                                <p class="text-gray-400">No baggage has been registered yet</p>
                            </div>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach (array_slice($recent_baggage, 0, 5) as $baggage): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-suitcase text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($baggage['baggage_code']); ?></h4>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($baggage['full_name']); ?></p>
                                            </div>
                                        </div>
                                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo getBaggageStatusText($baggage['status']); ?>
                                                    </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-500">Flight:</span>
                                            <span class="font-medium"><?php echo htmlspecialchars($baggage['flight_number']); ?></span>
                                </div>
                                        <div>
                                            <span class="text-gray-500">Airline:</span>
                                            <span class="font-medium"><?php echo htmlspecialchars($baggage['airline_name']); ?></span>
                            </div>
                        </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($allowed_widgets['upcoming_flights'] ?? false): ?>
                    <!-- Recent Flights -->
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-plane mr-2 text-green-600"></i>
                                Recent Flights
                            </h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_flights)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-plane text-gray-400 text-4xl mb-4"></i>
                                <h4 class="text-gray-500 text-lg">No flights found</h4>
                                <p class="text-gray-400">No flights have been registered yet</p>
                            </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                <?php foreach (array_slice($recent_flights, 0, 5) as $flight): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-plane text-green-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($flight['flight_number']); ?></h4>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($flight['airline_name']); ?></p>
                                            </div>
                                        </div>
                                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo getFlightStatusText($flight['status']); ?>
                                                    </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-500">Departure:</span>
                                            <span class="font-medium"><?php echo date('H:i', strtotime($flight['departure_time'])); ?></span>
                                            </div>
                                            <div>
                                            <span class="text-gray-500">Arrival:</span>
                                            <span class="font-medium"><?php echo date('H:i', strtotime($flight['arrival_time'])); ?></span>
                                            </div>
                                        </div>
                                    
                                    <div class="mt-3 text-sm">
                                        <span class="text-gray-500">Route:</span>
                                        <span class="font-medium">
                                            <?php echo htmlspecialchars($flight['origin_airport']); ?> → 
                                            <?php echo htmlspecialchars($flight['destination_airport']); ?>
                                        </span>
                                        </div>
                                    </div>
                                            <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Most Active Airlines -->
                <?php if ($allowed_widgets['top_airlines'] ?? false): ?>
                <div class="bg-white rounded-xl shadow-lg mb-8">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                            Most Active Airlines
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php
                        // Query for most active airlines
                        $stmt = $pdo->query("
                            SELECT a.airline_name, COUNT(b.id) as baggage_count
                            FROM baggage b
                            JOIN flights f ON b.flight_id = f.id
                            JOIN airlines a ON f.airline_id = a.id
                            GROUP BY a.id, a.airline_name
                            ORDER BY baggage_count DESC
                            LIMIT 5
                        ");
                        $top_airlines = $stmt->fetchAll();
                        ?>
                        <?php if (!empty($top_airlines)): ?>
                            <div class="space-y-4">
                                <?php foreach ($top_airlines as $index => $airline): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold mr-3">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <h6 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($airline['airline_name']); ?></h6>
                                            <p class="text-xs text-gray-500"><?php echo $airline['baggage_count']; ?> bags</p>
                                        </div>
                                    </div>
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, ($airline['baggage_count'] / max(1, $top_airlines[0]['baggage_count'])) * 100); ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-plane text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">No data available</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                    <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-<?php echo ($current_user_role === 'supervisor') ? '3' : '5'; ?> gap-6">
                    <?php if ($current_user_role === 'system_admin'): ?>
                    <a href="user_management.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-6 hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">User Management</h4>
                            <p class="text-blue-100 text-sm">Account Management</p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (in_array($current_user_role, ['system_admin', 'airline_manager', 'supervisor'])): ?>
                    <a href="analytics.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-6 hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-chart-bar text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">Analytics</h4>
                            <p class="text-green-100 text-sm">Detailed Reports</p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if ($current_user_role === 'system_admin'): ?>
                    <a href="backup.php" class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-6 hover:from-purple-600 hover:to-purple-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-database text-xl"></i>
                        </div>
                            <h4 class="font-semibold text-lg">Backups</h4>
                            <p class="text-purple-100 text-sm">Data Protection</p>
                        </div>
                    </a>

                    <a href="system_settings.php" class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl p-6 hover:from-orange-600 hover:to-orange-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-cog text-xl"></i>
                    </div>
                            <h4 class="font-semibold text-lg">System Settings</h4>
                            <p class="text-orange-100 text-sm">System Configuration</p>
                </div>
                    </a>
                    <?php endif; ?>

                    <?php if ($current_user_role === 'supervisor'): ?>
                    <a href="../staff/baggage_search.php" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-6 hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-search text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">Baggage Search</h4>
                            <p class="text-blue-100 text-sm">Search & Track</p>
                        </div>
                    </a>

                    <a href="../staff/tracking_update.php" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-xl p-6 hover:from-indigo-600 hover:to-indigo-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-map-marker-alt text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">Tracking Update</h4>
                            <p class="text-indigo-100 text-sm">Update Status</p>
                        </div>
                    </a>

                    <a href="../quality/inspection_reports.php" class="bg-gradient-to-r from-teal-500 to-teal-600 text-white rounded-xl p-6 hover:from-teal-600 hover:to-teal-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-clipboard-check text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">Inspection Reports</h4>
                            <p class="text-teal-100 text-sm">Quality Monitoring</p>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (in_array($current_user_role, ['system_admin', 'airline_manager'])): ?>
                    <a href="../baggage/reports.php" class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl p-6 hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:-translate-y-1 shadow-lg">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-file-alt text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-lg">Baggage Reports</h4>
                            <p class="text-red-100 text-sm">Comprehensive Reports</p>
                    </div>
                    </a>
                    <?php endif; ?>
                    </div>
                </div>
            </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart for baggage status
        const baggageStatusCtx = document.getElementById('baggageStatusChart').getContext('2d');
        const baggageStatusData = <?php echo json_encode($baggage_stats); ?>;
        
        new Chart(baggageStatusCtx, {
            type: 'doughnut',
            data: {
                labels: baggageStatusData.map(item => item.status),
                datasets: [{
                    data: baggageStatusData.map(item => item.count),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)'
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

        // Chart for user roles
        const userRoleCtx = document.getElementById('userRoleChart').getContext('2d');
        const userRoleData = <?php echo json_encode($user_stats); ?>;
        
        new Chart(userRoleCtx, {
            type: 'bar',
            data: {
                labels: userRoleData.map(item => item.role),
                datasets: [{
                    label: 'Number of Users',
                    data: userRoleData.map(item => item.count),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Auto refresh every 5 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);

        // Interactive effects
        document.querySelectorAll('.hover\\:shadow-xl').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
