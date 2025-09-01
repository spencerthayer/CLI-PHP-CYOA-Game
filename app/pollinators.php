<?php

namespace App;

/**
 * Pollinations.ai Image Generation Service
 * 
 * Cheap but sometimes unreliable image generation service.
 * No API key required, completely free to use.
 */
class PollinatorsService {
    private $debug;
    private $model;
    
    public function __construct($debug = false, $model = 'turbo') {
        $this->debug = $debug;
        $this->model = $model; // turbo or flux (flux often has server issues)
    }
    
    /**
     * Build the Pollinations.ai image generation URL
     * 
     * @param string $prompt The image generation prompt
     * @param int $timestamp Timestamp used as seed
     * @param int $width Image width (default: 360)
     * @param int $height Image height (default: 160)
     * @param string $negative_prompt Negative prompt (default: text)
     * @return string The fully constructed URL
     */
    public function buildImageUrl($prompt, $timestamp, $width = 360, $height = 160, $negative_prompt = 'text') {
        // Sanitize prompt: remove newlines before URL encoding
        $prompt = str_replace(["\n", "\r"], ' ', $prompt);
        $prompt_url = urlencode($prompt);
        
        return "https://image.pollinations.ai/prompt/$prompt_url"
             . "?negative_prompt=$negative_prompt"
             . "&nologo=true"
             . "&width=$width"
             . "&height=$height"
             . "&seed=$timestamp"
             . "&model={$this->model}";
    }
    
    /**
     * Generate an image using Pollinations.ai
     * 
     * @param string $prompt The image generation prompt
     * @param int $timestamp Timestamp for seed and file naming
     * @param string $save_path Path to save the image
     * @return bool True if successful, false otherwise
     */
    public function generateImage($prompt, $timestamp, $save_path) {
        $url = $this->buildImageUrl($prompt, $timestamp);
        
        if ($this->debug) {
            write_debug_log("[Pollinations] Generating image", [
                'url' => $url,
                'model' => $this->model
            ]);
        }
        
        // Use file_get_contents with error suppression
        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'ignore_errors' => true
            ]
        ]);
        
        $image_data = @file_get_contents($url, false, $context);
        
        if ($image_data === false) {
            $error = error_get_last();
            if ($this->debug) {
                write_debug_log("[Pollinations] Failed to generate image", [
                    'error' => $error['message'] ?? 'Unknown error'
                ]);
            }
            return false;
        }
        
        // Check if we got an actual image (not an error response)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        
        if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            if ($this->debug) {
                write_debug_log("[Pollinations] Invalid response (not an image)", [
                    'mime_type' => $mime_type,
                    'response_preview' => substr($image_data, 0, 200)
                ]);
            }
            return false;
        }
        
        // Save the image
        if (!@file_put_contents($save_path, $image_data)) {
            if ($this->debug) {
                write_debug_log("[Pollinations] Failed to save image", [
                    'path' => $save_path
                ]);
            }
            return false;
        }
        
        if ($this->debug) {
            write_debug_log("[Pollinations] Image generated successfully", [
                'path' => $save_path,
                'size' => filesize($save_path)
            ]);
        }
        
        return true;
    }
    
    /**
     * Check if Pollinations service is available
     * 
     * @return bool True if service is reachable
     */
    public function isAvailable() {
        $test_url = "https://image.pollinations.ai/prompt/test?width=1&height=1&nologo=true&model={$this->model}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'HEAD'
            ]
        ]);
        
        $headers = @get_headers($test_url, 1, $context);
        
        if ($headers === false) {
            return false;
        }
        
        // Check for 200 OK status
        return strpos($headers[0], '200') !== false;
    }
    
    /**
     * Get available models
     * 
     * @return array List of available models
     */
    public function getAvailableModels() {
        return [
            'turbo' => 'Fast generation, usually reliable',
            'flux' => 'Higher quality, but often has server issues'
        ];
    }
}
