const video = document.getElementById("strokeVideo");
const processCanvas = document.getElementById("strokeProcessCanvas");
const previewCanvas = document.getElementById("strokePreviewCanvas");
const resultText = document.getElementById("strokeResult");
const resultBox = document.getElementById("strokeResultBox");
const riskLevelIndicator = document.getElementById("strokeRiskLevel");
const recommendationBox = document.getElementById("strokeRecommendationBox");
const recommendations = document.getElementById("strokeRecommendations");
const recaptureBtn = document.getElementById("recaptureBtn");

let stream = null;
let model = null;
let countdownInterval = null; // GLOBAL for countdown

// ============ Camera FacingMode Switch Logic ============
let currentFacingMode = "user"; // "user" = front, "environment" = back


// ============ Utility to Show/Hide Floating Button ============
// Show the button
function showSwitchCameraButton() {
  const btn = document.getElementById("switchCameraBtn");
  if (btn && window.innerWidth <= 991) {
    btn.classList.add("mobile-visible");
  }
}

function hideSwitchCameraButton() {
  const btn = document.getElementById("switchCameraBtn");
  if (btn) {
    btn.classList.remove("mobile-visible");
  }
}
// ============ End Utility ============

// Switch camera function, keeping same API as startCameraStream for compatibility
async function switchCamera(facingMode) {
  // Stop previous stream if any
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    video.srcObject = null;
    stream = null;
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: { exact: facingMode } }
    });
    video.srcObject = stream;
    await video.play();
  } catch (error) {
    // Fallback for browsers that don't support { exact: ... }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: facingMode }
      });
      video.srcObject = stream;
      await video.play();
    } catch (error2) {
      console.error("Camera access failed:", error2);
      resultText.textContent = "Camera access denied or not available.";
    }
    
  }
}

// Add Switch Camera Button event
const switchCameraBtn = document.getElementById("switchCameraBtn");
if (switchCameraBtn) {
  switchCameraBtn.addEventListener("click", async () => {
    // Toggle between front and back
    currentFacingMode = (currentFacingMode === "user") ? "environment" : "user";
    await switchCamera(currentFacingMode);

    // Reset UI state
    previewCanvas.style.display = "none";
    video.style.display = "block";
    resultText.textContent = "Restarting capture…";
    recommendations.innerHTML = "";
    recommendationBox.style.display = "none";
    resultBox.className = "stroke-result";

    // Restart countdown!
    countdownAndCapture();
    showSwitchCameraButton(); // SHOW after switch
  });
}

// ============= End Camera Switch Logic ==================


async function loadModel() {
  try {
    model = await tf.loadLayersModel("stroke_models/model.json");
    console.log("✅ Model loaded successfully");
  } catch (e) {
    console.error("❌ Error loading model:", e);
  }
}

async function startCameraStream() {
  // Always use the currentFacingMode (user or environment)
  await switchCamera(currentFacingMode);
  showSwitchCameraButton(); 
}

function countdownAndCapture() {
  let countdown = 5;
  resultText.textContent = `Capturing in ${countdown}s…`;

  const countdownOverlay = document.getElementById("countdownOverlay");
  if (countdownOverlay) {
    countdownOverlay.style.display = "block";
    countdownOverlay.textContent = countdown;
  }

  if (countdownInterval) clearInterval(countdownInterval);

  countdownInterval = setInterval(() => {
    countdown--;

    resultText.textContent = `Capturing in ${countdown}s…`;
    if (countdownOverlay) {
      countdownOverlay.textContent = countdown > 0 ? countdown : '';
    }

    if (countdown <= 0) {
      clearInterval(countdownInterval);
      if (countdownOverlay) countdownOverlay.style.display = "none";
      captureImage();
      stopCameraStream();
      analyzeImage();
    }
  }, 1000);
}


// -------------- KEY PART: Ensures canvas and video always match --------------
function captureImage() {
  // Set canvas to same size as video ATTRIBUTES, not just CSS!
  previewCanvas.width = video.videoWidth;
  previewCanvas.height = video.videoHeight;

  // Draw the frame at full res
  const ctx = previewCanvas.getContext('2d');
  ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);

  // Draw for model
  processCanvas.width = 224;
  processCanvas.height = 224;
  const ctxProcess = processCanvas.getContext('2d');
  ctxProcess.drawImage(
    previewCanvas, // or video
    0, 0, previewCanvas.width, previewCanvas.height,
    0, 0, 224, 224
  );

  previewCanvas.style.display = 'block';
  video.style.display = 'none';

  hideSwitchCameraButton(); // HIDE switch camera after capture
}

