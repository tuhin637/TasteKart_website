<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to admin_order_history.php
error_log("Entering admin_order_history.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt to admin_order_history.php, user_id: " . ($_SESSION['user_id'] ?? 'none'));
    $_SESSION['error'] = 'Please log in as an admin to access this page.';
    header("Location: index.php");
    exit;
}

// Fetch total orders count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders");
    $stmt->execute();
    $totalOrders = $stmt->fetchColumn();
    error_log("Fetched total orders count: $totalOrders");
} catch (PDOException $e) {
    error_log("Error fetching total orders count: " . $e->getMessage());
    $totalOrders = 0;
    $_SESSION['error'] = 'Error fetching total orders count.';
}

// Fetch confirmed orders count (status = 'delivered')
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'delivered'");
    $stmt->execute();
    $confirmedOrdersCount = $stmt->fetchColumn();
    error_log("Fetched confirmed orders count (delivered) system-wide: $confirmedOrdersCount");
} catch (PDOException $e) {
    error_log("Error fetching confirmed orders count: " . $e->getMessage());
    $confirmedOrdersCount = 0;
    $_SESSION['error'] = 'Error fetching confirmed orders count.';
}

// Fetch all orders with customer and restaurant names
try {
    error_log("Attempting to fetch all orders system-wide");
    $stmt = $pdo->prepare("
        SELECT o.*, 
               uc.name as customer_name, 
               ur.name as restaurant_name 
        FROM orders o 
        LEFT JOIN users uc ON o.user_id = uc.id 
        LEFT JOIN users ur ON o.restaurant_id = ur.id 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($orders)) {
        error_log("No orders found system-wide. Verifying data...");
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders");
        $countStmt->execute();
        $orderCount = $countStmt->fetchColumn();
        error_log("Verified total orders system-wide: $orderCount");
    } else {
        error_log("Fetched " . count($orders) . " orders system-wide. Sample: " . json_encode(array_slice($orders, 0, 1)));
    }
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $_SESSION['error'] = 'Error fetching orders. Please ensure the database schema is applied.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Admin Order History</title>
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
        .error, .success { text-align: center; margin-bottom: 1rem; padding: 0.5rem; border-radius: 4px; }
        .error { color: #e74c3c; background-color: #ffebee; }
        .success { color: #27ae60; background-color: #e8f5e9; }
        .confirmed-orders { font-size: 1.2rem; font-weight: 600; color: var(--primary-color, #d32f2f); margin-bottom: 1rem; text-align: center; padding: 0.5rem; background: var(--card-bg, #fff); border-radius: 8px; box-shadow: 0 2px 5px var(--shadow, rgba(0,0,0,0.1)); }
        .confirmed-orders.zero { color: #999; }
        .order-table-container { overflow-x: auto; background: var(--card-bg, #fff); border-radius: 10px; box-shadow: 0 4px 12px var(--shadow, rgba(0,0,0,0.1)); padding: 1rem; margin-bottom: 2rem; }
        .order-table { width: 100%; border-collapse: collapse; font-size: 1rem; }
        .order-table th, .order-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
        .order-table th { background: var(--primary-color, #d32f2f); color: white; font-weight: 700; text-transform: uppercase; font-size: 0.9rem; }
        .order-table tr { transition: background 0.2s ease, transform 0.2s ease; }
        .order-table tr:hover { background: #f8f9fa; transform: translateY(-2px); }
        .order-table td { color: #333; }
        .order-table .status { text-transform: capitalize; font-weight: 600; color: var(--primary-dark, #b71c1c); }
        @media (max-width: 768px) {
            .order-table-container { overflow-x: auto; }
            .order-table th, .order-table td { padding: 0.75rem; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_order_history.php">Order History</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="dashboard-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Admin Order History</h2>

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
            <?php if ($confirmedOrdersCount == 0 && $totalOrders > 0): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders delivered yet.)</span>
            <?php elseif ($confirmedOrdersCount == 0): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders exist or are delivered yet.)</span>
            <?php endif; ?>
        </div>

        <div class="order-table-container">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Restaurant</th>
                        <th>Order Date</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 2rem;">
                                No orders available in the system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?></td>
                                <td><?php echo htmlspecialchars($order['restaurant_name'] ?? 'Unknown Restaurant'); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))); ?></td>
                                <td>৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td class="status"><?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</body>
</html>