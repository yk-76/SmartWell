<?php 
require_once 'auth_helper.php';
// Start secure session
start_secure_session();

// Check session consistency
check_session_consistency();


// Check session timeout
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Session expired
    session_unset();     // unset $_SESSION variable for this page
    session_destroy();   // destroy session data
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    // Append any error or success parameters
    $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'showLogin=1&error=something';
    header("Location: $redirect_url");
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity timestamp
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us - SmartWell</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/style.css" />
    <!--Bootstrap Bundle 5.0-->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <!--Google Fonts-->
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap"
      rel="stylesheet"
    />
    <!-- Font Awesome 6 (CDN, always works) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <!-- AOS CSS (put in <head>) -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  </head>
  <body>
    
    <!-- Navigation -->
    <nav class="navbar navbar-bg navbar-expand-lg px-0" data-aos="slide-down" data-aos-once="true" style="position: sticky; top: 0; z-index: 1000;">
      <div class="container">
        <a href="index.php" class="navbar-brand">
          <img src="image/SmartWell_logo.png" alt="SmartWell Logo" title="SmartWell Logo" />
        </a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarResponsive"
          aria-controls="navbarResponsive"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarResponsive">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item">
              <a href="index.php" class="nav-link">Home</a>
            </li>
            <li class="nav-item">
              <a href="About.php" class="nav-link">About Us</a>
            </li>
            <li class="nav-item">
              <a href="Service.php" class="nav-link">Services</a>
            </li>

            <!-- Login Button (shows when logged out) -->
            <li class="nav-item" id="loginMenuItem" style="<?php echo is_logged_in() ? 'display: none;' : ''; ?>">
              <a href="#" class="nav-link" id="loginBtn">Login</a>
            </li>

            <!-- Profile Dropdown (shows when logged in) -->
            <li
              class="nav-item profile-dropdown mobile-profile-dropdown-fix"
              id="profileMenuItem"
              style="<?php echo is_logged_in() ? '' : 'display: none;'; ?>"
            >
              <button
                id="profileDropdownBtn"
                class="profile-btn default-avatar ml-3 mt-2"
              >
                <i class="fas fa-user"></i>
              </button>
              <div id="profileDropdownContent" class="profile-content mt-lg-4">
                <div class="profile-header d-flex align-items-center justify-content-between">
                  <h6 class="mb-0"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></h6>
                  <div>
                    <a href="UserProfile.php" class="edit-profile">Edit profile <i class="fas fa-chevron-right"></i></a>
                    <!-- NEW: Add the "Link Desktop Login" scan button here -->
                    <button type="button" class="btn btn-outline-primary btn-sm ms-2 d-none" style="vertical-align: middle;" onclick="openQrScannerModal();event.stopPropagation();">
                        <i class="fas fa-qrcode"></i> Link Desktop Login
                    </button>
                  </div>
                </div>
                <ul class="profile-menu">
                  <li>
                    <a href="Dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                  </li>
                  <li>
                    <a href="History.php"><i class="far fa-clock"></i> History</a>
                  </li>
                  <li class="dropdown" id="servicesDropdown">
                    <a href="javascript:void(0);" onclick="toggleDropdown()">
                      <i class="far fa-lightbulb"></i> Services<span id="dropdownArrow" class="dropdownicon">&#9660;</span>
                    </a>
                    <ul class="dropdown-menu" id="dropdownMenu">
                      <li>
                        <a href="StrokeDetection.php" target="_blank">Stroke Detection</a>
                      </li>
                      <li>
                        <a href="ProductDetection.php" target="_blank">Product Detection</a>
                      </li>
                      <li>
                        <a href="BMIcalculator.php" target="_blank">BMI Calculator</a>
                      </li>
                    </ul>
                  </li>
                  <li class="divider"></li>
                  <li>
                    <a href="logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt text-danger"></i> Log out</a>
                  </li>
                </ul>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </nav>
    <!-- End Navigation -->

    <!-- Page Header Start -->
    <div class="page-header container-fluid" data-aos="fade-in" data-aos-delay="200">
      <div class="container text-center pt-5 pb-3">
        <h1 class="text-white display-4 mb-3">About Us</h1>
        <nav aria-label="page-header-nav animated slideInDown">
          <ol class="page-header-nav justify-content-center mb-0">
            <li class="page-header-nav-item">
              <a class="text-white" href="index.php">Home</a>
            </li>
            <li
              class="page-header-nav-item text-success active"
              aria-current="page"
            >
              About
            </li>
          </ol>
        </nav>
      </div>
    </div>
    <!-- Page Header End -->

    <!--Mission Column Start-->
    <div class="shadow-box mission-desktop-layout px-3 px-md-5 mt-5" data-aos="fade-up" data-aos-delay="300">
      <div class="row align-items-center">
        <!-- Mission Text -->
        <div class="col-lg-6 mobile-center mission-content">
          <h2 class="pb-2 mission-title text-success">Our Mission</h2>
          <p>
            <span class="d-none d-md-block">
              Our mission is to make health monitoring accessible through innovative technology. Early detection and informed choices can improve lives.
            </span>
            <span class="d-block d-md-none">
              Making health monitoring easier through smart technology.
            </span>
          </p>
          <p>
            <span class="d-none d-md-block">
              Using artificial intelligence and simple design, we provide tools for face stroke detection, healthy product classification, and BMI calculation to help users understand potential health risks.
            </span>
            <span class="d-block d-md-none">
              We use AI to offer tools like stroke detection, food rating, and BMI tracking.
            </span>
          </p>
          <p>
            <span class="d-none d-md-block">
              We aim to empower individuals and families to take control of their health anytime, anywhere.
            </span>
            <span class="d-block d-md-none">
              Our goal is to help everyone take control of their health anytime, anywhere.
            </span>
          </p>
        </div>
        <!-- Mission Image (with decorative circle for desktop) -->
        <div class="col-lg-6 pb-1 position-relative mission-image-wrapper">
          <div class="mission-image-circle d-none d-lg-block"></div>
          <img
            src="image/Doctor_03.jpg"
            class="fixed-size-mission d-none d-lg-block position-relative"
            alt="Doctor"
          />
        </div>
      </div>
    </div>
 
    <!-- Divider Line (Mobile Only) -->
    <hr class="mobile-divider" data-aos="fade-in">
    
    <!-- Beneficiaries -->
    <div class="container pt-0 pb-5" data-aos="fade-left">
      <div class="text-center mx-auto" style="max-width: 500px">
        <h2 class="benef-heading pb-2 mission-title text-success fw-bold">Our Beneficiaries </h2>
                <div class="border-success mx-auto mt-1 mb-4 custom-border"></div>

      </div>

      <div class="row g-4">
        <div class="col-lg-3 col-md-6" data-aos="fade-left">
          <div class="bnf-item text-center rounded overflow-hidden img-shadow">
            <img class="img-fluid" src="image/Beneficiary_01.jpg" alt="" />
            <div class="bnf-text">
              <div class="bnf-title px-3">
                <h5>Doctors and Medical Professionals</h5>
              </div>
              <div class="bnf-social pt-3 text-light px-3">
                <p>
                  Can use stroke detection to quickly screen patients and
                  support faster diagnosis.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6" data-aos="fade-left" data-aos-delay="200">
          <div class="bnf-item text-center rounded overflow-hidden img-shadow">
            <img class="img-fluid" src="image/Beneficiary_02.jpg" alt="" />
            <div class="bnf-text">
              <div class="bnf-title px-3">
                <h5>Health-Conscious Consumers</h5>
              </div>
              <div class="bnf-social pt-3 text-light px-3">
                <p>
                  Can use product classification to make healthier food choices.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6" data-aos="fade-left" data-aos-delay="300">
          <div class="bnf-item text-center rounded overflow-hidden img-shadow">
            <img class="img-fluid" src="image/Beneficiary_03.jpg" alt="" />
            <div class="bnf-text">
              <div class="bnf-title px-3">
                <h5>Fitness Trainers and Dietitians</h5>
              </div>
              <div class="bnf-social pt-3 text-light px-3">
                <p>
                  Can use the BMI calculator to assess health and plan
                  personalized routines.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6" data-aos="fade-left" data-aos-delay="400">
          <div class="bnf-item text-center rounded overflow-hidden img-shadow">
            <img class="img-fluid" src="image/Beneficiary_04.jpg" alt="" />
            <div class="bnf-text">
              <div class="bnf-title px-3">
                <h5>Caregivers and Family Members</h5>
              </div>
              <div class="bnf-social pt-3 text-light px-3">
                <p>
                  Can use stroke and BMI tools to monitor loved ones and take
                  early action.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Beneficiaries End -->

    <!--Start Footer-->
    <footer data-aos="fade-up">
      <div class="container">
        <div class="row text-light text-center py-2 justify-content-center">
          <div class="col-sm-10 col-md-8 col-lg-8">
            <img src="image/SmartWell_logo_small.png" alt="" />
            <p>
              Your all-in-one health assistant for early stroke detection,
              identifying healthy and unhealthy foods, and keeping track of your
              BMI — promoting a healthier, smarter lifestyle.
            </p>
            <ul class="social pt-3">
              <li>
                <a href="#" target="_blank"><i class="fab fa-facebook mx-3"></i></a>
              </li>
              <li>
                <a href="#" target="_blank"><i class="fab fa-twitter mx-3"></i></a>
              </li>
              <li>
                <a href="#" target="_blank"><i class="fab fa-instagram mx-3"></i></a>
              </li>
              <li>
                <a
                  href="https://youtu.be/dQw4w9WgXcQ?si=P4noS1gOnLpL3tXq"
                  target="_blank"
                  ><i class="fab fa-youtube mx-3"></i
                ></a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </footer>
    <!--End Footer-->

    <!--Start Socket-->
    <div class="socket text-light text-center py-3">
      <p>
        &copy;
        <a href="index.php" target="_blank" class="text-white">
          <span class="text-success">SmartWell</span>, All Right Reserved.
        </a>
      </p>
    </div>
    <!--End Socket-->

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
                                    <?php
                                    if (isset($_SESSION['login_errors']) && !empty($_SESSION['login_errors'])) {
                                        echo '<div class="alert alert-danger">';
                                        foreach ($_SESSION['login_errors'] as $error) {
                                            echo htmlspecialchars($error) . '<br>';
                                        }
                                        echo '</div>';
                                        unset($_SESSION['login_errors']);
                                    }
                                    if (isset($_GET['error']) && !empty($_GET['error'])) {
                                        echo '<div class="alert alert-danger">';
                                        echo htmlspecialchars(urldecode($_GET['error']));
                                        echo '</div>';
                                    }
                                    ?>
                                    <h2 class="auth-heading">User Login</h2>
    
                        <form id="modalLoginForm" action="login_process.php" method="POST">
                            <!-- Login Method Switch Tabs (just one now) -->
                            <div class="d-flex justify-content-center mb-3">
                                <button type="button" class="btn custom-green-login-btn me-2 active" id="traditionalTab" onclick="switchLoginMethod('traditional')">Login with Username</button>
                                <button type="button" class="btn custom-green-login-btn" id="qrShowTab" onclick="switchLoginMethod('showqr')">Login with QR Code</button>
                            </div>
    
                            <!-- Login Type Hidden Field -->
                            <input type="hidden" id="loginTypeInput" name="login_type" value="regular">
                            <!-- Traditional Login Section -->
                            <div class="traditional-login-section">
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text icon-login">
                                            <i class="fa-solid fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="modalUserName" name="uname" placeholder="User Name" value="<?php echo isset($_COOKIE['remembered_username']) ? htmlspecialchars($_COOKIE['remembered_username']) : ''; ?>" required/>
                                    </div>
                                </div>
                                <div class="mb-3 mt-lg-2">
                                    <div class="input-group">
                                        <span class="input-group-text icon-login">
                                            <i class="icon-login fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="modalPassword" name="password" placeholder="Password" required/>
                                    </div>
                                </div>
                                <div class="mb-3 d-flex justify-content-between">
                                    <!--<div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="modalRememberMe" name="rememberMe" <?php if (isset($_COOKIE['remembered_username'])) echo "checked"; ?> />
                                        <label class="form-check-label" for="modalRememberMe">Remember me</label>
                                    </div>-->
                                    <a href="#" class="auth-text-link" id="showForgotPasswordForm">Forgot password?</a>
                                </div>
                            </div>
    
                            <!-- QR Show Section (for desktop QR code login) -->
                            <div class="qr-show-section d-none">
                                <div class="mb-3 text-center">
                                    <div id="qrShowImageContainer" style="display: inline-block; border: 3px dashed #0d6efd; border-radius: 8px; padding: 12px;">
                                        <img id="qrShowImage" src="" alt="Scan this QR code with your phone" style="width:220px; height:220px;">
                                    </div>
                                    <div id="qrShowStatusMsg" class="qr-status-message mt-2 small text-muted"></div>
                                    <button id="qrShowRefreshBtn" type="button" class="btn btn-link mt-2 p-0" style="font-size:0.95em;">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh QR Code
                                    </button>
                                </div>
                            </div>
    
                            <div class="mb-3">
                                <button type="submit" class="btn-modern-login w-100" id="loginSubmitBtn">Login</button>
                            </div>
                            <div class="text-center mt-3">
                                <p>Don't have an account? <a href="#" class="auth-text-link" id="showRegisterForm">Register here</a></p>
                            </div>
                            <input type="hidden" id="qrDataInput" name="qr_data" value="" />
                        </form>
                    </div>
                </div>
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
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="modalRegUserName" name="userName" placeholder="Username" required/>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="modalEmail" name="email" placeholder="Email address" required/>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <div class="input-group">
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="modalPhoneNo" name="phoneNo" placeholder="Phone Number" required/>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="modalDateOfBirth" class="auth-field-label">Date Of Birth:</label>
                                    <div class="input-group">
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-calendar-days"></i>
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
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="modalRegPassword" name="password" placeholder="Password" required/>
                                    </div>
                                    <div class="auth-helper-text">Password must be at least 8 characters with numbers and letters.</div>
                                </div>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text icon-login ">
                                            <i class="fa-solid fa-lock"></i>
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
                                    <button type="submit" class="auth-submit-btn">Register</button>
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
                                <svg class="icon-green" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
                                    <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                                </svg>
                                <div>Email</div>
                            </div>
                        </div>
    
                            <!-- Email Form -->
                            <form id="forgotEmailForm" action="forgot_password_direct.php" method="POST" style="display:block;">
                                <div class="mb-3">
                                    <div class="input-group">
                                            <span class="input-group-text icon-login ">
                                                <i class="fa-solid fa-envelope"></i>
                                            </span>
                                        <input type="email" class="form-control" id="forgotEmail" name="email" placeholder="Enter your email address" required/>
                                    </div>
                                </div>
                                <input type="hidden" name="contactMethod" value="email">
                                <div class="mb-3">
                                    <button type="submit" class="auth-submit-btn">Send Verification Code</button>
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

    <!-- QR Scanner Modal for mobile "Link Desktop Login" -->
    <div id="qr-scan-modal" style="display:none; text-align:center;">
      <h5>Scan Desktop QR Code to Login</h5>
      <div id="qr-reader" style="width:220px; margin:0 auto;"></div>
      <button onclick="closeQrScannerModal()">Close</button>
    </div>
    
    <!-- JavaScript Section -->
    <script>
    let html5QrCode = null;
    let isScanning = false;
    
    // Only two tabs now: traditional and showqr
    function switchLoginMethod(method) {
        const traditionalTab = document.getElementById('traditionalTab');
        const qrShowTab = document.getElementById('qrShowTab');
        const traditionalSection = document.querySelector('.traditional-login-section');
        const qrShowSection = document.querySelector('.qr-show-section');
        const loginTypeInput = document.getElementById('loginTypeInput');
        const submitBtn = document.getElementById('loginSubmitBtn');
        if (method === 'traditional') {
            traditionalTab.classList.add('active');
            qrShowTab.classList.remove('active');
            traditionalSection.classList.remove('d-none');
            qrShowSection.classList.add('d-none');
            loginTypeInput.value = 'regular';
            submitBtn.textContent = 'Login';
            submitBtn.classList.add('auth-submit-btn');
            submitBtn.style.display = 'block'; // <-- Show login button
            stopQrLoginPolling();
        } else if (method === 'showqr') {
            qrShowTab.classList.add('active');
            traditionalTab.classList.remove('active');
            traditionalSection.classList.add('d-none');
            qrShowSection.classList.remove('d-none');
            loginTypeInput.value = 'qr_show';
            submitBtn.classList.add('auth-submit-btn');
            submitBtn.style.display = 'none'; // <-- Hide login button
            showDesktopQrCode();
            startQrLoginPolling();
        }
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        switchLoginMethod('traditional');
        document.getElementById('qrShowRefreshBtn').addEventListener('click', function() {
            showDesktopQrCode();
        });
    });
    
    let qrLoginPollingInterval = null;
    function showDesktopQrCode() {
        document.getElementById('qrShowImage').src = 'qr_login_session.php?t=' + Date.now();
        document.getElementById('qrShowStatusMsg').textContent = 'Scan this QR code with your mobile app to login.';
    }
    function startQrLoginPolling() {
        if (qrLoginPollingInterval) clearInterval(qrLoginPollingInterval);
        qrLoginPollingInterval = setInterval(checkDesktopQrStatus, 2000);
    }
    function stopQrLoginPolling() {
        if (qrLoginPollingInterval) {
            clearInterval(qrLoginPollingInterval);
            qrLoginPollingInterval = null;
        }
    }
    function checkDesktopQrStatus() {
        fetch('check_qr_status.php')
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    stopQrLoginPolling();
                    document.getElementById('qrShowStatusMsg').textContent = "Login successful! Redirecting...";
                    setTimeout(() => {
                        window.location.reload(true);
                    }, 1200);
                }
            });
    }
    
    // QR Scanner Modal for linking desktop from mobile (in profile)
    let qrScanner = null;
    let scanFinished = false;
    function openQrScannerModal() {
        document.getElementById('qr-scan-modal').style.display = 'block';
        startQrScanner();
    }
    function closeQrScannerModal() {
        stopQrScanner();
        document.getElementById('qr-scan-modal').style.display = 'none';
    }
    function startQrScanner() {
        const qrReader = document.getElementById('qr-reader');
        if (isScanning || !qrReader) return;
        isScanning = true;
        scanFinished = false;
        html5QrCode = new Html5Qrcode("qr-reader");
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 220 },
            qrCodeMessage => {
                if (scanFinished) return;
                scanFinished = true;
                let token = "";
                try {
                    const obj = JSON.parse(qrCodeMessage);
                    token = obj.qr_login_token || "";
                } catch (e) {
                    alert("Invalid QR code format.");
                    closeQrScannerModal();
                    return;
                }
                if (!token) {
                    alert("No login token found in QR code.");
                    closeQrScannerModal();
                    return;
                }
                fetch('qr_link_desktop.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ token })
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        alert('Desktop login confirmed!');
                    } else {
                        alert('QR code invalid or expired.');
                    }
                    closeQrScannerModal();
                });
            },
            errorMessage => {}
        );
    }
    function stopQrScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                isScanning = false;
            }).catch(() => {
                isScanning = false;
            });
        }
    }
    
    // Place at the end of your HTML, or in your JS file
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('profileDropdownContent');
      if (
        dropdown && 
        dropdown.style.display !== 'none' && 
        !dropdown.contains(event.target) && 
        !event.target.closest('#profileDropdownBtn')
      ) {
        dropdown.style.display = 'none';
      }
    });
    document.addEventListener("DOMContentLoaded", function() {
      var profileBtn = document.getElementById('profileDropdownBtn');
      var dropdown = document.getElementById('profileDropdownContent');
      if(profileBtn && dropdown) {
        profileBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        });
    
        // Close dropdown if click outside
        document.addEventListener('click', function(e) {
          if(dropdown.style.display === 'block' && !dropdown.contains(e.target) && e.target !== profileBtn) {
            dropdown.style.display = 'none';
          }
        });
      }
    });
    
    </script>

