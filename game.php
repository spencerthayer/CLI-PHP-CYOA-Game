<?php
// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Paths to store the API key and game history securely
$api_key_file = __DIR__ . '/.openai_api_key';
$game_history_file = __DIR__ . '/.game_history';
$debugging = in_array('--debug', $argv);
$chat_url = 'https://api.openai.com/v1/chat/completions';

// 1. Function to create a new game by checking for the '--new' flag
function check_for_new_game($argv, $game_history_file) {
    if (in_array('--new', $argv)) {
        if (file_exists($game_history_file)) {
            unlink($game_history_file);
            echo "Game history cleared. Starting a new game...\n";
        } else {
            echo "No game history found. Starting a new game...\n";
        }
    }
}
check_for_new_game($argv, $game_history_file);

// 2. Function to get the API key
function get_api_key($api_key_file) {
    if (file_exists($api_key_file)) {
        $api_key = trim(file_get_contents($api_key_file));
        if (!empty($api_key)) {
            return $api_key;
        }
    }
    echo "Please enter your OpenAI API key: ";
    $api_key = trim(fgets(STDIN));
    file_put_contents($api_key_file, $api_key, LOCK_EX);
    chmod($api_key_file, 0600);
    return $api_key;
}

// 3. Function to apply ANSI color codes
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
        '[cyan]' => "\033[36m",
        '[/cyan]' => "\033[0m",
        '[bold]' => "\033[1m",
        '[/bold]' => "\033[22m",
    ];
    return str_replace(array_keys($color_codes), array_values($color_codes), $text);
}

// 4. Function to generate ASCII art from an image
function generate_ascii_art($image_path) {
    global $debugging;
    if ($debugging) {
        echo "[DEBUG] Generating ASCII art from image at: $image_path\n";
    }
    $script_path = __DIR__ . '/ascii_art_converter.php';
    $output = shell_exec("php " . escapeshellarg($script_path) . " " . escapeshellarg($image_path));
    return $output;
}

// 5. Function to generate an image using DALL·E 2
function generate_image($prompt, $api_key) {
    global $debugging;
    $url = 'https://api.openai.com/v1/images/generations';
    $data = [
        'prompt' => $prompt,
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url'
    ];
    if ($debugging) {
        echo "[DEBUG] Sending request to DALL·E 2 API with prompt: $prompt\n";
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'DALL·E Request Error: ' . curl_error($ch) . "\n";
        return null;
    }
    curl_close($ch);
    if ($debugging) {
        echo "[DEBUG] Response from DALL·E 2 API: $response\n";
    }
    $result = json_decode($response, true);
    if (isset($result['data'][0]['url'])) {
        return $result['data'][0]['url'];
    } else {
        echo "Error generating image with DALL·E.\n";
        if (isset($result['error']['message'])) {
            echo "DALL·E Error Message: " . $result['error']['message'] . "\n";
        }
        return null;
    }
}

// 6. Debugging output function
function debug_log($message) {
    global $debugging;
    if ($debugging) {
        echo "[DEBUG] " . $message . "\n";
    }
}

// 7. Function to generate an image from the assistant's text
function process_image_from_text($text, $api_key) {
    global $debugging;
    $prompt = generate_image_prompt_summary($text);
    if ($debugging) {
        echo "[DEBUG] Attempting to generate an image with prompt: '$prompt'\n";
    }
    $image_url = generate_image($prompt, $api_key);
    if ($image_url) {
        if ($debugging) {
            echo "[DEBUG] Image generated successfully. URL: $image_url\n";
        }
        $image_data = file_get_contents($image_url);
        if ($image_data === false) {
            echo "Error downloading image from URL.\n";
            return "";
        }
        $image_path = __DIR__ . '/images/temp_image.jpg';
        file_put_contents($image_path, $image_data);
        $ascii_art = generate_ascii_art($image_path);
        return $ascii_art;
    } else {
        if ($debugging) {
            echo "[DEBUG] Failed to generate image from text.\n";
        }
        return "";
    }
}

// 8. Function to generate an image prompt from the assistant's text
function generate_image_prompt_summary($text) {
    $sentences = explode('.', $text);
    $prompt = implode('. ', array_slice($sentences, 0, 2));
    return trim($prompt);
}

