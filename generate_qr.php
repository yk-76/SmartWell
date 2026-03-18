<?php
// generate_qr.php - Handle QR code display and regeneration
require_once 'auth_helper.php';
require_once 'config.php';
require_once 'qrcodes/qrlib.php';

// Start secure session
start_secure_session();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    header("Location: Login.php");
    exit();
}

// Function to log messages
function log_message($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'qr_generation.log');
}

// Handle QR code display request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'display') {
    $userID = $_SESSION['id'];
    
    try {
        log_message("Attempting to display QR code for UserID: $userID");
        
        // Get QR code binary data from database
        $stmt = $conn->prepare("SELECT qr_code_path FROM user WHERE UserID = ?");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $userID);
        
        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $qrData = $row['qr_code_path'];
            
            if (!empty($qrData)) {
                // Verify the data is a valid image
                $imageInfo = @getimagesizefromstring($qrData);
                
                if ($imageInfo !== false && isset($imageInfo['mime']) && $imageInfo['mime'] === 'image/png') {
                    // Set proper headers for PNG image
                    header('Content-Type: image/png');
                    header('Content-Length: ' . strlen($qrData));
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    header('Content-Disposition: inline; filename="qr_code_' . $userID . '.png"');
                    
                    log_message("✅ Successfully serving QR code for UserID: $userID, Size: " . strlen($qrData) . " bytes");
                    
                    // Output the image data
                    echo $qrData;
                    exit();
                } else {
                    log_message("❌ Invalid QR code data for UserID: $userID");
                    throw new Exception("Invalid QR code data");
                }
            } else {
                log_message("⚠️ No QR code found for UserID: $userID");
                // Generate placeholder image
                generatePlaceholderImage("No QR code found. Please generate one.");
                exit();
            }
        } else {
            log_message("❌ User not found: $userID");
            throw new Exception("User not found");
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        log_message("❌ Error displaying QR code for UserID $userID: " . $e->getMessage());
        generatePlaceholderImage("Error loading QR code: " . $e->getMessage());
        exit();
    }
}

// Handle QR code regeneration request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_qr'])) {
    $userID = $_SESSION['id'];
    $username = $_SESSION['user_name'];
    
    try {
        log_message("Starting QR code regeneration for UserID: $userID");
        
        // Generate new secure token
        $newToken = bin2hex(random_bytes(32)); // 64-character secure token
        
        // Prepare QR content with essential information
        $qrContent = json_encode([
                    'username' => $username,
                    'token' => $newToken
                ], JSON_UNESCAPED_SLASHES);

        
        // Generate QR code to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'qr_regen_' . $userID . '_');
        
        if (!$tempFile) {
            throw new Exception("Could not create temporary file");
        }
        
        // Generate high-quality QR code
        QRcode::png($qrContent, $tempFile, QR_ECLEVEL_H, 10, 2);
        
        // Check if file was created successfully
        if (!file_exists($tempFile) || filesize($tempFile) === 0) {
            throw new Exception("QR code generation failed - temp file not created or empty");
        }

        // Read the generated QR code
        $imageData = file_get_contents($tempFile);
        
        // Clean up temp file immediately
        unlink($tempFile);
        
        // Verify the generated data is valid
        if (empty($imageData)) {
            throw new Exception("QR code generation failed - no image data");
        }

        // Verify it's a valid PNG image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false || !isset($imageInfo['mime']) || $imageInfo['mime'] !== 'image/png') {
            throw new Exception("Generated QR code data is not a valid PNG image");
        }

        // Update database with new token and QR code
        $stmt = $conn->prepare("UPDATE user SET RememberMe = ?, qr_code_path = ? WHERE UserID = ?");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("sss", $newToken, $imageData, $userID);
        
        if (!$stmt->execute()) {
            throw new Exception("Database update failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected_rows === 0) {
            throw new Exception("No rows were updated - UserID may not exist");
        }
        
        log_message("✅ QR code regenerated successfully for UserID: $userID, Size: " . strlen($imageData) . " bytes");
        
        // Return JSON response for AJAX request
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'QR code regenerated successfully',
                'qr_url' => 'generate_qr.php?action=display&t=' . time()
            ]);
            exit();
        } else {
            // Redirect for regular form submission
            header("Location: UserProfile.php?qr_regenerated=1");
            exit();
        }

    } catch (Exception $e) {
        $errorMessage = "❌ QR Regeneration Error for UserID $userID: " . $e->getMessage();
        log_message($errorMessage);
        
        // Clean up temp file if it exists
        if (isset($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        // Return error response
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ]);
            exit();
        } else {
            header("Location: UserProfile.php?qr_error=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Handle QR code info request (to check if QR exists)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'info') {
    $userID = $_SESSION['id'];
    
    try {
        $stmt = $conn->prepare("SELECT qr_code_path, RememberMe FROM user WHERE UserID = ?");
        $stmt->bind_param("s", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        header('Content-Type: application/json');
        
        if ($row = $result->fetch_assoc()) {
            $hasQR = !empty($row['qr_code_path']);
            $hasToken = !empty($row['RememberMe']);
            
            echo json_encode([
                'success' => true,
                'has_qr' => $hasQR,
                'has_token' => $hasToken,
                'qr_size' => $hasQR ? strlen($row['qr_code_path']) : 0
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
        
        $stmt->close();
        exit();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Generate a placeholder image with error message
 */
function generatePlaceholderImage($message) {
    // Create a simple error image
    $width = 300;
    $height = 300;
    $image = imagecreate($width, $height);
    
    // Colors
    $bg_color = imagecolorallocate($image, 240, 240, 240);
    $text_color = imagecolorallocate($image, 100, 100, 100);
    $border_color = imagecolorallocate($image, 200, 200, 200);
    
    // Fill background
    imagefill($image, 0, 0, $bg_color);
    
    // Draw border
    imagerectangle($image, 0, 0, $width-1, $height-1, $border_color);
    
    // Add text
    $font_size = 3;
    $text_lines = str_split($message, 25); // Break text into lines
    $line_height = 20;
    $start_y = ($height - (count($text_lines) * $line_height)) / 2;
    
    foreach ($text_lines as $i => $line) {
        $text_width = strlen($line) * 10;
        $x = ($width - $text_width) / 2;
        $y = $start_y + ($i * $line_height);
        imagestring($image, $font_size, $x, $y, $line, $text_color);
    }
    
    // Output image
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    imagepng($image);
    imagedestroy($image);
}

// If no valid action, redirect to profile
header("Location: UserProfile.php");
exit();
?>