<?php
require_once 'auth_helper.php';
require_once 'config.php';

// Start secure session
start_secure_session();

// Check session consistency
check_session_consistency();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    // Append any error or success parameters
    $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'showLogin=1&error=something';
    header("Location: $redirect_url");
    exit();
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: UserProfile.php");
    exit();
}

$user_id = $_SESSION['id'];
$errors = [];
$success = false;

// Get form data and sanitize
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$dateOfBirth = $_POST['dateOfBirth'] ?? '';
$gender = $_POST['gender'] ?? '';
$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Validate required fields
if (empty($username)) {
    $errors[] = 'Username is required';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Check if username already exists (for other users)
if (!empty($username)) {
    $stmt = $conn->prepare("SELECT UserID FROM user WHERE UserName = ? AND UserID != ?");
    $stmt->bind_param("ss", $username, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = 'Username is already taken';
    }
    $stmt->close();
}

// Check if email already exists (for other users)
if (!empty($email)) {
    $stmt = $conn->prepare("SELECT UserID FROM user WHERE Email = ? AND UserID != ?");
    $stmt->bind_param("ss", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = 'Email is already in use by another account';
    }
    $stmt->close();
}

// Password validation if user wants to change password
$updatePassword = false;
if (!empty($newPassword) || !empty($confirmPassword) || !empty($currentPassword)) {
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required to change password';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT Password FROM user WHERE UserID = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check if current password is correct
            if (!password_verify($currentPassword, $user['Password'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                // Validate new password
                if (empty($newPassword)) {
                    $errors[] = 'New password is required';
                } elseif (strlen($newPassword) < 8) {
                    $errors[] = 'New password must be at least 8 characters long';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors[] = 'New passwords do not match';
                } else {
                    $updatePassword = true;
                }
            }
        }
        $stmt->close();
    }
}

// If no errors, proceed with update
if (empty($errors)) {
    try {
        $conn->begin_transaction();
        
        if ($updatePassword) {
            // Update with password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user SET UserName = ?, Email = ?, PhoneNo = ?, DateOfBirth = ?, Gender = ?, Password = ? WHERE UserID = ?");
            $stmt->bind_param("sssssss", $username, $email, $phone, $dateOfBirth, $gender, $hashedPassword, $user_id);
        } else {
            // Update without password
            $stmt = $conn->prepare("UPDATE user SET UserName = ?, Email = ?, PhoneNo = ?, DateOfBirth = ?, Gender = ? WHERE UserID = ?");
            $stmt->bind_param("ssssss", $username, $email, $phone, $dateOfBirth, $gender, $user_id);
        }
        
        if ($stmt->execute()) {
            // Update session username if it changed
            $_SESSION['user_name'] = $username;
            
            $conn->commit();
            $success = true;
            
            // Redirect with success message
            header("Location: UserProfile.php?success=1");
            exit();
        } else {
            throw new Exception("Failed to update user profile");
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// If there are errors, redirect back with error messages
if (!empty($errors)) {
    $error_type = 'general';
    
    // Determine specific error type for better user experience
    foreach ($errors as $error) {
        if (strpos($error, 'password') !== false) {
            if (strpos($error, 'Current password') !== false) {
                $error_type = 'current_password_wrong';
                break;
            } elseif (strpos($error, 'do not match') !== false) {
                $error_type = 'password_mismatch';
                break;
            } elseif (strpos($error, 'at least 8 characters') !== false) {
                $error_type = 'weak_password';
                break;
            }
        } elseif (strpos($error, 'Email') !== false && strpos($error, 'already') !== false) {
            $error_type = 'email_exists';
            break;
        } elseif (strpos($error, 'Username') !== false && strpos($error, 'taken') !== false) {
            $error_type = 'username_exists';
            break;
        }
    }
    
    header("Location: UserProfile.php?error=" . $error_type);
    exit();
}

// Fallback redirect
header("Location: UserProfile.php");
exit();
?>