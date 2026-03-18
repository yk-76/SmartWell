<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'qrcodes/qrlib.php'; 

$log_file = 'register_log.txt';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}

log_message("Script started");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    log_message("POST data received: " . print_r($_POST, true));
    
    $username = trim($_POST['userName'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $dob = trim($_POST['dateOfBirth'] ?? ''); 
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phoneNo'] ?? '');
    
    log_message("Processed form data - Username: $username, Email: $email, DOB: $dob, Gender: $gender");

    function generateNextRecordId($conn) {
        $result = $conn->query("SELECT MAX(UserID) as max_id FROM user");
        
        if (!$result) {
            log_message("Error in MAX(UserID) query: " . mysqli_error($conn));
            return 'U0001';
        }
        
        $row = $result->fetch_assoc();
        $maxId = $row['max_id'];
        log_message("MAX UserID found: " . ($maxId ? $maxId : "None"));
        
        if ($maxId) {
            // Extract the numeric part and increment
            $num = (int)substr($maxId, 1);
            $nextNum = $num + 1;
        } else {
            $nextNum = 1;
        }
        
        $id = 'U' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        if (strlen($id) > 5) {
            log_message("WARNING: Generated ID $id exceeds varchar(5) limit");
            $id = 'U' . $nextNum;
        }
        return $id;
    }
    
    $userID = generateNextRecordId($conn);
    log_message("Generated UserID: $userID");

    $errors = [];
    
    // Validation
    if (!$username || !$password || !$email || !$dob || !$gender || !$phone) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($username) < 4) {
        $errors[] = "Username must be at least 4 characters.";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Validate data lengths against database constraints
    if (strlen($username) > 100) {
        $errors[] = "Username is too long (max 100 characters).";
    }
    
    if (strlen($email) > 100) {
        $errors[] = "Email is too long (max 100 characters).";
    }
    
    if (strlen($gender) > 10) {
        $errors[] = "Gender value is too long (max 10 characters).";
    }
    
    if (strlen($phone) > 20) {
        $errors[] = "Phone number is too long (max 20 characters).";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $check_sql = "SELECT UserID FROM user WHERE Email = ? OR UserName = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $username);
            if (mysqli_stmt_execute($check_stmt)) {
                mysqli_stmt_store_result($check_stmt);
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $errors[] = "Username or email already exists.";
                    log_message("Duplicate check: Username or email already exists");
                }
            } else {
                log_message("Error executing duplicate check: " . mysqli_stmt_error($check_stmt));
                $errors[] = "Database error during validation.";
            }
            mysqli_stmt_close($check_stmt);
        } else {
            log_message("Error preparing duplicate check: " . mysqli_error($conn));
            $errors[] = "Database error. Please try again later.";
        }
    }
    
    log_message("Validation completed. Errors: " . (empty($errors) ? "None" : implode(", ", $errors)));
    
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $createdAt = date('Y-m-d H:i:s');
        
        // Generate secure token for QR code
        $qrToken = bin2hex(random_bytes(32)); // 64-character secure token
        
        log_message("Attempting to insert user with ID: $userID");
        
        // First, insert user without QR code
        $sql = "INSERT INTO user (UserID, UserName, Password, Email, DateOfBirth, Gender, PhoneNo, CreatedAt, qr_token) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            if (!mysqli_stmt_bind_param($stmt, "sssssssss", 
                             $userID, $username, $hashedPassword, $email, 
                             $dob, $gender, $phone, $createdAt, $qrToken)) {
                log_message("Error binding parameters: " . mysqli_stmt_error($stmt));
                $errors[] = "Database error: Could not bind parameters.";
            } else {
                if (!mysqli_stmt_execute($stmt)) {
                    log_message("Error executing insert: " . mysqli_stmt_error($stmt));
                    $errors[] = "Database error: " . mysqli_stmt_error($stmt);
                } else {
                    log_message("User successfully registered with ID: $userID");
                    
                    //  generate and save QR code
                    $qrCodeGenerated = generateAndSaveQRCode($conn, $userID, $qrToken, $username);
                    
                    if ($qrCodeGenerated) {
                        // Send confirmation email
                        sendConfirmationEmail($email, $username);
                        
                        // Redirect to login page with success message
                        $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
                        $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'showLogin=1&success=1';
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $errors[] = "Account created but QR code generation failed. Please contact support.";
                    }
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            log_message("Error preparing insert statement: " . mysqli_error($conn));
            $errors[] = "Database error: " . mysqli_error($conn);
        }
    }
    
    // If we have errors, redirect back with error messages
    if (!empty($errors)) {
        log_message("Redirecting with errors: " . implode(", ", $errors));
        $error_str = implode("|", $errors);
        header("Location: index.php?showLogin=1&error=" . urlencode($error_str));
        exit();
    }
} else {
    log_message("Script accessed without POST data");
    header("Location: index.php");
    exit();
}

