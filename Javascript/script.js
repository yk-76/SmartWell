/**
 * SmartWell Web App - Main JavaScript File
 * Contains functionality from both original scripts
 */

document.addEventListener('DOMContentLoaded', function () {
  //=============================================================
  // SECTION 1: Helper Functions
  //=============================================================

  /**
   * Safely get an element by ID, with logging if missing
   * @param {string} id - Element ID to find
   * @param {boolean} logMissing - Whether to log if element not found
   * @returns {HTMLElement|null} - The found element or null
   */
  function safeGetElement (id, logMissing = true) {
    const element = document.getElementById(id)
    if (!element && logMissing) {
      console.log(`Element not found with ID: ${id}`)
    }
    return element
  }

  /**
   * Add event listener with null check
   * @param {HTMLElement|null} element - Element to add listener to
   * @param {string} event - Event name (click, submit, etc.)
   * @param {Function} handler - Event handler function
   */
  function safeAddEventListener (element, event, handler) {
    if (element) {
      element.addEventListener(event, handler)
    }
  }

  // REPLACE this section in your existing script.js file:

//=============================================================
// SECTION 2: Login Form Handling - UPDATED FOR TAB SUPPORT
//=============================================================

// Get login forms - support for both traditional and QR forms
const loginForm = safeGetElement('loginForm', false); // Legacy form ID
const traditionalForm = safeGetElement('traditionalLoginForm', false);
const qrForm = safeGetElement('qrLoginForm', false);
const modalLoginForm = safeGetElement('modalLoginForm', false);

// Handle traditional login form (new tab-based form)
if (traditionalForm) {
  console.log('Traditional login form found');
  
  traditionalForm.addEventListener('submit', function (event) {
    console.log('Traditional login form submission triggered');
    
    const username = safeGetElement('uname') ? safeGetElement('uname').value : '';
    const password = safeGetElement('password') ? safeGetElement('password').value : '';
    
    let isValid = true;
    const errors = [];
    
    if (!username || username.trim() === '') {
      isValid = false;
      errors.push('Please enter your username');
    }
    
    if (!password) {
      isValid = false;
      errors.push('Please enter your password');
    }
    
    if (!isValid) {
      event.preventDefault();
      console.log('Traditional form validation failed');
      alert(errors.join('\n'));
      return false;
    }
    
    console.log('Traditional form validation passed, submitting...');
    // Let form submit normally - don't prevent default
  });
}

// Handle QR login form (new tab-based form)
if (qrForm) {
  console.log('QR login form found');
  
  qrForm.addEventListener('submit', function (event) {
    console.log('QR login form submission triggered');
    
    const qrData = safeGetElement('qr_data') ? safeGetElement('qr_data').value : '';
    
    if (!qrData || qrData.trim() === '') {
      event.preventDefault();
      console.log('QR form validation failed');
      alert('Please enter QR code data');
      return false;
    }
    
    console.log('QR form validation passed, submitting...');
    // Let form submit normally - don't prevent default
  });
}

// Handle legacy login form (if still exists)
if (loginForm) {
  console.log('Legacy login form found with ID: loginForm');

  loginForm.addEventListener('submit', function (event) {
    console.log('Legacy login form submission triggered');
    event.preventDefault();

    // Get username - check all possible ID variations used across the site
    const username = safeGetElement('uname')
      ? safeGetElement('uname').value
      : safeGetElement('UserName')
      ? safeGetElement('UserName').value
      : safeGetElement('userName')
      ? safeGetElement('userName').value
      : safeGetElement('loginEmail')
      ? safeGetElement('loginEmail').value
      : '';

    // Get password - check all possible ID variations
    const password = safeGetElement('password')
      ? safeGetElement('password').value
      : safeGetElement('Password')
      ? safeGetElement('Password').value
      : safeGetElement('loginPassword')
      ? safeGetElement('loginPassword').value
      : '';

    console.log('Validating username and password');

    let isValid = true;
    const errors = [];

    if (!username || username.trim() === '') {
      isValid = false;
      errors.push('Please enter your username');
    }

    if (!password) {
      isValid = false;
      errors.push('Please enter your password');
    }

    if (!isValid) {
      console.log('Validation failed');
      alert(errors.join('\n'));
    } else {
      console.log('Form validation passed, submitting form');
      // Add action if not present (for the modal form)
      if (
        !this.getAttribute('action') &&
        window.location.pathname.includes('index.php')
      ) {
        this.setAttribute('action', 'login_process.php');
      }
      this.submit();
    }
  });
} else {
  console.log('No legacy login form found');
}

  //=============================================================
  // SECTION 3: Modal Auth Control (Login/Register Panel)
  //=============================================================

  // Get modal elements - with null checks
  const authModal = safeGetElement('authModal')
  const closeButton = document.querySelector('.auth-close')
  const formsContainer = safeGetElement('formsContainer')
  const showRegisterForm = safeGetElement('showRegisterForm')
  const showLoginForm = safeGetElement('showLoginForm')
  const loginBgSection = safeGetElement('loginBgSection')
  const registerBgSection = safeGetElement('registerBgSection')
  const modal = document.getElementById('authModal')
  const openModalBtn = document.getElementById('openModal')
  const closeModalBtn = document.querySelector('.auth-close')
  const showRegisterFormBtn = document.getElementById('showRegisterForm')
  const showLoginFormBtn = document.getElementById('showLoginForm')
  // Look for login/register links in the navigation
  const loginLinks = document.querySelectorAll(
    'a.nav-link:not([href="index.php"]):not([href="About.php"]):not([href="Service.php"])'
  )

  loginLinks.forEach(link => {
    if (link.textContent.toLowerCase().includes('login')) {
      link.addEventListener('click', function (e) {
        e.preventDefault()
        if (typeof openAuthModal === 'function') {
          openAuthModal('login')
        } else {
          window.location.href = 'Login.php';
        }
      })
    } else if (link.textContent.toLowerCase().includes('register')) {
      link.addEventListener('click', function (e) {
        e.preventDefault()
        if (typeof openAuthModal === 'function') {
          openAuthModal('register')
        } else {
          window.location.href = 'Register.php';
        }
      })
    }
  })

  // Function to set background images (only for desktop view)
  function setBackgroundImages () {
    // Only set backgrounds for desktop screens and if elements exist
    if (window.innerWidth >= 768) {
      if (loginBgSection) {
        loginBgSection.style.backgroundImage = "url('image/bgLogin.jpg')"
      }
      if (registerBgSection) {
        registerBgSection.style.backgroundImage = "url('image/bgRegister1.jpg')"
      }
    }
  }

  // Close modal when clicking the X
  safeAddEventListener(closeButton, 'click', function () {
    if (authModal) {
      authModal.style.display = 'none'
    }
  })

  // Close modal when clicking outside
  window.addEventListener('click', function (event) {
    if (authModal && event.target == authModal) {
      authModal.style.display = 'none'
    }
  })

  // Switch to registration form with slide effect
  safeAddEventListener(showRegisterForm, 'click', function (e) {
    e.preventDefault()
    if (formsContainer) {
      formsContainer.classList.add('show-register')
    }
  })

  // Switch to login form with slide effect
  safeAddEventListener(showLoginForm, 'click', function (e) {
    e.preventDefault()
    if (formsContainer) {
      formsContainer.classList.remove('show-register')
    }
  })

  // Set backgrounds on page load and resize
  if (loginBgSection || registerBgSection) {
    window.addEventListener('resize', setBackgroundImages)
    setBackgroundImages()
  }

  if (openModalBtn && modal) {
    openModalBtn.addEventListener('click', () => {
      modal.style.display = 'flex'
    })
  }

  // Close modal
  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
      modal.style.display = 'none'
    })
  }

  // Close modal when clicking outside
  window.addEventListener('click', e => {
    if (e.target === modal) {
      modal.style.display = 'none'
    }
  })

  // Switch to register form
  if (showRegisterFormBtn) {
    showRegisterFormBtn.addEventListener('click', e => {
      e.preventDefault()
      formsContainer.classList.add('show-register')
    })
  }

  // Switch to login form
  if (showLoginFormBtn) {
    showLoginFormBtn.addEventListener('click', e => {
      e.preventDefault()
      formsContainer.classList.remove('show-register')
    })
  }

  const showForgotPasswordBtn = document.getElementById(
    'showForgotPasswordForm'
  )
  const backToLoginBtn = document.getElementById('backToLoginForm')
  const forgotPasswordContainer = document.getElementById(
    'forgotPasswordContainer'
  )
  const emailMethodBtn = document.getElementById('emailMethodBtn')
  const phoneMethodBtn = document.getElementById('phoneMethodBtn')
  const emailInputGroup = document.getElementById('emailInputGroup')
  const phoneInputGroup = document.getElementById('phoneInputGroup')
  const contactMethodInput = document.getElementById('contactMethod')
  const forgotEmailInput = document.getElementById('forgotEmail')
  const forgotPhoneInput = document.getElementById('forgotPhone')

  // Show forgot password form
  if (showForgotPasswordBtn) {
    showForgotPasswordBtn.addEventListener('click', e => {
      e.preventDefault()
      if (forgotPasswordContainer) {
        forgotPasswordContainer.classList.add('show-forgot')
      }
    })
  }

  // Back to login form
  if (backToLoginBtn) {
    backToLoginBtn.addEventListener('click', e => {
      e.preventDefault()
      if (forgotPasswordContainer) {
        forgotPasswordContainer.classList.remove('show-forgot')
      }
    })
  }

  // Contact method switching
  if (emailMethodBtn) {
    emailMethodBtn.addEventListener('click', () => {
      emailMethodBtn.classList.add('active')
      phoneMethodBtn.classList.remove('active')
      emailInputGroup.style.display = 'block'
      phoneInputGroup.style.display = 'none'
      contactMethodInput.value = 'email'
      forgotEmailInput.required = true
      forgotPhoneInput.required = false
    })
  }

  if (phoneMethodBtn) {
    phoneMethodBtn.addEventListener('click', () => {
      phoneMethodBtn.classList.add('active')
      emailMethodBtn.classList.remove('active')
      phoneInputGroup.style.display = 'block'
      emailInputGroup.style.display = 'none'
      contactMethodInput.value = 'phone'
      forgotPhoneInput.required = true
      forgotEmailInput.required = false
    })
  }

  // Form submission handling
  const forgotPasswordForm = document.getElementById('forgotPasswordForm');
  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', e => {
      e.preventDefault()

      const formData = new FormData(e.target)
      const contactMethod = formData.get('contactMethod')
      const contact =
        contactMethod === 'email'
          ? formData.get('email')
          : formData.get('phone')

      // Here you would typically send the data to your server
      console.log('Reset password request:', {
        method: contactMethod,
        contact: contact
      })

      // Show success message (you can customize this)
      alert(`Password reset link has been sent to your ${contactMethod}!`)

      // Optionally reset the form and go back to login
      if (forgotPasswordContainer) {
        forgotPasswordContainer.classList.remove('show-forgot')
      }
    })
  }
 // Add this to your script.js file
