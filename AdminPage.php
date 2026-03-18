<?php 
// Include database connection
include('config.php');
require_once 'auth_helper.php';
start_secure_session();

check_session_consistency();



// === AJAX user search handler ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_search' && isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $stmt = $conn->prepare(
        "SELECT UserID, UserName, Email, last_login, 
            CONCAT(
                CASE WHEN (SELECT COUNT(*) FROM health_record WHERE health_record.UserID = user.UserID) > 0 THEN 'Stroke,' ELSE '' END,
                CASE WHEN (SELECT COUNT(*) FROM product_record WHERE product_record.UserID = user.UserID) > 0 THEN 'Nutrition,' ELSE '' END,
                CASE WHEN (SELECT COUNT(*) FROM bmi_record WHERE bmi_record.UserID = user.UserID) > 0 THEN 'BMI' ELSE '' END
            ) as services_used,
            CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active' ELSE 'Inactive' END as status
        FROM user
        WHERE UserID LIKE CONCAT('%', ?, '%') OR UserName LIKE CONCAT('%', ?, '%')
        ORDER BY UserID ASC"
    );
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $servicesArray = array_filter(array_map('trim', explode(',', $row['services_used'])));
        $row['services_used'] = implode(', ', $servicesArray);
        $results[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['data' => $results]);
    exit;
}

// === AJAX stroke detection search handler ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stroke_search' && isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $stmt = $conn->prepare(
        "SELECT RecordID, UserID, riskLevel, DetectedAt 
         FROM health_record 
         WHERE RecordID LIKE CONCAT('%', ?, '%') OR UserID LIKE CONCAT('%', ?, '%') 
         ORDER BY DetectedAt ASC"
    );
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['data' => $results]);
    exit;
}

// === AJAX nutrition analysis search handler ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'nutrition_search' && isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $stmt = $conn->prepare(
        "SELECT ProductID as RecordID, UserID, ProductScore, DetectedAt 
         FROM product_record 
         WHERE ProductID LIKE CONCAT('%', ?, '%') OR UserID LIKE CONCAT('%', ?, '%') 
         ORDER BY DetectedAt ASC"
    );
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['data' => $results]);
    exit;
}

// === AJAX BMI calculator search handler ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'bmi_search' && isset($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
    $stmt = $conn->prepare(
        "SELECT bmiID, UserID, height, weight, bmi, detectedAt 
         FROM bmi_record 
         WHERE bmiID LIKE CONCAT('%', ?, '%') OR UserID LIKE CONCAT('%', ?, '%') 
         ORDER BY detectedAt ASC"
    );
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate category for BMI
        $bmi = $row['bmi'];
        if ($bmi < 18.5) {
            $row['category'] = 'Underweight';
            $row['category_class'] = 'status-medium';
        } elseif ($bmi < 25) {
            $row['category'] = 'Normal';
            $row['category_class'] = 'status-low';
        } elseif ($bmi < 30) {
            $row['category'] = 'Overweight';
            $row['category_class'] = 'status-medium';
        } else {
            $row['category'] = 'Obese';
            $row['category_class'] = 'status-high';
        }
        $results[] = $row;
    }
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['data' => $results]);
    exit;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['ajax' => $_GET['ajax'], 'q' => $_GET['q'] ?? '']);
    exit;
}


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

// Fetch dashboard statistics
$totalUsers = 0;
$totalStrokeDetections = 0;
$totalNutritionScans = 0;
$totalBmiCalculations = 0;
$activeUsers = 0;

// Count users
$userQuery = "SELECT COUNT(*) as total FROM user";
$userResult = $conn->query($userQuery);
if ($userResult && $userRow = $userResult->fetch_assoc()) {
    $totalUsers = $userRow['total'];
}

// Count stroke detections
$strokeQuery = "SELECT COUNT(*) as total FROM health_record";
$strokeResult = $conn->query($strokeQuery);
if ($strokeResult && $strokeRow = $strokeResult->fetch_assoc()) {
    $totalStrokeDetections = $strokeRow['total'];
}

// Count nutrition scans
$nutritionQuery = "SELECT COUNT(*) as total FROM product_record";
$nutritionResult = $conn->query($nutritionQuery);
if ($nutritionResult && $nutritionRow = $nutritionResult->fetch_assoc()) {
    $totalNutritionScans = $nutritionRow['total'];
}

// Count BMI calculations
$bmiQuery = "SELECT COUNT(*) as total FROM bmi_record";
$bmiResult = $conn->query($bmiQuery);
if ($bmiResult && $bmiRow = $bmiResult->fetch_assoc()) {
    $totalBmiCalculations = $bmiRow['total'];
}

// Count active users (users who have used any service in the last 30 days)
$activeUsersQuery = "SELECT COUNT(DISTINCT UserID) as active FROM (
    SELECT UserID FROM health_record WHERE DetectedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    UNION
    SELECT UserID FROM product_record WHERE DetectedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    UNION
    SELECT UserID FROM bmi_record WHERE detectedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
) as active_users";
$activeUsersResult = $conn->query($activeUsersQuery);
if ($activeUsersResult && $activeRow = $activeUsersResult->fetch_assoc()) {
    $activeUsers = $activeRow['active'];
}

// Get recent activity
$recentActivityQuery = "
    (SELECT 'Stroke Detection' as type, UserID, DetectedAt as timestamp, riskLevel as details 
     FROM health_record ORDER BY DetectedAt DESC LIMIT 5)
    UNION
    (SELECT 'Nutrition Scan' as type, UserID, DetectedAt as timestamp, ProductScore as details 
     FROM product_record ORDER BY DetectedAt DESC LIMIT 5)
    UNION
    (SELECT 'BMI Calculation' as type, UserID, detectedAt as timestamp, CONCAT(bmi, ' BMI') as details 
     FROM bmi_record ORDER BY detectedAt DESC LIMIT 5)
    ORDER BY timestamp DESC LIMIT 10";
$recentActivityResult = $conn->query($recentActivityQuery);
$recentActivity = [];
if ($recentActivityResult) {
    while ($row = $recentActivityResult->fetch_assoc()) {
        $recentActivity[] = $row;
    }
}

//  User Pagination 
$userPage = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;
$userPageSize = 10;
$userOffset = ($userPage - 1) * $userPageSize;
$userTotalRows = $conn->query("SELECT COUNT(*) as cnt FROM user")->fetch_assoc()['cnt'];
$userTotalPages = ceil($userTotalRows / $userPageSize);

