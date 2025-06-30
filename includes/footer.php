<footer>
    <div class="container py-5">
        <!-- Logo or Name -->
        <div class="mb-4">
            <div class="d-flex align-items-center">
                <span class="text-danger fw-bold fs-4 me-2">/</span>
                <span class="fw-bold fs-4">RAREBLOCKS</span>
            </div>
        </div>

        <!-- Footer Sections -->
        <div class="row">
            <div class="col-md-3 mb-4">
                <h5 class="section-title">Company</h5>
                <ul class="list-unstyled">
                    <li><a href="about.php" class="text-decoration-none text-dark">About</a></li>
                    <li><a href="contact.php" class="text-decoration-none text-dark">Contact</a></li>
                    <li><a href="careers.php" class="text-decoration-none text-dark">Careers</a></li>
                    <li><a href="blog.php" class="text-decoration-none text-dark">Blog</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="section-title">Help</h5>
                <ul class="list-unstyled">
                    <li><a href="faq.php" class="text-decoration-none text-dark">FAQ</a></li>
                    <li><a href="shipping.php" class="text-decoration-none text-dark">Shipping</a></li>
                    <li><a href="returns.php" class="text-decoration-none text-dark">Returns</a></li>
                    <li><a href="privacy.php" class="text-decoration-none text-dark">Privacy Policy</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="section-title">Account</h5>
                <ul class="list-unstyled">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="profile.php" class="text-decoration-none text-dark">My Account</a></li>
                        <li><a href="orders.php" class="text-decoration-none text-dark">My Orders</a></li>
                        <li><a href="wishlist.php" class="text-decoration-none text-dark">Wishlist</a></li>
                        <li><a href="logout.php" class="text-decoration-none text-dark">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="text-decoration-none text-dark">Login</a></li>
                        <li><a href="signup.php" class="text-decoration-none text-dark">Register</a></li>
                        <li><a href="forgot-password.php" class="text-decoration-none text-dark">Forgot Password</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="section-title">Newsletter</h5>
                <form action="subscribe.php" method="POST" class="mb-3">
                    <div class="input-group">
                        <input type="email" name="email" class="form-control newsletter-input" placeholder="Enter email address" required>
                        <button type="submit" class="btn btn-dark newsletter-btn">Subscribe</button>
                    </div>
                </form>
                <div class="row">
                    <div class="col-6">
                        <p class="mb-1 fw-bold text-uppercase small">Call Us</p>
                        <p class="fw-semibold">+91-1234567890</p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1 fw-bold text-uppercase small">Email Us</p>
                        <p class="fw-semibold">info@rareblocks.xyz</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="row pt-4 border-top mt-4">
            <div class="col-md-6 text-muted">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Rareblocks. All Rights Reserved</p>
            </div>
            <div class="col-md-6 d-flex justify-content-end footer-icon">
                <a href="#" class="me-3 text-dark"><i class="fab fa-twitter"></i></a>
                <a href="#" class="me-3 text-dark"><i class="fab fa-facebook"></i></a>
                <a href="#" class="me-3 text-dark"><i class="fab fa-instagram"></i></a>
                <a href="#" class="text-dark"><i class="fab fa-github"></i></a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="assets/main.js"></script> 