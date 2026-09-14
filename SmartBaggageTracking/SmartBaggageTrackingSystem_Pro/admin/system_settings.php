<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security/role_guard.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin']);
$pdo = $db->getConnection();

// Get system settings
$settings = [];
try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM system_settings");
        $settings_data = $stmt->fetchAll();
        
        // Convert to key array
        foreach ($settings_data as $setting) {
            $settings[$setting['setting_key']] = $setting;
        }
    } else {
        // Create table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NOT NULL,
                setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
                description TEXT,
                category VARCHAR(50) DEFAULT 'general',
                is_public BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_key (setting_key)
            )
        ";
        $pdo->exec($create_table_sql);
        
        // Insert default settings
        $default_settings = [
            ['system_name', 'Smart Baggage Tracking System', 'string', 'System display name', 'general'],
            ['system_version', '2.0.0', 'string', 'System version', 'general'],
            ['maintenance_mode', 'false', 'boolean', 'Maintenance mode', 'general'],
            ['auto_backup', 'true', 'boolean', 'Automatic backup', 'backup'],
            ['backup_frequency', 'daily', 'string', 'Backup frequency', 'backup'],
            ['smtp_host', 'smtp.gmail.com', 'string', 'Mail server', 'email'],
            ['smtp_port', '587', 'integer', 'Mail port', 'email'],
            ['max_baggage_weight', '32', 'integer', 'Maximum baggage weight', 'baggage'],
            ['tracking_update_interval', '5', 'integer', 'Tracking update interval (minutes)', 'tracking']
        ];
        
        $insert_stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES (?, ?, ?, ?, ?)");
        foreach ($default_settings as $setting) {
            $insert_stmt->execute($setting);
        }
        
        // Reload settings
        $stmt = $pdo->query("SELECT * FROM system_settings");
        $settings_data = $stmt->fetchAll();
        foreach ($settings_data as $setting) {
            $settings[$setting['setting_key']] = $setting;
        }
    }
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
}

