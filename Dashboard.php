<?php 
require_once 'auth_helper.php';
// Start secure session
start_secure_session();

// Check session consistency
check_session_consistency();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    header("Location: login_process.php");
    exit();
}

// Check session timeout
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Session expired
    session_unset();     // unset $_SESSION variable for this page
    session_destroy();   // destroy session data
    header("Location: login_process.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity timestamp

// Include database connection
include('config.php');

// Get user ID from session
$userID = $_SESSION['id'];

// Initialize variables for dashboard data
$lastStrokeData = null;
$lastBMIData = null;
$nutritionStats = [
    'healthScore' => 0,
    'totalEntries' => 0,
    'healthyDays' => 0
];
$recentNutritionEntries = [];

// Fetch latest stroke detection data
try {
    $strokeQuery = "SELECT riskLevel, DetectedAt FROM health_record WHERE UserID = ? ORDER BY DetectedAt DESC LIMIT 1";
    $strokeStmt = $conn->prepare($strokeQuery);
    $strokeStmt->bind_param('s', $userID);
    $strokeStmt->execute();
    $strokeResult = $strokeStmt->get_result();
    if ($strokeResult->num_rows > 0) {
        $lastStrokeData = $strokeResult->fetch_assoc();
    }
    $strokeStmt->close();
} catch (Exception $e) {
    error_log("Error fetching stroke data: " . $e->getMessage());
}

// Fetch latest BMI data
try {
    $bmiQuery = "SELECT height, weight, bmi, detectedAt FROM bmi_record WHERE UserID = ? ORDER BY detectedAt DESC LIMIT 1";
    $bmiStmt = $conn->prepare($bmiQuery);
    $bmiStmt->bind_param('s', $userID);
    $bmiStmt->execute();
    $bmiResult = $bmiStmt->get_result();
    if ($bmiResult->num_rows > 0) {
        $lastBMIData = $bmiResult->fetch_assoc();
    }
    $bmiStmt->close();
} catch (Exception $e) {
    error_log("Error fetching BMI data: " . $e->getMessage());
}

// Fetch nutrition statistics
try {
    // Get total entries count
    $totalQuery = "SELECT COUNT(*) as total_entries FROM product_record WHERE UserID = ?";
    $totalStmt = $conn->prepare($totalQuery);
    $totalStmt->bind_param('s', $userID);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    if ($totalResult->num_rows > 0) {
        $totalRow = $totalResult->fetch_assoc();
        $nutritionStats['totalEntries'] = $totalRow['total_entries'];
    }
    $totalStmt->close();

    // Get healthy entries count (Score A or B, or numeric score >= 80)
    $healthyQuery = "SELECT COUNT(*) as healthy_entries FROM product_record 
                    WHERE UserID = ? AND (
                        ProductScore LIKE 'A%' OR 
                        ProductScore LIKE 'B%' OR 
                        (ProductScore REGEXP '^[0-9]+$' AND CAST(ProductScore AS DECIMAL(5,2)) >= 80)
                    )";
    $healthyStmt = $conn->prepare($healthyQuery);
    $healthyStmt->bind_param('s', $userID);
    $healthyStmt->execute();
    $healthyResult = $healthyStmt->get_result();
    if ($healthyResult->num_rows > 0) {
        $healthyRow = $healthyResult->fetch_assoc();
        $healthyEntries = $healthyRow['healthy_entries'];
        // Calculate health score percentage
        if ($nutritionStats['totalEntries'] > 0) {
            $nutritionStats['healthScore'] = round(($healthyEntries / $nutritionStats['totalEntries']) * 100);
        }
    }
    $healthyStmt->close();

    // Get number of healthy days
    $healthyDaysQuery = "SELECT COUNT(DISTINCT DATE(DetectedAt)) as healthy_days 
                        FROM product_record 
                        WHERE UserID = ? AND (
                            ProductScore LIKE 'A%' OR 
                            ProductScore LIKE 'B%' OR 
                            (ProductScore REGEXP '^[0-9]+$' AND CAST(ProductScore AS DECIMAL(5,2)) >= 80)
                        )";
    $healthyDaysStmt = $conn->prepare($healthyDaysQuery);
    $healthyDaysStmt->bind_param('s', $userID);
    $healthyDaysStmt->execute();
    $healthyDaysResult = $healthyDaysStmt->get_result();
    if ($healthyDaysResult->num_rows > 0) {
        $healthyDaysRow = $healthyDaysResult->fetch_assoc();
        $nutritionStats['healthyDays'] = $healthyDaysRow['healthy_days'];
    }
    $healthyDaysStmt->close();
} catch (Exception $e) {
    error_log("Error fetching nutrition stats: " . $e->getMessage());
}

// Fetch recent nutrition entries
try {
    $recentQuery = "SELECT ProductScore, DetectedAt, ProductImage FROM product_record WHERE UserID = ? ORDER BY DetectedAt DESC LIMIT 3";
    $recentStmt = $conn->prepare($recentQuery);
    $recentStmt->bind_param('s', $userID);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    while ($row = $recentResult->fetch_assoc()) {
        $recentNutritionEntries[] = $row;
    }
    $recentStmt->close();
} catch (Exception $e) {
    error_log("Error fetching recent nutrition entries: " . $e->getMessage());
}

// Helper function to format product score into readable label
function formatProductScore($score) {
    $score = strtoupper(trim($score));
    
    if (in_array($score, ['A', 'A+', 'A-'])) {
        return ["Great Choice", "success"];
    } elseif (in_array($score, ['B', 'B+', 'B-'])) {
        return ["Good Choice", "success"];
    } elseif (in_array($score, ['C', 'C+', 'C-'])) {
        return ["Moderate", "warning"];
    } elseif (in_array($score, ['D', 'D+', 'D-'])) {
        return ["Poor Choice", "danger"];
    } elseif (in_array($score, ['F', 'E'])) {
        return ["Very Poor Choice", "danger"];
    } else {
        // Handle numeric scores
        $numScore = floatval($score);
        if ($numScore >= 90) return ["Excellent Choice", "success"];
        elseif ($numScore >= 80) return ["Good Choice", "success"];
        elseif ($numScore >= 70) return ["Moderate", "warning"];
        elseif ($numScore >= 60) return ["Poor Choice", "danger"];
        else return ["Very Poor Choice", "danger"];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Services - SmartWell</title>
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
    <script>
      const recentNutritionEntries = <?php echo json_encode($recentNutritionEntries); ?>;
      const nutritionStats = <?php echo json_encode($nutritionStats); ?>;
    </script>   

    <style>
      /* Styles for recent entries in dashboard */
      .entry-card {
        transition: background-color 0.2s;
        border-radius: 4px;
      }
      
      .entry-card:hover {
        background-color: #f8f9fa;
      }
      
      .entry-image, .entry-image-placeholder {
        flex-shrink: 0;
      }
      
      .recent-entries {
        max-height: 350px;
        overflow-y: auto;
      }
      
      .entries-list {
        border-radius: 4px;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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

    <!--Dashboard Start-->
    <div class="dashboard-container" data-aos="fade-in">
      <!-- Main Dashboard Content -->
      <div class="container">
        <!-- Top Row with Last Records -->
        <div class="row g-0 mb-2">
          <!-- Stroke Detection Card -->
          <div class="col-md-6" data-aos="fade-left">
            <div class="dashboard-card stroke-card h-100">
              <div class="dashboard-card-header">
                <span>Last Stroke Detection</span>
              </div>
              <div class="dashboard-card-body">
                <div class="dashboard-metric">
                  <div class="dashboard-metric-icon stroke-risk-icon">
                    <i class="fas fa-heartbeat"></i>
                  </div>
                  <div>
                    <?php if ($lastStrokeData): ?>
                      <span class="dashboard-metric-value"><?php echo htmlspecialchars($lastStrokeData['riskLevel']); ?></span>
                      <span class="dashboard-metric-label">Last checked: <?php echo date('M j, Y', strtotime($lastStrokeData['DetectedAt'])); ?></span>
                    <?php else: ?>
                      <span class="dashboard-metric-value">No Data</span>
                      <span class="dashboard-metric-label">No stroke assessments yet</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- BMI Calculator Card -->
          <div class="col-md-6" data-aos="fade-left">
            <div class="dashboard-card bmi-card h-100">
              <div class="dashboard-card-header">
                <span>Last BMI Record</span>
              </div>
              <div class="dashboard-card-body">
                <div class="dashboard-metric">
                  <div class="dashboard-metric-icon bmi-icon">
                    <i class="fas fa-weight"></i>
                  </div>
                  <div>
                    <?php if ($lastBMIData): ?>
                      <span class="dashboard-metric-value">BMI = <?php echo number_format($lastBMIData['bmi'], 1); ?></span>
                    <?php else: ?>
                      <span class="dashboard-metric-value">No BMI Data</span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($lastBMIData): ?>
                <div class="row g-3">
                  <div class="col-6">
                    <div class="p-3 bg-light rounded">
                      <div class="small text-muted">Weight</div>
                      <div class="fw-bold"><?php echo $lastBMIData['weight']; ?> kg</div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded">
                      <div class="small text-muted">Height</div>
                      <div class="fw-bold"><?php echo $lastBMIData['height']; ?> cm</div>
                    </div>
                  </div>
                </div>
                <?php else: ?>
                <div class="text-center">
                  <p class="text-muted">No BMI calculations yet</p>
                  <a href="BMIcalculator.php" class="btn btn-primary btn-sm">Calculate BMI</a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Nutrition Journal and Analytics Card -->
        <div class="card mb-4 mt-4 card-journal" data-aos="fade-left">
            <div class="card-header px-3 py-2">
              <div class="row align-items-center">
                <div class="col-12 col-md-6 text-center text-md-start mb-2 mb-md-0">
                  <h5 class="mb-0 fw-bold">Nutrition Journal</h5>
                </div>
                <div class="col-12 col-md-6 text-center text-md-end">
                  <div class="d-grid d-md-flex justify-content-md-end mb-md-2">
                    <a href="product_journal.php" class="btn btn-sm custom-invert-btn-alt-full me-md-2 mb-2 mb-md-0">View Journal</a>
                    <a href="ProductDetection.php" class="btn btn-success btn-sm custom-green-btn">Go to Scanner</a>
                  </div>
                </div>
              </div>
            </div>
          <div class="card-body card-journal-body">
            <!-- Stats Grid -->
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-label">Nutrition Score</div>
                <div class="stat-value"><?php echo $nutritionStats['healthScore']; ?>%</div>
                <div class="stat-description">Percentage of nutritious choices</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Items Tracked</div>
                <div class="stat-value"><?php echo $nutritionStats['totalEntries']; ?></div>
                <div class="stat-description">Total foods analyzed</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Healthy Days</div>
                <div class="stat-value"><?php echo $nutritionStats['healthyDays']; ?></div>
                <div class="stat-description">Days with nutritious choices</div>
              </div>
            </div>
            
            <!-- Recent Entries Section -->
            <div class="recent-entries mt-4">
              <h6 class="mb-3 fw-semibold">Recent Food Entries</h6>
              
              <div class="entries-list">
                <?php if (empty($recentNutritionEntries)): ?>
                  <p class="text-muted text-center p-4">No food entries yet. Start scanning foods to build your nutrition journal!</p>
                <?php else: ?>
                  <?php foreach($recentNutritionEntries as $entry): 
                    $scoreInfo = formatProductScore($entry['ProductScore']);
                    $formattedDate = date('M j, Y', strtotime($entry['DetectedAt']));
                  ?>
                    <div class="entry-card d-flex p-3 mb-2 border-bottom">
                      <?php if (!empty($entry['ProductImage'])): ?>
                        <img src="<?php echo $entry['ProductImage']; ?>" class="entry-image" alt="Food item" style="width: 70px; height: 70px; object-fit: cover; border-radius: 4px;">
                      <?php else: ?>
                        <!-- Placeholder for missing image -->
                        <div class="entry-image-placeholder d-flex align-items-center justify-content-center" 
                             style="width: 70px; height: 70px; background-color: #f0f0f0; border-radius: 4px; color: #999; font-weight: bold; font-size: 16px;">
                          <?php echo substr($entry['ProductScore'], 0, 1); ?>
                        </div>
                      <?php endif; ?>
                      
                      <div class="ms-3">
                        <div class="fw-bold <?php echo $scoreInfo[1] === 'success' ? 'text-success' : ($scoreInfo[1] === 'danger' ? 'text-danger' : 'text-warning'); ?>">
                          <?php echo $scoreInfo[0]; ?>
                        </div>
                        <div class="small text-muted"><?php echo $formattedDate; ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  
                  <div class="text-center mt-3 pb-3">
                    <a href="ProductDetection.php" class="btn btn-sm btn-outline-primary">Scan More Foods</a>
                    <a href="product_journal.php" class="btn btn-sm btn-outline-secondary">View Full Journal</a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!--Dashboard End-->

    <!--Start Footer-->
    <footer>
      <div class="container" data-aos="fade-up">
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
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="modalRememberMe" name="rememberMe" <?php if (isset($_COOKIE['remembered_username'])) echo "checked"; ?> />
                                        <label class="form-check-label" for="modalRememberMe">Remember me</label>
                                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Javascript/script.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    
  </body>
</html>