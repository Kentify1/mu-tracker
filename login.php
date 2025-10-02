<?php
session_start();
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: index');
    exit;
}

$error_message = '';
$success_message = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = 'Please fill in all fields';
        } else {
            $result = $auth->login($username, $password);
            if ($result['success']) {
                header('Location: index');
                exit;
            } else {
                $error_message = $result['message'];
            }
        }
    } elseif ($_POST['action'] === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $license_key = trim($_POST['license_key'] ?? '');

        if (empty($username) || empty($email) || empty($password) || empty($license_key)) {
            $error_message = 'Please fill in all fields';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email address';
        } else {
            $result = $auth->register($username, $email, $password, $license_key);
            if ($result['success']) {
                $success_message = 'Registration successful! You can now login.';
            } else {
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MU Tracker - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="https://dragon.mu/assets/dragon/images/favicon.ico" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            margin: 0;
            font-size: 2em;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.8;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .tab-button {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }

        .tab-button.active {
            color: #4ecdc4;
            border-bottom-color: #4ecdc4;
        }

        .tab-button:hover {
            color: #4ecdc4;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4ecdc4;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 20px rgba(78, 205, 196, 0.3);
        }

        .form-group input::placeholder {
            color: #888;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(78, 205, 196, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }

        .form-content {
            display: none;
        }

        .form-content.active {
            display: block;
        }

        .license-info {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #4ecdc4;
        }

        .license-info h4 {
            margin: 0 0 10px 0;
            color: #4ecdc4;
        }

        .license-info p {
            margin: 5px 0;
            opacity: 0.8;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #4ecdc4;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-gamepad"></i> MU Tracker</h1>
            <p>Secure Character Tracking</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>


        <div class="form-tabs">
            <button class="tab-button active" onclick="showTab('login')">Login</button>
            <button class="tab-button" onclick="showTab('register')">Register</button>
        </div>

        <!-- Login Form -->
        <div id="login-form" class="form-content active">
            <form method="POST">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="login-username">Username or Email</label>
                    <input type="text" id="login-username" name="username" placeholder="Enter your username or email" required>
                </div>

                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>

        <!-- Register Form -->
        <div id="register-form" class="form-content">
            <div class="license-info">
                <h4><i class="fas fa-key"></i> License Required</h4>
                <p>You need a valid license key to register. Contact the administrator for a license key.</p>
                <p><strong>Sample License:</strong> MUTRACK-2024-001</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label for="reg-username">Username</label>
                    <input type="text" id="reg-username" name="username" placeholder="Choose a username" required>
                </div>

                <div class="form-group">
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="reg-password">Password</label>
                    <input type="password" id="reg-password" name="password" placeholder="Choose a password (min 6 characters)" required>
                </div>

                <div class="form-group">
                    <label for="reg-confirm-password">Confirm Password</label>
                    <input type="password" id="reg-confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                </div>

                <div class="form-group">
                    <label for="reg-license">License Key</label>
                    <input type="text" id="reg-license" name="license_key" placeholder="Enter your license key" required>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>
        </div>

        <div class="back-link">
            <a href="index"><i class="fas fa-arrow-left"></i> Back to Tracker</a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all forms
            document.querySelectorAll('.form-content').forEach(form => {
                form.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected form
            document.getElementById(tabName + '-form').classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>

</html>