<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

error_log("Entering review.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", order_id: " . ($_GET['order_id'] ?? 'none'));

// Check if user is logged in and order ID is provided
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    error_log("Unauthorized access or missing order_id in review.php");
    $_SESSION['error'] = 'Please log in and select an order to review.';
    header("Location: index.php");
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];

// Fetch order details
try {
    $stmt = $pdo->prepare("SELECT o.*, u.name as restaurant_name FROM orders o JOIN users u ON o.restaurant_id = u.id WHERE o.id = ? AND o.user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        error_log("Order ID $order_id not found or not authorized for user ID $user_id");
        $_SESSION['error'] = 'Order not found or you are not authorized to review it.';
        header("Location: order_history.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error fetching order details for order ID $order_id: " . $e->getMessage());
    $_SESSION['error'] = 'Error fetching order details. Please try again later.';
    header("Location: order_history.php");
    exit;
}

// Check if review already exists
try {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    if ($stmt->fetch()) {
        error_log("Review already exists for order ID $order_id and user ID $user_id");
        $_SESSION['error'] = 'You have already submitted a review for this order.';
        header("Location: order_history.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error checking existing review for order ID $order_id: " . $e->getMessage());
    $_SESSION['error'] = 'Error fetching reviews. Please try again later.';
    header("Location: order_history.php");
    exit;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if ($rating < 1 || $rating > 5) {
        error_log("Invalid rating $rating for order ID $order_id");
        $_SESSION['error'] = 'Rating must be between 1 and 5.';
        header("Location: review.php?order_id=" . $order_id);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reviews (order_id, user_id, restaurant_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$order_id, $user_id, $order['restaurant_id'], $rating, $comment]);
        error_log("Review submitted successfully for order ID $order_id by user ID $user_id");
        $_SESSION['success'] = 'Review submitted successfully.';
        header("Location: order_history.php");
        exit;
    } catch (PDOException $e) {
        error_log("Database error submitting review for order ID $order_id: " . $e->getMessage());
        $_SESSION['error'] = 'Error submitting review. Please try again later. Error: ' . $e->getMessage();
        header("Location: review.php?order_id=" . $order_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Leave a review for your TasteKart order." />
    <meta name="keywords" content="TasteKart, review, food delivery, Bangladesh" />
    <meta name="author" content="TasteKart Team" />
    <title>Review Your Order - TasteKart</title>
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
        .review-container {
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
        .review-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--shadow);
        }
        .review-card h2 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }
        .review-card h3 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 1rem;
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
        .review-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .review-form label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .review-form select, .review-form textarea {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .review-form select:focus, .review-form textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 196, 180, 0.3);
        }
        .review-form button {
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
        .review-form button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }
        .reviews-section {
            margin-top: 2rem;
        }
        .reviews-section h3 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 1rem;
        }
        .reviews-section p {
            color: var(--text-color);
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
            .review-container {
                padding: 1.5rem;
            }
            .review-card {
                padding: 1rem;
            }
            .review-form select, .review-form textarea {
                padding: 0.6rem;
            }
            .review-form button {
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
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="order_history.php">Order History</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="review-container">
        <div class="review-card">
            <h2>Review Your Order</h2>
            <h3>Order #<?php echo htmlspecialchars($order_id); ?> from <?php echo htmlspecialchars($order['restaurant_name']); ?></h3>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form class="review-form" method="POST" action="review.php?order_id=<?php echo $order_id; ?>">
                <label for="rating">Rating (1-5)</label>
                <select name="rating" id="rating" required>
                    <option value="">Select a rating</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <label for="comment">Comment (Optional)</label>
                <textarea name="comment" id="comment" rows="4" placeholder="Share your experience..."></textarea>
                <button type="submit">Submit Review</button>
            </form>

            <div class="reviews-section">
                <h3>Reviews for <?php echo htmlspecialchars($order['restaurant_name']); ?></h3>
                <p>No reviews yet for this restaurant.</p>
            </div>
        </div>
    </div>

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
</body>
</html>