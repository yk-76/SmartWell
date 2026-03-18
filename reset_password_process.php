<?php
// Process the password reset form submission
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 to hide PHP errors in production

// Get OTP from POST
$otp_digits = $_POST["otp"] ?? [];
$contact_method = $_POST["contact_method"] ?? "";
$contact_value = $_POST["contact_value"] ?? "";

// Convert OTP array to string
$otp = implode("", $otp_digits);

// Function to redirect with error message
function redirectWithError($error) {
    // Determine where to redirect based on contact info
    $contact_method = $_POST["contact_method"] ?? "";
    $contact_value = $_POST["contact_value"] ?? "";
    $redirect = "reset_password.php?";
    
    if ($contact_method == "email") {
        $redirect .= "email=" . urlencode($contact_value);
    } else {
        $redirect .= "phone=" . urlencode($contact_value);
    }
    
    $redirect .= "&error=" . urlencode($error);
    header("Location: " . $redirect);
    exit;
}

// Validation checks
if (empty($otp) || strlen($otp) != 6) {
    redirectWithError("Please enter a valid 6-digit verification code");
}

if (empty($contact_method) || empty($contact_value)) {
    redirectWithError("Missing contact information");
}

// Calculate the OTP hash
$otp_hash = hash("sha256", $otp);

// Connect to database
$mysqli = require __DIR__ . "/config.php";

// Check if we got a valid database connection
if (!($mysqli instanceof mysqli)) {
    redirectWithError("Database connection error");
}

// Find user with this contact method
$field = ($contact_method === "email") ? "Email" : "PhoneNo";
$sql = "SELECT * FROM user WHERE {$field} = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    redirectWithError("Database error");
}

$stmt->bind_param("s", $contact_value);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user === null) {
    redirectWithError("User not found with this " . $contact_method);
}

// Check if the OTP hash matches
if ($user["reset_token_hash"] !== $otp_hash) {
    redirectWithError("Invalid verification code");
}

// Check if OTP has expired
if (strtotime($user["reset_token_expires_at"]) <= time()) {
    redirectWithError("Verification code has expired. Please request a new code");
}

// Validate password
$password = $_POST["password"] ?? "";
$password_confirmation = $_POST["password_confirmation"] ?? "";

if (strlen($password) < 8) {
    redirectWithError("Password must be at least 8 characters");
}

if (!preg_match("/[a-z]/i", $password)) {
    redirectWithError("Password must contain at least one letter");
}

if (!preg_match("/[0-9]/", $password)) {
    redirectWithError("Password must contain at least one number");
}

if ($password !== $password_confirmation) {
    redirectWithError("Passwords do not match");
}

// Hash the new password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Update user's password and clear reset token
$sql = "UPDATE user 
        SET Password = ?, 
            reset_token_hash = NULL,
            reset_token_expires_at = NULL
        WHERE UserID = ?";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    redirectWithError("Database error: " . $mysqli->error);
}

$stmt->bind_param("ss", $password_hash, $user["UserID"]);

if (!$stmt->execute()) {
    redirectWithError("Update failed: " . $mysqli->error);
}

// Success! Show success message with countdown before redirecting
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful - SmartWell</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .success-card {
            max-width: 500px;
            margin: 100px auto;
            text-align: center;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .success-icon {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
        }
        .countdown {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>
                </svg>
            </div>
            <h2>Password Reset Successfully!</h2>
            <p class="mb-4">Your password has been updated. You can now log in with your new password.</p>
            <p>You will be redirected to the login page in <span id="countdown" class="countdown">5</span> seconds.</p>
            <div class="mt-4">
                <a href="Login.php?password_reset=success" class="btn btn-primary">Log In Now</a>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = 'Login.php?password_reset=success';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
exit;
?>