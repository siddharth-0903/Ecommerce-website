<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Check if email already exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already exists';
        } else {
            // Create new user
            $query = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'user')";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$firstName, $lastName, $email, $password])) {
                // Get the new user's ID
                $userId = $db->lastInsertId();
                
                // Set session variables
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = 'user';
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                
                header('Location: index.php');
                exit();
            } else {
                $error = 'Error creating account';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Sign Up | Rareblocks</title>
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
            .signup-card {
                position: relative;
                z-index: 1;
                background: #fff;
                border-radius: 18px;
                box-shadow: 0 0 32px 0 rgba(0,0,0,0.12), 0 0 0 1px #ffe942;
                max-width: 500px;
                width: 100%;
                padding: 2.5rem 2rem 2rem 2rem;
                margin: 2rem auto;
            }
            .signup-card .logo {
                text-align: center;
                margin-bottom: 1.5rem;
            }
            .signup-card .logo span {
                color: #19191b;
                font-weight: 700;
                font-size: 1.2rem;
                letter-spacing: 2px;
            }
            .signup-card h2 {
                font-weight: 700;
                text-align: center;
                margin-bottom: 0.5rem;
            }
            .signup-card p {
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
            .login-link {
                color: #19191b;
                font-weight: 600;
                text-decoration: none;
            }
            .login-link:hover {
                color: #ffe942;
                text-decoration: underline;
            }
            .signup-btn {
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
            .signup-btn:hover {
                background: #ffe942;
                color: #19191b;
            }
            .password-strength {
                font-size: 0.95rem;
                margin-top: 0.25rem;
                margin-bottom: 0.5rem;
                font-weight: 600;
            }
            .strength-weak { color: #ef4444; }
            .strength-medium { color: #f59e0b; }
            .strength-strong { color: #10b981; }
            .error-message {
                color: #dc3545;
                text-align: center;
                margin-bottom: 1rem;
            }
            .success-message {
                color: #198754;
                text-align: center;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="gradient-bg"></div>
        <div class="signup-card">
            <div class="logo mb-2">
                <span>/RAREBLOCKS</span>
            </div>
            <h2>Create Account</h2>
            <p>Join Rareblocks today &amp; start growing your business</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="signup-btn">Create Account</button>
            </form>
            <div class="text-center mt-3">
                <span>Already have an account? <a href="login.php" class="login-link">Sign in</a></span>
            </div>
        </div>

        <script>
            // Password strength checker
            const passwordInput = document.getElementById('password');
            const strengthIndicator = document.getElementById('passwordStrength');

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let message = '';

                if (password.length >= 8) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;

                switch (strength) {
                    case 0:
                    case 1:
                        message = '<span class="strength-weak">Weak password</span>';
                        break;
                    case 2:
                    case 3:
                        message = '<span class="strength-medium">Medium strength password</span>';
                        break;
                    case 4:
                    case 5:
                        message = '<span class="strength-strong">Strong password</span>';
                        break;
                }

                strengthIndicator.innerHTML = message;
            });
        </script>
    </body>
</html>
