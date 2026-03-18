const video = document.getElementById("video");
const processCanvas = document.getElementById("processCanvas");
const previewCanvas = document.getElementById("previewCanvas");
const resultText = document.getElementById("foodResult");
const resultBox = document.getElementById("foodResultBox");
const divider = document.getElementById("foodResultDivider");
const recommendationsBox = document.getElementById("FoodRecommendationBox");
const recommendationsList = document.getElementById("FoodRecommendations");
const recaptureBtn = document.getElementById("recaptureBtn");
const switchCameraBtn = document.getElementById("switchCameraBtn");

let stream = null;
let model = null;
let capturedImage = null;
let countdownInterval = null;
let currentFacingMode = "environment"; // default

const modelPath = "food_detection_model/model.json";
const adviceBank = {
  'Healthy - Score A': [
    [
      "Very healthy option! Contains plenty of nutrients.",
      "Excellent for daily meals—high in vitamins and minerals.",
      "Natural whole food—supports immunity and long-term health.",
      "Fiber-rich and low in unhealthy fats.",
      "Great for weight management and digestion.",
      "Contributes to heart health and stable energy.",
      "Best enjoyed as part of a balanced diet."
    ],
    [
      "Minimal to no stroke risk.",
      "Reduces risk of stroke and heart disease.",
      "Promotes good cardiovascular health.",
      "Rich in antioxidants that protect blood vessels.",
      "Maintains healthy blood pressure.",
      "Supports healthy cholesterol levels.",
      "Helps prevent chronic diseases."
    ],
    [
      "Keep up with whole foods, fruits, and veggies.",
      "Combine with lean proteins and whole grains.",
      "Drink plenty of water with your meals.",
      "Enjoy steamed, baked, or raw preparations.",
      "Add a variety of colors to your plate.",
      "Continue choosing foods rich in fiber and vitamins.",
      "Share healthy meals with friends and family."
    ]
  ],
  'Healthy - Score B': [
    [
      "Healthy choice with some minor limitations.",
      "Generally nutritious but check for added sugars or salt.",
      "Good for regular consumption in moderation.",
      "Offers essential nutrients for body and mind.",
      "Better than most processed options.",
      "May have added flavors—check ingredient labels.",
      "Suitable for active lifestyles."
    ],
    [
      "Very low stroke risk.",
      "Helps lower chronic disease risk.",
      "No significant risk if eaten regularly.",
      "Supports healthy arteries and brain function.",
      "Can help keep cholesterol in check.",
      "Assists in managing weight and metabolism.",
      "Encourages long-term wellness."
    ],
    [
      "Pair with more vegetables and lean proteins.",
      "Choose low-salt and low-sugar options when possible.",
      "Combine with more natural foods for a better meal.",
      "Watch portion sizes if sodium or sugar is present.",
      "Best served with fresh salads or fruits.",
      "Swap out dressings or sauces for healthier alternatives.",
      "Enjoy with whole grain sides."
    ]
  ],
  'Unhealthy - Score C': [
    [
      "Not a healthy option. Contains extra salt, sugar, or fat.",
      "Unhealthy due to processed ingredients.",
      "Caution: moderate nutrition, high in unhealthy additives.",
      "Often fried, sweetened, or preserved.",
      "Calories may be high for a small serving.",
      "Artificial colors and preservatives present.",
      "Not recommended for daily intake."
    ],
    [
      "Can raise stroke risk if eaten often.",
      "Long-term consumption may increase stroke and heart risk.",
      "Frequent intake not recommended for heart health.",
      "May contribute to high blood pressure.",
      "Can elevate cholesterol levels over time.",
      "Linked to metabolic syndrome and weight gain.",
      "Should be consumed sparingly."
    ],
    [
      "Switch to less processed alternatives.",
      "Opt for fresh fruits or homemade meals.",
      "Try whole grain or reduced-fat versions.",
      "Prepare similar foods at home for healthier options.",
      "Replace with steamed, baked, or grilled foods.",
      "Reduce portion size or frequency of consumption.",
      "Read food labels before choosing processed snacks."
    ]
  ],
  'Unhealthy - Score D': [
    [
      "Unhealthy food. High in salt, sugar, or fat.",
      "Low nutrition, high in calories.",
      "Heavily processed, not suitable for regular meals.",
      "Often loaded with saturated/trans fats.",
      "Excessive sugar may cause blood sugar spikes.",
      "Likely to be low in fiber and essential nutrients.",
      "Frequent intake leads to weight gain."
    ],
    [
      "Regular intake increases stroke risk.",
      "Significant risk for stroke or heart disease.",
      "Linked to high blood pressure and cardiovascular issues.",
      "Raises LDL (bad) cholesterol levels.",
      "Increases risk of diabetes and metabolic problems.",
      "Promotes chronic inflammation in the body.",
      "Can damage blood vessels over time."
    ],
    [
      "Replace with natural, whole foods.",
      "Eat more fruits, vegetables, and lean meats.",
      "Try to limit intake and choose healthier snacks.",
      "Substitute with baked or steamed alternatives.",
      "Drink water instead of sugary drinks.",
      "Add nuts, seeds, or yogurt as snacks.",
      "Plan balanced meals to avoid cravings."
    ]
  ],
  'Unhealthy - Score E': [
    [
      "Very unhealthy. Avoid whenever possible.",
      "Extremely high in harmful ingredients.",
      "Dangerously processed and sugary/fatty.",
      "Very low in nutrition, high in empty calories.",
      "Known to contain unhealthy additives and chemicals.",
      "May be addictive due to artificial flavors.",
      "Can cause rapid spikes in blood sugar."
    ],
    [
      "Very high stroke and heart disease risk.",
      "Greatly increases risk for serious health problems.",
      "May lead to stroke, diabetes, and heart issues.",
      "Linked to obesity and metabolic syndrome.",
      "Can cause fatty liver and other organ problems.",
      "Directly increases inflammation and blood pressure.",
      "Should be avoided by anyone with heart or health risks."
    ],
    [
      "Best to eliminate from diet.",
      "Switch completely to healthy, unprocessed foods.",
      "Consult a health professional for better alternatives.",
      "Adopt a diet rich in vegetables and lean proteins.",
      "Replace sugary/fatty foods with fruits and whole grains.",
      "Find support for healthier eating habits.",
      "Educate family and friends about food choices."
    ]
  ]
};

