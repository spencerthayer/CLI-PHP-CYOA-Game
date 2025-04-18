<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure readline is available and initialized
if (!extension_loaded('readline')) {
    die("The readline extension is required for this game. Please install it first.\n");
}

// Initialize readline
readline_completion_function(function($input) {
    return []; // No auto-completion for now
});

// Configure readline to use proper line editing
if (function_exists('readline_info')) {
    readline_info('bind-tty-special-chars', true);
}

// Simple autoloader
spl_autoload_register(function ($class) {
    // Convert namespace to full file path
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

use App\GameState;
use App\ImageHandler;
use App\ApiHandler;
use App\Utils;
use App\AudioHandler;

// Check if debug mode is enabled
$debug = in_array('--debug', $argv);

// Function to get the API key
function get_api_key($api_key_file) {
    $data_dir = dirname($api_key_file);
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }

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

// Function to write to debug log
function write_debug_log($message, $context = null) {
    global $debug, $config;
    if (!$debug) return;

    $debug_timestamp = date('Y-m-d H:i:s.u');
    $log_message = "[$debug_timestamp] $message";
    
    if ($context !== null) {
        $formatted_context = print_r($context, true);
        $log_message .= "\nContext: $formatted_context";
    }
    
    file_put_contents($config['paths']['debug_log_file'], $log_message . "\n", FILE_APPEND);
    echo "[DEBUG] $message\n";
}

// Ensure the script is run from the command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Load configuration
$config = require __DIR__ . '/app/config.php';

// Ensure images directory exists
if (!is_dir($config['paths']['images_dir'])) {
    mkdir($config['paths']['images_dir'], 0755, true);
}

// Ensure data directory exists for other files
$data_dir_paths = [
    $config['paths']['game_history_file'],
    $config['paths']['user_prefs_file'],
    $config['paths']['debug_log_file']
];
foreach ($data_dir_paths as $file_path) {
    $data_dir = dirname($file_path);
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
        break; // Only need to create it once
    }
}

// Initialize components
$gameState = new GameState($config, $debug);
write_debug_log("GameState initialized", ['config' => $config, 'debug' => $debug]);

$imageHandler = new ImageHandler($config, $debug);
write_debug_log("ImageHandler initialized");

$audioHandler = new AudioHandler($config, $debug);
write_debug_log("AudioHandler initialized");

// Test audio generation (DEBUG ONLY)
if ($debug) {
    write_debug_log("Testing audio generation API");
    $audioHandler->testAudioGeneration();
}

$apiHandler = new ApiHandler($config, get_api_key($config['paths']['api_key_file']), $gameState, $debug);
write_debug_log("ApiHandler initialized with CharacterStats integration");

// Initialize game variables
$generate_image_toggle = $config['game']['generate_image_toggle'] ?? true;
$generate_audio_toggle = $config['game']['audio_toggle'] ?? true;
$should_make_api_call = false;
$last_user_input = '';
$scene_data = null;

// Check for new game flag
if (in_array('--new', $argv)) {
    // Clear game history
    if (file_exists($config['paths']['game_history_file'])) {
        unlink($config['paths']['game_history_file']);
        echo "Game history cleared. Starting a new game...\n";
    } else {
        echo "No game history found. Starting a new game...\n";
    }
    
    // Clear debug log
    if (file_exists($config['paths']['debug_log_file'])) {
        unlink($config['paths']['debug_log_file']);
    }
    
    // Display title screen
    $title_art = $imageHandler->generate1Screen();
    if ($title_art) {
        echo "\n" . $title_art . "\n\n";
    }
    
    $imageHandler->clearImages();
    $audioHandler->clearAudioFiles();
    $gameState = new GameState($config);
    $gameState->addMessage('system', $config['system_prompt']);
    $gameState->addMessage('user', 'start game');
    $should_make_api_call = true;
    $last_user_input = 'start game';
}

// Function to validate API call
function validateApiCall($conversation, $user_input) {
    if (!is_string($user_input)) {
        return false;
    }

    if (in_array(strtolower($user_input), ['t', 'i', 'n', 'q', 's', 'a', 'c'])) {
        return false;
    }

    if ($user_input === 'start game') {
        return true;
    }

    if (empty($user_input)) {
        return false;
    }

    $last_message = end($conversation);
    if ($last_message && $last_message['role'] === 'assistant') {
        return true;
    }

    if ($last_message && $last_message['role'] === 'user' && !empty($last_message['content'])) {
        return true;
    }

    if (preg_match('/^[1-4]$/', $user_input)) {
        return true;
    }

    return false;
}

// Get initial conversation state
$conversation = $gameState->getConversation();

// If no conversation exists or --new flag was used, start a new game
if (empty($conversation) || in_array('--new', $argv)) {
    write_debug_log("Starting new game");
    if (empty($conversation)) {
        $gameState->addMessage('system', $config['system_prompt']);
        $gameState->addMessage('user', 'start game');
    }
    $should_make_api_call = true;
    $last_user_input = 'start game';
}

// Make initial API call if needed
if ($should_make_api_call) {
    write_debug_log("Making initial API call");
    $response = $apiHandler->makeApiCall($gameState->getConversation());
    
    if ($response) {
        if ($debug) {
            write_debug_log("API response received", $response);
        }
        
        $gameState->addMessage('assistant', '', ['name' => 'GameResponse', 'arguments' => json_encode($response)]);
        
        // Convert array to object for compatibility
        $scene_data = json_decode(json_encode($response));
        if (!$scene_data) {
            throw new \Exception("Failed to parse scene data");
        }
        // Add timestamp if not present
        if (!isset($scene_data->timestamp)) {
            $scene_data->timestamp = time();
        }
    } else {
        throw new \Exception("No valid response from API");
    }
    $should_make_api_call = false;
} else {
    // Get the last scene if it exists
    $lastMessage = end($conversation);
    if (isset($lastMessage['function_call']) && isset($lastMessage['function_call']->arguments)) {
        $scene_data = json_decode($lastMessage['function_call']->arguments);
    }
}

// Main game loop
while (true) {
    try {
        // Display the current scene and menu
        if ($scene_data) {
            displayScene($scene_data, $generate_image_toggle, $imageHandler, $generate_audio_toggle, $audioHandler);
        }
        displayGameMenu($generate_image_toggle, $generate_audio_toggle);
        
        // Get user input using readline
        $user_input = readline(Utils::colorize("\n[cyan]Your choice: [/cyan]"));
        readline_add_history($user_input); // Add to history for up/down arrow support
        
        // Add a newline after input
        echo "\n";
        
        // Process user input
        $user_input = strtolower(trim($user_input));
        
        if (empty($user_input)) {
            echo Utils::colorize("\n[red]Please enter a valid choice.[/red]\n");
            continue;
        }
        
        $should_make_api_call = false;
        
        switch($user_input) {
            case 'q':
                echo Utils::colorize("\n[bold][yellow]Thank you for playing 'The Dying Earth'![/yellow][/bold]\n");
                exit(0);
                
            case 'n':
                // Ask for confirmation before starting a new game
                echo Utils::colorize("\n[yellow]Are you sure you want to start a new game? All current progress will be lost.[/yellow]");
                echo Utils::colorize("\n[green](y) Yes[/green] | [red](n) No[/red]\n");
                $confirm = strtolower(trim(readline(Utils::colorize("\n[cyan]Your choice: [/cyan]"))));
                
                if ($confirm === 'y') {
                    // Clear game history and start new game
                    if (file_exists($config['paths']['game_history_file'])) {
                        unlink($config['paths']['game_history_file']);
                    }
                    echo "Starting a new game...\n";
                    
                    // Generate and display the title screen
                    $title_screen = $imageHandler->generate1Screen();
                    if ($title_screen) {
                        echo $title_screen . "\n";
                    }
                    
                    $imageHandler->clearImages();
                    $audioHandler->clearAudioFiles();
                    $gameState = new GameState($config, $debug);
                    $gameState->addMessage('system', $config['system_prompt']);
                    $gameState->addMessage('user', 'start game');
                    $should_make_api_call = true;
                    $last_user_input = 'start game';
                    $scene_data = null; // Reset scene data for new game
                } else {
                    echo Utils::colorize("\n[yellow]Continuing current game...[/yellow]\n");
                }
                break;
                
            case 'i':
                $generate_image_toggle = !$generate_image_toggle;
                echo Utils::colorize("\n[green]Image generation is now " . ($generate_image_toggle ? "enabled" : "disabled") . ".[/green]\n");
                // Only redisplay the scene if turning images ON
                if ($generate_image_toggle && $scene_data) {
                    displayScene($scene_data, $generate_image_toggle, $imageHandler, $generate_audio_toggle, $audioHandler);
                }
                // If turning off, just continue to show the menu again without full scene refresh
                continue 2; // Always continue to redisplay menu
                
            case 'a':
                $generate_audio_toggle = !$generate_audio_toggle;
                echo Utils::colorize("\n[green]Audio generation is now " . ($generate_audio_toggle ? "enabled" : "disabled") . ".[/green]\n");
                // Don't redisplay scene or audio, just show menu again
                continue 2;
                
            case 't':
                $custom_action = readline(Utils::colorize("\n[cyan]Type your action: [/cyan]"));
                readline_add_history($custom_action); // Add to history
                echo "\n"; // Add newline after input
                
                $custom_action = trim($custom_action);
                
                if (!empty($custom_action)) {
                    if (strlen($custom_action) > $config['game']['max_custom_action_length']) {
                        echo Utils::colorize("\n[red]Your action is too long. Please keep it under " . 
                            $config['game']['max_custom_action_length'] . " characters.[/red]\n");
                        continue 2;
                    }
                    $last_user_input = $custom_action;
                    $gameState->addMessage('user', $custom_action);
                    $should_make_api_call = true;
                } else {
                    echo Utils::colorize("\n[red]Please enter a valid action.[/red]\n");
                    continue 2;
                }
                break;
                
            case 's':
            case 'c':
                displayCharacterSheet($gameState);
                // Don't redisplay scene, just show menu again
                continue 2;
                
            default:
                // Get the current options based on any recent skill check
                $current_options = [];
                if (isset($scene_data->options->success) && isset($scene_data->options->failure)) {
                    $conversation = $gameState->getConversation();
                    $last_message = end($conversation);
                    if ($last_message && $last_message['role'] === 'assistant') {
                        $last_check = $gameState->getLastCheckResult();
                        if ($last_check) {
                            $current_options = $last_check['success'] ? $scene_data->options->success : $scene_data->options->failure;
                        } else {
                            $current_options = $scene_data->options->success;
                        }
                    } else {
                        $current_options = $scene_data->options->success;
                    }
                } else if (is_array($scene_data->options)) {
                    $current_options = $scene_data->options;
                }

                if (preg_match('/^[1-4]$/', $user_input) && $scene_data && isset($current_options[$user_input - 1])) {
                    $chosen_option = $current_options[$user_input - 1];
                    
                    // Check for various types of checks in the option text
                    $check_patterns = [
                        'SKILL_CHECK' => '/\[SKILL_CHECK:(\w+):(\d+)\]/',
                        'SAVE' => '/\[SAVE:(\w+):(\d+)\]/',
                        'SANITY_CHECK' => '/\[SANITY_CHECK:(\d+)\]/'
                    ];
                    
                    foreach ($check_patterns as $check_type => $pattern) {
                        if (preg_match($pattern, $chosen_option, $matches)) {
                            $result = null;
                            
                            switch ($check_type) {
                                case 'SKILL_CHECK':
                                    $attribute = $matches[1];
                                    $difficulty = intval($matches[2]);
                                    $result = $gameState->getCharacterStats()->skillCheck($attribute, $difficulty);
                                    echo Utils::colorize(sprintf(
                                        "\n🎲 [cyan]%s Check[/cyan]: %d + %d (modifier) + %d (proficiency) = [bold]%d[/bold] vs DC %d - [%s]%s[/%s]!\n",
                                        $attribute,
                                        $result['roll'],
                                        $result['modifier'],
                                        $result['proficiency'],
                                        $result['total'],
                                        $difficulty,
                                        $result['success'] ? 'green' : 'red',
                                        $result['success'] ? "Success" : "Failure",
                                        $result['success'] ? 'green' : 'red'
                                    ));
                                    break;
                                    
                                case 'SAVE':
                                    $save_type = $matches[1];
                                    $difficulty = intval($matches[2]);
                                    $result = $gameState->getCharacterStats()->savingThrow($save_type, $difficulty);
                                    echo Utils::colorize(sprintf(
                                        "\n🎲 [cyan]%s Save[/cyan]: %d + %d (modifier) = [bold]%d[/bold] vs DC %d - [%s]%s[/%s]!\n",
                                        $save_type,
                                        $result['roll'],
                                        $result['modifier'],
                                        $result['total'],
                                        $difficulty,
                                        $result['success'] ? 'green' : 'red',
                                        $result['success'] ? "Success" : "Failure",
                                        $result['success'] ? 'green' : 'red'
                                    ));
                                    break;
                                    
                                case 'SANITY_CHECK':
                                    $difficulty = intval($matches[1]);
                                    $result = $gameState->getCharacterStats()->sanityCheck($difficulty);
                                    echo Utils::colorize(sprintf(
                                        "\n🎲 [cyan]Sanity Check[/cyan]: %d + %d (modifier) = [bold]%d[/bold] vs DC %d - [%s]%s[/%s]!%s\n",
                                        $result['roll'],
                                        $result['modifier'],
                                        $result['total'],
                                        $difficulty,
                                        $result['success'] ? 'green' : 'red',
                                        $result['success'] ? "Success" : "Failure",
                                        $result['success'] ? 'green' : 'red',
                                        !$result['success'] ? Utils::colorize(sprintf(
                                            "\n[red]Lost %d Sanity! Current Sanity: %d/%d[/red]",
                                            $result['sanityLoss'],
                                            $result['currentSanity'],
                                            $gameState->getCharacterStats()->getStat('Sanity')['max']
                                        )) : ""
                                    ));
                                    break;
                            }
                            
                            if ($result) {
                                $gameState->setLastCheckResult($result);
                            }
                        }
                    }
                    
                    $last_user_input = $chosen_option;
                    $gameState->addMessage('user', $chosen_option);
                    $should_make_api_call = true;
                } else {
                    echo Utils::colorize("\n[red]Invalid choice. Please enter a number between 1-4 or use one of the menu options (t/i/q/n/s).[/red]\n");
                    continue 2;
                }
                break;
        }
        
        // Make API call if needed
        if ($should_make_api_call) {
            // Get current timestamp for this instance
            $current_timestamp = time();
            if ($debug) {
                echo "[DEBUG] New history instance timestamp: $current_timestamp\n";
            }
            write_debug_log("New history instance", ['timestamp' => $current_timestamp]);
            
            if ($debug) {
                echo "[DEBUG] Making API call\n";
            }
            write_debug_log("Making API call", [
                'last_user_input' => $last_user_input,
                'conversation_count' => count($gameState->getConversation())
            ]);
            
            $response = $apiHandler->makeApiCall($gameState->getConversation());
            
            if ($response) {
                if ($debug) {
                    write_debug_log("API response received", $response);
                }
                
                $gameState->addMessage('assistant', '', ['name' => 'GameResponse', 'arguments' => json_encode($response)]);
                
                // Convert array to object for compatibility
                $scene_data = json_decode(json_encode($response));
                if (!$scene_data) {
                    throw new \Exception("Failed to parse scene data");
                }
                // Add timestamp if not present
                if (!isset($scene_data->timestamp)) {
                    $scene_data->timestamp = $current_timestamp;
                }
            } else {
                throw new \Exception("No valid response from API");
            }
            $should_make_api_call = false;
        }
        
    } catch (Exception $e) {
        write_debug_log("Error in game loop: " . $e->getMessage());
        echo Utils::colorize("[red]An error occurred: " . $e->getMessage() . "\nPlease try again.[/red]\n");
        continue;
    }
}

// Function to display the game menu
function displayGameMenu($generate_image_toggle, $generate_audio_toggle) {
    echo "\n";
    echo Utils::colorize("[green](t) Type in your own action[/green]");
    echo " | ";
    echo Utils::colorize("[green](s) Character Sheet[/green]");
    echo " | ";
    echo Utils::colorize("[green](i) Generate Images (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
    echo " | ";
    echo Utils::colorize("[green](a) Generate Audio (" . ($generate_audio_toggle ? "On" : "Off") . ")[/green]");
    echo " | ";
    echo Utils::colorize("[green](q) Quit the game[/green]");
    echo " | ";
    echo Utils::colorize("[green](n) Start a new game[/green]");
    echo "\n";
}

// Function to display character sheet
function displayCharacterSheet($gameState) {
    $stats = $gameState->getCharacterStats();
    $allStats = $stats->getStats();
    $attributes = $allStats['attributes'];
    
    $box_top    = "[bold][yellow]╔════════════════════════════════════════╗[/yellow][/bold]\n";
    $box_title  = "[bold][yellow]║           CHARACTER SHEET              ║[/yellow][/bold]\n";
    $box_sep1   = "[bold][yellow]╠════════════════════════════════════════╣[/yellow][/bold]\n";
    $box_sep2   = "[bold][yellow]╟────────────────────────────────────────╢[/yellow][/bold]\n";
    $box_bottom = "[bold][yellow]╚════════════════════════════════════════╝[/yellow][/bold]\n";
    $box_left   = "[bold][yellow]║[/yellow][/bold] ";
    $box_right  = " [bold][yellow]  ║[/yellow][/bold]\n";
    
    echo "\n";
    echo Utils::colorize($box_top);
    echo Utils::colorize($box_title);
    echo Utils::colorize($box_sep1);
    
    // Level and Experience
    echo Utils::colorize($box_left . str_pad("Level: " . $allStats['level'], 36) . $box_right);
    echo Utils::colorize($box_left . str_pad("Experience: " . $allStats['experience'], 36) . $box_right);
    
    // Primary Attributes Section
    echo Utils::colorize($box_sep2);
    echo Utils::colorize($box_left . str_pad("ATTRIBUTES", 36) . $box_right);
    echo Utils::colorize($box_sep2);
    
    $primaryStats = ['Agility', 'Appearance', 'Charisma', 'Dexterity', 'Endurance', 
                    'Intellect', 'Knowledge', 'Luck', 'Perception', 'Spirit', 
                    'Strength', 'Vitality', 'Willpower', 'Wisdom'];
    
    foreach ($primaryStats as $stat) {
        if (isset($attributes[$stat])) {
            $current = $attributes[$stat]['current'];
            $modifier = $stats->calculateModifier($current);
            $modifierStr = ($modifier >= 0) ? "+$modifier" : "$modifier";
            $line = str_pad($stat . ": " . $current . " (" . $modifierStr . ")", 36);
            echo Utils::colorize($box_left . $line . $box_right);
        }
    }
    
    // Derived Stats Section
    echo Utils::colorize($box_sep2);
    echo Utils::colorize($box_left . str_pad("DERIVED STATS", 36) . $box_right);
    echo Utils::colorize($box_sep2);
    
    $derivedStats = ['Health', 'Focus', 'Stamina', 'Courage', 'Sanity'];
    foreach ($derivedStats as $stat) {
        if (isset($attributes[$stat])) {
            $current = $attributes[$stat]['current'];
            $max = $attributes[$stat]['max'];
            $line = str_pad($stat . ": " . $current . "/" . $max, 36);
            echo Utils::colorize($box_left . $line . $box_right);
        }
    }
    
    echo Utils::colorize($box_bottom);
    echo "\n";
}

// Function to display scene
function displayScene($scene_data, $generate_image_toggle = true, $imageHandler = null, $generate_audio_toggle = true, $audioHandler = null) {
    if (!isset($scene_data->narrative)) {
        return;
    }

    // Display image first (if enabled)
    if ($generate_image_toggle && $imageHandler) {
        // Try to display existing image first
        $ascii_art = null;
        
        if (isset($scene_data->timestamp)) {
            $ascii_art = $imageHandler->displayExistingImage($scene_data->timestamp);
            if ($ascii_art) {
                echo "\n" . $ascii_art . "\n\n";
            }
        }
        
        // If no existing image, generate a new one
        if (!$ascii_art && isset($scene_data->image->prompt)) {
            $timestamp = $scene_data->timestamp ?? time();
            $ascii_art = $imageHandler->generateImage($scene_data->image->prompt, $timestamp);
            if ($ascii_art) {
                echo "\n" . $ascii_art . "\n\n";
            }
        }
    }
    
    // --- Corrected Narrative Handling ---
    
    // 1. Get the raw narrative
    $raw_narrative = $scene_data->narrative ?? '';

    // 2. Prepare text for display: Colorize first, then wrap.
    $colorized_narrative = Utils::colorize($raw_narrative); // Apply [color] tags -> ANSI codes
    $display_narrative = Utils::wrapText($colorized_narrative); // Wrap text with ANSI codes
    echo "\n" . $display_narrative . "\n\n";

    // 3. Prepare text for audio: Strip [color] tags from raw text.
    if ($generate_audio_toggle && $audioHandler && !empty(trim($raw_narrative))) {
        // Strip [color] tags (using regex to match tags like [red], [/bold], etc.)
        $text_for_audio = preg_replace('/\[\/?\w+\]/', '', $raw_narrative);
        
        global $debug;
        if ($debug) {
            write_debug_log("Attempting to speak narrative", ['text' => $text_for_audio]);
        }
        $audioHandler->speakNarrative($text_for_audio);
    }

    // --- End Corrected Narrative Handling ---

    // Display options
    echo Utils::colorize("\n[bold]Choose your next action:[/bold]\n");

    // Get the appropriate options based on the last check result
    $options = [];
    $last_check = null;

    if (isset($scene_data->options->success) && isset($scene_data->options->failure)) {
        global $gameState;
        $last_check = $gameState->getLastCheckResult();

        if ($last_check) {
            $options = $last_check['success'] ? $scene_data->options->success : $scene_data->options->failure;
        } else {
            // If no check result is stored, but we expect one (e.g., options are structured), 
            // default to failure options maybe? Or log a warning? Let's assume success for now, but this might need review.
            write_debug_log("Warning: Expected last check result, but none found. Defaulting to success options.", ['scene_data' => $scene_data]);
            $options = $scene_data->options->success;
        }
    } else if (is_array($scene_data->options)) {
        // Handle legacy format where options is a simple array or if there was no check expected
        $options = $scene_data->options;
    } else {
        // Fallback to empty array if no valid options found
        write_debug_log("Warning: No valid options found in scene data.", ['scene_data' => $scene_data]);
        $options = [];
    }

    foreach ($options as $index => $option) {
        $number = $index + 1;
        echo Utils::colorize("[cyan]{$number}. {$option}[/cyan]\n");
    }
    
    // Clear the check result AFTER displaying the scene based on it
    if ($last_check) {
        $gameState->clearLastCheckResult();
    }
}

?>
