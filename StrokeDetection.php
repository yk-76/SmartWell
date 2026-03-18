<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stroke Risk Assessment Tool</title>
  <link rel="shortcut icon" href="image/SmartWell_logo_Only.png">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600,700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
  <style>
    /* Default: hidden */
    #switchCameraBtn {
      display: none; 
    }

    @media (max-width: 991px) {
      #switchCameraBtn.mobile-visible {
        display: flex !important;
      }
    }

    .show-camera-btn {
  display: flex !important;
}
.hide-camera-btn {
  display: none !important;
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

.stroke-camera-container {
position: relative;
            max-width: 100%;
            aspect-ratio: 4 / 3;
            background: black;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
}

#videoContainer {
  width: 100%;
  height: 100%;
  aspect-ratio: inherit;
  position: relative;
  overflow: hidden;
}

#strokeVideo,
#strokePreviewCanvas {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  border-radius: 12px;
  background: #000;
}

  </style>
</head>
<body>
  <div class="stroke-tool-wrapper container-fluid">
    <div class="stroke-header" style="padding-left:20px;">
        <div>
          <h1 class="stroke-title">Stroke Risk Assessment Tool</h1>
          <p class="stroke-subtitle">Early detection through AI-powered analysis</p>
        </div>
    </div>

    <div class="stroke-main-content">
      <div class="stroke-card px-3 py-4 px-md-4" >
        <h3 class="stroke-card-title"><span class="stroke-card-title-icon">1</span> Capture Image</h3>
        <div class="stroke-camera-container d-flex justify-content-center" style="margin-top: 15px;">
          <div id="videoContainer" class="w-100 mx-auto" style="
            position: relative;
            max-width: 100%;
            aspect-ratio: 4 / 3;
            background: black;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
          ">
          <div id="tapToStartOverlay" class="tap-overlay">
              <div class="tap-message">Tap to start camera</div>
            </div>
            <div id="countdownOverlay" class="countdown-overlay"></div>
            <video
              id="strokeVideo"
              width="640"
              height="480"
              style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;"
              autoplay muted playsinline
            ></video>
            <canvas
              id="strokePreviewCanvas"
              width="640"
              height="480"
              style="display: none; width: 100%; height: 100%; object-fit: cover; border-radius: 12px;"
            ></canvas>
            <button id="switchCameraBtn" class="camera-icon-btn">
              <img src="image/switch-camera.png" alt="Switch Camera" />
            </button>
          </div>
          
        </div>
        
        <div class="d-flex flex-column gap-3 my-3">
          <button
            id="recaptureBtn"
            class="btn fd-btn px-4 py-2 mt-3"
            style="background-color: #1e6ebf; color: white; border: none;">
            <i class="fas fa-redo me-2"></i> Capture Again
          </button>
        </div>
        <div class="stroke-instructions mt-2">
          <strong>Tips:</strong> Position your face clearly in the center of the frame. Ensure good lighting and a neutral expression.
        </div>
        
      </div>

      <div class="stroke-card px-3 py-4 px-md-4 py-md-4" style="padding: 20px;">
        <h3 class="stroke-card-title"><span class="stroke-card-title-icon">2</span> Analysis & Results</h3>
        <div class="stroke-result-container">
          <div class="stroke-result" id="strokeResultBox">
            <h3 class="stroke-result-title mb-2">Assessment Result</h3>
            <p id="strokeResult" class="mb-4">Capture an image and press analyze to begin assessment</p>
            <div class="stroke-risk-indicator mb-3">
              <div class="stroke-risk-level" id="strokeRiskLevel"></div>
            </div>
            <div id="strokeRecommendationBox" style="display: none">
              <h4 class="stroke-result-subtitle mb-2">Recommendations:</h4>
              <ul id="strokeRecommendations" style="padding-left: 20px;"></ul>
            </div>
            
          </div>
            
        </div>
         
      </div>
    </div>

    <canvas id="strokeProcessCanvas" class="stroke-process-canvas d-none" width="224" height="224"></canvas>
  </div>

  <script src="Javascript/jquery-3.5.1.min.js"></script>
  <script src="Javascript/bootstrap.min.js"></script>
  <script src="Javascript/popper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js"></script>
  <script src="Javascript/stroke.js"></script>
</body>
</html>
