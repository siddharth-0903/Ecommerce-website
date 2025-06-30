<?php
require_once 'config/Database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_site_settings':
                // Handle site settings update
                $site_name = trim($_POST['site_name']);
                $site_description = trim($_POST['site_description']);
                $contact_email = trim($_POST['contact_email']);
                $contact_phone = trim($_POST['contact_phone']);
                $address = trim($_POST['address']);
                
                // In a real application, you would store these in a settings table
                // For now, we'll just show success message
                $success_message = "Site settings updated successfully!";
                break;
                
            case 'update_payment_settings':
                // Handle payment settings
                $cod_enabled = isset($_POST['cod_enabled']) ? 1 : 0;
                $razorpay_enabled = isset($_POST['razorpay_enabled']) ? 1 : 0;
                $razorpay_key = trim($_POST['razorpay_key']);
                $razorpay_secret = trim($_POST['razorpay_secret']);
                
                $success_message = "Payment settings updated successfully!";
                break;
                
            case 'update_shipping_settings':
                // Handle shipping settings
                $free_shipping_threshold = floatval($_POST['free_shipping_threshold']);
                $standard_shipping_cost = floatval($_POST['standard_shipping_cost']);
                $express_shipping_cost = floatval($_POST['express_shipping_cost']);
                $delivery_days = intval($_POST['delivery_days']);
                
                $success_message = "Shipping settings updated successfully!";
                break;
                
            case 'update_email_settings':
                // Handle email settings
                $smtp_host = trim($_POST['smtp_host']);
                $smtp_port = intval($_POST['smtp_port']);
                $smtp_username = trim($_POST['smtp_username']);
                $smtp_password = trim($_POST['smtp_password']);
                $from_email = trim($_POST['from_email']);
                $from_name = trim($_POST['from_name']);
                
                $success_message = "Email settings updated successfully!";
                break;
                
            case 'update_seo_settings':
                // Handle SEO settings
                $meta_title = trim($_POST['meta_title']);
                $meta_description = trim($_POST['meta_description']);
                $meta_keywords = trim($_POST['meta_keywords']);
                $google_analytics = trim($_POST['google_analytics']);
                
                $success_message = "SEO settings updated successfully!";
                break;
        }
    }
}