function stopCameraStream() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    video.srcObject = null;
    stream = null;
  }
}

async function analyzeImage() {
  if (!model) {
    resultText.textContent = "Model not loaded.";
    return;
  }

  resultText.textContent = "Analyzing image…";
  recommendations.innerHTML = "";
  recommendationBox.style.display = "none";

  // Use processCanvas (224x224) for the model input
  const input = tf.browser.fromPixels(processCanvas)
    .resizeNearestNeighbor([224, 224])
    .toFloat()
    .div(tf.scalar(255))
    .expandDims(0);

  const prediction = model.predict(input);
  const data = await prediction.data();
  tf.dispose([input, prediction]);

  const probability = data[0];
  let riskLevel = "Low risk";
  let className = "stroke-result-negative";
  let adviceList = [
    'Continue regular health check-ups with your doctor.',
    'Maintain a healthy lifestyle with regular exercise.',
    'Follow a balanced diet low in sodium and saturated fats.',
    'Monitor blood pressure and cholesterol levels regularly.'
  ];

  if (probability >= 0.75) {
    riskLevel = "High risk";
    className = "stroke-result-positive";
    adviceList = [
      'Seek immediate medical attention or call emergency services.',
      'Do not drive yourself to the hospital.',
      'Note the time when symptoms began to report to medical staff.',
      'If possible, have someone stay with you until help arrives.'
    ];
  } else if (probability >= 0.25) {
    riskLevel = "Moderate risk";
    className = "stroke-result-warning";
    adviceList = [
      'Consult with a healthcare professional within 24 hours.',
      'Monitor for worsening symptoms and seek emergency care if they occur.',
      'Avoid strenuous activities until cleared by a doctor.',
      'Review and manage risk factors like high blood pressure and cholesterol.'
    ];
  }
  if (riskLevelIndicator) {
  riskLevelIndicator.style.width = `${(probability * 100).toFixed(2)}%`;
}

  resultBox.className = `stroke-result ${className}`;
  resultText.textContent = `Prediction: ${riskLevel} (${(probability * 100).toFixed(2)}% confident)`;
  adviceList.forEach(text => {
    const li = document.createElement('li');
    li.textContent = text;
    recommendations.appendChild(li);
  });
  recommendationBox.style.display = 'block';

  await sendDataToServer(riskLevel);
}

async function sendDataToServer(riskLevel) {
  const detectedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
  const formData = new FormData();
  formData.append('riskLevel', riskLevel);
  formData.append('detectedAt', detectedAt);

  try {
    const response = await fetch('stroke_save.php', {
      method: 'POST',
      body: formData
    });
    const data = await response.json();
    console.log('Server response:', data);
    if (data.status === 'success') {
      // Optionally, show a toast or message
      alert('Data saved successfully');
    } else {
      alert('Failed to save data');
    }
  } catch (error) {
    console.error('Error sending data:', error);
    alert('Error sending data');
  }
}

// Recapture: show video, hide preview, restart camera and countdown
recaptureBtn.addEventListener("click", async () => {
  previewCanvas.style.display = "none";
  video.style.display = "block";
  resultText.textContent = "Restarting capture…";
  recommendations.innerHTML = "";
  recommendationBox.style.display = "none";
  resultBox.className = "stroke-result";
  await startCameraStream();
  countdownAndCapture();
});

document.addEventListener("DOMContentLoaded", () => {
  const overlay = document.getElementById("tapToStartOverlay");

  // Hide switch camera on initial load
  hideSwitchCameraButton();

  // Wait for user tap to start the actual flow
  if (overlay) {
    overlay.addEventListener("click", async () => {
      overlay.style.display = "none"; // Hide overlay
      await loadModel();              // Load model
      await startCameraStream();      // Start video stream
      countdownAndCapture();          // Begin countdown + capture
    });
  }

  resultText.textContent = "Tap to start camera";
});
