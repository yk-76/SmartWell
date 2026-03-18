<?php
require_once 'auth_helper.php';
require_once 'config.php';

// Start secure session
start_secure_session();
check_session_consistency();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'showLogin=1&error=something';
    header("Location: $redirect_url");
    exit();
}

// Session timeout
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();
    session_destroy();
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    $redirect_url .= (parse_url($redirect_url, PHP_URL_QUERY) ? '&' : '?') . 'showLogin=1&error=something';
    header("Location: $redirect_url");
    exit();
}
$_SESSION['last_activity'] = time(); // update last activity timestamp

// Fetch user data from database
$user_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT UserID, UserName, Email, DateOfBirth, Gender, PhoneNo, ProfilePic, qr_code_path FROM user WHERE UserID = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user_data = null;
$qrImage = null;
$base64Image = '';
$hasQRCode = false;
$qrError = '';

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $qrImage = $user_data['qr_code_path'];
    if (!empty($qrImage)) {
        // Validate actual image data
        $imageInfo = @getimagesizefromstring($qrImage);
        if ($imageInfo !== false) {
            $base64Image = base64_encode($qrImage);
            $hasQRCode = true;
            error_log("QR Code loaded successfully for user: " . $user_id . ", size: " . strlen($qrImage) . " bytes");
        } else {
            $qrError = "QR code data is corrupted or not a valid image";
            error_log("Invalid QR code data for user: " . $user_id);
        }
    } else {
        $qrError = "No QR code data found";
        error_log("No QR code data for user: " . $user_id);
    }
} else {
    // User not found, force logout
    session_unset();
    session_destroy();
    header("Location: index.php?showLogin=1&error=User+not+found");
    exit();
}
$stmt->close();

