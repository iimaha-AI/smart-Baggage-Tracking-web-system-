<?php
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin', 'airline_manager', 'supervisor']);
$pdo = $db->getConnection();

// Handle management requests
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_user':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                
                // Check if user with same email or username exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                
                if ($stmt->rowCount() > 0) {
                    $message = 'Email or username already exists';
                    $message_type = 'error';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $role, $status]);
                    $message = 'User created successfully';
                    $message_type = 'success';
                }
                break;
                
            case 'update_user':
                $user_id = $_POST['user_id'];
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$full_name, $role, $status, $user_id]);
                $message = 'User updated successfully';
                $message_type = 'success';
                break;
                
            case 'delete_user':
                $user_id = $_POST['user_id'];
                // Cannot delete current user
                if ($user_id != $_SESSION['user_id']) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $message = 'User deleted successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Cannot delete your own account';
                    $message_type = 'error';
                }
                break;
        }
    } catch (PDOException $e) {
        $message = 'System error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all users with statistics
try {
    $stmt = $pdo->query("
        SELECT u.*, a.airline_name,
               (SELECT COUNT(*) FROM baggage WHERE passenger_id = u.id) as baggage_count,
               (SELECT COUNT(*) FROM baggage WHERE passenger_id = u.id AND status = 'claimed') as delivered_baggage
        FROM users u 
        LEFT JOIN airlines a ON u.airline_id = a.id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();

    // User statistics
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
    $user_stats = $stmt->fetchAll();

    // Active users today
    $stmt = $pdo->query("SELECT COUNT(*) as active_today FROM users WHERE DATE(last_login) = CURDATE()");
    $active_today = $stmt->fetch()['active_today'];

    // New users this month
    $stmt = $pdo->query("SELECT COUNT(*) as new_this_month FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $new_this_month = $stmt->fetch()['new_this_month'];

} catch (PDOException $e) {
    $users = [];
    $user_stats = [];
    $active_today = 0;
    $new_this_month = 0;
    error_log("User management error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Smart Baggage Tracking System</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
                        <a class="flex items-center px-4 py-3 text-gray-300 hover:text-white hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" href="analytics.php">
                            <i class="fas fa-chart-bar w-5 mr-3"></i>
                            Analytics
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center px-4 py-3 text-white bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all duration-200" href="user_management.php">
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
                        <h1 class="text-2xl font-bold text-gray-900 mr-4">User Management</h1>
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

                <?php if ($message): ?>
                <div class="p-6">
                    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-500 mr-3"></i>
                            <span class="text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 font-medium"><?php echo htmlspecialchars($message); ?></span>
                            <button type="button" class="mr-auto text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-500 hover:text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700" onclick="this.parentElement.parentElement.style.display='none'">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i class="fas fa-users text-2xl"></i>
                                </div>
                                <div class="mr-4">
                                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count($users); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $new_this_month; ?> new this month</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-user-check text-2xl"></i>
                                </div>
                                <div class="mr-4">
                                    <p class="text-sm font-medium text-gray-600">Active Today</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo $active_today; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-user-shield text-2xl"></i>
                                </div>
                                <div class="mr-4">
                                    <p class="text-sm font-medium text-gray-600">System Admins</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($users, function($u) { return $u['role'] === 'system_admin'; })); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-cyan-500 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-cyan-100 text-cyan-600">
                                    <i class="fas fa-user-tie text-2xl"></i>
                                </div>
                                <div class="mr-4">
                                    <p class="text-sm font-medium text-gray-600">Staff</p>
                                    <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($users, function($u) { return in_array($u['role'], ['counter_staff', 'handling_staff']); })); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add New User -->
                <div class="p-6">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h5 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-user-plus mr-2 text-blue-600"></i>Add New User
                        </h5>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="create_user">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="username" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="email" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                                <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="password" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="full_name" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="role" required>
                                        <option value="passenger">Passenger</option>
                                        <option value="counter_staff">Counter Staff</option>
                                        <option value="handling_staff">Handling Staff</option>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="airline_manager">Airline Manager</option>
                                        <option value="system_admin">System Admin</option>
                                    </select>
                                </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                            </div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200 flex items-center">
                            <i class="fas fa-save mr-2"></i>Create User
                        </button>
                    </form>
                    </div>
                </div>

                <!-- User List -->
                <div class="p-6">
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-users mr-2 text-blue-600"></i>User List
                    </h5>
                        </div>
                        <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Baggage</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-8">
                                            <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                                            <h5 class="text-gray-500">No registered users</h5>
                                            <p class="text-gray-400">Start by adding a new user using the form above</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold mr-3">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <div class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo getUserRoleText($user['role']); ?>
                                            </span>
                                    </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : ($user['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo $user['status'] === 'active' ? 'Active' : ($user['status'] === 'pending' ? 'Pending' : 'Inactive'); ?>
                                        </span>
                                    </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 mr-2">
                                                    <?php echo $user['baggage_count']; ?>
                                                </span>
                                                <span class="text-xs text-gray-500"><?php echo $user['delivered_baggage']; ?> delivered</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($user['last_login']): ?>
                                                <div><?php echo date('Y-m-d', strtotime($user['last_login'])); ?></div>
                                                <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($user['last_login'])); ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400">Never logged in</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="openEditModal(<?php echo $user['id']; ?>)" class="text-blue-600 hover:text-blue-900 p-2 rounded-md hover:bg-blue-50 transition-colors duration-200" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 p-2 rounded-md hover:bg-red-50 transition-colors duration-200" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit User Modal -->
                                <div id="editUserModal<?php echo $user['id']; ?>" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
                                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                        <div class="mt-3">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
                                                <button onclick="closeEditModal(<?php echo $user['id']; ?>)" class="text-gray-400 hover:text-gray-600">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="action" value="update_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                                    </div>
                                                
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="role" required>
                                                            <?php foreach (['passenger', 'counter_staff', 'handling_staff', 'supervisor', 'airline_manager', 'system_admin'] as $role): ?>
                                                            <option value="<?php echo $role; ?>" <?php echo $user['role'] === $role ? 'selected' : ''; ?>>
                                                                <?php echo getUserRoleText($role); ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" name="status" required>
                                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                            <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        </select>
                                                </div>
                                                
                                                <div class="flex items-center justify-end space-x-3 pt-4">
                                                    <button type="button" onclick="closeEditModal(<?php echo $user['id']; ?>)" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors duration-200">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors duration-200">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal(userId) {
            document.getElementById('editUserModal' + userId).classList.remove('hidden');
        }

        function closeEditModal(userId) {
            document.getElementById('editUserModal' + userId).classList.add('hidden');
        }

        // Close Modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('bg-gray-600')) {
                event.target.classList.add('hidden');
            }
        });

        // Interactive effects for cards
        document.querySelectorAll('.hover\\:-translate-y-1').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-hide alert messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]');
            alerts.forEach(alert => {
                if (alert.style.display !== 'none') {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// Function to convert user roles to text
function getUserRoleText($role) {
    $roles = [
        'passenger' => 'Passenger',
        'counter_staff' => 'Counter Staff',
        'handling_staff' => 'Handling Staff',
        'supervisor' => 'Supervisor',
        'airline_manager' => 'Airline Manager',
        'system_admin' => 'System Admin'
    ];
    return $roles[$role] ?? $role;
}
?>