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
    <title>BMI Calculator</title>
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
    <!--jQuery-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  </head>
  <body>
    <div class="bmi-tool-wrapper container-fluid">
      <div class="bmi-header" style="padding-left:20px;">
        <div>
          <h1 class="bmi-title">BMI Calculator</h1>
          <p class="bmi-subtitle">Calculate and track your Body Mass Index</p>
        </div>
        <!-- Display session information for debugging -->
        <div class="d-none" style="font-size: 12px; color: #999; margin-top: 5px;">
          User ID: <?php echo isset($_SESSION['id']) ? $_SESSION['id'] : 'Not set'; ?>
        </div>
      </div>

      <div class="bmi-main-content">
        <div class="bmi-card px-3 py-4 px-md-4">
          <h3 class="bmi-card-title">
            <span class="bmi-card-title-icon">1</span> Enter Your Information
          </h3>

          <div class="bmi-units-toggle">
            <div id="bmiMetricBtn" class="bmi-unit-btn active">Metric</div>
            <div id="bmiImperialBtn" class="bmi-unit-btn">Imperial</div>
          </div>

          <div id="bmiMetricInputs">
            <div class="bmi-input-group">
              <label class="bmi-input-label" for="bmiHeight">Height (cm)</label>
              <input
                type="number"
                id="bmiHeight"
                name="bmiHeight"
                class="bmi-input-field"
                placeholder="e.g., 175"
                min="50"
                max="250"
              />
              <div class="bmi-error-message">Please enter a valid height</div>
            </div>

            <div class="bmi-input-group">
              <label class="bmi-input-label" for="bmiWeight">Weight (kg)</label>
              <input
                type="number"
                id="bmiWeight"
                name="bmiWeight"
                class="bmi-input-field"
                placeholder="e.g., 70"
                min="20"
                max="300"
              />
              <div class="bmi-error-message">Please enter a valid weight</div>
            </div>
          </div>

          <div id="bmiImperialInputs" style="display: none">
            <div class="bmi-input-group">
              <label class="bmi-input-label" for="bmiFeet">Height (feet)</label>
              <input
                type="number"
                id="bmiFeet"
                name="bmiFeet"
                class="bmi-input-field"
                placeholder="e.g., 5"
                min="1"
                max="8"
              />
            </div>

            <div class="bmi-input-group">
              <label class="bmi-input-label" for="bmiInches"
                >Height (inches)</label
              >
              <input
                type="number"
                id="bmiInches"
                name="bmiInches"
                class="bmi-input-field"
                placeholder="e.g., 10"
                min="0"
                max="11"
              />
              <div class="bmi-error-message">Please enter a valid height</div>
            </div>

            <div class="bmi-input-group">
              <label class="bmi-input-label" for="bmiPounds"
                >Weight (lbs)</label
              >
              <input
                type="number"
                id="bmiPounds"
                name="bmiPounds"
                class="bmi-input-field"
                placeholder="e.g., 155"
                min="45"
                max="660"
              />
              <div class="bmi-error-message">Please enter a valid weight</div>
            </div>
          </div>

          <div class="bmi-input-group">
            <label class="bmi-input-label">Gender (optional)</label>
            <div class="bmi-radio-group">
              <div class="bmi-radio-option">
                <input
                  type="radio"
                  name="bmiGender"
                  id="bmiMale"
                  value="male"
                />
                <label class="bmi-radio-label" for="bmiMale">Male</label>
              </div>
              <div class="bmi-radio-option">
                <input
                  type="radio"
                  name="bmiGender"
                  id="bmiFemale"
                  value="female"
                />
                <label class="bmi-radio-label" for="bmiFemale">Female</label>
              </div>
            </div>
          </div>

          <div class="bmi-input-group">
            <label class="bmi-input-label" for="bmiAge">Age (optional)</label>
            <input
              type="number"
              id="bmiAge"
              class="bmi-input-field"
              placeholder="e.g., 30"
              min="2"
              max="120"
            />
          </div>

          <div class="bmi-controls">
            <button id="bmiCalculateBtn" class="bmi-btn bmi-btn-primary">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M12 22v-8"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M12 10V2"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M17 5l-5 3-5-3"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M17 19l-5-3-5 3"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
              </svg>
              Calculate BMI
            </button>
            <button id="bmiResetBtn" class="bmi-btn bmi-btn-secondary">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M23 4v6h-6"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M1 20v-6h6"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
                <path
                  d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  fill="none"
                ></path>
              </svg>
              Reset
            </button>
          </div>
        </div>

        <div class="bmi-card px-3 py-4 px-md-4">
          <h3 class="bmi-card-title">
            <span class="bmi-card-title-icon">2</span> Results & Analysis
          </h3>

          <div class="bmi-result-container">
            <div class="bmi-result" id="bmiResultBox">
              <h3 class="bmi-result-title">Your BMI</h3>
              <div class="bmi-result-value" id="bmiValue" name="bmiValue">--.--</div>
              <div class="bmi-result-category" id="bmiCategory">
                Enter your data to calculate
              </div>

              <div class="bmi-scale">
                <div
                  class="bmi-scale-marker"
                  id="bmiMarker"
                  style="left: 0%"
                ></div>
              </div>
              <div class="bmi-scale-labels">
                <span>18.5</span>
                <span>25</span>
                <span>30</span>
                <span>40</span>
              </div>

              <div class="bmi-info-container">
                <div class="bmi-info-card">
                  <h4 class="bmi-info-title">What Your BMI Means</h4>
                  <p class="bmi-info-text" id="bmiInterpretation">
                    Your BMI will provide an indication of your weight category
                    and potential health risks.
                  </p>
                </div>

                <div class="bmi-info-card">
                  <h4 class="bmi-info-title">Health Recommendations</h4>
                  <ul class="bmi-info-text" id="bmiRecommendations">
                    <li>
                      Enter your details to receive personalized health
                      recommendations.
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          
        </div>
      </div>

      <div class="bmi-disclaimer">
        <strong>Disclaimer:</strong> BMI is a general health indicator and may
        not be accurate for all body types. It does not directly measure body
        fat or account for muscle mass, bone density, or overall body
        composition. Athletes or muscular individuals may have a higher BMI
        without increased health risks. Always consult with healthcare
        professionals for personalized medical advice.
      </div>
    </div>

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="Javascript/BMI.js"></script>
    <!--<script src="BMI-test.js"></script>-->
    
    <!-- Add a notification area for saving status -->
    <div id="saveNotification" class="save-notification" style="display: none; position: fixed; bottom: 20px; right: 20px; background-color: #4CAF50; color: white; padding: 15px; border-radius: 5px; z-index: 1000;">
      BMI data saved successfully!
    </div>
    
    <script>
    // Add event listener to show save notification
    document.addEventListener('DOMContentLoaded', function() {
      console.log('BMI Calculator DOM loaded');
      
      // For jQuery AJAX events
      $(document).ajaxSuccess(function(event, xhr, settings) {
        console.log('AJAX Success:', settings.url, xhr.responseText);
        if (settings.url === 'bmi_process.php') {
          const saveNotification = document.getElementById('saveNotification');
          saveNotification.textContent = xhr.responseText;
          saveNotification.style.display = 'block';
          
          // Hide notification after 3 seconds
          setTimeout(function() {
            saveNotification.style.display = 'none';
          }, 3000);
        }
      });
      
      // For custom events from Fetch API
      document.addEventListener('ajaxSuccess', function(e) {
        console.log('Custom ajaxSuccess event received:', e.detail);
        if (e.detail && e.detail.url === 'bmi_process.php') {
          const saveNotification = document.getElementById('saveNotification');
          saveNotification.textContent = e.detail.response;
          saveNotification.style.display = 'block';
          
          // Hide notification after 3 seconds
          setTimeout(function() {
            saveNotification.style.display = 'none';
          }, 3000);
        }
      });
      
      // Also add error handler to show error messages
      $(document).ajaxError(function(event, xhr, settings) {
        console.log('AJAX Error:', settings.url, xhr.responseText);
        if (settings.url === 'bmi_process.php') {
          const saveNotification = document.getElementById('saveNotification');
          saveNotification.textContent = "Error saving BMI data: " + xhr.responseText;
          saveNotification.style.backgroundColor = "#f44336"; // Red for error
          saveNotification.style.display = 'block';
          
          // Hide notification after 5 seconds
          setTimeout(function() {
            saveNotification.style.display = 'none';
            saveNotification.style.backgroundColor = "#4CAF50"; // Reset color
          }, 5000);
        }
      });
    });
  </script>
  </body>
</html>