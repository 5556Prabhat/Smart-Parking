<?php

 


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Redirect to a URL
function redirect($url) {
    header("Location: $url");
    exit();
}

// Format currency
function formatCurrency($amount) {
    // Change from $ to ₹
    return '₹' . number_format($amount, 2);
}

// Format date and time
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Calculate parking duration in hours
function calculateDuration($entry, $exit) {
    if (!$entry || !$exit) return 0;
    $entry_time = strtotime($entry);
    $exit_time = strtotime($exit);
    $duration_seconds = $exit_time - $entry_time;
    return round($duration_seconds / 3600, 2); // Convert to hours
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get all parking space types for dropdowns
function getSpaceTypes() {
    return ['standard', 'premium', 'reserved'];
}

// Get all space statuses for dropdowns
function getSpaceStatuses() {
    return ['available', 'occupied', 'maintenance'];
}

// Generate a booking reference number
function generateBookingReference() {
    return 'PK' . strtoupper(substr(uniqid(), -6));
}

// Get space availability percentage
function getAvailabilityPercentage($conn) {
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
            FROM parking_spaces";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $total = $row['total'];
        $available = $row['available'];
        return ($total > 0) ? round(($available / $total) * 100) : 0;
    }
    return 0;
}

// Get total earnings for a given period
function getTotalEarnings($conn, $period = 'all') {
    $where = "";
    
    if ($period == 'today') {
        $where = "WHERE DATE(entry_time) = CURDATE()";
    } elseif ($period == 'week') {
        $where = "WHERE entry_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
    } elseif ($period == 'month') {
        $where = "WHERE entry_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
    
    $sql = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM bookings 
            $where AND payment_status = 'completed'";
    
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['total'];
    }
    return 0;
}

// Get count of bookings for a given period
function getBookingCount($conn, $period = 'all') {
    $where = "";
    
    if ($period == 'today') {
        $where = "WHERE DATE(entry_time) = CURDATE()";
    } elseif ($period == 'week') {
        $where = "WHERE entry_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
    } elseif ($period == 'month') {
        $where = "WHERE entry_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
    
    $sql = "SELECT COUNT(*) as count FROM bookings $where";
    
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['count'];
    }
    return 0;
}

// Create pagination links
function createPagination($current_page, $total_pages, $base_url) {
    $pagination = '';
    
    if ($total_pages > 1) {
        $pagination .= '<div class="pagination">';
        
        if ($current_page > 1) {
            $pagination .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . '">&laquo; Previous</a>';
        }
        
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);
        
        if ($start_page > 1) {
            $pagination .= '<a href="' . $base_url . '?page=1">1</a>';
            if ($start_page > 2) {
                $pagination .= '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                $pagination .= '<a class="active">' . $i . '</a>';
            } else {
                $pagination .= '<a href="' . $base_url . '?page=' . $i . '">' . $i . '</a>';
            }
        }
        
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $pagination .= '<span class="pagination-ellipsis">...</span>';
            }
            $pagination .= '<a href="' . $base_url . '?page=' . $total_pages . '">' . $total_pages . '</a>';
        }
        
        if ($current_page < $total_pages) {
            $pagination .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . '">Next &raquo;</a>';
        }
        
        $pagination .= '</div>';
    }
    
    return $pagination;
}

// Log system activity
function logActivity($conn, $user_id, $action, $description) {
    // Check if activity_log table exists
    $check_table = "SHOW TABLES LIKE 'activity_log'";
    $result = $conn->query($check_table);
    
    if ($result->num_rows == 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE activity_log (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($create_table);
    }
    
    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $user_id, $action, $description, $ip);
    return $stmt->execute();
}

