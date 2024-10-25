<?php
// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Path to store the API key securely
$api_key_file = __DIR__ . '/.openai_api_key';

// Function to get the API key
function get_api_key($api_key_file) {
    if (file_exists($api_key_file)) {
        // Read the API key from the file
        $api_key = trim(file_get_contents($api_key_file));
        if (!empty($api_key)) {
            return $api_key;
        }
    }
    // Prompt the user for the API key
    echo "Please enter your OpenAI API key: ";
    $api_key = trim(fgets(STDIN));

    // Save the API key to the file
    file_put_contents($api_key_file, $api_key, LOCK_EX);
    // Set file permissions to be readable and writable only by the owner
    chmod($api_key_file, 0600);

    return $api_key;
}

// Get the API key
$api_key = get_api_key($api_key_file);

// OpenAI API endpoint
$url = 'https://api.openai.com/v1/chat/completions';

// Initialize the conversation history
$conversation = [];

// System prompt to set up the adventure
$system_prompt = "You are an interactive text-based adventure game called 'The Quest of the Forgotten Realm'. 
Your role is to guide the player through a fantasy world filled with magic, dragons, and ancient mysteries. 
In each scene, provide a vivid and immersive description of the surroundings, incorporating basic ASCII art to represent key elements like landscapes, items, or creatures.
At the end of each scene, present exactly four options for the player to choose from, phrased as actions they can take.
After listing the options, prompt the player to select one.
Ensure that the storyline is engaging and that the player's choices have meaningful impacts on the adventure.";

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
