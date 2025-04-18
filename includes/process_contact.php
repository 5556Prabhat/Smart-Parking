<?php
require_once 'db_connect.php';
require_once 'functions.php';


$response = [
    'success' => false,
    'message' => 'An error occurred while processing your request.'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($message)) {
        $response['message'] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
    } else {
        
        $sql = "INSERT INTO contact_messages (name, email, message, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        
        $check_table = $conn->query("SHOW TABLES LIKE 'contact_messages'");
        if ($check_table->num_rows == 0) {
            $create_table = "CREATE TABLE contact_messages (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) DEFAULT 0
            )";
            $conn->query($create_table);
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $email, $message);
        
        if ($stmt->execute()) {
            // Send email notification (optional)
            $to = "info@parksmart.com"; // Replace with your email
            $subject = "New Contact Form Submission";
            $email_message = "Name: $name\n";
            $email_message .= "Email: $email\n\n";
            $email_message .= "Message:\n$message";
            $headers = "From: $email";
            
            // Uncomment to enable email sending
            // mail($to, $subject, $email_message, $headers);
            
            $response['success'] = true;
            $response['message'] = 'Your message has been sent successfully. We will get back to you soon!';
        }
    }
}

// Redirect back to the contact form with appropriate message
$status = $response['success'] ? 'success' : 'error';
$redirect_url = "../index.php?contact_status={$status}&message=" . urlencode($response['message']) . "#contact";
header("Location: $redirect_url");
exit;
?>
