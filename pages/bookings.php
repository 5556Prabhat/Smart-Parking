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

// Get filter parameters
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filter_date = isset($_GET['date_range']) ? sanitizeInput($_GET['date_range']) : '';
$sort_by = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';

// Build query conditions
$where_clauses = ["b.user_id = $user_id"];
$order_clause = "b.entry_time DESC"; // Default sorting

if (!empty($filter_status)) {
    if ($filter_status == 'active') {
        $where_clauses[] = "(b.exit_time IS NULL OR b.exit_time > NOW())";
    } elseif ($filter_status == 'completed') {
        $where_clauses[] = "b.exit_time IS NOT NULL AND b.exit_time <= NOW()";
    } elseif ($filter_status == 'cancelled') {
        $where_clauses[] = "b.payment_status = 'failed'";
    }
}

if (!empty($filter_date)) {
    if ($filter_date == 'today') {
        $where_clauses[] = "DATE(b.entry_time) = CURDATE()";
    } elseif ($filter_date == 'week') {
        $where_clauses[] = "b.entry_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
    } elseif ($filter_date == 'month') {
        $where_clauses[] = "b.entry_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
}

// Sorting
if ($sort_by == 'oldest') {
    $order_clause = "b.entry_time ASC";
} elseif ($sort_by == 'price_high') {
    $order_clause = "b.amount_paid DESC";
} elseif ($sort_by == 'price_low') {
    $order_clause = "b.amount_paid ASC";
}

// Combine all conditions
$where_clause = implode(" AND ", $where_clauses);

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) AS total FROM bookings b WHERE $where_clause";
$count_result = $conn->query($count_sql);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get bookings with pagination
$sql = "SELECT b.*, ps.space_number, ps.space_type 
        FROM bookings b
        JOIN parking_spaces ps ON b.space_id = ps.id
        WHERE $where_clause
        ORDER BY $order_clause
        LIMIT $offset, $items_per_page";
