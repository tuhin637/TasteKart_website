<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $address, $user_id]);
    header('Location: profile.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TasteKart</title>
    <style>
        <?php echo file_get_contents('style.css'); ?>
        .profile-section { max-width: 600px; margin: 3rem auto; padding: 1rem; }
        .profile-form { background: var(--card-bg); padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow); }
        .profile-form label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .profile-form input, .profile-form textarea { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .profile-form button { width: 100%; background: var(--primary-color); color: white; border: none; padding: 0.75rem; border-radius: 25px; font-weight: 700; cursor: pointer; }
        .profile-form button:hover { background: var(--primary-dark); }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="order_history.php">Order History</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <section class="profile-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color);">Profile</h2>
        <form method="POST" class="profile-form">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            <label for="address">Address</label>
            <textarea id="address" name="address" placeholder="ঠিকানা"><?php echo htmlspecialchars($user['address']); ?></textarea>
            <button type="submit">Update Profile</button>
        </form>
    </section>
</body>
</html>