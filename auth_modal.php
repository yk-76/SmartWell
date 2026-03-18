<script src="https://unpkg.com/html5-qrcode@2.3.9/html5-qrcode.min.js"></script>
<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://unpkg.com/html5-qrcode"></script>

<style>
    /* Input group container style */
/* Style for the toggle buttons */
#modalLoginForm .btn-outline-primary {
  color: #338294;
  border-color: #338294;
  background-color: transparent;
}

/* Hover effect */
#modalLoginForm .btn-outline-primary:hover {
  background-color: #338294;
  color: white;
}

/* Active button style (selected tab) */
#modalLoginForm .btn-outline-primary.active {
  background-color: #338294;
  color: white;
  border-color: #338294;
}
/* LOGIN button base style */
.auth-submit-btn {
  background-color: #338294;
  color: white;
  border: none;
  padding: 10px 20px;
  width: 100%;
  font-weight: bold;
  border-radius: 6px;
  transition: background-color 0.3s ease;
}

/* Hover effect */
.auth-submit-btn:hover {
  background-color: #2a6e7c; /* Slightly darker shade */
  color: white;
}


</style>

    <!-- Modal HTML Structure -->
    <div id="authModal" class="auth-modal">
        <div class="auth-modal-content">
            <span class="auth-close">&times;</span>

            <!-- Sliding Container -->
            <div class="forms-container" id="formsContainer">
                <!-- Login Form Panel -->
                <div class="form-panel">
                    <div class="bg-section col-md-6 d-none d-md-block" id="loginBgSection">
                        <div class="bg-content">
                            <h2>Welcome Back</h2>
                            <p>Log in to access your personalized healthcare dashboard and continue your wellness journey.</p>
                        </div>
                    </div>
                    <div class="form-section col-12 col-md-6">
                        <h2 class="auth-heading">USER LOGIN</h2>
                        <!-- Display login errors from session -->
                        <?php
                        if (isset($_SESSION['login_errors']) && !empty($_SESSION['login_errors'])) {
                            echo '<div class="alert alert-danger">';
                            foreach ($_SESSION['login_errors'] as $error) {
                                echo htmlspecialchars($error) . '<br>';
                            }
                            echo '</div>';
                            // Clear errors after displaying
                            unset($_SESSION['login_errors']);
                        }
                        
                        // Display errors from URL parameter
                        if (isset($_GET['error']) && !empty($_GET['error'])) {
                            echo '<div class="alert alert-danger">';
                            echo htmlspecialchars(urldecode($_GET['error']));
                            echo '</div>';
                        }
                        ?>
    

                        <form id="modalLoginForm" action="login_process.php" method="POST">
                    <!-- Login Method Switch Tabs -->
                    <div class="d-flex justify-content-center mb-3">
                        <button type="button" class="btn btn-outline-primary me-2 active" id="traditionalTab" onclick="switchLoginMethod('traditional')">Login with Username</button>
                        <button type="button" class="btn btn-outline-primary" id="qrTab" onclick="switchLoginMethod('qr')">Login with QR Code</button>
                    </div>

                    <!-- Login Type Hidden Field -->
                    <input type="hidden" id="loginTypeInput" name="login_type" value="regular">


                    <!-- Traditional Login Section -->
                    <div class="traditional-login-section">
                        <div class="mb-3">
                        <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
                                        </svg>
                                    </span>
                                    <input type="text" class="form-control" id="modalUserName" name="uname" placeholder="User Name" required/>
                                </div>
                        </div>
                        <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/>
                                        </svg>
                            </span>
                            <input type="password" class="form-control" id="modalPassword" name="password" placeholder="Password" required />
                        </div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modalRememberMe" name="rememberMe" />
                            <label class="form-check-label" for="modalRememberMe">Remember me</label>
                        </div>
                        <a href="#" class="auth-text-link" id="showForgotPasswordForm">Forgot password?</a>
                        </div>
                    </div>

                    <!-- QR Code Login Section (Desktop Shows QR) -->
                    <div class="qr-login-section d-none">
                        <div class="mb-3 text-center">
                            <div id="qrLoginImageContainer" style="display: inline-block; border: 3px dashed #0d6efd; border-radius: 8px; padding: 12px;">
                                <img id="qrLoginImage" src="" alt="Scan this QR code with your phone" style="width:220px; height:220px;">
                            </div>
                            <div id="qrLoginStatusMsg" class="qr-status-message mt-2 small text-muted"></div>
                            <button id="qrLoginRefreshBtn" type="button" class="btn btn-link mt-2 p-0" style="font-size:0.95em;">
                                <i class="bi bi-arrow-clockwise"></i> Refresh QR Code
                            </button>
                        </div>
                    </div>



                    <!-- Submit Button -->
                    <div class="mb-3">
                        <button type="submit" class="auth-submit-btn w-100 btn btn-primary" id="loginSubmitBtn">LOGIN</button>
                    </div>

                    <!-- Register Link -->
                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="#" class="auth-text-link" id="showRegisterForm">Register here</a></p>
                    </div>
                    </form>
    </div>
