<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Direct includes for PHPMailer files - no autoloader needed
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Create a new PHPMailer instance
$mail = new PHPMailer(true);

try {
    // Configure SMTP settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
    };
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';  // Use Gmail's SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'darylzsfoo@gmail.com';  // Your Gmail address
    $mail->Password = 'cppo xbom tkpo ebug';  // Your App Password
    
    // TRY WITH TLS FIRST
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Or try these alternate settings if the above doesn't work:
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL
    // $mail->Port = 465;  // SSL port
    
    // Additional settings
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Connection timeout settings
    $mail->Timeout = 60; // Timeout in seconds
    
    error_log("PHPMailer initialized with Host: {$mail->Host}, Port: {$mail->Port}, Secure: {$mail->SMTPSecure}");
    
} catch (Exception $e) {
    error_log("Error initializing PHPMailer: " . $e->getMessage());
}

return $mail;
?>