// Get current settings (in a real app, these would come from database)
$current_settings = [
    'site_name' => 'RAREBLOCKS',
    'site_description' => 'Premium E-commerce Platform',
    'contact_email' => 'contact@rareblocks.com',
    'contact_phone' => '+91 98620 30171',
    'address' => 'Tura, Meghalaya, India',
    'cod_enabled' => true,
    'razorpay_enabled' => false,
    'razorpay_key' => '',
    'razorpay_secret' => '',
    'free_shipping_threshold' => 500.00,
    'standard_shipping_cost' => 50.00,
    'express_shipping_cost' => 150.00,
    'delivery_days' => 5,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => 'noreply@rareblocks.com',
    'from_name' => 'RAREBLOCKS',
    'meta_title' => 'RAREBLOCKS - Premium E-commerce',
    'meta_description' => 'Discover premium products at RAREBLOCKS',
    'meta_keywords' => 'ecommerce, premium, products, online shopping',
    'google_analytics' => ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #19191b;
            --secondary-color: #ffe942;
            --sidebar-bg: #1a1a1c;
            --card-bg: #ffffff;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }

        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #2d2d2f;
        }

        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        .sidebar-brand i {
            color: var(--secondary-color);
            margin-right: 0.75rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.25rem;
        }

        .nav-link {
            color: #a3a3a3;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 0;
        }

        .nav-link:hover {
            background: rgba(255, 233, 66, 0.1);
            color: var(--secondary-color);
        }

        .nav-link.active {
            background: var(--secondary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu .notification-btn {
            background: none;
            border: none;
            font-size: 1.1rem;
            color: var(--text-muted);
            position: relative;
            cursor: pointer;
        }

        .user-menu .notification-btn .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Page Content */
        .page-content {
            padding: 2rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 233, 66, 0.25);
        }

        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #000;
            border-color: #000;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .btn-warning:hover {
            background: #f5d000;
            border-color: #f5d000;
            color: var(--primary-color);
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 8px;
            font-weight: 500;
        }

        /* Settings Navigation */
        .settings-nav {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }

        .settings-nav .nav-link {
            color: var(--text-muted);
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 0;
            font-weight: 500;
        }

        .settings-nav .nav-link.active {
            background: var(--secondary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .settings-nav .nav-link:hover {
            background: rgba(255, 233, 66, 0.1);
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 80px;
            }

            .top-bar {
                padding: 1rem;
            }

            .page-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-cube"></i>
                <span>RAREBLOCKS</span>
            </a>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-view-orders.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-view-products.php" class="nav-link">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-view-customer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-analytics.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="category.php" class="nav-link">
                        <i class="fas fa-tag"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span>Shipping</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-percent"></i>
                        <span>Coupons</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-settings.php" class="nav-link active">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-menu">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <div class="user-avatar">
                    AD
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <div class="row">
                <div class="col-12">
                    <h1 class="page-title">Settings</h1>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <div class="settings-nav">
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="site-tab" data-bs-toggle="tab" data-bs-target="#site-settings" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i>Site Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment-settings" type="button" role="tab">
                            <i class="fas fa-credit-card me-2"></i>Payment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping-settings" type="button" role="tab">
                            <i class="fas fa-truck me-2"></i>Shipping
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-settings" type="button" role="tab">
                            <i class="fas fa-envelope me-2"></i>Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo-settings" type="button" role="tab">
                            <i class="fas fa-search me-2"></i>SEO
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="tab-content" id="settingsTabContent">
                <!-- Site Settings -->
                <div class="tab-pane fade show active" id="site-settings" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-globe me-2"></i>Site Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_site_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_phone" class="form-label">Contact Phone</label>
                                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($current_settings['contact_phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($current_settings['address']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Site Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div class="tab-pane fade" id="payment-settings" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-credit-card me-2"></i>Payment Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_payment_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="cod_enabled" name="cod_enabled" <?php echo $current_settings['cod_enabled'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="cod_enabled">
                                                    Enable Cash on Delivery (COD)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="razorpay_enabled" name="razorpay_enabled" <?php echo $current_settings['razorpay_enabled'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="razorpay_enabled">
                                                    Enable Razorpay
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="razorpay_key" class="form-label">Razorpay Key ID</label>
                                            <input type="text" class="form-control" id="razorpay_key" name="razorpay_key" value="<?php echo htmlspecialchars($current_settings['razorpay_key']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="razorpay_secret" class="form-label">Razorpay Secret</label>
                                            <input type="password" class="form-control" id="razorpay_secret" name="razorpay_secret" value="<?php echo htmlspecialchars($current_settings['razorpay_secret']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Payment Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Shipping Settings -->
                <div class="tab-pane fade" id="shipping-settings" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-truck me-2"></i>Shipping Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_shipping_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="free_shipping_threshold" class="form-label">Free Shipping Threshold (₹)</label>
                                            <input type="number" step="0.01" class="form-control" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo $current_settings['free_shipping_threshold']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="delivery_days" class="form-label">Standard Delivery Days</label>
                                            <input type="number" class="form-control" id="delivery_days" name="delivery_days" value="<?php echo $current_settings['delivery_days']; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="standard_shipping_cost" class="form-label">Standard Shipping Cost (₹)</label>
                                            <input type="number" step="0.01" class="form-control" id="standard_shipping_cost" name="standard_shipping_cost" value="<?php echo $current_settings['standard_shipping_cost']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="express_shipping_cost" class="form-label">Express Shipping Cost (₹)</label>
                                            <input type="number" step="0.01" class="form-control" id="express_shipping_cost" name="express_shipping_cost" value="<?php echo $current_settings['express_shipping_cost']; ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Shipping Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="tab-pane fade" id="email-settings" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_email_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>" placeholder="smtp.gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo $current_settings['smtp_port']; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="smtp_password" class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" value="<?php echo htmlspecialchars($current_settings['from_email']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="from_name" class="form-label">From Name</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" value="<?php echo htmlspecialchars($current_settings['from_name']); ?>">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Email Settings
                </button>
            </form>
        </div>
    </div>
</div>

<!-- SEO Settings -->
<div class="tab-pane fade" id="seo-settings" role="tabpanel">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-search me-2"></i>SEO Settings</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_seo_settings">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?php echo htmlspecialchars($current_settings['meta_title']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="meta_keywords" class="form-label">Meta Keywords</label>
                            <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?php echo htmlspecialchars($current_settings['meta_keywords']); ?>" placeholder="keyword1, keyword2, keyword3">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="meta_description" class="form-label">Meta Description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3" placeholder="Enter meta description for search engines"><?php echo htmlspecialchars($current_settings['meta_description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="google_analytics" class="form-label">Google Analytics Tracking ID</label>
                    <input type="text" class="form-control" id="google_analytics" name="google_analytics" value="<?php echo htmlspecialchars($current_settings['google_analytics']); ?>" placeholder="G-XXXXXXXXXX">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save SEO Settings
                </button>
            </form>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle functionality
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        // Enable/disable Razorpay fields based on checkbox
        document.getElementById('razorpay_enabled').addEventListener('change', function() {
            const razorpayFields = document.querySelectorAll('#razorpay_key, #razorpay_secret');
            razorpayFields.forEach(function(field) {
                field.disabled = !this.checked;
                if (!this.checked) {
                    field.value = '';
                }
            });
        });

        // Initialize Razorpay fields state
        document.addEventListener('DOMContentLoaded', function() {
            const razorpayEnabled = document.getElementById('razorpay_enabled').checked;
            const razorpayFields = document.querySelectorAll('#razorpay_key, #razorpay_secret');
            razorpayFields.forEach(function(field) {
                field.disabled = !razorpayEnabled;
            });
        });
    </script>
</body>
</html>