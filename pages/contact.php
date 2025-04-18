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
    <title>Contact Us - ParkSmart</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <section class="page-header">
        <div class="container">
            <h1>Contact Us</h1>
            <p>We're here to answer any questions you have about our parking solutions</p>
        </div>
    </section>

    <section class="contact-page">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>Get in Touch</h2>
                    <p>Have questions about our parking solutions? Our team is ready to help with any inquiries regarding our services, pricing plans, or technical support.</p>
                    
                    <div class="contact-method">
                        <i class="fas fa-envelope"></i>
                        <div class="contact-details">
                            <h3>Email Us</h3>
                            <a href="mailto:pandeyprabhat5556@gmail.com">pandeyprabhat5556@gmail.com</a>
                            <p>We'll respond within 24 hours</p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-phone"></i>
                        <div class="contact-details">
                            <h3>Call Us</h3>
                            <a href="tel:+91999999999">+91 99999 99999</a>
                            <p>Monday to Friday, 9am to 6pm</p>
                        </div>
                    </div>
                    
                    <div class="contact-method">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="contact-details">
                            <h3>Visit Us</h3>
                            <p>144411 Lovely Professional University<br>Phagwara, Punjab</p>
                            <a href="https://maps.google.com" target="_blank" class="text-link">View on map <i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>

                    <div class="social-links">
                        <h3>Connect With Us</h3>
                        <div class="social-icons">
                            <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form-container">
                    <div class="form-header">
                        <h2>Send us a message</h2>
                        <p>Fill out the form below and we'll get back to you as soon as possible</p>
                    </div>

                    <?php
                    // Display success message if set
                    if (isset($_SESSION['contact_success'])) {
                        echo '<div class="alert alert-success">' . $_SESSION['contact_success'] . '</div>';
                        unset($_SESSION['contact_success']);
                    }
                    
                    // Display error message if set
                    if (isset($_SESSION['contact_error'])) {
                        echo '<div class="alert alert-danger">' . $_SESSION['contact_error'] . '</div>';
                        unset($_SESSION['contact_error']);
                    }
                    ?>
                    
                    <form action="../includes/process_contact.php" method="POST" class="contact-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Your name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject <span class="required">*</span></label>
                            <select id="subject" name="subject" required>
                                <option value="" disabled selected>Choose a subject</option>
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Technical Support">Technical Support</option>
                                <option value="Pricing Information">Pricing Information</option>
                                <option value="Partnership Opportunity">Partnership Opportunity</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your message <span class="required">*</span></label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="privacy" name="privacy" required>
                            <label for="privacy">I agree to the <a href="../pages/privacy-policy.php">privacy policy</a> <span class="required">*</span></label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="faq">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>How quickly will I receive a response?</h3>
                    <p>We typically respond to all inquiries within 24 hours during business days. For urgent matters, please call our support