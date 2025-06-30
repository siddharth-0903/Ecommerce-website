<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $password === $user['password']) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin-dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Rareblocks</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            background: #19191b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .gradient-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            background: radial-gradient(circle at 80% 20%, #ffe942 0%, #10b981 40%, transparent 70%);
            opacity: 0.18;
            filter: blur(16px);
            pointer-events: none;
        }
        .login-card {
            position: relative;
            z-index: 1;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 0 32px 0 rgba(0,0,0,0.12), 0 0 0 1px #ffe942;
            max-width: 400px;
            width: 100%;
            padding: 2.5rem 2rem 2rem 2rem;
            margin: 2rem auto;
            box-shadow: 0 0 40px 0 rgba(255,233,66,0.15), 0 0 0 1px #eee;
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-card .logo span {
            color: #19191b;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        .login-card h2 {
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .login-card p {
            text-align: center;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        .google-btn {
            background: #f5f5f5;
            border: none;
            border-radius: 6px;
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            color: #19191b;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        .google-btn:hover {
            background: #ececec;
        }
        .divider {
            text-align: center;
            color: #bbb;
            margin: 1.25rem 0 1rem 0;
            font-size: 0.95rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.97rem;
        }
        .form-control {
            border-radius: 6px;
            font-size: 1rem;
        }
        .forgot-link {
            font-size: 0.95rem;
            color: #6b7280;
            text-decoration: none;
        }
        .forgot-link:hover {
            color: #19191b;
            text-decoration: underline;
        }
        .login-btn {
            background: #19191b;
            color: #fff;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
            transition: background 0.2s;
        }
        .login-btn:hover {
            background: #ffe942;
            color: #19191b;
        }
        .signup-link {
            color: #19191b;
            font-weight: 600;
            text-decoration: none;
        }
        .signup-link:hover {
            color: #ffe942;
            text-decoration: underline;
        }
        .error-message {
            color: #dc3545;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="gradient-bg"></div>
    <div class="login-card">
        <div class="logo mb-2">
            <span>/RAREBLOCKS</span>
        </div>
        <h2>Welcome Back!</h2>
        <p>Sign in to Rareblocks today &amp; start growing your business</p>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email address" required>
            </div>
            <div class="mb-2 d-flex justify-content-between align-items-center">
                <label for="password" class="form-label mb-0">Password</label>
                <a href="#" class="forgot-link">Forgot Password?</a>
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password (min. 8 character)" required minlength="8">
            </div>
            <button type="submit" class="login-btn">Sign in</button>
        </form>
        <div class="text-center mt-3">
            <span>Don't have an account? <a href="signup.php" class="signup-link">Create Free Account</a></span>
        </div>
    </div>
</body>
</html> 