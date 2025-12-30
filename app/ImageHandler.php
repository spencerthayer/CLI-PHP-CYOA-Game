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
        
        // Check config for OpenRouter image generation
        $this->use_openrouter = ($config['image']['generation_service'] === 'openrouter') &&
                                 ($config['image']['openrouter']['enabled'] ?? false);
        
        // Load API key for OpenRouter
        $api_key_file = __DIR__ . '/../.data/.api_key';
        if (file_exists($api_key_file)) {
            $this->api_key = trim(file_get_contents($api_key_file));
        } else {
            $this->use_openrouter = false;
            if ($this->debug) {
                echo "[DEBUG] OpenRouter image generation disabled - no API key found\n";
            }
        }
    }
    
    /**
     * Generate image using OpenRouter API
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
        
        // Get model from config
        $model = $this->config['image']['openrouter']['model'] ?? 'google/gemini-2.5-flash-preview-05-20';
        $aspect_ratio = $this->config['image']['openrouter']['aspect_ratio'] ?? '16:9';
        $image_size = $this->config['image']['openrouter']['image_size'] ?? '1K';
        
        // Create prompt for 8-bit pixel art generation
        $image_prompt = "Generate an 8-bit pixel art style image. " .
                       "Do NOT include any text or words in the image. " .
                       "Scene: $prompt. " .
                       "Style: retro gaming, limited color palette, nostalgic 8-bit graphics, pixelated.";
        
        // Build request per OpenRouter documentation
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $image_prompt
                ]
            ],
            'modalities' => ['image', 'text'],  // Required for image generation
            'image_config' => [
                'aspect_ratio' => $aspect_ratio,
                'image_size' => $image_size
            ],
            'max_tokens' => 2000,
            'temperature' => 0.8
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
            'HTTP-Referer: https://github.com/spencerthayer/CLI-PHP-CYOA-Game',
            'X-Title: The Dying Earth CLI Game'
        ];
        
        if ($this->debug) {
            echo "[DEBUG] OpenRouter request model: $model\n";
            echo "[DEBUG] OpenRouter aspect_ratio: $aspect_ratio, image_size: $image_size\n";
        }
        
        // Use spinner while making the API call
        $utils = new Utils();
        $response = null;
        $http_code = null;
        $timeout = $this->config['image']['openrouter']['timeout'] ?? 60;
        
        $api_success = $utils->runWithSpinnerAndCancellation(function() use ($data, $headers, &$response, &$http_code, $timeout) {
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                write_debug_log("cURL error in OpenRouter request", ['error' => $curl_error]);
            }
            
            return $response !== false;
        }, 'image');
        
        if (!$api_success) {
            write_debug_log("OpenRouter API call cancelled or failed");
            return false;
        }
        
        if ($http_code !== 200) {
            write_debug_log("OpenRouter API error", ['http_code' => $http_code, 'response' => substr($response, 0, 500)]);
            if ($this->debug) {
                echo "[DEBUG] OpenRouter API HTTP Code: $http_code\n";
                echo "[DEBUG] OpenRouter API Response: " . substr($response, 0, 500) . "\n";
            }
            return false;
        }
        
        $response_data = json_decode($response, true);
        
        if ($this->debug) {
            $message_keys = isset($response_data['choices'][0]['message']) ? 
                array_keys($response_data['choices'][0]['message']) : [];
            echo "[DEBUG] OpenRouter response keys: " . json_encode($message_keys) . "\n";
        }
        
        // Extract image from OpenRouter response format
        // Images are in: choices[0].message.images[].image_url.url as base64 data URLs
        $image_data = null;
        
        if (isset($response_data['choices'][0]['message']['images'])) {
            $images = $response_data['choices'][0]['message']['images'];
            
            if ($this->debug) {
                echo "[DEBUG] Found " . count($images) . " image(s) in response\n";
            }
            
            if (is_array($images) && count($images) > 0) {
                $first_image = $images[0];
                
                // OpenRouter format: {"type": "image_url", "image_url": {"url": "data:image/png;base64,..."}}
                if (isset($first_image['image_url']['url'])) {
                    $url = $first_image['image_url']['url'];
                    
                    // Extract base64 from data URL
                    if (preg_match('/data:image\/[^;]+;base64,(.+)/', $url, $matches)) {
                        $image_data = base64_decode($matches[1]);
                        if ($this->debug) {
                            echo "[DEBUG] Successfully extracted image from base64 data URL\n";
                        }
                    }
                }
            }
        }
        
        // Fallback: check other possible locations
        if (!$image_data) {
            // Check content for inline base64
            if (isset($response_data['choices'][0]['message']['content'])) {
                $content = $response_data['choices'][0]['message']['content'];
                if (preg_match('/data:image\/[^;]+;base64,(.+)/s', $content, $matches)) {
                    $image_data = base64_decode($matches[1]);
                }
            }
            
            // Check for parts format (Gemini)
            if (!$image_data && isset($response_data['choices'][0]['message']['parts'])) {
                foreach ($response_data['choices'][0]['message']['parts'] as $part) {
                    if (isset($part['inline_data']['data'])) {
                        $image_data = base64_decode($part['inline_data']['data']);
                        break;
                    }
                }
            }
        }
        
        if (!$image_data) {
            write_debug_log("No image data found in OpenRouter response");
            if ($this->debug) {
                echo "[DEBUG] No image data found in response\n";
            }
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
        
        if ($this->debug) {
            echo "[DEBUG] Image saved successfully to: $save_path\n";
        }
        write_debug_log("OpenRouter image saved successfully", ['path' => $save_path]);
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
        
        // Use OpenRouter for image generation
        if ($this->use_openrouter && $this->api_key) {
            if ($this->debug) {
                echo "[DEBUG] Using OpenRouter for image generation...\n";
            }
            $success = $this->generateWithOpenRouter($prompt, $timestamp, $image_path);
        } else {
            if ($this->debug) {
                echo "[DEBUG] OpenRouter not available (no API key or disabled)\n";
            }
            write_debug_log("OpenRouter image generation not available - no API key or disabled");
        }
        
        if (!$success) {
            write_debug_log("Image generation failed");
            $utils = new Utils();
            echo $utils->colorize("[dim](Image generation failed - press 'i' to toggle images off)[/dim]\n");
            return null;
        }
        
        $ascii_art = $this->generateAsciiArt($image_path);
        if ($ascii_art) {
            write_debug_log("Successfully generated ASCII art");
            return $ascii_art;
        } else {
            // fallback to title screen logic
            $title_image_path = $this->config['paths']['images_dir'] . "/title_screen.jpg";
            $title_prompt = $this->getTitlePrompt();
            
            if ($this->generateWithOpenRouter($title_prompt, $timestamp, $title_image_path)) {
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
            
            if (!$this->generateWithOpenRouter($title_prompt, $timestamp, $title_image_path)) {
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