// Function to generate QR code for user if not exists
function generateQRCodeForUser($user_id, $username, $conn) {
    $qr_text = "SmartWell User: " . $username . " (ID: " . $user_id . ")";
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data=" . urlencode($qr_text);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'SmartWell-App/1.0'
        ]
    ]);
    $qr_image_data = @file_get_contents($qr_url, false, $context);

    if ($qr_image_data !== false && strlen($qr_image_data) > 0) {
        $imageInfo = @getimagesizefromstring($qr_image_data);
        if ($imageInfo !== false) {
            $stmt = $conn->prepare("UPDATE user SET qr_code_path = ? WHERE UserID = ?");
            $null = NULL; // Required for 'b' type in bind_param
            $stmt->bind_param("bs", $null, $user_id);
            $stmt->send_long_data(0, $qr_image_data);
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Profile - SmartWell</title>
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
    <style>
      .alert {
        margin-bottom: 20px;
      }
      
      /* Profile Picture Styles */
      .up-profile-avatar {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 15px;
        border: 3px solid #fff;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
        position: relative;
      }

      .up-profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .up-avatar-edit {
        position: absolute;
        right: calc(50% - 85px);
        top: 110px;
        width: 40px;
        height: 40px;
        background: #1abc9c;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        box-shadow: 0 0 8px rgba(0,0,0,0.2);
        transition: all 0.3s;
        z-index: 5;
      }

      .up-avatar-edit:hover {
        background: #138d75;
      }

      .up-avatar-edit input {
        display: none;
      }

      .up-profile-header {
        position: relative;
        text-align: center;
        padding: 20px 0;
      }
      
      /* Loading indicator for profile picture upload */
      .profile-upload-loading {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.7);
        border-radius: 50%;
        justify-content: center;
        align-items: center;
      }
      
      /* ---- QR Expand Panel Styles ---- */
    .qrExpandPanel-btn-top {
        position: absolute;
        top: 10px;           /* Top right of image */
        right: 10px;
        background: rgba(44,62,80,0.85);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 36px; height: 36px;
        display: flex;
        align-items: center; justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.13);
        transition: background 0.18s;
        z-index: 8;
        font-size: 1.08rem;
        opacity: 0.94;
        outline: none;
    }
    .qrExpandPanel-btn-top:hover,
    .qrExpandPanel-btn-top:focus {
        background: #138d75;
        opacity: 1;
    }
    
    .qrExpandPanel-modal {
        display: none;
        position: fixed; z-index: 999999;
        left: 0; top: 0;
        width: 100vw; height: 100vh;
        background: rgba(0,0,0,0.7);
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 0;
    }
    .qrExpandPanel-modal.active {
        display: flex;
        animation: qrExpandPanel-fadein 0.25s;
    }
    .qrExpandPanel-image {
        max-width: 90vw;
        max-height: 80vh;
        border-radius: 18px;
        box-shadow: 0 6px 48px rgba(0,0,0,0.2);
        background: #fff;
        padding: 1.5vw;
        margin: auto;
    }
    .qrExpandPanel-close {
        position: absolute; top: 18px; right: 28px;
        color: #fff;
        font-size: 2.2rem;
        font-weight: bold;
        cursor: pointer;
        z-index: 999;
        text-shadow: 0 2px 8px #000;
    }
    .qrExpandPanel-close:focus { outline: none; }
    @media (max-width: 600px) {
        .qrExpandPanel-image { max-width: 96vw; max-height: 60vh; padding: 0.8vw; }
        .qrExpandPanel-close { top: 6px; right: 16px; font-size: 1.8rem;}
    }
    @keyframes qrExpandPanel-fadein {
        from { opacity: 0; transform: scale(0.95);}
        to   { opacity: 1; transform: scale(1);}
    }
    </style>
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
              class="nav-item profile-dropdown"
              id="profileMenuItem"
              style="<?php echo is_logged_in() ? '' : 'display: none;'; ?>"
            >
              <button
                id="profileDropdownBtn"
                class="profile-btn default-avatar ml-3 mt-2"
              >
                <i class="fas fa-user"></i>
              </button>
              <div id="profileDropdownContent" class="profile-content mt-4">
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

    <!--User Profile Start-->
    <div class="container-fluid profile-background pt-3 pb-1" data-aos="fade-in" >
      <div class="up-container userprofile-colors">
        <div class="up-header">
          <h1 class="mt-2">User Profile</h1>
        </div>

        <!-- Display success/error messages -->
        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
              $success_msg = "Profile updated successfully!";
              if ($_GET['success'] == 'profile_pic_updated') {
                $success_msg = "Profile picture updated successfully!";
              }
              echo htmlspecialchars($success_msg);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            $error_msg = "An error occurred while updating your profile.";
            if ($_GET['error'] == 'password_mismatch') {
                $error_msg = "New passwords do not match.";
            } elseif ($_GET['error'] == 'current_password_wrong') {
                $error_msg = "Current password is incorrect.";
            } elseif ($_GET['error'] == 'weak_password') {
                $error_msg = "Password must be at least 8 characters long.";
            } elseif ($_GET['error'] == 'email_exists') {
                $error_msg = "Email address is already in use by another account.";
            } elseif ($_GET['error'] == 'username_exists') {
                $error_msg = "Username is already taken.";
            } elseif ($_GET['error'] == 'invalid_file_type') {
                $error_msg = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
            } elseif ($_GET['error'] == 'file_too_large') {
                $error_msg = "File is too large. Please upload an image smaller than 2MB.";
            } elseif ($_GET['error'] == 'upload_failed') {
                $error_msg = "Failed to upload image. Please try again.";
            }
            echo htmlspecialchars($error_msg);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="up-profile-card" data-aos="fade-up">
          <div class="up-profile-header">
            <form id="profilePicForm" action="update_profile_pic.php" method="POST" enctype="multipart/form-data">
              <div class="up-profile-avatar bg-white">
                <?php
                if (!empty($user_data['ProfilePic'])) {
                    echo '<img src="data:image/png;base64,' . base64_encode($user_data['ProfilePic']) . '" id="profileImagePreview" alt="Profile Picture" />';
                } else {
                    // Pick avatar based on gender
                    $gender = strtolower($user_data['Gender'] ?? 'other');
                    switch ($gender) {
                        case 'male':
                            $avatar = 'image/default-avatar-male.png';
                            break;
                        case 'female':
                            $avatar = 'image/default-avatar-female.png';
                            break;
                        default:
                            $avatar = 'image/default-avatar-other.png';
                            break;
                    }
                    echo '<img src="' . $avatar . '" id="profileImagePreview" alt="Default Avatar" />';
                }
                ?>
                <div class="profile-upload-loading" id="uploadLoading">
                  <span class="spinner-border text-primary" role="status"></span>
                </div>
              </div>
              <label for="imageUpload" class="up-avatar-edit" data-aos="fade-left" data-aos-delay="200">
                <i class="fas fa-camera "></i>
                <input type="file" id="imageUpload" name="profileImage" accept="image/*" />
              </label>
            </form>
            <h2 class="up-profile-name"><?php echo htmlspecialchars($user_data['UserName']); ?></h2>
            <p class="up-profile-email"><?php echo htmlspecialchars($user_data['Email']); ?></p>

            <!-- QR Code Buttons -->
            <div class="qr-code-buttons mt-2 d-flex justify-content-center gap-2" data-aos="fade-in">
                <?php if ($hasQRCode): ?>
                    <button class="btn btn-success btn-lg custom-green-btn" onclick="toggleQRCode()">
                        <i class="fas fa-qrcode"></i> <span id="qrToggleText">Show QR Code</span>
                    </button>
                <?php elseif (!empty($qrError)): ?>
                    <button class="btn btn-success btn-lg custom-green-btn" onclick="generateNewQRCode()">
                        <i class="fas fa-refresh"></i> Generate QR Code
                    </button>
                    <small class="text-muted d-block mt-1">Issue: <?php echo htmlspecialchars($qrError); ?></small>
                <?php else: ?>
                    <button class="btn btn-success btn-lg custom-green-btn" onclick="generateNewQRCode()">
                        <i class="fas fa-qrcode"></i> Create QR Code
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- "Link Desktop Login" Scan Button for Profile -->
            <button type="button"
                    class="btn btn-outline-success btn-outline-success-custom" data-aos="fade-in"
                    style="vertical-align: middle;"
                    onclick="openQrScannerModal();event.stopPropagation();">
                <i class="fas fa-qrcode"></i> Link Desktop Login
            </button>



        <!-- QR Code Display Section -->
        <div id="qrCodeSection" style="display: none; margin-top: 15px;" class="border p-4 rounded bg-white shadow-sm">
            <div class="text-center">
                <h5 class="mb-3"><i class="fas fa-qrcode text-success"></i>   Your Personal QR Code</h5>
                
                <?php if ($hasQRCode): ?>
                    <div class="qr-code-container mb-3" style="position: relative;">
                        <img 
                            id="qrCodeImage"
                            src="data:image/png;base64,<?php echo $base64Image; ?>" 
                            alt="User QR Code" 
                            class="img-fluid qr-image" 
                            style="max-width: 250px; max-height: 250px;"
                            onload="handleQRSuccess()"
                            onerror="handleQRError(this)"
                        />
                        <!-- Expand Button: Top right of the image -->
                        <button type="button"
                            class="qrExpandPanel-btn-top"
                            aria-label="Expand QR"
                            onclick="qrExpandPanel_open('<?php echo 'data:image/png;base64,' . $base64Image; ?>');"
                            title="Expand QR Code"
                        >
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>

                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle"></i> 
                        This QR code contains your SmartWell profile information
                    </p>
                    <div class="qr-actions">
                        <button
                            class="btn btn-sm custom-invert-btn-alt-full me-2"
                            onclick="downloadQR()"
                            id="qrDownloadBtn"
                            data-base64="<?php echo htmlspecialchars($base64Image, ENT_QUOTES, 'UTF-8'); ?>"
                            data-username="<?php echo htmlspecialchars($user_data['UserName'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <i class="fas fa-download"></i> Download
                        </button>
    
                        <button
                            class="btn btn-sm btn-outline-secondary"
                            onclick="shareQR()"
                            id="qrShareBtn"
                            data-userid="<?php echo htmlspecialchars($user_data['UserID'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
    
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        QR code is not available. Click "Generate QR Code" to create one.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- QR Code Scan Section -->
        <div id="scanQRSection" style="display:none; margin-top:15px;" class="border p-4 rounded bg-white shadow-sm">
            <div class="text-center">
                <h5 class="mb-3"><i class="fas fa-qrcode text-primary"></i> Scan a Desktop QR Code</h5>
                <div class="mb-3">
                    <!-- Video element for camera feed -->
                    <video id="qrScanVideo" width="250" height="250" style="border-radius:12px; background:#000;" autoplay></video>
                </div>
                <div class="mb-3">
                    <!-- Button to switch camera (mobile support) -->
                    <button class="btn btn-sm btn-outline-secondary" onclick="switchScanCamera()">
                        <i class="fas fa-sync-alt"></i> Invert Camera
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="closeScanQRSection()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
                <div id="qrScanResult" class="text-success fw-bold"></div>
                <div id="qrScanError" class="text-danger"></div>
            </div>
        </div>

        <!-- Loading overlay for QR generation -->
        <div id="qrLoadingOverlay" style="display: none;" class="mt-3">
            <div class="alert alert-info text-center">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Generating your QR code...
            </div>
        </div>
    </div>

          <div class="up-profile-content" data-aos="fade-left">
            <form id="profileForm" action="user_update.php" method="POST">
              <div class="up-form-section">
                <h3 class="up-section-title">
                  <i class="fas fa-user"></i> Personal Information
                </h3>
                <div class="up-form-group">
                  <label class="up-form-label" for="username">Username</label>
                  <input
                    type="text"
                    class="up-form-control"
                    id="username"
                    name="username"
                    value="<?php echo htmlspecialchars($user_data['UserName']); ?>"
                    required
                  />
                </div>
                <div class="up-form-group">
                  <label class="up-form-label" for="email">Email Address</label>
                  <input
                    type="email"
                    class="up-form-control"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($user_data['Email']); ?>"
                    required
                  />
                </div>
                <div class="up-form-group">
                  <label class="up-form-label" for="phone">Phone Number</label>
                  <input
                    type="tel"
                    class="up-form-control"
                    id="phone"
                    name="phone"
                    value="<?php echo htmlspecialchars($user_data['PhoneNo'] ?? ''); ?>"
                    placeholder="Enter your phone number"
                  />
                </div>
                <div class="up-form-group">
                  <label class="up-form-label" for="dateOfBirth">Date of Birth</label>
                  <input
                    type="date"
                    class="up-form-control"
                    id="dateOfBirth"
                    name="dateOfBirth"
                    value="<?php echo htmlspecialchars($user_data['DateOfBirth'] ?? ''); ?>"
                  />
                </div>
                <div class="up-form-group">
                  <label class="up-form-label">Gender</label>
                  <div class="d-flex flex-wrap">
                    <div class="form-check me-3">
                      <input
                        class="form-check-input"
                        type="radio"
                        name="gender"
                        id="male"
                        value="male"
                        <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'male') ? 'checked' : ''; ?>
                      />
                      <label class="form-check-label" for="male">Male</label>
                    </div>
                    <div class="form-check me-3">
                      <input
                        class="form-check-input"
                        type="radio"
                        name="gender"
                        id="female"
                        value="female"
                        <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'female') ? 'checked' : ''; ?>
                      />
                      <label class="form-check-label" for="female">Female</label>
                    </div>
                    <div class="form-check">
                      <input
                        class="form-check-input"
                        type="radio"
                        name="gender"
                        id="other"
                        value="other"
                        <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'other') ? 'checked' : ''; ?>
                      />
                      <label class="form-check-label" for="other">Other</label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="up-divider"></div>

              <div class="up-form-section">
                <h3 class="up-section-title">
                  <i class="fas fa-lock"></i> Password
                </h3>
                <div class="up-form-group">
                  <label class="up-form-label" for="currentPassword"
                    >Current Password</label
                  >
                  <input
                    type="password"
                    class="up-form-control"
                    id="currentPassword"
                    name="currentPassword"
                    placeholder="Enter current password to change password"
                  />
                  <small class="form-text text-muted">Leave blank if you don't want to change your password</small>
                </div>
                <div class="up-form-row">
                  <div class="up-form-group">
                    <label class="up-form-label" for="newPassword"
                      >New Password</label
                    >
                    <input
                      type="password"
                      class="up-form-control"
                      id="newPassword"
                      name="newPassword"
                      placeholder="Enter new password"
                    />
                  </div>
                  <div class="up-form-group">
                    <label class="up-form-label" for="confirmPassword"
                      >Confirm New Password</label
                    >
                    <input
                      type="password"
                      class="up-form-control"
                      id="confirmPassword"
                      name="confirmPassword"
                      placeholder="Confirm new password"
                    />
                  </div>
                </div>
              </div>

              <button type="submit" class="up-save-button">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <!--User Profile End-->

    <!--Start Footer-->
    <footer data-aos="fade-up">
      <div class="container">
        <div class="row text-light text-center py-2 justify-content-center">
          <div class="col-sm-10 col-md-8 col-lg-6">
            <img src="image/SmartWell_logo_small.png" alt="" />
            <p>
              Your all-in-one health assistant for early stroke detection,
              identifying healthy and unhealthy foods, and keeping track of your
              BMI — promoting a healthier, smarter lifestyle.
            </p>
            <ul class="social pt-3">
              <li>
                <a href="#" target="_blank"><i class="fab fa-facebook"></i></a>
              </li>
              <li>
                <a href="#" target="_blank"><i class="fab fa-twitter"></i></a>
              </li>
              <li>
                <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
              </li>
              <li>
                <a href="#" target="_blank"><i class="fab fa-youtube"></i></a>
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
        <a href="index.html" target="_blank" class="text-white">
          <span class="text-success">SmartWell</span>, All Right Reserved.
        </a>
      </p>
    </div>
    <!--End Socket-->
    
    <!-- QR Scanner Modal for Link Desktop Login (reuse from index.php) -->
    <div id="qr-scan-modal" style="display:none; text-align:center; z-index:1055; background:rgba(0,0,0,0.7); position:fixed; top:0;left:0;width:100vw;height:100vh;justify-content:center;align-items:center;">
        <div style="background:#fff;display:inline-block;padding:2em 1em;border-radius:16px;max-width:95vw;">
            <h5>Scan Desktop QR Code to Login</h5>
            <div id="qr-reader" style="width:220px; margin:0 auto;"></div>
            <button class="btn btn-sm btn-outline-danger mt-2" onclick="closeQrScannerModal()">Close</button>
        </div>
    </div>

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
                    <div class="form-section col-12 col-md-6">
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
    
    <!-- QR Expand Panel Modal -->
    <div id="qrExpandPanel-modal" class="qrExpandPanel-modal">
      <span class="qrExpandPanel-close" onclick="qrExpandPanel_close()">&times;</span>
      <img id="qrExpandPanel-img" src="" alt="QR Code Enlarged" class="qrExpandPanel-image" />
    </div>
    
    <!-- JavaScript Section -->
    <script>
  
      // Handle form validation
      document.getElementById('profileForm').addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        // If user wants to change password
        if (newPassword || confirmPassword) {
          if (!currentPassword) {
            e.preventDefault();
            alert('Please enter your current password to change your password.');
            return;
          }
          
          if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match.');
            return;
          }
          
          if (newPassword.length < 8) {
            e.preventDefault();
            alert('New password must be at least 8 characters long.');
            return;
          }
        }
      });

      // Handle image upload preview and form submission
      document.getElementById('imageUpload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          // Show loading indicator
          document.getElementById('uploadLoading').style.display = 'flex';
          
          // Show preview
          const reader = new FileReader();
          reader.onload = function(e) {
            document.getElementById('profileImagePreview').src = e.target.result;
          };
          reader.readAsDataURL(file);
          
          // Create FormData and submit via AJAX
          const formData = new FormData(document.getElementById('profilePicForm'));
          
          fetch('update_profile_pic.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            // Hide loading indicator
            document.getElementById('uploadLoading').style.display = 'none';
            
            if (response.ok) {
              // Refresh the page to show success message
              window.location.href = 'UserProfile.php?success=profile_pic_updated';
            } else {
              throw new Error('Failed to upload image');
            }
          })
          .catch(error => {
            // Hide loading indicator
            document.getElementById('uploadLoading').style.display = 'none';
            alert('Failed to upload image. Please try again.');
            console.error('Error:', error);
          });
        }
      });
      

        let scanStream = null;
        let scanFacingMode = "environment"; // Default: back camera
        let scanActive = false;
        
        function toggleScanQRSection() {
            const section = document.getElementById('scanQRSection');
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                startQRScanner();
            } else {
                closeScanQRSection();
            }
        }
        function closeScanQRSection() {
            document.getElementById('scanQRSection').style.display = 'none';
            stopQRScanner();
            document.getElementById('qrScanResult').innerText = '';
            document.getElementById('qrScanError').innerText = '';
        }
        
        function switchScanCamera() {
            scanFacingMode = scanFacingMode === "environment" ? "user" : "environment";
            stopQRScanner();
            startQRScanner();
        }
        
        async function startQRScanner() {
            scanActive = true;
            const video = document.getElementById('qrScanVideo');
            try {
                scanStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: scanFacingMode }
                });
                video.srcObject = scanStream;
                video.setAttribute('playsinline', true); // iOS
                video.play();
                scanQRFrame();
            } catch (err) {
                document.getElementById('qrScanError').innerText = 'Camera error: ' + err;
            }
        }
        function stopQRScanner() {
            scanActive = false;
            const video = document.getElementById('qrScanVideo');
            if (scanStream) {
                scanStream.getTracks().forEach(track => track.stop());
                scanStream = null;
            }
            video.srcObject = null;
        }
        
        // Real-time QR scan using jsQR
        function scanQRFrame() {
            if (!scanActive) return;
            const video = document.getElementById('qrScanVideo');
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });
                if (code) {
                    // Found QR! You can do more with code.data
                    document.getElementById('qrScanResult').innerText = "✅ QR Code: " + code.data;
                    stopQRScanner();
                    // You might want to send code.data to your server or do further processing
                    return;
                }
            }
            requestAnimationFrame(scanQRFrame);
        }
    
        let html5QrCode = null;
        let isScanning = false;
        let scanFinished = false;
        
        function openQrScannerModal() {
            document.getElementById('qr-scan-modal').style.display = 'flex'; // Show modal as flex for overlay
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
                errorMessage => {
                    // Optionally handle error messages
                }
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
        
        // --- QR Expand Panel Functions ---
function qrExpandPanel_open(src) {
    const panel = document.getElementById('qrExpandPanel-modal');
    const img = document.getElementById('qrExpandPanel-img');
    img.src = src;
    panel.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent background scroll
}
function qrExpandPanel_close() {
    const panel = document.getElementById('qrExpandPanel-modal');
    panel.classList.remove('active');
    setTimeout(() => { // Small delay for animation
        document.getElementById('qrExpandPanel-img').src = '';
    }, 200);
    document.body.style.overflow = '';
}
// Dismiss modal on backdrop click or ESC
document.getElementById('qrExpandPanel-modal').addEventListener('click', function(e) {
    if (e.target === this) qrExpandPanel_close();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') qrExpandPanel_close();
});

</script>

    <!-- Botpress WebChat -->
<script src="http://localhost:3000/assets/modules/channel-web/inject.js"></script>

<!--Botpress WebChat Script ONLY--!>
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

<!--Botpress WebChat CSS ONLY--!>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Javascript/script.js"></script>
    
  </body>
</html>

