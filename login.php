<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
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
    <title>Login - TasteKart</title>
    <style>
        <?php echo file_get_contents('style.css'); ?>
        .login-section { max-width: 400px; margin: 3rem auto; padding: 1rem; }
        .login-form { background: var(--card-bg); padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow); }
        .login-form label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .login-form input { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .login-form button { width: 100%; background: var(--primary-color); color: white; border: none; padding: 0.75rem; border-radius: 25px; font-weight: 700; cursor: pointer; }
        .login-form button:hover { background: var(--primary-dark); }
        .error { color: red; text-align: center; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="register.php">Register</a>
        </nav>
    </header>

    <section class="login-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color);">Login</h2>
        <?php if (isset($error)): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" class="login-form">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </section>
</body>
</html>