$result = $conn->query($sql);

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Verify booking belongs to user
    $check_sql = "SELECT space_id FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows === 1) {
        $booking = $check_result->fetch_assoc();
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
            
            // Refresh the page to update the booking list
            header("Location: bookings.php?status=$filter_status&date_range=$filter_date&sort=$sort_by&page=$page");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error cancelling booking: " . $e->getMessage();
        }
    } else {
        $error = "Invalid booking or permission denied";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <h3><?php echo $_SESSION['username']; ?></h3>
                    <p>Member</p>
                </div>
            </div>
            
            <ul class="dashboard-nav">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="book-space.php"><i class="fas fa-parking"></i> Book Parking</a></li>
                <li><a href="bookings.php" class="active"><i class="fas fa-history"></i> Booking History</a></li>
                <li><a href="profile.php"><i class="fas fa-user-cog"></i> My Profile</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/index.php"><i class="fas fa-user-shield"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>Booking History</h1>
                <a href="book-space.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Booking
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
            
            <div class="filter-bar">
                <form action="" method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="">All Bookings</option>
                            <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_range">Date Range</label>
                        <select id="date_range" name="date_range" onchange="this.form.submit()">
                            <option value="">All Time</option>
                            <option value="today" <?php echo ($filter_date == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo ($filter_date == 'week') ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo ($filter_date == 'month') ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sort_by == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="page" value="1">
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="bookings.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
            
            <div class="booking-summary">
                <div class="summary-item">
                    <div class="summary-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Bookings</h3>
                        <p><?php echo $total_items; ?></p>
                    </div>
                </div>
                
                <?php
                // Get active bookings count
                $active_sql = "SELECT COUNT(*) AS count FROM bookings WHERE user_id = $user_id AND (exit_time IS NULL OR exit_time > NOW())";
                $active_count = $conn->query($active_sql)->fetch_assoc()['count'];
                
                // Get completed bookings count
                $completed_sql = "SELECT COUNT(*) AS count FROM bookings WHERE user_id = $user_id AND exit_time IS NOT NULL AND exit_time <= NOW() AND payment_status = 'completed'";
                $completed_count = $conn->query($completed_sql)->fetch_assoc()['count'];
                
                // Get total spent
                $spent_sql = "SELECT SUM(amount_paid) AS total FROM bookings WHERE user_id = $user_id AND payment_status = 'completed'";
                $total_spent = $conn->query($spent_sql)->fetch_assoc()['total'] ?? 0;
                ?>
                
                <div class="summary-item">
                    <div class="summary-icon active">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Active Bookings</h3>
                        <p><?php echo $active_count; ?></p>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Completed</h3>
                        <p><?php echo $completed_count; ?></p>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Spent</h3>
                        <p><?php echo formatCurrency($total_spent); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="bookings-list">
                    <?php while ($booking = $result->fetch_assoc()): 
                        $is_active = ($booking['exit_time'] == NULL || strtotime($booking['exit_time']) > time());
                        $booking_status = $is_active ? 'active' : 'completed';
                        if ($booking['payment_status'] === 'failed') {
                            $booking_status = 'cancelled';
                        }
                    ?>
                        <div class="booking-item <?php echo $booking_status; ?>">
                            <div class="booking-header">
                                <div class="booking-id">
                                    <h3>Booking #<?php echo $booking['id']; ?></h3>
                                    <span class="date"><?php echo formatDateTime($booking['entry_time'], 'M d, Y'); ?></span>
                                </div>
                                <div class="booking-status">
                                    <?php if ($booking_status === 'active'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php elseif ($booking_status === 'completed'): ?>
                                        <span class="status-badge status-completed">Completed</span>
                                    <?php else: ?>
                                        <span class="status-badge status-failed">Cancelled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="booking-detail-row">
                                    <div class="booking-detail">
                                        <span class="detail-label">Space</span>
                                        <span class="detail-value"><?php echo $booking['space_number']; ?> (<?php echo getSpaceTypeLabel($booking['space_type']); ?>)</span>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <span class="detail-label">Vehicle</span>
                                        <span class="detail-value"><?php echo $booking['vehicle_number']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="booking-detail-row">
                                    <div class="booking-detail">
                                        <span class="detail-label">Entry Time</span>
                                        <span class="detail-value"><?php echo formatDateTime($booking['entry_time']); ?></span>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <span class="detail-label">Exit Time</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($booking['exit_time']) {
                                                echo formatDateTime($booking['exit_time']);
                                            } else {
                                                echo '<span class="in-progress">In Progress</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="booking-detail-row">
                                    <div class="booking-detail">
                                        <span class="detail-label">Duration</span>
                                        <span class="detail-value">
                                            <?php 
                                            $exit_time = $booking['exit_time'] ?? date('Y-m-d H:i:s');
                                            $duration = calculateDuration($booking['entry_time'], $exit_time);
                                            echo $duration . ' hr' . ($duration != 1 ? 's' : '');
                                            if (!$booking['exit_time']) echo ' (ongoing)';
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="booking-detail">
                                        <span class="detail-label">Amount</span>
                                        <span class="detail-value"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
                                <?php if ($is_active): ?>
                                    <form action="" method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking?')">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" name="cancel_booking" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Cancel Booking
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="book-space.php" class="btn btn-primary">
                                        <i class="fas fa-redo"></i> Book Again
                                    </a>
                                <?php endif; ?>
                                
                                <a href="#" class="btn btn-secondary" onclick="printReceipt(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-print"></i> Receipt
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?status=<?php echo $filter_status; ?>&date_range=<?php echo $filter_date; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $page-1; ?>">
                                &laquo; Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?status=<?php echo $filter_status; ?>&date_range=<?php echo $filter_date; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $i; ?>" 
                               class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?status=<?php echo $filter_status; ?>&date_range=<?php echo $filter_date; ?>&sort=<?php echo $sort_by; ?>&page=<?php echo $page+1; ?>">
                                Next &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Bookings Found</h3>
                    <p>You haven't made any bookings that match your filter criteria.</p>
                    <a href="book-space.php" class="btn btn-primary">Book Parking Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal" id="receiptModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Parking Receipt</h2>
                <span class="close" onclick="closeReceiptModal()">&times;</span>
            </div>
            <div class="modal-body" id="receipt-content">
                <!-- Receipt content will be populated here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="printReceiptContent()">Print Receipt</button>
                <button class="btn btn-secondary" onclick="closeReceiptModal()">Close</button>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Receipt functionality
        const receiptModal = document.getElementById('receiptModal');
        
        function printReceipt(bookingId) {
            // This would normally be an AJAX call to get receipt data
            // For demo purposes, we'll create it directly with the data we have
            fetch(`../includes/get_receipt.php?booking_id=${bookingId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('receipt-content').innerHTML = html;
                    receiptModal.classList.add('show-modal');
                })
                .catch(error => {
                    console.error('Error fetching receipt:', error);
                    alert('Failed to load receipt. Please try again.');
                });
        }
        
        function closeReceiptModal() {
            receiptModal.classList.remove('show-modal');
        }
        
        function printReceiptContent() {
            const printWindow = window.open('', '_blank');
            const receiptContent = document.getElementById('receipt-content').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Parking Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .receipt { max-width: 800px; margin: 0 auto; }
                        .receipt-header { text-align: center; margin-bottom: 20px; }
                        .receipt-info { margin-bottom: 20px; }
                        .receipt-table { width: 100%; border-collapse: collapse; }
                        .receipt-table th, .receipt-table td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
                        .receipt-total { margin-top: 20px; text-align: right; font-weight: bold; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt">
                        ${receiptContent}
                    </div>
                    <div class="no-print" style="text-align: center; margin-top: 30px;">
                        <button onclick="window.print()">Print</button>
                        <button onclick="window.close()">Close</button>
                    </div>
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                            }, 500);
                        }
                    </script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === receiptModal) {
                closeReceiptModal();
            }
        };
    </script>
</body>
</html>
