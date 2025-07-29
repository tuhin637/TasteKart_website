<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to payment.php
error_log("Step 1: Entering payment.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id());

// Log session data
error_log("Step 2: Session data in payment.php: " . json_encode($_SESSION, JSON_PRETTY_PRINT));

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Step 3: User not logged in, redirecting to login.php");
    $_SESSION['error'] = 'Please log in to continue with payment.';
    session_write_close();
    header("Location: login.php");
    exit;
}

// Set time zone to Asia/Dhaka (UTC+6)
date_default_timezone_set('Asia/Dhaka');
error_log("Step 4: Time zone set to Asia/Dhaka");

// Check if cart exists and is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    error_log("Step 5: Cart is empty or not set in payment.php: " . json_encode($_SESSION['cart']));
    $_SESSION['error'] = 'Your cart is empty. Please add items to proceed.';
    session_write_close();
    header("Location: index.php");
    exit;
}
error_log("Step 6: Cart exists and is not empty");

// Check if delivery address exists
$error = '';
$success = '';
if (!isset($_SESSION['delivery_address']) || empty($_SESSION['delivery_address'])) {
    error_log("Step 7: Delivery address missing in payment.php");
    $error = 'Delivery address is missing. Please go back to checkout.';
}
error_log("Step 8: Delivery address check completed");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && isset($_POST['phone_number']) && isset($_POST['otp_code'])) {
    error_log("Step 9: Processing POST request for payment");
    $cart = $_SESSION['cart'];
    $total_amount = array_sum(array_map(function($item) {
        return $item['price'] * $item['qty'];
    }, $cart));

    // Apply promo code discount if present
    $discount_percentage = isset($_SESSION['discount_percentage']) ? $_SESSION['discount_percentage'] : 0;
    $discount_amount = $total_amount * ($discount_percentage / 100);
    $final_amount = $total_amount - $discount_amount;
    $payment_method = trim($_POST['payment_method'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $otp_code = trim($_POST['otp_code'] ?? '');

    // Get delivery address from session
    $delivery_address = $_SESSION['delivery_address'];

    // Get restaurant_id from cart (assuming all items are from the same restaurant)
    $restaurant_id = $cart[0]['restaurant_id'] ?? 0;

    if (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } elseif (empty($phone_number) || !preg_match('/^\+?1?\d{10,15}$/', $phone_number)) {
        $error = 'Please enter a valid phone number (e.g., +8801234567890).';
    } elseif (empty($otp_code)) {
        $error = 'Please enter the OTP code received.';
    } elseif (empty($delivery_address)) {
        $error = 'Delivery address is missing.';
    } elseif (!$restaurant_id) {
        $error = 'Restaurant information is missing from the cart.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, restaurant_id, total_amount, status, delivery_address, created_at, estimated_delivery) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $stmt->execute([$_SESSION['user_id'], $restaurant_id, $final_amount, 'validating', $delivery_address]);
            $order_id = $pdo->lastInsertId();

            foreach ($cart as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
            }

            // Store phone number and OTP code for manual verification
            $stmt = $pdo->prepare("INSERT INTO payment_transactions (order_id, payment_method, phone_number, otp_code, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $payment_method, $phone_number, $otp_code, $final_amount]);

            $pdo->commit();

            // Clear session data
            unset($_SESSION['cart']);
            unset($_SESSION['promo_code']);
            unset($_SESSION['discount_percentage']);
            unset($_SESSION['delivery_address']);

            $success = 'Payment processed successfully with OTP. Please wait for admin verification.';
            session_write_close();
            header("Location: order_confirmation.php?order_id=" . $order_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error processing payment: ' . $e->getMessage();
            error_log("Payment error: " . $e->getMessage());
        }
    }
}
error_log("Step 10: Preparing to render payment page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Complete your payment on TasteKart with secure OTP verification." />
    <meta name="keywords" content="TasteKart, payment, food delivery, Bangladesh" />
    <meta name="author" content="TasteKart Team" />
    <title>Payment - TasteKart</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00c4b4;
            --primary-dark: #009688;
            --secondary-color: #6a1b9a;
            --accent-color: #ff7f50;
            --card-bg: #f5f5f0;
            --shadow: rgba(0, 0, 0, 0.1);
            --text-color: #333333;
            --gradient: linear-gradient(135deg, #00c4b4, #6a1b9a);
            --gradient-overlay: linear-gradient(135deg, rgba(0, 196, 180, 0.8), rgba(106, 27, 154, 0.8));
            --background-gradient: linear-gradient(135deg, #e6e6fa 0%, #e0f7f9 100%);
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
        .container {
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
        .payment-section {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--shadow);
        }
        .payment-section h2 {
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
        .error, .success {
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 4px;
            animation: fadeIn 0.5s ease-out;
        }
        .error {
            color: #e74c3c;
            background-color: #fff4f4;
        }
        .success {
            color: #27ae60;
            background-color: #e8f5e9;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .order-summary {
            margin: 1rem 0;
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px var(--shadow);
        }
        .order-summary div {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ddd;
        }
        .order-summary div span {
            color: var(--text-color);
        }
        .order-summary .total {
            font-weight: 700;
            color: var(--primary-color);
        }
        .payment-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .payment-form select, .payment-form input {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .payment-form select:focus, .payment-form input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 196, 180, 0.3);
        }
        .payment-form button {
            background: var(--gradient);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 25px;
            font-weight: 600;
            letter-spacing: 1.5px;
            cursor: pointer;
            transition: all 0.4s ease;
        }
        .payment-form button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }
        .payment-form button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        #send-otp-btn {
            background: var(--primary-dark);
        }
        #send-otp-btn:hover {
            background: #00796b;
        }
        #otp-section {
            display: none;
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
            .container {
                padding: 1.5rem;
            }
            .payment-section {
                padding: 1rem;
            }
            .payment-form select, .payment-form input {
                padding: 0.6rem;
            }
            .payment-form button {
                padding: 0.6rem;
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
<?php error_log("Step 12: Starting HTML rendering"); ?>
<header>
    <a href="index.php" class="logo">TasteKart</a>
    <nav>
        <a href="index.php">Home</a>
        <a href="order_history.php">Order History</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <div class="payment-section">
        <?php error_log("Step 13: Rendering payment section"); ?>
        <h2>Complete Your Payment</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php
        error_log("Step 15: Calculating totals");
        $cart = $_SESSION['cart'];
        $total_amount = array_sum(array_map(function($item) {
            return $item['price'] * $item['qty'];
        }, $cart));
        $discount_percentage = isset($_SESSION['discount_percentage']) ? $_SESSION['discount_percentage'] : 0;
        $discount_amount = $total_amount * ($discount_percentage / 100);
        $final_amount = $total_amount - $discount_amount;
        error_log("Step 16: Displaying payment form with total: ৳" . $final_amount . " at " . date('Y-m-d H:i:s'));
        ?>
        <div class="order-summary">
            <div><span>Subtotal</span><span>৳<?php echo number_format($total_amount, 2); ?></span></div>
            <?php if ($discount_amount > 0): ?>
                <div><span>Discount (<?php echo $discount_percentage; ?>%)</span><span>-৳<?php echo number_format($discount_amount, 2); ?></span></div>
            <?php endif; ?>
            <div class="total"><span>Total</span><span>৳<?php echo number_format($final_amount, 2); ?></span></div>
        </div>
        <?php error_log("Step 17: Rendering payment form"); ?>
        <form class="payment-form" method="POST" action="payment.php">
            <select name="payment_method" id="payment-method" required>
                <option value="">Select Payment Method</option>
                <option value="bkash">bKash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
            </select>
            <input type="tel" name="phone_number" id="phone-number" placeholder="Enter Phone Number (e.g., +8801234567890)" required pattern="\+?1?\d{10,15}" title="Please enter a valid phone number (e.g., +8801234567890)">
            <button type="button" id="send-otp-btn">Send OTP</button>
            <div id="otp-section">
                <input type="text" name="otp_code" id="otp-code" placeholder="Enter OTP Code" required>
                <button type="submit" id="pay-now-btn" disabled>Pay Now</button>
            </div>
        </form>
    </div>
</div>

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
document.addEventListener('DOMContentLoaded', function() {
    const sendOtpBtn = document.getElementById('send-otp-btn');
    const otpSection = document.getElementById('otp-section');
    const payNowBtn = document.getElementById('pay-now-btn');
    const phoneNumber = document.getElementById('phone-number');
    const paymentMethod = document.getElementById('payment-method');
    const otpCode = document.getElementById('otp-code');

    if (!sendOtpBtn || !otpSection || !payNowBtn || !phoneNumber || !paymentMethod || !otpCode) {
        console.error('One or more DOM elements not found:', {
            sendOtpBtn, otpSection, payNowBtn, phoneNumber, paymentMethod, otpCode
        });
        return;
    }

    sendOtpBtn.addEventListener('click', function() {
        console.log('Send OTP button clicked at ' + new Date().toISOString());

        // Validate inputs
        const phoneRegex = /^\+?1?\d{10,15}$/;
        if (!phoneNumber.value || !phoneRegex.test(phoneNumber.value)) {
            alert('Please enter a valid phone number (e.g., +8801234567890).');
            return;
        }
        if (!paymentMethod.value) {
            alert('Please select a payment method.');
            return;
        }

        // Disable button to prevent multiple clicks
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = 'Sending...';

        // Simulate sending OTP (replace with actual API call in production)
        console.log(`Simulating OTP sent to ${phoneNumber.value} for ${paymentMethod.value}`);
        alert('OTP sent to your phone number. Please enter the OTP code.');

        // Show OTP input and Pay Now button
        otpSection.style.display = 'block';
        payNowBtn.disabled = true; // Ensure Pay Now is disabled until OTP is entered
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = 'Send OTP';
    });

    otpCode.addEventListener('input', function() {
        console.log('OTP input changed at ' + new Date().toISOString());
        payNowBtn.disabled = this.value.trim() === '';
    });
});
</script>
<?php error_log("Step 18: HTML rendering completed"); ?>
</body>
</html>