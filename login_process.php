<?php
// Enable error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Logging utility
function logDebug($msg) {
    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false, // set to true on production with HTTPS!
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
    'cookie_path' => '/',
    'cookie_domain' => '',
]);

include 'config.php';

logDebug("Login attempt");
logDebug("Session started: " . session_id());

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

function validate($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$uname = isset($_POST['uname']) ? validate($_POST['uname']) : '';
$pass = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['rememberMe']) && $_POST['rememberMe'] === 'on';

logDebug("POST uname: $uname");
logDebug("RememberMe checked: " . ($remember ? "yes" : "no"));

// Error handler
function loginFail($msg) {
    $_SESSION['login_errors'] = [$msg];
    header("Location: index.php?showLogin=1");
    exit();
}

if (empty($uname) || empty($pass)) {
    loginFail("Please enter both username and password");
}

// Prepare SQL & fetch user
$stmt = $conn->prepare("SELECT * FROM user WHERE UserName=?");
if (!$stmt) {
    logDebug("SQL Prepare failed: " . $conn->error);
    loginFail("System error, try again.");
}
$stmt->bind_param("s", $uname);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    logDebug("Username not found: $uname");
    loginFail("Invalid login credentials");
}
$row = $result->fetch_assoc();

// Check password hash
if (!password_verify($pass, $row['Password'])) {
    logDebug("Password mismatch for $uname");
    loginFail("Invalid login credentials");
}

// ---- LOGIN SUCCESS ----

// Set session
$_SESSION['user_name'] = $row['UserName'];
$_SESSION['id'] = $row['UserID'];
$_SESSION['role'] = $row['Role'];
$_SESSION['token'] = bin2hex(random_bytes(32));
$_SESSION['last_activity'] = time();
$_SESSION['expire_time'] = 30 * 60; // 30 minutes

// Update last_login
$updateLoginStmt = $conn->prepare("UPDATE user SET last_login = NOW() WHERE UserID = ?");
if ($updateLoginStmt) {
    $updateLoginStmt->bind_param("s", $row['UserID']);
    $updateLoginStmt->execute();
    $updateLoginStmt->close();
}

// Handle "Remember Me" for username ONLY
if ($remember) {
    setcookie('remembered_username', $uname, time() + (30 * 24 * 60 * 60), '/');
    logDebug("Set remembered_username cookie");
} else {
    setcookie('remembered_username', '', time() - 3600, '/');
    logDebug("Cleared remembered_username cookie");
}

// Clear any form data on success
unset($_SESSION['form_data']);

// Redirect based on role
$userRole = strtolower(trim($row['Role']));
if ($userRole === 'admin') {
    logDebug("Redirecting to AdminPage.php");
    header("Location: AdminPage.php");
    exit();
} else {
    logDebug("Redirecting to index.php");
    header("Location: index.php");
    exit();
}
?>