// Get human-readable space type
function getSpaceTypeLabel($type) {
    $types = [
        'standard' => 'Standard',
        'premium' => 'Premium',
        'reserved' => 'Reserved'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

// Get a status label with appropriate CSS class
function getStatusLabel($status) {
    $labels = [
        'available' => '<span class="status-badge status-available">Available</span>',
        'occupied' => '<span class="status-badge status-occupied">Occupied</span>',
        'maintenance' => '<span class="status-badge status-maintenance">Maintenance</span>',
        'pending' => '<span class="status-badge status-pending">Pending</span>',
        'completed' => '<span class="status-badge status-completed">Completed</span>',
        'failed' => '<span class="status-badge status-failed">Failed</span>',
        'active' => '<span class="status-badge status-active">Active</span>'
    ];
    
    return $labels[$status] ?? '<span class="status-badge">' . ucfirst($status) . '</span>';
}

// Get space utilization metrics
function getSpaceUtilization($conn) {
    $sql = "SELECT 
            space_type,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
            FROM parking_spaces
            GROUP BY space_type";
    
    $result = $conn->query($sql);
    $utilization = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $utilization[$row['space_type']] = $row;
        }
    }
    
    return $utilization;
}

// Calculate parking fee based on hours and space type
function calculateParkingFee($conn, $space_id, $hours) {
    $sql = "SELECT hourly_rate FROM parking_spaces WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $space_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return round($row['hourly_rate'] * $hours, 2);
    }
    
    return 0; // Default if space not found
}

// Check if a table exists in the database
function tableExists($conn, $table_name) {
    $check_table = "SHOW TABLES LIKE '$table_name'";
    $result = $conn->query($check_table);
    return $result->num_rows > 0;
}

// Check if a specific column exists in a table
function columnExists($conn, $table_name, $column_name) {
    $check_column = "SHOW COLUMNS FROM `$table_name` LIKE '$column_name'";
    $result = $conn->query($check_column);
    return $result->num_rows > 0;
}

// Generate a random password
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $max)];
    }
    
    return $password;
}

// Get user's role name
function getUserRole($is_admin) {
    return $is_admin ? 'Administrator' : 'Regular User';
}

// Format bytes to human-readable format
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get custom settings from database
function getSetting($conn, $setting_name, $default = '') {
    if (!tableExists($conn, 'settings')) {
        // Create settings table if it doesn't exist
        $conn->query("CREATE TABLE settings (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        return $default;
    }
    
    $sql = "SELECT setting_value FROM settings WHERE setting_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $setting_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    
    return $default;
}

// Save a setting to database
function saveSetting($conn, $setting_name, $setting_value) {
    if (!tableExists($conn, 'settings')) {
        // Create settings table if it doesn't exist
        $conn->query("CREATE TABLE settings (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    $sql = "INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $setting_name, $setting_value);
    return $stmt->execute();
}
/**
 * Send password reset email
 * 
 * @param string $email Recipient email address
 * @param string $username Recipient username
 * @param string $reset_url Password reset URL
 * @return bool Whether the email was sent successfully
 */
function sendPasswordResetEmail($email, $username, $reset_url) {
    $subject = "Password Reset - ParkSmart";
    
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .logo { text-align: center; margin-bottom: 20px; }
            .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; }
            .button { display: inline-block; padding: 10px 20px; background-color: #1a73e8; color: #ffffff; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='logo'>
                <h1>ParkSmart</h1>
            </div>
            <div class='content'>
                <h2>Password Reset Request</h2>
                <p>Hello {$username},</p>
                <p>We received a request to reset your password. If you didn't make this request, you can ignore this email.</p>
                <p>To reset your password, click the button below:</p>
                <p style='text-align: center;'>
                    <a class='button' href='{$reset_url}'>Reset Password</a>
                </p>
                <p>Or copy and paste the following URL into your browser:</p>
                <p>{$reset_url}</p>
                <p>This link will expire in 1 hour.</p>
            </div>
            <div class='footer'>
                <p>ParkSmart - Smart Parking Management System</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ParkSmart <noreply@parksmart.com>" . "\r\n";
    
    
    return mail($email, $subject, $message, $headers);
}

?>
