<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include files
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../pages/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $existing_user = $result->fetch_assoc();
            if ($existing_user['username'] === $username) {
                $error = "Username already taken. Please choose another.";
            } else {
                $error = "Email address already registered. Please use another email.";
            }
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Log the registration
                if (function_exists('logActivity')) {
                    logActivity($conn, $user_id, 'register', 'New user registration');
                }
                
                $success = "Registration successful! You can now log in.";
                
                // Optionally auto-login the user
                /*
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = 0;
                header("Location: ../pages/dashboard.php");
                exit();
                */
            } else {
                $error = "Registration failed: " . $conn->error;
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
    <title>Sign Up - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form-container">
                    <h1>Create an Account</h1>
                    <p>Join ParkSmart and start managing parking easily</p>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <p>You will be redirected to the login page in <span id="countdown">5</span> seconds...</p>
                        </div>
                        <script>
                            // Countdown and redirect
                            let seconds = 5;
                            const countdownElement = document.getElementById('countdown');
                            const interval = setInterval(function() {
                                seconds--;
                                countdownElement.textContent = seconds;
                                if (seconds <= 0) {
                                    clearInterval(interval);
                                    window.location.href = 'login.php';
                                }
                            }, 1000);
                        </script>
                    <?php else: ?>
                        <form action="" method="POST" class="auth-form" id="register-form">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" id="username" name="username" required minlength="3" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" required
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" required minlength="6">
                                </div>
                                <small class="password-hint">Minimum 6 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="form-group terms-checkbox">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="terms" name="terms" required>
                                    <label for="terms">I agree to the <a href="#" class="terms-link">Terms of Service</a> and <a href="#" class="terms-link">Privacy Policy</a></label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                        </form>
                        
                        <div class="auth-separator">
                            <span>Or</span>
                        </div>
                        
                        <div class="auth-footer">
                            <p>Already have an account? <a href="login.php">Log In</a></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="auth-image">
                    <div class="auth-image-content">
                        <h2>Smart Parking Made Simple</h2>
                        <p>Create your account today and enjoy:</p>
                        <ul class="auth-features">
                            <li><i class="fas fa-check-circle"></i> Easy booking of parking spaces</li>
                            <li><i class="fas fa-check-circle"></i> Real-time availability monitoring</li>
                            <li><i class="fas fa-check-circle"></i> Secure online payments</li>
                            <li><i class="fas fa-check-circle"></i> Booking history and receipts</li>
                            <li><i class="fas fa-check-circle"></i> Exclusive member discounts</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Client-side validation
        document.getElementById('register-form')?.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match!');
            }
        });
        
        // Password strength indicator could be added here
    </script>
</body>
</html>
