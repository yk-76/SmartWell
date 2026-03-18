<?php
// --- CONFIG & SESSION SETUP ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth_helper.php';
require_once 'config.php';

start_secure_session();
check_session_consistency();

// --- AUTH CHECK ---
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    header("Location: index.php?showLogin=1&error=unauthorized");
    exit();
}

// --- REQUEST METHOD CHECK ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: UserProfile.php?error=invalid_request");
    exit();
}

$user_id = $_SESSION['id'];

// --- FILE VALIDATION ---
if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profileImage'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Check file type
    $detectedType = mime_content_type($file['tmp_name']);
    if (!in_array($detectedType, $allowedTypes)) {
        header("Location: UserProfile.php?error=invalid_file_type");
        exit();
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        header("Location: UserProfile.php?error=file_too_large");
        exit();
    }

    // --- BASE64 ENCODE & SAVE TO DB ---
    $fileContent = file_get_contents($file['tmp_name']);
    $base64Image = 'data:' . $detectedType . ';base64,' . base64_encode($fileContent);

    // Double-check the user exists
    $check = $conn->prepare("SELECT UserID FROM user WHERE UserID = ?");
    $check->bind_param("s", $user_id);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows == 0) {
        $check->close();
        header("Location: UserProfile.php?error=user_not_found");
        exit();
    }
    $check->close();

    // Update profile picture
    $stmt = $conn->prepare("UPDATE user SET ProfilePic = ? WHERE UserID = ?");
    $stmt->bind_param("ss", $base64Image, $user_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: UserProfile.php?success=profile_pic_updated");
        exit();
    } else {
        $stmt->close();
        header("Location: UserProfile.php?error=database_error");
        exit();
    }
} else {
    // No file or upload error
    $errorCode = isset($_FILES['profileImage']) ? $_FILES['profileImage']['error'] : 'no_file';
    header("Location: UserProfile.php?error=upload_failed&code=" . $errorCode);
    exit();
}
?>
