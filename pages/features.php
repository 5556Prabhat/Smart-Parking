<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Smart Parking Features</h1>
            <p>Discover what makes our parking management solution stand out</p>
        </div>
    </section>

    <section class="features-showcase">
        <div class="container">
            <div class="showcase-grid">
                <div class="showcase-content">
                    <h2>Real-time Space Management</h2>
                    <p>Our system provides instant visibility of parking space availability, helping users find parking quickly and efficiently.</p>
                    
                    <ul class="feature-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Live monitoring of parking space status</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Color-coded space indicators for easy identification</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Historical usage data for space optimization</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Automated space assignment algorithms</span>
                        </li>
                    </ul>
                </div>
                
                <div class="showcase-image">
                    <img src="../assets/images/space-management-software.webp" alt="Space Management">
                </div>
            </div>
        </div>
    </section>
    
    <section class="features-showcase alt">
        <div class="container">
            <div class="showcase-grid reverse">
                <div class="showcase-content">
                    <h2>Mobile Integration</h2>
                    <p>Access the parking system from any device with our responsive design and dedicated mobile experience.</p>
                    
                    <ul class="feature-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Responsive design works on all devices</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Book parking spaces on the go</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Receive real-time notifications</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Digital parking tickets and receipts</span>
                        </li>
                    </ul>
                </div>
                
                <div class="showcase-image">
                    <img src="../assets/images/mobile integration.png" alt="Mobile Integration">
                </div>
            </div>
        </div>
    </section>
    
    <section class="features-showcase">
        <div class="container">
            <div class="showcase-grid">
                <div class="showcase-content">
                    <h2>Comprehensive Analytics</h2>
                    <p>Make data-driven decisions with detailed reports and insights on parking usage and revenue.</p>
                    
                    <ul class="feature-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Customizable dashboard with key metrics</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Revenue tracking and forecasting</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Usage patterns and peak time analysis</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Exportable reports for business planning</span>
                        </li>
                    </ul>
                </div>
                
                <div class="showcase-image">
                    <img src="../assets/images/comprehensive image.webp" alt="Analytics Dashboard">
                </div>
            </div>
        </div>
    </section>
    
    <section class="feature-grid-section">
        <div class="container">
            <h2 class="section-title">More Powerful Features</h2>
            <p class="section-subtitle">Everything you need for efficient parking management</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon purple">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Flexible Payment Options</h3>
                    <p>Support multiple payment methods including credit cards, mobile payments, and pre-paid parking credits.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon red">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Time-Based Pricing</h3>
                    <p>Implement dynamic pricing based on time of day, demand levels, and special events in your area.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon teal">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h3>Reserved Parking</h3>
                    <p>Allow users to reserve parking spots in advance, guaranteeing availability when they arrive.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon orange">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Smart Notifications</h3>
                    <p>Automated alerts for booking confirmations, expiring sessions, and available spaces.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon green">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Admin Controls</h3>
                    <p>Comprehensive backend for managers to oversee operations, adjust pricing, and manage users.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon blue">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Automated Reporting</h3>
                    <p>Schedule and generate custom reports to monitor performance and optimize operations.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to transform your parking operations?</h2>
                <p>Start using our smart parking solution today and see the difference it makes.</p>
                <div class="cta-buttons">
                    <a href="pricing.php" class="btn btn-primary">View Pricing</a>
                    <a href="#contact" class="btn btn-secondary">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
