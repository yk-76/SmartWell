<?php 
require_once 'auth_helper.php';
require_once 'config.php';

// Start secure session
start_secure_session();

// Check session consistency
check_session_consistency();

// Check if user_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: AdminPage.php");
    exit();
}

$user_id = $_GET['id'];
$error_message = '';
$success_message = '';

// Check for success or error messages
if (isset($_GET['success'])) {
    $success_message = "User updated successfully!";
} elseif (isset($_GET['error'])) {
    $error_message = "Error: " . ($_GET['message'] ?? "An error occurred while updating the user.");
}

// Fetch user data
$user_data = null;
$stmt = $conn->prepare("SELECT UserID, UserName, Email, DateOfBirth, Gender, PhoneNo FROM user WHERE UserID = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
} else {
    header("Location: AdminPage.php?error=user_not_found");
    exit();
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit User - Admin Dashboard - SmartWell</title>
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
    <!--Font Awesome-->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    />
    <style>
        .admin-edit-container {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-section {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body class="admin-bg">
    <div class="admin-edit-container">
        <div class="admin-header">
            <h1>Edit User</h1>
            <a href="AdminPage.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h3 class="form-title">User Details</h3>
            
            <form action="admin_update_user.php" method="POST">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_data['UserID']); ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                        value="<?php echo htmlspecialchars($user_data['UserName']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                        value="<?php echo htmlspecialchars($user_data['Email']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                        value="<?php echo htmlspecialchars($user_data['PhoneNo'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="dateOfBirth" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" 
                        value="<?php echo htmlspecialchars($user_data['DateOfBirth'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Gender</label>
                    <div class="d-flex flex-wrap">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" name="gender" id="male" value="male"
                                <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'male') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="male">Male</label>
                        </div>
                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" name="gender" id="female" value="female"
                                <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'female') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="female">Female</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="other" value="other"
                                <?php echo (isset($user_data['Gender']) && $user_data['Gender'] == 'other') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="other">Other</label>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="newPassword" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="newPassword" name="newPassword" placeholder="Leave blank to keep current password">
                    <small class="form-text text-muted">Minimum 8 characters. Leave blank if you don't want to change the password.</small>
                </div>
                
                <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="AdminPage.php" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!--jQuery-->
    <script src="Javascript/jquery-3.5.1.min.js"></script>
    <!--Bootstrap JS-->
    <script src="Javascript/bootstrap.min.js"></script>
    <!--Popper JS-->
    <script src="Javascript/popper.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword) {
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('New password must be at least 8 characters long.');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return;
                }
            } else if (confirmPassword) {
                e.preventDefault();
                alert('Please enter the new password in both fields.');
                return;
            }
        });
    </script>
</body>
</html>