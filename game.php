<?php
// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Replace 'YOUR_API_KEY' with your actual OpenAI API key
$api_key = 'YOUR_API_KEY';

// OpenAI API endpoint
$url = 'https://api.openai.com/v1/chat/completions';

// Initialize the conversation history
$conversation = [];

// System prompt to set up the adventure
$system_prompt = "You are an interactive text-based adventure game called 'The Quest of the Forgotten Realm'. 
Guide the player through a fantasy world filled with magic, dragons, and ancient mysteries. 
Describe scenes vividly, present choices when appropriate, and ask the player what they want to do next. 
Keep the storyline engaging and responsive to the player's actions.";

// Add the system prompt to the conversation
$conversation[] = ['role' => 'system', 'content' => $system_prompt];

echo "Welcome to 'The Quest of the Forgotten Realm'!\n(Type 'exit' or 'quit' to end the game at any time.)\n";

// Main game loop
while (true) {
    // Prepare the API request data
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $conversation,
        'max_tokens' => 200,
        'temperature' => 0.7,
    ];

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options for the API request
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Set HTTP headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ]);

    // Execute the API request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Request Error: ' . curl_error($ch) . "\n";
        break;
    }

    // Close cURL session
    curl_close($ch);

    // Decode the API response
    $result = json_decode($response, true);

    // Check if the response contains a message
    if (isset($result['choices'][0]['message']['content'])) {
        $reply = $result['choices'][0]['message']['content'];
        // Display the game narration
        echo "\n" . $reply . "\n";
        // Add the assistant's reply to the conversation
        $conversation[] = ['role' => 'assistant', 'content' => $reply];
    } else {
        // Handle API errors
        echo "Error in API response.\n";
        if (isset($result['error']['message'])) {
            echo "API Error Message: " . $result['error']['message'] . "\n";
        }
        break;
    }

    // Prompt the user for their action
    echo "\nYour action: ";
    $user_input = trim(fgets(STDIN));

    // Check for exit conditions
    if (strtolower($user_input) == 'exit' || strtolower($user_input) == 'quit') {
        echo "Thank you for playing 'The Quest of the Forgotten Realm'!\n";
        break;
    }

    // Add the user's input to the conversation
    $conversation[] = ['role' => 'user', 'content' => $user_input];
}

?>
