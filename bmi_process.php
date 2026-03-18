<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session first to ensure we have session data
require_once 'auth_helper.php';
start_secure_session();

// Include your config file to establish the connection
include('config.php');

// Check if the connection was successful
if (!$conn) {
    header('Content-Type: text/plain');
    echo "Database connection failed: " . mysqli_connect_error();
    exit;
}

// Debug information to track session data
$debug_info = "Session data: ";
if (isset($_SESSION)) {
    $debug_info .= "Session exists. ";
    if (isset($_SESSION['id'])) {
        $debug_info .= "User ID: " . $_SESSION['id'] . ". ";
    } else {
        $debug_info .= "No user ID in session. ";
    }
} else {
    $debug_info .= "No session data. ";
}

// Check if the user is logged in by checking for the UserID in the session
if (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
    $userID = $_SESSION['id']; // Get the UserID from the session

    // Validate and sanitize input data
    if (isset($_POST['height']) && isset($_POST['weight']) && isset($_POST['bmi'])) {
        $height = mysqli_real_escape_string($conn, $_POST['height']);
        $weight = mysqli_real_escape_string($conn, $_POST['weight']);
        $bmi = mysqli_real_escape_string($conn, $_POST['bmi']);
        
        // Debug information
        $debug_info .= "Received data: height=$height, weight=$weight, bmi=$bmi. ";

        // Validate numeric values
        if (!is_numeric($height) || !is_numeric($weight) || !is_numeric($bmi)) {
            header('Content-Type: text/plain');
            echo "Error: Height, weight, and BMI must be numeric values. " . $debug_info;
            exit;
        }

        // Generate the next bmiID (B1, B2, etc.)
        $query = "SELECT MAX(CAST(SUBSTRING(bmiID, 2) AS UNSIGNED)) AS max_id FROM bmi_record";
        $result = $conn->query($query);
        
        if (!$result) {
            header('Content-Type: text/plain');
            echo "Error in query: " . $conn->error . ". " . $debug_info;
            exit;
        }
        
        $row = $result->fetch_assoc();
        $nextID = ($row['max_id'] ?? 0) + 1;
        $bmiID = "B" . $nextID;

        $debug_info .= "Generated BMI ID: $bmiID. ";

        // Prepare the query to insert data into the bmi_record table
        $query = "INSERT INTO bmi_record (bmiID, UserID, height, weight, bmi, detectedAt) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
                  
        // Use prepared statement
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            header('Content-Type: text/plain');
            echo "Error preparing statement: " . $conn->error . ". " . $debug_info;
            exit;
        }
        
        $stmt->bind_param('ssddd', $bmiID, $userID, $height, $weight, $bmi);
        
        // Execute the query
        if ($stmt->execute()) {
            header('Content-Type: text/plain');
            echo "BMI data saved successfully!";
        } else {
            header('Content-Type: text/plain');
            echo "Error executing statement: " . $stmt->error . ". " . $debug_info;
        }
        
        $stmt->close();
    } else {
        header('Content-Type: text/plain');
        echo "Required data not received. height=" . (isset($_POST['height']) ? $_POST['height'] : 'not set') . 
             ", weight=" . (isset($_POST['weight']) ? $_POST['weight'] : 'not set') . 
             ", bmi=" . (isset($_POST['bmi']) ? $_POST['bmi'] : 'not set') . ". " . $debug_info;
    }
} else {
    header('Content-Type: text/plain');
    echo "User not logged in. " . $debug_info;
}

// Close the connection
$conn->close();
?>