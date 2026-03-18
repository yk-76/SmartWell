<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuration from Environment Variables
$api_key = getenv('OPENROUTER_API_KEY');
$referer = getenv('OPENROUTER_REFERER') ?: "https://your-app-url.com";
$api_url = getenv('OPENROUTER_API_URL') ?: "https://openrouter.ai/v1/chat/completions";

$input = file_get_contents('php://input');
$ch = curl_init($api_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $input,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $api_key",
        "HTTP-Referer: $referer"
    ],
    CURLOPT_SSL_VERIFYPEER => false 
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode([
        "success" => false,
        "error" => "cURL failed",
        "curl_error" => $curl_error,
        "http_code" => $http_code
    ]);
    exit;
}

http_response_code($http_code);
echo $response;
?>
