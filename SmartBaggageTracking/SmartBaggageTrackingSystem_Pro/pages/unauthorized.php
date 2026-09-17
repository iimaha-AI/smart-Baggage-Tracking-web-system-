<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security/role_guard.php';

// Get current user information
$user_role = $_SESSION['user_role'] ?? 'unknown';
$user_name = $_SESSION['user_name'] ?? 'Unknown User';
$role_info = getRoleInfo($user_role);

// Determine appropriate redirect page
$redirect_url = '../pages/login.php';
if ($user_role !== 'unknown') {
    switch ($user_role) {
        case 'system_admin':
        case 'airline_manager':
            $redirect_url = '../admin/dashboard.php';
            break;
        case 'supervisor':
        case 'handling_staff':
        case 'counter_staff':
            $redirect_url = '../staff/dashboard.php';
            break;
        case 'passenger':
            $redirect_url = '../passenger/dashboard.php';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Smart Baggage Tracking System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
                    }
                }
            }
        }
    </script>
</head>
<body class="font-inter bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <!-- Error Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 text-center">
            <!-- Error Icon -->
            <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-shield-alt text-red-600 text-4xl"></i>
            </div>
            
            <!-- Error Title -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Unauthorized Access
            </h1>
            
            <!-- Error Message -->
            <div class="text-gray-600 mb-6">
                <p class="mb-3">
                    Sorry, you don't have permission to access this page.
                </p>
                
                <?php if ($role_info): ?>
                <div class="bg-blue-50 rounded-lg p-4 mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-user-tag mr-2"></i>
                        Your Current Role: <?php echo $role_info['name']; ?>
                    </h3>
                    <p class="text-blue-700 text-sm">
                        <?php echo $role_info['description']; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <p class="text-sm text-gray-500">
                    If you believe this is an error, please contact the system administrator.
                </p>
            </div>
            
            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="<?php echo $redirect_url; ?>" 
                   class="block w-full bg-primary text-white py-3 px-6 rounded-lg hover:bg-opacity-90 transition-all duration-200 font-semibold">
                    <i class="fas fa-home mr-2"></i>
                    Return to Home Page
                </a>
                
                <a href="../pages/login.php" 
                   class="block w-full bg-gray-100 text-gray-700 py-3 px-6 rounded-lg hover:bg-gray-200 transition-all duration-200 font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login Again
                </a>
            </div>
            
            <!-- Additional Information -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="text-xs text-gray-500 space-y-1">
                    <p><i class="fas fa-clock mr-1"></i> Time: <?php echo date('Y-m-d H:i:s'); ?></p>
                    <p><i class="fas fa-user mr-1"></i> User: <?php echo htmlspecialchars($user_name); ?></p>
                    <p><i class="fas fa-tag mr-1"></i> Role: <?php echo htmlspecialchars($user_role); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Help Information -->
        <div class="mt-6 bg-white rounded-xl shadow-lg p-6">
            <h3 class="font-semibold text-gray-800 mb-3">
                <i class="fas fa-question-circle mr-2 text-blue-600"></i>
                Need Help?
            </h3>
            <div class="space-y-2 text-sm text-gray-600">
                <p><i class="fas fa-phone mr-2 text-green-600"></i> Technical Support: +966 11 123 4567</p>
                <p><i class="fas fa-envelope mr-2 text-blue-600"></i> Email: support@baggage-tracker.com</p>
                <p><i class="fas fa-clock mr-2 text-orange-600"></i> Working Hours: 24/7</p>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="mt-4 grid grid-cols-2 gap-3">
            <a href="../pages/forgot_password.php" 
               class="bg-yellow-50 text-yellow-700 py-2 px-4 rounded-lg hover:bg-yellow-100 transition-colors text-sm text-center">
                <i class="fas fa-key mr-1"></i>
                Forgot Password?
            </a>
            
            <a href="../pages/register.php" 
               class="bg-green-50 text-green-700 py-2 px-4 rounded-lg hover:bg-green-100 transition-colors text-sm text-center">
                <i class="fas fa-user-plus mr-1"></i>
                Create New Account
            </a>
        </div>
    </div>
    
    <!-- JavaScript for Auto Redirect -->
    <script>
        // Auto redirect after 30 seconds
        let countdown = 30;
        const countdownElement = document.createElement('div');
        countdownElement.className = 'mt-4 text-center text-sm text-gray-500';
        countdownElement.innerHTML = `You will be redirected automatically in <span id="countdown">${countdown}</span> seconds`;
        document.querySelector('.max-w-md').appendChild(countdownElement);
        
        const countdownInterval = setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '<?php echo $redirect_url; ?>';
            }
        }, 1000);
        
        // Stop countdown when clicking on any link
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                clearInterval(countdownInterval);
            });
        });
    </script>
</body>
</html>