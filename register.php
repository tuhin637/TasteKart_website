<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $role = $_POST['role'];

    // Basic security check (to be enhanced): Only allow admin role if authorized (e.g., via session or admin key)
    if ($role === 'admin' && !isset($_SESSION['is_admin_authorized'])) {
        $error = "Admin registration requires authorization.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $password, $phone, $role]);
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TasteKart</title>
    <style>
        <?php echo file_get_contents('style.css'); ?>
        .register-section { max-width: 400px; margin: 3rem auto; padding: 1rem; }
        .register-form { background: var(--card-bg); padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow); }
        .register-form label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .register-form input, .register-form select { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .register-form button { width: 100%; background: var(--primary-color); color: white; border: none; padding: 0.75rem; border-radius: 25px; font-weight: 700; cursor: pointer; }
        .register-form button:hover { background: var(--primary-dark); }
        .error { color: red; text-align: center; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
        </nav>
    </header>

    <section class="register-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color);">Register</h2>
        <?php if (isset($error)): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST" class="register-form">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" required>
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="customer">Customer</option>
                <option value="restaurant">Restaurant</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit">Register</button>
        </form>
    </section>
</body>
</html>