$usersQuery = "SELECT UserID, UserName, Email, last_login, CONCAT(
    CASE WHEN (SELECT COUNT(*) FROM health_record WHERE health_record.UserID = user.UserID) > 0 THEN 'Stroke,' ELSE '' END,
    CASE WHEN (SELECT COUNT(*) FROM product_record WHERE product_record.UserID = user.UserID) > 0 THEN 'Nutrition,' ELSE '' END,
    CASE WHEN (SELECT COUNT(*) FROM bmi_record WHERE bmi_record.UserID = user.UserID) > 0 THEN 'BMI' ELSE '' END
) as services_used,
CASE 
    WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
    ELSE 'Inactive'
END as status
FROM user
ORDER BY UserID ASC
LIMIT $userOffset, $userPageSize";
$usersResult = $conn->query($usersQuery);
$users = [];
if ($usersResult) {
    while ($row = $usersResult->fetch_assoc()) {
        $users[] = $row;
    }
}
foreach ($users as &$user) {
    $servicesArray = array_filter(array_map('trim', explode(',', $user['services_used'])));
    $user['services_used'] = implode(', ', $servicesArray);
}
unset($user); 


// Nutrition Analysis Pagination 
$nutritionPage = isset($_GET['nutrition_page']) ? max(1, intval($_GET['nutrition_page'])) : 1;
$nutritionPageSize = 10;
$nutritionOffset = ($nutritionPage - 1) * $nutritionPageSize;

// Total rows & total pages
$nutritionTotalRows = $conn->query("SELECT COUNT(*) as cnt FROM product_record")->fetch_assoc()['cnt'];
$nutritionTotalPages = ceil($nutritionTotalRows / $nutritionPageSize);

// Fetch records
$nutritionRecordsQuery = "SELECT ProductID as RecordID, UserID, ProductScore, DetectedAt 
                         FROM product_record 
                         ORDER BY DetectedAt ASC 
                         LIMIT $nutritionOffset, $nutritionPageSize";
$nutritionRecordsResult = $conn->query($nutritionRecordsQuery);
$nutritionRecords = [];
if ($nutritionRecordsResult) {
    while ($row = $nutritionRecordsResult->fetch_assoc()) {
        $nutritionRecords[] = $row;
    }
}


// --- BMI Calculator Pagination ---
$bmiPage = isset($_GET['bmi_page']) ? max(1, intval($_GET['bmi_page'])) : 1;
$bmiPageSize = 10;
$bmiOffset = ($bmiPage - 1) * $bmiPageSize;

// Total rows & total pages
$bmiTotalRows = $conn->query("SELECT COUNT(*) as cnt FROM bmi_record")->fetch_assoc()['cnt'];
$bmiTotalPages = ceil($bmiTotalRows / $bmiPageSize);

// Fetch records
$bmiRecordsQuery = "SELECT bmiID, UserID, height, weight, bmi, detectedAt 
                   FROM bmi_record 
                   ORDER BY detectedAt ASC 
                   LIMIT $bmiOffset, $bmiPageSize";
$bmiRecordsResult = $conn->query($bmiRecordsQuery);
$bmiRecords = [];
if ($bmiRecordsResult) {
    while ($row = $bmiRecordsResult->fetch_assoc()) {
        // Calculate BMI category 
        $bmi = $row['bmi'];
        if ($bmi < 18.5) {
            $row['category'] = 'Underweight';
            $row['category_class'] = 'status-medium';
        } elseif ($bmi < 25) {
            $row['category'] = 'Normal';
            $row['category_class'] = 'status-low';
        } elseif ($bmi < 30) {
            $row['category'] = 'Overweight';
            $row['category_class'] = 'status-medium';
        } else {
            $row['category'] = 'Obese';
            $row['category_class'] = 'status-high';
        }
        $bmiRecords[] = $row;
    }
}

// Stroke Detection Pagination 
$strokePage = isset($_GET['stroke_page']) ? max(1, intval($_GET['stroke_page'])) : 1;
$strokePageSize = 10;
$strokeOffset = ($strokePage - 1) * $strokePageSize;

// Total rows & total pages
$strokeTotalRows = $conn->query("SELECT COUNT(*) as cnt FROM health_record")->fetch_assoc()['cnt'];
$strokeTotalPages = ceil($strokeTotalRows / $strokePageSize);

// Fetch records
$strokeRecordsQuery = "SELECT RecordID, UserID, riskLevel, DetectedAt 
                      FROM health_record 
                      ORDER BY DetectedAt ASC 
                      LIMIT $strokeOffset, $strokePageSize";
$strokeRecordsResult = $conn->query($strokeRecordsQuery);
$strokeRecords = [];
if ($strokeRecordsResult) {
    while ($row = $strokeRecordsResult->fetch_assoc()) {
        $strokeRecords[] = $row;
    }
}


// Helper function to determine class based on risk level
function getRiskLevelClass($riskLevel) {
    $riskLevel = strtolower($riskLevel);
    if (strpos($riskLevel, 'low') !== false) {
        return 'status-low';
    } elseif (strpos($riskLevel, 'medium') !== false || strpos($riskLevel, 'moderate') !== false) {
        return 'status-medium';
    } else {
        return 'status-high';
    }
}

// Helper function to determine nutrition score class
function getNutritionScoreClass($score) {
    $score = strtoupper(trim($score));
    if (in_array($score, ['A', 'A+', 'A-'])) return 'grade-a';
    elseif (in_array($score, ['B', 'B+', 'B-'])) return 'grade-b';
    elseif (in_array($score, ['C', 'C+', 'C-'])) return 'grade-c';
    elseif (in_array($score, ['D', 'D+', 'D-'])) return 'grade-d';
    elseif ($score === 'E') return 'grade-f'; 
    
    else {
        $numScore = floatval($score);
        if ($numScore >= 90) return 'grade-a';
        elseif ($numScore >= 80) return 'grade-b';
        elseif ($numScore >= 70) return 'grade-c';
        elseif ($numScore >= 60) return 'grade-d';
        else return 'grade-f';  
    }
}


