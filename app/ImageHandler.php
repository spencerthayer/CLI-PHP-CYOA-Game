<?php

namespace App;

class ImageHandler {
    private $config;
    private $debug;
    
    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
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
        
        // Sanitize prompt: remove newlines before URL encoding
        $prompt = str_replace(["\n", "\r"], ' ', $prompt); // Replace newlines with spaces
        
        $prompt_url = urlencode("8bit ANSI video game $prompt");
        $url = "https://image.pollinations.ai/prompt/$prompt_url?nologo=true&width=360&height=160&seed=$timestamp&model=flux";
        write_debug_log("Requesting image from URL", ['url' => $url]);
        
        if ($this->debug) {
            echo "[DEBUG] Requesting image from URL: $url\n";
        }
        
        $loading_pid = Utils::showLoadingAnimation();
        
        // Set context options for timeout
        $context = stream_context_create(['http' => ['timeout' => 15]]); // 15 second timeout
        
        // Use error suppression and check the result with context
        $image_data = @file_get_contents($url, false, $context);
        Utils::stopLoadingAnimation($loading_pid);
        
        if ($image_data === false) {
            $error = error_get_last();
            $error_message = $error['message'] ?? 'Unknown error';
            write_debug_log("Failed to fetch image", ['error' => $error_message]);
            echo Utils::colorize("[yellow]Warning: Could not download image from Pollinations.ai ($error_message). Skipping image display.[/yellow]\n");
            return null;
        }
        
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
        write_debug_log("Saving image", ['path' => $image_path]);
        
        if (!@file_put_contents($image_path, $image_data)) {
            write_debug_log("Failed to save image");
            echo "Error saving image to: $image_path\n";
            return null;
        }
        
        write_debug_log("Image saved successfully", ['path' => $image_path]);
        if ($this->debug) {
            echo "[DEBUG] Image saved to: $image_path\n";
        }
        
        $ascii_art = $this->generateAsciiArt($image_path);
        if ($ascii_art) {
            write_debug_log("Successfully generated ASCII art");
            return $ascii_art;
        } else {
            write_debug_log("Failed to generate ASCII art");
            return null;
        }
    }
    
    public function generateTitleScreen() {
        $title_image_path = $this->config['paths']['images_dir'] . '/static_title.png';
        
        // Generate title image if it doesn't exist
        if (!file_exists($title_image_path)) {
            $title_url = "https://image.pollinations.ai/prompt/" . 
                urlencode("8bit pixel art game title screen for The Dying Earth, dark fantasy RPG game") . 
                "?nologo=true&width=360&height=160&seed=123&model=flux";
            
            write_debug_log("Requesting title screen image from URL", ['url' => $title_url]);
            
            // Set context options for timeout
            $context = stream_context_create(['http' => ['timeout' => 15]]); // 15 second timeout
            
            // Use error suppression and check the result with context
            $image_data = @file_get_contents($title_url, false, $context);
            
            if ($image_data !== false) {
                if (!@file_put_contents($title_image_path, $image_data)) {
                    write_debug_log("Failed to save title screen image");
                    echo Utils::colorize("[yellow]Warning: Failed to save title screen image.[/yellow]\n");
                    return null; // Explicitly return null on save failure
                }
            } else {
                $error = error_get_last();
                $error_message = $error['message'] ?? 'Network error or timeout'; // Improved default message
                write_debug_log("Failed to fetch title screen image", ['error' => $error_message]);
                echo Utils::colorize("[yellow]Warning: Could not download title screen image ($error_message).[/yellow]\n");
                return null; // Explicitly return null on fetch failure
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