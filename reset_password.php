<?php
// Page that displays the OTP verification form with development helpers

// Get contact information from query parameters
$email = $_GET["email"] ?? "";
$phone = $_GET["phone"] ?? "";
$error = $_GET["error"] ?? "";

// Determine which contact method was used
$contactMethod = !empty($email) ? "email" : "phone";
$contactValue = !empty($email) ? $email : $phone;

if (empty($contactValue)) {
    exit("Missing contact information");
}

// For development: Get the OTP from database if available
$otp_from_db = "";
if (!empty($contactValue)) {
    $mysqli = require __DIR__ . "/config.php";
    if ($mysqli instanceof mysqli) {
        $field = ($contactMethod === "email") ? "Email" : "PhoneNo";
        $sql = "SELECT reset_token_hash, reset_token_expires_at FROM user WHERE {$field} = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $contactValue);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && $user["reset_token_hash"] && strtotime($user["reset_token_expires_at"]) > time()) {
                // We don't store the raw OTP, but we can show token exists
                $otp_exists = true;
                $expires_in = ceil((strtotime($user["reset_token_expires_at"]) - time()) / 60);
            }
        }
    }
}
?>

<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - SmartWell</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .otp-input {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .otp-input input {
            width: 50px;
            height: 50px;
            font-size: 24px;
            text-align: center;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .dev-info {
            background-color: #ffeeba;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Verify Your Identity</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Development Info Box -->
                        <?php if (isset($otp_exists) && $otp_exists): ?>
                        <div class="dev-info">
                            <strong>Development Info:</strong> A valid reset token exists for this user and will expire in approximately <?= $expires_in ?> minutes.
                            <br>
                            <small>Note: For security reasons, we don't store the actual OTP, only a hash. Check your error logs for the OTP value used during creation.</small>
                        </div>
                        <?php endif; ?>
                        
                        <p>We've sent a verification code to your <?= $contactMethod ?>: <strong><?= htmlspecialchars($contactValue) ?></strong></p>
                        
                        <form action="reset_password_process.php" method="POST">
                            <input type="hidden" name="contact_method" value="<?= htmlspecialchars($contactMethod) ?>">
                            <input type="hidden" name="contact_value" value="<?= htmlspecialchars($contactValue) ?>">
                            
                            <div class="mb-4">
                                <label class="form-label">Enter Verification Code</label>
                                <div class="otp-input">
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                    <input type="text" maxlength="1" class="form-control otp-digit" name="otp[]" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/>
                                        </svg>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="form-text">Password must be at least 8 characters with numbers and letters.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-check" viewBox="0 0 16 16">
                                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                                            <path d="M10.5 11.5a.5.5 0 0 1-1 0V10h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0v1h1a.5.5 0 0 1 0 1h-1v1.5z"/>
                                        </svg>
                                    </span>
                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <p>Didn't receive the code? <a href="#" id="resendCode">Resend Code</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-tab through OTP fields
    document.addEventListener('DOMContentLoaded', function() {
        const otpInputs = document.querySelectorAll('.otp-digit');
        
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value.length === this.maxLength) {
                    // Move to next input field if available
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                }
            });
            
            // Handle backspace key
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
        });
        
        // Resend code functionality
        document.getElementById('resendCode').addEventListener('click', function(e) {
            e.preventDefault();
            
            const contactMethod = '<?= $contactMethod ?>';
            const contactValue = '<?= $contactValue ?>';
            
            // Create form data
            const formData = new FormData();
            formData.append('contactMethod', contactMethod);
            
            if (contactMethod === 'email') {
                formData.append('email', contactValue);
            } else {
                formData.append('phone', contactValue);
            }
            
            // Send request to regenerate code
            fetch('forgot_password_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('A new verification code has been sent.');
                } else {
                    alert('Failed to send new code: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
    </script>
</body>
</html>