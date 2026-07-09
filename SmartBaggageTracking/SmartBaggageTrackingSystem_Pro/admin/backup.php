<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin']);
$pdo = $db->getConnection();

// Handle backup requests
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_backup':
                $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backup_path = '../backups/' . $backup_file;
                
                // Create backup directory if it doesn't exist
                if (!is_dir('../backups')) {
                    mkdir('../backups', 0755, true);
                }
                
                // Execute backup
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                $backup_content = "-- Smart Baggage System Backup\n";
                $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    // Table structure
                    $backup_content .= "--\n-- Table structure for table `$table`\n--\n";
                    $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                    $backup_content .= $create_table['Create Table'] . ";\n\n";
                    
                    // Table data
                    $backup_content .= "--\n-- Dumping data for table `$table`\n--\n";
                    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rows) > 0) {
                        $columns = array_keys($rows[0]);
                        $backup_content .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        
                        $values = [];
                        foreach ($rows as $row) {
                            $row_values = array_map(function($value) use ($pdo) {
                                return $value === null ? 'NULL' : $pdo->quote($value);
                            }, $row);
                            $values[] = "(" . implode(', ', $row_values) . ")";
                        }
                        
                        $backup_content .= implode(",\n", $values) . ";\n\n";
                    }
                }
                
                if (file_put_contents($backup_path, $backup_content)) {
                    $message = 'Backup created successfully: ' . $backup_file;
                    $message_type = 'success';
                } else {
                    $message = 'Failed to create backup';
                    $message_type = 'error';
                }
                break;
                
            case 'restore_backup':
                $backup_file = $_POST['backup_file'];
                $backup_path = '../backups/' . $backup_file;
                
                if (file_exists($backup_path)) {
                    $sql = file_get_contents($backup_path);
                    $pdo->exec($sql);
                    $message = 'Backup restored successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Backup file not found';
                    $message_type = 'error';
                }
                break;
                
            case 'delete_backup':
                $backup_file = $_POST['backup_file'];
                $backup_path = '../backups/' . $backup_file;
                
                if (file_exists($backup_path) && unlink($backup_path)) {
                    $message = 'Backup deleted successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete backup';
                    $message_type = 'error';
                }
                break;
        }
    } catch (PDOException $e) {
        $message = 'An error occurred: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get backup list
$backups = [];
if (is_dir('../backups')) {
    $files = scandir('../backups', SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = '../backups/' . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path)
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management - Smart Baggage Tracking System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'primary': '#1e40af',
                        'secondary': '#64748b',
                        'accent': '#3b82f6',
                        'success': '#10b981',
                        'warning': '#f59e0b',
                        'danger': '#ef4444',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                        'bounce-slow': 'bounce 2s infinite',
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            height: 8px;
            transition: width 0.3s ease;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="font-inter bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <!-- Animated Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <div class="relative z-10 flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl min-h-screen">
            <div class="p-6">
                <!-- System Logo -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-2xl mb-4 floating">
                        <i class="fas fa-database text-white text-2xl"></i>
                    </div>
                    <h3 class="text-white text-xl font-bold">Tracking System</h3>
                    <p class="text-slate-400 text-sm">Admin Dashboard</p>
                </div>
                
                <!-- Navigation Menu -->
                <nav class="space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-tachometer-alt w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="analytics.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-chart-bar w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="user_management.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-users w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>User Management</span>
                    </a>
                    <a href="../flights/management.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-plane w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Flight Management</span>
                    </a>
                    <a href="../baggage/reports.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-suitcase w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Baggage Reports</span>
                    </a>
                    <a href="system_settings.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white rounded-xl transition-all duration-200 group">
                        <i class="fas fa-cog w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>System Settings</span>
                    </a>
                    <a href="backup.php" class="flex items-center px-4 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl shadow-lg">
                        <i class="fas fa-database w-5 h-5 mr-3"></i>
                        <span>Backup</span>
                    </a>
                </nav>
                
                <!-- Logout -->
                <div class="mt-8 pt-6 border-t border-slate-700">
                    <a href="../includes/logout.php" class="flex items-center px-4 py-3 text-red-400 hover:bg-red-900/20 hover:text-red-300 rounded-xl transition-all duration-200 group">
                        <i class="fas fa-sign-out-alt w-5 h-5 mr-3 group-hover:scale-110 transition-transform"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Top Navigation Bar -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg p-6 mb-8 animate-fade-in">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-database text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800">Backup Management</h1>
                            <p class="text-slate-600">Database backup management</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <p class="text-slate-600 text-sm">Welcome,</p>
                            <p class="font-semibold text-slate-800"><?php echo $_SESSION['user_name']; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="mb-6 animate-slide-up">
                <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 rounded-xl p-4 flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-500 text-xl"></i>
                    </div>
                    <div class="mr-3 flex-1">
                        <p class="text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-800 font-medium"><?php echo $message; ?></p>
                    </div>
                    <button type="button" class="text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 hover:text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-600" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Create Backup Card -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-8 card-hover animate-fade-in">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-database text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">Create Backup</h2>
                        <p class="text-slate-600">Create a complete backup of the database</p>
                    </div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-blue-800 font-semibold mb-2">Backup Information</h3>
                            <ul class="text-blue-700 space-y-1 text-sm">
                                <li>• A complete backup of all tables will be created</li>
                                <li>• All data and structures will be included</li>
                                <li>• File will be saved in SQL format</li>
                                <li>• Data can be restored at any time</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="flex items-center space-x-4">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-8 py-3 rounded-xl font-semibold transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Create New Backup
                    </button>
                    <div class="text-slate-500 text-sm">
                        <i class="fas fa-clock mr-1"></i>
                        This may take a few minutes
                    </div>
                </form>
            </div>

            <!-- Previous Backups List -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-8 card-hover animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-teal-600 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-history text-white text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-800">Previous Backups</h2>
                            <p class="text-slate-600">Manage saved backups</p>
                        </div>
                    </div>
                    <div class="bg-slate-100 rounded-lg px-4 py-2">
                        <span class="text-slate-600 text-sm">Total Backups: </span>
                        <span class="font-bold text-slate-800"><?php echo count($backups); ?></span>
                    </div>
                </div>
                
                <?php if (empty($backups)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-slate-400 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-600 mb-2">No Backups Found</h3>
                    <p class="text-slate-500">No backups have been created yet. Create the first backup now.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($backups as $index => $backup): ?>
                    <div class="bg-slate-50 rounded-xl p-6 border border-slate-200 hover:border-blue-300 transition-all duration-200 hover:shadow-md">
                        <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-800 text-lg"><?php echo htmlspecialchars($backup['name']); ?></h3>
                                <div class="flex items-center space-x-4 text-sm text-slate-600">
                                    <span class="flex items-center">
                                        <i class="fas fa-weight-hanging mr-1"></i>
                                        <?php echo formatSize($backup['size']); ?>
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('Y-m-d H:i:s', $backup['modified']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                            <div class="flex items-center space-x-2">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this backup? All current data will be replaced.');">
                                    <input type="hidden" name="action" value="restore_backup">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center" title="Restore">
                                        <i class="fas fa-undo mr-2"></i>
                                        Restore
                                    </button>
                                </form>
                                <a href="../backups/<?php echo htmlspecialchars($backup['name']); ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center" 
                                   download 
                                   title="Download">
                                    <i class="fas fa-download mr-2"></i>
                                    Download
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center" title="Delete">
                                        <i class="fas fa-trash mr-2"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Database Statistics -->
            <div class="bg-white rounded-2xl shadow-lg p-8 card-hover animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-chart-bar text-white text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-800">Database Statistics</h2>
                            <p class="text-slate-600">Overview of database tables</p>
                        </div>
                    </div>
                </div>
                
                <?php
                try {
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    $tableData = [];
                    $totalRecords = 0;
                    $totalSize = 0;
                    
                    foreach ($tables as $table) {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                        $count = $stmt->fetch()['count'];
                        $stmt = $pdo->query("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size FROM information_schema.TABLES WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = '" . DB_NAME . "'");
                        $size = $stmt->fetch()['size'];
                        
                        $tableData[] = [
                            'name' => $table,
                            'count' => $count,
                            'size' => $size
                        ];
                        
                        $totalRecords += $count;
                        $totalSize += $size;
                    }
                ?>
                
                <!-- General Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm">Total Tables</p>
                                <p class="text-3xl font-bold"><?php echo count($tables); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-table text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Total Records</p>
                                <p class="text-3xl font-bold"><?php echo number_format($totalRecords); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-list text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-sm">Total Size</p>
                                <p class="text-3xl font-bold"><?php echo number_format($totalSize, 2); ?> MB</p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-hdd text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Table Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($tableData as $table): ?>
                    <div class="bg-slate-50 rounded-xl p-6 border border-slate-200 hover:border-blue-300 transition-all duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-table text-white"></i>
                            </div>
                            <div class="text-left">
                                <h3 class="font-semibold text-slate-800"><?php echo $table['name']; ?></h3>
                                <p class="text-slate-600 text-sm">Database Table</p>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 text-sm">Records:</span>
                                <span class="font-semibold text-slate-800"><?php echo number_format($table['count']); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 text-sm">Size:</span>
                                <span class="font-semibold text-slate-800"><?php echo $table['size']; ?> MB</span>
                            </div>
                            
                            <!-- Size Progress Bar -->
                            <div class="w-full bg-slate-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full" 
                                     style="width: <?php echo $table['size'] > 0 ? min(($table['size'] / max($totalSize, 1)) * 100, 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php
                } catch (PDOException $e) {
                    echo '<div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i>
                            <h3 class="text-red-800 font-semibold mb-2">Error Loading Statistics</h3>
                            <p class="text-red-600">An error occurred while loading database statistics</p>
                          </div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- JavaScript for Interactions -->
    <script>
        // Card animation effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card-hover');
            
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Scroll effect
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in');
                    }
                });
            });
            
            cards.forEach(card => {
                observer.observe(card);
            });
        });
        
        // Confirm sensitive operations
        function confirmAction(message) {
            return confirm(message);
        }
        
        // Button effects
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.textContent.includes('Create')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
                    this.disabled = true;
                }
            });
        });
    </script>
</body>
</html>

<?php
// Function to format file size
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>