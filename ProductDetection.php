<?php 
require_once 'auth_helper.php';
// Start secure session
start_secure_session();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_name'])) {
    // Store current page for redirect after login
    $_SESSION['requested_page'] = $_SERVER['REQUEST_URI'];
    header("Location: index.php?showLogin=1&service=" . basename($_SERVER['PHP_SELF']));
    exit();
}

// Check session consistency
check_session_consistency();

// Keep the timeout check
$inactive = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();     
    session_destroy();   
    header("Location: index.php?showLogin=1&service=" . basename($_SERVER['PHP_SELF']));
    exit();
}
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Food Detection</title>
    <link rel="shortcut icon" href="image/SmartWell_logo_Only.png" />
    <!--Bootstraap CSS-->
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
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.11.0/dist/tf.min.js"></script>
    <style>
      #switchCameraBtn { display: none !important; }
      @media (max-width: 991px) {
        #switchCameraBtn { display: inline-block !important; }
      }

          .camera-icon-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 50px;
  height: 50px;
  background-color: white;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  z-index: 10;
  cursor: pointer;
}

.camera-icon-btn img {
  width: 24px;
  height: 24px;
}

.tap-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 500;
  text-align: center;
  z-index: 20;
  cursor: pointer;
  border-radius: 12px;
}

.tap-message {
  padding: 1rem;
  background: rgba(0,0,0,0.4);
  border-radius: 10px;
}

.countdown-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 5rem;
  font-weight: bold;
  color: white;
  text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
  z-index: 30;
  display: none;
}

#previewCanvas {
  position: absolute;
  top: 0;
  left: 0;
  display: none;
  z-index: 10;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 12px;
}

/* For Food Assessment (Healthy) */
.fd-result.fd-result-healthy {
  background: #e7f7ea;
  border: 2px solid #a0e2b0;
  color: #204625;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(110, 200, 130, 0.08);
}

/* For Food Assessment (Unhealthy) */
.fd-result.fd-result-unhealthy {
  background: #fff0f0;
  border: 2px solid #e4a7a7;
  color: #800d0d;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(230, 80, 80, 0.06);
}

/* For "hr" divider */
#foodResultDivider {
  margin: 18px 0;
  border-top: 6px solid #dedede;
  border-radius: 8px;
} 
    </style>
  </head>
  <body class="food-detection-vars">
    <div class="fd-container container-fluid">
      <header class="fd-header" style="padding-left:20px;">
        <div>
          <h1 class="fd-title">Food Detection</h1>
          <p class="fd-subtitle">AI-powered food analysis and nutritional assessment</p>
        </div>
      </header>

      

      <div class="fd-main-content">
        <div class="fd-card px-3 py-4 px-md-4">
          <h3 class="fd-card-heading"><span class="fd-card-heading-number">1</span> Capture Image</h3>
          <div class="fd-camera-container">
            <div id="tapToStartOverlay" class="tap-overlay">
              <div class="tap-message">Tap to start camera</div>
            </div>
            <div id="countdownOverlay" class="countdown-overlay"></div>
            <video id="video" class="fd-video" autoplay muted playsinline></video>
            <canvas id="previewCanvas" class="fd-preview-canvas" width="224" height="224" style="display:none;"></canvas>
            <button id="switchCameraBtn" class="camera-icon-btn">
              <img src="image/switch-camera.png" alt="Switch Camera" />
            </button>
          </div>

          <div class="d-flex flex-column gap-3 my-3">
            <button id="recaptureBtn" class="fd-btn fd-btn-primary">
              <i class="fas fa-redo-alt me-2"></i> Capture Again
            </button>
          </div>
          
          <div class="fd-instructions">
            <strong>Tips:</strong> Position the food item clearly in the center of the frame. Ensure good lighting for the most accurate identification and nutritional analysis.
          </div>
        </div>

        <div class="fd-card px-3 py-4 px-md-4">
        <h3 class="fd-card-heading"><span class="fd-card-heading-number">2</span> Analysis & Results</h3>
        <div class="fd-result-container">
          <div class="fd-result" id="foodResultBox">
            <h3 class="fd-result-heading mb-2">Assessment Result</h3>
            <p id="foodResult" class="mb-4">Capture an image and press analyze to begin assessment</p>
            <hr id="foodResultDivider" style="display: none;">
            <div id="FoodRecommendationBox" style="display: none;">
              <h4 class="food-result-subtitle mb-2">Recommendations:</h4>
              <ul id="FoodRecommendations" style="padding-left: 20px;"></ul>
            </div>
              
            </div>
            
              <div class="fd-controls mt-3 d-flex gap-3">
                <button id="analyze" class="fd-btn fd-btn-primary" onclick="window.location.href='product_journal.php'">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M22 11.08V12a10 10 0 1 1-5.93-9.14"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    fill="none"
                  ></path>
                  <polyline
                    points="22 4 12 14.01 9 11.01"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    fill="none"
                  ></polyline>
                </svg>
                View Journal
              </button>
              </div>
          </div>
        </div>

        
        

        <canvas id="processCanvas" width="224" height="224" style="display: none"></canvas>
      </div>
    </div>


    <!--jQuery-->
    <script src="Javascript/jquery-3.5.1.min.js"></script>
    <!--Bootstrap JS-->
    <script src="Javascript/bootstrap.min.js"></script>
    <!--Popper JS-->
    <script src="Javascript/popper.min.js"></script>
    <!--Font Awesome-->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    />
    <!--JS Script-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js"></script>
    <script src="Javascript/product.js"></script>
  </body>
</html>