// 9. Function to process the scene and generate image
function process_scene($scene_data, $api_key) {
    global $debugging;
    echo "\n" . colorize($scene_data->narrative) . "\n\n";
    
    echo colorize("\n[bold]Choose your next action:[/bold]\n");
    foreach ($scene_data->options as $index => $option) {
        $number = $index + 1;
        echo colorize("[cyan]{$number}. {$option}[/cyan]\n");
    }
    
    // Add additional options
    echo "\n"; // Add spacing
    echo colorize("[cyan](g) Generate an image of this scene[/cyan]\n");
    echo colorize("[cyan](q) Quit the game[/cyan]\n");
    echo colorize("[cyan](n) Start a new game[/cyan]\n");
}

// 10. Updated system prompt
$system_prompt = "
You are an interactive text-based adventure game called 'The Quest of the Forgotten Realm'.

Your role is to guide the player through a fantasy world filled with magic, dragons, and ancient mysteries.

In each scene, provide a vivid and immersive description of the surroundings, including multiple emojis to enhance visualization.

You must ALWAYS provide exactly 4 options for the player to choose from. Each option should be a single sentence with 1-2 relevant emojis.
";

// 11. Define the structured output format without unsupported validations
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

// 12. Get the API key
$api_key = get_api_key($api_key_file);

// 13. Initialize the conversation history
$conversation = [];

// 14. Load the conversation history or start a new game
if (file_exists($game_history_file)) {
    $history_content = file_get_contents($game_history_file);
    if ($history_content === false) {
        echo "Error: Unable to read the game history file.\n";
        exit(1);
    }
    $saved_conversation = json_decode($history_content, true);
    if ($saved_conversation && is_array($saved_conversation) && count($saved_conversation) > 0) {
        $conversation = $saved_conversation;
        $last_assistant_message = null;
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            if ($conversation[$i]['role'] === 'assistant') {
                $last_assistant_message = $conversation[$i]['content'];
                break;
            }
        }
        if ($last_assistant_message) {
            echo colorize("[bold][green]Welcome back to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
            echo "(Type 'exit' or 'quit' to end the game at any time.)\n";
            echo colorize("\n[bold][yellow]You return to your adventure...[/yellow][/bold]\n");
            $scene_data = json_decode($last_assistant_message);
            if ($scene_data && isset($scene_data->narrative, $scene_data->options, $scene_data->image)) {
                process_scene($scene_data, $api_key);
                $skip_api_call = true;
            } else {
                echo colorize("[red]Error: Corrupted game history. Starting a new game.[/red]\n");
                $conversation = [];
                $conversation[] = ['role' => 'system', 'content' => $system_prompt];
                debug_log("Corrupted game history. Starting new conversation.");
            }
        }
    } else {
        echo colorize("[bold][green]Starting a new adventure in 'The Quest of the Forgotten Realm'![/green][/bold]\n");
        echo "(Type 'exit' or 'quit' to end the game at any time.)\n";
        $conversation = [];
        $conversation[] = ['role' => 'system', 'content' => $system_prompt];
    }
} else {
    echo colorize("[bold][green]Welcome to 'The Quest of the Forgotten Realm'![/green][/bold]\n");
    echo "(Type 'exit' or 'quit' to end the game at any time.)\n";
    $conversation[] = ['role' => 'system', 'content' => $system_prompt];
}

// 15. Ensure the images directory exists
$images_dir = __DIR__ . '/images';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}
function removeImageAndDirectory() {
    // Define the file and directory paths
    $image_path = __DIR__ . '/images/temp_image.jpg';
    $directory_path = __DIR__ . '/images';

    // Check if the image file exists and delete it if it does
    if (file_exists($image_path)) {
        // Unlink (delete) the image file
        if (unlink($image_path)) {
            // echo "File '$image_path' deleted successfully.\n";
        } else {
            // echo "Failed to delete the file '$image_path'.\n";
            return; // Stop if the file could not be deleted
        }
    }

    // Check if the directory is empty and exists
    if (is_dir($directory_path)) {
        // Check if the directory is empty before attempting to remove it
        if (count(scandir($directory_path)) === 2) { // Only '.' and '..' entries exist
            if (rmdir($directory_path)) {
                // echo "Directory '$directory_path' removed successfully.\n";
            } else {
                // echo "Failed to remove the directory '$directory_path'.\n";
            }
        } else {
            // echo "Directory '$directory_path' is not empty and cannot be removed.\n";
        }
    }
}

