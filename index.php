<?php
session_start();
include 'includes/db.php';

try {
    // Fetch menu items from the database
    $stmt = $pdo->query("SELECT mi.*, u.name as restaurant_name 
                       FROM menu_items mi 
                       JOIN users u ON mi.restaurant_id = u.id 
                       WHERE mi.availability = 1");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($menuItems)) {
        error_log("No menu items found in database.");
    } else {
        foreach ($menuItems as &$item) {
            $item['id'] = (int)$item['id'];
            $item['price'] = (float)$item['price'];
            $item['prep_time'] = (int)$item['prep_time'];
            $item['restaurant_id'] = (int)$item['restaurant_id'];
        }
    }

    // Fetch restaurants for the "Restaurants" link
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'restaurant'");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent orders for logged-in users
    $orders = [];
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, u.name as restaurant_name 
                FROM orders o 
                LEFT JOIN users u ON o.restaurant_id = u.id 
                WHERE o.user_id = ? 
                ORDER BY o.created_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($orders) . " recent orders for user ID $user_id on index.php");
        } catch (PDOException $e) {
            error_log("Error fetching orders for user ID $user_id on index.php: " . $e->getMessage());
            $orders = [];
        }
    }

    // Sync cart from POST if present
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_items'])) {
        $cart = json_decode($_POST['cart_items'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $_SESSION['cart'] = $cart;
            error_log("Cart synced to session from POST: " . json_encode($_SESSION['cart']));
            header("Location: checkout.php");
            exit;
        } else {
            error_log("Failed to decode cart items: " . json_last_error_msg());
        }
    }

    // Determine Order History link based on role
    $orderHistoryLink = '#';
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'admin':
                $orderHistoryLink = 'admin_order_history.php';
                break;
            case 'customer':
                $orderHistoryLink = 'order_history.php';
                break;
            case 'restaurant':
                $orderHistoryLink = 'restaurant_order_history.php';
                break;
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $menuItems = [];
    $restaurants = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Online Food Ordering</title>
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

        nav a, nav button {
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

        nav a:hover, nav button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px) scale(1.05);
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .btn-login, .btn-register {
            background: var(--accent-color);
            border: none;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 1.5px;
        }

        #cart-toggle {
            background: none;
            border: none;
            font-size: 1.6rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        #cart-toggle:hover {
            transform: rotate(15deg) scale(1.1);
            color: #e0f7f9;
        }

        #cart-count {
            position: absolute;
            top: -5px;
            right: -10px;
            background: #fff;
            color: var(--primary-color);
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.9rem;
        }

        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4rem 3rem;
            background: rgba(255, 255, 255, 0.95);
            margin: 2rem auto;
            border-radius: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            max-width: 1400px;
            backdrop-filter: blur(5px);
        }

        .hero-text {
            max-width: 50%;
            padding-right: 3rem;
        }

        .hero-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 2px 2px 8px rgba(0, 196, 180, 0.4);
        }

        .hero-text p {
            font-size: 1.3rem;
            color: #555;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .btn-order {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 30px;
            font-size: 1.2rem;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 1.5px;
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
            transition: all 0.4s ease;
        }

        .btn-order:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 196, 180, 0.6);
        }

        .hero-image {
            width: 50%;
            overflow: hidden;
            border-radius: 25px;
        }

        .hero-slider {
            display: flex;
            width: 100%;
            height: 400px;
            transition: transform 0.7s ease-in-out;
        }

        .hero-slide {
            min-width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .hero-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s ease;
        }

        .hero-slide:hover img {
            transform: scale(1.15);
        }

        .hero-slide::before {
            content: attr(data-text);
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 600;
            text-shadow: 1px 1px 10px rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .hero-slide:hover::before {
            opacity: 1;
        }

        .search-section {
            padding: 3rem 2rem;
            text-align: center;
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            border-radius: 20px;
            margin: 2rem auto;
            max-width: 1400px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.05);
        }

        .location-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 1.5rem;
            background: #fff;
            border-radius: 40px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            margin: 0 auto 2rem;
            border: 2px solid rgba(0, 196, 180, 0.2);
        }

        #location-input {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 40px 0 0 40px;
            font-size: 1.1rem;
            outline: none;
            color: #757575;
            font-weight: 500;
            background: #f9f9f9;
        }

        #locate-me, #find-food {
            padding: 12px 30px;
            border: none;
            border-radius: 0 40px 40px 0;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        #locate-me {
            background: #fff;
            color: var(--primary-color);
            border-left: 1px solid #eee;
        }

        #locate-me:hover {
            background: #f1f1f1;
            transform: translateX(2px);
        }

        #find-food {
            background: var(--gradient);
            color: #fff;
            margin-left: -2px;
        }

        #find-food:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 196, 180, 0.4);
        }

        .categories {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .category {
            padding: 0.8rem 2rem;
            cursor: pointer;
            border-radius: 25px;
            background: #e0eaff;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .category::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .category:hover::after {
            width: 100%;
        }

        .category.active {
            background: var(--gradient);
            color: #fff;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 196, 180, 0.5);
            animation: glow 1.5s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 4px 15px rgba(0, 196, 180, 0.5); }
            50% { box-shadow: 0 4px 25px rgba(0, 196, 180, 0.8); }
        }

        .category:hover {
            background: #d1e0ff;
            transform: translateY(-2px);
        }

        .menu-section {
            padding: 3rem 2rem;
            background: #fff;
            border-radius: 25px;
            margin: 2rem auto;
            max-width: 1400px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .menu-header h2 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 2.5rem;
            font-size: 2.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            justify-items: center;
        }

        .menu-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, #fafafa 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.4s ease;
            width: 100%;
            max-width: 350px;
        }

        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 196, 180, 0.2);
        }

        .menu-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 20px 20px 0 0;
            transition: transform 0.4s ease;
        }

        .menu-card:hover img {
            transform: scale(1.1);
        }

        .menu-card-content {
            padding: 1.5rem;
        }

        .menu-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
            color: var(--primary-color);
            font-weight: 700;
            text-transform: capitalize;
            text-shadow: 1px 1px 3px rgba(0, 196, 180, 0.3);
            position: relative;
            padding-bottom: 5px;
        }

        .menu-title::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .menu-title:hover::after {
            width: 100%;
        }

        .menu-desc {
            font-size: 1rem;
            color: #666;
            margin-bottom: 0.8rem;
            line-height: 1.5;
        }

        .menu-price-preptime {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            font-weight: 600;
            color: #444;
        }

        .add-to-cart-btn {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 1rem;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.4s ease;
            width: 100%;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
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
            font-size: 2.2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
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
            margin-bottom: 1.5rem;
            transition: all 0.4s ease;
        }

        .toggle-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
        }

        .order-history {
            display: none;
        }

        .order-history.active {
            display: grid;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        #cart-sidebar {
            position: fixed;
            top: 0;
            right: -350px;
            width: 350px;
            height: 100%;
            background: linear-gradient(135deg, #fff 0%, #f9f9f9 100%);
            box-shadow: -6px 0 20px rgba(0, 0, 0, 0.15);
            padding: 2rem;
            transition: right 0.4s ease;
            border-left: 1px solid rgba(0, 0, 0, 0.1);
        }

        #cart-sidebar.active {
            right: 0;
        }

        #cart-items {
            max-height: 65vh;
            overflow-y: auto;
            padding-right: 1rem;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1.2rem;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .cart-item:hover {
            transform: translateX(5px);
        }

        .cart-item-info {
            display: flex;
            flex-direction: column;
        }

        .cart-item-name {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .cart-item-qty {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }

        .qty-btn {
            background: none;
            border: 1px solid #ddd;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-weight: 600;
            margin: 0 0.3rem;
            transition: all 0.3s ease;
        }

        .qty-btn:hover {
            background: #f1f1f1;
            transform: scale(1.1);
        }

        .cart-item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .remove-btn {
            background: none;
            border: none;
            color: #ff4444;
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .remove-btn:hover {
            color: #cc0000;
        }

        #cart-total {
            font-weight: 700;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #eee;
            color: var(--primary-color);
            font-size: 1.3rem;
            text-align: right;
        }

        #checkout-btn {
            background: var(--gradient);
            color: #fff;
            border: none;
            padding: 1rem;
            border-radius: 30px;
            width: 100%;
            cursor: pointer;
            margin-top: 1.5rem;
            font-weight: 600;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(0, 196, 180, 0.4);
            transition: all 0.4s ease;
        }

        #checkout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 196, 180, 0.6);
        }

        .restaurants-section {
            padding: 3rem 2rem;
            background: #fff;
            border-radius: 25px;
            margin: 2rem auto;
            max-width: 1400px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .restaurants-header h2 {
            text-align: center;
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            margin-bottom: 2.5rem;
            font-size: 2.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 1px 1px 5px rgba(0, 196, 180, 0.2);
        }

        .restaurants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            justify-items: center;
        }

        .restaurant-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, #fafafa 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.4s ease;
            width: 100%;
            max-width: 350px;
            text-align: center;
            padding: 1.5rem;
            cursor: pointer;
        }

        .restaurant-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 196, 180, 0.2);
        }

        .restaurant-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
            color: var(--primary-color);
            font-weight: 700;
            text-transform: capitalize;
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
            header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }

            nav a, nav button {
                margin: 0.5rem;
                padding: 0.4rem 1rem;
            }

            .hero {
                flex-direction: column;
                padding: 2rem;
                text-align: center;
            }

            .hero-text, .hero-image {
                width: 100%;
                max-width: 100%;
            }

            .hero-slider {
                height: 250px;
            }

            .location-bar {
                flex-direction: column;
                padding: 1rem;
                gap: 0.5rem;
                max-width: 100%;
            }

            #location-input {
                width: 100%;
                border-radius: 40px;
            }

            #locate-me, #find-food {
                width: 100%;
                border-radius: 40px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .menu-card {
                max-width: 100%;
            }

            #cart-sidebar {
                width: 280px;
            }

            .restaurants-grid {
                grid-template-columns: 1fr;
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
            <a href="#restaurants">Restaurants</a>
            <a href="#menu">Menu</a>
            <a href="offers.php">Offers</a>
            <a href="<?php echo htmlspecialchars($orderHistoryLink); ?>">Order History</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'restaurant'): ?>
                    <a href="restaurant_admin.php">Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <button class="btn-login" onclick="window.location.href='login.php'">Login</button>
                <button class="btn-register" onclick="window.location.href='register.php'">Register</button>
            <?php endif; ?>
            <button id="cart-toggle" aria-label="View Cart" title="Cart">
                🛒 <span id="cart-count">0</span>
            </button>
        </nav>
    </header>

    <section class="hero" aria-label="Hero section with promotional content">
        <div class="hero-text">
            <h1>Delicious Meals Delivered</h1>
            <p>Explore a world of flavors with fast and fresh delivery to your doorstep.</p>
            <button class="btn-order" id="explore-menu-btn">Order Now</button>
        </div>
        <div class="hero-image">
            <div class="hero-slider" id="hero-slider">
                <div class="hero-slide" data-text="Explore Delicious Pizzas!">
                    <img src="https://foodibd.com/_next/image?url=%2F_next%2Fstatic%2Fmedia%2Fhero-2.15d15703.png&w=3840&q=75" alt="Delicious Pizza" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
                <div class="hero-slide" data-text="Savor Juicy Burgers!">
                    <img src="https://cdn.create.vista.com/downloads/35bf4216-d74e-4189-b021-6872296da4fb_640.jpeg" alt="Juicy Burger" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
                <div class="hero-slide" data-text="Taste Asian Delights!">
                    <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Asian Cuisine" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
                <div class="hero-slide" data-text="Indulge in Desserts!">
                    <img src="https://img.freepik.com/premium-psd/fresh-delicious-food-discount-offers-instagram-promotion-social-media-template_605333-137.jpg?semt=ais_hybrid&w=740" alt="Dessert Treat" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
                <div class="hero-slide" data-text="Refresh with Beverages!">
                    <img src="https://img.freepik.com/premium-vector/big-deals-food-menu-banner-template_225928-84.jpg" alt="Refreshing Beverage" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
                <div class="hero-slide" data-text="Try Our Special Combo!">
                    <img src="https://img.freepik.com/premium-psd/food-social-media-post-christimas-promotion-template_448714-448.jpg?semt=ais_hybrid&w=740" alt="Special Combo" onerror="this.src='https://via.placeholder.com/1200x400?text=Image+Not+Found'">
                </div>
            </div>
        </div>
    </section>

    <section class="restaurants-section" id="restaurants" aria-label="Restaurants list">
        <div class="restaurants-header">
            <h2>Explore Restaurants</h2>
        </div>
        <div class="restaurants-grid" id="restaurants-grid">
            <?php if (empty($restaurants)): ?>
                <p style="text-align: center; color: #999;">No restaurants found.</p>
            <?php else: ?>
                <?php foreach ($restaurants as $restaurant): ?>
                    <div class="restaurant-card" data-restaurant="<?php echo htmlspecialchars($restaurant['name']); ?>" tabindex="0">
                        <h3 class="restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h3>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>


    <section class="search-section" aria-label="Food search and location">
        <div class="location-bar">
            <input type="text" id="location-input" placeholder="Enter your location" aria-label="Enter your location" />
            <button id="locate-me" aria-label="Locate me"><span class="locate-icon">📍</span> Locate Me</button>
            <button id="find-food" aria-label="Find food">Find Food</button>
        </div>
        <select id="filter-restaurant" class="category" style="padding: 10px 26px; margin-top: 1rem;">
            <option value="">সব রেস্টুরেন্ট</option>
            <?php foreach ($restaurants as $restaurant): ?>
                <option value="<?php echo htmlspecialchars($restaurant['name']); ?>"><?php echo htmlspecialchars($restaurant['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="categories" role="list" aria-label="Food categories filter">
            <div class="category active" data-category="all" role="listitem" tabindex="0">All</div>
            <div class="category" data-category="pizza" role="listitem" tabindex="0">Pizza</div>
            <div class="category" data-category="burgers" role="listitem" tabindex="0">Burgers</div>
            <div class="category" data-category="asian" role="listitem" tabindex="0">Asian</div>
            <div class="category" data-category="desserts" role="listitem" tabindex="0">Desserts</div>
            <div class="category" data-category="beverages" role="listitem" tabindex="0">Beverages</div>
        </div>
    </section>



    <section class="menu-section" id="menu" aria-label="Menu items">
        <div class="menu-header">
            <h2>Popular Dishes</h2>
        </div>
        <div class="menu-grid" id="menu-grid">
            <!-- Menu cards will be populated by JS -->
        </div>
    </section>

    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="dashboard-section">
            <h2>Recent Orders</h2>
            <button class="toggle-button" onclick="toggleOrderHistory()">View Orders</button>
            <div class="order-history" id="order-history">
                <div class="order-grid">
                    <?php if (empty($orders)): ?>
                        <p style="text-align: center; color: #999;">You have no recent orders.</p>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="menu-card">
                                <div class="menu-card-content">
                                    <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                                    <p>Restaurant: <?php echo htmlspecialchars($order['restaurant_name'] ?? 'Unknown Restaurant'); ?></p>
                                    <p>Total: ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                    <p>Status: <?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="dashboard-section" style="text-align: center;">
            <p style="color: #666;">Please <a href="login.php">log in</a> to view your orders.</p>
        </section>
    <?php endif; ?>

    <aside id="cart-sidebar" aria-label="Shopping Cart" aria-hidden="true">
        <header>
            <h3>Cart</h3>
            <button id="close-cart" aria-label="Close cart sidebar">×</button>
        </header>
        <div id="cart-items" tabindex="0">
            <p style="padding: 20px; text-align: center; color: #999;">Your cart is empty</p>
        </div>
        <div id="cart-total" aria-live="polite" aria-atomic="true">
            <span>Total: </span><span id="cart-total-price">৳0.00</span>
        </div>
        <button id="checkout-btn" aria-label="Proceed to checkout">Checkout</button>
    </aside>

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
        const menuItems = <?php echo json_encode($menuItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>;
        let cart = [];

        try {
            const storedCart = localStorage.getItem('cart');
            cart = storedCart ? JSON.parse(storedCart) : [];
        } catch (error) {
            console.error('Error parsing cart from localStorage:', error);
            cart = [];
        }

        const menuGrid = document.getElementById('menu-grid');
        const categories = document.querySelectorAll('.category');
        const filterRestaurant = document.getElementById('filter-restaurant');
        const cartCount = document.getElementById('cart-count');
        const cartSidebar = document.getElementById('cart-sidebar');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotalPrice = document.getElementById('cart-total-price');
        const cartToggleBtn = document.getElementById('cart-toggle');
        const closeCartBtn = document.getElementById('close-cart');
        const checkoutBtn = document.getElementById('checkout-btn');
        const exploreMenuBtn = document.getElementById('explore-menu-btn');
        const locationInput = document.getElementById('location-input');
        const locateMeBtn = document.getElementById('locate-me');
        const findFoodBtn = document.getElementById('find-food');
        const heroSlider = document.getElementById('hero-slider');
        const restaurantsGrid = document.getElementById('restaurants-grid');

        if (!menuGrid || !cartCount || !cartItemsContainer || !cartTotalPrice || !cartSidebar || !cartToggleBtn || !closeCartBtn || !checkoutBtn || !exploreMenuBtn || !locationInput || !locateMeBtn || !findFoodBtn || !heroSlider || !restaurantsGrid) {
            console.error('Critical DOM elements missing. Cannot proceed.');
            alert('একটি ত্রুটি ঘটেছে। পৃষ্ঠাটি সঠিকভাবে লোড হয়নি। দয়া করে আবার চেষ্টা করুন।');
            throw new Error('Missing critical DOM elements');
        }

        let activeCategory = 'all';
        let activeRestaurant = '';
        let currentHeroIndex = 0;
        let intervalIdHero;

        function renderMenuItems() {
            try {
                if (!Array.isArray(menuItems)) {
                    console.error('menuItems is not an array:', menuItems);
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">একটি ত্রুটি ঘটেছে। মেনু আইটেম লোড করা যায়নি।</p>';
                    return;
                }
                if (menuItems.length === 0) {
                    console.warn('menuItems is empty');
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">কোন আইটেম পাওয়া যায়নি। দয়া করে রেস্টুরেন্টে আইটেম যোগ করুন।</p>';
                    return;
                }

                let filteredItems = menuItems;
                if (activeCategory !== 'all') {
                    filteredItems = filteredItems.filter(item => item.category.toLowerCase() === activeCategory);
                }
                if (activeRestaurant) {
                    filteredItems = filteredItems.filter(item => item.restaurant_name.toLowerCase() === activeRestaurant.toLowerCase());
                }

                if (filteredItems.length === 0) {
                    menuGrid.innerHTML = '<p style="text-align:center; color:#999;">কোন আইটেম পাওয়া যায়নি।</p>';
                    return;
                }

                const cardsHtml = filteredItems.map(item => {
                    if (!item.id) {
                        console.warn('Item missing id:', item);
                        return '';
                    }
                    let originalPrice = parseFloat(item.price);
                    let discountedPrice = originalPrice;
                    let discountText = '';
                    if (item.category.toLowerCase() === 'pizza') {
                        discountedPrice = originalPrice * 0.75;
                        discountText = '<span style="color: green;">(25% off)</span>';
                    } else if (item.category.toLowerCase() === 'burgers') {
                        discountedPrice = originalPrice * 0.80;
                        discountText = '<span style="color: green;">(20% off)</span>';
                    }
                    return `
                        <article class="menu-card" tabindex="0" aria-label="${item.name}. মূল্য ৳${discountedPrice.toFixed(2)}">
                            <img src="${item.image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'}" alt="${item.name}" />
                            <div class="menu-card-content">
                                <h3 class="menu-title">${item.name}</h3>
                                <p class="menu-desc">From: ${item.restaurant_name}</p>
                                <div class="menu-price-preptime">
                                    <span>৳${discountedPrice.toFixed(2)} ${discountText}</span>
                                    <span>${item.prep_time} মিনিট</span>
                                </div>
                                <button class="add-to-cart-btn" data-id="${item.id}">কার্টে যোগ করুন</button>
                            </div>
                        </article>
                    `;
                }).join('');
                menuGrid.innerHTML = cardsHtml;

                const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
                if (addToCartButtons.length === 0) {
                    console.warn('No Add to Cart buttons found. Check menu item rendering.');
                }
                addToCartButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = parseInt(btn.getAttribute('data-id'));
                        if (isNaN(id)) {
                            console.error('Invalid item ID:', btn.getAttribute('data-id'));
                            alert('একটি ত্রুটি ঘটেছে। আইটেম আইডি সঠিক নয়।');
                            return;
                        }
                        addToCart(id);
                    });
                });
            } catch (error) {
                console.error('Error rendering menu:', error);
                menuGrid.innerHTML = '<p style="text-align:center; color:#999;">একটি ত্রুটি ঘটেছে। দয়া করে আবার চেষ্টা করুন।</p>';
            }
        }

        categories.forEach(cat => {
            cat.addEventListener('click', () => {
                categories.forEach(c => c.classList.remove('active'));
                cat.classList.add('active');
                activeCategory = cat.dataset.category;
                renderMenuItems();
            });
            cat.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    cat.click();
                }
            });
        });

        filterRestaurant.addEventListener('change', () => {
            activeRestaurant = filterRestaurant.value;
            renderMenuItems();
        });

        function addToCart(itemId) {
            try {
                console.log('Attempting to add item with ID:', itemId);
                const item = menuItems.find(m => m.id === itemId);
                if (!item) {
                    console.warn('Item not found for ID:', itemId, 'Available IDs:', menuItems.map(m => m.id));
                    alert('আইটেমটি পাওয়া যায়নি। দয়া করে আবার চেষ্টা করুন।');
                    return;
                }
                console.log('Found item:', item);
                let originalPrice = parseFloat(item.price);
                let cartPrice = originalPrice;
                if (item.category.toLowerCase() === 'pizza') {
                    cartPrice = originalPrice * 0.75;
                } else if (item.category.toLowerCase() === 'burgers') {
                    cartPrice = originalPrice * 0.80;
                }
                const existing = cart.find(c => c.id === itemId);
                if (existing) {
                    existing.qty++;
                } else {
                    cart.push({ id: itemId, qty: 1, price: cartPrice, name: item.name, restaurant_id: item.restaurant_id });
                }
                try {
                    localStorage.setItem('cart', JSON.stringify(cart));
                    console.log('Cart updated in localStorage:', cart);
                } catch (error) {
                    console.error('Error saving cart to localStorage:', error);
                }
                updateCartUI();
                openCart();
            } catch (error) {
                console.error('Error in addToCart:', error);
                alert('কার্টে যোগ করতে একটি ত্রুটি ঘটেছে।');
            }
        }

        function updateCartUI() {
            try {
                cartCount.textContent = cart.reduce((acc, c) => acc + c.qty, 0) || 0;
                if (cart.length === 0) {
                    cartItemsContainer.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">আপনার কার্ট খালি।</p>';
                    cartTotalPrice.textContent = '৳0.00';
                    return;
                }
                let cartHtml = '';
                cart.forEach(item => {
                    cartHtml += `
                        <div class="cart-item" role="listitem" aria-label="${item.qty} x ${item.name}, মূল্য ৳${(item.price * item.qty).toFixed(2)}">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-qty">পরিমাণ: 
                                    <button class="qty-btn decrease" data-id="${item.id}">-</button>
                                    <span>${item.qty}</span>
                                    <button class="qty-btn increase" data-id="${item.id}">+</button>
                                </div>
                            </div>
                            <div>
                                <div class="cart-item-price">৳${(item.price * item.qty).toFixed(2)}</div>
                                <button class="remove-btn" data-id="${item.id}">Remove</button>
                            </div>
                        </div>
                    `;
                });
                cartItemsContainer.innerHTML = cartHtml;
                const totalPrice = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
                cartTotalPrice.textContent = '৳' + totalPrice.toFixed(2);

                const removeButtons = document.querySelectorAll('.remove-btn');
                removeButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = parseInt(btn.getAttribute('data-id'));
                        if (isNaN(id)) {
                            console.error('Invalid item ID for remove:', btn.getAttribute('data-id'));
                            return;
                        }
                        cart = cart.filter(c => c.id !== id);
                        try {
                            localStorage.setItem('cart', JSON.stringify(cart));
                        } catch (error) {
                            console.error('Error saving cart to localStorage:', error);
                        }
                        updateCartUI();
                    });
                });

                const qtyButtons = document.querySelectorAll('.qty-btn');
                qtyButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = parseInt(btn.getAttribute('data-id'));
                        const isIncrease = btn.classList.contains('increase');
                        const item = cart.find(c => c.id === id);
                        if (item) {
                            if (isIncrease) {
                                item.qty++;
                            } else {
                                item.qty = Math.max(1, item.qty - 1);
                            }
                            try {
                                localStorage.setItem('cart', JSON.stringify(cart));
                            } catch (error) {
                                console.error('Error saving cart to localStorage:', error);
                            }
                            updateCartUI();
                        }
                    });
                });
            } catch (error) {
                console.error('Error in updateCartUI:', error);
                cartItemsContainer.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">কার্ট আপডেট করতে একটি ত্রুটি ঘটেছে।</p>';
            }
        }

        function openCart() {
            try {
                cartSidebar.classList.add('active');
                cartSidebar.setAttribute('aria-hidden', 'false');
                cartSidebar.focus();
            } catch (error) {
                console.error('Error in openCart:', error);
            }
        }

        function closeCart() {
            try {
                cartSidebar.classList.remove('active');
                cartSidebar.setAttribute('aria-hidden', 'true');
                cartToggleBtn.focus();
            } catch (error) {
                console.error('Error in closeCart:', error);
            }
        }

        cartToggleBtn.addEventListener('click', () => {
            if (cartSidebar.classList.contains('active')) {
                closeCart();
            } else {
                openCart();
            }
        });

        closeCartBtn.addEventListener('click', closeCart);

        checkoutBtn.addEventListener('click', () => {
            try {
                if (cart.length === 0) {
                    alert('আপনার কার্ট খালি। আইটেম যোগ করুন।');
                    return;
                }
                if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
                    alert('চেকআউট করতে লগইন করুন।');
                    window.location.href = 'login.php';
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'checkout.php';
                const cartInput = document.createElement('input');
                cartInput.type = 'hidden';
                cartInput.name = 'cart_items';
                cartInput.value = JSON.stringify(cart);
                form.appendChild(cartInput);
                document.body.appendChild(form);
                form.submit();
            } catch (error) {
                console.error('Error in checkout:', error);
                alert('চেকআউট প্রক্রিয়ায় একটি ত্রুটি ঘটেছে।');
            }
        });

        exploreMenuBtn.addEventListener('click', () => {
            document.getElementById('menu').scrollIntoView({ behavior: 'smooth' });
        });

        function toggleOrderHistory() {
            const orderHistory = document.getElementById('order-history');
            orderHistory.classList.toggle('active');
        }

        locateMeBtn.addEventListener('click', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        locationInput.value = `Lat: ${latitude.toFixed(6)}, Long: ${longitude.toFixed(6)}`;
                    },
                    (error) => {
                        alert('Unable to retrieve your location. Please ensure location services are enabled and permissions are granted. Error: ' + error.message);
                        locationInput.value = 'Location not available';
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser. Please enter your location manually.');
                locationInput.value = 'Location not supported';
            }
        });

        findFoodBtn.addEventListener('click', () => {
            const address = locationInput.value.trim();
            if (address) {
                document.getElementById('menu').scrollIntoView({ behavior: 'smooth' });
            } else {
                alert('Please enter your location or use "Locate me" to find food.');
            }
        });

        if (restaurantsGrid) {
            const restaurantCards = document.querySelectorAll('.restaurant-card');
            restaurantCards.forEach(card => {
                card.addEventListener('click', () => {
                    const restaurantName = card.getAttribute('data-restaurant');
                    activeRestaurant = restaurantName;
                    filterRestaurant.value = restaurantName; // Sync with dropdown
                    renderMenuItems();
                    document.getElementById('menu').scrollIntoView({ behavior: 'smooth' });
                });
                card.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        card.click();
                    }
                });
            });
        }

        function cycleHero() {
            const heroSlides = document.querySelectorAll('.hero-slide');
            currentHeroIndex = (currentHeroIndex + 1) % heroSlides.length;
            const offset = -currentHeroIndex * 100;
            heroSlider.style.transform = `translateX(${offset}%)`;
        }

        function startHeroCycle() {
            intervalIdHero = setInterval(cycleHero, 5000);
        }

        function pauseHeroCycle() {
            clearInterval(intervalIdHero);
        }

        heroSlider.addEventListener('mouseover', pauseHeroCycle);
        heroSlider.addEventListener('mouseout', startHeroCycle);

        startHeroCycle();

        try {
            renderMenuItems();
            updateCartUI();
        } catch (error) {
            console.error('Error during initial load:', error);
        }
    </script>
</body>
</html>