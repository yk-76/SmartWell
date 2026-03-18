<?php
// forgot_password_direct.php - Direct form handler with improved error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for proper error display
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Get contact method and contact value
$contactMethod = $_POST["contactMethod"] ?? "";
$email = $_POST["email"] ?? "";
$phone = $_POST["phone"] ?? "";

// Validate input
if (empty($contactMethod)) {
    die("<p>Error: Contact method is required</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

if ($contactMethod === "email" && empty($email)) {
    die("<p>Error: Email is required</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

if ($contactMethod === "phone" && empty($phone)) {
    die("<p>Error: Phone number is required</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

// Connect to database
$mysqli = require __DIR__ . "/config.php";

// Check if we got a valid database connection
if (!($mysqli instanceof mysqli)) {
    die("<p>Error: Database connection failed - got " . gettype($mysqli) . " instead of mysqli object</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

// Validate email or phone exists
if ($contactMethod === "email") {
    $sql = "SELECT UserID, UserName FROM user WHERE Email = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
} else {
    $sql = "SELECT UserID, UserName FROM user WHERE PhoneNo = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $phone);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<p>Error: " . ($contactMethod === "email" ? 
        "Email not found in our records" : 
        "Phone number not found in our records") . "</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

$user = $result->fetch_assoc();
$userId = $user["UserID"];
$userName = $user["UserName"] ?? "User";

// Generate OTP
$otp = sprintf("%06d", mt_rand(100000, 999999));
$otp_hash = hash("sha256", $otp);
$expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 minutes

// Store OTP in database
$sql = "UPDATE user SET reset_token_hash = ?, reset_token_expires_at = ? WHERE UserID = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("sss", $otp_hash, $expiry, $userId);

if (!$stmt->execute()) {
    die("<p>Error: Failed to update user record: " . $mysqli->error . "</p><p><a href='javascript:history.back()'>Go Back</a></p>");
}

// Log OTP for debugging and development purposes
error_log("OTP for user {$userId}: {$otp}");

// Send OTP based on contact method
if ($contactMethod === "email") {
    // Send email with OTP
    try {
        error_log("Attempting to send email to {$email}");
        $mail = require __DIR__ . "/mailer.php";
        
        $mail->setFrom('darylzsfoo@gmail.com', 'SmartWell');
        $mail->addAddress($email, $userName);
        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .otp-box { 
                        background-color: #f4f4f4; 
                        padding: 15px; 
                        border-radius: 5px; 
                        font-size: 24px;
                        letter-spacing: 5px;
                        text-align: center;
                        font-weight: bold;
                        margin: 20px 0;
                    }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Password Reset Verification Code</h2>
                    <p>Hello ' . htmlspecialchars($userName) . ',</p>
                    <p>You requested to reset your password. Use this verification code to complete the process:</p>
                    <div class="otp-box">' . $otp . '</div>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you did not request a password reset, please ignore this email and contact support if you have concerns.</p>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>';
        
        $mail->send();
        error_log("Email sent successfully to {$email}");
        
        // Redirect to reset password page
        header("Location: reset_password.php?email=" . urlencode($email));
        exit;
    } catch (Exception $e) {
        // Log the error
        error_log("Email sending failed: " . $e->getMessage());
        
        // DEVELOPMENT MODE: In development, show the OTP and provide link to continue
        // In production, you should remove this and handle errors more securely
        echo "<h2>Email Sending Error</h2>";
        echo "<p>There was a problem sending the verification code to your email.</p>";
        echo "<p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>For development purposes only, your verification code is: <strong>{$otp}</strong></p>";
        echo "<p><a href='reset_password.php?email=" . urlencode($email) . "'>Continue to reset password</a></p>";
        exit;
    }
} else {
    // For phone verification (SMS)
    // In a real app, send SMS with OTP here
    // For now, just redirect
    
    // DEVELOPMENT MODE: Show the OTP before redirecting
    echo "<h2>SMS Verification Code (Development Only)</h2>";
    echo "<p>Since this is development mode and SMS sending is not implemented, here is your code: <strong>{$otp}</strong></p>";
    echo "<p>You will be redirected in 10 seconds...</p>";
    echo "<p><a href='reset_password.php?phone=" . urlencode($phone) . "'>Click here if not redirected</a></p>";
    
    // Add meta refresh for automatic redirect after 10 seconds
    echo "<meta http-equiv='refresh' content='10;url=reset_password.php?phone=" . urlencode($phone) . "'>";
    exit;
}
?>