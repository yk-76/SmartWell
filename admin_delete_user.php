<?php
require_once 'auth_helper.php';
require_once 'config.php';
start_secure_session();
check_session_consistency();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['userId']) || empty($_POST['userId'])) {
    echo json_encode(['success' => false, 'message' => 'No user ID']);
    exit;
}

$user_id = $_POST['userId'];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("DELETE FROM health_record WHERE UserID = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM product_record WHERE UserID = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM bmi_record WHERE UserID = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM user WHERE UserID = ?");
    $stmt->bind_param("s", $user_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to delete user");
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
exit;
