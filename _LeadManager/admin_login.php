<?php
session_start();
include('db.php');

$error = '';

// Redirect if already logged in
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle login form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if(empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, email, full_name FROM admins WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Direct password comparison (no hashing)
            if($password === $admin['password']) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $admin['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Redirect to admin dashboard
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Domain Monitor</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: float 20s ease-in-out infinite;
        }
        
        body::before {
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.2);
            top: -100px;
            left: -100px;
        }
        
        body::after {
            width: 500px;
            height: 500px;
            background: rgba(118, 75, 162, 0.3);
            bottom: -150px;
            right: -150px;
            animation-delay: 5s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(50px, -50px) scale(1.1); }
            66% { transform: translate(-50px, 50px) scale(0.9); }
        }
        
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            padding: 20px;
            margin: 0 auto;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 50px 40px;
            animation: fadeInScale 0.6s ease;
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logo-section h1 {
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.2);
        }
        
        .logo-section p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            font-weight: 400;
        }
        
        .alert {
            background: rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 14px 18px;
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 14px;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .alert i {
            margin-right: 8px;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 30px;
        }
        
        .input-group label {
            display: block;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.2);
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
            z-index: 1;
        }
        
        .toggle-password {
            position: absolute;
            right: 18px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 18px;
            cursor: pointer;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .toggle-password:hover {
            color: rgba(255, 255, 255, 1);
        }
        
        .input-field {
            width: 100%;
            padding: 16px 50px 16px 50px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .input-field::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .input-field:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: rgba(255, 255, 255, 0.95);
            color: #667eea;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
            background: #fff;
        }
        
        .btn-login:active {
            transform: translateY(-1px);
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .divider span {
            background: transparent;
            padding: 0 15px;
            position: relative;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 600;
        }
        
        .user-link {
            text-align: center;
        }
        
        .user-link a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .user-link a:hover {
            transform: scale(1.05);
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        @media (max-width: 576px) {
            .glass-card {
                padding: 40px 30px;
                border-radius: 25px;
            }
            
            .logo-icon {
                width: 70px;
                height: 70px;
                font-size: 32px;
            }
            
            .logo-section h1 {
                font-size: 28px;
            }
            
            .input-field {
                padding: 14px 45px 14px 45px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="glass-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1>Admin Portal</h1>
                <p>Domain Monitoring System</p>
            </div>
            
            <?php if(!empty($error)): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="input-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               id="username"
                               name="username" 
                               class="input-field" 
                               placeholder="Enter your username" 
                               required 
                               autofocus
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               id="password"
                               name="password" 
                               class="input-field" 
                               placeholder="Enter your password" 
                               required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-arrow-right-to-bracket"></i> Login
                </button>
            </form>
            
            <div class="divider">
                <span>OR</span>
            </div>
            
            <div class="user-link">
                <a href="user_login.php">
                    <i class="fas fa-users"></i> User Login
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle Password Visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
