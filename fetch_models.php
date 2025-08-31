<?php
/**
 * Fetch the complete list of available models from OpenRouter API
 * This script can be run to update the models list in the configuration
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

echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n");
echo Utils::colorize("[bold][yellow]        OpenRouter Model Fetcher[/yellow][/bold]\n");
echo Utils::colorize("[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");

echo Utils::colorize("[dim]Fetching latest models from OpenRouter API...[/dim]\n\n");

// Fetch models from OpenRouter API
$ch = curl_init('https://openrouter.ai/api/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo Utils::colorize("[red]Failed to fetch models: " . $curl_error . "[/red]\n");
    exit(1);
}

if ($http_code !== 200) {
    echo Utils::colorize("[red]API returned HTTP code: " . $http_code . "[/red]\n");
    exit(1);
}

$data = json_decode($response, true);

if (!isset($data['data']) || !is_array($data['data'])) {
    echo Utils::colorize("[red]Unexpected response format[/red]\n");
    exit(1);
}

$models = $data['data'];
echo Utils::colorize("[green]Found " . count($models) . " models![/green]\n\n");

// Group models by provider
$grouped_models = [];
$model_details = [];

foreach ($models as $model) {
    $id = $model['id'];
    $name = $model['name'] ?? $id;
    
    // Extract provider from ID (e.g., "openai/gpt-4" -> "openai")
    $parts = explode('/', $id);
    $provider = $parts[0] ?? 'other';
    
    if (!isset($grouped_models[$provider])) {
        $grouped_models[$provider] = [];
    }
    
    // Create a display name with context information
    $display_name = $name;
    
    // Add context information if available
    $context_info = [];
    
    if (isset($model['context_length'])) {
        $context = $model['context_length'];
        if ($context >= 128000) {
            $context_info[] = round($context / 1000) . "k context";
        }
    }
    
    $is_free = false;
    if (isset($model['pricing'])) {
        $input_price = $model['pricing']['prompt'] ?? 0;
        $output_price = $model['pricing']['completion'] ?? 0;
        
        // Convert from per-token to per-million tokens
        $input_price_per_m = $input_price * 1000000;
        $output_price_per_m = $output_price * 1000000;
        
        if ($input_price_per_m == 0) {
            $context_info[] = "🆓 FREE";
            $is_free = true;
        } else if ($input_price_per_m < 0.5) {
            $context_info[] = "Very cheap";
        } else if ($input_price_per_m < 2) {
            $context_info[] = "Affordable";
        }
    }
    
    // Check if model ID contains ":free" suffix
    if (strpos($id, ':free') !== false && !$is_free) {
        $context_info[] = "🆓 FREE";
        $is_free = true;
    }
    
    // Check for special capabilities
    $capabilities = [];
    if (isset($model['architecture']) && isset($model['architecture']['modality'])) {
        $modality = $model['architecture']['modality'];
        // Handle both string and array formats
        if (is_string($modality)) {
            if (strpos($modality, 'image') !== false || strpos($modality, 'vision') !== false) {
                $capabilities[] = "Vision";
            }
            if (strpos($modality, 'audio') !== false || strpos($modality, 'speech') !== false) {
                $capabilities[] = "Audio";
            }
        } else if (is_array($modality)) {
            if (in_array('image', $modality)) {
                $capabilities[] = "Vision";
            }
            if (in_array('audio', $modality)) {
                $capabilities[] = "Audio";
            }
        }
    }
    
    // Check for function calling support
    if (isset($model['supported_parameters']) && in_array('tools', $model['supported_parameters'])) {
        $capabilities[] = "Functions";
    }
    
    if (!empty($capabilities)) {
        $context_info[] = implode(', ', $capabilities);
    }
    
    if (!empty($context_info)) {
        $display_name .= " (" . implode(', ', $context_info) . ")";
    }
    
    $grouped_models[$provider][$id] = $display_name;
    $model_details[$id] = $model;
}

// Sort providers and models
ksort($grouped_models);
foreach ($grouped_models as &$models) {
    ksort($models);
}

// Count free models
$free_model_count = 0;
$free_models_list = [];
foreach ($grouped_models as $provider => $models) {
    foreach ($models as $id => $name) {
        if (strpos($name, '🆓 FREE') !== false || strpos($id, ':free') !== false) {
            $free_model_count++;
            $free_models_list[$id] = $name;
        }
    }
}

echo Utils::colorize("[bold][green]Found " . $free_model_count . " FREE models available![/green][/bold]\n\n");

// Display organized list
echo Utils::colorize("[bold]Models by Provider:[/bold]\n\n");

// Priority providers at the top
$priority_providers = ['openai', 'anthropic', 'google', 'meta-llama', 'mistralai', 'x-ai', 'deepseek'];
$other_providers = array_diff(array_keys($grouped_models), $priority_providers);

// Display priority providers first
foreach ($priority_providers as $provider) {
    if (isset($grouped_models[$provider])) {
        displayProviderModels($provider, $grouped_models[$provider]);
    }
}

// Display other providers
foreach ($other_providers as $provider) {
    displayProviderModels($provider, $grouped_models[$provider]);
}

function displayProviderModels($provider, $models) {
    $provider_display = ucfirst(str_replace(['-', '_'], ' ', $provider));
    
    // Count free models in this provider
    $free_count = 0;
    foreach ($models as $id => $name) {
        if (strpos($name, '🆓 FREE') !== false || strpos($id, ':free') !== false) {
            $free_count++;
        }
    }
    
    $header = "[yellow]━━━ " . $provider_display . " (" . count($models) . " models";
    if ($free_count > 0) {
        $header .= ", [green]" . $free_count . " FREE[/green]";
    }
    $header .= ") ━━━[/yellow]\n";
    echo Utils::colorize($header);
    
    $count = 0;
    foreach ($models as $id => $name) {
        $count++;
        if ($count <= 10) {
            // Highlight free models
            if (strpos($name, '🆓 FREE') !== false) {
                echo Utils::colorize("[dim]  • " . $id . "[/dim] - [bold][green]" . $name . "[/green][/bold]\n");
            } else {
                echo Utils::colorize("[dim]  • " . $id . "[/dim] - [green]" . $name . "[/green]\n");
            }
        }
    }
    
    if ($count > 10) {
        echo Utils::colorize("[dim]  ... and " . ($count - 10) . " more[/dim]\n");
    }
    
    echo "\n";
}

// Ask if user wants to update config
echo Utils::colorize("[bold]Do you want to generate an updated configuration file?[/bold]\n");
echo Utils::colorize("[dim]This will create a new file with all available models.[/dim]\n\n");

$choice = readline(Utils::colorize("[cyan]Generate config? (y/n): [/cyan]"));

if (strtolower(trim($choice)) === 'y') {
    // Generate PHP configuration array
    $config_content = "<?php\n\n";
    $config_content .= "/**\n";
    $config_content .= " * OpenRouter Models Configuration\n";
    $config_content .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
    $config_content .= " * Total models: " . count($models) . "\n";
    $config_content .= " */\n\n";
    $config_content .= "return [\n";
    
    // Add priority models for quick selection
    $config_content .= "    // Popular models for quick selection\n";
    $config_content .= "    'popular' => [\n";
    
    $popular_models = [
        'openai/gpt-4o' => 'GPT-4o (Most capable)',
        'openai/gpt-4o-mini' => 'GPT-4o Mini (Fast & affordable)',
        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (Best overall)',
        'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & cheap)',
        'google/gemini-pro-1.5' => 'Gemini Pro 1.5',
        'meta-llama/llama-3.1-405b-instruct' => 'Llama 3.1 405B',
        'mistralai/mistral-large' => 'Mistral Large',
        'x-ai/grok-beta' => 'Grok Beta',
        'deepseek/deepseek-chat' => 'DeepSeek Chat',
    ];
    
    foreach ($popular_models as $id => $name) {
        if (isset($model_details[$id])) {
            $config_content .= "        '" . $id . "' => '" . addslashes($name) . "',\n";
        }
    }
    
    $config_content .= "    ],\n\n";
    
    // Add all models grouped by provider
    $config_content .= "    // All models grouped by provider\n";
    $config_content .= "    'all' => [\n";
    
    foreach ($priority_providers as $provider) {
        if (isset($grouped_models[$provider])) {
            $config_content .= "        // " . ucfirst($provider) . " Models\n";
            foreach ($grouped_models[$provider] as $id => $name) {
                $config_content .= "        '" . $id . "' => '" . addslashes($name) . "',\n";
            }
            $config_content .= "\n";
        }
    }
    
    foreach ($other_providers as $provider) {
        $config_content .= "        // " . ucfirst($provider) . " Models\n";
        foreach ($grouped_models[$provider] as $id => $name) {
            $config_content .= "        '" . $id . "' => '" . addslashes($name) . "',\n";
        }
        $config_content .= "\n";
    }
    
    $config_content .= "    ],\n";
    $config_content .= "];\n";
    
    // Save to file
    $output_file = __DIR__ . '/app/openrouter_models.php';
    file_put_contents($output_file, $config_content);
    
    echo Utils::colorize("\n[green]✓ Configuration saved to: app/openrouter_models.php[/green]\n");
    echo Utils::colorize("[dim]You can now update app/config.php to use this dynamic list.[/dim]\n");
}

echo Utils::colorize("\n[bold][green]Done![/green][/bold]\n\n");
