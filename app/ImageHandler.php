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
        if (!is_string($prompt)) {
            if ($this->debug) {
                echo "[DEBUG] Invalid prompt format: " . print_r($prompt, true) . "\n";
            }
            return null;
        }
        
        if ($this->debug) {
            echo "[DEBUG] Generating image with prompt: $prompt\n";
        }
        
        // Check if images directory exists and is writable
        if (!is_dir($this->config['paths']['images_dir'])) {
            if (!mkdir($this->config['paths']['images_dir'], 0755, true)) {
                echo "Error: Could not create images directory.\n";
                return null;
            }
        }
        
        if (!is_writable($this->config['paths']['images_dir'])) {
            echo "Error: Images directory is not writable.\n";
            return null;
        }
        
        $prompt_url = urlencode("8bit ANSI video game $prompt");
        $url = "https://image.pollinations.ai/prompt/$prompt_url?nologo=true&width=360&height=160&seed=$timestamp&model=flux";
        
        if ($this->debug) {
            echo "[DEBUG] Requesting image from URL: $url\n";
        }
        
        $loading_pid = Utils::showLoadingAnimation();
        $image_data = @file_get_contents($url);
        Utils::stopLoadingAnimation($loading_pid);
        
        if ($image_data === false) {
            $error = error_get_last();
            echo "Error downloading image from Pollinations.ai: " . ($error['message'] ?? 'Unknown error') . "\n";
            return null;
        }
        
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
        if (!@file_put_contents($image_path, $image_data)) {
            echo "Error saving image to: $image_path\n";
            return null;
        }
        
        if ($this->debug) {
            echo "[DEBUG] Image saved to: $image_path\n";
        }
        
        $ascii_art = $this->generateAsciiArt($image_path);
        return $ascii_art !== false ? $ascii_art : null;
    }
    
    public function generateTitleScreen() {
        $title_image_path = $this->config['paths']['images_dir'] . '/static_title.png';
        
        // Generate title image if it doesn't exist
        if (!file_exists($title_image_path)) {
            $title_url = "https://image.pollinations.ai/prompt/" . 
                urlencode("8bit pixel art game title screen for The Dying Earth, dark fantasy RPG game") . 
                "?nologo=true&width=360&height=160&seed=123&model=flux";
            
            $image_data = file_get_contents($title_url);
            
            if ($image_data !== false) {
                file_put_contents($title_image_path, $image_data);
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
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
        if (file_exists($image_path)) {
            if ($this->debug) {
                echo "[DEBUG] Found existing image at: $image_path\n";
            }
            return $this->generateAsciiArt($image_path);
        }
        return null;
    }
    
    public function clearImages() {
        $files = glob($this->config['paths']['images_dir'] . '/temp_image_*.jpg');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }
} 