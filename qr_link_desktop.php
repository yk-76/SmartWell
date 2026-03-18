<?php
require_once 'auth_helper.php';
require_once 'config.php';
start_secure_session();

if (!isset($_SESSION['id'])) {
    echo json_encode(['success'=>false, 'reason'=>'Not logged in']);
    exit;
}
$user_id = $_SESSION['id'];
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
if (!$token) {
    echo json_encode(['success'=>false, 'reason'=>'No token']);
    exit;
}
$stmt = $conn->prepare("UPDATE qr_login_requests SET user_id = ?, confirmed = 1 WHERE qr_token = ?");
$stmt->bind_param("ss", $user_id, $token);
$stmt->execute();
if ($stmt->affected_rows > 0) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'reason'=>'Invalid or expired QR']);
}
?>
