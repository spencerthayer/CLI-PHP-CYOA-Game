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
        
        $prompt_url = urlencode("8bit ANSI video game $prompt");
        $url = "https://image.pollinations.ai/prompt/$prompt_url?nologo=true&width=360&height=160&seed=$timestamp&model=flux";
        
        $loading_pid = Utils::showLoadingAnimation();
        $image_data = file_get_contents($url);
        Utils::stopLoadingAnimation($loading_pid);
        
        if ($image_data === false) {
            echo "Error downloading image from Pollinations.ai.\n";
            return null;
        }
        
        $image_path = $this->config['paths']['images_dir'] . "/temp_image_$timestamp.jpg";
        file_put_contents($image_path, $image_data);
        return $this->generateAsciiArt($image_path);
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
        if ($this->debug) {
            echo "[DEBUG] Generating ASCII art from image at: $image_path\n";
        }
        
        $converter = new AsciiArtConverter($this->config);
        return $converter->convertImage($image_path);
    }
    
    public function clearImages() {
        $files = glob($this->config['paths']['images_dir'] . '/temp_image_*.jpg');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
    }
} 