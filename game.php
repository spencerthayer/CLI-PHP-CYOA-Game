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
$generate_image_toggle = false;  // Initialize as off by default
$user_prefs_file = __DIR__ . '/.user_prefs';
$debug_log_file = __DIR__ . '/.debug_log';

// Near the top of the file, add:
$is_loading_saved_game = false;
$should_make_api_call = false;  // Initialize as false
$last_user_input = null;

// Function to create a new game by checking for the '--new' flag
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

// Function to get the API key
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
        '[cyan]' => "\033[36m",
        '[/cyan]' => "\033[0m",
        '[bold]' => "\033[1m",
        '[/bold]' => "\033[22m",
    ];
    $wrapped_text = wordwrap($text, 192, "\n", true);
    return str_replace(array_keys($color_codes), array_values($color_codes), $wrapped_text);
}

// Function to generate ASCII art from an image
function generate_ascii_art($image_path) {
    global $debugging;
    if ($debugging) {
        echo "[DEBUG] Generating ASCII art from image at: $image_path\n";
    }
    $script_path = __DIR__ . '/ascii_art_converter.php';
    $output = shell_exec("php " . escapeshellarg($script_path) . " " . escapeshellarg($image_path));
    return $output;
}

// Add this new function with the other utility functions
function show_loading_animation($message = "Generating image") {
    $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    $frameCount = count($frames);
    
    // Start on a new line
    echo "\n";
    
    // Create a separate process for the animation
    $pid = pcntl_fork();
    
    if ($pid == 0) {  // Child process
        $i = 0;
        while (true) {
            echo "\r" . $frames[$i % $frameCount] . " $message...";
            usleep(100000); // 0.1 second delay
            $i++;
            flush();
        }
    }
    
    return $pid;
}

function stop_loading_animation($pid) {
    if ($pid) {
        posix_kill($pid, SIGTERM);
        pcntl_wait($pid); // Prevent zombie process
        echo "\r" . str_repeat(" ", 50) . "\r"; // Clear the line
    }
}

// Modify the generate_image function
function generate_image($prompt, $timestamp) {
    global $debugging;
    
    if (!is_string($prompt)) {
        if ($debugging) {
            echo "[DEBUG] Invalid prompt format: " . print_r($prompt, true) . "\n";
        }
        return null;
    }
    
    $prompt_url = urlencode("8bit ANSI video game $prompt");
    $url = "https://image.pollinations.ai/prompt/$prompt_url?nologo=true&width=360&height=160&seed=$timestamp&model=flux";

    if ($debugging) {
        echo "[DEBUG] Generated Pollinations URL with timestamp: $timestamp\n";
    }
    
    $loading_pid = show_loading_animation();
    
    $image_data = file_get_contents($url);
    
    stop_loading_animation($loading_pid);
    
    if ($image_data === false) {
        echo "Error downloading image from Pollinations.ai.\n";
        return null;
    }
    
    $image_path = __DIR__ . "/images/temp_image_$timestamp.jpg";
    file_put_contents($image_path, $image_data);
    return generate_ascii_art($image_path);
}

// Debugging output function
function debug_log($message) {
    write_debug_log($message);
}

// Function to generate an image prompt from the assistant's text
function generate_image_prompt_summary($text) {
    // Ensure we're working with a string
    if (is_array($text)) {
        if (isset($text['prompt'])) {
            $text = $text['prompt'];
        } else {
            return ""; // Return empty string if no prompt found
        }
    }
    $sentences = explode('.', $text);
    $prompt = implode('. ', array_slice($sentences, 0, 2));
    return trim($prompt);
}

// Function to process image from text
function process_image_from_text($text, $timestamp) {
    global $debugging;
    if (is_array($text)) {
        if (isset($text['prompt'])) {
            $text = $text['prompt'];
        } else {
            return "";
        }
    }
    $prompt = generate_image_prompt_summary($text);
    if ($debugging) {
        echo "[DEBUG] Attempting to generate an image with prompt: '$prompt' and timestamp: $timestamp\n";
    }
    $ascii_art = generate_image($prompt, $timestamp);
    return $ascii_art ?: "";
}

