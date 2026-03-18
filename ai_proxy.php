<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$input = file_get_contents('php://input');
$ch = curl_init("https://openrouter.ai/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: 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",
    "HTTP-Referer: https://chopper.twopiz.com"
]);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode([
        "error" => "cURL failed",
        "curl_error" => $curl_error,
        "http_code" => $http_code
    ]);
    exit;
}

http_response_code($http_code);
echo $response;
?>
