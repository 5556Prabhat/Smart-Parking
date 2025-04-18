<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Process booking form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_number = $_POST['vehicle-number'] ?? '';
    $space_id = $_POST['space-id'] ?? '';
    $hours = $_POST['hours'] ?? 1;
    
    // Basic validation
    if (empty($vehicle_number) || empty($space_id) || $hours < 1) {
        $error = "All fields are required and hours must be at least 1";
    } else {
        // Convert hours to exit time
        $entry_time = date('Y-m-d H:i:s');
        $exit_time = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        
        // Get space hourly rate
        $spaceQuery = "SELECT hourly_rate, space_type FROM parking_spaces WHERE id = ?";
        $stmt = $conn->prepare($spaceQuery);
        $stmt->bind_param("i", $space_id);
        $stmt->execute();
        $spaceResult = $stmt->get_result();
        
        if ($spaceRow = $spaceResult->fetch_assoc()) {
            // Apply the new rates if the database hasn't been updated yet
            if ($spaceRow['space_type'] == 'standard' && $spaceRow['hourly_rate'] == 2) {
                $hourly_rate = 79;
            } else if ($spaceRow['space_type'] == 'premium' && $spaceRow['hourly_rate'] == 4) {
                $hourly_rate = 99;
            } else if ($spaceRow['space_type'] == 'reserved' && $spaceRow['hourly_rate'] == 6) {
                $hourly_rate = 149;
            } else {
                $hourly_rate = $spaceRow['hourly_rate'];
            }
            
            $amount = $hourly_rate * $hours;
            
            // Create booking
            $bookingQuery = "INSERT INTO bookings (user_id, space_id, vehicle_number, entry_time, exit_time, amount_paid, payment_status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'completed')";
            
            $stmt = $conn->prepare($bookingQuery);
            $user_id = $_SESSION['user_id'] ?? 1; // Guest user if not logged in
            $stmt->bind_param("iisssd", $user_id, $space_id, $vehicle_number, $entry_time, $exit_time, $amount);
            
            if ($stmt->execute()) {
                // Update space status
                $updateSpace = "UPDATE parking_spaces SET status = 'occupied' WHERE id = ?";
                $stmt = $conn->prepare($updateSpace);
                $stmt->bind_param("i", $space_id);
                $stmt->execute();
                
                $success = "Booking successful! Your parking space is reserved.";
            } else {
                $error = "Error creating booking: " . $conn->error;
            }
        } else {
            $error = "Invalid parking space selected";
        }
    }
}

// Get available parking spaces
$spacesQuery = "SELECT id, space_number, space_type, hourly_rate FROM parking_spaces WHERE status = 'available' ORDER BY space_type, space_number";
$spacesResult = $conn->query($spacesQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Parking Space - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Book a Parking Space</h1>
            <p>Reserve your parking spot in advance and avoid the hassle</p>
        </div>
    </section>

    <section class="booking-section">
        <div class="container">
            <div class="booking-grid">
                <div class="booking-form-container">
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
                    
                    <form action="" method="POST" class="booking-form" id="booking-form">
                        <h2>Enter Booking Details</h2>
                        
                        <div class="form-group">
                            <label for="vehicle-number">Vehicle License Plate</label>
                            <input type="text" id="vehicle-number" name="vehicle-number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="space-id">Select Parking Space</label>
                            <select id="space-id" name="space-id" required onchange="calculateCost()">
                                <option value="">-- Select a space --</option>
                                <?php if($spacesResult && $spacesResult->num_rows > 0): ?>
                                    <?php while($space = $spacesResult->fetch_assoc()): ?>
                                        <?php
                                        // Display new rates in the dropdown if database hasn't been updated
                                        $display_rate = $space['hourly_rate'];
                                        if ($space['space_type'] == 'standard' && $display_rate == 2) {
                                            $display_rate = 79;
                                        } else if ($space['space_type'] == 'premium' && $display_rate == 4) {
                                            $display_rate = 99;
                                        } else if ($space['space_type'] == 'reserved' && $display_rate == 6) {
                                            $display_rate = 149;
                                        }
                                        ?>
                                        <option value="<?php echo $space['id']; ?>" data-rate="<?php echo $display_rate; ?>" data-type="<?php echo $space['space_type']; ?>">
                                            <?php echo $space['space_number']; ?> (<?php echo ucfirst($space['space_type']); ?> - ₹<?php echo $display_rate; ?>/hr)
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>No spaces available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="hours">Duration (hours)</label>
                            <input type="number" id="hours" name="hours" min="1" max="24" value="1" required onchange="calculateCost()">
                        </div>
                        
                        <div class="cost-calculator">
                            <h3>Total Cost: <span id="cost-display">₹0.00</span></h3>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Reserve Now</button>
                    </form>
                </div>
                
                <div class="booking-info">
                    <h2>How It Works</h2>
                    
                    <div class="steps">
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="step-content">
                                <h3>Enter Vehicle Details</h3>
                                <p>Provide your vehicle's license plate number.</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="step-content">
                                <h3>Choose Your Space</h3>
                                <p>Select from available parking spaces based on your preferences.</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="step-content">
                                <h3>Set Duration</h3>
                                <p>Choose how long you'll need the parking space.</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-content">
                                <h3>Confirm and Pay</h3>
                                <p>Complete your booking and receive a confirmation.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notes">
                        <h3>Important Notes:</h3>
                        <ul>
                            <li>Arrive within 15 minutes of your booking time</li>
                            <li>Cancellations must be made at least 1 hour in advance for a full refund</li>
                            <li>Extended stays are charged at standard hourly rates</li>
                            <li>Your space is guaranteed for the duration of your booking</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function calculateCost() {
            const spaceSelect = document.getElementById('space-id');
            const hours = document.getElementById('hours').value;
            const costDisplay = document.getElementById('cost-display');
            
            if (spaceSelect.value) {
                const rate = spaceSelect.options[spaceSelect.selectedIndex].getAttribute('data-rate');
                const spaceType = spaceSelect.options[spaceSelect.selectedIndex].getAttribute('data-type');
                
                // Use new rates for calculation if needed
                let displayRate = parseFloat(rate);
                
                const totalCost = (displayRate * hours).toFixed(2);
                costDisplay.textContent = `₹${totalCost}`;
            } else {
                costDisplay.textContent = '₹0.00';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            calculateCost();
        });
    </script>
</body>
</html>