// ===== DOM READY =====
document.addEventListener("DOMContentLoaded", () => {
  const overlay = document.getElementById("tapToStartOverlay");

  // Set up canvas styling
  const cameraContainer = document.querySelector('.fd-camera-container');
  cameraContainer.style.position = 'relative';
  cameraContainer.appendChild(previewCanvas);
  Object.assign(previewCanvas.style, {
    position: 'absolute',
    top: '0',
    left: '0',
    width: '100%',
    height: '100%',
    display: 'none',
    zIndex: '10'
  });

  resultText.textContent = "Tap to start camera";

  if (overlay) {
    overlay.addEventListener("click", async () => {
      overlay.style.display = "none";
      await initializeProcess();
    });
  }
});

// ===== INITIALIZE PROCESS =====
async function initializeProcess() {
  try {
    await loadModel();
    await startCamera();
    startCaptureCountdown();
  } catch (error) {
    console.error("Initialization error:", error);
    resultText.textContent = "Error during initialization.";
  }
}

// ===== LOAD MODEL =====
async function loadModel() {
  try {
    resultText.textContent = "Loading AI model...";
    model = await tf.loadLayersModel(modelPath);
    console.log("✅ Model loaded");
    resultText.textContent = "AI model loaded successfully!";
  } catch (error) {
    console.error("Model load error:", error);
    resultText.textContent = "Model failed to load.";
  }
}

// ===== START CAMERA =====
async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: currentFacingMode }
    });
    video.srcObject = stream;
    await video.play();
  } catch (err) {
    alert("Camera access denied or not available.");
    console.error("Camera start failed:", err);
  }
}

// ===== STOP CAMERA =====
function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    resultText.textContent = "Camera stopped. Analyzing image...";
  }
}

// ===== COUNTDOWN & CAPTURE =====
function startCaptureCountdown() {
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
      stopCamera();
      analyzeImage();
    }
  }, 1000);
}

// ===== CAPTURE IMAGE =====
function captureImage() {
  processCanvas.width = video.videoWidth;
  processCanvas.height = video.videoHeight;

  previewCanvas.width = video.videoWidth;
  previewCanvas.height = video.videoHeight;

  // Draw full resolution from video
  const ctx = processCanvas.getContext("2d");
  ctx.drawImage(video, 0, 0, processCanvas.width, processCanvas.height);

  const previewCtx = previewCanvas.getContext("2d");
  previewCtx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);

  previewCanvas.style.display = "block";
  video.style.display = "none";

  capturedImage = processCanvas.toDataURL("image/jpeg");
  resultText.textContent = "Image captured successfully.";
}

