<?php
session_start();
include 'includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

error_log("Entering restaurant_order_history.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    error_log("Unauthorized access attempt to restaurant_order_history.php, user_id: " . ($_SESSION['user_id'] ?? 'none'));
    $_SESSION['error'] = 'Please log in as a restaurant to view order history.';
    header("Location: index.php");
    exit;
}

$restaurant_id = (int)$_SESSION['user_id'];

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

$orderHistoryLink = 'restaurant_order_history.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Restaurant Order History</title>
    <style>
        <?php 
        try {
            echo file_get_contents('style.css'); 
        } catch (Exception $e) {
            error_log("Failed to load style.css: " . $e->getMessage());
            echo "/* Failed to load styles. Using fallback styles. */ body { font-family: Arial, sans-serif; } .dashboard-section { max-width: 1200px; margin: 3rem auto; padding: 1rem; }";
        }
        ?>
        .dashboard-section {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 1rem;
        }
        .error, .success {
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 4px;
        }
        .error {
            color: #e74c3c;
            background-color: #ffebee;
        }
        .success {
            color: #27ae60;
            background-color: #e8f5e9;
        }
        .confirmed-orders {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color, #d32f2f);
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            background: var(--card-bg, #fff);
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow, rgba(0,0,0,0.1));
        }
        .confirmed-orders.zero {
            color: #999;
        }
        .order-table-container {
            overflow-x: auto;
            background: var(--card-bg, #fff);
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow, rgba(0,0,0,0.1));
            padding: 1rem;
            margin-bottom: 2rem;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }
        .order-table th, .order-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .order-table th {
            background: var(--primary-color, #d32f2f);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .order-table tr {
            transition: background 0.2s ease, transform 0.2s ease;
        }
        .order-table tr:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        .order-table td {
            color: #333;
        }
        .order-table .status {
            text-transform: capitalize;
            font-weight: 600;
            color: var(--primary-dark, #b71c1c);
        }
        .order-table select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .order-table button {
            background: var(--primary-color, #d32f2f);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .order-table button:hover {
            background: var(--primary-dark, #b71c1c);
        }
        @media (max-width: 768px) {
            .order-table-container {
                overflow-x: auto;
            }
            .order-table th, .order-table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            .order-table select, .order-table button {
                width: 100%;
                padding: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="restaurant_admin.php">Dashboard</a>
            <a href="<?php echo htmlspecialchars($orderHistoryLink); ?>">Order History</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="dashboard-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Restaurant Order History</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="confirmed-orders <?php echo $confirmedOrdersCount == 0 ? 'zero' : ''; ?>">
            Total Confirmed Orders (Delivered): <?php echo htmlspecialchars($confirmedOrdersCount); ?>
            <?php if ($confirmedOrdersCount == 0 && !empty($orders)): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders delivered yet. Update an order status to 'Delivered' using the form below.)</span>
            <?php elseif ($confirmedOrdersCount == 0): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders exist or are delivered yet. Update orders below.)</span>
            <?php endif; ?>
        </div>

        <div class="order-table-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 2rem;">
                                No orders available for your restaurant.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))); ?></td>
                                <td>৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td class="status"><?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></td>
                                <td>
                                    <form method="POST" action="update_order.php" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                        <select name="status">
                                            <option value="pending" <?php echo isset($order['status']) && $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="received" <?php echo isset($order['status']) && $order['status'] === 'received' ? 'selected' : ''; ?>>Received</option>
                                            <option value="preparing" <?php echo isset($order['status']) && $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                            <option value="delivered" <?php echo isset($order['status']) && $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo isset($order['status']) && $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="validating" <?php echo isset($order['status']) && $order['status'] === 'validating' ? 'selected' : ''; ?>>Validating</option>
                                        </select>
                                        <button type="submit" name="update_status">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</body>
</html>