document.addEventListener('DOMContentLoaded', function() {
    // Check URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('showLogin')) {
        // Determine which form to show (login or register)
        const showForm = urlParams.get('showRegister') ? 'register' : 'login';
        openAuthModal(showForm);
        
        // If there are errors, display them
        if (urlParams.has('error')) {
            const errorMsg = decodeURIComponent(urlParams.get('error'));
            const errorContainer = document.querySelector('.auth-modal .alert-danger') || 
                                  createErrorContainer();
            errorContainer.innerHTML = errorMsg;
            errorContainer.style.display = 'block';
        }
    }
    
    // Function to create error container if it doesn't exist
    function createErrorContainer() {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        
        const formSection = document.querySelector('.form-section');
        if (formSection) {
            formSection.insertBefore(errorDiv, formSection.firstChild);
        }
        
        return errorDiv;
    }
});

  //=============================================================
  // SECTION 4: Registration Form Validation
  //=============================================================

  const registrationForm = safeGetElement('registrationForm')
  const modalRegistrationForm = safeGetElement('modalRegistrationForm', false);

  safeAddEventListener(registrationForm, 'submit', function (event) {
    event.preventDefault()

    // Get password fields - try to get the elements before accessing values
    const passwordField = safeGetElement('password')
    const confirmPasswordField = safeGetElement('confirmPassword')

    if (!passwordField || !confirmPasswordField) {
      alert('Error: Password fields not found')
      return
    }

    const password = passwordField.value
    const confirmPassword = confirmPasswordField.value

    if (password !== confirmPassword) {
      alert('Passwords do not match!')
      return
    }

    if (password.length < 8) {
      alert('Password must be at least 8 characters!')
      return
    }

    // If validation passes, submit the form
    alert('Registration successful!')
    if (authModal) {
      authModal.style.display = 'none'
    }
    this.submit()
  })

  // Additional registration form handling from second file
  function handleRegistrationSubmit(event) {
    event.preventDefault();

    // Get the form that was submitted
    const form = event.target;
    
    // Find password fields within this form
    const passwordField = form.querySelector('[name="password"]');
    const confirmPasswordField = form.querySelector('[name="confirmPassword"], [name="password_confirmation"]');

    if (!passwordField || !confirmPasswordField) {
      alert('Error: Password fields not found');
      return;
    }

    const password = passwordField.value;
    const confirmPassword = confirmPasswordField.value;

    if (password !== confirmPassword) {
      alert('Passwords do not match!');
      return;
    }

    if (password.length < 8) {
      alert('Password must be at least 8 characters!');
      return;
    }

    if (!(/[a-z]/i.test(password) && /[0-9]/.test(password))) {
      alert('Password must contain at least one letter and one number!');
      return;
    }

    // If validation passes, submit the form
    console.log('Registration validation passed, submitting form');
    form.submit();
  }

  // Add event listener to the modal registration form if it exists
  safeAddEventListener(modalRegistrationForm, 'submit', handleRegistrationSubmit);

  //=============================================================
  // SECTION 5: Profile Dropdown
  //=============================================================

  document.addEventListener('DOMContentLoaded', () => {
    const profileDropdownBtn = document.getElementById('profileDropdownBtn')
    const profileDropdownContent = document.getElementById(
      'profileDropdownContent'
    )

    if (profileDropdownBtn && profileDropdownContent) {
      profileDropdownBtn.addEventListener('click', function (event) {
        event.stopPropagation()
        profileDropdownContent.classList.toggle('show')
      })
    }

    window.addEventListener('click', function (event) {
      if (!event.target.closest('#profileMenuItem')) {
        const dropdowns = document.getElementsByClassName('profile-content')
        for (let i = 0; i < dropdowns.length; i++) {
          const openDropdown = dropdowns[i]
          if (openDropdown && openDropdown.classList.contains('show')) {
            openDropdown.classList.remove('show')
          }
        }
      }
    })
  })

  // Additional profile dropdown functionality from second file
  const profileBtn = safeGetElement('profileDropdownBtn', false);
  const profileContent = safeGetElement('profileDropdownContent', false);

  if (profileBtn && profileContent) {
    profileBtn.addEventListener('click', function(event) {
      event.stopPropagation();
      profileContent.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!profileBtn.contains(e.target) && !profileContent.contains(e.target)) {
        profileContent.classList.remove('show');
      }
    });
  }

  //=============================================================
  // SECTION 6: Services Dropdown Toggle
  //=============================================================

  // Function to toggle the services dropdown
  function toggleDropdown () {
    const dropdownMenu = document.getElementById('dropdownMenu')
    const dropdownArrow = document.getElementById('dropdownArrow')

    if (dropdownMenu) {
      dropdownMenu.classList.toggle('show')

      if (dropdownArrow) {
        // Change arrow direction based on dropdown state
        dropdownArrow.innerHTML = dropdownMenu.classList.contains('show')
          ? '&#9650;'
          : '&#9660;'
      }
    }
  }

  // Initialize dropdown functionality for services menu
  const servicesDropdown = document.getElementById('servicesDropdown')
  if (servicesDropdown) {
    const dropdownMenu = document.getElementById('dropdownMenu')
    const dropdownArrow = document.getElementById('dropdownArrow')

    servicesDropdown.addEventListener('click', function (e) {
      if (
        e.target.tagName !== 'A' ||
        !e.target.classList.contains('dropdown-item')
      ) {
        const isExpanded = dropdownMenu.classList.contains('show')

        // Toggle dropdown visibility
        if (isExpanded) {
          dropdownMenu.classList.remove('show')
          dropdownArrow.innerHTML = '&#9660;' // down arrow
        } else {
          dropdownMenu.classList.add('show')
          dropdownArrow.innerHTML = '&#9650;' // up arrow
        }
      }
    })
  }

  // Define toggleDropdown function in global scope
  window.toggleDropdown = function () {
    const menu = safeGetElement('dropdownMenu')
    const arrow = safeGetElement('dropdownArrow')

    if (menu && arrow) {
      const isOpen = menu.style.display === 'block'
      menu.style.display = isOpen ? 'none' : 'block'
      arrow.classList.toggle('rotate', !isOpen)
    }
  }

  // Window-level event listener for dropdown closure
  document.addEventListener('click', function (event) {
    const dropdown = safeGetElement('servicesDropdown', false)
    const menu = safeGetElement('dropdownMenu', false)
    const arrow = safeGetElement('dropdownArrow', false)

    if (dropdown && menu && arrow && !dropdown.contains(event.target)) {
      menu.style.display = 'none'
      arrow.classList.remove('rotate')
    }
  })

  //=============================================================
  // SECTION 7: Logout Button Functionality
  //=============================================================

  const logoutBtn = safeGetElement('logoutBtn')
  const loginMenuItem = safeGetElement('loginMenuItem')
  const profileMenuItem = safeGetElement('profileMenuItem')

  // Only set up event handler if all elements exist
  if (logoutBtn && loginMenuItem && profileMenuItem) {
    safeAddEventListener(logoutBtn, 'click', function (e) {
      // We want it to navigate to logout.php now, so don't prevent default
      // The link's href will handle the navigation
    })
  }

  //=============================================================
  // SECTION 8: Camera Control for Stroke Detection Page
  //=============================================================

  const video = safeGetElement('video', false)
  const startCameraBtn = safeGetElement('startCamera', false)
  const stopCameraBtn = safeGetElement('stopCamera', false)
  const captureBtn = safeGetElement('capture', false)
  const analyzeBtn = safeGetElement('analyze', false)

  // Stream reference for camera
  let stream = null
  let isImageCaptured = false

  // Only initialize camera controls if elements exist (on stroke detection page)
  if (video && startCameraBtn && stopCameraBtn && captureBtn && analyzeBtn) {
    // Initialize camera
    safeAddEventListener(startCameraBtn, 'click', async function () {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            width: { ideal: 640 },
            height: { ideal: 480 },
            facingMode: 'user'
          },
          audio: false
        })

        video.srcObject = stream
        startCameraBtn.disabled = true
        stopCameraBtn.disabled = false
        captureBtn.disabled = false
      } catch (error) {
        console.error('Error accessing camera:', error)
        alert(
          'Unable to access camera. Please ensure you have given permission.'
        )
      }
    })

    // Stop camera
    safeAddEventListener(stopCameraBtn, 'click', function () {
      if (stream) {
        stream.getTracks().forEach(track => track.stop())
        video.srcObject = null
        startCameraBtn.disabled = false
        stopCameraBtn.disabled = true
        captureBtn.disabled = true
      }
    })

    // Capture image
    safeAddEventListener(captureBtn, 'click', function () {
      const previewCanvas = safeGetElement('previewCanvas')
      const processCanvas = safeGetElement('processCanvas')

      if (previewCanvas && processCanvas) {
        const context = previewCanvas.getContext('2d')
        context.drawImage(
          video,
          0,
          0,
          previewCanvas.width,
          previewCanvas.height
        )

        // Process for model input
        const processContext = processCanvas.getContext('2d')
        processContext.drawImage(
          video,
          0,
          0,
          processCanvas.width,
          processCanvas.height
        )

        isImageCaptured = true
        analyzeBtn.disabled = false
      }
    })

    // Analyze image
    safeAddEventListener(analyzeBtn, 'click', async function () {
      const resultBox = safeGetElement('resultBox')
      const resultText = safeGetElement('result')
      const riskLevel = safeGetElement('riskLevel')
      const recommendationBox = safeGetElement('recommendationBox')
      const recommendations = safeGetElement('recommendations')

      if (!isImageCaptured) {
        alert('Please capture an image first')
        return
      }

      if (resultBox && resultText && riskLevel) {
        resultBox.className = 'result loading'
        resultText.textContent = 'Analyzing image...'
        riskLevel.style.width = '0%'

        try {
          // This would be your actual analysis code using the loaded model
          // For demo purposes, we'll simulate analysis with a timeout and random result
          await new Promise(resolve => setTimeout(resolve, 2000))

          // Generate a random score for demo purposes
          // In a real implementation, this would come from your model prediction
          const riskScore = Math.random()

          // Update UI based on result
          if (recommendationBox && recommendations) {
            // Set risk level indicator
            riskLevel.style.width = `${riskScore * 100}%`

            // Show recommendations
            recommendationBox.style.display = 'block'
            recommendations.innerHTML = '' // Clear previous recommendations

            if (riskScore > 0.7) {
              resultText.textContent =
                'High risk detected. Please seek immediate medical attention.'
              resultBox.className = 'result positive'

              // Add high risk recommendations
              addRecommendation(
                'Call emergency services (911) immediately',
                recommendations
              )
              addRecommendation(
                'Do not drive yourself to the hospital',
                recommendations
              )
              addRecommendation(
                'Take note of when symptoms began',
                recommendations
              )
            } else if (riskScore > 0.4) {
              resultText.textContent =
                'Moderate risk detected. Consult with a healthcare provider soon.'
              resultBox.className = 'result positive'

              // Add moderate risk recommendations
              addRecommendation(
                'Schedule an appointment with your doctor within 24-48 hours',
                recommendations
              )
              addRecommendation(
                'Monitor for worsening symptoms',
                recommendations
              )
              addRecommendation(
                'Avoid strenuous activity until evaluated',
                recommendations
              )
            } else {
              resultText.textContent =
                'Low risk detected. Continue monitoring your health.'
              resultBox.className = 'result negative'

              // Add low risk recommendations
              addRecommendation('Maintain a healthy lifestyle', recommendations)
              addRecommendation(
                'Regular check-ups with your healthcare provider',
                recommendations
              )
              addRecommendation(
                'Be aware of stroke warning signs',
                recommendations
              )
            }
          }
        } catch (error) {
          console.error('Analysis error:', error)
          resultText.textContent =
            'An error occurred during analysis. Please try again.'
          resultBox.className = 'result'
        }
      }
    })

    // Add recommendation item helper
    function addRecommendation (text, recommendationsElement) {
      const li = document.createElement('li')
      li.textContent = text
      recommendationsElement.appendChild(li)
    }

    // Load the model on page load
    async function loadModel () {
      const modelStatus = safeGetElement('modelStatus')

      if (modelStatus) {
        try {
          modelStatus.textContent = 'Loading AI model...'
          modelStatus.className = 'status loading'

          // Replace with your actual model loading code
          // This is a placeholder - you'll need to use actual TensorFlow.js code here
          // model = await tf.loadLayersModel('model/model.json');

          // For demo purposes, we'll simulate model loading with a timeout
          await new Promise(resolve => setTimeout(resolve, 2000))

          modelStatus.textContent = 'AI model ready'
          modelStatus.className = 'status ready'
          startCameraBtn.disabled = false
        } catch (error) {
          console.error('Failed to load model:', error)
          modelStatus.textContent = 'Error loading model'
          modelStatus.className = 'status error'
        }
      }
    }

    // Initialize model on page load
    loadModel()
  }

  //=============================================================
  // SECTION 9: Admin Page Functions
  //=============================================================

  // Navigation functionality
  window.showSection = function (sectionName) {
    // Hide all sections
    const sections = document.querySelectorAll('.content-section')
    sections.forEach(section => {
      section.style.display = 'none'
    })

    // Show selected section
    const targetSection = document.getElementById(sectionName + '-section')
    if (targetSection) {
      targetSection.style.display = 'block'
    }

    // Update active nav link
    const navLinks = document.querySelectorAll('.nav-link')
    navLinks.forEach(link => {
      link.classList.remove('active')
    })
    event.target.classList.add('active')

    // Update page title
    const titles = {
      dashboard: 'Dashboard Overview',
      users: 'User Management',
      stroke: 'Stroke Detection Records',
      nutrition: 'Nutrition Analysis Records',
      bmi: 'BMI Calculator Records',
      reports: 'Reports & Analytics',
      settings: 'System Settings'
    }

    const pageTitle = document.getElementById('page-title');
    if (pageTitle) {
      pageTitle.textContent = titles[sectionName] || 'Dashboard';
    }
  }

  // Simulate real-time updates
  function updateStats () {
    const statsNumbers = document.querySelectorAll('.stat-number')
    statsNumbers.forEach(stat => {
      const currentValue = parseInt(stat.textContent.replace(',', ''))
      const newValue = currentValue + Math.floor(Math.random() * 3)
      stat.textContent = newValue.toLocaleString()
    })
  }

  // Update stats every 30 seconds (simulation)
  setInterval(updateStats, 30000)

  // Add some interactivity to the tables
  document.querySelectorAll('.data-table tr').forEach(row => {
    row.addEventListener('click', function () {
      // Remove previous selections
      document.querySelectorAll('.data-table tr').forEach(r => {
        r.style.backgroundColor = ''
      })
      // Highlight selected row
      if (this.querySelector('th') === null) {
        // Don't highlight header
        this.style.backgroundColor = '#e0e7ff'
      }
    })
  })

  // Search functionality
  document.querySelectorAll('.search-input').forEach(input => {
    if (input.type === 'text') {
      input.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase()
        const table = this.closest('.data-table-container').querySelector('table')
        if (table) {
          const rows = table.querySelectorAll('tbody tr')
          rows.forEach(row => {
            const text = row.textContent.toLowerCase()
            row.style.display = text.includes(searchTerm) ? '' : 'none'
          })
        }
      })
    }
  })

  // Initialize with dashboard view if on admin page
  const adminDashboard = document.querySelector('.admin-dashboard');
  if (adminDashboard) {
    window.showSection('dashboard');
  }
})