</div>

<!-- QR Scanner Script -->
<script>
// ---- Desktop QR Login Mode ----
let qrLoginPollingInterval = null;
let qrLoginToken = null;

// Show QR code in desktop QR login section
function showDesktopQrCode() {
    document.getElementById('qrLoginImage').src = 'qr_login_session.php?t=' + Date.now();
    document.getElementById('qrLoginStatusMsg').textContent = 'Scan the QR code with your mobile app to login.';
    if (qrLoginPollingInterval) clearInterval(qrLoginPollingInterval);
    qrLoginPollingInterval = setInterval(checkDesktopQrStatus, 2000);
}

// Fetch new QR image and token
function fetchNewDesktopQr() {
    const qrImg = document.getElementById('qrLoginImage');
    const statusMsg = document.getElementById('qrLoginStatusMsg');
    // AJAX to generate QR and get token
    fetch('generate_qr.php?action=info&t=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (data.success && data.has_qr && data.has_token) {
                // Set the QR image src (always force reload)
                qrImg.src = 'generate_qr.php?action=display&t=' + Date.now();
                // Use the RememberMe token for polling status
                fetch('generate_qr.php?action=display&t=' + Date.now(), { method: 'HEAD' })
                    .then(() => {
                        qrLoginToken = getRememberMeToken();
                    });
                // Get token from DB
                getRememberMeToken();
                statusMsg.textContent = 'Scan the QR code with your mobile app to login.';
            } else {
                qrImg.src = '';
                statusMsg.textContent = 'No QR code available. Please refresh.';
            }
        })
        .catch(() => {
            statusMsg.textContent = 'Unable to load QR code. Please try again.';
        });
}

// Get the RememberMe token from generate_qr.php
function getRememberMeToken() {
    // This assumes you have stored the token in the DB; just get it and store globally.
    fetch('generate_qr.php?action=info&t=' + Date.now())
        .then(res => res.json())
        .then(data => {
            if (data.success && data.has_token) {
                qrLoginToken = data.token || '';
            }
        });
}

// Polling: check if login confirmed
function checkDesktopQrStatus() {
    fetch('check_qr_status.php')
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                clearInterval(qrLoginPollingInterval);
                document.getElementById('qrLoginStatusMsg').textContent = "Login successful! Redirecting...";
                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            }
        });
}

// Listen for refresh button
document.getElementById('qrLoginRefreshBtn').addEventListener('click', function() {
    showDesktopQrCode();
});

