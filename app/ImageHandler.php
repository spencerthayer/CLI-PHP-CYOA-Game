<?php

namespace App;

class ImageHandler {
    private $config;
    private $debug;
    
    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
    }
    
    /**
     * Build a standardized URL for Pollinations.ai image generation
     * 
     * @param string $prompt The image generation prompt
     * @param int $timestamp Timestamp used as seed
     * @param int $width Image width (default: 360)
     * @param int $height Image height (default: 160)
     * @param string $model Model to use (default: flux)
     * @param string $negative_prompt Negative prompt (default: text)
     * @return string The fully constructed URL
     */
    private function buildPollinationsUrl($prompt, $timestamp, $width = 360, $height = 160, $model = 'flux', $negative_prompt = 'text') {
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
        
        $final_prompt = "8bit pixel art $prompt";
        $url = $this->buildPollinationsUrl($final_prompt, $timestamp);
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
        
        if (!$this->fetchAndSaveImage($url, $image_path, 'image')) {
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
            $title_url = $this->buildPollinationsUrl($title_prompt, $timestamp);
            
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
            $title_url = $this->buildPollinationsUrl($title_prompt, $timestamp);
            
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
        }
        
        $converter = new AsciiArtConverter($this->config);
        $result = $converter->convertImage($image_path);
        return $result !== false ? $result : null;
    }
    
    public function displayExistingImage($timestamp) {
        write_debug_log("Checking for existing image", ['timestamp' => $timestamp]);
        
        if ($this->debug) {
            echo "[DEBUG] Checking for existing image with timestamp: $timestamp\n";
        }
        
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
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