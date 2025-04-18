<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include files
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();


// Get active bookings
$sql = "SELECT b.*, ps.space_number, ps.space_type 
        FROM bookings b
        JOIN parking_spaces ps ON b.space_id = ps.id
        WHERE b.user_id = ? AND (b.exit_time IS NULL OR b.exit_time > NOW())
        ORDER BY b.entry_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_bookings = $stmt->get_result();

// Get past bookings (limited to 5)
$sql = "SELECT b.*, ps.space_number, ps.space_type 
        FROM bookings b
        JOIN parking_spaces ps ON b.space_id = ps.id
        WHERE b.user_id = ? AND (b.exit_time IS NOT NULL AND b.exit_time <= NOW())
        ORDER BY b.entry_time DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$past_bookings = $stmt->get_result();

// Get user stats
$sql = "SELECT 
        COUNT(*) as total_bookings,
        SUM(amount_paid) as total_spent,
        MAX(entry_time) as last_booking
        FROM bookings
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stats = $stmt->get_result()->fetch_assoc();

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Verify booking belongs to user
    $check_sql = "SELECT space_id FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $booking = $result->fetch_assoc();
        $space_id = $booking['space_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update booking
            $update_sql = "UPDATE bookings SET exit_time = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            
            // Update space status
            $space_sql = "UPDATE parking_spaces SET status = 'available' WHERE id = ?";
            $stmt = $conn->prepare($space_sql);
            $stmt->bind_param("i", $space_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success = "Booking cancelled successfully";
            logActivity($conn, $user_id, 'cancel_booking', "Cancelled booking ID: $booking_id");
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error cancelling booking: " . $e->getMessage();
        }
    } else {
        $error = "Invalid booking or permission denied";
    }
}

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $password = $_POST['password'];
    
    // Verify user password
    $check_sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        
        // Verify password (assuming you're using password_hash/password_verify)
        if (password_verify($password, $user_data['password'])) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete user bookings
                $booking_sql = "DELETE FROM bookings WHERE user_id = ?";
                $stmt = $conn->prepare($booking_sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Check if activity_logs table exists and delete from it if it does
                $table_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
                if ($table_check->num_rows > 0) {
                    $activity_sql = "DELETE FROM activity_logs WHERE user_id = ?";
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
    } else {
        $error = "User verification failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Updated styles to match the first photo design */
        .dashboard-body {
            background-color: #f5f7fa;
        }
        
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 80px);
        }
        
        .dashboard-sidebar {
            width: 240px;
            background-color: #1e293b;
            color: white;
            padding: 0;
            flex-shrink: 0;
        }
        
        .user-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background-color: #2c3e50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .user-info h3 {
            margin: 10px 0 5px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
        }
        
        .user-info p {
            margin: 0;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .dashboard-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .dashboard-nav li {
            margin: 0;
        }
        
        .dashboard-nav li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .dashboard-nav li a:hover,
        .dashboard-nav li a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: #3498db;
        }
        
        .dashboard-nav li a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .dashboard-content {
            flex: 1;
            padding: 30px;
            background-color: #f5f7fa;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .dashboard-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #2c3e50;
        }
        
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 15px;
            color: white;
        }
        
        /* Colored icons for stat cards */
        .stat-card:nth-child(1) .stat-card-icon {
            background-color: #3498db;
        }
        
        .stat-card:nth-child(2) .stat-card-icon {
            background-color: #2ecc71;
        }
        
        .stat-card:nth-child(3) .stat-card-icon {
            background-color: #f39c12;
        }
        
        .stat-card:nth-child(4) .stat-card-icon {
            background-color: #9b59b6;
        }
        
        .stat-card-content h3 {
            margin: 0 0 8px;
            font-size: 0.9rem;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        
        .dashboard-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .dashboard-section h2 {
            margin: 0 0 20px;
            font-size: 1.3rem;
            color: #2c3e50;
        }
        
        .booking-cards {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 20px;
        }
        
        .booking-card {
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .booking-header {
            padding: 15px 20px;
            background-color: #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-space h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #2c3e50;
        }
        
        .booking-type {
            font-size: 0.8rem;
            background-color: #3498db;
            padding: 3px 10px;
            border-radius: 12px;
            color: white;
        }
        
        .booking-details {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .booking-detail {
            display: flex;
            align-items: flex-start;
        }
        
        .booking-detail i {
            width: 20px;
            margin-right: 15px;
            color: #7f8c8d;
        }
        
        .booking-detail div {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #95a5a6;
            margin-bottom: 3px;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .booking-footer {
            padding: 15px 20px;
            background-color: #f9f9f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-badge.status-active {
            background-color: #2ecc71;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            color: #2c3e50;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fff;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #ecf0f1;
        }

        .modal-header h3 {
            margin: 0;
            color: #e74c3c;
        }

        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
        }

        .modal-body {
            padding: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .stat-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .dashboard-sidebar {
                width: 100%;
            }
            
            .stat-cards {
                grid-template-columns: 1fr;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-body">
    <?php include '../includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-sidebar">
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <h3><?php echo $user['username']; ?></h3>
                    <p><?php echo $user['email']; ?></p>
                </div>
            </div>
            
            <ul class="dashboard-nav">
                <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="book-space.php"><i class="fas fa-parking"></i> Book Parking</a></li>
                <li><a href="bookings.php"><i class="fas fa-history"></i> Booking History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-cog"></i> My Profile</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/index.php"><i class="fas fa-user-shield"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="#" id="delete-account-btn" style="color: white;"><i class="fas fa-user-times"></i> Delete Account</a></li>
                <li><a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Welcome, <?php echo $user['username']; ?></h1>
                <a href="book-space.php" class="btn btn-primary">
                    + New Booking
                </a>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>Total Bookings</h3>
                        <p class="stat-value"><?php echo $user_stats['total_bookings']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>Total Spent</h3>
                        <p class="stat-value"><?php echo formatCurrency($user_stats['total_spent']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>Last Booking</h3>
                        <p class="stat-value">
                            <?php 
                            if ($user_stats['last_booking']) {
                                echo formatDateTime($user_stats['last_booking'], 'M d, Y h:i A');
                            } else {
                                echo 'None yet';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3>Active Bookings</h3>
                        <p class="stat-value"><?php echo $active_bookings->num_rows; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-section">
                <h2>Current Bookings</h2>
                
                <?php if ($active_bookings->num_rows > 0): ?>
                    <div class="booking-cards">
                        <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div class="booking-space">
                                        <h3>Space <?php echo $booking['space_number']; ?></h3>
                                        <span class="booking-type">
                                            <?php echo ucfirst($booking['space_type']); ?>
                                        </span>
                                    </div>
                                    <div class="booking-actions">
                                        <form action="" method="POST" class="cancel-form">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn-danger" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="booking-detail">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <span class="detail-label">Entry Time</span>
                                            <span class="detail-value"><?php echo formatDateTime($booking['entry_time'], 'M d, Y h:i A'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <i class="fas fa-calendar-times"></i>
                                        <div>
                                            <span class="detail-label">Exit Time</span>
                                            <span class="detail-value"><?php echo $booking['exit_time'] ? formatDateTime($booking['exit_time'], 'M d, Y h:i A') : 'In Progress'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <i class="fas fa-car"></i>
                                        <div>
                                            <span class="detail-label">Vehicle</span>
                                            <span class="detail-value"><?php echo $booking['vehicle_number']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <i class="fas fa-rupee-sign"></i>
                                        <div>
                                            <span class="detail-label">Amount</span>
                                            <span class="detail-value"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="booking-footer">
                                    <div class="booking-status">
                                        <span class="status-badge status-active">Active</span>
                                    </div>
                                    <div class="booking-id">
                                        Booking ID: <?php echo $booking['id']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-parking"></i>
                        <h3>No Active Bookings</h3>
                        <p>You don't have any active parking bookings.</p>
                        <a href="book-space.php" class="btn btn-primary">Book Parking Now</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Booking History</h2>
                    <a href="bookings.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                
                <?php if ($past_bookings->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Space</th>
                                    <th>Vehicle</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $past_bookings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $booking['id']; ?></td>
                                        <td><?php echo $booking['space_number']; ?></td>
                                        <td><?php echo $booking['vehicle_number']; ?></td>
                                        <td><?php echo formatDateTime($booking['entry_time']); ?></td>
                                        <td><?php echo formatDateTime($booking['exit_time']); ?></td>
                                        <td>
                                            <?php 
                                            $duration = calculateDuration($booking['entry_time'], $booking['exit_time']);
                                            echo $duration . ' hr' . ($duration != 1 ? 's' : '');
                                            ?>
                                        </td>
                                        <td><?php echo formatCurrency($booking['amount_paid']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-history"></i>
                        <h3>No Booking History</h3>
                        <p>You haven't made any bookings yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal" id="deleteAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Account</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Warning:</strong> This action cannot be undone. All your data including booking history will be permanently deleted.</p>
                <form id="delete-account-form" method="POST" action="">
                    <div class="form-group">
                        <label for="password">Enter your password to confirm:</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <input type="hidden" name="delete_account" value="1">
                    <div class="form-group">
                        <button type="submit" class="btn btn-danger">Delete My Account Permanently</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Modal functionality for account deletion
        document.addEventListener('DOMContentLoaded', function() {
            const deleteAccountBtn = document.getElementById('delete-account-btn');
            const deleteAccountModal = document.getElementById('deleteAccountModal');
            const closeModal = document.querySelector('.close-modal');
            
            deleteAccountBtn.addEventListener('click', function(e) {
                e.preventDefault();
                deleteAccountModal.style.display = 'flex';
            });
            
            closeModal.addEventListener('click', function() {
                deleteAccountModal.style.display = 'none';
            });
            
            window.addEventListener('click', function(e) {
                if (e.target === deleteAccountModal) {
                    deleteAccountModal.style.display = 'none';
                }
            });
            
            // Add form validation
            const deleteForm = document.getElementById('delete-account-form');
            deleteForm.addEventListener('submit', function(e) {
                if (!confirm('Are you absolutely sure you want to delete your account? This action CANNOT be undone!')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
