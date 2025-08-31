<?php
/**
 * Test script to verify AI provider integration
 * Tests both OpenAI and OpenRouter connectivity
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
use App\ProviderManager;

// Load configuration
$config = require __DIR__ . '/app/config.php';

echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n");
echo Utils::colorize("[bold][yellow]        AI Provider Integration Test[/yellow][/bold]\n");
echo Utils::colorize("[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");

// Check if provider is configured
$providerManager = new ProviderManager($config, false);

if (!$providerManager->isConfigured()) {
    echo Utils::colorize("[red]No provider configured.[/red]\n");
    echo Utils::colorize("[yellow]Run 'php game.php --setup' to configure a provider first.[/yellow]\n\n");
    exit(1);
}

// Display current configuration
$provider = $providerManager->getProvider();
$model = $providerManager->getModel();
$provider_config = $providerManager->getProviderConfig();

echo Utils::colorize("[bold]Current Configuration:[/bold]\n");
echo Utils::colorize("[green]Provider:[/green] " . $provider_config['name'] . "\n");
echo Utils::colorize("[green]Model:[/green] " . $model . "\n");
echo Utils::colorize("[green]API URL:[/green] " . $providerManager->getChatUrl() . "\n\n");

// Test API connectivity
echo Utils::colorize("[bold]Testing API Connectivity...[/bold]\n\n");

$test_message = "Respond with exactly: 'Connection successful'";

$data = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'You are a test assistant. Follow instructions exactly.'],
        ['role' => 'user', 'content' => $test_message]
    ],
    'max_tokens' => 50,
    'temperature' => 0
];

// Add provider-specific parameters if needed
$extra_params = $providerManager->getExtraBodyParams();
if (!empty($extra_params)) {
    $data = array_merge($data, $extra_params);
}

// Make test API call
$ch = curl_init($providerManager->getChatUrl());
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $providerManager->getHeaders());
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

echo Utils::colorize("[dim]Sending test request...[/dim]\n");

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo Utils::colorize("[red]✗ Connection failed:[/red] " . $curl_error . "\n\n");
    exit(1);
}

if ($http_code !== 200) {
    echo Utils::colorize("[red]✗ API Error (HTTP $http_code)[/red]\n");
    
    $error_data = json_decode($response, true);
    if (isset($error_data['error']['message'])) {
        echo Utils::colorize("[red]Error message:[/red] " . $error_data['error']['message'] . "\n");
    } else {
        echo Utils::colorize("[red]Response:[/red] " . substr($response, 0, 200) . "\n");
    }
    
    if ($http_code === 401) {
        echo Utils::colorize("\n[yellow]Authentication failed. Please check your API key.[/yellow]\n");
        echo Utils::colorize("[yellow]Run 'php game.php --setup' to reconfigure.[/yellow]\n");
    } else if ($http_code === 404) {
        echo Utils::colorize("\n[yellow]Model not found. The selected model may not be available.[/yellow]\n");
        echo Utils::colorize("[yellow]Run 'php game.php --setup' to select a different model.[/yellow]\n");
    } else if ($http_code === 429) {
        echo Utils::colorize("\n[yellow]Rate limit exceeded. Please wait a moment and try again.[/yellow]\n");
    }
    
    echo "\n";
    exit(1);
}

// Parse response
$response_data = json_decode($response, true);

if (!isset($response_data['choices'][0]['message']['content'])) {
    echo Utils::colorize("[red]✗ Unexpected response format[/red]\n");
    echo Utils::colorize("[dim]Response: " . substr($response, 0, 200) . "[/dim]\n\n");
    exit(1);
}

$ai_response = $response_data['choices'][0]['message']['content'];

echo Utils::colorize("[green]✓ Connection successful![/green]\n");
echo Utils::colorize("[green]Response:[/green] " . $ai_response . "\n\n");

// Display usage information if available
if (isset($response_data['usage'])) {
    echo Utils::colorize("[bold]Token Usage:[/bold]\n");
    echo Utils::colorize("[dim]Prompt tokens: " . $response_data['usage']['prompt_tokens'] . "[/dim]\n");
    echo Utils::colorize("[dim]Completion tokens: " . $response_data['usage']['completion_tokens'] . "[/dim]\n");
    echo Utils::colorize("[dim]Total tokens: " . $response_data['usage']['total_tokens'] . "[/dim]\n\n");
}

// Test function calling support
echo Utils::colorize("[bold]Testing Function Calling Support...[/bold]\n");

$function_test_data = $data;
$function_test_data['functions'] = [
    [
        'name' => 'test_function',
        'description' => 'A test function',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'A test message'
                ]
            ],
            'required' => ['message']
        ]
    ]
];
$function_test_data['function_call'] = 'auto';

$ch = curl_init($providerManager->getChatUrl());
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($function_test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $providerManager->getHeaders());
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo Utils::colorize("[green]✓ Function calling is supported[/green]\n");
} else {
    echo Utils::colorize("[yellow]⚠ Function calling may not be supported by this model[/yellow]\n");
}

echo Utils::colorize("\n[bold][green]═══════════════════════════════════════════════════════[/green][/bold]\n");
echo Utils::colorize("[bold][green]        All tests completed successfully![/green][/bold]\n");
echo Utils::colorize("[bold][green]═══════════════════════════════════════════════════════[/green][/bold]\n\n");
echo Utils::colorize("[green]Your configuration is working correctly.[/green]\n");
echo Utils::colorize("[green]You can now run 'php game.php' to start playing![/green]\n\n");
