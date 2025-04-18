<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
session_start();

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

// Check if user is verified through OTP
if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    redirect('forgot_password.php');
}

$error = '';
$success = '';
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

// If no email in session, redirect
if (empty($email)) {
    redirect('forgot_password.php');
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user's password
        $update_sql = "UPDATE users SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // Delete the used OTP
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            $success = "Your password has been successfully reset. You can now log in with your new password.";
            
            // Get the user ID for logging
            $user_sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($user_sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user_result = $stmt->get_result();
            
            if ($user_result->num_rows === 1) {
                $user = $user_result->fetch_assoc();
                logActivity($conn, $user['id'], 'password_reset', "Password reset completed for user ID: {$user['id']}");
            }
            
            // Clear the reset session variables
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_email']);
        } else {
            $error = "An error occurred while resetting your password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ParkSmart</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container reset-password-container">
                <div class="auth-form-container">
                    <h1>Reset Your Password</h1>
                    <p>Create a new password for your account</p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                        <div class="back-link">
                            <a href="pages/login.php" class="btn btn-primary">Log In</a>
                        </div>
                    <?php else: ?>
                        <form action="" method="POST" class="auth-form" id="password-reset-form">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" minlength="6" required>
                                </div>
                                <div class="password-requirements">
                                    Password must be at least 6 characters long
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="reset_password" class="btn btn-primary btn-block">Reset Password</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <div class="auth-image">
                    <div class="auth-image-content">
                        <h2>Create a Strong Password</h2>
                        <p>Keep your account secure with a strong password.</p>
                        <ul class="auth-features">
                            <li><i class="fas fa-check-circle"></i> Use at least 6 characters</li>
                            <li><i class="fas fa-check-circle"></i> Include numbers and special characters</li>
                            <li><i class="fas fa-check-circle"></i> Don't reuse passwords from other sites</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Password validation
        const passwordForm = document.getElementById('password-reset-form');
        if (passwordForm) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            passwordForm.addEventListener('submit', function(event) {
                if (password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match');
                }
            });
        }
    </script>
</body>
</html>
