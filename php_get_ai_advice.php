<?php
header("Content-Type: application/json");

$hf_token = getenv('HF_TOKEN');
$model_id = getenv('HF_MODEL_ID');

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);
$label = $input["label"] ?? "Unknown";

// Compose prompt
$prompt = "A user scanned a food labeled '{$label}'.\n"
        . "1. Is this healthy or unhealthy? Explain.\n"
        . "2. Mention stroke risk (if any).\n"
        . "3. Suggest healthier alternatives.\n"
        . "4. Make all the answers short.";
$data = ["inputs" => $prompt];

$data = [
    "inputs" => "<|system|>You are a helpful food and health assistant.<|end|><|user|>{$prompt}<|end|>"
];

$ch = curl_init("https://api-inference.huggingface.co/models/{$model_id}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $hf_token",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);

// 🟡 Check for cURL error **immediately**
if ($response === false) {
    file_put_contents('hf_debug.txt', curl_error($ch));
    echo json_encode(["error" => curl_error($ch)]);
    exit;
}

// 🟢 Only continue if cURL succeeded!
$decoded = json_decode($response, true);
file_put_contents('hf_debug.txt', $response);

$output = null;
if (isset($decoded[0]["generated_text"])) {
    $outText = $decoded[0]["generated_text"];
    $parts = explode('<|assistant|>', $outText);
    $output = trim(end($parts));
}
echo json_encode(["advice" => $output ?? "No advice available."]);
