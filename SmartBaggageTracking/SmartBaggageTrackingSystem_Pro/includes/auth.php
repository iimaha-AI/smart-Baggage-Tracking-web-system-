<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($email, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            // Prevent session fixation after successful authentication.
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && (time() - $_SESSION['login_time']) < SESSION_TIMEOUT;
    }
    
    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUserName() {
        return $_SESSION['user_name'] ?? null;
    }
    
    public function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: ../pages/login.php');
            exit;
        }
    }
    
    public function requireRole($requiredRoles) {
        $this->requireAuth();
        
        if (is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }
        
        if (!in_array($this->getUserRole(), $requiredRoles)) {
            header('Location: ../pages/unauthorized.php');
            exit;
        }
    }
    
    public function register($userData) {
        // Check if email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE email = ?",
            [$userData['email']]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // إدخال المستخدم
        $this->db->query(
            "INSERT INTO users (full_name, email, password, role, status) 
             VALUES (?, ?, ?, 'passenger', 'active')",
            [
                $userData['full_name'],
                $userData['email'],
                $hashedPassword
            ]
        );
        
        return ['success' => true, 'message' => 'تم إنشاء الحساب بنجاح'];
    }
    
    public function getUserInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ?",
            [$this->getUserId()]
        );
    }
    
    public function updateProfile($userData) {
        $this->requireAuth();
        
        $this->db->query(
            "UPDATE users SET full_name = ?, email = ? WHERE id = ?",
            [$userData['full_name'], $userData['email'], $this->getUserId()]
        );
        
        $_SESSION['user_name'] = $userData['full_name'];
        $_SESSION['user_email'] = $userData['email'];
        
        return true;
    }
    
    public function changePassword($currentPassword, $newPassword) {
        $this->requireAuth();
        
        $user = $this->getUserInfo();
        
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $this->db->query(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $this->getUserId()]
        );
        
        return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح'];
    }
}

// إنشاء كائن المصادقة (يتم إنشاؤه عند الحاجة فقط)
// $auth = new Auth($database);
?>