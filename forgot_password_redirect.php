<?php
// For login_process.php - updated to handle both input ID formats and role-based redirection

// For debugging purposes only - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log login attempt
file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Login attempt\n", FILE_APPEND);

// Start session with secure parameters - TEMPORARILY DISABLE SECURE FLAG FOR LOCAL TESTING
session_start([
        'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
        'cookie_secure' => false,       // Only send cookie over HTTPS
        'cookie_samesite' => 'Lax',    // Helps prevent CSRF
        'use_strict_mode' => true,     // Use strict session mode
        'cookie_path' => '/',          // Explicit path
        'cookie_domain' => '',         // Empty = current domain
]);

// Log session status
file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Session started: " . session_id() . "\n", FILE_APPEND);

// Database connection - directly in this file
$host = "localhost";
$username = "root";
$password = "";
$database = "fyp_mariadb";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - DB connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    die("Connection failed: " . $conn->connect_error);
}

// Log database connection
file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - DB connection: " . ($conn ? "Success" : "Failed") . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    function validate($data){
       $data = trim($data);
       $data = stripslashes($data);
       $data = htmlspecialchars($data);
       return $data;
    }

    // Get username from either possible form field name
    $uname = isset($_POST['uname']) ? validate($_POST['uname']) : 
            (isset($_POST['UserName']) ? validate($_POST['UserName']) : '');
    
    // Get password from either possible form field name
    $pass = isset($_POST['password']) ? validate($_POST['password']) : 
           (isset($_POST['loginPassword']) ? validate($_POST['loginPassword']) : '');
    
    // Log the actual POST data for debugging
    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - POST data: " . 
                     "uname=" . (isset($_POST['uname']) ? "SET" : "NOT SET") . ", " .
                     "UserName=" . (isset($_POST['UserName']) ? "SET" : "NOT SET") . ", " .
                     "password=" . (isset($_POST['password']) ? "SET" : "NOT SET") . ", " .
                     "loginPassword=" . (isset($_POST['loginPassword']) ? "SET" : "NOT SET") . "\n", FILE_APPEND);
    
    // Store form data in session for repopulation on error
    $_SESSION['form_data'] = [
        'userName' => $uname
    ];

    $errors = [];

    if (empty($uname)) {
        $errors[] = "Username is required";
    }
    
    if(empty($pass)) {
        $errors[] = "Password is required";
    }

    if (empty($errors)) {
        // Use prepared statements to prevent SQL injection
        $sql = "SELECT * FROM user WHERE UserName=?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $uname);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                
                // Based on your database screenshot, it appears passwords might not be hashed
                // For production, you should implement password hashing
                // For now, checking if direct match or using password_verify
                if ($row['UserName'] === $uname && ($pass === $row['Password'] || password_verify($pass, $row['Password']))) {
                    // Set session variables using correct column names
                    $_SESSION['user_name'] = $row['UserName'];
                    $_SESSION['id'] = $row['UserID'];
                    $_SESSION['role'] = isset($row['Role']) ? $row['Role'] : 'user'; // Add role to session, default to user
                    
                    // Set a session token for security
                    $_SESSION['token'] = bin2hex(random_bytes(32));
                    
                    // Set session timeout (30 minutes)
                    $_SESSION['last_activity'] = time();
                    $_SESSION['expire_time'] = 30 * 60; // 30 minutes
                    
                    // Remember me functionality - updated to use UserID
                    if (isset($_POST['rememberMe']) && $_POST['rememberMe'] === 'on') {
                        // Create a secure remember me token
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        // Check if RememberME table exists and has the right structure
                        $stmt = $conn->prepare("INSERT INTO remembertoken (UserID, token, expires) VALUES (?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("iss", $row['UserID'], $token, date('Y-m-d H:i:s', $expires));
                            $stmt->execute();
                            
                            // Set cookie with the token
                            setcookie('remembertoken', $token, [
                                'expires' => $expires,
                                'path' => '/',
                                'secure' => true,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        } else {
                            // Log RememberMe table issue
                            file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - remembertoken table error: " . $conn->error . "\n", FILE_APPEND);
                        }
                    }
                    
                    // Clear form data on successful login
                    unset($_SESSION['form_data']);
                    
                    // Log successful login with role information
                    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Successful login for username: " . $uname . ", UserID: " . $row['UserID'] . "\n", FILE_APPEND);
                    
                    // Role-based redirection
                    if (isset($row['Role']) && strtolower($row['Role']) === 'admin') {
                        header("Location: AdminPage.php");
                    } else {
                        // Default to user page for any other role (including 'user')
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $errors[] = "Incorrect username or password";
                    file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Failed login attempt: password mismatch for username: " . $uname . "\n", FILE_APPEND);
                }
            } else {
                $errors[] = "Incorrect username or password";
                file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Failed login attempt: username not found: " . $uname . "\n", FILE_APPEND);
            }
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
            file_put_contents('login_debug.log', date('Y-m-d H:i:s') . " - Database error: " . mysqli_error($conn) . "\n", FILE_APPEND);
        }
    }
    
    // Store errors in session and redirect back to login page
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location:  index.php?showLogin=1");
        exit();
    }
    
} else {
    // Invalid request
    header("Location:  index.php?showLogin=1");
    exit();
}

// Close database connection
$conn->close();
?>