//=============================================================
// SECTION 10: Auth Modal Function (Global Scope)
//=============================================================


/**
 * Opens the authentication modal with specified form type and optional message
 * @param {string} formType - 'login' or 'register'
 * @param {string} message - Optional message to display
 * @param {string} error - Optional error message to display
 */
window.openAuthModal = function(formType, message, error) {
  const authModal = document.getElementById('authModal');
  const formsContainer = document.getElementById('formsContainer');
  
  if (authModal) {
    // Show the modal
    authModal.style.display = 'flex';
    
    // Switch between login and register forms
    if (formsContainer) {
      if (formType === 'register') {
        formsContainer.classList.add('show-register');
      } else {
        formsContainer.classList.remove('show-register');
      }
    }
    
    // Show message if provided
    if (message) {
      let messageEl = document.getElementById('auth-message');
      if (!messageEl) {
        messageEl = document.createElement('div');
        messageEl.id = 'auth-message';
        messageEl.className = 'auth-message alert alert-info';
        
        // Find the active form section to insert the message
        const formSection = formType === 'register' 
          ? document.querySelectorAll('.form-section')[1] 
          : document.querySelector('.form-section');
          
        if (formSection) {
          formSection.insertBefore(messageEl, formSection.firstChild);
        }
      }
      
      messageEl.textContent = message;
      messageEl.style.display = 'block';
    }
    
    // Show error if provided
    if (error) {
      let errorEl = document.getElementById('auth-error');
      if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.id = 'auth-error';
        errorEl.className = 'auth-error alert alert-danger';
        
        // Find the active form section to insert the error
        const formSection = formType === 'register' 
          ? document.querySelectorAll('.form-section')[1] 
          : document.querySelector('.form-section');
          
        if (formSection) {
          formSection.insertBefore(errorEl, formSection.firstChild);
        }
      }
      
      errorEl.innerHTML = error; // Use innerHTML to support line breaks
      errorEl.style.display = 'block';
    }
  } else {
    console.error("Auth modal not found in the DOM");
  }
};

