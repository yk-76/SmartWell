<?php
require_once 'auth_helper.php';
require_once 'config.php';
require_once 'qrcodes/qrlib.php';
start_secure_session();

$qr_token = bin2hex(random_bytes(32));
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO qr_login_requests (qr_token, created_at, confirmed) VALUES (?, ?, 0)");
$stmt->bind_param("ss", $qr_token, $now);
$stmt->execute();
$stmt->close();

$_SESSION['desktop_qr_token'] = $qr_token;
$qr_content = json_encode(['qr_login_token' => $qr_token]);
header('Content-Type: image/png');
QRcode::png($qr_content);
exit;
?>