<!-- Botpress WebChat -->
<script src="http://localhost:3000/assets/modules/channel-web/inject.js"></script>

<script>
  // Mobile Stop Carousel
  document.addEventListener("DOMContentLoaded", function () {
    const isMobile = window.innerWidth < 768;
    const carouselEl = document.querySelector('#carouselExampleFade');

    if (isMobile) {
      // Disable swipe and keyboard controls
      carouselEl.addEventListener('touchstart', e => e.preventDefault(), { passive: false });
      carouselEl.addEventListener('touchmove', e => e.preventDefault(), { passive: false });
      carouselEl.addEventListener('keydown', e => e.preventDefault());
      
      // Optionally hide controls
      document.querySelectorAll('.carousel-control-prev, .carousel-control-next').forEach(btn => {
        btn.style.display = 'none';
      });
    } else {
      new bootstrap.Carousel(carouselEl, {
        interval: 5000,
        ride: 'carousel'
      });
    }
  });

  // Random message
  const messages = [
    "You can ask me anything here!",
    "Need help? I'm here for you.",
    "Hi! What can I do for you today?",
    "SmartWell Assistant is ready!",
    "Have a question? Ask away!"
  ];
  const randomText = messages[Math.floor(Math.random() * messages.length)];

  // Initialize Botpress
  window.botpressWebChat.init({
    host: 'http://localhost:3000',
    botId: 'smartwell',
    hideWidget: false,
    showCloseButton: true,
    enableReset: true,
    containerWidth: '500px',
    containerHeight: '80%',
    layoutWidth: '100%',
    botName: 'SmartWell Assistant',
    stylesheet: 'http://localhost:3000/assets/modules/channel-web/default.css',
    locale: 'en'
  });

  // Wait for widget to appear and then attach the message
