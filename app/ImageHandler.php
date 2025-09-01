<?php

namespace App;

class ImageHandler {
    private $config;
    private $debug;
    private $useChunky;
    private $api_key;
    private $use_openrouter;
    
    public function __construct($config, $debug = false, $useChunky = false) {
        $this->config = $config;
        $this->debug = $debug;
        $this->useChunky = $useChunky;
        
        // Check config for OpenRouter image generation preference
        $this->use_openrouter = ($config['image']['generation_service'] === 'openrouter' || 
                                 $config['image']['generation_service'] === 'both') &&
                                 ($config['image']['openrouter']['enabled'] ?? false);
        
        // Load API key for OpenRouter if enabled
        if ($this->use_openrouter) {
            $api_key_file = '.data/.api_key';
            if (file_exists($api_key_file)) {
                $this->api_key = trim(file_get_contents($api_key_file));
            } else {
                $this->use_openrouter = false; // Disable if no API key
                if ($this->debug) {
                    echo "[DEBUG] OpenRouter image generation disabled - no API key found\n";
                }
            }
        }
    }
    
    /**
     * Build a standardized URL for Pollinations.ai image generation
     * 
     * @param string $prompt The image generation prompt
     * @param int $timestamp Timestamp used as seed
     * @param int $width Image width (default: 640 for 16:9)
     * @param int $height Image height (default: 360 for 16:9)
     * @param string $model Model to use (default: turbo)
     * @param string $negative_prompt Negative prompt (default: text)
     * @return string The fully constructed URL
     */
    private function buildPollinationsUrl($prompt, $timestamp, $width = 640, $height = 360, $model = 'turbo', $negative_prompt = 'text') {
        // Sanitize prompt: remove newlines before URL encoding
        $prompt = str_replace(["\n", "\r"], ' ', $prompt);
        $prompt_url = urlencode($prompt);
        
        return "https://image.pollinations.ai/prompt/$prompt_url"
             . "?negative_prompt=$negative_prompt"
             . "&nologo=true"
             . "&width=$width"
             . "&height=$height"
             . "&seed=$timestamp"
             . "&model=$model";
    }
    
