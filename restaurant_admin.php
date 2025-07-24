<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to restaurant_admin.php
error_log("Entering restaurant_admin.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));

// Check if user is logged in and has restaurant role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    error_log("Unauthorized access attempt to restaurant_admin.php, user_id: " . ($_SESSION['user_id'] ?? 'none'));
    header("Location: index.php");
    exit;
}

$restaurant_id = (int)$_SESSION['user_id'];

// Handle menu item addition or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $prep_time = (int)$_POST['prep_time'];
        $image = trim($_POST['image']) ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80';

        try {
            $stmt = $pdo->prepare("INSERT INTO menu_items (restaurant_id, name, category, price, prep_time, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$restaurant_id, $name, $category, $price, $prep_time, $image]);
            error_log("Menu item '$name' added by restaurant ID $restaurant_id");
            $_SESSION['success'] = 'Menu item added successfully.';
        } catch (PDOException $e) {
            error_log("Error adding menu item: " . $e->getMessage());
            $_SESSION['error'] = 'Error adding menu item.';
        }
    } elseif (isset($_POST['update_item'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $price = (float)$_POST['price'];
        $prep_time = (int)$_POST['prep_time'];
        $image = trim($_POST['image']);

        try {
            $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, category = ?, price = ?, prep_time = ?, image = ? WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$name, $category, $price, $prep_time, $image, $id, $restaurant_id]);
            error_log("Menu item ID $id updated by restaurant ID $restaurant_id");
            $_SESSION['success'] = 'Menu item updated successfully.';
        } catch (PDOException $e) {
            error_log("Error updating menu item ID $id: " . $e->getMessage());
            $_SESSION['error'] = 'Error updating menu item.';
        }
    }
}

// Fetch menu items
try {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE restaurant_id = ?");
    $stmt->execute([$restaurant_id]);
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched " . count($menuItems) . " menu items for restaurant ID $restaurant_id");
} catch (PDOException $e) {
    error_log("Error fetching menu items: " . $e->getMessage());
    $menuItems = [];
    $_SESSION['error'] = 'Error fetching menu items.';
}

// Fetch confirmed orders count (status = 'delivered' by default)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE restaurant_id = ? AND status = 'delivered'");
    $stmt->execute([$restaurant_id]);
    $confirmedOrdersCount = $stmt->fetchColumn();
    error_log("Fetched confirmed orders count (delivered) for restaurant ID $restaurant_id: $confirmedOrdersCount");
} catch (PDOException $e) {
    error_log("Error fetching confirmed orders count for restaurant ID $restaurant_id: " . $e->getMessage());
    $confirmedOrdersCount = 0;
    $_SESSION['error'] = 'Error fetching confirmed orders count.';
}

