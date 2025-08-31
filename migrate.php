<?php
/**
 * Migration script for existing users
 * Converts old OpenAI-only configuration to new multi-provider format
 */

// Simple autoloader
spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Utils;

// Load configuration
$config = require __DIR__ . '/app/config.php';

echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n");
echo Utils::colorize("[bold][yellow]        The Dying Earth - Configuration Migration[/yellow][/bold]\n");
echo Utils::colorize("[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");

// Check for old OpenAI API key file
$old_api_key_file = __DIR__ . '/.data/.openai_api_key';
$new_api_key_file = $config['paths']['api_key_file'];
$provider_config_file = $config['paths']['provider_config_file'];

if (!file_exists($old_api_key_file)) {
    echo Utils::colorize("[yellow]No existing OpenAI configuration found.[/yellow]\n");
    echo Utils::colorize("[green]Run 'php game.php' to set up your AI provider.[/green]\n\n");
    exit(0);
}

if (file_exists($provider_config_file)) {
    echo Utils::colorize("[yellow]New configuration already exists.[/yellow]\n");
    $overwrite = readline(Utils::colorize("[cyan]Do you want to overwrite it? (y/n): [/cyan]"));
    if (strtolower(trim($overwrite)) !== 'y') {
        echo Utils::colorize("\n[green]Migration cancelled.[/green]\n\n");
        exit(0);
    }
}

// Read old API key
$api_key = trim(file_get_contents($old_api_key_file));

if (empty($api_key)) {
    echo Utils::colorize("[red]Error: Empty API key found in old configuration.[/red]\n");
    exit(1);
}

echo Utils::colorize("[green]✓ Found existing OpenAI API key[/green]\n\n");

// Ask user what they want to do
echo Utils::colorize("[bold]What would you like to do?[/bold]\n\n");
echo Utils::colorize("[cyan]1.[/cyan] [green]Continue using OpenAI[/green] (migrate existing configuration)\n");
echo Utils::colorize("[cyan]2.[/cyan] [green]Switch to OpenRouter[/green] (set up new provider)\n");
echo Utils::colorize("[cyan]3.[/cyan] [green]Cancel migration[/green]\n\n");

$choice = null;
while ($choice === null) {
    $input = readline(Utils::colorize("[cyan]Enter your choice (1-3): [/cyan]"));
    $choice_num = intval($input);
    if ($choice_num >= 1 && $choice_num <= 3) {
        $choice = $choice_num;
    } else {
        echo Utils::colorize("[red]Invalid choice. Please try again.[/red]\n");
    }
}

echo "\n";

switch ($choice) {
    case 1:
        // Migrate to OpenAI in new format
        echo Utils::colorize("[green]Migrating to new configuration format with OpenAI...[/green]\n\n");
        
        // Select OpenAI model
        $openai_models = array_keys($config['providers']['openai']['models']);
        echo Utils::colorize("[bold]Select your preferred OpenAI model:[/bold]\n\n");
        
        foreach ($openai_models as $i => $model) {
            echo Utils::colorize(sprintf(
                "[cyan]%d.[/cyan] [green]%s[/green]\n",
                $i + 1,
                $config['providers']['openai']['models'][$model]
            ));
        }
        
        echo "\n";
        $model_choice = null;
        while ($model_choice === null) {
            $input = readline(Utils::colorize("[cyan]Enter your choice (1-" . count($openai_models) . "): [/cyan]"));
            $choice_num = intval($input);
            if ($choice_num >= 1 && $choice_num <= count($openai_models)) {
                $model_choice = $openai_models[$choice_num - 1];
            } else {
                echo Utils::colorize("[red]Invalid choice. Please try again.[/red]\n");
            }
        }
        
        // Save new configuration
        $provider_config = [
            'provider' => 'openai',
            'model' => $model_choice
        ];
        
        // Ensure data directory exists
        $data_dir = dirname($provider_config_file);
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
        
        // Save provider config
        file_put_contents($provider_config_file, json_encode($provider_config, JSON_PRETTY_PRINT), LOCK_EX);
        chmod($provider_config_file, 0600);
        
        // Copy API key to new location
        file_put_contents($new_api_key_file, $api_key, LOCK_EX);
        chmod($new_api_key_file, 0600);
        
        echo Utils::colorize("\n[bold][green]✓ Migration completed successfully![/green][/bold]\n");
        echo Utils::colorize("[green]Provider:[/green] OpenAI\n");
        echo Utils::colorize("[green]Model:[/green] " . $config['providers']['openai']['models'][$model_choice] . "\n");
        
        // Optionally remove old file
        echo Utils::colorize("\n[yellow]The old configuration file can be safely removed.[/yellow]\n");
        $remove = readline(Utils::colorize("[cyan]Remove old configuration file? (y/n): [/cyan]"));
        if (strtolower(trim($remove)) === 'y') {
            unlink($old_api_key_file);
            echo Utils::colorize("[green]Old configuration file removed.[/green]\n");
        }
        
        echo Utils::colorize("\n[bold][green]You can now run 'php game.php' to start playing![/green][/bold]\n\n");
        break;
        
    case 2:
        // Switch to OpenRouter
        echo Utils::colorize("[yellow]To switch to OpenRouter:[/yellow]\n\n");
        echo Utils::colorize("1. Get an API key from: [cyan]https://openrouter.ai/keys[/cyan]\n");
        echo Utils::colorize("2. Run: [cyan]php game.php --setup[/cyan]\n");
        echo Utils::colorize("3. Select OpenRouter as your provider\n");
        echo Utils::colorize("4. Enter your OpenRouter API key\n");
        echo Utils::colorize("5. Choose from 200+ available models\n\n");
        
        echo Utils::colorize("[dim]Your game history and saves will be preserved.[/dim]\n\n");
        break;
        
    case 3:
        echo Utils::colorize("[green]Migration cancelled.[/green]\n\n");
        break;
}
