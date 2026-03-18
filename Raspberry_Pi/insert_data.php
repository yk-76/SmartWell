<?php
// insert_data.php - Pi-side PHP for inserting health_record

$host = "103.6.245.103";
$username = "wilsandb_smartwell";
$password = "0k;j~dc!^NUf";
$dbname = "wilsandb_fyp";
$port = '3306';

// Connect to the database
$conn = new mysqli($host, $username, $password, $dbname, $port);

file_put_contents('/tmp/insert_data_debug.log', "Connected DB: " . $conn->host_info . "\n", FILE_APPEND);

$res = $conn->query("SELECT DATABASE() AS db, @@hostname AS servername");
if ($res) {
    $row = $res->fetch_assoc();
    file_put_contents('/tmp/insert_data_debug.log', "Connected to DB: " . $row['db'] . ", on server: " . $row['servername'] . "\n", FILE_APPEND);
}


// Check connection
if ($conn->connect_error) {
    file_put_contents('/tmp/insert_data_debug.log', "DB Connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    die("Connection failed: " . $conn->connect_error);
}

// Read from environment variables
$userID    = getenv('USER_ID');
$riskLevel = getenv('RISK_LEVEL');
$detectedAt = getenv('DETECTED_AT');

if (!$userID || !$riskLevel || !$detectedAt) {
    file_put_contents('/tmp/insert_data_debug.log', "Error: Missing required environment variables (USER_ID, RISK_LEVEL, DETECTED_AT)\n", FILE_APPEND);
    die("Error: Missing required environment variables (USER_ID, RISK_LEVEL, DETECTED_AT)");
}

$success = false;
$max_attempts = 5;
$attempt = 0;

while (!$success && $attempt < $max_attempts) {
    // Generate next RecordID
    $sql = "SELECT RecordID FROM health_record ORDER BY RecordID DESC LIMIT 1";
    $result = $conn->query($sql);
    $RecordID = "R001";
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastID = $row['RecordID'];
        $num = intval(substr($lastID, 1)) + 1;
        $RecordID = 'R' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    // Prepare statement
    $stmt = $conn->prepare("INSERT INTO health_record (RecordID, UserID, riskLevel, DetectedAt) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        file_put_contents('/tmp/insert_data_debug.log', "Error preparing statement: " . $conn->error . "\n", FILE_APPEND);
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("ssss", $RecordID, $userID, $riskLevel, $detectedAt);

    file_put_contents('/tmp/insert_data_debug.log', "About to insert: RecordID=$RecordID, UserID=$userID, riskLevel=$riskLevel, DetectedAt=$detectedAt\n", FILE_APPEND);

    if ($stmt->execute()) {
        $success = true;
        file_put_contents('/tmp/insert_data_debug.log', "Insert SUCCESS: $RecordID\n", FILE_APPEND);
        echo "Success - Record inserted with ID: " . $RecordID;
    } else {
        // If duplicate, try next
        file_put_contents('/tmp/insert_data_debug.log', "Insert FAILED: " . $stmt->error . "\n", FILE_APPEND);
        if (strpos($stmt->error, "Duplicate entry") !== false) {
            $attempt++;
            usleep(50000);
            $stmt->close();
            continue;
        } else {
            echo "Error: " . $stmt->error;
            $stmt->close();
            break;
        }
    }
    $stmt->close();
}

$conn->close();

if (!$success) {
    file_put_contents('/tmp/insert_data_debug.log', "Error: Could not insert new record after $max_attempts attempts.\n", FILE_APPEND);
    die("Error: Could not insert new record after $max_attempts attempts.");
}
?>
