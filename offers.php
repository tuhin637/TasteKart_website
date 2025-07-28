<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon']) && $_SESSION['role'] === 'admin') {
    $code = $_POST['code'];
    $discount = $_POST['discount'];
    $expiry_date = $_POST['expiry_date'];
    $min_order_value = $_POST['min_order_value'];
    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount, expiry_date, min_order_value) VALUES (?, ?, ?, ?)");
    $stmt->execute([$code, $discount, $expiry_date, $min_order_value]);
}

$stmt = $pdo->query("SELECT * FROM coupons");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Offers</title>
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
            position: relative;
            background: rgba(255, 255, 255, 0.1);
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px) scale(1.05);
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .offer-section {
            max-width: 1400px;
            margin: 3rem auto;
            padding: 2.5rem;
            background: #fff;
            border-radius: 25px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .offer-section h2 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-size: 2.2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }

        .offer-section h3 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin: 2rem 0 1rem;
            font-size: 1.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 1px 1px 3px rgba(0, 196, 180, 0.1);
        }

        .offer-form {
            background: var(--gradient);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            color: #fff;
            margin-bottom: 2rem;
            transition: all 0.4s ease;
        }

        .offer-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 196, 180, 0.2);
        }

        .offer-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        .offer-form input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #333;
            font-weight: 500;
        }

        .offer-form button {
            background: #fff;
            color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            width: 100%;
        }

        .offer-form button:hover {
            background: var(--accent-color);
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }

        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 0;
        }

        .offer-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, #fafafa 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: all 0.4s ease;
            text-align: center;
            width: 100%;
            max-width: 350px;
        }

        .offer-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 196, 180, 0.2);
        }

        .offer-card h4 {
            margin: 0 0 1rem;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 1.4rem;
            font-weight: 700;
            text-transform: uppercase;
            text-shadow: 1px 1px 3px rgba(0, 196, 180, 0.3);
        }

        .offer-card p {
            margin: 0.5rem 0;
            font-size: 1.1rem;
            color: #444;
            font-weight: 500;
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

            .offer-section {
                padding: 1.5rem;
                margin: 1.5rem auto;
            }

            .offer-form {
                padding: 1.5rem;
            }

            .offer-form input {
                margin-bottom: 1rem;
            }

            .offers-grid {
                grid-template-columns: 1fr;
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
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="order_history.php">Order History</a>
                <a href="profile.php">Profile</a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="offer-section">
        <h2>Offers & Coupons</h2>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <form method="POST" class="offer-form">
                <label for="code">Coupon Code</label>
                <input type="text" id="code" name="code" required>
                <label for="discount">Discount (%)</label>
                <input type="number" id="discount" name="discount" step="0.01" required>
                <label for="expiry_date">Expiry Date</label>
                <input type="date" id="expiry_date" name="expiry_date" required>
                <label for="min_order_value">Minimum Order Value (৳)</label>
                <input type="number" id="min_order_value" name="min_order_value" step="0.01" required>
                <button type="submit" name="add_coupon">Add Coupon</button>
            </form>
        <?php endif; ?>
        <h3>Available Offers</h3>
        <div class="offers-grid">
            <?php foreach ($coupons as $coupon): ?>
                <div class="offer-card">
                    <h4>Code: <?php echo htmlspecialchars($coupon['code']); ?></h4>
                    <p>Discount: <?php echo htmlspecialchars($coupon['discount']); ?>%</p>
                    <p>Minimum Order: ৳<?php echo htmlspecialchars($coupon['min_order_value']); ?></p>
                    <p>Expires: <?php echo htmlspecialchars($coupon['expiry_date']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <footer>
        <div class="footer-container">
            <div class="footer-column footer-logo">
                <a href="index.php" class="logo">TasteKart</a>
                <p>Delivering joy, one meal at a time. TasteKart brings you the best local flavors with fast, reliable service across Bangladesh.</p>
                <p>Contact us: +880 1234-567890 | support@tastekart.com</p>
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
            <p>© 2025 TasteKart. All rights reserved. Designed inspired by Tuhin.</p>
        </div>
    </footer>
</body>
</html>