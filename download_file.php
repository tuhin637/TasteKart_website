<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

error_log("Entering download_file.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id() . ", order_id: " . ($_GET['order_id'] ?? 'none') . ", type: " . ($_GET['type'] ?? 'none'));

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id']) || !isset($_GET['type'])) {
    error_log("Unauthorized access or missing parameters in download_file.php");
    header("Location: index.php");
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = (int)$_SESSION['user_id'];
$type = $_GET['type'];

try {
    $stmt = $pdo->prepare("SELECT o.*, u.name as restaurant_name FROM orders o JOIN users u ON o.restaurant_id = u.id WHERE o.id = ? AND o.user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        error_log("Order ID $order_id not found or not authorized for user ID $user_id in download_file.php");
        header("Location: order_confirmation.php?order_id=$order_id");
        exit;
    }

    $stmt = $pdo->prepare("SELECT oi.*, mi.name as item_name FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT latitude, longitude FROM delivery_locations WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $coords = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in download_file.php for order ID $order_id: " . $e->getMessage());
    header("Location: order_confirmation.php?order_id=$order_id");
    exit;
}

if ($type === 'pdf') {
    $content = "TasteKart Receipt\n\n";
    $content .= "Order Number: $order_id\n";
    $content .= "Restaurant: $order[restaurant_name]\n";
    $content .= "Total Amount: BDT $order[total_amount]\n";
    $content .= "Delivery Address: $order[delivery_address]\n";
    $content .= "Estimated Delivery: $order[estimated_delivery]\n";
    $content .= "Status: $order[status]\n\n";
    $content .= "Items Ordered:\n";
    foreach ($order_items as $item) {
        $content .= "$item[item_name] (x$item[quantity]) - BDT " . number_format($item['price'] * $item['quantity'], 2) . "\n";
    }
    $content .= "\nLocation Status: " . ($_SESSION['location_address'] ?? $order['delivery_address']) . "\n";
    if ($coords && $coords['latitude'] != 0 && $coords['longitude'] != 0) {
        $content .= "Coordinates: Latitude $coords[latitude], Longitude $coords[longitude]\n";
    } else {
        $content .= "Coordinates: Not available\n";
    }

    // Attempt to use FPDF for PDF generation
    $tempFile = tempnam(sys_get_temp_dir(), 'receipt_') . '.pdf';
    $fpdfAvailable = false;
    if (class_exists('FPDF')) {
        require_once 'vendor/autoload.php'; // Adjust path if needed
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'TasteKart Receipt', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $content);
        $pdf->Output($tempFile, 'F');
        $fpdfAvailable = file_exists($tempFile);
    }

    if ($fpdfAvailable && file_exists($tempFile)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="receipt_order_' . $order_id . '.pdf"');
        readfile($tempFile);
        unlink($tempFile); // Clean up
    } else {
        // Fallback to text file if FPDF fails
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="receipt_order_' . $order_id . '.txt"');
        echo $content;
        error_log("FPDF not available or failed, falling back to text for order ID $order_id");
    }
} elseif ($type === 'location') {
    $locationData = "Order ID: $order_id\n";
    $locationData .= "Delivery Address: $order[delivery_address]\n";
    $locationData .= "Location Address: " . ($_SESSION['location_address'] ?? 'Not set') . "\n";
    if ($coords && $coords['latitude'] != 0 && $coords['longitude'] != 0) {
        $locationData .= "Latitude: $coords[latitude]\nLongitude: $coords[longitude]\n";
    } else {
        $locationData .= "Coordinates: Not available\n";
    }

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="location_data_order_' . $order_id . '.txt"');
    echo $locationData;
} else {
    error_log("Invalid type requested in download_file.php: $type");
    header("Location: order_confirmation.php?order_id=$order_id");
    exit;
}
?>