// Handle settings update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                // Validate data
                $value = trim($value);
                if (!empty($value)) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                }
            }
            $message = 'Settings saved successfully';
            $message_type = 'success';
            
            // Reload settings
            $stmt = $pdo->query("SELECT * FROM system_settings");
            $settings_data = $stmt->fetchAll();
            $settings = [];
            foreach ($settings_data as $setting) {
                $settings[$setting['setting_key']] = $setting;
            }
        } else {
            $message = 'No valid data was sent';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        error_log("System settings update error: " . $e->getMessage());
        $message = 'An error occurred while saving settings: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Smart Baggage Tracking System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-primary: linear-gradient(135deg, #6366f1, #8b5cf6);
            --gradient-success: linear-gradient(135deg, #10b981, #059669);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #d97706);
            --gradient-danger: linear-gradient(135deg, #ef4444, #dc2626);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gradient);
            min-height: 100vh;
            color: var(--dark);
            overflow-x: hidden;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .main-container {
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--dark);
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            left: 0;
            top: 0;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h3 {
            font-weight: 800;
            margin-bottom: 5px;
            background: linear-gradient(90deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-header p {
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .nav-item {
            margin: 5px 15px;
        }

        .nav-link {
            color: #cbd5e1;
            padding: 14px 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        .nav-link:hover {
            color: white;
            background: rgba(99, 102, 241, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .nav-link .badge {
            margin-left: auto;
            font-size: 0.7rem;
            padding: 4px 8px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        /* Top Navigation Bar */
        .top-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-weight: 800;
            font-size: 1.8rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Settings Card */
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
        }

        .card-header-custom {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(99, 102, 241, 0.2);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
        }

        .card-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .card-title {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--dark);
            margin: 0;
        }

        .card-subtitle {
            color: var(--gray);
            font-size: 1rem;
            margin-top: 5px;
        }

        /* Settings Sections */
        .settings-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .settings-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .settings-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.3rem;
        }

        .icon-general { background: var(--gradient-primary); }
        .icon-baggage { background: var(--gradient-success); }
        .icon-email { background: var(--gradient-warning); }
        .icon-backup { background: var(--gradient-danger); }

        .section-title {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--dark);
            margin: 0;
        }

        .section-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* Input Fields */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 8px;
            color: var(--primary);
        }

        .form-control, .form-select {
            border: 2px solid var(--gray-light);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.5);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-light);
            color: var(--gray);
            border-radius: 12px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-3px);
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        /* Animation Effects */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out;
        }

        .animate-slideInRight {
            animation: slideInRight 0.6s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .settings-card {
                padding: 20px;
            }
            
            .settings-section {
                padding: 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .section-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }

        /* Mobile Menu Button */
        .menu-toggle {
            display: none;
            background: var(--gradient-primary);
            border: none;
            border-radius: 10px;
            width: 45px;
            height: 45px;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);
        }

        /* Page Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Additional Effects */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }

        .floating {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-plane-departure me-2"></i>Tracking System</h3>
                <p>Admin Dashboard</p>
            </div>
            
            <ul class="nav flex-column mt-4">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-bar"></i>
                        Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="user_management.php">
                        <i class="fas fa-users"></i>
                        User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../flights/management.php">
                        <i class="fas fa-plane"></i>
                        Flight Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../baggage/reports.php">
                        <i class="fas fa-suitcase"></i>
                        Baggage Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="system_settings.php">
                        <i class="fas fa-cog"></i>
                        System Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="backup.php">
                        <i class="fas fa-database"></i>
                        Backup
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-warning" href="../includes/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
            </ul>
            
            <div class="footer">
                <p>Version 2.1.0</p>
                <p>All rights reserved &copy; 2023</p>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation Bar -->
            <nav class="top-navbar">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle me-3">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">System Settings</h1>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../pages/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../includes/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> animate-fadeIn">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="settings-card animate-fadeIn">
                <div class="card-header-custom">
                    <div class="card-icon floating">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div>
                        <h1 class="card-title">Advanced System Settings</h1>
                        <p class="card-subtitle">Customize system settings according to your needs</p>
                    </div>
                </div>

                <form method="POST">
                    <!-- General Settings -->
                    <div class="settings-section animate-slideInRight">
                        <div class="section-header">
                            <div class="section-icon icon-general">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div>
                                <h3 class="section-title">General Settings</h3>
                                <p class="section-description">Basic settings for system operation</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-tag"></i>System Name
                                    </label>
                                    <input type="text" class="form-control" name="settings[system_name]" 
                                           value="<?php echo htmlspecialchars($settings['system_name']['setting_value'] ?? ''); ?>"
                                           placeholder="Enter system name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-code-branch"></i>System Version
                                    </label>
                                    <input type="text" class="form-control" name="settings[system_version]" 
                                           value="<?php echo htmlspecialchars($settings['system_version']['setting_value'] ?? ''); ?>"
                                           placeholder="e.g. 1.0.0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Baggage Settings -->
                    <div class="settings-section animate-slideInRight" style="animation-delay: 0.1s">
                        <div class="section-header">
                            <div class="section-icon icon-baggage">
                                <i class="fas fa-suitcase"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Baggage Settings</h3>
                                <p class="section-description">Control baggage tracking settings</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-weight"></i>Maximum Baggage Weight (kg)
                                    </label>
                                    <input type="number" class="form-control" name="settings[max_baggage_weight]" 
                                           value="<?php echo htmlspecialchars($settings['max_baggage_weight']['setting_value'] ?? '32'); ?>"
                                           min="1" max="100" placeholder="32">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-clock"></i>Tracking Update Interval (minutes)
                                    </label>
                                    <input type="number" class="form-control" name="settings[tracking_update_interval]" 
                                           value="<?php echo htmlspecialchars($settings['tracking_update_interval']['setting_value'] ?? '5'); ?>"
                                           min="1" max="60" placeholder="5">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="settings-section animate-slideInRight" style="animation-delay: 0.2s">
                        <div class="section-header">
                            <div class="section-icon icon-email">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Email Settings</h3>
                                <p class="section-description">Configure email messaging settings</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-server"></i>SMTP Server
                                    </label>
                                    <input type="text" class="form-control" name="settings[smtp_host]" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host']['setting_value'] ?? ''); ?>"
                                           placeholder="smtp.gmail.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-plug"></i>SMTP Port
                                    </label>
                                    <input type="number" class="form-control" name="settings[smtp_port]" 
                                           value="<?php echo htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587'); ?>"
                                           min="1" max="65535" placeholder="587">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup Settings -->
                    <div class="settings-section animate-slideInRight" style="animation-delay: 0.3s">
                        <div class="section-header">
                            <div class="section-icon icon-backup">
                                <i class="fas fa-database"></i>
                            </div>
                            <div>
                                <h3 class="section-title">Backup Settings</h3>
                                <p class="section-description">Automatic backup settings</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-toggle-on"></i>Automatic Backup
                                    </label>
                                    <select class="form-select" name="settings[auto_backup]">
                                        <option value="true" <?php echo ($settings['auto_backup']['setting_value'] ?? '') === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="false" <?php echo ($settings['auto_backup']['setting_value'] ?? '') === 'false' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-alt"></i>Backup Frequency
                                    </label>
                                    <select class="form-select" name="settings[backup_frequency]">
                                        <option value="daily" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo ($settings['backup_frequency']['setting_value'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg me-3 pulse">
                            <i class="fas fa-save me-2"></i>Save Settings
                        </button>
                        <button type="button" class="btn btn-outline btn-lg" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset all settings?')) {
                document.querySelector('form').reset();
            }
        }

        // Improve user experience
        document.addEventListener('DOMContentLoaded', function() {
            // Add interactive effects to fields
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });

            // Scroll effects
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Monitor elements to add appearance effects
            document.querySelectorAll('.settings-section').forEach(section => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(section);
            });
        });

        // Data validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const maxWeight = document.querySelector('input[name="settings[max_baggage_weight]"]');
            const updateInterval = document.querySelector('input[name="settings[tracking_update_interval]"]');
            const smtpPort = document.querySelector('input[name="settings[smtp_port]"]');
            const systemName = document.querySelector('input[name="settings[system_name]"]');
            
            let isValid = true;
            let errorMessage = '';
            
            // Validate system name
            if (systemName && systemName.value.trim().length < 3) {
                errorMessage += 'System name must be at least 3 characters\n';
                isValid = false;
            }
            
            // Validate baggage weight
            if (maxWeight && maxWeight.value && (maxWeight.value < 1 || maxWeight.value > 100)) {
                errorMessage += 'Maximum baggage weight must be between 1 and 100 kg\n';
                isValid = false;
            }
            
            // Validate update interval
            if (updateInterval && updateInterval.value && (updateInterval.value < 1 || updateInterval.value > 60)) {
                errorMessage += 'Tracking update interval must be between 1 and 60 minutes\n';
                isValid = false;
            }
            
            // Validate SMTP port
            if (smtpPort && smtpPort.value && (smtpPort.value < 1 || smtpPort.value > 65535)) {
                errorMessage += 'SMTP port must be between 1 and 65535\n';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please correct the following errors:\n' + errorMessage);
            }
        });
    </script>
</body>
</html>