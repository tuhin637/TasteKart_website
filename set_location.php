<?php
session_start();
include 'includes/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

error_log("Entering set_location.php at " . date('Y-m-d H:i:s') . " with session ID: " . session_id());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lat']) && isset($_POST['lon'])) {
    $lat = filter_var($_POST['lat'], FILTER_VALIDATE_FLOAT);
    $lon = filter_var($_POST['lon'], FILTER_VALIDATE_FLOAT);

    if ($lat !== false && $lon !== false) {
        // Fetch address using Nominatim with a user agent
        $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=$lat&lon=$lon";
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: TasteKart/1.0 (https://tastekart.com)\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $data = json_decode($response, true);
            $address = $data['display_name'] ?? "Address not found for lat $lat, lon $lon";
            $_SESSION['location_address'] = $address;
            $_SESSION['latitude'] = $lat;
            $_SESSION['longitude'] = $lon; // Store coordinates in session
            error_log("Step 10: Location address set to $address, lat $lat, lon $lon for session ID " . session_id());
            echo json_encode(['success' => true, 'address' => $address, 'lat' => $lat, 'lon' => $lon]);
        } else {
            error_log("Step 11: Failed to fetch address from Nominatim for lat $lat, lon $lon. Response: " . ($response ?: 'No response'));
            echo json_encode(['success' => false, 'message' => 'Unable to fetch address']);
        }
    } else {
        error_log("Step 12: Invalid latitude or longitude received: lat $lat, lon $lon");
        echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    }
} else {
    error_log("Step 13: Invalid request to set_location.php. Method: " . $_SERVER['REQUEST_METHOD'] . ", POST data: " . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>