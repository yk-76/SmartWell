<?php
require_once 'auth_helper.php';
require_once 'config.php';
start_secure_session();

$token = $_SESSION['desktop_qr_token'] ?? '';
if (!$token) {
    echo json_encode(['success'=>false]);
    exit;
}

// 1. Check QR login requests table for confirmation
$stmt = $conn->prepare("SELECT confirmed, user_id FROM qr_login_requests WHERE qr_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row && $row['confirmed'] && $row['user_id']) {
    // 2. Fetch user details from user table
    $userStmt = $conn->prepare("SELECT * FROM user WHERE UserID = ?");
    $userStmt->bind_param("s", $row['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();

    if ($user) {
        // 3. Set all necessary session variables
        $_SESSION['user_name'] = $user['UserName'];
        $_SESSION['id'] = $user['UserID'];
        $_SESSION['role'] = $user['Role'];
        $_SESSION['token'] = bin2hex(random_bytes(32));
        $_SESSION['last_activity'] = time();
        $_SESSION['expire_time'] = 30 * 60; // 30 minutes

        // Optional: Clean up the QR login request so it can't be reused
        $deleteStmt = $conn->prepare("DELETE FROM qr_login_requests WHERE qr_token = ?");
        $deleteStmt->bind_param("s", $token);
        $deleteStmt->execute();

        echo json_encode(['success'=>true]);
        exit;
    }
}

// Not confirmed or failed
echo json_encode(['success'=>false]);
?>
