<?php

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

// Check if debug mode is enabled
$debugging = in_array('--debug', $argv);

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

// Function to write to debug log
function write_debug_log($message, $context = null) {
    global $debugging, $config;
    if (!$debugging) return;

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

// Initialize components
$gameState = new GameState($config);
$imageHandler = new ImageHandler($config, $debugging);
$apiHandler = new ApiHandler($config, get_api_key($config['paths']['api_key_file']));

// Initialize game variables
$generate_image_toggle = $config['game']['generate_image_toggle'] ?? true;
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
    $title_art = $imageHandler->generateTitleScreen();
    if ($title_art) {
        echo "\n" . $title_art . "\n\n";
    }
    
    $imageHandler->clearImages();
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

    if (in_array(strtolower($user_input), ['t', 'g', 'n', 'q'])) {
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
    
    if ($response && isset($response->choices[0]->message)) {
        $message = $response->choices[0]->message;
        $gameState->addMessage('assistant', '', $message->function_call);
        
        if (isset($message->function_call->arguments)) {
            $scene_data = json_decode($message->function_call->arguments);
            if ($scene_data) {
                // Generate and display image if enabled
                if ($generate_image_toggle && isset($scene_data->image)) {
                    $ascii_art = $imageHandler->generateImage($scene_data->image->prompt, time());
                    if ($ascii_art) {
                        echo "\n" . $ascii_art . "\n\n";
                    }
                }
                
                // Display scene text
                $narrative = Utils::wrapText($scene_data->narrative);
                echo "\n" . Utils::colorize($narrative) . "\n\n";
                echo Utils::colorize("\n[bold]Choose your next action:[/bold]\n");
                
                foreach ($scene_data->options as $index => $option) {
                    $number = $index + 1;
                    echo Utils::colorize("[cyan]{$number}. {$option}[/cyan]\n");
                }
                
                // Display additional options
                echo "\n";
                echo Utils::colorize("[green](t) Type in your own action[/green]");
                echo " | ";
                echo Utils::colorize("[green](g) Generate Images (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
                echo " | ";
                echo Utils::colorize("[green](q) Quit the game[/green]");
                echo " | ";
                echo Utils::colorize("[green](n) Start a new game[/green]");
                echo "\n";
            }
        }
    }
    $should_make_api_call = false;
} else {
    // Display the last scene if it exists
    $lastMessage = end($conversation);
    if (isset($lastMessage['function_call']) && isset($lastMessage['function_call']->arguments)) {
        $scene_data = json_decode($lastMessage['function_call']->arguments);
        if ($scene_data) {
            // Generate and display image if enabled
            if ($generate_image_toggle && isset($scene_data->image)) {
                $ascii_art = $imageHandler->generateImage($scene_data->image->prompt, time());
                if ($ascii_art) {
                    echo "\n" . $ascii_art . "\n\n";
                }
            }
            
            // Display scene text
            $narrative = Utils::wrapText($scene_data->narrative);
            echo "\n" . Utils::colorize($narrative) . "\n\n";
            echo Utils::colorize("\n[bold]Choose your next action:[/bold]\n");
            
            foreach ($scene_data->options as $index => $option) {
                $number = $index + 1;
                echo Utils::colorize("[cyan]{$number}. {$option}[/cyan]\n");
            }
            
            // Display additional options
            echo "\n";
            echo Utils::colorize("[green](t) Type in your own action[/green]");
            echo " | ";
            echo Utils::colorize("[green](g) Generate Images (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
            echo " | ";
            echo Utils::colorize("[green](q) Quit the game[/green]");
            echo " | ";
            echo Utils::colorize("[green](n) Start a new game[/green]");
            echo "\n";
        }
    }
}

// Initialize game state
$gameState = new GameState($config);

// Load previous game state if it exists
if (file_exists($config['paths']['game_history_file'])) {
    write_debug_log("Loading game history from: " . $config['paths']['game_history_file']);
    $conversation = $gameState->getConversation();
    write_debug_log("Conversation loaded", ['conversation_count' => count($conversation)]);
    
    // Get the last scene data
    $scene_data = $gameState->getSceneData();
    if ($scene_data) {
        write_debug_log("Found previous scene data");
        $narrative = Utils::wrapText($scene_data->narrative);
        echo "\n" . Utils::colorize($narrative) . "\n\n";
        echo Utils::colorize("\n[bold]Choose your next action:[/bold]\n");
        foreach ($scene_data->options as $index => $option) {
            $number = $index + 1;
            echo Utils::colorize("[cyan]{$number}. {$option}[/cyan]\n");
        }
    } else {
        write_debug_log("No valid scene data found, starting fresh");
        $last_user_input = 'start game';
        $should_make_api_call = true;
    }
} else {
    write_debug_log("No game history file found at: " . $config['paths']['game_history_file']);
}

// Function to display the game menu
function displayGameMenu($generate_image_toggle) {
    echo "\n";
    echo Utils::colorize("[green](t) Type in your own action[/green]");
    echo " | ";
    echo Utils::colorize("[green](g) Generate Images (" . ($generate_image_toggle ? "On" : "Off") . ")[/green]");
    echo " | ";
    echo Utils::colorize("[green](q) Quit the game[/green]");
    echo " | ";
    echo Utils::colorize("[green](n) Start a new game[/green]");
    echo "\n";
}

// Function to display scene
function displayScene($scene_data, $generate_image_toggle = true, $imageHandler = null) {
    if ($generate_image_toggle && isset($scene_data->image) && $imageHandler) {
        $ascii_art = $imageHandler->generateImage($scene_data->image->prompt, time());
        if ($ascii_art) {
            echo "\n" . $ascii_art . "\n\n";
        }
    }
    
    $narrative = Utils::wrapText($scene_data->narrative);
    echo "\n" . Utils::colorize($narrative) . "\n\n";
    echo Utils::colorize("\n[bold]Choose your next action:[/bold]\n");
    foreach ($scene_data->options as $index => $option) {
        $number = $index + 1;
        echo Utils::colorize("[cyan]{$number}. {$option}[/cyan]\n");
    }
}

// Main game loop
while (true) {
    try {
        displayGameMenu($generate_image_toggle);
        
        // Get user input
        echo Utils::colorize("\n[cyan]Your choice: [/cyan]");
        $user_input = strtolower(trim(readline()));
        
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
                // Clear game history and start new game
                if (file_exists($config['paths']['game_history_file'])) {
                    unlink($config['paths']['game_history_file']);
                }
                echo "Starting a new game...\n";
                $gameState = new GameState($config);
                $gameState->addMessage('system', $config['system_prompt']);
                $gameState->addMessage('user', 'start game');
                $should_make_api_call = true;
                $last_user_input = 'start game';
                break;
                
            case 'g':
                $generate_image_toggle = !$generate_image_toggle;
                echo Utils::colorize("\n[green]Image generation is now " . ($generate_image_toggle ? "enabled" : "disabled") . ".[/green]\n");
                // Redisplay the current scene
                if ($scene_data) {
                    displayScene($scene_data, $generate_image_toggle, $imageHandler);
                }
                continue 2;
                
            case 't':
                echo Utils::colorize("\n[cyan]Type your action: [/cyan]");
                $custom_action = trim(readline());
                
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
                
            default:
                if (preg_match('/^[1-4]$/', $user_input) && $scene_data && isset($scene_data->options[$user_input - 1])) {
                    $chosen_option = $scene_data->options[$user_input - 1];
                    $last_user_input = $chosen_option;
                    $gameState->addMessage('user', $chosen_option);
                    $should_make_api_call = true;
                } else {
                    echo Utils::colorize("\n[red]Invalid choice. Please enter a number between 1-4 or use one of the menu options (t/g/q/n).[/red]\n");
                    continue 2;
                }
                break;
        }
        
        // Make API call if needed
        if ($should_make_api_call) {
            write_debug_log("Making API call", [
                'last_user_input' => $last_user_input,
                'conversation_count' => count($gameState->getConversation())
            ]);
            
            $response = $apiHandler->makeApiCall($gameState->getConversation());
            
            if ($response && isset($response->choices[0]->message)) {
                $message = $response->choices[0]->message;
                $gameState->addMessage('assistant', '', $message->function_call);
                
                if (isset($message->function_call->arguments)) {
                    $scene_data = json_decode($message->function_call->arguments);
                    if ($scene_data) {
                        displayScene($scene_data, $generate_image_toggle, $imageHandler);
                    } else {
                        write_debug_log("Failed to parse scene data", [
                            'function_call_arguments' => $message->function_call->arguments
                        ]);
                        echo Utils::colorize("\n[red]Error: Failed to parse scene data. Please try again.[/red]\n");
                    }
                } else {
                    write_debug_log("No function call arguments in response");
                    echo Utils::colorize("\n[red]Error: Invalid response from API. Please try again.[/red]\n");
                }
            } else {
                write_debug_log("Invalid API response", [
                    'response' => $response
                ]);
                echo Utils::colorize("\n[red]Error: Failed to get response from API. Please try again.[/red]\n");
            }
            $should_make_api_call = false;
        }
        
    } catch (Exception $e) {
        write_debug_log("Error in game loop: " . $e->getMessage());
        echo Utils::colorize("[red]An error occurred: " . $e->getMessage() . "\nPlease try again.[/red]\n");
        continue;
    }
}

?>
