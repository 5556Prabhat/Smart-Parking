<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
session_start();

// Enable debugging for development (remove in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

$error = '';
$success = '';
$show_otp_form = false;
$email = '';
$debug_info = '';

// Process email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = "Email address is required";
    } else {
        // Check if email exists in the database
        $sql = "SELECT id, username FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate a 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes')); // Extended time to 30 minutes
            
            // Delete any existing OTP for this email
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            // Store the OTP in the database
            $insert_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $email, $otp, $expires_at);
            
            if ($stmt->execute()) {
                // In a production environment, send the OTP via email
                // Example: sendEmail($email, 'Your Password Reset OTP', 'Your OTP is: ' . $otp);
                
                $success = "An OTP has been sent to your email address.";
                $show_otp_form = true;
                
                // Log the password reset request
                logActivity($conn, $user['id'], 'password_reset_request', "Password reset OTP sent for user ID: {$user['id']}");
                
                // For development purposes, display the OTP
                $otp_display = "<div class='otp-display'>
                                <p>Since this is a local development environment, here's the OTP:</p>
                                <div class='otp-code'>{$otp}</div>
                               </div>";
                
                // Store email and OTP in session for verification
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp; // Store OTP directly in session as backup
                $_SESSION['reset_time'] = time(); // Store the time when OTP was generated
            } else {
                $error = "An error occurred. Please try again.";
            }
        } else {
            // Don't reveal if the email exists or not (security best practice)
            $success = "If the email address exists in our system, an OTP will be sent.";
            
            // For better UX in development, you might want to show a specific message
            $error = "Email address not found.";
        }
    }
}

// Process OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim(sanitizeInput($_POST['otp']));
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    $session_otp = isset($_SESSION['reset_otp']) ? $_SESSION['reset_otp'] : '';
    
    if (empty($entered_otp) || empty($email)) {
        $error = "OTP and email are required";
        $show_otp_form = true;
    } else {
        // First check - direct session comparison (backup method)
        if ($entered_otp === $session_otp) {
            // OTP matches session directly - proceed
            $_SESSION['reset_verified'] = true;
            redirect('reset_password.php');
        } else {
            // Second check - database verification
            $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $entered_otp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                // OTP is valid, redirect to reset password page
                $_SESSION['reset_verified'] = true;
                redirect('reset_password.php');
            } else {
                // For debugging (you can remove in production)
                $check_sql = "SELECT token, expires_at FROM password_resets WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $token_data = $check_result->fetch_assoc();
                    $debug_info = "Database OTP: " . $token_data['token'] . 
                                 ", Entered OTP: " . $entered_otp . 
                                 ", Expires at: " . $token_data['expires_at'];
                } else {
                    $debug_info = "No OTP found in database for this email.";
                }
                
                $error = "Invalid or expired OTP. Please try again.";
                $show_otp_form = true;
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
    <title>Forgot Password - ParkSmart</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .otp-display {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
        }
        
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #3498db;
            margin: 10px 0;
        }
        
        .otp-input {
            letter-spacing: 15px;
            font-size: 20px;
            padding: 10px 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .otp-form {
            margin-top: 15px;
        }
        
        .resend-otp {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .debug-info {
            background: #f8f8f8;
            border: 1px dashed #ccc;
            padding: 10px;
            margin-top: 15px;
            font-size: 11px;
            color: #666;
            font-family: monospace;
            display: none; /* Change to block to show debugging info */
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container forgot-password-container">
                <div class="auth-form-container">
                    <h1>Forgot Your Password?</h1>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                        
                        <?php if(isset($otp_display)): ?>
                            <?php echo $otp_display; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if($debug_info): // Display debug info when available ?>
                        <div class="debug-info">
                            Debug: <?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($show_otp_form): ?>
                        <!-- OTP Verification Form -->
                        <p>Please enter the 6-digit OTP sent to your email address:</p>
                        <form action="" method="POST" class="auth-form otp-form">
                            <div class="form-group">
                                <label for="otp">Enter OTP</label>
                                <input type="text" id="otp" name="otp" maxlength="6" class="otp-input" 
                                       pattern="[0-9]{6}" placeholder="******" required autofocus>
                            </div>
                            
                            <button type="submit" name="verify_otp" class="btn btn-primary btn-block">Verify OTP</button>
                        </form>
                        
                        <div class="resend-otp">
                            <p>Didn't receive the OTP? <a href="forgot_password.php">Resend OTP</a></p>
                        </div>
                    <?php else: ?>
                        <!-- Email Input Form -->
                        <p>Enter your email address and we'll send you an OTP to reset your password.</p>
                        <form action="" method="POST" class="auth-form">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" required autofocus>
                                </div>
                            </div>
                            
                            <button type="submit" name="send_otp" class="btn btn-primary btn-block">Send OTP</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="auth-footer">
                        <p>Remember your password? <a href="pages/login.php">Log In</a></p>
                    </div>
                </div>
                
                <div class="auth-image">
                    <div class="auth-image-content">
                        <h2>Password Recovery</h2>
                        <p>We'll help you get back into your account safely.</p>
                        <ul class="auth-features">
                            <li><i class="fas fa-lock"></i> Secure verification</li>
                            <li><i class="fas fa-envelope"></i> Email OTP</li>
                            <li><i class="fas fa-shield-alt"></i> Account protection</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Auto format and validate OTP input
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                // Remove non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Limit to 6 digits
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
            
            // Focus the input automatically
            otpInput.focus();
        }
    </script>
</body>
</html>
