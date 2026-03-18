<?php
date_default_timezone_set('Asia/Kuala_Lumpur');  

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth_helper.php';
start_secure_session();

$riskLevel  = isset($_POST['riskLevel'])  ? $_POST['riskLevel']  : null;
$detectedAt = date('Y-m-d H:i:s');


if ($riskLevel) {
    $riskLevel = strtoupper($riskLevel);
}

$userID = get_user_id();

$log  = date('Y-m-d H:i:s') . " - Request received\n";
$log .= date('Y-m-d H:i:s') . " - UserID from session: " . var_export($userID, true) . "\n";
$log .= date('Y-m-d H:i:s') . " - Risk Level: "          . var_export($riskLevel, true) . "\n";
$log .= date('Y-m-d H:i:s') . " - Detected At: "         . var_export($detectedAt, true) . "\n";
$log .= date('Y-m-d H:i:s') . " - SESSION: "             . var_export($_SESSION, true) . "\n";
file_put_contents('stroke_save_debug.log', $log, FILE_APPEND);

// Connect to the database
require_once 'config.php';
file_put_contents('stroke_save_debug.log', date('Y-m-d H:i:s')." - Database connected.\n", FILE_APPEND);
// (Ensure config.php defines and connects $conn = new mysqli(...);)

// Check if user is logged in
if (! $userID) {
    $response = [
        'status'  => 'error',
        'message' => 'User not logged in. Cannot save data.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Validate inputs
if (! $riskLevel) {
    $response = [
        'status'  => 'error',
        'message' => 'Risk level is required'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    // Get highest existing RecordID number
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(RecordID, 2) AS UNSIGNED)) AS max_id FROM health_record");
    if (! $result) {
        throw new Exception("Error getting max RecordID: " . $conn->error);
    }
    $row    = $result->fetch_assoc();
    $nextId = 1;
    if ($row && $row['max_id'] !== null) {
        $nextId = intval($row['max_id']) + 1;
    }

    // Generate unique RecordID (if somehow duplicated, increment until unused)
    do {
        $recordID = sprintf('R%03d', $nextId);
        $checkResult = $conn->query("SELECT 1 FROM health_record WHERE RecordID = '$recordID' LIMIT 1");
        if (!$checkResult) throw new Exception("Error checking RecordID: " . $conn->error);
        $exists = $checkResult->num_rows > 0;
        if ($exists) $nextId++;
    } while ($exists);

    file_put_contents('stroke_save_debug.log',
        date('Y-m-d H:i:s') . " - Generated RecordID: {$recordID}\n",
        FILE_APPEND
    );

    $stmt = $conn->prepare("
        INSERT INTO health_record
            (RecordID, UserID, riskLevel, DetectedAt)
        VALUES (?, ?, ?, ?)
    ");
    if (! $stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("ssss", $recordID, $userID, $riskLevel, $detectedAt);

    file_put_contents('stroke_save_debug.log',
        date('Y-m-d H:i:s')
        . " - Inserting: RecordID={$recordID}, UserID={$userID}, riskLevel={$riskLevel}, DetectedAt={$detectedAt}\n",
        FILE_APPEND
    );

    if (! $stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }

    $response = [
        'status'    => 'success',
        'message'   => 'Health record saved successfully',
        'record_id' => $recordID,
        'user_id'   => $userID,
        'data'      => [
            'risk_level'  => $riskLevel,
            'detected_at' => $detectedAt
        ]
    ];
    file_put_contents('stroke_save_debug.log',
        date('Y-m-d H:i:s') . " - Save successful\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    $response = [
        'status'  => 'error',
        'message' => $e->getMessage()
    ];
    file_put_contents('stroke_save_debug.log',
        date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}

// Register shutdown function for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL) {
        $log = date('Y-m-d H:i:s') . " - Fatal Error: " . print_r($error, true) . "\n";
        file_put_contents('stroke_save_debug.log', $log, FILE_APPEND);
    }
});

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