// Check for URL parameters related to authentication on page load
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  
  // Handle login/register parameters
  if (urlParams.has('showLogin') || urlParams.has('showRegister')) {
    const showRegister = urlParams.has('showRegister');
    const formType = showRegister ? 'register' : 'login';
    let message = '';
    
    // Get message based on parameters
    if (urlParams.has('timeout')) {
      message = 'Your session has timed out. Please log in again.';
    } else if (urlParams.has('service')) {
      const service = urlParams.get('service');
      if (service === 'StrokeDetection.php') {
        message = 'Please log in to use the Stroke Detection service';
      } else if (service === 'ProductDetection.php') {
        message = 'Please log in to use the Product Detection service';
      } else if (service === 'BMIcalculator.php') {
        message = 'Please log in to use the BMI Calculator service';
      } else {
        message = 'Please log in to access this service';
      }
    } else if (urlParams.has('registered')) {
      message = 'Registration successful! You are now logged in.';
    }
    
    // Get error message if present
    const error = urlParams.has('error') ? decodeURIComponent(urlParams.get('error')) : '';
    
    // Open the modal with the appropriate form, message, and error
    openAuthModal(formType, message, error);
  }
});

// Add this to properly close the modal
document.addEventListener('DOMContentLoaded', function() {
  // Close the modal when clicking the X
  const closeButton = document.querySelector('.auth-close');
  if (closeButton) {
    closeButton.addEventListener('click', function() {
      const authModal = document.getElementById('authModal');
      if (authModal) {
        authModal.style.display = 'none';
      }
      
      // Clear any messages or errors
      const messageEl = document.getElementById('auth-message');
      const errorEl = document.getElementById('auth-error');
      
      if (messageEl) messageEl.style.display = 'none';
      if (errorEl) errorEl.style.display = 'none';
    });
  }
  
  // Close the modal when clicking outside
  window.addEventListener('click', function(event) {
    const authModal = document.getElementById('authModal');
    if (event.target === authModal) {
      authModal.style.display = 'none';
      
      // Clear any messages or errors
      const messageEl = document.getElementById('auth-message');
      const errorEl = document.getElementById('auth-error');
      
      if (messageEl) messageEl.style.display = 'none';
      if (errorEl) errorEl.style.display = 'none';
    }
  });
});

