<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to admin.php
error_log("Entering admin_dashboard.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt to admin_dashboard.php, user_id: " . ($_SESSION['user_id'] ?? 'none'));
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

// Fetch total revenue
try {
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders");
    $stmt->execute();
    $totalRevenue = $stmt->fetchColumn() ?? 0;
    error_log("Fetched total revenue: ৳$totalRevenue");
} catch (PDOException $e) {
    error_log("Error fetching total revenue: " . $e->getMessage());
    $totalRevenue = 0;
    $_SESSION['error'] = 'Error fetching total revenue.';
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

// Fetch order trends (daily count) for the last two days
try {
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as order_date, COUNT(*) as order_count 
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY) 
        GROUP BY DATE(created_at)
    ");
    $stmt->execute();
    $orderTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Format dates for better readability (e.g., "06/04" instead of "2025-06-04")
    foreach ($orderTrends as &$trend) {
        $trend['order_date'] = date('m/d', strtotime($trend['order_date']));
    }
    unset($trend);
    error_log("Fetched order trends: " . json_encode($orderTrends));
} catch (PDOException $e) {
    error_log("Error fetching order trends: " . $e->getMessage());
    $orderTrends = [];
    $_SESSION['error'] = 'Error fetching order trends.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>TasteKart - Admin Dashboard</title>
    <!-- Include Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .dashboard-card { background: var(--card-bg, #fff); padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow, rgba(0,0,0,0.1)); text-align: center; margin-bottom: 1.5rem; }
        .dashboard-card h3 { margin: 0 0 0.5rem; color: var(--primary-color, #d32f2f); }
        .dashboard-card p { margin: 0; font-size: 1.2rem; color: #333; }
        .order-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; }
        .menu-card { background: var(--card-bg, #fff); padding: 1.5rem; border-radius: 15px; box-shadow: 0 5px 15px var(--shadow, rgba(0,0,0,0.1)); }
        .menu-card h3 { margin-top: 0; color: #333; }
        .menu-card p { margin: 0.5rem 0; color: #666; }
        .error, .success { text-align: center; margin-bottom: 1rem; padding: 0.5rem; border-radius: 4px; }
        .error { color: #e74c3c; background-color: #ffebee; }
        .success { color: #27ae60; background-color: #e8f5e9; }
        .confirmed-orders { font-size: 1.2rem; font-weight: 600; color: var(--primary-color, #d32f2f); margin-bottom: 1rem; text-align: center; padding: 0.5rem; background: var(--card-bg, #fff); border-radius: 8px; box-shadow: 0 2px 5px var(--shadow, rgba(0,0,0,0.1)); }
        .confirmed-orders.zero { color: #999; }
        .order-history { display: none; }
        .order-history.active { display: block; }
        .toggle-button { background: var(--primary-color, #d32f2f); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 25px; font-weight: 700; cursor: pointer; margin-bottom: 1rem; }
        .toggle-button:hover { background: var(--primary-dark, #b71c1c); }
        #orderTrendsChart { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">TasteKart</a>
        <nav>
            <a href="index.php">Home</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <section class="dashboard-section">
        <h2 style="text-align: center; margin-bottom: 1.5rem; color: var(--primary-color, #d32f2f);">Admin Dashboard</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="dashboard-card">
            <h3>Total Orders</h3>
            <p><?php echo htmlspecialchars($totalOrders); ?></p>
        </div>
        <div class="dashboard-card">
            <h3>Total Revenue (৳)</h3>
            <p><?php echo htmlspecialchars(number_format($totalRevenue, 2)); ?></p>
        </div>
        <div class="dashboard-card">
            <h3>Order Trends</h3>
            <?php if (!empty($orderTrends)): ?>
                <div id="orderTrendsChart">
                    <canvas id="chart-canvas"></canvas>
                </div>
                <script>
                    const ctx = document.getElementById('chart-canvas').getContext('2d');
                    const orderTrendsData = {
                        labels: <?php echo json_encode(array_column($orderTrends, 'order_date')); ?>,
                        datasets: [{
                            label: 'Orders per Day',
                            data: <?php echo json_encode(array_column($orderTrends, 'order_count')); ?>,
                            fill: false,
                            borderColor: '#d32f2f',
                            tension: 0.1
                        }]
                    };
                    const orderTrendsChart = new Chart(ctx, {
                        type: 'line',
                        data: orderTrendsData,
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Number of Orders'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Date (MM/DD)'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });
                </script>
            <?php else: ?>
                <p style="text-align: center; color: #999;">No order trends available for the last 2 days.</p>
            <?php endif; ?>
        </div>

        <div class="confirmed-orders <?php echo $confirmedOrdersCount == 0 ? 'zero' : ''; ?>">
            Total Confirmed Orders (Delivered): <?php echo htmlspecialchars($confirmedOrdersCount); ?>
            <?php if ($confirmedOrdersCount == 0 && !empty($orders)): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders delivered yet.)</span>
            <?php elseif ($confirmedOrdersCount == 0): ?>
                <br><span style="font-size: 0.9rem; color: #666;">(No orders exist or are delivered yet.)</span>
            <?php endif; ?>
        </div>
        <button class="toggle-button" onclick="toggleOrderHistory()">View Orders</button>
        <div class="order-history" id="order-history">
            <div class="order-grid">
                <?php if (empty($orders)): ?>
                    <p style="text-align: center; color: #999;">No orders available in the system.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="menu-card">
                            <h3>Order #<?php echo htmlspecialchars($order['id']); ?></h3>
                            <p>Customer: <?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?></p>
                            <p>Restaurant: <?php echo htmlspecialchars($order['restaurant_name'] ?? 'Unknown Restaurant'); ?></p>
                            <p>Total: ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                            <p>Status: <?php echo htmlspecialchars($order['status'] ?? 'Unknown'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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