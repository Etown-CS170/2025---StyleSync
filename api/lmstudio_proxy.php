<?php
// api/lmstudio_proxy.php
//
// This endpoint proxies a text prompt to a locally-running LM Studio server
// using its OpenAI-compatible /v1/chat/completions API.
//
// Flow:
//  - Frontend (JS) sends POST JSON to this file:
//        { "prompt": "...", "album": "...", "notes": "..." }
//  - This script builds a single user message and sends it to LM Studio.
//  - It returns the model's reply as JSON: { ok: true, content: "..." }

// ---------- HTTP headers ----------
header('Content-Type: application/json');

// Allow local front-end calls during development
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'message' => 'CORS preflight']);
    exit;
}

// Only POST is allowed for real work
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok'    => false,
        'error' => 'Method not allowed. Use POST with JSON.'
    ]);
    exit;
}

// ---------- Read & validate JSON body ----------
//
// Expected JSON shape:
// {
//   "prompt": "Describe my fall poster campaign",
//   "album": "Fall_Posters_2025",
//   "notes": "Warm colors, cozy vibe, no people"
// }

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Invalid JSON body.'
    ]);
    exit;
}

if (empty($input['prompt'])) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Missing \"prompt\" field in JSON body.'
    ]);
    exit;
}

$prompt = $input['prompt'];
$album  = $input['album'] ?? null;
$notes  = $input['notes'] ?? null;

// Build a richer prompt for the model:
$userPrompt = $prompt;

if (!empty($album)) {
    $userPrompt .= "\n\nAlbum (portfolio folder): {$album}";
}

if (!empty($notes)) {
    $userPrompt .= "\n\nDesigner notes: {$notes}";
}

// ---------- LM Studio configuration ----------
//
// In LM Studio, start the local server (OpenAI-compatible):
//   - Developer / Local Server → Start
//   - or CLI: lms server start --port 1234
//
// That exposes: http://127.0.0.1:1234/v1/chat/completions
//
$lmstudioUrl = 'http://127.0.0.1:1234/v1/chat/completions';

// IMPORTANT: Change this to match the model name you loaded in LM Studio.
// You’ll see the model ID at the top of the Local Server panel,
// e.g. "lmstudio-community/Meta-Llama-3-8B-Instruct" or similar.
$modelName = 'lmstudio-community/Meta-Llama-3-8B-Instruct';

// OpenAI-style payload
$payload = [
    'model'    => $modelName,
    'messages' => [
        [
            'role'    => 'system',
            'content' =>
                "You are a design assistant inside a tool called StyleSync. " .
                "Given album names (folders of reference designs) and short " .
                "designer notes, your job is to:\n" .
                "  1) Suggest style directions and campaign concepts.\n" .
                "  2) Produce high-quality image-to-image prompts that can be " .
                "     used by a Stable Diffusion image model.\n" .
                "Always return clear bullet points or numbered lists."
        ],
        [
            'role'    => 'user',
            'content' => $userPrompt
        ],
    ],
    'temperature' => 0.6,
];

// ---------- Send request to LM Studio ----------
$jsonPayload = json_encode($payload);

$ch = curl_init($lmstudioUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $jsonPayload,
]);

$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error calling LM Studio: ' . $err,
    ]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'LM Studio returned HTTP ' . $httpCode,
        'raw'   => $response,
    ]);
    exit;
}

// ---------- Parse LM Studio response ----------
$data = json_decode($response, true);

if (!isset($data['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Unexpected LM Studio response format.',
        'raw'   => $response,
    ]);
    exit;
}

$modelText = $data['choices'][0]['message']['content'];

// ---------- Return clean JSON to the front-end ----------
echo json_encode([
    'ok'      => true,
    'content' => $modelText,
    'debug'   => [
        'model'      => $modelName,
        'statusCode' => $httpCode,
    ],
]);
