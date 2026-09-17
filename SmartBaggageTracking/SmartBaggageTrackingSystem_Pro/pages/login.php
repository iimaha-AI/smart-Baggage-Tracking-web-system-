<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security/role_guard.php';

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $form_data = [
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'remember' => isset($_POST['remember'])
    ];

    // Validate data
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($form_data['password'])) {
        $errors['password'] = 'Password is required';
    }

    // Attempt login if no errors
    if (empty($errors)) {
        try {
            $db = new Database();
            $pdo = $db->getConnection();
            
            // Search for user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check account status
                if ($user['status'] === 'pending') {
                    $errors['general'] = 'Your account is pending verification. Please check your email or contact administration.';
                } elseif ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                    $errors['general'] = 'Your account is inactive. Please contact administration.';
                } elseif ($user['status'] === 'active' && password_verify($form_data['password'], $user['password'])) {
                    // Successful login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Update last login time
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?");
                    $update_stmt->execute([$user['id']]);
                    
                    // Redirect based on role
                    $roleGuard = new RoleGuard();
                    $roleGuard->redirectToAppropriatePage($user['role']);
                } else {
                    $errors['general'] = 'Invalid email or password';
                    
                    // Increase failed login attempts
                    $attempts_stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?");
                    $attempts_stmt->execute([$user['id']]);
                }
            } else {
                $errors['general'] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'System error occurred. Please try again later.';
            error_log("Database error: " . $e->getMessage());
        }
    }
}

// Function to determine redirect page based on user role
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Baggage Tracking System</title>
    
    <!-- Basic Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: var(--gradient);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateX(-50px) translateY(-50px); }
            100% { transform: translateX(50px) translateY(50px); }
        }
        
        .login-header-content {
            position: relative;
            z-index: 2;
        }
        
        .login-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-label {
            color: #495057;
            font-weight: 500;
        }
        
        .input-group-text {
            background: var(--gradient);
            border: none;
            color: white;
            border-radius: 15px 0 0 15px;
        }
        
        .btn-login {
            background: var(--gradient);
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .form-check-input:checked {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .form-check-label {
            color: #495057;
            font-weight: 500;
        }
        
        .login-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .login-links a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 10px;
        }
        
        .login-links a:hover {
            color: var(--primary);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .features-list {
            margin-top: 25px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 15px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .feature-item:hover {
            background: rgba(52, 152, 219, 0.15);
            transform: translateX(-5px);
        }
        
        .feature-item i {
            font-size: 1.2rem;
            color: var(--secondary);
            margin-right: 10px;
            width: 25px;
            text-align: center;
        }
        
        .feature-item span {
            color: #495057;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 5;
        }
        
        .password-toggle:hover {
            color: var(--secondary);
        }
        
        @media (max-width: 768px) {
            .login-header h1 {
                font-size: 1.8rem;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .login-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-header-content">
                    <h1><i class="fas fa-sign-in-alt"></i> Login</h1>
                    <p>Welcome back to the Smart Baggage Tracking System</p>
                </div>
            </div>
            
            <div class="login-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <div class="form-floating">
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               id="email" name="email" placeholder="Email Address" 
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-floating position-relative">
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock"></i> Password *</label>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" 
                                   <?php echo isset($form_data['remember']) && $form_data['remember'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="remember">
                                Remember Me
                            </label>
                        </div>
                        
                        <a href="forgot-password.php" class="text-decoration-none">
                            <small>Forgot Password?</small>
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="login-links">
                    <p class="mb-3">Don't have an account?</p>
                    <a href="register.php" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
                
                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Your data is protected with the highest security standards</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bell"></i>
                        <span>Real-time tracking of your baggage with instant notifications</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-headset"></i>
                        <span>24/7 technical support available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Show/hide password
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Additional visual effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
        
        // Auto focus on email field
        document.getElementById('email').focus();
    </script>
</body>
</html>