// 16. Main game loop
$max_iterations = 1000; // Prevent infinite loops
$current_iteration = 0;

// Initialize scene_data for image generation
$scene_data = null;
$should_make_api_call = true;

while (true) {
    if ($current_iteration++ >= $max_iterations) {
        echo "Reached maximum number of iterations. Exiting.\n";
        break;
    }

    if ($should_make_api_call && !isset($skip_api_call)) {
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => $conversation,
            'max_tokens' => 1000,
            'temperature' => 0.8,
            'response_format' => $response_format,
        ];

        debug_log("Sending request to GPT-4o-mini: " . json_encode($data));

        // Make the API call
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
            echo 'Request Error: ' . curl_error($ch) . "\n";
            break;
        }
        curl_close($ch);
        $result = json_decode($response);
        if (isset($result->choices[0]->message->content)) {
            $scene_data = json_decode($result->choices[0]->message->content);
            if ($scene_data && isset($scene_data->narrative, $scene_data->options, $scene_data->image)) {
                process_scene($scene_data, $api_key);
                // Save to conversation history
                $conversation[] = [
                    'role' => 'assistant',
                    'content' => $result->choices[0]->message->content
                ];
                $write_success = file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
                if ($write_success === false) {
                    echo "Error: Unable to write to the game history file.\n";
                    exit(1);
                }
                chmod($game_history_file, 0600);
                debug_log("Updated conversation after assistant reply: " . json_encode($conversation));
            } else {
                echo colorize("[red]Error: Received improperly formatted scene data from the assistant.[/red]\n");
                break;
            }
        } else {
            echo colorize("[red]Error in API response.\n");
            if (isset($result->error->message)) {
                echo "API Error Message: " . $result->error->message . "\n";
            }
            break;
        }
    } else {
        debug_log("Skipping API call as per flag.");
        unset($skip_api_call);
    }

    // Handle user input
    echo colorize("\n[cyan]Your choice (1-4, 'g' for image, 'q' to quit, 'n' for new game): [/cyan]");
    $user_input = trim(fgets(STDIN));
    debug_log("User input received: $user_input");

    // Reset API call flag
    $should_make_api_call = false;

    // Handle special commands first
    if (strtolower($user_input) == 'q') {
        echo colorize("\n[bold][yellow]Thank you for playing 'The Quest of the Forgotten Realm'![/yellow][/bold]\n");
        removeImageAndDirectory();
        break;
    }

    if (strtolower($user_input) == 'n') {
        echo colorize("\n[bold][yellow]Starting a new game...[/yellow][/bold]\n");
        $conversation = [];
        $conversation[] = ['role' => 'system', 'content' => $system_prompt];
        // Remove existing game history
        if (file_exists($game_history_file)) {
            unlink($game_history_file);
            debug_log("Game history file deleted for new game.");
        }
        $should_make_api_call = true;
        continue;
    }

    if (strtolower($user_input) == 'g') {
        if ($scene_data) {
            echo colorize("\n[bold][yellow]Generating image for the current scene...[/yellow][/bold]\n");
            $image_prompt = "Low-res 8bit ANSI art: " . $scene_data->image;
            $ascii_art = process_image_from_text($image_prompt, $api_key);
            if (!empty($ascii_art)) {
                echo "\n" . $ascii_art . "\n\n";
                // Redisplay the current scene narrative and options
                process_scene($scene_data, $api_key);
            }
        } else {
            echo colorize("[red]No scene data available to generate an image.[/red]\n");
        }
        continue;
    }

    // Handle regular number inputs
    if (!preg_match('/^[1-4]$/', $user_input)) {
        echo colorize("[red]Invalid input. Please enter a number between 1-4, 'g' for image, 'q' to quit, or 'n' for new game.[/red]\n");
        // Redisplay the current scene
        if ($scene_data) {
            process_scene($scene_data, $api_key);
        }
        continue;
    }

    // Process valid number input
    $conversation[] = ['role' => 'user', 'content' => $user_input];
    debug_log("Updated conversation after user input: " . json_encode($conversation));
    $should_make_api_call = true;

    // Save the updated conversation history
    $write_success = file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
    if ($write_success === false) {
        echo "Error: Unable to write to the game history file.\n";
        exit(1);
    }
    chmod($game_history_file, 0600);
}
?>