// Function to process the scene and generate image
function process_scene($scene_data, $api_key) {
    global $debugging, $generate_image_toggle;
    
    // Ensure we have a timestamp
    if (!isset($scene_data->timestamp)) {
        $scene_data->timestamp = time();
    }
    
    write_debug_log("Processing new scene", [
        'generate_image_toggle' => $generate_image_toggle,
        'timestamp' => $scene_data->timestamp,
        'scene_data' => $scene_data
    ]);
    
    if ($generate_image_toggle) {
        $image_path = __DIR__ . "/images/temp_image_{$scene_data->timestamp}.jpg";
        
        // Check if image already exists
        if (file_exists($image_path)) {
            if ($debugging) {
                echo "[DEBUG] Using existing image for timestamp: {$scene_data->timestamp}\n";
            }
            $ascii_art = generate_ascii_art($image_path);
        } else {
            // Generate new image if it doesn't exist
            $image_prompt = '';
            if (is_object($scene_data->image)) {
                $image_prompt = $scene_data->image->prompt;
            } elseif (is_array($scene_data->image)) {
                $image_prompt = $scene_data->image['prompt'];
            } else {
                $image_prompt = $scene_data->image;
            }

            if (!is_string($image_prompt)) {
                if ($debugging) {
                    echo "[DEBUG] Invalid image prompt format: " . print_r($image_prompt, true) . "\n";
                }
                return;
            }

            $scene_data->image = [
                'prompt' => $image_prompt,
                'timestamp' => $scene_data->timestamp
            ];
            
            $ascii_art = process_image_from_text($image_prompt, $scene_data->timestamp);
        }

        if (!empty($ascii_art)) {
            echo "\n" . $ascii_art . "\n\n";
        } else {
            echo colorize("[red]Failed to generate image.[/red]\n");
        }
    }

    // Display the scene narrative, options, and prompt
    echo "\n" . colorize($scene_data->narrative) . "\n\n";
    echo colorize("\n[bold]Choose your next action:[/bold]\n");

    foreach ($scene_data->options as $index => $option) {
        $number = $index + 1;
        echo colorize("[cyan]{$number}. {$option}[/cyan]\n");
    }

    // Add additional options
    echo "\n"; // Add spacing
    echo colorize("[green](t) Type in your own action[/green]");
    echo " | ";
    echo colorize("[green](g) Toggle image generation (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
    echo " | ";
    echo colorize("[green](q) Quit the game[/green]");
    echo " | ";
    echo colorize("[green](n) Start a new game[/green]");
    echo "\n";
}

// Updated system prompt
$system_prompt = "
You are an interactive text-based adventure game called 'The Dying Earth.'

This is a grimdark world set in the twilight of civilization, where magic has decayed, the sun is dying, and ancient horrors lurk beneath a crumbling earth. The story should evoke the style and prose of Jack Vance, using rich, archaic language and an atmosphere of cosmic horror. Each scene is to be vivid, darkly poetic, and filled with the weight of a world in decline, offering the player a sense of despair, decay, and alien mysteries.

In each scene, describe the surroundings as eerie, strange, and unearthly, with an undercurrent of dread and despair. The setting should be filled with grotesque creatures, ancient ruins, and fragments of lost knowledge. The air is thick with the residue of forgotten magic, and the world feels on the brink of an inevitable collapse. Ensure the player feels like a fragile intruder in a world that has seen countless ages, indifferent to the survival of any individual.

Always provide exactly 4 options for the player to choose from. Each option should be a single sentence, crafted with the grim grandeur and existential dread typical of Vance's narrative style. Use 1-2 relevant, themed emojis to enhance visualization (e.g., 🕯️ for a flickering candle, ☠️ for a hint of death, or 🌑 for a desolate landscape).

Guide the player through this dying realm with language that enhances the cosmic horror and conveys a sense of the unknown. Every choice should feel like a path into further darkness, where enlightenment may come at a terrible cost.
";

// Get the API key
$api_key = get_api_key($api_key_file);

// Initialize the conversation history
$conversation = [];
$scene_data = null;

// Load the conversation history or start a new game
if (file_exists($game_history_file)) {
    $history_content = file_get_contents($game_history_file);
    if ($history_content !== false) {
        $saved_conversation = json_decode($history_content, true);
        if ($saved_conversation && is_array($saved_conversation)) {
            $conversation = $saved_conversation;
            $last_assistant_message = null;
            
            // Load user preferences first
            $user_prefs = load_user_preferences($user_prefs_file);
            $generate_image_toggle = isset($user_prefs['generate_images']) ? $user_prefs['generate_images'] : false;
            
            foreach (array_reverse($conversation) as $message) {
                if ($message['role'] === 'assistant' && isset($message['function_call'])) {
                    $function_call = $message['function_call'];
                    $arguments = is_array($function_call) ? $function_call['arguments'] : $function_call->arguments;
                    $scene_data = json_decode($arguments);
                    $scene_data->timestamp = $message['timestamp'];
                    $is_loading_saved_game = true;
                    
                    echo colorize("[bold][green]Welcome back to 'The Dying Earth'![/green][/bold]\n");
                    
                    // Check for and display saved image before processing scene
                    if ($generate_image_toggle) {
                        $image_path = __DIR__ . "/images/temp_image_{$scene_data->timestamp}.jpg";
                        if (file_exists($image_path)) {
                            if ($debugging) {
                                echo "[DEBUG] Loading saved image for timestamp: {$scene_data->timestamp}\n";
                            }
                            $ascii_art = generate_ascii_art($image_path);
                            if (!empty($ascii_art)) {
                                echo "\n" . $ascii_art . "\n\n";
                            }
                        } else {
                            if ($debugging) {
                                echo "[DEBUG] No saved image found for timestamp: {$scene_data->timestamp}\n";
                            }
                        }
                    }
                    
                    // Display scene text and options
                    echo "\n" . colorize($scene_data->narrative) . "\n\n";
                    echo colorize("\n[bold]Choose your next action:[/bold]\n");

                    foreach ($scene_data->options as $index => $option) {
                        $number = $index + 1;
                        echo colorize("[cyan]{$number}. {$option}[/cyan]\n");
                    }

                    // Add additional options
                    echo "\n";
                    echo colorize("[green](t) Type in your own action[/green]");
                    echo " | ";
                    echo colorize("[green](g) Toggle image generation (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
                    echo " | ";
                    echo colorize("[green](q) Quit the game[/green]");
                    echo " | ";
                    echo colorize("[green](n) Start a new game[/green]");
                    echo "\n";
                    
                    break;
                }
            }
        }
    }
} else {
    echo colorize("[bold][green]Starting a new adventure in 'The Dying Earth'![/green][/bold]\n");
    $conversation[] = ['role' => 'system', 'content' => $system_prompt];
    $conversation[] = ['role' => 'user', 'content' => 'start game', 'timestamp' => time()];
    $should_make_api_call = true;
    $last_user_input = 'start game';
}

// Ensure the images directory exists
$images_dir = __DIR__ . '/images';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}

// Modify the removeImageAndDirectory function to be clearImages
function clearImages() {
    $directory_path = __DIR__ . '/images';
    if (is_dir($directory_path)) {
        $files = glob($directory_path . '/temp_image_*.jpg');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }
}

// Initialize variables before the main game loop
$max_iterations = 1000;
$current_iteration = 0;

// Modify the initialization section (before the main game loop)
$user_prefs = load_user_preferences($user_prefs_file);
$generate_image_toggle = isset($user_prefs['generate_images']) ? $user_prefs['generate_images'] : false;

// Main game loop
while ($current_iteration++ < $max_iterations) {
    if ($should_make_api_call && validateApiCall($conversation, $last_user_input)) {
        write_debug_log("Making API call", [
            'conversation_length' => count($conversation),
            'last_user_input' => $last_user_input
        ]);
        
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => $conversation,
            'max_tokens' => 1000,
            'temperature' => 0.8,
            'functions' => [
                [
                    'name' => 'GameResponse',
                    'description' => 'Response from the game, containing the narrative, options, and image prompt.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'narrative' => [
                                'type' => 'string',
                                'description' => 'The main story text describing the current scene'
                            ],
                            'options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'minItems' => 4,
                                'maxItems' => 4,
                                'description' => 'Exactly 4 options for the player to choose from'
                            ],
                            'image' => [
                                'type' => 'object',
                                'properties' => [
                                    'prompt' => [
                                        'type' => 'string',
                                        'minCharacters' => 32,
                                        'maxCharacters' => 128,
                                        'description' => 'A descriptive prompt for generating an 8-bit style image of the current scene'
                                    ]
                                ],
                                'required' => ['prompt'],
                                'additionalProperties' => false
                            ]
                        ],
                        'required' => ['narrative', 'options', 'image'],
                        'additionalProperties' => false
                    ]
                ]
            ],
            'function_call' => ['name' => 'GameResponse']
        ];

        write_debug_log("API request payload", $data);
        
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
        write_debug_log("API response received", [
            'raw_response' => $response,
            'curl_info' => curl_getinfo($ch)
        ]);
        if (curl_errno($ch)) {
            echo 'Request Error: ' . curl_error($ch) . "\n";
            break;
        }
        curl_close($ch);
        $result = json_decode($response);
        if (isset($result->choices[0]->message->function_call)) {
            $function_call = $result->choices[0]->message->function_call;
            if ($function_call->name === 'GameResponse') {
                $current_timestamp = time();
                $scene_data = json_decode($function_call->arguments);
                if ($scene_data && isset($scene_data->narrative, $scene_data->options, $scene_data->image)) {
                    $scene_data->timestamp = $current_timestamp;
                    process_scene($scene_data, $api_key);
                    $conversation[] = [
                        'role' => 'assistant',
                        'content' => '',
                        'function_call' => $function_call,
                        'timestamp' => $current_timestamp
                    ];
                    file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
                }
            }
        }
    }

    // Get user input
    echo colorize("\n[cyan]Your choice: [/cyan]");
    $user_input = trim(fgets(STDIN));
    
    write_debug_log("User input received", [
        'input' => $user_input,
        'is_loading_saved_game' => $is_loading_saved_game
    ]);

    // Reset loading saved game flag after first input
    if ($is_loading_saved_game) {
        $is_loading_saved_game = false;
    }

    // Handle user input
    if (strtolower($user_input) == 'q') {
        echo colorize("\n[bold][yellow]Thank you for playing 'The Dying Earth'![/yellow][/bold]\n");
        break;
    }

    if (strtolower($user_input) == 'n') {
        echo colorize("\n[bold][yellow]Starting a new game...[/yellow][/bold]\n");
        $conversation = [['role' => 'system', 'content' => $system_prompt]];
        if (file_exists($game_history_file)) unlink($game_history_file);
        clearImages(); // Only clear images when starting a new game
        $should_make_api_call = true;
        $last_user_input = 'start game';
        continue;
    }

    if (strtolower($user_input) == 'g') {
        $generate_image_toggle = !$generate_image_toggle;
        $user_prefs['generate_images'] = $generate_image_toggle;
        save_user_preferences($user_prefs_file, $user_prefs);
        echo colorize("\n[bold][yellow]Image generation is now " . ($generate_image_toggle ? "On" : "Off") . "[/yellow][/bold]\n");
        if (isset($scene_data)) process_scene($scene_data, $api_key);
        continue;
    }

    if (strtolower($user_input) == 't') {
        echo colorize("\n[cyan]Type your action: [/cyan]");
        $custom_action = trim(fgets(STDIN));
        if (!empty($custom_action)) {
            $conversation[] = ['role' => 'user', 'content' => $custom_action, 'timestamp' => time()];
            $should_make_api_call = true;
            file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
            continue;
        } else {
            echo colorize("[red]No action entered. Please try again.[/red]\n");
            if ($scene_data) process_scene($scene_data, $api_key);
            continue;
        }
    }

    if (!preg_match('/^[1-4]$/', $user_input)) {
        echo colorize("[red]Invalid input. Please enter a number between 1-4, 't' to type an action, 'g' for image, 'q' to quit, or 'n' for new game.[/red]\n");
        if ($scene_data) process_scene($scene_data, $api_key);
        continue;
    }

    // Set up for next API call
    if (preg_match('/^[1-4]$/', $user_input) || strtolower($user_input) == 't') {
        $conversation[] = ['role' => 'user', 'content' => $user_input, 'timestamp' => time()];
        $should_make_api_call = true;
        $last_user_input = $user_input;
        file_put_contents($game_history_file, json_encode($conversation), LOCK_EX);
    }
}

