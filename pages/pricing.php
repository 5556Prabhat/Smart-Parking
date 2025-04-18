<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Create subscriptions table if it doesn't exist
$checkTable = $conn->query("SHOW TABLES LIKE 'subscriptions'");
if ($checkTable->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        plan_name VARCHAR(50) NOT NULL,
        plan_price DECIMAL(10,2) NOT NULL,
        duration_months INT NOT NULL,
        billing_cycle VARCHAR(20) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createTable)) {
        $error = "Error creating subscriptions table: " . $conn->error;
    }
}

// Handle plan subscription for logged-in users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_plan']) && $isLoggedIn) {
    $plan_name = sanitizeInput($_POST['plan_name']);
    $plan_price = floatval($_POST['plan_price']);
    $duration_months = intval($_POST['duration_months']);
    $billing_cycle = sanitizeInput($_POST['billing_cycle']);
    $user_id = $_SESSION['user_id'];
    
    // Calculate total amount with discount
    $discount = 0;
    if ($duration_months == 6) {
        $discount = 0.05; // 5% discount
    } else if ($duration_months == 12) {
        $discount = 0.10; // 10% discount
    }
    $total_amount = ($plan_price * $duration_months) * (1 - $discount);
    
    // Insert into subscriptions table
    $sql = "INSERT INTO subscriptions (user_id, plan_name, plan_price, duration_months, billing_cycle, total_amount, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH), 'active')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdisdi", $user_id, $plan_name, $plan_price, $duration_months, $billing_cycle, $total_amount, $duration_months);
    
    if ($stmt->execute()) {
        // Redirect to dashboard with success message
        $_SESSION['subscription_success'] = "You have successfully subscribed to the $plan_name plan!";
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Error processing subscription: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Modal styling for subscription form */
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
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: #fff;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .subscription-summary {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .plan-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .plan-price {
            font-size: 1.1rem;
            font-weight: 500;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .duration-selector {
            margin-bottom: 15px;
        }
        
        .duration-options {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        
        .duration-option {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .duration-option:hover {
            border-color: #3498db;
        }
        
        .duration-option.selected {
            border-color: #3498db;
            background-color: #ebf5fb;
        }
        
        .total-cost {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 15px 0;
            text-align: right;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Simple, Transparent Pricing</h1>
            <p>Choose the best plan for your parking management needs</p>
        </div>
    </section>

    <?php if(isset($error)): ?>
    <div class="container">
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    </div>
    <?php endif; ?>

    <section class="pricing-tabs">
        <div class="container">
            <div class="tab-container">
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('monthly')">Monthly Billing</button>
                    <button class="tab-btn" onclick="switchTab('yearly')">Annual Billing <span class="badge">Save 20%</span></button>
                </div>
                
                <div id="monthly" class="tab-content active">
                    <div class="pricing-grid">
                        <div class="pricing-card">
                            <div class="pricing-header">
                                <h3>Starter</h3>
                                <div class="price">
                                    <span class="currency">₹</span>
                                    <span class="amount">1,999</span>
                                    <span class="period">/month</span>
                                </div>
                                <p class="pricing-subtitle">Perfect for small parking lots</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Up to 50 parking spaces</li>
                                    <li>Basic analytics</li>
                                    <li>Email support</li>
                                    <li>Mobile responsive</li>
                                    <li>Payment processing</li>
                                    <li class="unavailable">Advanced reporting</li>
                                    <li class="unavailable">API access</li>
                                    <li class="unavailable">Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <?php if($isLoggedIn): ?>
                                <a href="#" class="btn btn-outline subscribe-btn" data-plan="Starter" data-price="1999" data-cycle="monthly">Get Started</a>
                                <?php else: ?>
                                <a href="register.php" class="btn btn-outline">Get Started</a>
                                <?php endif; ?>
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
                                <p class="pricing-subtitle">For growing operations</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Up to 200 parking spaces</li>
                                    <li>Advanced analytics</li>
                                    <li>Priority support</li>
                                    <li>SMS notifications</li>
                                    <li>Payment processing</li>
                                    <li>Advanced reporting</li>
                                    <li>API access</li>
                                    <li class="unavailable">Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <?php if($isLoggedIn): ?>
                                <a href="#" class="btn btn-primary subscribe-btn" data-plan="Professional" data-price="2999" data-cycle="monthly">Get Started</a>
                                <?php else: ?>
                                <a href="register.php" class="btn btn-primary">Get Started</a>
                                <?php endif; ?>
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
                                <p class="pricing-subtitle">For large-scale facilities</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Unlimited parking spaces</li>
                                    <li>Custom analytics</li>
                                    <li>24/7 dedicated support</li>
                                    <li>SMS & email notifications</li>
                                    <li>Payment processing</li>
                                    <li>Advanced reporting</li>
                                    <li>API access</li>
                                    <li>Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <a href="mailto:pandeyprabhat5556@gmail.com?subject=ParkSmart Enterprise Plan Inquiry&body=Hello,%0D%0A%0D%0AI'm interested in learning more about your Enterprise plan for our parking facility. Please provide more information about customization options and implementation details.%0D%0A%0D%0AThank you!" class="btn btn-outline" title="Contact our sales team via email">Contact Sales</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="yearly" class="tab-content">
                    <div class="pricing-grid">
                        <div class="pricing-card">
                            <div class="pricing-header">
                                <h3>Starter</h3>
                                <div class="price">
                                    <span class="currency">₹</span>
                                    <span class="amount">1,499</span>
                                    <span class="period">/month</span>
                                </div>
                                <p class="pricing-subtitle">Perfect for small parking lots</p>
                                <p class="pricing-save">Billed annually (₹23,988/year)</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Up to 50 parking spaces</li>
                                    <li>Basic analytics</li>
                                    <li>Email support</li>
                                    <li>Mobile responsive</li>
                                    <li>Payment processing</li>
                                    <li class="unavailable">Advanced reporting</li>
                                    <li class="unavailable">API access</li>
                                    <li class="unavailable">Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <?php if($isLoggedIn): ?>
                                <a href="#" class="btn btn-outline subscribe-btn" data-plan="Starter" data-price="1499" data-cycle="yearly">Get Started</a>
                                <?php else: ?>
                                <a href="register.php" class="btn btn-outline">Get Started</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pricing-card featured">
                            <div class="ribbon">Most Popular</div>
                            <div class="pricing-header">
                                <h3>Professional</h3>
                                <div class="price">
                                    <span class="currency">₹</span>
                                    <span class="amount">2,399</span>
                                    <span class="period">/month</span>
                                </div>
                                <p class="pricing-subtitle">For growing operations</p>
                                <p class="pricing-save">Billed annually (₹35,988/year)</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Up to 200 parking spaces</li>
                                    <li>Advanced analytics</li>
                                    <li>Priority support</li>
                                    <li>SMS notifications</li>
                                    <li>Payment processing</li>
                                    <li>Advanced reporting</li>
                                    <li>API access</li>
                                    <li class="unavailable">Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <?php if($isLoggedIn): ?>
                                <a href="#" class="btn btn-primary subscribe-btn" data-plan="Professional" data-price="2399" data-cycle="yearly">Get Started</a>
                                <?php else: ?>
                                <a href="register.php" class="btn btn-primary">Get Started</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pricing-card">
                            <div class="pricing-header">
                                <h3>Enterprise</h3>
                                <div class="price">
                                    <span class="currency">₹</span>
                                    <span class="amount">3,999</span>
                                    <span class="period">/month</span>
                                </div>
                                <p class="pricing-subtitle">For large-scale facilities</p>
                                <p class="pricing-save">Billed annually (₹59,988/year)</p>
                            </div>
                            <div class="pricing-features">
                                <ul>
                                    <li>Unlimited parking spaces</li>
                                    <li>Custom analytics</li>
                                    <li>24/7 dedicated support</li>
                                    <li>SMS & email notifications</li>
                                    <li>Payment processing</li>
                                    <li>Advanced reporting</li>
                                    <li>API access</li>
                                    <li>Custom branding</li>
                                </ul>
                            </div>
                            <div class="pricing-cta">
                                <a href="mailto:pandeyprabhat5556@gmail.com?subject=ParkSmart Enterprise Annual Plan Inquiry&body=Hello,%0D%0A%0D%0AI'm interested in learning more about your Enterprise annual plan for our parking facility. Please provide more information about customization options and implementation details.%0D%0A%0D%0AThank you!" class="btn btn-outline" title="Contact our sales team via email">Contact Sales</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Remaining sections - FAQ, comparison table, and CTA section remain unchanged -->
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>What payment methods do you accept?</h3>
                    <p>We accept all major credit cards including Visa, Mastercard, American Express, and Discover. We also support payments via PayPal.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Can I upgrade or downgrade my plan?</h3>
                    <p>Yes, you can change your plan at any time. When upgrading, we prorate the remaining time on your current plan. When downgrading, the new rate will apply at the next billing cycle.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Is there a contract or commitment?</h3>
                    <p>No, all our plans are month-to-month or year-to-year with no long-term contracts. You can cancel anytime without penalties.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Do you offer a free trial?</h3>
                    <p>Yes, we offer a 14-day free trial on all plans. No credit card required during the trial period.</p>
                </div>
                
                <div class="faq-item">
                    <h3>How do I get started with implementation?</h3>
                    <p>After signing up, our onboarding team will reach out to guide you through the setup process. Typical implementation takes 1-3 days depending on the size of your facility.</p>
                </div>
                
                <div class="faq-item">
                    <h3>What kind of support do you provide?</h3>
                    <p>All plans include email support with varying response times. Professional and Enterprise plans include phone support and dedicated account managers.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="comparison-section">
        <div class="container">
            <h2 class="section-title">Plan Comparison</h2>
            
            <div class="table-responsive">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Features</th>
                            <th>Starter</th>
                            <th>Professional</th>
                            <th>Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Parking Spaces</td>
                            <td>Up to 50</td>
                            <td>Up to 200</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>User Accounts</td>
                            <td>3</td>
                            <td>10</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>Analytics Dashboard</td>
                            <td>Basic</td>
                            <td>Advanced</td>
                            <td>Custom</td>
                        </tr>
                        <tr>
                            <td>Support Response Time</td>
                            <td>48 hours</td>
                            <td>24 hours</td>
                            <td>4 hours</td>
                        </tr>
                        <tr>
                            <td>Phone Support</td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-check"></i></td>
                            <td><i class="fas fa-check"></i></td>
                        </tr>
                        <tr>
                            <td>API Access</td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-check"></i></td>
                            <td><i class="fas fa-check"></i></td>
                        </tr>
                        <tr>
                            <td>Custom Branding</td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-check"></i></td>
                        </tr>
                        <tr>
                            <td>Data Retention</td>
                            <td>3 months</td>
                            <td>1 year</td>
                            <td>Unlimited</td>
                        </tr>
                        <tr>
                            <td>SMS Notifications</td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-check"></i></td>
                            <td><i class="fas fa-check"></i></td>
                        </tr>
                        <tr>
                            <td>Priority Support</td>
                            <td><i class="fas fa-times"></i></td>
                            <td><i class="fas fa-check"></i></td>
                            <td><i class="fas fa-check"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to optimize your parking operations?</h2>
                <p>Join thousands of businesses that trust our parking management solution.</p>
                <div class="cta-buttons">
                    <?php if($isLoggedIn): ?>
                    <a href="#" class="btn btn-primary subscribe-btn" data-plan="Professional" data-price="2999" data-cycle="monthly">Get Started Today</a>
                    <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Get Started Today</a>
                    <?php endif; ?>
                    <a href="mailto:pandeyprabhat5556@gmail.com?subject=ParkSmart Sales Inquiry&body=Hello,%0D%0A%0D%0AI'm interested in learning more about ParkSmart solutions for our parking facility. Please contact me to discuss our specific needs.%0D%0A%0D%0AThank you!" class="btn btn-secondary">Talk to Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Subscription Modal -->
    <div class="modal" id="subscriptionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Subscribe to <span id="modalPlanName"></span></h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="subscription-summary">
                    <div class="plan-title" id="planTitle"></div>
                    <div class="plan-price" id="planPrice"></div>
                </div>
                
                <form action="" method="POST" id="subscriptionForm">
                    <input type="hidden" name="plan_name" id="planNameInput">
                    <input type="hidden" name="plan_price" id="planPriceInput">
                    <input type="hidden" name="billing_cycle" id="billingCycleInput">
                    
                    <div class="duration-selector">
                        <label>Select Duration:</label>
                        <div class="duration-options">
                            <div class="duration-option selected" data-months="1">
                                <div>1 Month</div>
                                <small>No discount</small>
                            </div>
                            <div class="duration-option" data-months="6">
                                <div>6 Months</div>
                                <small>5% off</small>
                            </div>
                            <div class="duration-option" data-months="12">
                                <div>12 Months</div>
                                <small>10% off</small>
                            </div>
                        </div>
                        <input type="hidden" name="duration_months" id="durationInput" value="1">
                    </div>
                    
                    <div class="total-cost">
                        Total: <span id="totalCost"></span>
                    </div>
                    
                    <button type="submit" name="subscribe_plan" class="btn btn-primary btn-block">Subscribe Now</button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function switchTab(tabName) {
           // Your existing tab switching code
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            document.getElementById(tabName).classList.add('active');
            
            event.currentTarget.classList.add('active');
        }
        
        // Subscription modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const subscribeBtns = document.querySelectorAll('.subscribe-btn');
            const modal = document.getElementById('subscriptionModal');
            const closeModal = document.querySelector('.close-modal');
            const modalPlanName = document.getElementById('modalPlanName');
            const planTitle = document.getElementById('planTitle');
            const planPrice = document.getElementById('planPrice');
            const totalCost = document.getElementById('totalCost');
            
            // Form inputs
            const planNameInput = document.getElementById('planNameInput');
            const planPriceInput = document.getElementById('planPriceInput');
            const billingCycleInput = document.getElementById('billingCycleInput');
            const durationInput = document.getElementById('durationInput');
            
            // Duration options
            const durationOptions = document.querySelectorAll('.duration-option');
            
            // Show modal when subscription button is clicked
            subscribeBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const plan = this.getAttribute('data-plan');
                    const price = this.getAttribute('data-price');
                    const cycle = this.getAttribute('data-cycle');
                    const formattedPrice = parseInt(price).toLocaleString('en-IN');
                    
                    // Set modal content
                    modalPlanName.textContent = plan;
                    planTitle.textContent = plan + " Plan";
                    planPrice.textContent = "₹" + formattedPrice + " per month";
                    
                    // Set form inputs
                    planNameInput.value = plan;
                    planPriceInput.value = price;
                    billingCycleInput.value = cycle;
                    
                    // Reset duration selection
                    durationOptions.forEach(option => {
                        option.classList.remove('selected');
                    });
                    durationOptions[0].classList.add('selected');
                    durationInput.value = 1;
                    
                    // Calculate initial total
                    calculateTotal(price, 1);
                    
                    // Show modal
                    modal.classList.add('show');
                });
            });
            
            // Handle duration selection
            durationOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    durationOptions.forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Update duration input
                    const months = this.getAttribute('data-months');
                    durationInput.value = months;
                    
                    // Calculate new total
                    calculateTotal(planPriceInput.value, months);
                });
            });
            
            // Calculate total cost with discount
            function calculateTotal(price, months) {
                let discount = 0;
                if (months == 6) {
                    discount = 0.05; // 5% discount for 6 months
                } else if (months == 12) {
                    discount = 0.10; // 10% discount for 12 months
                }
                
                const monthlyPrice = parseFloat(price);
                const totalWithDiscount = (monthlyPrice * months) * (1 - discount);
                totalCost.textContent = "₹" + totalWithDiscount.toLocaleString('en-IN');
            }
            
            // Close modal
            closeModal.addEventListener('click', function() {
                modal.classList.remove('show');
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