//=============================================================
// SECTION 11: QR Code functionality
//=============================================================

// QR Code functionality
let qrCodeVisible = false;

function toggleQRCode() {
    const section = document.getElementById('qrCodeSection');
    const toggleText = document.getElementById('qrToggleText');

    if (!section || !toggleText) return;

    qrCodeVisible = !qrCodeVisible;
    section.style.display = qrCodeVisible ? 'block' : 'none';
    toggleText.textContent = qrCodeVisible ? 'Hide QR Code' : 'Show QR Code';

    if (qrCodeVisible) {
        setTimeout(() => {
            section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
}

function handleQRSuccess() {
    console.log('QR Code loaded successfully');
}

function handleQRError(img) {
    console.error('QR Code failed to load');

    const container = img?.parentNode;
    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> 
            Failed to load QR code image<br>
            <small>The QR code data may be corrupted. Try generating a new one.</small>
        </div>
        <button class="btn btn-warning btn-sm mt-2" onclick="generateNewQRCode()">
            <i class="fas fa-refresh"></i> Generate New QR Code
        </button>
    `;
}

function generateNewQRCode() {
    const loadingOverlay = document.getElementById('qrLoadingOverlay');
    if (loadingOverlay) loadingOverlay.style.display = 'block';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_qr.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'generate_qr';
    input.value = '1';

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function downloadQR() {
    const downloadBtn = document.getElementById('qrDownloadBtn');
    const base64 = downloadBtn?.dataset?.base64;
    const userName = downloadBtn?.dataset?.username || 'qr-code';

    if (!base64 || base64.length < 100) {
        alert('QR code is missing or invalid. Please regenerate it.');
        return;
    }

    try {
        const link = document.createElement('a');
        link.download = `smartwell-qr-${userName}.png`;
        link.href = `data:image/png;base64,${base64}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (error) {
        console.error('QR Download Error:', error);
        alert('Download failed. Try regenerating the QR code.');
    }
}


function shareQR() {
    const userId = document.getElementById('qrShareBtn')?.dataset?.userid;
    const profileLink = `${window.location.origin}/profile/${userId}`;

    if (navigator.share) {
        navigator.share({
            title: 'My SmartWell QR Code',
            text: 'Check out my SmartWell profile QR code!',
            url: profileLink
        }).catch(console.error);
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(profileLink)
            .then(() => alert('Profile link copied to clipboard!'))
            .catch(() => alert('Unable to copy to clipboard'));
    } else {
        alert('Sharing not supported on this device');
    }
}

function debugQRInfo() {
    const debugBtn = document.getElementById('qrDebugBtn');
    const hasQRCode = debugBtn?.dataset?.hasqr === 'true';
    const qrError = debugBtn?.dataset?.qrerror || '';
    const base64 = debugBtn?.dataset?.base64 || '';
    const userId = debugBtn?.dataset?.userid || '';

    console.log('=== QR Code Debug Info ===');
    console.log('Has QR Code:', hasQRCode);
    console.log('QR Error:', qrError);
    if (hasQRCode) {
        console.log('Base64 length:', base64.length);
        console.log('Base64 preview:', base64.substring(0, 50) + '...');
    }
    console.log('User ID:', userId);
}