// Add this new function after other utility functions
function load_user_preferences($user_prefs_file) {
    if (file_exists($user_prefs_file)) {
        $prefs = json_decode(file_get_contents($user_prefs_file), true);
        return is_array($prefs) ? $prefs : [];
    }
    return [];
}

function save_user_preferences($user_prefs_file, $prefs) {
    file_put_contents($user_prefs_file, json_encode($prefs), LOCK_EX);
    chmod($user_prefs_file, 0600);
}

function validateApiCall($conversation, $user_input) {
    // Allow 'start game' for the initial scene
    if ($user_input === 'start game') {
        return true;
    }

    // Don't make API call if there's no user input
    if (empty($user_input)) {
        return false;
    }

    // Don't make API call if the last message was from the assistant
    $last_message = end($conversation);
    if ($last_message && $last_message['role'] === 'assistant') {
        return false;
    }

    // Validate user input format
    if (!in_array(strtolower($user_input), ['t', 'g', 'n', 'q']) && 
        !preg_match('/^[1-4]$/', $user_input)) {
        return false;
    }

    return true;
}

// Add this new function with other utility functions
function write_debug_log($message, $context = null) {
    global $debugging, $debug_log_file;
    if (!$debugging) return;

    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($context !== null) {
        $formatted_context = print_r($context, true);
        $log_message .= "\nContext: $formatted_context";
    }
    
    file_put_contents($debug_log_file, $log_message . "\n", FILE_APPEND);
    echo "[DEBUG] $message\n";
}
?>
