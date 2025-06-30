<?php
require_once 'auth.php';
?>
<header class="custom-header">
    <div class="container">
        <div class="header-container">
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn icon-btn" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Logo -->
            <div class="logo">
                <a href="index.php" class="d-inline-flex">
                    <img src="https://cdn.rareblocks.xyz/collection/clarity-ecommerce/images/logo.svg" alt="Rareblocks Logo">
                </a>
            </div>

            <!-- Header Actions -->
            <div class="header-actions">
                <!-- Desktop Auth Links -->
                <div class="auth-links desktop-nav d-none d-lg-flex">
                    <?php if (isLoggedIn()): ?>
                        <a href="profile.php">My Account</a>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a href="signup.php">Create Free Account</a>
                        <a href="login.php">Login</a>
                    <?php endif; ?>
                </div>

                <!-- Divider -->
                <div class="header-divider d-none d-lg-block"></div>

                <!-- Search Button -->
                <button class="icon-btn">
                    <i class="fas fa-search"></i>
                </button>

                <!-- Mobile Divider -->
                <div class="header-divider d-lg-none"></div>

                <!-- Cart Button -->
                <a href="cart.php" class="icon-btn">
                    <i class="fas fa-shopping-bag"></i>
                    <?php
                    $cartCount = 0;
                    if (isLoggedIn()) {
                        require_once 'includes/cart.php';
                        $cartCount = getCartItemCount();
                    }
                    ?>
                    <span class="cart-badge"><?php echo $cartCount; ?></span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <button class="close-btn" id="closeMobileMenu">
        <i class="fas fa-times"></i>
    </button>
    <div class="nav-links">
        <a href="#">All Brands</a>
        <a href="#">Men</a>
        <a href="#">Women</a>
        <a href="#">Accessories</a>
        <a href="#">Sports</a>
        <a href="#">Kids</a>
        <?php if (isLoggedIn()): ?>
            <a href="profile.php">My Account</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="signup.php">Create Free Account</a>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('mobileMenuToggle').addEventListener('click', function() {
    document.getElementById('mobileMenu').classList.add('active');
});

document.getElementById('closeMobileMenu').addEventListener('click', function() {
    document.getElementById('mobileMenu').classList.remove('active');
});
</script> 