//  Weekly usage stats for the bar chart (no CSS/JS conflicts) 
$thisMonday = date('Y-m-d 00:00:00', strtotime('monday this week'));
$thisSunday = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$thisWeekStats = [
    'BMI' => (int)($conn->query("SELECT COUNT(*) as cnt FROM bmi_record WHERE detectedAt BETWEEN '$thisMonday' AND '$thisSunday'")->fetch_assoc()['cnt']),
    'Stroke' => (int)($conn->query("SELECT COUNT(*) as cnt FROM health_record WHERE DetectedAt BETWEEN '$thisMonday' AND '$thisSunday'")->fetch_assoc()['cnt']),
    'Food' => (int)($conn->query("SELECT COUNT(*) as cnt FROM product_record WHERE DetectedAt BETWEEN '$thisMonday' AND '$thisSunday'")->fetch_assoc()['cnt'])
];
$thisWeekStatsJson = json_encode($thisWeekStats);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - SmartWell</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    <!--Bootstrap CSS-->
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <!--Style CSS-->
    <link rel="stylesheet" href="css/style.css" />
    <!--Bootstrap CSS 5.0-->
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
      crossorigin="anonymous"
    ></script>
    <!--Bootstrap Bundle 5.0-->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
      crossorigin="anonymous"
    />
    <!--Google Fonts-->
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap"
      rel="stylesheet"
    />
  </head>