window.addEventListener('load', () => {
  const checkInterval = setInterval(() => {
    const widget = document.querySelector('#bp-web-widget');
    if (widget) {
      clearInterval(checkInterval);

      // Create bubble message
      const bubble = document.createElement('div');
      bubble.id = 'chat-welcome-msg';
      bubble.textContent = randomText;
      document.body.appendChild(bubble);
      bubble.style.display = 'block';

      // Auto-close message after 15 seconds
      setTimeout(() => {
        const msg = document.getElementById('chat-welcome-msg');
        if (msg) msg.remove();
      }, 5000);

      // OPTIONAL: Click to open chat
      bubble.onclick = () => {
        window.botpressWebChat.sendEvent({ type: 'show' });
        bubble.remove();
      };

      // Detect when chat is opened manually
      const observer = new MutationObserver(() => {
        const iframe = widget.querySelector('iframe');
        if (iframe && iframe.style.display !== 'none') {
          const msg = document.getElementById('chat-welcome-msg');
          if (msg) msg.remove();
          observer.disconnect();
        }
      });

      observer.observe(widget, { childList: true, subtree: true });
    }
  }, 300);
});

</script>

<style>
  #bp-web-widget {
    position: fixed !important;
    top: 5vh !important; /* navbar height (5%) */
    height: 80vh !important; /* chat window height (80%) */
    right: 20px !important;
    bottom: auto !important; /* unset bottom to allow top+height combo */
    max-height: none !important; /* prevent unwanted limits */
    z-index: 9999; /* make sure it's above other elements */
  }

  #chat-welcome-msg {
  position: fixed;
  bottom: 110px;
  right: 30px;
  background-color: #2e2e2e; /* dark gray */
  color: #fff;
  padding: 10px 14px;
  border-radius: 16px;
  font-family: 'Segoe UI', sans-serif;
  font-size: 14px;
  max-width: 240px;
  z-index: 99999;
  display: none;
  animation: fadeIn 0.4s ease-in-out;
}

