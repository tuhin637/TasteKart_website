<?php
session_start();
header('Content-Type: application/json');

// Log session start
error_log("send_otp.php: Session started at " . date('Y-m-d H:i:s') . " with ID: " . session_id());

// Generate a 4-digit OTP
$otp_code = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // Ensure 4 digits
$_SESSION['otp_code'] = $otp_code;
$_SESSION['otp_timestamp'] = time(); // Store timestamp in UTC+6
$_SESSION['otp_expires'] = 300; // 5 minutes expiration

// Log OTP generation
error_log("send_otp.php: Generated OTP $otp_code for session ID " . session_id() . ", Full session: " . json_encode($_SESSION));

echo json_encode(['success' => true, 'message' => 'OTP generated successfully', 'otp' => $otp_code]);
exit;
?>