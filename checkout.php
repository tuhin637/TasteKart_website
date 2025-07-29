<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log session ID
error_log("Checkout.php session ID: " . session_id() . " at " . date('Y-m-d H:i:s'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in, redirecting to login.php");
    header("Location: login.php");
    exit;
}

// Sync cart from POST if present
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_items'])) {
    $cart = json_decode($_POST['cart_items'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $_SESSION['cart'] = $cart;
        error_log("Cart synced to session from POST in checkout.php: " . json_encode($_SESSION['cart']));
    } else {
        error_log("Failed to decode cart items in checkout.php: " . json_last_error_msg());
    }
}

// Check if cart exists and is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    error_log("Cart is empty or not set in checkout.php: " . json_encode($_SESSION['cart']));
    $_SESSION['error'] = 'Your cart is empty. Please add items to proceed.';
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_address'])) {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $coupon_code = trim($_POST['coupon_code'] ?? '');

    if (empty($delivery_address)) {
        $error = 'Please enter a delivery address.';
    } else {
        // Calculate total amount from cart
        $cart = $_SESSION['cart'];
        $total_amount = array_sum(array_map(function($item) {
            return $item['price'] * $item['qty'];
        }, $cart));

        // Apply coupon if provided
        $discount_percentage = 0;
        if ($coupon_code) {
            try {
                $stmt = $pdo->prepare("SELECT discount FROM coupons WHERE code = ? AND expiry_date >= CURDATE() AND min_order_value <= ?");
                $stmt->execute([$coupon_code, $total_amount]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($coupon) {
                    $discount_percentage = $coupon['discount'];
                    $_SESSION['discount_percentage'] = $discount_percentage;
                    $_SESSION['promo_code'] = $coupon_code;
                } else {
                    $error = 'Invalid or expired coupon code.';
                }
            } catch (PDOException $e) {
                error_log("Coupon query error: " . $e->getMessage());
                $error = 'Error applying coupon. Please try again.';
            }
        }

        if (!$error) {
            // Store delivery address in session
            $_SESSION['delivery_address'] = $delivery_address;

            // Log session data before redirect
            error_log("Checkout completed. Session data before redirect to payment.php: " . json_encode([
                'cart' => $_SESSION['cart'],
                'delivery_address' => $_SESSION['delivery_address'],
                'discount_percentage' => $_SESSION['discount_percentage'] ?? 0,
                'promo_code' => $_SESSION['promo_code'] ?? ''
            ], JSON_PRETTY_PRINT));

            // Force session save
            session_write_close();

            // Redirect to payment.php
            header("Location: payment.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Complete your order checkout on TasteKart. Enter delivery details and apply coupons." />
    <meta name="keywords" content="TasteKart, checkout, food delivery, Bangladesh" />
    <meta name="author" content="TasteKart Team" />
    <title>TasteKart - Checkout</title>
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

        .checkout-section {
            max-width: 600px;
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

        .checkout-section h2 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-size: 2.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }

        .checkout-section form {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--shadow);
        }

        .checkout-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .checkout-section input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .checkout-section input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 196, 180, 0.3);
        }

        .checkout-section button {
            width: 100%;
            background: var(--gradient);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 30px;
            font-weight: 600;
            letter-spacing: 1.5px;
            cursor: pointer;
            transition: all 0.4s ease;
        }

        .checkout-section button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }

        .error {
            color: #e74c3c;
            background-color: #fff4f4;
            padding: 0.5rem;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 1rem;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Footer Styles (Matching index.php) */
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
            header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav a {
                margin: 0.5rem;
                padding: 0.4rem 1rem;
            }

            .checkout-section {
                padding: 1.5rem;
            }

            .checkout-section input {
                padding: 0.6rem;
            }

            .checkout-section button {
                padding: 0.75rem;
            }

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

    <section class="checkout-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color);">Checkout</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="checkout.php">
            <label for="delivery_address">Delivery Address</label>
            <input type="text" id="delivery_address" name="delivery_address" required value="<?php echo isset($_SESSION['delivery_address']) ? htmlspecialchars($_SESSION['delivery_address']) : ''; ?>">
            <label for="coupon_code">Coupon Code (Optional)</label>
            <input type="text" id="coupon_code" name="coupon_code" value="<?php echo isset($_SESSION['promo_code']) ? htmlspecialchars($_SESSION['promo_code']) : ''; ?>">
            <button type="submit">Proceed to Payment</button>
        </form>
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
</body>
</html>