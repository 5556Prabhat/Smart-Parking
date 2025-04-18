<?php
/**
 * Logout Script
 * Handles user logout by destroying the session and redirecting to the login page
 */

// Start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database and functions files for logging the logout
require_once 'db_connect.php';
require_once 'functions.php';

// Log the logout action if user was logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    
    // Log the logout activity
    if (function_exists('logActivity')) {
        logActivity($conn, $user_id, 'logout', "User $username logged out");
    }
}

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy that too
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a logout message
$logout_message = urlencode("You have been successfully logged out.");

// Redirect to login page with a message
header("Location: ../pages/login.php?message=$logout_message");
exit();
?>
