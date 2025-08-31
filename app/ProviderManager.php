<?php

namespace App;

class ProviderManager {
    private $config;
    private $provider;
    private $api_key;
    private $model;
    private $debug;
    
    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
        $this->loadProviderSettings();
    }
    
    /**
     * Load saved provider settings or prompt for setup
     */
    public function loadProviderSettings() {
        $provider_config_file = $this->config['paths']['provider_config_file'];
        
        if (file_exists($provider_config_file)) {
            $saved_config = json_decode(file_get_contents($provider_config_file), true);
            $this->provider = $saved_config['provider'] ?? null;
            $this->model = $saved_config['model'] ?? null;
            
            // Load API key
            $api_key_file = $this->config['paths']['api_key_file'];
            if (file_exists($api_key_file)) {
                $this->api_key = trim(file_get_contents($api_key_file));
            }
        }
    }
    
    /**
     * Save provider settings
     */
    public function saveProviderSettings() {
        $provider_config = [
            'provider' => $this->provider,
            'model' => $this->model
        ];
        
        $provider_config_file = $this->config['paths']['provider_config_file'];
        $data_dir = dirname($provider_config_file);
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
        
        file_put_contents($provider_config_file, json_encode($provider_config, JSON_PRETTY_PRINT), LOCK_EX);
        chmod($provider_config_file, 0600);
        
        // Save API key
        if ($this->api_key) {
            $api_key_file = $this->config['paths']['api_key_file'];
            file_put_contents($api_key_file, $this->api_key, LOCK_EX);
            chmod($api_key_file, 0600);
        }
    }
    
    /**
     * Check if provider is configured
     */
    public function isConfigured() {
        return $this->provider && $this->api_key && $this->model;
    }
    
    /**
     * Interactive setup for provider configuration
     */
    public function setupProvider() {
        echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n");
        echo Utils::colorize("[bold][yellow]        AI Provider Configuration Setup[/yellow][/bold]\n");
        echo Utils::colorize("[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");
        
        // Select provider
        echo Utils::colorize("[bold]Select your AI provider:[/bold]\n\n");
        $providers = array_keys($this->config['providers']);
        foreach ($providers as $i => $provider) {
            $provider_config = $this->config['providers'][$provider];
            echo Utils::colorize(sprintf(
                "[cyan]%d.[/cyan] [green]%s[/green]\n",
                $i + 1,
                $provider_config['name']
            ));
        }
        
        echo "\n";
        $choice = null;
        while ($choice === null) {
            $input = readline(Utils::colorize("[cyan]Enter your choice (1-" . count($providers) . "): [/cyan]"));
            $choice_num = intval($input);
            if ($choice_num >= 1 && $choice_num <= count($providers)) {
                $choice = $providers[$choice_num - 1];
            } else {
                echo Utils::colorize("[red]Invalid choice. Please try again.[/red]\n");
            }
        }
        
        $this->provider = $choice;
        $provider_config = $this->config['providers'][$this->provider];
        
        echo Utils::colorize("\n[green]Selected: " . $provider_config['name'] . "[/green]\n\n");
        
        // Get API key
        echo Utils::colorize("[bold]Enter your API key:[/bold]\n");
        
        if ($this->provider === 'openai') {
            echo Utils::colorize("[dim]Get your API key from: https://platform.openai.com/api-keys[/dim]\n");
        } else if ($this->provider === 'openrouter') {
            echo Utils::colorize("[dim]Get your API key from: https://openrouter.ai/keys[/dim]\n");
        }
        
        echo "\n";
        $this->api_key = trim(readline(Utils::colorize("[cyan]API Key: [/cyan]")));
        
        // Select model
        echo Utils::colorize("\n[bold]Select your preferred model:[/bold]\n\n");
        $models = array_keys($provider_config['models']);
        
        // Group models by provider for OpenRouter
        if ($this->provider === 'openrouter') {
            $grouped_models = [];
            foreach ($models as $model) {
                $parts = explode('/', $model);
                $provider_name = $parts[0] ?? 'other';
                if (!isset($grouped_models[$provider_name])) {
                    $grouped_models[$provider_name] = [];
                }
                $grouped_models[$provider_name][] = $model;
            }
            
            $model_index = 1;
            $model_map = [];
            
            foreach ($grouped_models as $provider_name => $provider_models) {
                echo Utils::colorize("[yellow]── " . ucfirst($provider_name) . " Models ──[/yellow]\n");
                foreach ($provider_models as $model) {
                    $model_map[$model_index] = $model;
                    echo Utils::colorize(sprintf(
                        "[cyan]%2d.[/cyan] [green]%s[/green]\n",
                        $model_index,
                        $provider_config['models'][$model]
                    ));
                    $model_index++;
                }
                echo "\n";
            }
            
            $model_choice = null;
            while ($model_choice === null) {
                $input = readline(Utils::colorize("[cyan]Enter your choice (1-" . ($model_index - 1) . "): [/cyan]"));
                $choice_num = intval($input);
                if ($choice_num >= 1 && $choice_num < $model_index) {
                    $model_choice = $model_map[$choice_num];
                } else {
                    echo Utils::colorize("[red]Invalid choice. Please try again.[/red]\n");
                }
            }
        } else {
            // Simple list for OpenAI
            foreach ($models as $i => $model) {
                echo Utils::colorize(sprintf(
                    "[cyan]%d.[/cyan] [green]%s[/green]\n",
                    $i + 1,
                    $provider_config['models'][$model]
                ));
            }
            
            echo "\n";
            $model_choice = null;
            while ($model_choice === null) {
                $input = readline(Utils::colorize("[cyan]Enter your choice (1-" . count($models) . "): [/cyan]"));
                $choice_num = intval($input);
                if ($choice_num >= 1 && $choice_num <= count($models)) {
                    $model_choice = $models[$choice_num - 1];
                } else {
                    echo Utils::colorize("[red]Invalid choice. Please try again.[/red]\n");
                }
            }
        }
        
        $this->model = $model_choice;
        
        echo Utils::colorize("\n[green]Selected model: " . $provider_config['models'][$this->model] . "[/green]\n");
        
        // Save configuration
        $this->saveProviderSettings();
        
        echo Utils::colorize("\n[bold][green]✓ Configuration saved successfully![/green][/bold]\n");
        echo Utils::colorize("[dim]You can change these settings anytime by running the game with --setup flag[/dim]\n\n");
    }
    
    /**
     * Get the current provider
     */
    public function getProvider() {
        return $this->provider;
    }
    
    /**
     * Get the current model
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Get the API key
     */
    public function getApiKey() {
        return $this->api_key;
    }
    
    /**
     * Get provider configuration
     */
    public function getProviderConfig() {
        return $this->config['providers'][$this->provider] ?? null;
    }
    
    /**
     * Get the full API URL for chat completions
     */
    public function getChatUrl() {
        $provider_config = $this->getProviderConfig();
        if (!$provider_config) {
            throw new \Exception("Provider not configured");
        }
        return $provider_config['base_url'] . $provider_config['chat_endpoint'];
    }
    
    /**
     * Get headers for API requests
     */
    public function getHeaders() {
        $provider_config = $this->getProviderConfig();
        if (!$provider_config) {
            throw new \Exception("Provider not configured");
        }
        
        $headers = [];
        foreach ($provider_config['headers'] as $key => $value) {
            $headers[] = $key . ': ' . str_replace('{API_KEY}', $this->api_key, $value);
        }
        
        return $headers;
    }
    
    /**
     * Get extra body parameters for the API request
     */
    public function getExtraBodyParams() {
        $provider_config = $this->getProviderConfig();
        return $provider_config['extra_body'] ?? [];
    }
    
    /**
     * Display current configuration
     */
    public function displayConfiguration() {
        if (!$this->isConfigured()) {
            echo Utils::colorize("[red]No provider configured.[/red]\n");
            return;
        }
        
        $provider_config = $this->getProviderConfig();
        
        echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n");
        echo Utils::colorize("[bold][yellow]        Current AI Configuration[/yellow][/bold]\n");
        echo Utils::colorize("[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");
        
        echo Utils::colorize("[bold]Provider:[/bold] [green]" . $provider_config['name'] . "[/green]\n");
        echo Utils::colorize("[bold]Model:[/bold] [green]" . $provider_config['models'][$this->model] . "[/green]\n");
        echo Utils::colorize("[bold]Model ID:[/bold] [dim]" . $this->model . "[/dim]\n");
        echo Utils::colorize("[bold]API Key:[/bold] [dim]" . substr($this->api_key, 0, 8) . "..." . substr($this->api_key, -4) . "[/dim]\n");
        
        echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");
    }
}
