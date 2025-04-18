<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include files only once
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../pages/dashboard.php");
    exit();
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Both username and password are required";
    } else {
        // Get user from database
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = ($user['username'] === 'admin') ? 1 : 0;
                
                // Log the login activity if function exists
                if (function_exists('logActivity')) {
                    logActivity($conn, $user['id'], 'login', 'User logged in');
                }
                
                // Redirect to dashboard
                header("Location: ../pages/dashboard.php");
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Username not found";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-container">
                    <h1>Welcome Back</h1>
                    <p>Log in to your ParkSmart account</p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" class="auth-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="username" name="username" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="form-footer">
    <div class="remember-me">
        <input type="checkbox" id="remember" name="remember">
        <label for="remember">Remember me</label>
    </div>
    <a href="../forgot_password.php" class="forgot-password">Forgot Password?</a>
</div>

                        
                        <button type="submit" class="btn btn-primary btn-block">Log In</button>
                    </form>
                    
                    <div class="auth-separator">
                        <span>Or</span>
                    </div>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? <a href="register.php">Sign Up</a></p>
                    </div>
                </div>
                
                <div class="auth-image">
                    <div class="auth-image-content">
                        <h2>Smart Parking Made Simple</h2>
                        <p>Find, book, and pay for parking in seconds.</p>
                        <ul class="auth-features">
                            <li><i class="fas fa-check-circle"></i> Real-time availability</li>
                            <li><i class="fas fa-check-circle"></i> Secure online payments</li>
                            <li><i class="fas fa-check-circle"></i> Reserved parking spaces</li>
                            <li><i class="fas fa-check-circle"></i> Easy booking management</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