/**
 * Generate QR code and save to database
 */
function generateAndSaveQRCode($conn, $userID, $token, $username) {
    try {
        log_message("Starting QR code generation for UserID: $userID");
        
        $qrContent = json_encode([
            'userID' => $userID,
            'token' => $token,
            'username' => $username,
            'type' => 'user_auth',
            'generated' => date('Y-m-d H:i:s'),
            'expires' => date('Y-m-d H:i:s', strtotime('+1 year')) 
        ], JSON_UNESCAPED_SLASHES);
        
        log_message("QR Content prepared for $userID: " . substr($qrContent, 0, 100) . "...");
        
        // Generate QR code to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'qr_reg_' . $userID . '_');
        
        if (!$tempFile) {
            throw new Exception("Could not create temporary file");
        }
        
        // Generate high-quality QR code
        QRcode::png($qrContent, $tempFile, QR_ECLEVEL_H, 10, 2);
        
        // Verify file was created and has content
        if (!file_exists($tempFile) || filesize($tempFile) === 0) {
            throw new Exception("QR code file generation failed or file is empty");
        }
        
        // Read the generated QR code binary data
        $imageData = file_get_contents($tempFile);
        
        // Clean up temporary file immediately
        unlink($tempFile);
        
        // Validate the image data
        if (empty($imageData)) {
            throw new Exception("No image data generated");
        }
        
        // Verify it's a valid PNG image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false || !isset($imageInfo['mime']) || $imageInfo['mime'] !== 'image/png') {
            throw new Exception("Generated data is not a valid PNG image");
        }
        
        log_message("QR code generated successfully. Size: " . strlen($imageData) . " bytes, Dimensions: " . $imageInfo[0] . "x" . $imageInfo[1]);
        
        // Save QR code binary data to database using prepared statement
        $stmt_qr = $conn->prepare("UPDATE user SET qr_code_path = ? WHERE UserID = ?");
        
        if (!$stmt_qr) {
            throw new Exception("Could not prepare QR update statement: " . $conn->error);
        }
        
        $stmt_qr->bind_param("ss", $imageData, $userID);
        
        if (!$stmt_qr->execute()) {
            $stmt_qr->close();
            throw new Exception("Database update failed: " . $stmt_qr->error);
        }
        
        $affected_rows = $stmt_qr->affected_rows;
        $stmt_qr->close();
        
        if ($affected_rows === 0) {
            throw new Exception("No rows were updated - UserID may not exist");
        }
        
        log_message("QR code successfully stored in database for UserID: $userID");
        return true;
        
    } catch (Exception $e) {
        $errorMessage = "QR Generation Error for UserID $userID: " . $e->getMessage();
        log_message($errorMessage);
        
        // Clean up temp file if it still exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        return false;
    }
}

/**
 * Send confirmation email to user
 */
function sendConfirmationEmail($email, $username) {
    try {
        // Check if mailer.php exists before including
        if (!file_exists(__DIR__ . "/mailer.php")) {
            log_message("mailer.php not found - skipping email");
            return false;
        }
        
        // Get the PHPMailer instance
        $mail = require __DIR__ . "/mailer.php";
        
        // Set email content
        $mail->setFrom('darylzsfoo@gmail.com', 'SmartWell');
        $mail->addAddress($email, $username);
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to SmartWell - Registration Successful';
        $mail->Body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4CAF50; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                    .highlight { background-color: #e8f5e8; padding: 10px; border-radius: 5px; margin: 15px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>🎉 Welcome to SmartWell!</h2>
                    </div>
                    <div class="content">
                        <h3>Hello ' . htmlspecialchars($username) . ',</h3>
                        <p>Congratulations! Your SmartWell account has been created successfully.</p>
                        
                        <div class="highlight">
                            <h4>✅ Account Details:</h4>
                            <ul>
                                <li><strong>Username:</strong> ' . htmlspecialchars($username) . '</li>
                                <li><strong>Email:</strong> ' . htmlspecialchars($email) . '</li>
                                <li><strong>Registration Date:</strong> ' . date('F j, Y') . '</li>
                            </ul>
                        </div>
                        
                        <p><strong>🔐 Your personal QR code has been generated!</strong> You can access it from your profile once you log in.</p>
                        
                        <p>You can now log in to access all SmartWell services and features.</p>
                        
                        <p>If you have any questions or need assistance, please don\'t hesitate to contact our support team.</p>
                        
                        <p>Thank you for joining SmartWell!</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated email. Please do not reply to this message.</p>
                        <p>&copy; ' . date('Y') . ' SmartWell. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>';
        
        // Send the email
        if ($mail->send()) {
            log_message("✅ Registration confirmation email sent to {$email}");
            return true;
        } else {
            log_message("❌ Failed to send email to {$email}: " . $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        log_message("❌ Email sending error: " . $e->getMessage());
        return false;
    }
}

mysqli_close($conn);
log_message("Script ended successfully");
?>