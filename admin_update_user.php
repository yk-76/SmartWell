<?php
require_once 'config.php';
require_once 'auth_helper.php';

// Start secure session
start_secure_session();

// Check session consistency
check_session_consistency();

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: AdminPage.php");
    exit();
}

// Get form data
$user_id = $_POST['user_id'] ?? '';
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$dateOfBirth = $_POST['dateOfBirth'] ?? '';
$gender = $_POST['gender'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validate required fields
if (empty($user_id) || empty($username) || empty($email)) {
    header("Location: AdminEditUser.php?id=$user_id&error=1&message=Required fields are missing");
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: AdminEditUser.php?id=$user_id&error=1&message=Invalid email format");
    exit();
}

// Check if username already exists (for other users)
$stmt = $conn->prepare("SELECT UserID FROM user WHERE UserName = ? AND UserID != ?");
$stmt->bind_param("ss", $username, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: AdminEditUser.php?id=$user_id&error=1&message=Username is already taken");
    exit();
}
$stmt->close();

// Check if email already exists (for other users)
$stmt = $conn->prepare("SELECT UserID FROM user WHERE Email = ? AND UserID != ?");
$stmt->bind_param("ss", $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: AdminEditUser.php?id=$user_id&error=1&message=Email is already in use by another account");
    exit();
}
$stmt->close();

// Password validation if admin wants to change user's password
$updatePassword = false;
if (!empty($newPassword) || !empty($confirmPassword)) {
    if (empty($newPassword)) {
        header("Location: AdminEditUser.php?id=$user_id&error=1&message=New password is required");
        exit();
    } elseif (strlen($newPassword) < 8) {
        header("Location: AdminEditUser.php?id=$user_id&error=1&message=New password must be at least 8 characters long");
        exit();
    } elseif ($newPassword !== $confirmPassword) {
        header("Location: AdminEditUser.php?id=$user_id&error=1&message=New passwords do not match");
        exit();
    } else {
        $updatePassword = true;
    }
}

// Update user information
try {
    $conn->begin_transaction();
    
    if ($updatePassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE user SET UserName = ?, Email = ?, PhoneNo = ?, DateOfBirth = ?, Gender = ?, Password = ? WHERE UserID = ?");
        $stmt->bind_param("sssssss", $username, $email, $phone, $dateOfBirth, $gender, $hashedPassword, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET UserName = ?, Email = ?, PhoneNo = ?, DateOfBirth = ?, Gender = ? WHERE UserID = ?");
        $stmt->bind_param("ssssss", $username, $email, $phone, $dateOfBirth, $gender, $user_id);
    }
    
    if ($stmt->execute()) {
        $conn->commit();
        header("Location: AdminEditUser.php?id=$user_id&success=1");
        exit();
    } else {
        throw new Exception("Failed to update user information");
    }
    
} catch (Exception $e) {
    $conn->rollback();
    header("Location: AdminEditUser.php?id=$user_id&error=1&message=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
exit();