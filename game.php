<?php
// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Paths to store the API key and game history securely
$api_key_file = __DIR__ . '/.openai_api_key';
$game_history_file = __DIR__ . '/.game_history';
$debugging = true;

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

// Function to generate ASCII art from an image
function generate_ascii_art($image_path) {
    global $debugging;
    if ($debugging) {
        echo "[DEBUG] Generating ASCII art from image at: $image_path\n";
    }
    // Path to the ASCII art converter script
    $script_path = __DIR__ . '/ascii_art_converter.php';
    // Execute the script and capture the output
    $output = shell_exec("php $script_path " . escapeshellarg($image_path));
    return $output;
}

// Function to generate an image using DALL·E 2
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

// Add debugging output function
function debug($message) {
    global $debugging;
    if ($debugging) {
        echo "[DEBUG] " . $message . "\n";
    }
}

// Get the API key
$api_key = get_api_key($api_key_file);

// OpenAI API endpoint for chat completions
$chat_url = 'https://api.openai.com/v1/chat/completions';

// Initialize the conversation history
$conversation = [];

// System prompt without any mention of image generation
$system_prompt = "You are an interactive text-based adventure game called 'The Quest of the Forgotten Realm'.

Your role is to guide the player through a fantasy world filled with magic, dragons, and ancient mysteries.

In each scene, provide a vivid and immersive description of the surroundings, including multiple emojis to enhance visualization.

At the end of each scene, present exactly four options for the player to choose from, phrased as actions they can take.

After listing the options, prompt the player to select one.

Ensure that the storyline is engaging and that the player's choices have meaningful impacts on the adventure.";

// Load the conversation history or start a new game
if (file_exists($game_history_file)) {
    $saved_conversation = json_decode(file_get_contents($game_history_file), true);
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

            echo colorize($last_assistant_message);

            $skip_api_call = true;
        } else {
            echo colorize("[bold][green]Starting a new adventure in 'The Quest of the Forgotten Realm'![/green][/bold]\n");
            echo "(Type 'exit' or 'quit' to end the game at any time.)\n";

            $conversation = [];
            $conversation[] = ['role' => 'system', 'content' => $system_prompt];
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

// Ensure the images directory exists
$images_dir = __DIR__ . '/images';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}

// Function to generate an image from the assistant's text
function process_image_from_text($text, $api_key) {
    global $debugging;
    $prompt = generate_image_prompt($text);

    if ($debugging) {
        echo "[DEBUG] Attempting to generate an image with prompt: '$prompt'\n";
    }

    $image_url = generate_image($prompt, $api_key);

    if ($image_url) {
        if ($debugging) {
            echo "[DEBUG] Image generated successfully. URL: $image_url\n";
        }
        $image_data = file_get_contents($image_url);
        $image_path = __DIR__ . '/images/temp_image.jpg';
        file_put_contents($image_path, $image_data);

        $ascii_art = generate_ascii_art($image_path);

        unlink($image_path);

        $text_with_image = $ascii_art . "\n\n" . $text;

        return $text_with_image;
    } else {
        if ($debugging) {
            echo "[DEBUG] Failed to generate image from text.\n";
        }
        return $text;
    }
}

// Function to generate an image prompt from the assistant's text
function generate_image_prompt($text) {
    $sentences = explode('.', $text);
    $first_sentence = $sentences[0];
    $prompt = trim($first_sentence);

    return $prompt;
}

// Main game loop
while (true) {
    if (!isset($skip_api_call) || !$skip_api_call) {
        if (empty($conversation)) {
            $conversation[] = ['role' => 'system', 'content' => $system_prompt];
        }

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => $conversation,
            'max_tokens' => 1000,
            'temperature' => 0.8,
        ];

        debug("Sending request to GPT-4o-mini: " . json_encode($data));

        $ch = curl_init($chat_url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Request Error: ' . curl_error($ch) . "\n";
            break;
        }

        curl_close($ch);

        debug("Response from GPT-4o-mini: " . $response);

        $result = json_decode($response, true);

        if (isset($result['choices'][0]['message']['content'])) {
            $reply = $result['choices'][0]['message']['content'];

            if (stripos($reply, "I cannot generate images") !== false) {
                debug("Removing unwanted text about inability to generate images.");
                $reply = str_ireplace("I'm sorry, but I cannot generate images directly.", "", $reply);
            }

            if (stripos($reply, '[generate image]') !== false) {
                $reply = str_replace('[generate image]', '', $reply);
                $reply = process_image_from_text($reply, $api_key);
            }

            $reply_colored = colorize($reply);

            echo "\n" . $reply_colored . "\n";

            $conversation[] = ['role' => 'assistant', 'content' => $reply];

            file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
            chmod($game_history_file, 0600);
        } else {
            echo "Error in API response.\n";
            if (isset($result['error']['message'])) {
                echo "API Error Message: " . $result['error']['message'] . "\n";
            }
            break;
        }
    } else {
        unset($skip_api_call);
    }

    echo colorize("\n[cyan]Your action (enter the number of your choice): [/cyan]");
    $user_input = trim(fgets(STDIN));

    if (strtolower($user_input) == 'exit' || strtolower($user_input) == 'quit') {
        echo colorize("\n[bold][yellow]Thank you for playing 'The Quest of the Forgotten Realm'![/yellow][/bold]\n");
        break;
    }

    $conversation[] = ['role' => 'user', 'content' => $user_input];

    file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
    chmod($game_history_file, 0600);
}
?>
