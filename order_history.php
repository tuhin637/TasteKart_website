<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to order_history.php
error_log("Entering order_history.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));

// Check if user is logged in and has customer role
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    error_log("Unauthorized access attempt to order_history.php, user_id: " . ($_SESSION['user_id'] ?? 'none'));
    $_SESSION['error'] = 'Please log in as a customer to view your order history.';
    header("Location: index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch orders for the customer
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as restaurant_name 
        FROM orders o 
        LEFT JOIN users u ON o.restaurant_id = u.id 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($orders)) {
        error_log("No orders found for user ID $user_id");
    } else {
        error_log("Fetched " . count($orders) . " orders for user ID $user_id");
    }
} catch (PDOException $e) {
    error_log("Error fetching orders for user ID $user_id: " . $e->getMessage());
    $orders = [];
    $_SESSION['error'] = 'Error fetching your order history. Please try again later.';
}

// Check for existing reviews to disable review buttons
$reviewedOrders = [];
if (!empty($orders)) {
    try {
        $stmt = $pdo->prepare("SELECT order_id FROM reviews WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $reviewedOrders = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        error_log("Fetched " . count($reviewedOrders) . " reviewed orders for user ID $user_id");
    } catch (PDOException $e) {
        error_log("Error fetching reviewed orders for user ID $user_id: " . $e->getMessage());
        $_SESSION['error'] = 'Error checking existing reviews.';
    }
}

// Determine Order History and Orders link based on role
$orderLink = '#';
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $orderLink = 'admin.php';
            break;
        case 'customer':
            $orderLink = 'order_history.php';
            break;
        case 'restaurant':
            $orderLink = 'restaurant_admin.php';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="View your order history on TasteKart. Track past orders and leave reviews for delivered meals." />
    <meta name="keywords" content="TasteKart, order history, food delivery, Bangladesh" />
    <meta name="author" content="TasteKart Team" />
    <title>TasteKart - Order History</title>
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

        .dashboard-section {
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

        .dashboard-section h2 {
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
            margin: 1rem auto;
            padding: 1rem;
            border-radius: 10px;
            max-width: 600px;
            font-weight: 500;
        }

        .error {
            background: #fff4f4;
            color: #e74c3c;
        }

        .success {
            background: #e8f5e9;
            color: #27ae60;
        }

        .toggle-button {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 30px;
            font-weight: 600;
            letter-spacing: 1.5px;
            cursor: pointer;
            margin: 0 auto 1.5rem;
            display: block;
            transition: all 0.4s ease;
        }

        .toggle-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }

        .order-history {
            display: none;
        }

        .order-history.active {
            display: block;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .order-table-container {
            overflow-x: auto;
            background: linear-gradient(135deg, var(--card-bg) 0%, #fafafa 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .order-table th, .order-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .order-table th {
            background: var(--gradient);
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }

        .order-table tr {
            transition: all 0.3s ease;
        }

        .order-table tr:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 196, 180, 0.2);
        }

        .order-table td {
            color: var(--text-color);
        }

        .order-table .status {
            text-transform: capitalize;
            font-weight: 600;
            color: var(--primary-color);
        }

        .review-button {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 25px;
            font-weight: 600;
            letter-spacing: 1px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.4s ease;
        }

        .review-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }

        .review-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

            .dashboard-section {
                padding: 1.5rem;
            }

            .order-table th, .order-table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .review-button {
                width: 100%;
                padding: 0.6rem;
            }
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
            <a href="<?php echo htmlspecialchars($orderLink); ?>">Order History</a>
            <a href="<?php echo htmlspecialchars($orderLink); ?>">Orders</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="dashboard-section" aria-label="Order history section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Your Order History</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <button class="toggle-button" onclick="toggleOrderHistory()">View Orders</button>
        <div class="order-history" id="order-history" aria-live="polite">
            <div class="order-table-container">
                <table class="order-table" role="grid">
                    <thead>
                        <tr>
                            <th scope="col">Order ID</th>
                            <th scope="col">Restaurant</th>
                            <th scope="col">Total</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #999; padding: 2rem;">
                                    You have no order history yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr role="row">
                                    <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['restaurant_name'] ?? 'Unknown Restaurant'); ?></td>
                                    <td>৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                    <td class="status"><?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <?php if ($order['status'] === 'delivered'): ?>
                                            <?php if (in_array($order['id'], $reviewedOrders)): ?>
                                                <a href="#" class="review-button" disabled aria-disabled="true">Review Submitted</a>
                                            <?php else: ?>
                                                <a href="review.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="review-button" aria-label="Leave a review for order <?php echo htmlspecialchars($order['id']); ?>">Leave a Review</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-container">
            <div class="footer-column footer-logo">
                <a href="index.php" class="logo">TasteKart</a>
                <p>Delivering joy, one meal at a time. TasteKart brings you the best local flavors with fast, reliable service across Bangladesh.</p>
                <p>Contact us: +880 01792920637 | support@tastekart.com</p>
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
            if (orderHistory.classList.contains('active')) {
                orderHistory.querySelector('.order-table').focus();
            }
        }

        // Focus trapping for accessibility
        function trapFocus(element) {
            const focusable = element.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            element.addEventListener('keydown', e => {
                if (e.key === 'Tab') {
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        }

        const orderHistory = document.getElementById('order-history');
        if (orderHistory) {
            trapFocus(orderHistory);
        }
    </script>
</body>
</html>