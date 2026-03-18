<?php
// At the very beginning
$is_included = isset($is_included) ? $is_included : false;

if (!$is_included) {
    header('Content-Type: application/json');
}

// Start output buffering to capture any unwanted output or errors
ob_start();

try {
    // Get database connection
    $mysqli = require __DIR__ . "/config.php";
    
    // Verify we got a database connection object, not an integer
    if (!($mysqli instanceof mysqli)) {
        throw new Exception("Database connection failed - got " . gettype($mysqli) . " instead of mysqli object");
    }
    
    // Get contact method and validate input
    if (isset($contactMethod) && isset($contactValue)) {
        // Variables already set from the including file
    } else {
        $contactMethod = $_POST["contactMethod"] ?? "email";
        
        if ($contactMethod === "email") {
            $contactValue = filter_var($_POST["email"] ?? "", FILTER_SANITIZE_EMAIL);
        } elseif ($contactMethod === "phone") {
            $contactValue = $_POST["phone"] ?? "";
        }
    }
    
    if ($contactMethod === "email") {
        $email = $contactValue;
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Check if the email exists in the database
        $sql = "SELECT UserID, UserName FROM user WHERE Email = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $mysqli->error);
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Email not found in our records");
        }
        
        // Get the user ID
        $user = $result->fetch_assoc();
        $userId = $user["UserID"];
        $userName = $user["UserName"] ?? "User";
        
        // Generate OTP - 6 digit code
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $otp_hash = hash("sha256", $otp);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 minutes
        
        // Update the user record with OTP
        $sql = "UPDATE user SET reset_token_hash = ?, reset_token_expires_at = ? WHERE UserID = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $otp_hash, $expiry, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user record: " . $mysqli->error);
        }
        
        // For debugging, log the OTP
        error_log("OTP generated: " . $otp);

        // Send email with OTP - BUT ONLY if not included by another file that already sent an email
        if (!$is_included) {
            try {
                // Get the PHPMailer instance
                $mail = require __DIR__ . "/mailer.php";
                
                // Set email content
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
                
                // Try to send the email - with better error capture
                if (!$mail->send()) {
                    throw new Exception("Email sending failed: " . $mail->ErrorInfo);
                }
                
                error_log("Email successfully sent to {$email}");
            } catch (Exception $e) {
                // Log detailed email error
                error_log("Email sending failed in forgot_password_process: " . $e->getMessage());
                
                // For API requests, throw the exception to be caught by outer try/catch
                // This ensures the JSON response includes the email error
                throw new Exception("Failed to send verification email: " . $e->getMessage());
            }
        }
        
        // Create verification URL - make sure to use correct path
        // Get domain and path from current request
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        
        // Construct full URL
        $verification_url = $protocol . $host . $path . "/reset_password.php?email=" . urlencode($email);
        
        // For debugging
        error_log("Generated redirect URL: " . $verification_url);
        
        // Success message
        $success_message = "Verification code sent to your email: " . $email;
        
    } elseif ($contactMethod === "phone") {
        $phone = $contactValue;
        
        if (empty($phone)) {
            throw new Exception("Phone number is required");
        }
        
        // Check if phone exists
        $sql = "SELECT UserID FROM user WHERE PhoneNo = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Phone number not found in our records");
        }
        
        // Get the user ID
        $user = $result->fetch_assoc();
        $userId = $user["UserID"];

        // Generate OTP - 6 digit code
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $otp_hash = hash("sha256", $otp);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // 15 minutes

        // Debug log
        error_log("Generated OTP: " . $otp);
        error_log("OTP hash: " . $otp_hash);

        // Update the user record
        $sql = "UPDATE user SET reset_token_hash = ?, reset_token_expires_at = ? WHERE UserID = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $otp_hash, $expiry, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update user record: " . $mysqli->error);
        }
        
        // In a real implementation, you would integrate with an SMS service here
        
        // Create verification URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['PHP_SELF']);
        
        $verification_url = $protocol . $host . $path . "/reset_password.php?phone=" . urlencode($phone);
        
        // For debugging
        error_log("Generated redirect URL: " . $verification_url);
        
        // Success message
        $success_message = "Verification code sent to your phone: " . $phone;
    } else {
        throw new Exception("Invalid contact method");
    }
    
    // Clean output buffer and return success response
    if (ob_get_length()) ob_end_clean();
    
    // Only return JSON if not included by another file
    if (!$is_included) {
        echo json_encode([
            "success" => true,
            "message" => $success_message,
            "redirect" => $verification_url,
            "debug_otp" => $otp  // REMOVE THIS IN PRODUCTION - just for testing
        ]);
    } else {
        // Return values for the including file to use
        $result = [
            "success" => true,
            "message" => $success_message,
            "redirect" => $verification_url,
            "debug_otp" => $otp  // REMOVE THIS IN PRODUCTION
        ];
        return $result;
    }
    
} catch (Exception $e) {
    // Clean output buffer and return error response
    if (ob_get_length()) ob_end_clean();
    
    if (!$is_included) {
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    } else {
        throw $e; // Rethrow the exception for the including file to handle
    }
}
?>