<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log entry to update_order.php
error_log("Entering update_order.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id());

// Check if user is logged in and has restaurant role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'restaurant') {
    error_log("Unauthorized access attempt to update_order.php");
    $_SESSION['error'] = 'Unauthorized access.';
    header("Location: index.php");
    exit;
}

// Set time zone to Asia/Dhaka (UTC+6)
date_default_timezone_set('Asia/Dhaka');

// Handle POST request for status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $restaurant_id = (int)$_SESSION['user_id'];

    // Validate inputs
    $valid_statuses = ['pending', 'received', 'preparing', 'delivered', 'cancelled', 'validating'];
    if ($order_id <= 0) {
        error_log("Invalid order ID: $order_id");
        $_SESSION['error'] = 'Invalid order ID.';
        header("Location: restaurant_admin.php");
        exit;
    }
    if (!in_array($new_status, $valid_statuses)) {
        error_log("Invalid status: $new_status for order ID: $order_id");
        $_SESSION['error'] = 'Invalid status selected.';
        header("Location: restaurant_admin.php");
        exit;
    }

    try {
        // Verify the order belongs to this restaurant
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$order_id, $restaurant_id]);
        if (!$stmt->fetch()) {
            error_log("Order ID $order_id not found or does not belong to restaurant ID $restaurant_id");
            $_SESSION['error'] = 'Order not found or you are not authorized to update it.';
            header("Location: restaurant_admin.php");
            exit;
        }

        // Update the order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        error_log("Order ID $order_id status updated to $new_status by restaurant ID $restaurant_id");

        $_SESSION['success'] = 'Order status updated successfully.';
        header("Location: restaurant_admin.php");
        exit;
    } catch (PDOException $e) {
        error_log("Database error updating order status for order ID $order_id: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating order status. Please try again.';
        header("Location: restaurant_admin.php");
        exit;
    }
}

error_log("Invalid request to update_order.php");
$_SESSION['error'] = 'Invalid request.';
header("Location: restaurant_admin.php");
exit;
?>