<body>
<div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h1>SmartWell</h1>
                <p>Admin Dashboard</p>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" onclick="showSection('dashboard')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('users')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                        User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('stroke')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        Stroke Detection
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('nutrition')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707L6 18.414V5.586L3.707 3.293zM17.707 5.293L14 1.586v12.828l2.293 2.293A1 1 0 0018 16V6a1 1 0 00-.293-.707z" clip-rule="evenodd"/>
                        </svg>
                        Nutrition Analysis
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('bmi')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                        </svg>
                        BMI Calculator
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm1 2h10v10H4V5zm4.293 2.293a1 1 0 011.414 0l2 2a1 1 0 010 1.414l-2 2a1 1 0 01-1.414-1.414L9.586 10 8.293 8.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
        <!-- Header -->
        <header class="header">
            <h2 id="page-title">Dashboard Overview</h2>
            <div class="user-info d-none d-lg-flex align-items-center">
                <span class="me-2">
                    Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?>
                </span>
                <div class="avatar">
                    <?php echo isset($_SESSION['user_name']) ? htmlspecialchars(substr($_SESSION['user_name'], 0, 1)) : 'A'; ?>
                </div>
            </div>
        </header>


            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section">
              <div class="dashboard-flex">
                <!-- LEFT COLUMN: Stats and Chart in a column -->
                <div class="dashboard-main">
                  <div class="stats-grid-2x2">
                    <div class="stat-card stat-stroke">
                      <div class="stat-header">
                        <span class="stat-title">Stroke Detections</span>
                        <div class="stat-icon stroke-icon"><i class="fa-solid fa-brain"></i></div>
                      </div>
                      <div class="stat-number"><?php echo number_format($totalStrokeDetections); ?></div>
                      <div class="stat-change change-positive">
                        <span>↗</span>
                        <span>Total records</span>
                      </div>
                    </div>
                    <div class="stat-card stat-nutrition">
                      <div class="stat-header">
                        <span class="stat-title">Nutrition Scans</span>
                        <div class="stat-icon nutrition-icon"><i class="fa-solid fa-apple-whole"></i></div>
                      </div>
                      <div class="stat-number"><?php echo number_format($totalNutritionScans); ?></div>
                      <div class="stat-change change-positive">
                        <span>↗</span>
                        <span>Total scans</span>
                      </div>
                    </div>
                    <div class="stat-card stat-users">
                      <div class="stat-header">
                        <span class="stat-title">Active Users</span>
                        <div class="stat-icon users-icon"><i class="fa-solid fa-users"></i></div>
                      </div>
                      <div class="stat-number"><?php echo number_format($activeUsers); ?></div>
                      <div class="stat-change change-positive">
                        <span>↗</span>
                        <span>Out of <?php echo number_format($totalUsers); ?> total users</span>
                      </div>
                    </div>
                    <div class="stat-card stat-bmi">
                      <div class="stat-header">
                        <span class="stat-title">BMI Calculations</span>
                        <div class="stat-icon bmi-icon"><i class="fa-solid fa-weight-scale"></i></div>
                      </div>
                      <div class="stat-number"><?php echo number_format($totalBmiCalculations); ?></div>
                      <div class="stat-change change-positive">
                        <span>↗</span>
                        <span>Total calculations</span>
                      </div>
                    </div>
                  </div>
                  <div class="chart-container analytics-chart-small">
                    <h3 class="section-title">This Week's Service Usage</h3>
                    <canvas id="usageBarChartThisWeek" class="chartjs-thisweek"></canvas>
                  </div>
                </div>
                <!-- RIGHT COLUMN: Recent Activity -->
                <div class="dashboard-side">
                  <div class="activity-feed activity-feed-small">
                    <h3 class="section-title">Recent Activity</h3>
                    <?php if (empty($recentActivity)): ?>
                      <div class="empty-state">No recent activity found</div>
                    <?php else: ?>
                      <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                          <?php 
                            $icon = '<i class="fa-solid fa-clipboard" style="color:#333;"></i>';
                            if ($activity['type'] == 'Stroke Detection') {
                              $icon = '<i class="fa-solid fa-brain" style="color:#ec5e8f"></i>';
                            } else if ($activity['type'] == 'Nutrition Scan') {
                              $icon = '<i class="fa-solid fa-utensils" style="color:#ecb14f"></i>';
                            } else if ($activity['type'] == 'BMI Calculation') {
                              $icon = '<i class="fa-solid fa-weight-scale" style="color:#878484"></i>';
                            }
                          ?>
                          <div class="activity-icon"><?php echo $icon; ?></div>
                          <div class="activity-content">
                            <div class="activity-title">
                              <?php echo htmlspecialchars($activity['type']); ?>: 
                              <?php echo htmlspecialchars($activity['details']); ?>
                            </div>
                            <div class="activity-time">
                              <?php 
                              $timestamp = strtotime($activity['timestamp']);
                              $timeAgo = time() - $timestamp;
                              if ($timeAgo < 60) {
                                echo "Just now";
                              } elseif ($timeAgo < 3600) {
                                echo floor($timeAgo / 60) . " minutes ago";
                              } elseif ($timeAgo < 86400) {
                                echo floor($timeAgo / 3600) . " hours ago";
                              } else {
                                echo date("M j, Y", $timestamp);
                              }
                              ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            
              <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
              <script>
              //  This week's usage data (from PHP) 
              const statsThisWeek = <?php echo $thisWeekStatsJson; ?>;
              console.log(statsThisWeek);
              document.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('usageBarChartThisWeek');
                if (canvas) {
                  const ctx = canvas.getContext('2d');
                  new Chart(ctx, {
                    type: 'bar',
                    data: {
                      labels: ['BMI', 'Stroke Detection', 'Food Detection'],
                      datasets: [{
                        label: 'Total Uses (This Week)',
                        data: [statsThisWeek.BMI, statsThisWeek.Stroke, statsThisWeek.Food],
                        backgroundColor: [
                          'rgba(54, 162, 235, 0.7)',
                          'rgba(255, 99, 132, 0.7)',
                          'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                          'rgba(54, 162, 235, 1)',
                          'rgba(255, 99, 132, 1)',
                          'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 2
                      }]
                    },
                    options: {
                      responsive: true,
                      plugins: {
                        legend: { display: false },
                        title: { display: false }
                      },
                      scales: {
                        y: {
                          beginAtZero: true,
                          title: {
                            display: true,
                            text: 'Number of Uses'
                          },
                          ticks: {
                            stepSize: 1
                          }
                        }
                      }
                    }
                  });
                }
              });
              </script>
            </div>
            <!-- Dashboard Section END -->


            <!-- Users Section -->
            <div id="users-section" class="content-section" style="display: none;">
                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-bar">
                    <input type="text" class="search-input" placeholder="Search User ID or Name ..." id="userSearchInput" name="user_search">
                                <button class="btn btn-primary btn-search-user" type="button">Search</button>
                                    <button class="btn btn-outline-secondary btn-clear-search clear-search-btn-user" type="button">Clear</button>
                                </div>
                            </div>
                                <div class="table-responsive">

                        <table class="data-table">
                            <thead>
                                <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Last Login</th>
                                <th>Services Used</th>
                                <th>Status</th>
                                <th>Actions</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No users found</td> 
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['UserID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['UserName']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                        <td><?php echo $user['last_login'] ? date("Y-m-d", strtotime($user['last_login'])) : 'Never'; ?></td>
                                        <td><?php echo htmlspecialchars($user['services_used']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $user['status'] === 'Active' ? 'status-low' : 'status-medium'; ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions-column">
                                            <a href="AdminEditUser.php?id=<?php echo htmlspecialchars($user['UserID']); ?>" class="btn btn-sm btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button class="btn btn-sm btn-delete " 
                                                    data-user-id="<?php echo htmlspecialchars($user['UserID']); ?>"
                                                    data-user-name="<?php echo htmlspecialchars($user['UserName']); ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>      
                    </div>
                    <div class="pagination-controls d-flex justify-content-center mt-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($userPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?user_page=<?php echo $userPage-1; ?>#users-section">&lt;</a>
                                </li>
                            <?php endif; ?>
                            <?php
                            $maxPagesToShow = 5;
                            $startPage = max(1, $userPage - 2);
                            $endPage = min($userTotalPages, $startPage + $maxPagesToShow - 1);
                            if ($endPage - $startPage < $maxPagesToShow - 1) {
                                $startPage = max(1, $endPage - $maxPagesToShow + 1);
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo ($i == $userPage) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?user_page=<?php echo $i; ?>#users-section"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($userPage < $userTotalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?user_page=<?php echo $userPage+1; ?>#users-section">&gt;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>  
            </div>
        </div>
    
    
    
<style>
.search-bar {
width: 100%;
justify-content: flex-start;
}
.search-input {
min-width: 100px;
}

.btn-clear-search,
.btn-clear-user,
.btn-clear-stroke,
.btn-clear-nutrition,
.btn-clear-bmi {
  background: #fff !important;
  color: #343a40 !important;           
  border: 2px solid #e3e6ea !important;
  border-radius: 7px;
  font-weight: 600;
  padding: 7px 22px;
  transition: background 0.18s, color 0.18s, border 0.18s, box-shadow 0.16s;
  outline: none !important;
  box-shadow: none;
  margin-left: 8px;
}

.btn-clear-search:hover,
.btn-clear-user:hover,
.btn-clear-stroke:hover,
.btn-clear-nutrition:hover,
.btn-clear-bmi:hover,
.btn-clear-search:focus,
.btn-clear-user:focus,
.btn-clear-stroke:focus,
.btn-clear-nutrition:focus,
.btn-clear-bmi:focus {
  background: #f5f6fa !important;
  color: #212529 !important;           
  border-color: #bfc6cf !important;
  box-shadow: 0 0 0 2px #d8dee7;
  cursor: pointer;
}

.btn-clear-search:active,
.btn-clear-user:active,
.btn-clear-stroke:active,
.btn-clear-nutrition:active,
.btn-clear-bmi:active {
  background: #ececec !important;
  color: #111 !important;
  border-color: #bfc6cf !important;
}

.btn-search-user,
.btn-search-stroke,
.btn-search-nutrition,
.btn-search-bmi {
  background: #fff !important;            
  color: #212529 !important;              
  border: 2px solid #e3e6ea !important;  
  border-radius: 7px;
  font-weight: 600;
  padding: 7px 22px;
  transition: background 0.18s, color 0.18s, border 0.18s, box-shadow 0.16s;
  outline: none !important;
  box-shadow: none;
}

.btn-search-user:hover,
.btn-search-stroke:hover,
.btn-search-nutrition:hover,
.btn-search-bmi:hover,
.btn-search-user:focus,
.btn-search-stroke:focus,
.btn-search-nutrition:focus,
.btn-search-bmi:focus {
  background: #f5f6fa !important;         
  color: #111 !important;
  border-color: #bfc6cf !important;       
  box-shadow: 0 0 0 2px #d8dee7;          
  cursor: pointer;
}

.btn-search-user:active,
.btn-search-stroke:active,
.btn-search-nutrition:active,
.btn-search-bmi:active {
  background: #ececec !important;         
  color: #111 !important;
  border-color: #bfc6cf !important;
}

.pagination .page-link {
  background: #fff !important;
  color: #212529 !important;
  border: 2px solid #e3e6ea !important;
  transition: background 0.18s, color 0.18s, border 0.18s, box-shadow 0.16s;
  outline: none !important;
  box-shadow: none;
  margin:0 auto;
}

/* Hover & Focus */
.pagination .page-link:hover,
.pagination .page-link:focus {
  background: #f5f6fa !important;
  color: #111 !important;
  border-color: #bfc6cf !important;
  box-shadow: 0 0 0 2px #d8dee7;
  cursor: pointer;
}

/* Active Page */
.pagination .page-item.active .page-link {
  background: #e3e6ea !important;
  color: #111 !important;
  border-color: #bfc6cf !important;
  box-shadow: 0 0 0 2px #bfc6cf;
}

/* Disabled */
.pagination .page-item.disabled .page-link {
  color: #bfc6cf !important;
  background: #fff !important;
  border-color: #e3e6ea !important;
  cursor: not-allowed;
  pointer-events: none;
}

.data-table th.category-col,
    .data-table td.category-col {
        text-align: center !important;
    }
    
.actions-column .btn-primary {
    background-color: #4e73df !important;
    border-color: #4e73df !important;
    color: #fff !important;
    border-radius: 7px;
}
.actions-column .btn-primary:hover,
.actions-column .btn-primary:focus {
    background-color: #2e59d9 !important;
    border-color: #2e59d9 !important;
    color: #fff !important;
}

/* Table delete button (blue border for edit, red for delete) */
.actions-column .btn-delete,
.actions-column .btn-danger {
  background-color: #ef4444 !important;
  border: 2px solid #b91c1c !important;  /* Stronger red border */
  color: #fff !important;
  border-radius: 7px !important;
  font-weight: 600 !important;
  padding: 6px 18px !important;
  transition: background 0.18s, border 0.18s, color 0.18s;
  box-shadow: 0 1px 2px rgba(239, 68, 68, 0.05);
}

.actions-column .btn-delete:hover,
.actions-column .btn-delete:focus,
.actions-column .btn-danger:hover,
.actions-column .btn-danger:focus {
  background-color: #b91c1c !important;
  border: 2px solid #991b1b !important;
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.13);
}


.actions-column .btn-edit {
      background-color: #e0f0ff !important;    
      border: 2px solid #2563eb !important;    
      color: #2563eb !important;              
      border-radius: 7px !important;
      font-weight: 600 !important;
      padding: 6px 18px !important;
      box-shadow: 0 1px 2px rgba(37, 99, 235, 0.05);
      transition: 
    background 0.18s, 
    border 0.18s, 
    color 0.18s, 
    box-shadow 0.16s;
}

.actions-column .btn-edit:hover,
.actions-column .btn-edit:focus {
  background-color: #b6e0fe !important;    
  border: 2px solid #1e40af !important;    
  color: #1e40af !important;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.13);
}

.actions-column .btn-delete,
.btn.btn-delete {
  background-color: #fde7e9 !important;   
  border: 2px solid #b91c1c !important;   
  color: #b91c1c !important;              
  border-radius: 7px !important;
  font-weight: 600 !important;
  padding: 6px 18px !important;
  transition: background 0.18s, border 0.18s, color 0.18s;
  box-shadow: 0 1px 2px rgba(239, 68, 68, 0.05);
}

.actions-column .btn-delete:hover,
.actions-column .btn-delete:focus,
.btn.btn-delete:hover,
.btn.btn-delete:focus {
  background-color: #fca5a5 !important;   
  border: 2px solid #991b1b !important;   
  color: #991b1b !important;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.13);
}

 @media (max-width: 768px) {
  #page-title {
    font-size: 1.4rem;         
    margin-top: 0px;   
    margin: 0;
    padding: 0;
    text-align: center;
  }
  
}

 @media (max-width: 768px) {
    .data-table-container {
        padding-left: 0;
        padding-right: 0;
    }
    .table-responsive {
        margin-left: -8px;
        margin-right: -8px;
    }
    .data-table th, .data-table td {
        white-space: nowrap; 
        font-size: 14px;
        padding: 0.4rem 0.6rem;
    }
    .pagination-controls nav ul.pagination {
        flex-wrap: wrap;
    }
    .modal-dialog {
        max-width: 96vw;
        margin: 1.5rem auto;
    }
    
}

</style>

            <!-- Stroke Detection Section -->
            <div id="stroke-section" class="content-section" style="display: none;">
                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-bar">
<input type="text" class="search-input" placeholder="Search by Record ID..." id="strokeSearchInput" name="stroke_search">
                                <button class="btn btn-primary btn-search-stroke" type="button">Search</button>
                                    <button class="btn btn-outline-secondary btn-clear-search clear-search-btn-stroke" type="button">Clear</button>
                                </div>
                            </div>
                                <div class="table-responsive">

                        <table class="data-table">
                            <thead>
                                <tr>
                                <th>Record ID</th>
                                <th>User ID</th>
                                <th>Risk Level</th>
                                <th>Detected At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($strokeRecords)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No stroke detection records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($strokeRecords as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['RecordID']); ?></td>
                                        <td><?php echo htmlspecialchars($record['UserID']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo getRiskLevelClass($record['riskLevel']); ?>">
                                                <?php echo htmlspecialchars($record['riskLevel']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("Y-m-d H:i", strtotime($record['DetectedAt'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="pagination-controls d-flex justify-content-center mt-3">
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($strokePage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?stroke_page=<?php echo $strokePage-1; ?>#stroke-section">&lt;</a>
                                    </li>
                                <?php endif; ?>
                                <?php
                                $maxPagesToShow = 5;
                                $startPage = max(1, $strokePage - 2);
                                $endPage = min($strokeTotalPages, $startPage + $maxPagesToShow - 1);
                                if ($endPage - $startPage < $maxPagesToShow - 1) {
                                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo ($i == $strokePage) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?stroke_page=<?php echo $i; ?>#stroke-section"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($strokePage < $strokeTotalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?stroke_page=<?php echo $strokePage+1; ?>#stroke-section">&gt;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Nutrition Analysis Section -->
            <div id="nutrition-section" class="content-section" style="display: none;">
                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-bar">
<input type="text" class="search-input" placeholder="Search by Record ID..." id="nutritionSearchInput" name="nutrition_search">
                                <button class="btn btn-primary btn-search-nutrition" type="button">Search</button>
                                    <button class="btn btn-outline-secondary btn-clear-search clear-search-btn-nutrition" type="button">Clear</button>
                                        </div>
                                    </div>
                                        <div class="table-responsive">

                                    <table class="data-table ">
                                        <thead>
                                            <tr>
                                                <th>Record ID</th>
                                                <th>User ID</th>
                                                <th class="category-col">Nutrition Score</th>
                                                <th>Scanned At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            <?php if (empty($nutritionRecords)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No nutrition analysis records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($nutritionRecords as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['RecordID']); ?></td>
                                        <td><?php echo htmlspecialchars($record['UserID']); ?></td>
                                        <td class="category-col">
                                            <span class="status-badge <?php echo getNutritionScoreClass($record['ProductScore']); ?>">
                                                <?php echo htmlspecialchars($record['ProductScore']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("Y-m-d H:i", strtotime($record['DetectedAt'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="pagination-controls d-flex justify-content-center mt-3">
                    <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php if ($nutritionPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?nutrition_page=<?php echo $nutritionPage-1; ?>#nutrition-section">&lt;</a>
                            </li>
                        <?php endif; ?>
                        <?php
                        $maxPagesToShow = 5;
                        $startPage = max(1, $nutritionPage - 2);
                        $endPage = min($nutritionTotalPages, $startPage + $maxPagesToShow - 1);
                        if ($endPage - $startPage < $maxPagesToShow - 1) {
                            $startPage = max(1, $endPage - $maxPagesToShow + 1);
                        }
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo ($i == $nutritionPage) ? 'active' : ''; ?>">
                                <a class="page-link" href="?nutrition_page=<?php echo $i; ?>#nutrition-section"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($nutritionPage < $nutritionTotalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?nutrition_page=<?php echo $nutritionPage+1; ?>#nutrition-section">&gt;</a>
                            </li>
                        <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

            <!-- BMI Calculator Section -->
            <div id="bmi-section" class="content-section" style="display: none;">
                <div class="data-table-container">
                    <div class="table-header">
                        <div class="search-bar">
<input type="text" class="search-input" placeholder="Search by BMI ID..." id="bmiSearchInput" name="bmi_search">
                                <button class="btn btn-primary btn-search-bmi" type="button">Search</button>
                                    <button class="btn btn-outline-secondary btn-clear-search clear-search-btn-bmi" type="button">Clear</button>
                                </div>
                            </div>
                                <div class="table-responsive">

                        <table class="data-table">
                            <thead>
                                <tr>
                                <th>BMI ID</th>
                                <th>User ID</th>
                                <th>Height (cm)</th>
                                <th>Weight (kg)</th>
                                <th>BMI</th>
                                <th class="category-col">Category</th>
                                <th>Calculated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bmiRecords)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No BMI calculation records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bmiRecords as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['bmiID']); ?></td>
                                        <td><?php echo htmlspecialchars($record['UserID']); ?></td>
                                        <td><?php echo number_format($record['height'], 1); ?></td>
                                        <td><?php echo number_format($record['weight'], 1); ?></td>
                                        <td><?php echo number_format($record['bmi'], 1); ?></td>
                                            <td class="category-col">
                                            <span class="status-badge <?php echo $record['category_class']; ?>">
                                                <?php echo htmlspecialchars($record['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date("Y-m-d H:i", strtotime($record['detectedAt'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                             <?php endif; ?>
                            </tbody>
                            </table>
                                </div>
                            <div class="pagination-controls d-flex justify-content-center mt-3">
                            <nav>
                            <ul class="pagination pagination-sm mb-0">
                            <?php if ($bmiPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?bmi_page=<?php echo $bmiPage-1; ?>#bmi-section">&lt;</a>
                                </li>
                            <?php endif; ?>
                            <?php
                            $maxPagesToShow = 5;
                            $startPage = max(1, $bmiPage - 2);
                            $endPage = min($bmiTotalPages, $startPage + $maxPagesToShow - 1);
                            if ($endPage - $startPage < $maxPagesToShow - 1) {
                                $startPage = max(1, $endPage - $maxPagesToShow + 1);
                            }
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo ($i == $bmiPage) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?bmi_page=<?php echo $i; ?>#bmi-section"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($bmiPage < $bmiTotalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?bmi_page=<?php echo $bmiPage+1; ?>#bmi-section">&gt;</a>
                                            </li>
                                <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
    
    <!-- Delete User Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <span id="deleteUserName"></span>?</p>
                    <p class="text-danger">This action cannot be undone. All user data will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete User</button>
                </div>
                <input type="hidden" id="deleteUserId">

                </div>
            </div>
        </div>

    <script>
    // Universal clear button setup with event delegation
function setupClearButtonDelegation(sectionName) {
    const section = document.getElementById(sectionName + '-section');
    if (!section) return;
    const searchBar = section.querySelector('.search-bar');
    if (!searchBar) return;

    searchBar.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-clear-search')) {
            const searchInput = section.querySelector('.search-input');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            // Simulate click on active page link to restore table and pagination
            const activePage = section.querySelector('.pagination .page-item.active .page-link');
            if (activePage) {
                activePage.click();
            } else {
                // Fallback for first page
                if (sectionName === 'users') window.location.href = '?user_page=1#users-section';
                if (sectionName === 'stroke') window.location.href = '?stroke_page=1#stroke-section';
                if (sectionName === 'nutrition') window.location.href = '?nutrition_page=1#nutrition-section';
                if (sectionName === 'bmi') window.location.href = '?bmi_page=1#bmi-section';
            }
        }
    });
}

// Call it once for each section (only ONCE, not after each AJAX)
setupClearButtonDelegation('users');
setupClearButtonDelegation('stroke');
setupClearButtonDelegation('nutrition');
setupClearButtonDelegation('bmi');


    // Section switching and page title
    function showSection(section) {
        // Hide all sections
        document.querySelectorAll('.content-section').forEach(el => {
            el.style.display = 'none';
        });

        // Remove active class from all nav items
        document.querySelectorAll('.nav-link').forEach(el => {
            el.classList.remove('active');
        });

        // Show selected section
        const selectedSection = document.getElementById(section + '-section');
        if (selectedSection) {
            selectedSection.style.display = 'block';
        }

        // Update the page title
        const pageTitle = document.getElementById('page-title');
        switch(section) {
            case 'dashboard':
                pageTitle.textContent = 'Dashboard Overview';
                break;
            case 'users':
                pageTitle.textContent = 'User Management';
                break;
            case 'stroke':
                pageTitle.textContent = 'Stroke Detection Records';
                break;
            case 'nutrition':
                pageTitle.textContent = 'Nutrition Analysis';
                break;
            case 'bmi':
                pageTitle.textContent = 'BMI Records';
                break;
            case 'reports':
                pageTitle.textContent = 'Reports & Analytics';
                break;
            case 'settings':
                pageTitle.textContent = 'System Settings';
                break;
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            // If onclick attribute matches, add active
            if (link.getAttribute('onclick') === `showSection('${section}')`) {
                link.classList.add('active');
            }
        });
    }

    // User Management AJAX search
function setupUserSearchAJAX() {
    const section = document.getElementById('users-section');
    if (!section) return;
    const searchInput = section.querySelector('.search-input');
    const searchButton = section.querySelector('.btn-search-user');
    const clearButton = section.querySelector('.clear-search-btn-user');
    const tableBody = section.querySelector('tbody');
    const pagination = section.querySelector('.pagination-controls');

    function renderRows(users) {
        let html = '';
        if (users.length === 0) {
            html = `<tr><td colspan="7" class="text-center">No users found</td></tr>`;
        } else {
            users.forEach(user => {
                html += `<tr>
                    <td>${user.UserID}</td>
                    <td>${user.UserName}</td>
                    <td>${user.Email}</td>
                    <td>${user.last_login ? user.last_login.substring(0,10) : 'Never'}</td>
                    <td>${user.services_used ? user.services_used : ''}</td>
                    <td>
                      <span class="status-badge ${user.status === 'Active' ? 'status-low' : 'status-medium'}">
                        ${user.status}
                      </span>
                    </td>
                    <td class="actions-column">
                        <a href="AdminEditUser.php?id=${user.UserID}" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
                        <button class="btn btn-sm btn-delete" data-user-id="${user.UserID}" data-user-name="${user.UserName}"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>`;
            });
        }
        tableBody.innerHTML = html;
        pagination.style.display = users.length ? 'none' : '';
    }

    function doSearch() {
        const term = searchInput.value.trim();
        if (term === "") {
            loadDefaultTable();
            return;
        }
        fetch('?ajax=user_search&q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    function loadDefaultTable() {
        fetch('?ajax=user_search&q=')
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    if (searchButton) searchButton.onclick = doSearch;
    if (searchInput) searchInput.onkeyup = e => { if (e.key === "Enter") doSearch(); };
    
    if (clearButton) clearButton.onclick = () => {
    searchInput.value = '';
    searchInput.focus();
    loadDefaultTable(); // <- This triggers the AJAX to reload the default (paginated) table for the current page!
};


}


// JS equivalent of PHP's getRiskLevelClass
function getRiskLevelClass(riskLevel) {
    if (!riskLevel) return '';
    riskLevel = riskLevel.toLowerCase();
    if (riskLevel.includes('low')) {
        return 'status-low';
    } else if (riskLevel.includes('medium') || riskLevel.includes('moderate')) {
        return 'status-medium';
    } else {
        return 'status-high';
    }
}

// JS equivalent of PHP's getNutritionScoreClass
function getNutritionScoreClass(score) {
    if (!score) return '';
    score = score.trim().toUpperCase();
    if (['A', 'A+', 'A-'].includes(score)) return 'grade-a';
    if (['B', 'B+', 'B-'].includes(score)) return 'grade-b';
    if (['C', 'C+', 'C-'].includes(score)) return 'grade-c';
    if (['D', 'D+', 'D-'].includes(score)) return 'grade-d';
    if (score === 'E') return 'grade-f';

    // If numeric
    var numScore = parseFloat(score);
    if (!isNaN(numScore)) {
        if (numScore >= 90) return 'grade-a';
        if (numScore >= 80) return 'grade-b';
        if (numScore >= 70) return 'grade-c';
        if (numScore >= 60) return 'grade-d';
        return 'grade-f';
    }
    return '';
}


// Stroke Detection AJAX search
function setupStrokeSearchAJAX() {
    const section = document.getElementById('stroke-section');
    if (!section) return;
    const searchInput = section.querySelector('.search-input');
    const searchButton = section.querySelector('.btn-search-stroke');
    const clearButton = section.querySelector('.clear-search-btn-stroke');
    const tableBody = section.querySelector('tbody');
    const pagination = section.querySelector('.pagination-controls');

    function renderRows(records) {
        let html = '';
        if (records.length === 0) {
            html = `<tr><td colspan="5" class="text-center">No stroke detection records found</td></tr>`;
        } else {
            records.forEach(record => {
                html += `<tr>
                    <td>${record.RecordID}</td>
                    <td>${record.UserID}</td>
                    <td>
                      <span class="status-badge ${getRiskLevelClass(record.riskLevel)}">
                        ${record.riskLevel}
                      </span>
                    </td>
                    <td>${record.DetectedAt ? record.DetectedAt.replace('T',' ').substring(0,16) : ''}</td>
                </tr>`;
            });
        }
        tableBody.innerHTML = html;
        pagination.style.display = records.length ? 'none' : '';
    }

    function doSearch() {
        const term = searchInput.value.trim();
        if (term === "") {
            loadDefaultTable();
            return;
        }
        fetch('?ajax=stroke_search&q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    function loadDefaultTable() {
        // Get current page
        let currentPage = 1;
        const activePage = section.querySelector('.pagination .page-item.active .page-link');
        if (activePage && !isNaN(parseInt(activePage.textContent))) {
            currentPage = parseInt(activePage.textContent);
        }
        fetch(`?ajax=stroke_search&q=&stroke_page=${currentPage}`)
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    if (searchButton) searchButton.onclick = doSearch;
    if (searchInput) searchInput.onkeyup = e => { if (e.key === "Enter") doSearch(); };

    if (clearButton) clearButton.onclick = () => {
        searchInput.value = '';
        searchInput.focus();
        loadDefaultTable();
    };
}

// Nutrition Analysis AJAX search
function setupNutritionSearchAJAX() {
    const section = document.getElementById('nutrition-section');
    if (!section) return;
    const searchInput = section.querySelector('.search-input');
    const searchButton = section.querySelector('.btn-search-nutrition');
    const clearButton = section.querySelector('.clear-search-btn-nutrition');
    const tableBody = section.querySelector('tbody');
    const pagination = section.querySelector('.pagination-controls');

    function renderRows(records) {
        let html = '';
        if (records.length === 0) {
            html = `<tr><td colspan="5" class="text-center">No nutrition analysis records found</td></tr>`;
        } else {
            records.forEach(record => {
                html += `<tr>
                    <td>${record.RecordID}</td>
                    <td>${record.UserID}</td>
                    <td class="category-col">
                        <span class="status-badge ${getNutritionScoreClass(record.ProductScore)}">
                            ${record.ProductScore}
                        </span>
                    </td>
                    <td>${record.DetectedAt ? record.DetectedAt.replace('T',' ').substring(0,16) : ''}</td>
                </tr>`;
            });
        }
        tableBody.innerHTML = html;
        pagination.style.display = records.length ? 'none' : '';
    }

    function doSearch() {
        const term = searchInput.value.trim();
        if (term === "") {
            loadDefaultTable();
            return;
        }
        fetch('?ajax=nutrition_search&q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    function loadDefaultTable() {
        // Get current page
        let currentPage = 1;
        const activePage = section.querySelector('.pagination .page-item.active .page-link');
        if (activePage && !isNaN(parseInt(activePage.textContent))) {
            currentPage = parseInt(activePage.textContent);
        }
        fetch(`?ajax=nutrition_search&q=&nutrition_page=${currentPage}`)
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    if (searchButton) searchButton.onclick = doSearch;
    if (searchInput) searchInput.onkeyup = e => { if (e.key === "Enter") doSearch(); };

    if (clearButton) clearButton.onclick = () => {
        searchInput.value = '';
        searchInput.focus();
        loadDefaultTable();
    };
}


// BMI Calculator AJAX search
function setupBmiSearchAJAX() {
    const section = document.getElementById('bmi-section');
    if (!section) { console.log('No BMI section found!'); return; }
    const searchInput = section.querySelector('.search-input');
    const searchButton = section.querySelector('.btn-search-bmi');
    const clearButton = section.querySelector('.clear-search-btn-bmi');
    const tableBody = section.querySelector('tbody');
    const pagination = section.querySelector('.pagination-controls');

    function renderRows(records) {
        let html = '';
        if (!records || records.length === 0) {
            html = `<tr><td colspan="7" class="text-center">No BMI calculation records found</td></tr>`;
        } else {
            records.forEach(record => {
                html += `<tr>
                    <td>${record.bmiID}</td>
                    <td>${record.UserID}</td>
                    <td>${parseFloat(record.height).toFixed(1)}</td>
                    <td>${parseFloat(record.weight).toFixed(1)}</td>
                    <td>${parseFloat(record.bmi).toFixed(1)}</td>
                    <td class="category-col">
                        <span class="status-badge ${record.category_class}">${record.category}</span>
                    </td>
                    <td>${record.detectedAt ? record.detectedAt.replace('T',' ').substring(0,16) : ''}</td>
                </tr>`;
            });
        }
        tableBody.innerHTML = html;
        pagination.style.display = (records && records.length) ? 'none' : '';
    }

    function doSearch() {
        const term = searchInput.value.trim();
        if (term === "") {
            loadDefaultTable();
            return;
        }
        fetch('?ajax=bmi_search&q=' + encodeURIComponent(term))
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    function loadDefaultTable() {
        let currentPage = 1;
        const activePage = section.querySelector('.pagination .page-item.active .page-link');
        if (activePage && !isNaN(parseInt(activePage.textContent))) {
            currentPage = parseInt(activePage.textContent);
        }
        fetch(`?ajax=bmi_search&q=&bmi_page=${currentPage}`)
            .then(res => res.json())
            .then(data => renderRows(data.data));
    }

    if (searchButton) searchButton.onclick = doSearch;
    if (searchInput) searchInput.onkeyup = e => { if (e.key === "Enter") doSearch(); };
    if (clearButton) clearButton.onclick = () => {
        searchInput.value = '';
        searchInput.focus();
        loadDefaultTable();
    };
}

    document.addEventListener('DOMContentLoaded', function() {
    // Get section from hash, fallback to dashboard
    let hash = window.location.hash || '';
    let match = hash.match(/^#(\w+)-section$/);
    let section = match ? match[1] : 'dashboard';
    showSection(section);

    setupUserSearchAJAX();
    setupStrokeSearchAJAX();
    setupNutritionSearchAJAX();
    setupBmiSearchAJAX();

    // Setup delete modal for user management (AJAX version)
let userRowToDelete = null;
const deleteModalEl = document.getElementById('deleteUserModal');
if (deleteModalEl) {
    const deleteModal = new bootstrap.Modal(deleteModalEl);
    // Event delegation for dynamic table rows
    document.getElementById('users-section').addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;
        const userId = btn.getAttribute('data-user-id');
        const userName = btn.getAttribute('data-user-name');
        userRowToDelete = btn.closest('tr');
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
        deleteModal.show();
    });

    document.getElementById('confirmDeleteBtn').onclick = function() {
        const userId = document.getElementById('deleteUserId').value;
        fetch('admin_delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'userId=' + encodeURIComponent(userId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (userRowToDelete) userRowToDelete.remove();
                showToast('User deleted successfully!', 'success');
            } else {
                showToast(data.message || 'Delete failed.', 'danger');
            }
            deleteModal.hide();
        })
        .catch(() => {
            showToast('Delete failed. Network error.', 'danger');
            deleteModal.hide();
        });
    };
}

// Toast function
function showToast(msg, type = 'success') {
    let toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.position = 'fixed';
    toast.style.top = '24px';
    toast.style.right = '24px';
    toast.style.zIndex = 9999;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 1800);
}

});

function loadDefaultTable() {
    // Get current page from pagination (or default to 1)
    let currentPage = 1;
    const activePage = section.querySelector('.pagination .page-item.active .page-link');
    if (activePage && !isNaN(parseInt(activePage.textContent))) {
        currentPage = parseInt(activePage.textContent);
    }
    fetch(`?ajax=user_search&q=&user_page=${currentPage}`)
        .then(res => res.json())
        .then(data => renderRows(data.data));
}


</script>


    <!--jQuery-->
    <script src="Javascript/jquery-3.5.1.min.js"></script>
    <!--Bootstrap JS-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!--<script src="Javascript/bootstrap.min.js"></script>-->
    <!--Popper JS-->
    <!--<script src="Javascript/popper.min.js"></script>-->
    <!--Font Awesome-->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    />
    <!-- Font Awesome 6 (CDN, always works) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
</body>
</html>