// Update switchLoginMethod
function switchLoginMethod(method) {
    const traditionalTab = document.getElementById('traditionalTab');
    const qrTab = document.getElementById('qrTab');
    const traditionalSection = document.querySelector('.traditional-login-section');
    const qrSection = document.querySelector('.qr-login-section');
    const loginTypeInput = document.getElementById('loginTypeInput');
    const submitBtn = document.getElementById('loginSubmitBtn');

    if (method === 'traditional') {
        traditionalTab.classList.add('active');
        qrTab.classList.remove('active');
        traditionalSection.classList.remove('d-none');
        qrSection.classList.add('d-none');
        loginTypeInput.value = 'regular';
        submitBtn.textContent = 'LOGIN';
        submitBtn.classList.add('auth-submit-btn');
        if (qrLoginPollingInterval) clearInterval(qrLoginPollingInterval);
    } else {
        qrTab.classList.add('active');
        traditionalTab.classList.remove('active');
        traditionalSection.classList.add('d-none');
        qrSection.classList.remove('d-none');
        loginTypeInput.value = 'qr';
        submitBtn.textContent = 'LOGIN WITH QR CODE';
        submitBtn.classList.add('auth-submit-btn');
        showDesktopQrCode();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    switchLoginMethod('traditional');
});
</script>

                <!-- Registration Form Panel -->
                <div class="form-panel">
                    <div class="bg-section col-md-6 d-none d-md-block" id="registerBgSection">
                        <div class="bg-content">
                            <h2>Join HealthCare</h2>
                            <p>Create an account to start your wellness journey with us. Access personalized care and health resources.</p>
                        </div>
                    </div>
                    <div class="form-section col-12 col-md-6">
                        <h2 class="auth-heading">Create Your Account</h2>
                        <form id="modalRegistrationForm" action="register_process.php" method="POST">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
                                        </svg>
                                    </span>
                                    <input type="text" class="form-control" id="modalRegUserName" name="userName" placeholder="Username" required/>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                                        </svg>
                                    </span>
                                    <input type="email" class="form-control" id="modalEmail" name="email" placeholder="Email address" required/>
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                                            <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                                        </svg>
                                    </span>
                                    <input type="tel" class="form-control" id="modalPhoneNo" name="phoneNo" placeholder="Phone Number" required/>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="modalDateOfBirth" class="auth-field-label">Date Of Birth:</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar" viewBox="0 0 16 16">
                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                                        </svg>
                                    </span>
                                    <input type="date" class="form-control" id="modalDateOfBirth" name="dateOfBirth" required/>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="auth-field-label">Gender:</label>
                                <div class="d-flex flex-wrap">
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="gender" id="modalMale" value="male" checked/>
                                        <label class="form-check-label" for="modalMale">Male</label>
                                    </div>
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="gender" id="modalFemale" value="female"/>
                                        <label class="form-check-label" for="modalFemale">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="modalOther" value="other"/>
                                        <label class="form-check-label" for="modalOther">Other</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2zM5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/>
                                        </svg>
                                    </span>
                                    <input type="password" class="form-control" id="modalRegPassword" name="password" placeholder="Password" required/>
                                </div>
                                <div class="auth-helper-text">Password must be at least 8 characters with numbers and letters.</div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-check" viewBox="0 0 16 16">
                                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                                            <path d="M10.5 11.5a.5.5 0 0 1-1 0V10h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0v1h1a.5.5 0 0 1 0 1h-1v1.5z"/>
                                        </svg>
                                    </span>
                                    <input type="password" class="form-control" id="modalConfirmPassword" name="confirmPassword" placeholder="Confirm Password" required/>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalTermsCheck" name="termsCheck" required/>
                                    <label class="form-check-label" for="modalTermsCheck">
                                        I agree to the <a href="#" class="auth-text-link">Terms of Service</a> and <a href="#" class="auth-text-link">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button type="submit" class="auth-submit-btn">REGISTER</button>
                            </div>
                            <div class="text-center mt-3">
                                <p>Already have an account? <a href="#" class="auth-text-link" id="showLoginForm">Login here</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Forgot Password Panel -->
            <div class="forgot-password-container" id="forgotPasswordContainer">
                <div class="forgot-password-panel">
                    <div class="forgot-bg-section col-md-6 d-none d-md-block">
                        <div class="forgot-bg-content">
                            <h2>Reset Password</h2>
                            <p>Don't worry! It happens to everyone. We'll send you a verification code to reset your password.</p>
                        </div>
                    </div>
                    <div class="forgot-form-section col-12 col-md-6">
                        <h2 class="auth-heading">Forgot Password?</h2>
                        
                        <!-- Contact Method Selector -->
                        <div class="contact-method-selector">
                            <div class="contact-method-btn active" id="emailMethodBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                    <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                                </svg>
                                <div>Email</div>
                            </div>
                            <div class="contact-method-btn" id="phoneMethodBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                                    <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                                </svg>
                                <div>Phone</div>
                            </div>
                        </div>

                        <!-- Email Form -->
                        <form id="forgotEmailForm" action="forgot_password_direct.php" method="POST" style="display:block;">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                                        </svg>
                                    </span>
                                    <input type="email" class="form-control" id="forgotEmail" name="email" placeholder="Enter your email address" required/>
                                </div>
                            </div>
                            <input type="hidden" name="contactMethod" value="email">
                            <div class="mb-3">
                                <button type="submit" class="forgot-submit-btn">SEND VERIFICATION CODE</button>
                            </div>
                        </form>
                        
                        <!-- Phone Form -->
                        <form id="forgotPhoneForm" action="forgot_password_direct.php" method="POST" style="display:none;">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
                                            <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                                        </svg>
                                    </span>
                                    <input type="tel" class="form-control" id="forgotPhone" name="phone" placeholder="Enter your phone number" required/>
                                </div>
                            </div>
                            <input type="hidden" name="contactMethod" value="phone">
                            <div class="mb-3">
                                <button type="submit" class="forgot-submit-btn">SEND VERIFICATION CODE</button>
                            </div>
                        </form>

                        <div class="forgot-helper-text">
                            We'll send you a verification code to reset your password. Check your email or phone messages.
                        </div>

                        <div class="text-center mt-4">
                            <p>Remember your password? <a href="#" class="forgot-text-link" id="backToLoginForm">Back to Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>