#chat-welcome-msg::after {
  content: '';
  position: absolute;
  bottom: -10px;
  right: 16px;
  border-width: 10px 10px 0;
  border-style: solid;
  border-color: #2e2e2e transparent transparent transparent;
}


  #chat-welcome-msg::after {
    content: '';
    position: absolute;
    bottom: -10px;
    right: 16px;
    border-width: 10px 10px 0;
    border-style: solid;
    border-color: #2e2e2e transparent transparent transparent;
  }
  
      /* Force control on internal video feed rendered by html5-qrcode */
    #qrReader video {
      width: 100% !important;
      height: auto !important;
      aspect-ratio: 1 / 1 !important;
      object-fit: cover !important;
      border-radius: 8px;
      max-height: 250px;
    }
    
    /* Keep outer container size controlled */
    #qrReader {
      width: 100%;
      max-width: 250px;
      height: auto;
      margin: 0 auto;
      border: 3px dashed #0d6efd;
      border-radius: 8px;
      overflow: hidden;
    }

  @media (max-width: 768px) {
  .qr-login-section {
    padding: 10px;
  }

  #qrReader {
    width: 100% !important;
    height: 250px !important;
    max-height: 250px;
    aspect-ratio: 1 / 1;
    border: 2px dashed #0d6efd;
    border-radius: 8px;
    margin-bottom: 10px;
  }

  .qr-status-message {
    font-size: 0.9rem;
    text-align: center;
  }

  #loginSubmitBtn {
    font-size: 1rem;
  }
}

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<!-- Data AOS Fix (Mobile) -->
<style>
    @media (max-width: 768px) {
  html, body {
    overflow-x: hidden !important;
  }
}
</style>

    <!-- AOS JS (put before </body>) -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
      AOS.init();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Javascript/script.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>

  </body>
</html>

