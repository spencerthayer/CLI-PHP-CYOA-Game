<?php
// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Paths to store the API key and game history securely
$api_key_file = __DIR__ . '/.openai_api_key';
$game_history_file = __DIR__ . '/.game_history';

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

// Function to apply ANSI color codes
function colorize($text) {
    $color_codes = [
        '[red]' => "\033[31m",
        '[/red]' => "\033[0m",
        '[green]' => "\033[32m",
        '[/green]' => "\033[0m",
        '[yellow]' => "\033[33m",
        '[/yellow]' => "\033[0m",
        '[blue]' => "\033[34m",
        '[/blue]' => "\033[0m",
        '[magenta]' => "\033[35m",
        '[/magenta]' => "\033[0m",
        '[cyan]' => "\033[36m",
        '[/cyan]' => "\033[0m",
        '[white]' => "\033[37m",
        '[/white]' => "\033[0m",
        '[reset]' => "\033[0m",
        '[bold]' => "\033[1m",
        '[/bold]' => "\033[22m",
    ];

    return str_replace(array_keys($color_codes), array_values($color_codes), $text);
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
In each scene, provide a vivid and immersive description of the surroundings, incorporating basic ASCII art and multiple emojis to represent key elements like landscapes, items, or creatures.
At the end of each scene, present exactly four options for the player to choose from, phrased as actions they can take.
After listing the options, prompt the player to select one.
Ensure that the storyline is engaging and that the player's choices have meaningful impacts on the adventure.";

$system_prompt = "Always remember to include multiple emojis throughout the descriptions to enhance the visualizations and wrap colored text appropriately. Use simple terminal colors by wrapping text with color tags like [green]...[/green], [bold]...[/bold], etc., and reset the color or style with [/color] or [/bold].";

// Add the system prompt to the conversation if starting a new game
$skip_api_call = false;

if (file_exists($game_history_file)) {
    // Load the conversation history from the file
    $saved_conversation = json_decode(file_get_contents($game_history_file), true);
    if ($saved_conversation) {
        $conversation = $saved_conversation;

        // Get the last assistant's message
        $last_assistant_message = null;
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            if ($conversation[$i]['role'] === 'assistant') {
                $last_assistant_message = $conversation[$i]['content'];
                break;
            }
        }

        if ($last_assistant_message) {
            // Welcome back message
            echo colorize("[bold][green]Welcome back to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
            echo "(Type 'exit' or 'quit' to end the game at any time.)\n";

            echo colorize("\n[bold][yellow]You return to your adventure...[/yellow][/bold]\n");
            echo colorize($last_assistant_message);

            $skip_api_call = true; // Skip API call on first loop iteration
        } else {
            // If no assistant message found, start a new game
            echo colorize("[bold][green]Welcome to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
            echo "(Type 'exit' or 'quit' to end the game at any time.)\n";

            $conversation = [];
            $conversation[] = ['role' => 'system', 'content' => $system_prompt];
        }
    } else {
        // If the file is empty or corrupted, start a new game
        echo colorize("[bold][green]Welcome to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
        echo "(Type 'exit' or 'quit' to end the game at any time.)\n";

        $conversation = [];
        $conversation[] = ['role' => 'system', 'content' => $system_prompt];
    }
} else {
    echo colorize("[bold][green]Welcome to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
    echo "(Type 'exit' or 'quit' to end the game at any time.)\n";

    $conversation[] = ['role' => 'system', 'content' => $system_prompt];
}

// Main game loop
while (true) {
    if (!$skip_api_call) {
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => $conversation,
            'max_tokens' => 1000,
            'temperature' => 0.8,
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

            // Apply colorization to the reply
            $reply_colored = colorize($reply);

            // Display the game narration
            echo "\n" . $reply_colored . "\n";

            // Add the assistant's reply to the conversation
            $conversation[] = ['role' => 'assistant', 'content' => $reply];

            // Save the conversation history to the file
            file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
            // Set file permissions to be readable and writable only by the owner
            chmod($game_history_file, 0600);
        } else {
            // Handle API errors
            echo "Error in API response.\n";
            if (isset($result['error']['message'])) {
                echo "API Error Message: " . $result['error']['message'] . "\n";
            }
            break;
        }
    } else {
        $skip_api_call = false; // Reset the flag after first iteration
    }

    // Prompt the user for their action
    echo colorize("\n[cyan]Your action (enter the number of your choice): [/cyan]");
    $user_input = trim(fgets(STDIN));

    // Check for exit conditions
    if (strtolower($user_input) == 'exit' || strtolower($user_input) == 'quit') {
        echo colorize("\n[bold][yellow]Thank you for playing 'The Quest of the Forgotten Realm'![/yellow][/bold]\n");
        // Optionally, delete the game history file to reset the game
        // unlink($game_history_file);
        break;
    }

    // Add the user's input to the conversation
    $conversation[] = ['role' => 'user', 'content' => $user_input];

    // Save the conversation history to the file
    file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
    // Set file permissions to be readable and writable only by the owner
    chmod($game_history_file, 0600);
}
?>
