<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? 0;
$stmt = $pdo->prepare("SELECT o.*, u.name as restaurant_name FROM orders o JOIN users u ON o.restaurant_id = u.id WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: order_history.php");
    exit;
}

$stmt = $pdo->prepare("SELECT oi.quantity, mi.name, mi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Order confirmation for your TasteKart purchase." />
    <meta name="keywords" content="TasteKart, order confirmation, food delivery, Bangladesh" />
    <meta name="author" content="TasteKart Team" />
    <title>TasteKart - Order Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00c4b4; /* Vibrant Teal */
            --primary-dark: #009688;
            --secondary-color: #6a1b9a; /* Deep Plum */
            --accent-color: #ff7f50; /* Soft Coral */
            --card-bg: #f5f5f0; /* Creamy Off-White */
            --shadow: rgba(0, 0, 0, 0.1);
            --text-color: #333333; /* Charcoal Gray */
            --gradient: linear-gradient(135deg, #00c4b4, #6a1b9a); /* Teal to Plum */
            --gradient-overlay: linear-gradient(135deg, rgba(0, 196, 180, 0.8), rgba(106, 27, 154, 0.8));
            --background-gradient: linear-gradient(135deg, #e6e6fa 0%, #e0f7f9 100%); /* Lavender to Mint */
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-gradient);
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
        }
        header {
            background: var(--gradient);
            color: #fff;
            padding: 1.5rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideDown 0.5s ease-out;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .logo {
            font-size: 2rem;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: transform 0.3s ease;
        }
        .logo:hover {
            transform: scale(1.05);
        }
        nav {
            display: flex;
            align-items: center;
        }
        nav a {
            color: #fff;
            margin-left: 1.5rem;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
        }
        nav a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px) scale(1.05);
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .confirmation-section {
            max-width: 600px;
            margin: 3rem auto;
            padding: 2.5rem;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s ease-out;
            text-align: center;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .confirmation-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--shadow);
            padding: 2rem;
        }
        .confirmation-card h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }
        .confirmation-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-color);
        }
        .confirmation-card p {
            margin: 0.5rem 0;
            color: var(--text-color);
        }
        .item-list {
            text-align: left;
            margin-top: 1rem;
            padding: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px var(--shadow);
        }
        .item-list p {
            margin: 0.5rem 0;
        }
        .toggle-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 1rem;
            transition: all 0.4s ease;
        }
        .toggle-button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }
        .order-history {
            display: none;
        }
        .order-history.active {
            display: block;
        }
        a.order-history-link {
            display: inline-block;
            margin-top: 1rem;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.4s ease;
        }
        a.order-history-link:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }
        @media (max-width: 768px) {
            header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }
            nav a {
                margin: 0.5rem;
                padding: 0.4rem 1rem;
            }
            .confirmation-section {
                padding: 1.5rem;
            }
            .confirmation-card {
                padding: 1rem;
            }
            .toggle-button {
                padding: 0.6rem 1.2rem;
            }
            a.order-history-link {
                padding: 0.6rem 1.2rem;
            }
        }
        footer {
            background: var(--gradient);
            color: #fff;
            padding: 3rem 2rem;
            margin-top: 3rem;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.1);
        }
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 2rem;
        }
        .footer-column {
            flex: 1;
            min-width: 200px;
        }
        .footer-logo .logo {
            font-size: 2.5rem;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: transform 0.3s ease;
        }
        .footer-logo .logo:hover {
            transform: scale(1.05);
            color: #e0f7f9;
        }
        .footer-logo p {
            font-size: 0.9rem;
            margin-top: 1rem;
            color: #e0e0e0;
            line-height: 1.5;
        }
        .footer-links h4 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--accent-color);
        }
        .footer-links a {
            color: #fff;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1rem;
            transition: color 0.3s ease;
        }
        .footer-links a:hover {
            color: var(--accent-color);
        }
        .footer-social h4 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: var(--accent-color);
        }
        .footer-social a {
            color: #fff;
            margin: 0 0.5rem;
            font-size: 1.5rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        .footer-social .social-icon {
            display: inline-block;
        }
        .footer-social a:hover {
            transform: scale(1.2);
            color: var(--accent-color);
        }
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #e0e0e0;
        }
        @media (max-width: 768px) {
            .footer-container {
                flex-direction: column;
                text-align: center;
            }
            .footer-column {
                margin-bottom: 1.5rem;
            }
            .footer-bottom {
                margin-top: 1rem;
            }
        }
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

    <section class="confirmation-section">
        <div class="confirmation-card">
            <h2>Order Confirmed!</h2>
            <button class="toggle-button" onclick="toggleOrderHistory()">View Order Details</button>
            <div class="order-history" id="order-history">
                <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                <p>Restaurant: <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                <p>Total: ৳<?php echo htmlspecialchars($order['total_amount']); ?></p>
                <p>Status: <?php echo htmlspecialchars($order['status']); ?></p>
                <p>Placed on: <?php echo htmlspecialchars($order['created_at']); ?></p>
                <h4>Items:</h4>
                <div class="item-list">
                    <?php foreach ($items as $item): ?>
                        <p><?php echo htmlspecialchars($item['name']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>) - ৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                    <?php endforeach; ?>
                </div>
                <p>Thank you for your order! It will be delivered soon.</p>
                <a href="order_history.php" class="order-history-link">View Order History</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-container">
            <div class="footer-column footer-logo">
                <a href="index.php" class="logo">TasteKart</a>
                <p>Delivering joy, one meal at a time. TasteKart brings you the best local flavors with fast, reliable service across Bangladesh.</p>
                <p>Contact us: +880 01792920637 | tastekart@gmail.com</p>
            </div>
            <div class="footer-column footer-links">
                <h4>Quick Links</h4>
                <a href="about.php">About Us</a>
                <a href="contact.php">Contact</a>
                <a href="terms.php">Terms & Conditions</a>
                <a href="privacy.php">Privacy Policy</a>
            </div>
            <div class="footer-column footer-social">
                <h4>Follow Us</h4>
                <a href="https://facebook.com" target="_blank" aria-label="Facebook"><span class="social-icon">🌐</span></a>
                <a href="https://twitter.com" target="_blank" aria-label="Twitter"><span class="social-icon">🐦</span></a>
                <a href="https://instagram.com" target="_blank" aria-label="Instagram"><span class="social-icon">📸</span></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 TasteKart. All rights reserved. Designed inspired by Tuhinuzzaman Tuhin</p>
        </div>
    </footer>

    <script>
        function toggleOrderHistory() {
            const orderHistory = document.getElementById('order-history');
            orderHistory.classList.toggle('active');
        }
    </script>
</body>
</html>