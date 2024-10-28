<?php
header('Content-Type: application/json');

// Configuration
$api_key_file = __DIR__ . '/.openai_api_key';
$game_history_file = __DIR__ . '/.game_history';
$chat_url = 'https://api.openai.com/v1/chat/completions';
$debugging = false;

// Get the API key
function get_api_key($api_key_file) {
    if (file_exists($api_key_file)) {
        return trim(file_get_contents($api_key_file));
    }
    return null; // In a web context, you should handle this case appropriately
}

// System prompt
$system_prompt = "
You are an interactive text-based adventure game called 'The Quest of the Forgotten Realm'.
Your role is to guide the player through a fantasy world filled with magic, dragons, and ancient mysteries.
In each scene, provide a vivid and immersive description of the surroundings, including multiple emojis to enhance visualization.
You must ALWAYS provide exactly 4 options for the player to choose from. Each option should be a single sentence with 1-2 relevant emojis.
";

// Define the response format
$response_format = [
    "type" => "json_schema",
    "json_schema" => [
        "name" => "game_scene",
        "schema" => [
            "type" => "object",
            "properties" => [
                "narrative" => [
                    "type" => "string",
                    "description" => "The main story text describing the current scene, including emojis"
                ],
                "options" => [
                    "type" => "array",
                    "items" => [
                        "type" => "string",
                        "description" => "A single-sentence option with 1-2 emojis"
                    ]
                ],
                "image" => [
                    "type" => "string",
                    "description" => "A concise scene description for DALL·E 2 image generation"
                ]
            ],
            "required" => ["narrative", "options", "image"],
            "additionalProperties" => false
        ],
        "strict" => true
    ]
];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Handle image generation request
if (isset($input['action']) && $input['action'] === 'generate_image') {
    $scene = $input['scene'];
    $api_key = get_api_key($api_key_file);
    
    if (!$api_key) {
        http_response_code(500);
        echo json_encode(['error' => 'API key not configured']);
        exit;
    }

    // Generate image using DALL-E
    $image_prompt = "Low-res 8bit ANSI art: " . $scene['image'];
    $url = 'https://api.openai.com/v1/images/generations';
    $data = [
        'prompt' => $image_prompt,
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ]
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'DALL-E API error']);
        exit;
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['data'][0]['url'])) {
        $image_url = $result['data'][0]['url'];
        $image_data = file_get_contents($image_url);
        
        if ($image_data !== false) {
            $image_path = __DIR__ . '/images/temp_image.jpg';
            file_put_contents($image_path, $image_data);
            
            // Generate ASCII art
            $ascii_art = shell_exec("php " . escapeshellarg(__DIR__ . '/ascii_art_converter.php') . " " . escapeshellarg($image_path));
            
            echo json_encode(['ascii_art' => $ascii_art]);
            exit;
        }
    }

    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate image']);
    exit;
}

// Handle regular game request
$api_key = get_api_key($api_key_file);
if (!$api_key) {
    http_response_code(500);
    echo json_encode(['error' => 'API key not configured']);
    exit;
}

// Initialize or continue conversation
$conversation = [];
if (isset($input['history']) && is_array($input['history'])) {
    $conversation = $input['history'];
}

if (empty($conversation)) {
    $conversation[] = ['role' => 'system', 'content' => $system_prompt];
}

// Add user choice if provided
if (isset($input['choice'])) {
    $conversation[] = ['role' => 'user', 'content' => (string)$input['choice']];
}

// Make API request to GPT
$data = [
    'model' => 'gpt-4o-mini',
    'messages' => $conversation,
    'max_tokens' => 1000,
    'temperature' => 0.8,
    'response_format' => $response_format,
];

$ch = curl_init($chat_url);
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ]
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API error']);
    exit;
}
curl_close($ch);

$result = json_decode($response, true);
if (isset($result['choices'][0]['message']['content'])) {
    $scene_data = json_decode($result['choices'][0]['message']['content'], true);
    echo json_encode($scene_data);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from OpenAI API']);
}
?>