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
     * Fetch models from OpenRouter API
     */
    private function fetchOpenRouterModels() {
        $cache_file = __DIR__ . '/openrouter_models_cache.json';
        $cache_duration = 3600; // 1 hour cache
        
        // Check if cache exists and is fresh
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file), true);
            if (isset($cache_data['timestamp']) && (time() - $cache_data['timestamp'] < $cache_duration)) {
                return $cache_data['models'];
            }
        }
        
        echo Utils::colorize("\n[dim]Fetching latest models from OpenRouter...[/dim]\n");
        
        // Fetch from API
        $ch = curl_init('https://openrouter.ai/api/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                $models = [];
                
                // Process and organize models
                foreach ($data['data'] as $model) {
                    $id = $model['id'];
                    $name = $model['name'] ?? $id;
                    
                    // Add context information
                    $info = [];
                    
                    if (isset($model['context_length'])) {
                        $context = $model['context_length'];
                        if ($context >= 128000) {
                            $info[] = round($context / 1000) . "k ctx";
                        }
                    }
                    
                    $is_free = false;
                    if (isset($model['pricing'])) {
                        $input_price = ($model['pricing']['prompt'] ?? 0) * 1000000;
                        if ($input_price == 0) {
                            $info[] = "🆓 FREE";
                            $is_free = true;
                        } else if ($input_price < 0.5) {
                            $info[] = "$";
                        } else if ($input_price < 2) {
                            $info[] = "$$";
                        } else {
                            $info[] = "$$$";
                        }
                    }
                    
                    // Check if model ID contains ":free" suffix
                    if (strpos($id, ':free') !== false) {
                        if (!$is_free) {
                            $info[] = "🆓 FREE";
                        }
                    }
                    
                    // Check capabilities
                    if (isset($model['architecture']['modality'])) {
                        $modality = $model['architecture']['modality'];
                        // Handle both string and array formats
                        if (is_string($modality)) {
                            if (strpos($modality, 'image') !== false || strpos($modality, 'vision') !== false) {
                                $info[] = "Vision";
                            }
                            if (strpos($modality, 'audio') !== false || strpos($modality, 'speech') !== false) {
                                $info[] = "Audio";
                            }
                        } else if (is_array($modality)) {
                            if (in_array('image', $modality)) {
                                $info[] = "Vision";
                            }
                            if (in_array('audio', $modality)) {
                                $info[] = "Audio";
                            }
                        }
                    }
                    
                    $display_name = $name;
                    if (!empty($info)) {
                        $display_name .= " (" . implode(", ", $info) . ")";
                    }
                    
                    $models[$id] = $display_name;
                }
                
                // Cache the results
                $cache_data = [
                    'timestamp' => time(),
                    'models' => $models
                ];
                file_put_contents($cache_file, json_encode($cache_data));
                
                return $models;
            }
        }
        
        // Fallback to default models if API fails
        echo Utils::colorize("[yellow]Could not fetch latest models, using default list.[/yellow]\n");
        return $this->config['providers']['openrouter']['models'];
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
        
        // Get models based on provider
        if ($this->provider === 'openrouter') {
            // Fetch dynamic model list for OpenRouter
            $all_models = $this->fetchOpenRouterModels();
            $models = array_keys($all_models);
            
            // Use fetched models instead of static config
            $provider_config['models'] = $all_models;
            // Organize models for better display
            $priority_providers = ['openai', 'anthropic', 'google', 'meta-llama', 'mistralai', 'x-ai', 'deepseek'];
            
            // Extract free models first
            $free_models = [];
            foreach ($all_models as $model_id => $model_name) {
                if (strpos($model_name, '🆓 FREE') !== false || strpos($model_id, ':free') !== false) {
                    $free_models[$model_id] = $model_name;
                }
            }
            
            // Popular paid models
            $popular_models = [
                'openai/gpt-4o',
                'openai/gpt-4o-mini', 
                'anthropic/claude-3.5-sonnet',
                'anthropic/claude-3-haiku',
                'google/gemini-pro-1.5',
                'meta-llama/llama-3.3-70b-instruct',
                'mistralai/mistral-large',
                'x-ai/grok-beta',
                'deepseek/deepseek-chat'
            ];
            
            // Show FREE models first (highlighted)
            echo Utils::colorize("[bold][green]━━━ 🆓 FREE MODELS ━━━[/green][/bold]\n");
            echo Utils::colorize("[dim]No API costs - great for testing and development![/dim]\n\n");
            $model_index = 1;
            $model_map = [];
            
            // Venice uncensored model first if available
            $venice_model = 'cognitivecomputations/dolphin-mistral-24b-venice-edition:free';
            if (isset($free_models[$venice_model])) {
                $model_map[$model_index] = $venice_model;
                echo Utils::colorize(sprintf(
                    "[cyan]%2d.[/cyan] [bold][green]⭐ %s[/green][/bold] [yellow](DEFAULT)[/yellow]\n",
                    $model_index,
                    $free_models[$venice_model]
                ));
                $model_index++;
                unset($free_models[$venice_model]);
            }
            
            // Show up to 9 more free models
            $free_count = 0;
            foreach ($free_models as $model_id => $model_name) {
                if ($free_count < 9) {
                    $model_map[$model_index] = $model_id;
                    echo Utils::colorize(sprintf(
                        "[cyan]%2d.[/cyan] [green]%s[/green]\n",
                        $model_index,
                        $model_name
                    ));
                    $model_index++;
                    $free_count++;
                } else {
                    break;
                }
            }
            
            if (count($free_models) > 9) {
                echo Utils::colorize("[dim]     ... and " . (count($free_models) - 9) . " more free models (type 'free' to see all)[/dim]\n");
            }
            
            // Show popular paid models
            echo Utils::colorize("\n[yellow]━━━ Popular Paid Models ━━━[/yellow]\n");
            
            foreach ($popular_models as $model) {
                if (isset($all_models[$model]) && strpos($all_models[$model], '🆓 FREE') === false) {
                    $model_map[$model_index] = $model;
                    echo Utils::colorize(sprintf(
                        "[cyan]%2d.[/cyan] [green]%s[/green]\n",
                        $model_index,
                        $all_models[$model]
                    ));
                    $model_index++;
                }
            }
            
            echo Utils::colorize("\n[yellow]━━━ All Models by Provider ━━━[/yellow]\n");
            echo Utils::colorize("[dim](Showing first 5 per provider, type 'more' to see all, 'free' to see all free models)[/dim]\n\n");
            
            // Group remaining models
            $grouped_models = [];
            foreach ($models as $model) {
                if (!in_array($model, $popular_models)) {
                    $parts = explode('/', $model);
                    $provider_name = $parts[0] ?? 'other';
                    if (!isset($grouped_models[$provider_name])) {
                        $grouped_models[$provider_name] = [];
                    }
                    $grouped_models[$provider_name][] = $model;
                }
            }
            
            // Show priority providers first
            foreach ($priority_providers as $provider_name) {
                if (isset($grouped_models[$provider_name]) && !empty($grouped_models[$provider_name])) {
                    echo Utils::colorize("[yellow]── " . ucfirst(str_replace('-', ' ', $provider_name)) . " ──[/yellow]\n");
                    $shown = 0;
                    foreach ($grouped_models[$provider_name] as $model) {
                        if ($shown < 5) {
                            $model_map[$model_index] = $model;
                            echo Utils::colorize(sprintf(
                                "[cyan]%2d.[/cyan] [green]%s[/green]\n",
                                $model_index,
                                $all_models[$model]
                            ));
                            $model_index++;
                            $shown++;
                        }
                    }
                    if (count($grouped_models[$provider_name]) > 5) {
                        echo Utils::colorize("[dim]     ... and " . (count($grouped_models[$provider_name]) - 5) . " more[/dim]\n");
                    }
                    echo "\n";
                }
            }
            
            $model_choice = null;
            while ($model_choice === null) {
                $input = readline(Utils::colorize("[cyan]Enter your choice (1-" . ($model_index - 1) . ") or model ID: [/cyan]"));
                
                // Check if it's a number
                if (is_numeric($input)) {
                    $choice_num = intval($input);
                    if ($choice_num >= 1 && $choice_num < $model_index) {
                        $model_choice = $model_map[$choice_num];
                    } else {
                        echo Utils::colorize("[red]Invalid choice number. Please try again.[/red]\n");
                    }
                } else {
                    // Check if it's a model ID
                    $input_lower = strtolower(trim($input));
                    if ($input_lower === 'more') {
                        // Show all models
                        echo Utils::colorize("\n[yellow]━━━ All Available Models (" . count($models) . " total) ━━━[/yellow]\n");
                        foreach ($models as $model) {
                            if (!isset($model_map[$model_index])) {
                                $model_map[$model_index] = $model;
                                $display_name = $all_models[$model];
                                // Highlight free models
                                if (strpos($display_name, '🆓 FREE') !== false) {
                                    echo Utils::colorize(sprintf(
                                        "[cyan]%3d.[/cyan] [bold][green]%s[/green][/bold] - %s\n",
                                        $model_index,
                                        $model,
                                        $display_name
                                    ));
                                } else {
                                    echo Utils::colorize(sprintf(
                                        "[cyan]%3d.[/cyan] [green]%s[/green] - %s\n",
                                        $model_index,
                                        $model,
                                        $display_name
                                    ));
                                }
                                $model_index++;
                            }
                        }
                        echo "\n";
                    } else if ($input_lower === 'free') {
                        // Show all free models
                        echo Utils::colorize("\n[bold][green]━━━ All FREE Models ━━━[/green][/bold]\n");
                        $free_count = 0;
                        foreach ($models as $model) {
                            if (strpos($all_models[$model], '🆓 FREE') !== false || strpos($model, ':free') !== false) {
                                if (!isset($model_map[$model_index])) {
                                    $model_map[$model_index] = $model;
                                    echo Utils::colorize(sprintf(
                                        "[cyan]%3d.[/cyan] [bold][green]%s[/green][/bold]\n        %s\n",
                                        $model_index,
                                        $model,
                                        $all_models[$model]
                                    ));
                                    $model_index++;
                                    $free_count++;
                                }
                            }
                        }
                        echo Utils::colorize("\n[green]Total free models: " . $free_count . "[/green]\n\n");
                    } else if (isset($all_models[$input_lower])) {
                        $model_choice = $input_lower;
                    } else {
                        // Try to match partial model ID
                        $matches = array_filter($models, function($m) use ($input_lower) {
                            return strpos(strtolower($m), $input_lower) !== false;
                        });
                        
                        if (count($matches) === 1) {
                            $model_choice = reset($matches);
                        } else if (count($matches) > 1) {
                            echo Utils::colorize("[yellow]Multiple matches found:[/yellow]\n");
                            foreach (array_slice($matches, 0, 10) as $match) {
                                echo Utils::colorize("  • " . $match . "\n");
                            }
                            if (count($matches) > 10) {
                                echo Utils::colorize("[dim]  ... and " . (count($matches) - 10) . " more[/dim]\n");
                            }
                        } else {
                            echo Utils::colorize("[red]Model not found. Please try again.[/red]\n");
                        }
                    }
                }
            }
        } else {
            // For other providers (like OpenAI), use static config
            $models = array_keys($provider_config['models']);
            
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
        
        // Get model display name
        $model_display = $this->model;
        if ($this->provider === 'openrouter') {
            // For OpenRouter, fetch models to get the proper display name
            $models = $this->fetchOpenRouterModels();
            if (isset($models[$this->model])) {
                $model_display = $models[$this->model];
            } else {
                // Fallback display for Venice model
                if ($this->model === 'cognitivecomputations/dolphin-mistral-24b-venice-edition:free') {
                    $model_display = "Venice: Uncensored (free) (🆓 FREE)";
                }
            }
        } else if (isset($provider_config['models'][$this->model])) {
            $model_display = $provider_config['models'][$this->model];
        }
        
        echo Utils::colorize("[bold]Model:[/bold] [green]" . $model_display . "[/green]\n");
        echo Utils::colorize("[bold]Model ID:[/bold] [dim]" . $this->model . "[/dim]\n");
        echo Utils::colorize("[bold]API Key:[/bold] [dim]" . substr($this->api_key, 0, 8) . "..." . substr($this->api_key, -4) . "[/dim]\n");
        
        echo Utils::colorize("\n[bold][cyan]═══════════════════════════════════════════════════════[/cyan][/bold]\n\n");
    }
}
