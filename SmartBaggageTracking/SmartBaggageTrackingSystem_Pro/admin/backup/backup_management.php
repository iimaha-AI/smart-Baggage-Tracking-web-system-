<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check admin permissions
$db = new Database();
$auth = new Auth($db);
$auth->requireRole(['system_admin']);
$pdo = $db->getConnection();

$message = '';
$messageType = '';
$backupSettings = [];
$scheduledBackups = [];

// Get backup settings
try {
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE 'backup_%'");
    $settings = $stmt->fetchAll();
    foreach ($settings as $setting) {
        $backupSettings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    $backupSettings = [
        'backup_auto_enabled' => 'false',
        'backup_frequency' => 'daily',
        'backup_retention_days' => '30',
        'backup_cloud_enabled' => 'false',
        'backup_cloud_provider' => 'aws',
        'backup_encryption' => 'true'
    ];
}

// جلب النسخ الاحتياطية المجدولة
try {
    $stmt = $pdo->query("
        SELECT * FROM reports 
        WHERE report_type = 'backup' 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $scheduledBackups = $stmt->fetchAll();
} catch (Exception $e) {
    $scheduledBackups = [];
}

// معالجة طلبات النسخ الاحتياطي
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_manual_backup':
                $backupResult = createBackup($pdo, 'manual');
                if ($backupResult['success']) {
                    $message = 'تم إنشاء النسخة الاحتياطية بنجاح: ' . $backupResult['filename'];
                    $messageType = 'success';
                } else {
                    $message = 'فشل في إنشاء النسخة الاحتياطية: ' . $backupResult['error'];
                    $messageType = 'error';
                }
                break;
                
            case 'update_backup_settings':
                $settings = [
                    'backup_auto_enabled' => $_POST['auto_enabled'] ?? 'false',
                    'backup_frequency' => $_POST['frequency'] ?? 'daily',
                    'backup_retention_days' => $_POST['retention_days'] ?? '30',
                    'backup_cloud_enabled' => $_POST['cloud_enabled'] ?? 'false',
                    'backup_cloud_provider' => $_POST['cloud_provider'] ?? 'aws',
                    'backup_encryption' => $_POST['encryption'] ?? 'true'
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) 
                        VALUES (?, ?, 'string', 'Backup setting', 'backup')
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $message = 'تم تحديث إعدادات النسخ الاحتياطي بنجاح';
                $messageType = 'success';
                break;
                
            case 'schedule_backup':
                $scheduleTime = $_POST['schedule_time'];
                $frequency = $_POST['schedule_frequency'];
                
                // إضافة مهمة مجدولة
                $stmt = $pdo->prepare("
                    INSERT INTO reports (report_type, report_name, generated_by, parameters, status) 
                    VALUES ('backup', 'Scheduled Backup', ?, ?, 'scheduled')
                ");
                $stmt->execute([
                    $auth->getUserId(),
                    json_encode(['schedule_time' => $scheduleTime, 'frequency' => $frequency])
                ]);
                
                $message = 'تم جدولة النسخة الاحتياطية بنجاح';
                $messageType = 'success';
                break;
                
            case 'cloud_sync':
                $backupId = $_POST['backup_id'];
                $syncResult = syncToCloud($backupId);
                if ($syncResult['success']) {
                    $message = 'تم مزامنة النسخة الاحتياطية مع السحابة بنجاح';
                    $messageType = 'success';
                } else {
                    $message = 'فشل في المزامنة: ' . $syncResult['error'];
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'خطأ: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Function to create backup
function createBackup($pdo, $type = 'manual') {
    try {
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '_' . $type . '.sql';
        $backupPath = '../../backups/' . $backupFile;
        
        // إنشاء مجلد النسخ الاحتياطي
        if (!is_dir('../../backups')) {
            mkdir('../../backups', 0755, true);
        }
        
        // تنفيذ النسخ الاحتياطي
        $command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $backupPath;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupPath)) {
            // تسجيل النسخة الاحتياطية في قاعدة البيانات
            $stmt = $pdo->prepare("
                INSERT INTO reports (report_type, report_name, generated_by, file_path, file_size, status) 
                VALUES ('backup', ?, ?, ?, ?, 'completed')
            ");
            $stmt->execute([
                'Backup - ' . date('Y-m-d H:i:s'),
                $_SESSION['user_id'] ?? 1,
                $backupPath,
                filesize($backupPath)
            ]);
            
            return ['success' => true, 'filename' => $backupFile, 'path' => $backupPath];
        } else {
            return ['success' => false, 'error' => 'فشل في تنفيذ أمر النسخ الاحتياطي'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// دالة المزامنة مع السحابة
function syncToCloud($backupId) {
    try {
        // محاكاة المزامنة مع السحابة
        // في التطبيق الحقيقي، هنا سيتم رفع الملف إلى AWS S3 أو Google Cloud
        $cloudProviders = [
            'aws' => 'Amazon S3',
            'google' => 'Google Cloud Storage',
            'azure' => 'Azure Blob Storage'
        ];
        
        // تسجيل عملية المزامنة
        $syncLog = [
            'backup_id' => $backupId,
            'cloud_provider' => 'aws',
            'sync_time' => date('Y-m-d H:i:s'),
            'status' => 'completed'
        ];
        
        return ['success' => true, 'log' => $syncLog];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management - Smart Baggage Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../includes/assets/css/style.css" rel="stylesheet">
    <style>
        .backup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }
        .backup-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .backup-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .btn-backup {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-backup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .cloud-sync {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .schedule-card {
            background: linear-gradient(45deg, #ffc107, #ff8c00);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
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
                <a class="nav-link active" href="backup_management.php">
                    <i class="fas fa-database me-1"></i>
                    النسخ الاحتياطية
                </a>
                <a class="nav-link" href="../analytics/analytics.php">
                    <i class="fas fa-chart-bar me-1"></i>
                    التحليلات
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
        <div class="backup-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="fas fa-database me-2"></i>
                        إدارة النسخ الاحتياطية المتقدمة
                    </h2>
                    <p class="mb-0">إدارة النسخ الاحتياطية التلقائية والمزامنة مع السحابة</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light btn-lg" onclick="createBackup()">
                        <i class="fas fa-plus me-2"></i>
                        نسخة احتياطية فورية
                    </button>
                </div>
            </div>
        </div>

        <!-- رسائل النظام -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- إعدادات النسخ الاحتياطي -->
            <div class="col-lg-6">
                <div class="backup-card">
                    <h5 class="mb-3">
                        <i class="fas fa-cog me-2"></i>
                        إعدادات النسخ الاحتياطي
                    </h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_backup_settings">
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_enabled" name="auto_enabled" 
                                       <?php echo $backupSettings['backup_auto_enabled'] === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_enabled">
                                    تفعيل النسخ الاحتياطي التلقائي
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="frequency" class="form-label">تكرار النسخ الاحتياطية</label>
                            <select class="form-select" id="frequency" name="frequency">
                                <option value="hourly" <?php echo $backupSettings['backup_frequency'] === 'hourly' ? 'selected' : ''; ?>>كل ساعة</option>
                                <option value="daily" <?php echo $backupSettings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>يومياً</option>
                                <option value="weekly" <?php echo $backupSettings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>أسبوعياً</option>
                                <option value="monthly" <?php echo $backupSettings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>شهرياً</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="retention_days" class="form-label">فترة الاحتفاظ (أيام)</label>
                            <input type="number" class="form-control" id="retention_days" name="retention_days" 
                                   value="<?php echo $backupSettings['backup_retention_days']; ?>" min="1" max="365">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="cloud_enabled" name="cloud_enabled" 
                                       <?php echo $backupSettings['backup_cloud_enabled'] === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cloud_enabled">
                                    تفعيل المزامنة مع السحابة
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cloud_provider" class="form-label">مزود الخدمة السحابية</label>
                            <select class="form-select" id="cloud_provider" name="cloud_provider">
                                <option value="aws" <?php echo $backupSettings['backup_cloud_provider'] === 'aws' ? 'selected' : ''; ?>>Amazon S3</option>
                                <option value="google" <?php echo $backupSettings['backup_cloud_provider'] === 'google' ? 'selected' : ''; ?>>Google Cloud</option>
                                <option value="azure" <?php echo $backupSettings['backup_cloud_provider'] === 'azure' ? 'selected' : ''; ?>>Azure Blob</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="encryption" name="encryption" 
                                       <?php echo $backupSettings['backup_encryption'] === 'true' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="encryption">
                                    تشفير النسخ الاحتياطية
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-backup text-white w-100">
                            <i class="fas fa-save me-2"></i>
                            حفظ الإعدادات
                        </button>
                    </form>
                </div>
            </div>

            <!-- جدولة النسخ الاحتياطية -->
            <div class="col-lg-6">
                <div class="schedule-card">
                    <h5 class="mb-3">
                        <i class="fas fa-clock me-2"></i>
                        جدولة نسخة احتياطية جديدة
                    </h5>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="schedule_backup">
                        
                        <div class="mb-3">
                            <label for="schedule_time" class="form-label">وقت التنفيذ</label>
                            <input type="datetime-local" class="form-control" id="schedule_time" name="schedule_time" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="schedule_frequency" class="form-label">التكرار</label>
                            <select class="form-select" id="schedule_frequency" name="schedule_frequency" required>
                                <option value="once">مرة واحدة</option>
                                <option value="daily">يومياً</option>
                                <option value="weekly">أسبوعياً</option>
                                <option value="monthly">شهرياً</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-light w-100">
                            <i class="fas fa-calendar-plus me-2"></i>
                            جدولة النسخة الاحتياطية
                        </button>
                    </form>
                </div>

                <!-- المزامنة مع السحابة -->
                <div class="cloud-sync">
                    <h5 class="mb-3">
                        <i class="fas fa-cloud me-2"></i>
                        المزامنة مع السحابة
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1">
                                <i class="fas fa-check-circle me-2"></i>
                                النسخ الاحتياطية المحلية: <?php echo count($scheduledBackups); ?>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-cloud me-2"></i>
                                المزامنة مع السحابة: <?php echo $backupSettings['backup_cloud_enabled'] === 'true' ? 'مفعلة' : 'معطلة'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1">
                                <i class="fas fa-shield-alt me-2"></i>
                                التشفير: <?php echo $backupSettings['backup_encryption'] === 'true' ? 'مفعل' : 'معطل'; ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                آخر مزامنة: <?php echo date('Y-m-d H:i'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- النسخ الاحتياطية المجدولة -->
        <div class="backup-card">
            <h5 class="mb-3">
                <i class="fas fa-list me-2"></i>
                النسخ الاحتياطية المجدولة
            </h5>
            
            <?php if (!empty($scheduledBackups)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>اسم النسخة الاحتياطية</th>
                            <th>التاريخ</th>
                            <th>الحجم</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduledBackups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['report_name']); ?></td>
                            <td><?php echo formatDate($backup['created_at']); ?></td>
                            <td><?php echo $backup['file_size'] ? number_format($backup['file_size'] / 1024, 2) . ' KB' : 'غير محدد'; ?></td>
                            <td>
                                <span class="status-badge bg-<?php echo $backup['status'] === 'completed' ? 'success' : 'warning'; ?> text-white">
                                    <?php echo $backup['status'] === 'completed' ? 'مكتملة' : 'قيد التنفيذ'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadBackup(<?php echo $backup['id']; ?>)">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="syncToCloud(<?php echo $backup['id']; ?>)">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup(<?php echo $backup['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-database fa-3x mb-3"></i>
                <h6>لا توجد نسخ احتياطية</h6>
                <p>لم يتم إنشاء أي نسخ احتياطية بعد</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createBackup() {
            if (confirm('هل تريد إنشاء نسخة احتياطية فورية؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="create_manual_backup">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function syncToCloud(backupId) {
            if (confirm('هل تريد مزامنة هذه النسخة الاحتياطية مع السحابة؟')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cloud_sync">
                    <input type="hidden" name="backup_id" value="${backupId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function downloadBackup(backupId) {
            // في التطبيق الحقيقي، هنا سيتم تحميل الملف
            alert('سيتم تحميل النسخة الاحتياطية...');
        }

        function deleteBackup(backupId) {
            if (confirm('هل أنت متأكد من حذف هذه النسخة الاحتياطية؟')) {
                // في التطبيق الحقيقي، هنا سيتم حذف الملف
                alert('سيتم حذف النسخة الاحتياطية...');
            }
        }

        // تحديث الصفحة كل 30 ثانية لعرض الحالة الحالية
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