    /**
     * Generate image using OpenRouter with Gemini 2.5 Flash Image Preview
     * 
     * @param string $prompt The image generation prompt
     * @param int $timestamp Timestamp for seeding
     * @param string $save_path Path to save the image
     * @return bool True if successful, false otherwise
     */
    private function generateWithOpenRouter($prompt, $timestamp, $save_path) {
        if (!$this->api_key) {
            if ($this->debug) {
                echo "[DEBUG] No API key available for OpenRouter\n";
            }
            return false;
        }
        
        write_debug_log("Attempting OpenRouter image generation", ['prompt' => substr($prompt, 0, 100)]);
        
        // Build request for Gemini 2.5 Flash Image Preview
        $model = $this->config['image']['openrouter']['model'] ?? 'google/gemini-2.5-flash-image-preview:free';
        
        // Create prompt for 8-bit pixel art generation
        // Note: Gemini through OpenRouter seems to generate square images by default
        // We'll request widescreen but may need to crop/adjust in post-processing
        $image_prompt = "Generate a ratio 1:1 aspect ratio 8-bit pixel art image. " .
                       "The image must not contain any English text, unless it's part of the `Content:` prompt." .
                       "Content: $prompt. " .
                       "Style: retro gaming, limited color palette, nostalgic 8-bit graphics, pixelated. ";
        
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an image generation AI.'
                ],
                [
                    'role' => 'user',
                    'content' => $image_prompt
                ]
            ],
            'modalities' => ['image', 'text'],  // CRITICAL: This tells OpenRouter to generate an image!
            'max_tokens' => 2000, // Images need more tokens (1290 per image according to docs)
            'temperature' => 0.8
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
            'HTTP-Referer: https://github.com/the-dying-earth-cli',
            'X-Title: The Dying Earth CLI Game'
        ];
        
        // Use spinner while making the API call
        $utils = new Utils();
        $response = null;
        $http_code = null;
        
        $api_success = $utils->runWithSpinnerAndCancellation(function() use ($data, $headers, &$response, &$http_code) {
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $response !== false;
        }, 'image');
        
        if (!$api_success) {
            write_debug_log("OpenRouter API call cancelled or failed");
            return false;
        }
        
        if ($http_code !== 200) {
            write_debug_log("OpenRouter API error", ['http_code' => $http_code, 'response' => substr($response, 0, 500)]);
            return false;
        }
        
        $response_data = json_decode($response, true);
        
        // Debug: Log the actual response structure
        if ($this->debug) {
            $debug_info = [
                'has_choices' => isset($response_data['choices']),
                'content_preview' => isset($response_data['choices'][0]['message']['content']) ? 
                    substr($response_data['choices'][0]['message']['content'], 0, 200) : 'no content',
                'has_parts' => isset($response_data['choices'][0]['message']['parts']),
                'has_modalities' => isset($response_data['choices'][0]['message']['modalities']),
                'has_image' => isset($response_data['choices'][0]['message']['image']),
                'message_keys' => isset($response_data['choices'][0]['message']) ? 
                    array_keys($response_data['choices'][0]['message']) : [],
                'full_response' => substr(json_encode($response_data), 0, 2000)
            ];
            write_debug_log("OpenRouter response structure", $debug_info);
            echo "[DEBUG] OpenRouter response keys: " . json_encode($debug_info['message_keys']) . "\n";
        }
        
        // Try to extract image data from response
        // The image might be in different formats depending on how OpenRouter returns it
        $image_data = null;
        
        // Check for base64 encoded image in content
        if (isset($response_data['choices'][0]['message']['content'])) {
            $content = $response_data['choices'][0]['message']['content'];
            
            // OpenRouter with modalities might return content in different formats
            // Try to decode JSON if content looks like JSON
            if (is_string($content) && (substr($content, 0, 1) === '{' || substr($content, 0, 1) === '[')) {
                $content_data = @json_decode($content, true);
                if ($content_data && isset($content_data['image'])) {
                    $image_data = base64_decode($content_data['image']);
                }
            }
            
            // Look for base64 image data in content string
            if (!$image_data && preg_match('/data:image\/(png|jpeg|jpg);base64,(.+)/s', $content, $matches)) {
                $image_data = base64_decode($matches[2]);
            } else if (!$image_data && preg_match('/^[A-Za-z0-9+\/]+=*$/s', trim($content)) && strlen($content) > 1000) {
                // Content might be raw base64
                $image_data = @base64_decode(trim($content), true);
            }
        }
        
        // Check for image in parts/attachments (Gemini format)
        if (!$image_data && isset($response_data['choices'][0]['message']['parts'])) {
            foreach ($response_data['choices'][0]['message']['parts'] as $part) {
                if (isset($part['inline_data']['data'])) {
                    $image_data = base64_decode($part['inline_data']['data']);
                    break;
                } else if (isset($part['image'])) {
                    $image_data = base64_decode($part['image']);
                    break;
                }
            }
        }
        
        // Check for modalities response format
        if (!$image_data && isset($response_data['choices'][0]['message']['modalities'])) {
            $modalities = $response_data['choices'][0]['message']['modalities'];
            if (isset($modalities['image'])) {
                $image_data = base64_decode($modalities['image']);
            }
        }
        
        // Check for image directly in the message
        if (!$image_data && isset($response_data['choices'][0]['message']['image'])) {
            $image_data = base64_decode($response_data['choices'][0]['message']['image']);
        }
        
        // Check for images array in the message (OpenRouter format with modalities)
        if (!$image_data && isset($response_data['choices'][0]['message']['images'])) {
            $images = $response_data['choices'][0]['message']['images'];
            
            if ($this->debug) {
                write_debug_log("Found images in response", [
                    'count' => count($images),
                    'first_image_type' => isset($images[0]) ? gettype($images[0]) : 'none',
                    'first_image_preview' => isset($images[0]) ? substr(json_encode($images[0]), 0, 200) : 'none'
                ]);
            }
            
            if (is_array($images) && count($images) > 0) {
                // Get the first image
                $first_image = $images[0];
                if (is_string($first_image)) {
                    // Might be base64 encoded or a data URL
                    if (strpos($first_image, 'data:image') === 0) {
                        // Extract base64 from data URL
                        preg_match('/data:image\/[^;]+;base64,(.+)/', $first_image, $matches);
                        if (isset($matches[1])) {
                            $image_data = base64_decode($matches[1]);
                            if ($this->debug) {
                                write_debug_log("Extracted image from data URL");
                            }
                        }
                    } else {
                        // Assume it's raw base64
                        $image_data = base64_decode($first_image);
                        if ($this->debug) {
                            write_debug_log("Decoded raw base64 image");
                        }
                    }
                } else if (is_array($first_image)) {
                    // Check for OpenRouter's nested image_url structure
                    if (isset($first_image['image_url']['url'])) {
                        $url = $first_image['image_url']['url'];
                        if ($this->debug) {
                            write_debug_log("Found image_url structure", ['url_preview' => substr($url, 0, 100)]);
                        }
                        
                        // Check if it's a data URL with base64
                        if (strpos($url, 'data:image') === 0) {
                            preg_match('/data:image\/[^;]+;base64,(.+)/', $url, $matches);
                            if (isset($matches[1])) {
                                $image_data = base64_decode($matches[1]);
                                if ($this->debug) {
                                    write_debug_log("Successfully extracted image from OpenRouter image_url data URL");
                                }
                            }
                        } else {
                            // It's an external URL - download it
                            $image_data = @file_get_contents($url);
                        }
                    } else if (isset($first_image['data'])) {
                        $image_data = base64_decode($first_image['data']);
                        if ($this->debug) {
                            write_debug_log("Decoded image from array structure");
                        }
                    } else if (isset($first_image['url'])) {
                        // Image might be a URL reference
                        if ($this->debug) {
                            write_debug_log("Image is URL reference", ['url' => $first_image['url']]);
                        }
                        // Try to download the image
                        $image_data = @file_get_contents($first_image['url']);
                    }
                }
            }
        }
        
        if (!$image_data) {
            write_debug_log("No image data found in OpenRouter response");
            return false;
        }
        
        // Verify it's actually an image
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        
        if (strpos($mime_type, 'image') === false) {
            write_debug_log("OpenRouter returned non-image data", ['mime_type' => $mime_type]);
            return false;
        }
        
        // Save the image
        if (file_put_contents($save_path, $image_data) === false) {
            write_debug_log("Failed to save OpenRouter image", ['path' => $save_path]);
            return false;
        }
        
        write_debug_log("OpenRouter image saved successfully", ['path' => $save_path]);
        return true;
    }
    
    /**
     * Fetch and save an image from Pollinations.ai
     *
     * @param string $url The URL to fetch the image from
     * @param string $save_path The path to save the image to
     * @param string $context_name Context name for logging (default: 'image')
     * @return bool True if successful, false otherwise
     */
    private function fetchAndSaveImage($url, $save_path, $context_name = 'image') {
        write_debug_log("Requesting image from URL", ['url' => $url]);
        
        if ($this->debug) {
            echo "[DEBUG] Requesting image from URL: $url\n";
        }
        
        $image_data = null;
        $success = false;
        
        // Get the image with spinner
        $utils = new Utils();
        $success = $utils->runWithSpinnerAndCancellation(function() use ($url, &$image_data) {
            // Set context options for timeout
            $context = stream_context_create(['http' => ['timeout' => 15]]);
            // Use error suppression and check the result with context
            $image_data = @file_get_contents($url, false, $context);
            return $image_data !== false;
        }, $context_name);
        
        if (!$success) {
            $error = error_get_last();
            $error_message = $error['message'] ?? 'Unknown error or cancelled';
            write_debug_log("Failed to fetch image", ['error' => $error_message]);
            echo $utils->colorize("[yellow]Warning: Could not download image from Pollinations.ai ($error_message).[/yellow]\n");
            return false;
        }
        
        write_debug_log("Saving image", ['path' => $save_path]);
        
        if (!@file_put_contents($save_path, $image_data)) {
            write_debug_log("Failed to save image");
            echo $utils->colorize("[yellow]Warning: Failed to save image to: {$save_path}[/yellow]\n");
            return false;
        }
        
        write_debug_log("Image saved successfully", ['path' => $save_path]);
        if ($this->debug) {
            echo "[DEBUG] Image saved to: $save_path\n";
        }
        
        return true;
    }
    
    /**
     * Returns the title screen generation prompt.
     *
     * @return string
     */
    private function getTitlePrompt(): string {
        return "8bit pixel art game title screen for \"The Dying Earth\", dark fantasy RPG game";
    }
    
    public function generateImage($prompt, $timestamp) {
        // FIRST check if an image with this timestamp already exists
        if ($this->imageExistsForTimestamp($timestamp)) {
            if ($this->debug) {
                echo "[DEBUG] Image already exists for timestamp $timestamp. Using existing image instead of generating new one.\n";
            }
            write_debug_log("Using existing image instead of generating new one", ['timestamp' => $timestamp]);
            return $this->displayExistingImage($timestamp);
        }
    
        write_debug_log("Generating new image", ['prompt' => $prompt, 'timestamp' => $timestamp]);
        
        if (!is_string($prompt)) {
            write_debug_log("Invalid prompt format", ['prompt' => print_r($prompt, true)]);
            return null;
        }
        
        // Check prompt length
        if (strlen($prompt) > $this->config['api']['max_image_description_length']) {
            write_debug_log("Prompt exceeds maximum length", [
                'length' => strlen($prompt),
                'max_length' => $this->config['api']['max_image_description_length']
            ]);
            $prompt = substr($prompt, 0, $this->config['api']['max_image_description_length']);
            write_debug_log("Truncated prompt", ['prompt' => $prompt]);
        }

        if ($this->debug) {
            echo "[DEBUG] Generating image with prompt: $prompt\n";
        }
        
        // Check if images directory exists and is writable
        if (!is_dir($this->config['paths']['images_dir'])) {
            write_debug_log("Creating images directory", ['path' => $this->config['paths']['images_dir']]);
            if (!mkdir($this->config['paths']['images_dir'], 0755, true)) {
                write_debug_log("Error: Could not create images directory");
                echo "Error: Could not create images directory.\n";
                return null;
            }
        }
        
        if (!is_writable($this->config['paths']['images_dir'])) {
            write_debug_log("Error: Images directory is not writable");
            echo "Error: Images directory is not writable.\n";
            return null;
        }
        
        $image_path = $this->getImagePathForTimestamp($timestamp);
        $success = false;
        
        // Use OpenRouter/Google Gemini exclusively
        if ($this->use_openrouter && $this->api_key) {
            if ($this->debug) {
                echo "[DEBUG] Using Google Gemini for image generation...\n";
            }
            $success = $this->generateWithOpenRouter($prompt, $timestamp, $image_path);
            
            if (!$success) {
                if ($this->debug) {
                    echo "[DEBUG] Google Gemini image generation failed\n";
                }
                write_debug_log("Google Gemini image generation failed");
                return null; // No fallback - Google only
            }
        } else {
            write_debug_log("Google Gemini not available - no API key or disabled");
            return null; // No image generation without Google
        }
        
        $ascii_art = $this->generateAsciiArt($image_path);
        if ($ascii_art) {
            write_debug_log("Successfully generated ASCII art");
            return $ascii_art;
        } else {
            // fallback to title screen logic
            $title_image_path = $this->config['paths']['images_dir'] . "/title_screen.jpg";
            $title_prompt = $this->getTitlePrompt();
            $width = $this->config['image']['pollinations']['width'] ?? 640;
            $height = $this->config['image']['pollinations']['height'] ?? 360;
            $title_url = $this->buildPollinationsUrl($title_prompt, $timestamp, $width, $height);
            
            if ($this->fetchAndSaveImage($title_url, $title_image_path, 'title')) {
                return $this->generateAsciiArt($title_image_path);
            }
        }
        
        return null;
    }
    
    public function generate1Screen($timestamp = null) {
        $title_image_path = $this->config['paths']['images_dir'] . '/static_title.png';
        
        // Generate title image if it doesn't exist
        if (!file_exists($title_image_path)) {
            if ($timestamp === null) {
                $timestamp = time();
            }
            
            $title_prompt = $this->getTitlePrompt();
            // Use configured dimensions from config (16:9 aspect ratio)
            $width = $this->config['image']['pollinations']['width'] ?? 640;
            $height = $this->config['image']['pollinations']['height'] ?? 360;
            $title_url = $this->buildPollinationsUrl($title_prompt, $timestamp, $width, $height);
            
            if (!$this->fetchAndSaveImage($title_url, $title_image_path, 'title')) {
                return null;
            }
        }
        
        // Display the title screen if the image exists
        if (file_exists($title_image_path)) {
            return $this->generateAsciiArt($title_image_path);
        }
        
        return null;
    }
    
    public function generateAsciiArt($image_path) {
        if (!file_exists($image_path)) {
            if ($this->debug) {
                echo "[DEBUG] Image file not found: $image_path\n";
            }
            return null;
        }

        if ($this->debug) {
            echo "[DEBUG] Generating ASCII art from image at: $image_path\n";
            if ($this->useChunky) {
                echo "[DEBUG] Using Chunky ASCII converter\n";
            } else {
                echo "[DEBUG] Using standard ASCII converter\n";
            }
        }
        
        if ($this->useChunky) {
            $converter = new ChunkyAsciiArtConverter($this->config, 8, true, true);
        } else {
            $converter = new AsciiArtConverter($this->config);
        }
        
        $result = $converter->convertImage($image_path);
        return $result !== false ? $result : null;
    }
    
    public function displayExistingImage($timestamp) {
        write_debug_log("Checking for existing image", ['timestamp' => $timestamp]);
        
        if ($this->debug) {
            echo "[DEBUG] Checking for existing image with timestamp: $timestamp\n";
        }
        
        $image_path = $this->getImagePathForTimestamp($timestamp);
        write_debug_log("Looking for image", ['path' => $image_path]);
        
        if ($this->debug) {
            echo "[DEBUG] Looking for image at path: $image_path\n";
        }
        
        if (file_exists($image_path)) {
            write_debug_log("Found existing image", ['path' => $image_path]);
            if ($this->debug) {
                echo "[DEBUG] Found existing image at: $image_path\n";
            }
            $ascii_art = $this->generateAsciiArt($image_path);
            if ($ascii_art) {
                write_debug_log("Successfully converted image to ASCII art");
                if ($this->debug) {
                    echo "[DEBUG] Successfully converted image to ASCII art\n";
                }
                return $ascii_art;
            } else {
                write_debug_log("Failed to convert image to ASCII art");
                if ($this->debug) {
                    echo "[DEBUG] Failed to convert image to ASCII art\n";
                }
            }
        } else {
            write_debug_log("No existing image found", ['path' => $image_path]);
            if ($this->debug) {
                echo "[DEBUG] No existing image found at: $image_path\n";
            }
        }
        return null;
    }
    
    /**
     * Get the expected image path for a given timestamp
     *
     * @param int $timestamp Timestamp used for the image
     * @return string Path to where the image should be stored
     */
    public function getImagePathForTimestamp($timestamp) {
        return $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
    }
    
    /**
     * Checks if an image exists for the given timestamp
     *
     * @param int $timestamp Timestamp used for the image
     * @return bool True if image exists, false otherwise
     */
    public function imageExistsForTimestamp($timestamp) {
        $image_path = $this->getImagePathForTimestamp($timestamp);
        return file_exists($image_path);
    }
    
    public function clearImages() {
        write_debug_log("Clearing temporary images");
        $files = glob($this->config['paths']['images_dir'] . '/temp_image_*.jpg');
        write_debug_log("Found temporary images", ['count' => count($files)]);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                write_debug_log("Deleting temporary image", ['file' => $file]);
                unlink($file);
            }
        }
    }
} 