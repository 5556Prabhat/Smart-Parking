<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = '';
$error = '';

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $password_confirmation = $_POST['password_confirmation'];
    $delete_confirmation = $_POST['delete_confirmation'];
    
    // Validate input
    if (empty($password_confirmation) || $delete_confirmation !== 'DELETE') {
        $error = "Please confirm deletion properly by typing DELETE and entering your password";
    } else {
        // Verify password
        if (password_verify($password_confirmation, $user['password'])) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete user bookings
                $booking_sql = "DELETE FROM bookings WHERE user_id = ?";
                $stmt = $conn->prepare($booking_sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Check if activity_log table exists and delete from it if it does
                $table_check = $conn->query("SHOW TABLES LIKE 'activity_log'");
                if ($table_check->num_rows > 0) {
                    $activity_sql = "DELETE FROM activity_log WHERE user_id = ?";
                    $stmt = $conn->prepare($activity_sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                }
                
                // Delete the user account
                $user_sql = "DELETE FROM users WHERE id = ?";
                $stmt = $conn->prepare($user_sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Log the user out by destroying session
                session_destroy();
                
                // Redirect to home page with a message
                header("Location: ../index.php?message=account_deleted");
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error deleting account: " . $e->getMessage();
            }
        } else {
            $error = "Incorrect password. Account deletion failed.";
        }
    }
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    
    // Validate input
    if (empty($username) || empty($email)) {
        $error = "Username and email are required";
    } else {
        // Check if username or email already exists for another user
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssi", $username, $email, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully";
                $_SESSION['username'] = $username;
                
                // Refresh user data
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Error updating profile: " . $conn->error;
            }
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password changed successfully";
            } else {
                $error = "Error changing password: " . $conn->error;
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
}

// Get account activity
$activity_sql = "SELECT action, description, created_at FROM activity_log 
                WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($activity_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activity_result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal.show-modal {
            display: flex;
        }
        
        .modal-content {
            background-color: #fff;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #e74c3c;
            font-size: 1.5rem;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #777;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .delete-warning {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fef2f2;
            border-radius: 8px;
        }
        
        .delete-warning i {
            font-size: 40px;
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .delete-warning h3 {
            color: #e74c3c;
            margin: 10px 0;
        }
        
        .danger-zone {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border: 1px solid #e74c3c;
        }
        
        .danger-zone h2 {
            color: #e74c3c;
            margin-top: 0;
        }
        
        .text-right {
            text-align: right;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-sidebar">
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <h3><?php echo $user['username']; ?></h3>
                    <p>Member</p>
                </div>
            </div>
            
            <ul class="dashboard-nav">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="book-space.php"><i class="fas fa-parking"></i> Book Parking</a></li>
                <li><a href="bookings.php"><i class="fas fa-history"></i> Booking History</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user-cog"></i> My Profile</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/index.php"><i class="fas fa-user-shield"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>My Profile</h1>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-grid">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h2>Profile Information</h2>
                        <p>Update your account details</p>
                    </div>
                    
                    <form action="" method="POST" class="profile-form">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo $user['username']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Member Since</label>
                            <input type="text" value="<?php echo formatDateTime($user['created_at'], 'F j, Y'); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <div class="profile-card">
                    <div class="profile-card-header">
                        <h2>Change Password</h2>
                        <p>Update your password regularly for security</p>
                    </div>
                    
                    <form action="" method="POST" class="profile-form" id="password-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                            <div class="password-requirements">
                                Password must be at least 6 characters long
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
                
                <div class="profile-card activity">
                    <div class="profile-card-header">
                        <h2>Recent Account Activity</h2>
                        <p>Your recent actions and login history</p>
                    </div>
                    
                    <div class="activity-list">
                        <?php if ($activity_result && $activity_result->num_rows > 0): ?>
                            <?php while ($activity = $activity_result->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $icon = 'fa-info-circle';
                                        if (strpos($activity['action'], 'login') !== false) {
                                            $icon = 'fa-sign-in-alt';
                                        } elseif (strpos($activity['action'], 'book') !== false) {
                                            $icon = 'fa-calendar-plus';
                                        } elseif (strpos($activity['action'], 'cancel') !== false) {
                                            $icon = 'fa-calendar-times';
                                        } elseif (strpos($activity['action'], 'update') !== false) {
                                            $icon = 'fa-edit';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p class="activity-desc"><?php echo $activity['description']; ?></p>
                                        <p class="activity-time"><?php echo formatDateTime($activity['created_at']); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-activity">
                                <p>No recent activity found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-card preferences">
                    <div class="profile-card-header">
                        <h2>Account Preferences</h2>
                        <p>Manage your account settings</p>
                    </div>
                    
                    <form action="" method="POST" class="profile-form">
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="email_notifications" name="email_notifications" checked>
                            <label for="email_notifications">Email Notifications</label>
                            <p class="help-text">Receive booking confirmations and reminders via email</p>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications">
                            <label for="sms_notifications">SMS Notifications</label>
                            <p class="help-text">Receive booking confirmations and reminders via SMS</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="default_vehicle">Default Vehicle</label>
                            <input type="text" id="default_vehicle" name="default_vehicle" placeholder="Enter license plate number">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_preferences" class="btn btn-primary">Save Preferences</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="danger-zone">
                <h2>Danger Zone</h2>
                <p>Permanently delete your account and all associated data</p>
                
                <button class="btn btn-danger" onclick="showDeleteConfirmation()">
                    <i class="fas fa-trash"></i> Delete Account
                </button>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal" id="deleteAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Account</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Warning: This action cannot be undone</h3>
                    <p>Deleting your account will permanently remove all your data, including booking history, payments, and account information.</p>
                </div>
                
                <form action="" method="POST" id="delete-account-form">
                    <div class="form-group">
                        <label for="delete_confirmation">Type "DELETE" to confirm</label>
                        <input type="text" id="delete_confirmation" name="delete_confirmation" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirmation">Enter your password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" name="delete_account" class="btn btn-danger">Delete My Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Password validation
        const passwordForm = document.getElementById('password-form');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        passwordForm.addEventListener('submit', function(event) {
            if (newPassword.value !== confirmPassword.value) {
                event.preventDefault();
                alert('New passwords do not match');
            }
        });
        
        // Delete account modal
        const deleteModal = document.getElementById('deleteAccountModal');
        const deleteForm = document.getElementById('delete-account-form');
        const deleteConfirmation = document.getElementById('delete_confirmation');
        
        function showDeleteConfirmation() {
            deleteModal.classList.add('show-modal');
        }
        
        function closeDeleteModal() {
            deleteModal.classList.remove('show-modal');
        }
        
        deleteForm.addEventListener('submit', function(event) {
            if (deleteConfirmation.value !== 'DELETE') {
                event.preventDefault();
                alert('Please type DELETE to confirm account deletion');
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        };
    </script>
</body>
</html>
