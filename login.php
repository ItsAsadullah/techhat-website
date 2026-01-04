<?php
require_once 'core/auth.php';

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($email, $password)) {
        if (is_admin()) {
            header("Location: admin/index.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TechHat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container { max-width: 400px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; background: #333; color: #fff; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="auth-container">
        <h2>Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register</a></p>
    </div>
</body>
</html>