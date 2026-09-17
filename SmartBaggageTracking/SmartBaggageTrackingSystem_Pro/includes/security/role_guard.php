<?php
/**
 * Centralized Role and Permission Protection System
 * Role Guard System - Centralized Role & Permission Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class RoleGuard {
    private $db;
    
    // Role and permissions definition
    private $role_permissions = [
        'system_admin' => [
            'name' => 'System Administrator',
            'description' => 'Full permissions on all system components',
            'allowed_pages' => [
                '/admin/dashboard.php',
                '/admin/analytics.php',
                '/admin/backup.php',
                '/admin/system_settings.php',
                '/admin/user_management.php',
                '/admin/audit/audit_logs.php',
                '/admin/audit/change_history.php',
                '/admin/audit/user_activity.php',
                '/admin/permissions/permission_management.php',
                '/admin/settings/advanced/*'
            ],
            'data_access' => 'all',
            'airline_filter' => false
        ],
        'airline_manager' => [
            'name' => 'Airline Manager',
            'description' => 'Airline and flight management',
            'allowed_pages' => [
                '/admin/dashboard.php',
                '/flights/management.php',
                '/flights/schedule.php',
                '/admin/analytics.php',
                '/notifications/notification_center.php',
                '/pages/profile.php',
                '/reports/advanced/payment_reports.php',
                '/crm/customer_feedback.php'
            ],
            'data_access' => 'airline_specific',
            'airline_filter' => true
        ],
        'supervisor' => [
            'name' => 'Supervisor',
            'description' => 'Airport operations monitoring and supervision',
            'allowed_pages' => [
                '/admin/dashboard.php',
                '/admin/analytics.php',
                '/staff/baggage_search.php',
                '/staff/tracking_update.php',
                '/reports/advanced/custom_report_builder.php',
                '/pages/profile.php',
                '/quality/inspection_reports.php',
                '/support/ticket_dashboard.php'
            ],
            'data_access' => 'airport_specific',
            'airline_filter' => false
        ],
        'handling_staff' => [
            'name' => 'Handling Staff',
            'description' => 'Baggage loading and unloading',
            'allowed_pages' => [
                '/staff/dashboard.php',
                '/staff/tracking_update.php',
                '/staff/baggage_search.php',
                '/pages/profile.php',
                '/maps/baggage_location.php'
            ],
            'data_access' => 'limited',
            'airline_filter' => false
        ],
        'counter_staff' => [
            'name' => 'Counter Staff',
            'description' => 'Baggage registration and customer service',
            'allowed_pages' => [
                '/staff/dashboard.php',
                '/staff/baggage_register.php',
                '/staff/baggage_search.php',
                '/staff/tracking_update.php',
                '/pages/profile.php',
                '/payments/payment_gateway.php',
                '/reports/advanced/data_export.php'
            ],
            'data_access' => 'limited',
            'airline_filter' => false
        ],
        'passenger' => [
            'name' => 'Passenger',
            'description' => 'Baggage tracking and personal services',
            'allowed_pages' => [
                '/passenger/dashboard.php',
                '/passenger/baggage_tracking.php',
                '/passenger/flight_info.php',
                '/passenger/history.php',
                '/pages/profile.php',
                '/support/create_ticket.php',
                '/notifications/notification_center.php',
                '/support/live_chat.php',
                '/multilingual/language_manager.php'
            ],
            'data_access' => 'own_data_only',
            'airline_filter' => false
        ]
    ];
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        }
    }
    
    /**
     * Check page access permission
     */
    public function checkPageAccess($page_path, $user_role = null) {
        if (!$user_role) {
            $user_role = $_SESSION['user_role'] ?? null;
        }
        
        if (!$user_role || !isset($this->role_permissions[$user_role])) {
            return false;
        }
        
        $allowed_pages = $this->role_permissions[$user_role]['allowed_pages'];
        
        // Check specific page
        foreach ($allowed_pages as $allowed_page) {
            if ($allowed_page === $page_path) {
                return true;
            }
            
            // Check pages ending with /*
            if (substr($allowed_page, -2) === '/*') {
                $prefix = substr($allowed_page, 0, -2);
                if (strpos($page_path, $prefix) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check data access permission
     */
    public function checkDataAccess($user_role = null, $airline_id = null) {
        if (!$user_role) {
            $user_role = $_SESSION['user_role'] ?? null;
        }
        
        if (!$user_role || !isset($this->role_permissions[$user_role])) {
            return false;
        }
        
        $data_access = $this->role_permissions[$user_role]['data_access'];
        $airline_filter = $this->role_permissions[$user_role]['airline_filter'];
        
        // Check airline filtering
        if ($airline_filter && $airline_id) {
            $user_airline_id = $_SESSION['user_airline_id'] ?? null;
            if ($user_airline_id && $user_airline_id != $airline_id) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get role information
     */
    public function getRoleInfo($role) {
        return $this->role_permissions[$role] ?? null;
    }
    
    /**
     * Get all roles
     */
    public function getAllRoles() {
        return array_keys($this->role_permissions);
    }
    
    /**
     * Check if role exists
     */
    public function isValidRole($role) {
        return isset($this->role_permissions[$role]);
    }
    
    /**
     * Add airline filter to queries
     */
    public function addAirlineFilter($sql, $user_role = null, $airline_id_column = 'airline_id') {
        if (!$user_role) {
            $user_role = $_SESSION['user_role'] ?? null;
        }
        
        if (!$user_role || !isset($this->role_permissions[$user_role])) {
            return $sql;
        }
        
        $airline_filter = $this->role_permissions[$user_role]['airline_filter'];
        
        if ($airline_filter) {
            $user_airline_id = $_SESSION['user_airline_id'] ?? null;
            if ($user_airline_id) {
                // Add WHERE clause or AND clause
                if (stripos($sql, 'WHERE') !== false) {
                    $sql .= " AND {$airline_id_column} = {$user_airline_id}";
                } else {
                    $sql .= " WHERE {$airline_id_column} = {$user_airline_id}";
                }
            }
        }
        
        return $sql;
    }
    
    /**
     * Log unauthorized access attempt
     */
    public function logUnauthorizedAccess($page, $user_id = null, $user_role = null) {
        if (!$user_id) {
            $user_id = $_SESSION['user_id'] ?? 'anonymous';
        }
        if (!$user_role) {
            $user_role = $_SESSION['user_role'] ?? 'unknown';
        }
        
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'user_role' => $user_role,
            'attempted_page' => $page,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Save to log file
        $log_file = '../logs/security/unauthorized_access.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Redirect user to appropriate page
     */
    public function redirectToAppropriatePage($user_role = null) {
        if (!$user_role) {
            $user_role = $_SESSION['user_role'] ?? null;
        }
        
        switch ($user_role) {
            case 'system_admin':
            case 'airline_manager':
                header('Location: ../admin/dashboard.php');
                break;
            case 'supervisor':
            case 'handling_staff':
            case 'counter_staff':
                header('Location: ../staff/dashboard.php');
                break;
            case 'passenger':
                header('Location: ../passenger/dashboard.php');
                break;
            default:
                header('Location: ../pages/unauthorized.php');
                break;
        }
        exit;
    }
    
    /**
     * Comprehensive permission check
     */
    public function requireAccess($page_path, $user_role = null) {
        if (!$this->checkPageAccess($page_path, $user_role)) {
            $this->logUnauthorizedAccess($page_path);
            header('Location: ../pages/unauthorized.php');
            exit;
        }
    }
}

// Create global RoleGuard object
$roleGuard = new RoleGuard();

// Helper functions for quick use
function requireRole($roles) {
    global $roleGuard;
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    $user_role = $_SESSION['user_role'] ?? null;
    
    if (!$user_role || !in_array($user_role, $roles)) {
        $roleGuard->logUnauthorizedAccess($_SERVER['REQUEST_URI']);
        header('Location: ../pages/unauthorized.php');
        exit;
    }
}

function requirePageAccess($page_path) {
    global $roleGuard;
    $roleGuard->requireAccess($page_path);
}

function addAirlineFilter($sql, $airline_id_column = 'airline_id') {
    global $roleGuard;
    return $roleGuard->addAirlineFilter($sql, null, $airline_id_column);
}

function getRoleInfo($role) {
    global $roleGuard;
    return $roleGuard->getRoleInfo($role);
}

function redirectToAppropriatePage() {
    global $roleGuard;
    $roleGuard->redirectToAppropriatePage();
}
?>
