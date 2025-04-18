<?php

$base_path = '';
if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    $base_path = '../';
}
?>

<header>
    <div class="container">
        <div class="navbar-container">
            <div class="navbar">
                <a href="<?php echo $base_path; ?>index.php" class="logo">
                    <i class="fas fa-parking logo-icon"></i>
                    ParkSmart
                </a>
                
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                
                <ul class="nav-links">
                    <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $base_path; ?>pages/features.php">Features</a></li>
                    <li><a href="<?php echo $base_path; ?>pages/pricing.php">Pricing</a></li>
                    <a href="/parking-solution/pages/contact.php">Contact</a>

                </ul>
                
                <div class="nav-btn">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $base_path; ?>pages/dashboard.php" class="btn btn-secondary">My Dashboard</a>
                        <a href="<?php echo $base_path; ?>includes/logout.php" class="btn btn-primary">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo $base_path; ?>pages/login.php" class="btn btn-secondary">Login</a>
                        <a href="<?php echo $base_path; ?>pages/register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

