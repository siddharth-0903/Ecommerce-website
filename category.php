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

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $status = $_POST['status'] ?? 'active';

                if (empty($name)) {
                    $error = "Category name is required.";
                } else {
                    try {
                        $query = "INSERT INTO categories (name, description, status) VALUES (:name, :description, :status)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':status', $status);
                        
                        if ($stmt->execute()) {
                            $success = "Category added successfully!";
                        } else {
                            $error = "Error adding category.";
                        }
                    } catch (PDOException $e) {
                        $error = "Error: " . $e->getMessage();
                    }
                }
                break;

            case 'edit':
                $id = $_POST['id'] ?? 0;
                $name = $_POST['name'] ?? '';
                $description = $_POST['description'] ?? '';
                $status = $_POST['status'] ?? 'active';

                if (empty($name)) {
                    $error = "Category name is required.";
                } else {
                    try {
                        $query = "UPDATE categories SET name = :name, description = :description, status = :status WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $id);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':status', $status);
                        
                        if ($stmt->execute()) {
                            $success = "Category updated successfully!";
                        } else {
                            $error = "Error updating category.";
                        }
                    } catch (PDOException $e) {
                        $error = "Error: " . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? 0;
                try {
                    // Check if category has products
                    $query = "SELECT COUNT(*) as count FROM products WHERE category_id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['count'] > 0) {
                        $error = "Cannot delete category with associated products. Please reassign or delete products first.";
                    } else {
                        $query = "DELETE FROM categories WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $id);
                        
                        if ($stmt->execute()) {
                            $success = "Category deleted successfully!";
                        } else {
                            $error = "Error deleting category.";
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all categories
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category ID from URL
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get category details
$query = "SELECT * FROM categories WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $categoryId);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: categories.php');
    exit();
}

// Get products in this category
$query = "SELECT * FROM products WHERE category_id = :id AND status = 'active' ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $categoryId);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management | Rareblocks</title>
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

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.97rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 233, 66, 0.25);
        }

        /* Categories Table */
        .categories-table {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-top: none;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .action-btn {
            background: none;
            border: none;
            padding: 0.25rem;
            margin: 0 0.25rem;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: var(--secondary-color);
            color: var(--primary-color);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
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
                    <a href="#" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link active">
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
                    <a href="#" class="nav-link">
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
            <div class="page-header">
                <div>
                    <h1 class="page-title">Categories Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                            <li class="breadcrumb-item active">Categories</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add Category Form -->
            <div class="form-card">
                <h3 class="section-title mb-4">Add New Category</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Categories Table -->
            <div class="categories-table">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $category['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ucfirst($category['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn" title="Edit" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn" title="Delete" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this category? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Edit category functionality
        function editCategory(category) {
            document.getElementById('edit_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_status').value = category.status;
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }

        // Delete category functionality
        function deleteCategory(categoryId) {
            document.getElementById('delete_id').value = categoryId;
            new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
        }
    </script>
</body>
</html> 