// Fetch all orders with detailed logging
try {
    error_log("Attempting to fetch orders for restaurant_id: $restaurant_id");
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.restaurant_id = ? ORDER BY o.created_at DESC");
    $stmt->execute([$restaurant_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($orders)) {
        error_log("No orders found for restaurant ID $restaurant_id. Verifying data...");
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE restaurant_id = ?");
        $countStmt->execute([$restaurant_id]);
        $orderCount = $countStmt->fetchColumn();
        error_log("Verified total orders for restaurant ID $restaurant_id: $orderCount");
    } else {
        error_log("Fetched " . count($orders) . " orders for restaurant ID $restaurant_id. Sample: " . json_encode(array_slice($orders, 0, 1)));
    }
} catch (PDOException $e) {
    error_log("Error fetching orders for restaurant ID $restaurant_id: " . $e->getMessage());
    $orders = [];
    $_SESSION['error'] = 'Error fetching orders. Please ensure the database schema is applied and orders are associated with your restaurant.';
}

// Determine Order History link based on role
$orderHistoryLink = '#';
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $orderHistoryLink = 'admin.php';
            break;
        case 'customer':
            $orderHistoryLink = 'order_history.php';
            break;
        case 'restaurant':
            $orderHistoryLink = 'restaurant_admin.php';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Restaurant Dashboard</title>
    <style>
        <?php 
        try {
            echo file_get_contents('style.css'); 
        } catch (Exception $e) {
            error_log("Failed to load style.css: " . $e->getMessage());
            echo "/* Failed to load styles. Using fallback styles. */ body { font-family: Arial, sans-serif; } .dashboard-section { max-width: 1200px; margin: 3rem auto; padding: 1rem; }";
        }
        ?>
        .dashboard-section { max-width: 1200px; margin: 3rem auto; padding: 1rem; }
        .dashboard-section form { background: var(--card-bg, #fff); padding: 2rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow, rgba(0,0,0,0.1)); margin-bottom: 2rem; }
        .dashboard-section label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .dashboard-section input, .dashboard-section select { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .dashboard-section button { background: var(--primary-color, #d32f2f); color: white; border: none; padding: 0.75rem; border-radius: 25px; font-weight: 700; cursor: pointer; }
        .dashboard-section button:hover { background: var(--primary-dark, #b71c1c); }
        .menu-grid, .order-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .error, .success { text-align: center; margin-bottom: 1rem; padding: 0.5rem; border-radius: 4px; }
        .error { color: #e74c3c; background-color: #ffebee; }
        .success { color: #27ae60; background-color: #e8f5e9; }
        .confirmed-orders { font-size: 1.2rem; font-weight: 600; color: var(--primary-color, #d32f2f); margin-bottom: 1rem; text-align: center; padding: 0.5rem; background: var(--card-bg, #fff); border-radius: 8px; box-shadow: 0 2px 5px var(--shadow, rgba(0,0,0,0.1)); }
        .confirmed-orders.zero { color: #999; }
        .order-history { display: none; }
        .order-history.active { display: block; }
        .toggle-button { background: var(--primary-color, #d32f2f); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 25px; font-weight: 700; cursor: pointer; margin-bottom: 1rem; }
        .toggle-button:hover { background: var(--primary-dark, #b71c1c); }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="<?php echo htmlspecialchars($orderHistoryLink); ?>">Order History</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="dashboard-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Restaurant Dashboard</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <h3>Add New Menu Item</h3>
        <form method="POST">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
            <label for="category">Category</label>
            <select id="category" name="category" required>
                <option value="pizza">Pizza</option>
                <option value="burgers">Burgers</option>
                <option value="asian">Asian</option>
                <option value="desserts">Desserts</option>
                <option value="beverages">Beverages</option>
            </select>
            <label for="price">Price (৳)</label>
            <input type="number" id="price" name="price" step="0.01" required>
            <label for="prep_time">Preparation Time (minutes)</label>
            <input type="number" id="prep_time" name="prep_time" required>
            <label for="image">Image URL</label>
            <input type="text" id="image" name="image" placeholder="Optional">
            <button type="submit" name="add_item">Add Item</button>
        </form>

        <h3>Your Menu Items</h3>
        <div class="menu-grid">
            <?php if (empty($menuItems)): ?>
                <p style="text-align: center; color: #999;">No menu items available.</p>
            <?php else: ?>
                <?php foreach ($menuItems as $item): ?>
                    <div class="menu-card">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p>Category: <?php echo htmlspecialchars($item['category']); ?></p>
                        <p>Price: ৳<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></p>
                        <p>Prep Time: <?php echo htmlspecialchars($item['prep_time']); ?> mins</p>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                            <label for="name_<?php echo $item['id']; ?>">Name</label>
                            <input type="text" id="name_<?php echo $item['id']; ?>" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                            <label for="category_<?php echo $item['id']; ?>">Category</label>
                            <select id="category_<?php echo $item['id']; ?>" name="category" required>
                                <option value="pizza" <?php echo $item['category'] === 'pizza' ? 'selected' : ''; ?>>Pizza</option>
                                <option value="burgers" <?php echo $item['category'] === 'burgers' ? 'selected' : ''; ?>>Burgers</option>
                                <option value="asian" <?php echo $item['category'] === 'asian' ? 'selected' : ''; ?>>Asian</option>
                                <option value="desserts" <?php echo $item['category'] === 'desserts' ? 'selected' : ''; ?>>Desserts</option>
                                <option value="beverages" <?php echo $item['category'] === 'beverages' ? 'selected' : ''; ?>>Beverages</option>
                            </select>
                            <label for="price_<?php echo $item['id']; ?>">Price (৳)</label>
                            <input type="number" id="price_<?php echo $item['id']; ?>" name="price" step="0.01" value="<?php echo htmlspecialchars($item['price']); ?>" required>
                            <label for="prep_time_<?php echo $item['id']; ?>">Preparation Time (minutes)</label>
                            <input type="number" id="prep_time_<?php echo $item['id']; ?>" name="prep_time" value="<?php echo htmlspecialchars($item['prep_time']); ?>" required>
                            <label for="image_<?php echo $item['id']; ?>">Image URL</label>
                            <input type="text" id="image_<?php echo $item['id']; ?>" name="image" value="<?php echo htmlspecialchars($item['image']); ?>">
                            <button type="submit" name="update_item">Update Item</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <h3>Orders</h3>
        <div class="confirmed-orders <?php echo $confirmedOrdersCount == 0 ? 'zero' : ''; ?>">
            Total Confirmed Orders (Delivered): <?php echo htmlspecialchars($confirmedOrdersCount); ?>
            <?php if ($confirmedOrdersCount == 0 && !empty($orders)): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders delivered yet. Update an order status to 'Delivered' using the form below.)</span>
            <?php elseif ($confirmedOrdersCount == 0): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders exist or are delivered yet. Add or update orders below.)</span>
            <?php endif; ?>
        </div>
        <button class="toggle-button" onclick="toggleOrderHistory()">View Orders</button>
        <div class="order-history" id="order-history">
            <div class="order-grid">
                <?php if (empty($orders)): ?>
                    <p style="text-align: center; color: #999;">No orders available for your restaurant.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="menu-card">
                            <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                            <p>Customer: <?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?></p>
                            <p>Total: ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                            <p>Status: <?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="order-grid">
            <?php if (empty($orders)): ?>
                <p style="text-align: center; color: #999;">No orders available for your restaurant.</p>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="menu-card">
                        <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                        <p>Customer: <?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?></p>
                        <p>Total: ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                        <p>Status: <?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></p>
                        <form method="POST" action="update_order.php">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                            <select name="status">
                                <option value="pending" <?php echo isset($order['status']) && $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="received" <?php echo isset($order['status']) && $order['status'] === 'received' ? 'selected' : ''; ?>>Received</option>
                                <option value="preparing" <?php echo isset($order['status']) && $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                <option value="delivered" <?php echo isset($order['status']) && $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo isset($order['status']) && $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="validating" <?php echo isset($order['status']) && $order['status'] === 'validating' ? 'selected' : ''; ?>>Validating</option>
                            </select>
                            <button type="submit" name="update_status">Update Status</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function toggleOrderHistory() {
            const orderHistory = document.getElementById('order-history');
            orderHistory.classList.toggle('active');
        }
    </script>
</body>
</html>