// ===== ANALYZE IMAGE =====
async function analyzeImage() {
  if (!model) {
    resultText.textContent = "Model not loaded.";
    return;
  }

  resultText.textContent = "Analyzing image...";
  recommendationsList.innerHTML = "";
  recommendationsBox.style.display = "none";
  divider.style.display = "none";
  resultBox.classList.remove("fd-result-healthy", "fd-result-unhealthy");

  try {
    const imageTensor = tf.browser.fromPixels(processCanvas)
      .resizeNearestNeighbor([224, 224])
      .toFloat()
      .div(tf.scalar(255))
      .expandDims(0);

    const prediction = model.predict(imageTensor);
    const predictionValues = await prediction.data();

    const classLabels = [
      'Healthy - Score A',
      'Healthy - Score B',
      'Unhealthy - Score C',
      'Unhealthy - Score D',
      'Unhealthy - Score E'
    ];

    const topIndex = predictionValues.indexOf(Math.max(...predictionValues));
    const topLabel = classLabels[topIndex];
    const topConfidence = (predictionValues[topIndex] * 100).toFixed(2);

    // 1. Get array of random advice
    const adviceArr = getRandomAdvice(topLabel);
    // 2. Display UI
    displayAssessmentResult(topLabel, topConfidence, adviceArr);

    // 3. Join for journal
    const adviceForJournal = adviceArr.map((advice, idx) => `${idx + 1}. ${advice}`).join('\n');

    const entryData = {
      productScore: topLabel.split("Score ")[1],
      image: capturedImage,
      advice: adviceForJournal
    };

    const saveResult = await saveEntryToJournal(entryData);
    if (saveResult) showEntryConfirmation();

    tf.dispose([imageTensor, prediction]);
  } catch (error) {
    console.error("Error during analysis:", error);
    resultText.textContent = "Error during analysis.";
    divider.style.display = "none";
    recommendationsBox.style.display = "none";
  }
}

// ===== Display Assessment Result (green/red, divider, rec list) =====
function displayAssessmentResult(topLabel, confidence, adviceArr) {
  // Set main result
  resultText.textContent = `Prediction: ${topLabel} (${confidence}% confident)`;

  // Color
  resultBox.classList.remove('fd-result-healthy', 'fd-result-unhealthy');
  if (topLabel.startsWith('Healthy')) {
    resultBox.classList.add('fd-result-healthy');
  } else {
    resultBox.classList.add('fd-result-unhealthy');
  }

  // Show divider
  divider.style.display = 'block';

  // Recommendations
  recommendationsList.innerHTML = '';
  adviceArr.forEach(advice => {
    const li = document.createElement('li');
    li.textContent = advice;
    recommendationsList.appendChild(li);
  });
  recommendationsBox.style.display = "block";
}

// ===== Random Advice for Journal & UI =====
function getRandomAdvice(label) {
  const adviceArr = adviceBank[label];
  if (!adviceArr) return ["No advice available for this category."];
  return adviceArr.map(arr => arr[Math.floor(Math.random() * arr.length)]);
}

// ===== SAVE ENTRY =====
async function saveEntryToJournal(entryData) {
  try {
    const res = await fetch('journal_api.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entryData)
    });

    const data = await res.json();
    if (data.success) {
      console.log("✅ Entry saved:", data);
      return true;
    } else {
      console.error("❌ Save failed:", data.error);
      resultText.textContent = "Failed to save entry to journal.";
      return false;
    }
  } catch (error) {
    console.error("Save error:", error);
    resultText.textContent = "Connection error when saving to journal.";
    return false;
  }
}

// ===== ENTRY CONFIRMATION =====
function showEntryConfirmation() {
  const message = document.createElement('div');
  message.className = "entry-notification";
  message.innerHTML = `
    <div class="entry-content">
      <p>Food entry successfully saved to journal!</p>
      <a href="product_journal.php" class="view-journal">View Journal</a>
    </div>
  `;
  document.body.appendChild(message);
  setTimeout(() => message.classList.add('show'), 100);
  setTimeout(() => {
    message.classList.remove('show');
    setTimeout(() => document.body.removeChild(message), 300);
  }, 60000);
}

// ===== RECATURE LOGIC =====
recaptureBtn.addEventListener('click', async () => {
  previewCanvas.style.display = 'none';
  video.style.display = 'block';
  recommendationsBox.style.display = "none";
  divider.style.display = "none";
  resultText.textContent = 'Waiting for analysis…';
  resultBox.classList.remove('fd-result-healthy', 'fd-result-unhealthy');
  try {
    await startCamera();
    if (countdownInterval) clearInterval(countdownInterval);
    startCaptureCountdown();
  } catch (error) {
    console.error("Recapture camera error:", error);
    resultText.textContent = "Unable to restart camera.";
  }
});

// ===== SWITCH CAMERA =====
if (switchCameraBtn) {
  switchCameraBtn.addEventListener("click", async () => {
    currentFacingMode = (currentFacingMode === "user") ? "environment" : "user";
    await startCamera();
    previewCanvas.style.display = 'none';
    video.style.display = 'block';
    recommendationsBox.style.display = "none";
    divider.style.display = "none";
    resultText.textContent = 'Waiting for analysis…';
    resultBox.classList.remove('fd-result-healthy', 'fd-result-unhealthy');
    if (countdownInterval) clearInterval(countdownInterval);
    startCaptureCountdown();
  });
}

// ===== STOP CAMERA ON UNLOAD =====
window.addEventListener("beforeunload", () => {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
  }
});
