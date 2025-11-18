<?php
// TEMP DEBUG (you can leave this on while developing)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Allow this script to run longer than the default 30–120s
set_time_limit(0); // 600 seconds = 10 minutes; or use 0 for unlimited

header("Content-Type: application/json");

// Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Use POST"]);
    exit;
}

// Validate incoming fields
if (!isset($_FILES["image"]) || !isset($_POST["prompt"])) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Missing image or prompt"]);
    exit;
}

$prompt   = (string)$_POST["prompt"];
$strength = isset($_POST["strength"]) ? (float)$_POST["strength"] : 0.6;
$guidance = isset($_POST["guidance_scale"]) ? (float)$_POST["guidance_scale"] : 7.5;

$tmp  = $_FILES["image"]["tmp_name"];
$type = $_FILES["image"]["type"] ?? "image/png";
$name = $_FILES["image"]["name"] ?? "input.png";

// Basic sanity on file
if (!is_uploaded_file($tmp)) {
    echo json_encode(["ok" => false, "error" => "Uploaded file missing or invalid tmp_name"]);
    exit;
}

// Make sure curl & CURLFile exist
if (!function_exists('curl_init')) {
    echo json_encode(["ok" => false, "error" => "cURL extension not enabled in PHP."]);
    exit;
}
if (!class_exists('CURLFile')) {
    echo json_encode(["ok" => false, "error" => "CURLFile class not available in this PHP build."]);
    exit;
}

// Build CURLFile
$fileField = new CURLFile($tmp, $type, $name);

// Call Python img2img server
$ch = curl_init("http://127.0.0.1:8001/img2img");

$postFields = [
    "prompt"         => $prompt,
    "strength"       => $strength,
    "guidance_scale" => $guidance,
    "image"          => $fileField,
];

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_RETURNTRANSFER => true,
]);

$response    = curl_exec($ch);
$curlErr     = curl_error($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Handle curl failure
if ($response === false) {
    echo json_encode([
        "ok"        => false,
        "error"     => "Error calling img2img server: " . $curlErr,
        "http_code" => $httpCode,
    ]);
    exit;
}

// If Python responded with an error or non-image
if ($httpCode >= 400 || strpos((string)$contentType, "image/") !== 0) {
    echo json_encode([
        "ok"          => false,
        "error"       => "Python server error or non-image response.",
        "http_code"   => $httpCode,
        "contentType" => $contentType,
        "raw"         => $response,
    ]);
    exit;
}

// At this point $response should be image bytes
$uploadDir = __DIR__ . "/../uploads/generated";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$filename = "gen_" . time() . "_" . bin2hex(random_bytes(4)) . ".png";
$fullPath = $uploadDir . "/" . $filename;

file_put_contents($fullPath, $response);

// Web path
$publicPath = "uploads/generated/" . $filename;

echo json_encode([
    "ok"           => true,
    "image_url"    => $publicPath,
    "prompt"       => $prompt,
    "strength"     => $strength,
    "guidance"     => $guidance,
    "http_code"    => $httpCode,
    "content_type" => $contentType,
]);
