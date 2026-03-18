<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Error & exception handlers
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "$errstr in $errfile at line $errline"
    ]);
    exit;
});
set_exception_handler(function($ex) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "Uncaught exception: " . $ex->getMessage()
    ]);
    exit;
});

// ---- Rest of your script below ----


    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Start the session first to ensure we have session data
    require_once 'auth_helper.php';
    start_secure_session();
    
    // Include your config file to establish the connection
    include('config.php');
    
    /*function get_health_advice($food_label) {
        // Use your DeepSeek model via GitHub API
        $api_key = getenv('GITHUB_TOKEN'); // your GitHub token
        $url = getenv('AI_MODEL_ENDPOINT');            // DeepSeek endpoint
    
        $prompt = <<<EOT
    A user scanned a food labeled '$food_label'.
    
    1. Is this healthy or unhealthy? Explain.
    2. Mention stroke risk (if any).
    3. Suggest healthier alternatives.
    4. Make all the answer short.
    EOT;
    
        $data = [
            $model = getenv('AI_MODEL_NAME'),
            "messages" => [
                ["role" => "system", "content" => "You are a helpful food and health assistant."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.8,
            "max_tokens" => 400
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
        $result = curl_exec($ch);
        curl_close($ch);
    
        $response = json_decode($result, true);
    
        if(isset($response["choices"][0]["message"]["content"])) {
            return $response["choices"][0]["message"]["content"];
        } else {
            return "No advice generated. (API error)";
        }
    }*/
    
    
    // Set content type to JSON
    header('Content-Type: application/json');
    error_log("=== ENHANCED JOURNAL API DEBUG START ===");
    
    // ENHANCED DEBUGGING
    error_log("=== ENHANCED JOURNAL API DEBUG START ===");
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("Session ID exists: " . (isset($_SESSION['id']) ? 'YES' : 'NO'));
    error_log("Session ID value: " . ($_SESSION['id'] ?? 'NOT SET'));
    error_log("All session data: " . print_r($_SESSION, true));
    error_log("Database connection status: " . ($conn ? 'CONNECTED' : 'FAILED'));
    if (!$conn) {
        error_log("Database connection error: " . mysqli_connect_error());
    }
    $rawInput = file_get_contents('php://input');
    error_log("Raw input length: " . strlen($rawInput));
    error_log("Raw input preview: " . substr($rawInput, 0, 200) . "...");
    error_log("=== ENHANCED JOURNAL API DEBUG END ===");
    
    // Check if the connection was successful
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit;
    }
    
    // Check if the user is logged in
    if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
        http_response_code(401);
        error_log("AUTHENTICATION FAILED - Session ID not found or empty");
        echo json_encode([
            'error' => 'User not logged in', 
            'debug' => [
                'session_exists' => isset($_SESSION['id']),
                'session_empty' => empty($_SESSION['id']),
                'session_value' => $_SESSION['id'] ?? 'undefined'
            ]
        ]);
        exit;
    }
    
    $userID = $_SESSION['id'];
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'entries') {
                getEntries($conn, $userID);
            } elseif ($action === 'stats') {
                getStatistics($conn, $userID);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'POST':
        if ($action === 'save') {
            saveEntry($conn, $userID);
        } else if ($action === 'clear') {  // <-- add this for POST
            clearEntries($conn, $userID);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
    function saveEntry($conn, $userID) {
    try {
        ob_clean();

        error_log("=== SAVE ENTRY FUNCTION START ===");
        error_log("User ID: " . $userID);

        // Get JSON input
        $rawInput = file_get_contents('php://input');
        error_log("Raw input length: " . strlen($rawInput));

        $input = json_decode($rawInput, true);
        if (!$input) {
            error_log("JSON decode failed: " . json_last_error_msg());
            throw new Exception("Failed to decode input JSON: " . json_last_error_msg());
        }

        $productScore = $input['productScore'] ?? null;
        $productImage = $input['image'] ?? null;

        if (!$productScore) {
            throw new Exception("Missing required field: productScore");
        }

        // Generate ProductID
        $query = "SELECT MAX(CAST(SUBSTRING(ProductID, 2) AS UNSIGNED)) AS max_id FROM product_record";
        $result = $conn->query($query);

        if (!$result) {
            throw new Exception("Failed to get max ProductID: " . $conn->error);
        }

        $row = $result->fetch_assoc();
        $nextID = ($row['max_id'] ?? 0) + 1;
        $productID = "P" . $nextID;

        error_log("Generated ProductID: " . $productID);
        error_log("ProductScore: " . $productScore);
        error_log("ProductImage length: " . (strlen($productImage) ?? 'null'));

        $query = "INSERT INTO product_record (ProductID, UserID, ProductScore, DetectedAt, ProductImage) VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('ssss', $productID, $userID, $productScore, $productImage);

        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed: " . $stmt->error);
        }

        error_log("Insert successful. Affected rows: " . $stmt->affected_rows);

        $now = date('Y-m-d\TH:i:s');

        $newEntry = [
            'id' => $productID,
            'label' => generateLabel($productScore),
            'date' => $now,
            'score' => $productScore,
            'image' => $productImage
        ];

        echo json_encode(['success' => true, 'entry' => $newEntry]);
    } catch (Exception $e) {
        ob_clean();
        error_log("Error in saveEntry: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

    
    function getEntries($conn, $userID) {
        try {
            $query = "SELECT ProductID, ProductScore, DetectedAt, ProductImage FROM product_record WHERE UserID = ? ORDER BY DetectedAt DESC";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            
            $stmt->bind_param('s', $userID);
            
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $entries = [];
            
            while ($row = $result->fetch_assoc()) {
                // Convert DetectedAt to ISO 8601 format for JS compatibility
                $isoDate = date('Y-m-d\TH:i:s', strtotime($row['DetectedAt']));
                $entry = [
                    'id' => $row['ProductID'],
                    'label' => generateLabel($row['ProductScore']),
                    'date' => $isoDate, // always ISO format now!
                    'score' => $row['ProductScore'],
                    'image' => $row['ProductImage'] ?: generatePlaceholderImage($row['ProductScore']),
                    'advice' => generateAdvice($row['ProductScore'])
                ];
                $entries[] = $entry;
            }
            
            $stmt->close();
            echo json_encode(['success' => true, 'entries' => $entries]);
            
        } catch (Exception $e) {
            error_log("Error in getEntries: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch entries: ' . $e->getMessage()]);
        }
    }
    
    function clearEntries($conn, $userID) {
        try {
            $query = "DELETE FROM product_record WHERE UserID = ?";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            
            $stmt->bind_param('s', $userID);
            
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            echo json_encode(['success' => true, 'deleted' => $affectedRows]);
            
        } catch (Exception $e) {
            error_log("Error in clearEntries: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to clear entries: ' . $e->getMessage()]);
        }
    }
    
    function getStatistics($conn, $userID) {
        try {
            // Get total entries count
            $totalQuery = "SELECT COUNT(*) as total_entries FROM product_record WHERE UserID = ?";
            $totalStmt = $conn->prepare($totalQuery);
            $totalStmt->bind_param('s', $userID);
            $totalStmt->execute();
            $totalResult = $totalStmt->get_result();
            $totalRow = $totalResult->fetch_assoc();
            $totalEntries = $totalRow['total_entries'];
            $totalStmt->close();
    
            // Get healthy entries count (Score A or B, or numeric score >= 80)
            $healthyQuery = "SELECT COUNT(*) as healthy_entries FROM product_record 
                            WHERE UserID = ? AND (
                                ProductScore IN ('A', 'B') OR 
                                (ProductScore REGEXP '^[0-9]+$' AND CAST(ProductScore AS DECIMAL(5,2)) >= 80)
                            )";
            $healthyStmt = $conn->prepare($healthyQuery);
            $healthyStmt->bind_param('s', $userID);
            $healthyStmt->execute();
            $healthyResult = $healthyStmt->get_result();
            $healthyRow = $healthyResult->fetch_assoc();
            $healthyEntries = $healthyRow['healthy_entries'];
            $healthyStmt->close();
    
            // Calculate health score percentage
            $healthScore = $totalEntries > 0 ? round(($healthyEntries / $totalEntries) * 100) : 0;
    
            // Get number of healthy days (days with at least one healthy entry)
            $healthyDaysQuery = "SELECT COUNT(DISTINCT DATE(DetectedAt)) as healthy_days 
                                FROM product_record 
                                WHERE UserID = ? AND (
                                    ProductScore IN ('A', 'B') OR 
                                    (ProductScore REGEXP '^[0-9]+$' AND CAST(ProductScore AS DECIMAL(5,2)) >= 80)
                                )";
            $healthyDaysStmt = $conn->prepare($healthyDaysQuery);
            $healthyDaysStmt->bind_param('s', $userID);
            $healthyDaysStmt->execute();
            $healthyDaysResult = $healthyDaysStmt->get_result();
            $healthyDaysRow = $healthyDaysResult->fetch_assoc();
            $healthyStreak = $healthyDaysRow['healthy_days'];
            $healthyDaysStmt->close();
    
            $stats = [
                'healthScore' => $healthScore,
                'totalEntries' => $totalEntries,
                'healthyStreak' => $healthyStreak,
                'healthyEntries' => $healthyEntries
            ];
    
            echo json_encode(['success' => true, 'stats' => $stats]);
            
        } catch (Exception $e) {
            error_log("Error in getStatistics: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch statistics: ' . $e->getMessage()]);
        }
    }
    
    // Helper function to generate label from score
    function generateLabel($score) {
        $score = strtoupper(trim($score));
        
        switch($score) {
            case 'A':
                return "Excellent Choice - Score A";
            case 'B':
                return "Good Choice - Score B";
            case 'C':
                return "Average Choice - Score C";
            case 'D':
                return "Poor Choice - Score D";
            case 'F':
                return "Very Poor Choice - Score F";
            default:
                // Handle numeric scores (0-100)
                if (is_numeric($score)) {
                    $numScore = floatval($score);
                    if ($numScore >= 90) return "Excellent Choice - Score A";
                    elseif ($numScore >= 80) return "Good Choice - Score B";
                    elseif ($numScore >= 70) return "Average Choice - Score C";
                    elseif ($numScore >= 60) return "Poor Choice - Score D";
                    else return "Very Poor Choice - Score F";
                }
                return "Unknown Score - " . $score;
        }
    }
    
    // Helper function to generate placeholder image based on score
    function generatePlaceholderImage($score) {
        $score = strtoupper(trim($score));
        
        // Determine color and display text based on score
        $displayText = $score;
        if ($score === 'A' || (is_numeric($score) && floatval($score) >= 90)) {
            $color = '4CAF50'; // Green
            $displayText = is_numeric($score) ? $score : 'A';
        } elseif ($score === 'B' || (is_numeric($score) && floatval($score) >= 80)) {
            $color = '8BC34A'; // Light Green
            $displayText = is_numeric($score) ? $score : 'B';
        } elseif ($score === 'C' || (is_numeric($score) && floatval($score) >= 70)) {
            $color = 'FFC107'; // Yellow
            $displayText = is_numeric($score) ? $score : 'C';
        } elseif ($score === 'D' || (is_numeric($score) && floatval($score) >= 60)) {
            $color = 'FF9800'; // Orange
            $displayText = is_numeric($score) ? $score : 'D';
        } else {
            $color = 'F44336'; // Red
            $displayText = is_numeric($score) ? $score : 'F';
        }
        
        // Return a data URL for a colored square (SVG)
        return "data:image/svg+xml;base64," . base64_encode("
            <svg width='80' height='80' xmlns='http://www.w3.org/2000/svg'>
                <rect width='80' height='80' fill='#{$color}' rx='8'/>
                <text x='40' y='45' font-family='Arial' font-size='20' fill='white' text-anchor='middle' font-weight='bold'>{$displayText}</text>
            </svg>
        ");
    }
    
    // Helper function to generate advice based on score
    function generateAdvice($score) {
        $score = strtoupper(trim($score));
        
        if ($score === 'A' || (is_numeric($score) && floatval($score) >= 90)) {
            return "Excellent nutritional choice! This food is rich in essential nutrients and supports your health goals. Keep making choices like this to maintain optimal nutrition.";
        } elseif ($score === 'B' || (is_numeric($score) && floatval($score) >= 80)) {
            return "Good nutritional choice! This food provides valuable nutrients with minimal drawbacks. It's a solid addition to a balanced diet.";
        } elseif ($score === 'C' || (is_numeric($score) && floatval($score) >= 70)) {
            return "Average nutritional choice. This food has some beneficial nutrients but may also contain elements to consume in moderation. Consider pairing with more nutrient-dense options.";
        } elseif ($score === 'D' || (is_numeric($score) && floatval($score) >= 60)) {
            return "Poor nutritional choice. This food is low in essential nutrients and may be high in ingredients that should be limited. Try to choose more nutritious alternatives when possible.";
        } else {
            return "Very poor nutritional choice. This food offers minimal nutritional value and may contain high amounts of unhealthy ingredients. Consider replacing with more nutritious options for better health outcomes.";
        }
    }
    
    $conn->close();
    ?>
