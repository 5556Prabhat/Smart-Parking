<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkSmart - Cost Effective Parking Solutions</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        
        .account-deleted-message {
            background-color: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            margin: 20px auto;
            max-width: 800px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php if(isset($_GET['message']) && $_GET['message'] == 'account_deleted'): ?>
    <div class="container">
        <div class="account-deleted-message">
            <i class="fas fa-check-circle"></i> Your account has been permanently deleted. We're sorry to see you go!
        </div>
    </div>
    <?php endif; ?>

    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Smart Parking Solutions <span>For Every Budget</span></h1>
                <p>Optimize your parking operation, reduce costs, and enhance user experience with our innovative and cost-effective parking management solution.</p>
                <div class="cta-buttons">
                    <a href="pages/book-space.php" class="btn btn-primary">Book Parking</a>
                    <a href="pages/pricing.php" class="btn btn-secondary">View Pricing</a>
                </div>
            </div>
            
            <div class="hero-image">
                <div class="availability-box">
                    <h3>Real-time Availability</h3>
                    <?php
                    
                    $sql = "SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
                           FROM parking_spaces";
                    $result = $conn->query($sql);
                    
                    if ($result && $row = $result->fetch_assoc()) {
                        $total = $row['total'];
                        $available = $row['available'];
                        $percentAvailable = ($total > 0) ? ($available / $total) * 100 : 0;
                        
                        echo "<div class='availability-meter'>";
                        echo "<div class='meter-fill' style='width: {$percentAvailable}%'></div>";
                        echo "</div>";
                        echo "<p><strong>{$available}/{$total}</strong> spaces available now</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h2 class="section-title">Powerful Features</h2>
            <p class="section-subtitle">Everything you need to effectively manage parking, at a fraction of the cost of traditional systems</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon blue">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Smart Space Management</h3>
                    <p>Track and optimize parking space utilization with real-time monitoring and automated space assignment.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon green">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobile Integration</h3>
                    <p>A responsive design allows users to check availability and book parking spots from any device.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Analytics Dashboard</h3>
                    <p>Comprehensive reports and insights to help optimize your parking operations and revenue.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon purple">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Flexible Payment Options</h3>
                    <p>Accept multiple payment methods and offer various pricing plans for different user needs.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon red">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Time-Based Pricing</h3>
                    <p>Implement dynamic pricing based on time of day, demand, and special events.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon teal">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <h3>Reserved Parking</h3>
                    <p>Allow users to reserve spots in advance, guaranteeing availability when they arrive.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="pricing">
        <div class="container">
            <h2 class="section-title">Simple, Transparent Pricing</h2>
            <p class="section-subtitle">Choose the best plan for your needs and budget</p>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Starter</h3>
                        <div class="price">
                            <span class="currency">₹</span>
                            <span class="amount">1,999</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Up to 50 parking spaces</li>
                            <li>Basic analytics</li>
                            <li>Email support</li>
                            <li>Mobile responsive</li>
                            <li>Payment processing</li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <a href="#" class="btn btn-outline">Start with Starter</a>
                    </div>
                </div>
                
                <div class="pricing-card featured">
                    <div class="ribbon">Most Popular</div>
                    <div class="pricing-header">
                        <h3>Professional</h3>
                        <div class="price">
                            <span class="currency">₹</span>
                            <span class="amount">2,999</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Up to 200 parking spaces</li>
                            <li>Advanced analytics</li>
                            <li>Priority support</li>
                            <li>SMS notifications</li>
                            <li>Admin dashboard</li>
                            <li>Multiple payment options</li>
                            <li>Booking management</li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <a href="#" class="btn btn-primary">Choose Professional</a>
                    </div>
                </div>
                
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Enterprise</h3>
                        <div class="price">
                            <span class="currency">₹</span>
                            <span class="amount">4,999</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    <div class="pricing-features">
                        <ul>
                            <li>Unlimited parking spaces</li>
                            <li>Custom analytics & reports</li>
                            <li>24/7 premium support</li>
                            <li>API integration</li>
                            <li>License plate recognition</li>
                            <li>Custom development</li>
                            <li>SLA guarantee</li>
                        </ul>
                    </div>
                    <div class="pricing-cta">
                        <a href="#" class="btn btn-outline">Contact Sales</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>Get in Touch</h2>
                    <p>Have questions about our parking solutions? Our team is ready to help!</p>
                    
                    <div class="contact-method">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:info@parksmart.com">info@parksmart.com</a>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-phone"></i>
                        <a href="tel:+1234567890">+91999999999</a>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>144401,Lovely Professional University<br>Phagwara</p>
                    </div>
                </div>
                
                <div class="contact-form">
                    <h2>Send us a message</h2>
                    <form action="includes/process_contact.php" method="POST">
                        <div class="form-group">
                            <label for="name">Your name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your message</label>
                            <textarea id="message" name="message" rows="4" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
