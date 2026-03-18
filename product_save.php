<?php
date_default_timezone_set('Asia/Kuala_Lumpur');  

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth_helper.php';
start_secure_session();

// Include your config file to establish the connection
include('config.php');

$debug_data = array(
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'post_data' => $_POST,
    'session_data' => $_SESSION ?? 'no session',
    'raw_input' => file_get_contents('php://input')
);

$debug_info = json_encode($debug_data, JSON_PRETTY_PRINT);
error_log("DEBUG DATA: " . $debug_info);

// Check if the connection was successful
if (!$conn) {
    header('Content-Type: text/plain');
    echo "Database connection failed: " . mysqli_connect_error();
    error_log("Database connection failed: " . mysqli_connect_error());
    exit;
}

// Check if the user is logged in by checking for the UserID in the session
if (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
    $userID = $_SESSION['id']; // Get the UserID from the session
    error_log("User ID from session: " . $userID);

    // Validate and sanitize input data
    if (isset($_POST['productScore'])) {
        $productScore = mysqli_real_escape_string($conn, $_POST['productScore']);
        
        // Debug information
        error_log("Received data: productScore=$productScore");

        // Generate the next ProductID (P1, P2, etc.)
        $query = "SELECT MAX(CAST(SUBSTRING(ProductID, 2) AS UNSIGNED)) AS max_id FROM product_record";
        $result = $conn->query($query);
        
        if (!$result) {
            header('Content-Type: text/plain');
            $error_msg = "Error in query: " . $conn->error;
            echo $error_msg;
            error_log($error_msg);
            exit;
        }
        
        $row = $result->fetch_assoc();
        $nextID = ($row['max_id'] ?? 0) + 1;
        $productID = "P" . $nextID;

        error_log("Generated Product ID: $productID");

       $malaysiaTime = date('Y-m-d H:i:s'); 

        $query = "INSERT INTO product_record (ProductID, UserID, ProductScore, DetectedAt) 
                  VALUES (?, ?, ?, ?)";
              
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            header('Content-Type: text/plain');
            $error_msg = "Error preparing statement: " . $conn->error;
            echo $error_msg;
            error_log($error_msg);
            exit;
        }
        
            $stmt->bind_param('ssss', $productID, $userID, $productScore, $malaysiaTime); 
        
        // Execute the query
        if ($stmt->execute()) {
            header('Content-Type: text/plain');
            echo "Product score data saved successfully!";
            error_log("Product score data saved successfully! ProductID: $productID, UserID: $userID, ProductScore: $productScore");
        } else {
            header('Content-Type: text/plain');
            $error_msg = "Error executing statement: " . $stmt->error;
            echo $error_msg;
            error_log($error_msg);
        }
        
        $stmt->close();
    } else {
        // Check if we have data in raw input
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['productScore'])) {
            header('Content-Type: text/plain');
            echo "Data received as JSON, expected form data. Please check content type.";
            error_log("Data received as JSON, expected form data. JSON data: " . json_encode($input));
        } else {
            header('Content-Type: text/plain');
            echo "Required data not received. POST data: " . json_encode($_POST);
            error_log("Required data not received. POST: " . json_encode($_POST) . ", Raw input: " . file_get_contents('php://input'));
        }
    }
} else {
    header('Content-Type: text/plain');
    echo "User not logged in. Session data: " . json_encode($_SESSION ?? 'no session');
    error_log("User not logged in. Session data: " . json_encode($_SESSION ?? 'no session'));
}

// Close the connection
$conn->close();
?>