// BMI.js with Fetch API implementation

document.addEventListener("DOMContentLoaded", function () {
  // Unit buttons & input sections
  const metricBtn = document.getElementById('bmiMetricBtn');
  const imperialBtn = document.getElementById('bmiImperialBtn');
  const metricInputs = document.getElementById('bmiMetricInputs');
  const imperialInputs = document.getElementById('bmiImperialInputs');

  // Buttons
  const calculateBtn = document.getElementById('bmiCalculateBtn');
  const resetBtn = document.getElementById('bmiResetBtn');

  // Inputs
  const heightInput = document.getElementById('bmiHeight');
  const weightInput = document.getElementById('bmiWeight');
  const feetInput = document.getElementById('bmiFeet');
  const inchesInput = document.getElementById('bmiInches');
  const poundsInput = document.getElementById('bmiPounds');
  const ageInput = document.getElementById('bmiAge');
  const maleRadio = document.getElementById('bmiMale');
  const femaleRadio = document.getElementById('bmiFemale');

  // Result elements
  const bmiValue = document.getElementById('bmiValue');
  const bmiCategory = document.getElementById('bmiCategory');
  const bmiMarker = document.getElementById('bmiMarker');
  const bmiInterpretation = document.getElementById('bmiInterpretation');
  const bmiRecommendations = document.getElementById('bmiRecommendations');

  // Chart
  const bmiChart = document.getElementById('bmiChart');
  let bmiChartInstance = null;

  let isMetric = true;

  // Unit switch
  metricBtn.addEventListener('click', () => {
    isMetric = true;
    metricBtn.classList.add('active');
    imperialBtn.classList.remove('active');
    metricInputs.style.display = 'block';
    imperialInputs.style.display = 'none';
  });

  imperialBtn.addEventListener('click', () => {
    isMetric = false;
    imperialBtn.classList.add('active');
    metricBtn.classList.remove('active');
    imperialInputs.style.display = 'block';
    metricInputs.style.display = 'none';
  });

  // Validation
  function validateInputs() {
    let valid = true;

    function showError(input, show) {
      input.classList.toggle('error', show);
      input.nextElementSibling.style.display = show ? 'block' : 'none';
    }

    if (isMetric) {
      showError(heightInput, !heightInput.value || heightInput.value < 50 || heightInput.value > 250);
      showError(weightInput, !weightInput.value || weightInput.value < 20 || weightInput.value > 300);
      valid = !heightInput.classList.contains('error') && !weightInput.classList.contains('error');
    } else {
      showError(inchesInput, !feetInput.value || feetInput.value < 1 || feetInput.value > 8 ||
        !inchesInput.value || inchesInput.value < 0 || inchesInput.value > 11);
      showError(poundsInput, !poundsInput.value || poundsInput.value < 45 || poundsInput.value > 660);
      valid = !inchesInput.classList.contains('error') && !poundsInput.classList.contains('error');
    }

    return valid;
  }

  // BMI Calculation function
  function calculateBMI() {
    if (!validateInputs()) return;

    let bmi = 0;
    let heightCm = 0;
    let weightKg = 0;

    if (isMetric) {
      heightCm = parseFloat(heightInput.value);
      weightKg = parseFloat(weightInput.value);
      bmi = weightKg / ((heightCm / 100) * (heightCm / 100));
    } else {
      const feet = parseFloat(feetInput.value);
      const inches = parseFloat(inchesInput.value);
      const totalInches = feet * 12 + inches;
      const pounds = parseFloat(poundsInput.value);
      
      // Convert to metric for saving to database
      heightCm = totalInches * 2.54;
      weightKg = pounds * 0.453592;
      
      bmi = (pounds * 703) / (totalInches * totalInches);
    }

    // Format values to 2 decimal places
    const formattedBmi = bmi.toFixed(2);
    const formattedHeight = heightCm.toFixed(2);
    const formattedWeight = weightKg.toFixed(2);

    // Log values before saving (for debugging)
    console.log('BMI Calculation Results:', {
      height: formattedHeight,
      weight: formattedWeight,
      bmi: formattedBmi
    });

    // Call the function with proper parameters
    saveBMIData(formattedHeight, formattedWeight, formattedBmi);

    // Display results in the UI
    displayResults(bmi);
  }

  // BMI category info
  function getBMICategory(bmi) {
    if (bmi < 16) return { category: "Severe Thinness", position: 0 };
    if (bmi < 17) return { category: "Moderate Thinness", position: 10 };
    if (bmi < 18.5) return { category: "Mild Thinness", position: 20 };
    if (bmi < 25) return { category: "Normal weight", position: 32 };
    if (bmi < 30) return { category: "Overweight", position: 55 };
    if (bmi < 35) return { category: "Obese Class I", position: 70 };
    if (bmi < 40) return { category: "Obese Class II", position: 85 };
    return { category: "Obese Class III", position: 100 };
  }

  // Display BMI results
  function displayResults(bmi) {
    const { category, position } = getBMICategory(bmi);
    
    bmiValue.textContent = bmi.toFixed(2);
    bmiCategory.textContent = category;
    bmiMarker.style.left = `${position}%`;
    
    bmiInterpretation.textContent = getInterpretation(category);
    
    // Clear and update recommendations
    bmiRecommendations.innerHTML = '';
    const recommendations = getRecommendations(category);
    recommendations.forEach(rec => {
      const li = document.createElement('li');
      li.textContent = rec;
      bmiRecommendations.appendChild(li);
    });
    
    // Update chart
    updateChart(bmi);
    
    return true;
  }

  // Interpretation
  function getInterpretation(category) {
    const messages = {
      "Severe Thinness": "Your BMI indicates severe thinness, which may pose serious health risks.",
      "Moderate Thinness": "Your BMI indicates moderate thinness, which may be associated with health concerns.",
      "Mild Thinness": "Your BMI indicates mild thinness. While close to the normal range, some health considerations may apply.",
      "Normal weight": "Your BMI is within the normal range, which is associated with lowest health risks for most people.",
      "Overweight": "Your BMI indicates overweight, which may increase the risk of developing certain health conditions.",
      "Obese Class I": "Your BMI indicates class I obesity, which is associated with higher risk of health problems.",
      "Obese Class II": "Your BMI indicates class II obesity, with significant health risks that should be addressed.",
      "Obese Class III": "Your BMI indicates class III obesity, which is associated with severe health risks that require medical attention."
    };
    return messages[category];
  }

  // Recommendations
  function getRecommendations(category) {
    const recs = {
      "Severe Thinness": [
        "Consult with a healthcare provider immediately.",
        "Consider a nutritionist-guided high-calorie diet.",
        "Focus on nutrient-dense foods.",
        "Regular monitoring of health parameters is essential."
      ],
      "Moderate Thinness": [
        "Consult with a healthcare provider.",
        "Gradually increase caloric intake with nutritious foods.",
        "Consider strength training to build muscle mass.",
        "Regular health check-ups are recommended."
      ],
      "Mild Thinness": [
        "Aim for a balanced diet that includes adequate protein.",
        "Consider a moderate increase in caloric intake.",
        "Regular physical activity for overall health.",
        "Monitor your weight to ensure it stays stable or increases slightly."
      ],
      "Normal weight": [
        "Maintain your current healthy weight through balanced nutrition.",
        "Engage in regular physical activity (150+ minutes per week).",
        "Continue routine health check-ups.",
        "Focus on overall wellness and stress management."
      ],
      "Overweight": [
        "Aim for gradual weight loss of 0.5-1 kg per week.",
        "Increase physical activity to at least 150-300 minutes per week.",
        "Focus on a balanced diet with portion control.",
        "Consider consulting with a healthcare provider for personalized advice."
      ],
      "Obese Class I": [
        "Consult with a healthcare provider for a comprehensive health assessment.",
        "Consider a structured weight management program.",
        "Aim for regular physical activity as advised by your doctor.",
        "Monitor related health metrics like blood pressure and blood sugar."
      ],
      "Obese Class II": [
        "Seek medical supervision for a weight management plan.",
        "Regular monitoring of related health conditions is important.",
        "Consider behavioral therapy approaches for sustainable lifestyle changes.",
        "Structured physical activity under professional guidance."
      ],
      "Obese Class III": [
        "Immediate medical consultation is recommended.",
        "Comprehensive medical assessment and supervised weight management.",
        "Regular monitoring of related health conditions is crucial.",
        "Consider specialized medical interventions if appropriate."
      ]
    };
    return recs[category];
  }



  // COMPLETELY REDESIGNED function to use modern Fetch API instead of jQuery AJAX
  function saveBMIData(heightCm, weightKg, bmi) {
    // Verify all values
    if (!heightCm || !weightKg || !bmi) {
      console.error('Invalid BMI data:', { height: heightCm, weight: weightKg, bmi: bmi });
      return;
    }
    
    // Create URLSearchParams object for form data encoding
    const formData = new URLSearchParams();
    formData.append('height', heightCm);
    formData.append('weight', weightKg);
    formData.append('bmi', bmi);
    
    // Log what we're sending
    console.log('Sending data via fetch:', formData.toString());
    
    // Use fetch API instead of jQuery AJAX
    fetch('bmi_process.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: formData
    })
    .then(response => {
      console.log('Response status:', response.status);
      return response.text();
    })
    .then(data => {
      console.log('Server response:', data);
      showNotification(data, data.includes('successfully'));
      
      // Also trigger the jQuery ajaxSuccess handler (for compatibility)
      const event = new CustomEvent('ajaxSuccess', { 
        detail: { response: data, url: 'bmi_process.php' } 
      });
      document.dispatchEvent(event);
    })
    .catch(error => {
      console.error('Error saving BMI record:', error);
      showNotification("Error saving BMI data: " + error.message, false);
    });
    
    // Helper function to show notifications
    function showNotification(message, isSuccess) {
      const saveNotification = document.getElementById('saveNotification');
      if (saveNotification) {
        saveNotification.textContent = message;
        saveNotification.style.backgroundColor = isSuccess ? "#4CAF50" : "#f44336";
        saveNotification.style.display = 'block';
        
        // Hide notification after 5 seconds
        setTimeout(function() {
          saveNotification.style.display = 'none';
          if (!isSuccess) {
            saveNotification.style.backgroundColor = "#4CAF50"; // Reset color
          }
        }, 5000);
      }
    }
  }

  // Reset
  function resetForm() {
    document.querySelectorAll('input').forEach(input => {
      input.value = '';
      input.classList.remove('error');
      const error = input.nextElementSibling;
      if (error && error.classList.contains('bmi-error-message')) {
        error.style.display = 'none';
      }
    });
    bmiValue.textContent = "--.--";
    bmiCategory.textContent = "Enter your data to calculate";
    bmiMarker.style.left = "0%";
    bmiInterpretation.textContent = "Your BMI will provide an indication of your weight category and potential health risks.";
    bmiRecommendations.innerHTML = "<li>Enter your details to receive personalized health recommendations.</li>";
    if (bmiChartInstance) {
      bmiChartInstance.destroy();
      bmiChartInstance = null;
    }
  }

  // Events
  calculateBtn.addEventListener('click', calculateBMI);
  resetBtn.addEventListener('click', resetForm);

  // Document custom event for compatibility with the notification system in BMIcalculator.php
  document.addEventListener('ajaxSuccess', function(e) {
    if (e.detail && e.detail.url === 'bmi_process.php') {
      console.log('Custom ajaxSuccess event triggered:', e.detail.response);
    }
  });
});