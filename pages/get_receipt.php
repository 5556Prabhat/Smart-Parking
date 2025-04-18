<?php
require_once 'db_connect.php';
require_once 'functions.php';
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    exit('Access denied');
}

// Get booking ID
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = $_SESSION['user_id'];

// Verify booking belongs to user
$sql = "SELECT b.*, ps.space_number, ps.space_type, ps.hourly_rate, u.username, u.email
        FROM bookings b
        JOIN parking_spaces ps ON b.space_id = ps.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit('Booking not found or access denied');
}

$booking = $result->fetch_assoc();

// Calculate duration
$entry_time = strtotime($booking['entry_time']);
$exit_time = $booking['exit_time'] ? strtotime($booking['exit_time']) : time();
$duration_seconds = $exit_time - $entry_time;
$duration_hours = round($duration_seconds / 3600, 2);

// Generate receipt number
$receipt_number = 'PK' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT);

// Format dates
$entry_date = date('F j, Y', $entry_time);
$entry_time_str = date('g:i A', $entry_time);
$exit_date = date('F j, Y', $exit_time);
$exit_time_str = date('g:i A', $exit_time);
$issue_date = date('F j, Y h:i A');

// Output receipt HTML
?>

<div class="receipt-content">
    <div class="receipt-header">
        <h2>PARKING RECEIPT</h2>
        <p>ParkSmart Parking Management System</p>
    </div>
    
    <div class="receipt-info">
        <div class="receipt-row">
            <div class="receipt-col">
                <strong>Receipt #:</strong> <?php echo $receipt_number; ?>
            </div>
            <div class="receipt-col">
                <strong>Date Issued:</strong> <?php echo $issue_date; ?>
            </div>
        </div>
        
        <div class="receipt-row">
            <div class="receipt-col">
                <strong>User:</strong> <?php echo $booking['username']; ?>
            </div>
            <div class="receipt-col">
                <strong>Email:</strong> <?php echo $booking['email']; ?>
            </div>
        </div>
    </div>
    
    <div class="receipt-details">
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Parking Space</th>
                    <th>Vehicle</th>
                    <th>Entry Date/Time</th>
                    <th>Exit Date/Time</th>
                    <th>Duration</th>
                    <th>Rate</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $booking['space_number']; ?> (<?php echo getSpaceTypeLabel($booking['space_type']); ?>)</td>
                    <td><?php echo $booking['vehicle_number']; ?></td>
                    <td><?php echo $entry_date; ?><br><?php echo $entry_time_str; ?></td>
                    <td>
                        <?php if ($booking['exit_time']): ?>
                            <?php echo $exit_date; ?><br><?php echo $exit_time_str; ?>
                        <?php else: ?>
                            <em>In Progress</em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $duration_hours; ?> hrs</td>
                    <td><?php echo formatCurrency($booking['hourly_rate']); ?>/hr</td>
                    <td><?php echo formatCurrency($booking['amount_paid']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="receipt-summary">
        <div class="receipt-total">
            <table>
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td class="amount"><?php echo formatCurrency($booking['amount_paid']); ?></td>
                </tr>
                <tr>
                    <td><strong>Tax:</strong></td>
                    <td class="amount">$0.00</td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total Amount:</strong></td>
                    <td class="amount"><?php echo formatCurrency($booking['amount_paid']); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Status:</strong></td>
                    <td>
                        <?php if ($booking['payment_status'] === 'completed'): ?>
                            <span class="status-paid">PAID</span>
                        <?php else: ?>
                            <span class="status-pending"><?php echo strtoupper($booking['payment_status']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="receipt-footer">
        <p>Thank you for using ParkSmart Parking Management System!</p>
        <p class="small">This is a computer-generated receipt and does not require a signature.</p>
    </div>
</div>
