<?php

namespace App;

/**
 * OpenRouter Image Generation Handler using Gemini 2.5 Flash Image Preview
 * 
 * This handler uses Google's Gemini 2.5 Flash Image Preview model via OpenRouter
 * to generate high-quality images. The model supports:
 * - Image generation from text prompts
 * - Multi-image fusion
 * - Character consistency
 * - Image editing
 * - World knowledge integration
 */
class OpenRouterImageHandler implements ImageGenerationInterface {
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    private $model = 'google/gemini-2.5-flash-image-preview:free';
    private $debug;
    
    public function __construct($api_key, $debug = false) {
        $this->api_key = $api_key;
        $this->debug = $debug;
    }
    
    /**
     * Generate an image using Gemini 2.5 Flash Image Preview via OpenRouter
     */
    public function generateImage($prompt, $timestamp, $save_path) {
        try {
            if ($this->debug) {
                $this->write_debug_log("Starting OpenRouter image generation with prompt: " . substr($prompt, 0, 100) . "...");
            }
            
            // Enhance the prompt for better 8-bit pixel art generation
            $enhanced_prompt = $this->enhancePromptFor8BitArt($prompt);
            
            // Build the request for image generation
            $data = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $enhanced_prompt
                    ]
                ],
                'max_tokens' => 2000, // Images require more tokens (~1290 per image)
                'temperature' => 0.8,
                'seed' => $timestamp
            ];
            
            // Make the API call
            $response = $this->callAPI($data);
            
            if (!$response || !isset($response['choices'][0]['message']['content'])) {
                throw new \Exception("Invalid response from OpenRouter");
            }
            
            // Extract the image data from the response
            $content = $response['choices'][0]['message']['content'];
            
            // The response should contain base64 encoded image data
            // Look for inline_data or attachment format
            $image_data = $this->extractImageData($content, $response);
            
            if (!$image_data) {
                throw new \Exception("No image data found in response");
            }
            
            // Save the image
            $result = file_put_contents($save_path, $image_data);
            
            if ($result === false) {
                throw new \Exception("Failed to save image to: $save_path");
            }
            
            if ($this->debug) {
                $this->write_debug_log("Image successfully generated and saved to: $save_path");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->write_debug_log("OpenRouter image generation failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Enhance prompt for 8-bit pixel art style
     */
    private function enhancePromptFor8BitArt($prompt) {
        return "Generate an 8-bit pixel art style image. " .
               "Use a limited color palette reminiscent of classic video games. " .
               "The image should have clear pixelated edges and retro game aesthetics. " .
               "Scene description: " . $prompt . " " .
               "Style: 8-bit pixel art, retro gaming, limited colors, pixelated, nostalgic game graphics. " .
               "Resolution: low-res pixel art suitable for terminal display.";
    }
    
    /**
     * Extract image data from the API response
     */
    private function extractImageData($content, $response) {
        // Check if the content contains base64 encoded image
        if (preg_match('/data:image\/(png|jpeg|jpg);base64,(.+)/', $content, $matches)) {
            return base64_decode($matches[2]);
        }
        
        // Check for inline_data in parts (Gemini format)
        if (isset($response['choices'][0]['message']['parts'])) {
            foreach ($response['choices'][0]['message']['parts'] as $part) {
                if (isset($part['inline_data']['data'])) {
                    return base64_decode($part['inline_data']['data']);
                }
            }
        }
        
        // Check for tool_calls with image generation results
        if (isset($response['choices'][0]['message']['tool_calls'])) {
            foreach ($response['choices'][0]['message']['tool_calls'] as $tool_call) {
                if (isset($tool_call['function']['image_data'])) {
                    return base64_decode($tool_call['function']['image_data']);
                }
            }
        }
        
        // If content itself is base64
        $decoded = @base64_decode($content, true);
        if ($decoded && $this->isValidImage($decoded)) {
            return $decoded;
        }
        
        return null;
    }
    
    /**
     * Check if data is a valid image
     */
    private function isValidImage($data) {
        // Check for common image file signatures
        $signatures = [
            "\xFF\xD8\xFF" => 'jpeg',
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'png',
            "GIF" => 'gif'
        ];
        
        foreach ($signatures as $signature => $type) {
            if (substr($data, 0, strlen($signature)) === $signature) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Make API call to OpenRouter
     */
    private function callAPI($data) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
            'HTTP-Referer: https://github.com/spencerthayer/CLI-PHP-CYOA-Game',
            'X-Title: The Dying Earth CLI Game'
        ];
        
        $ch = curl_init($this->base_url . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new \Exception("API returned HTTP $http_code: $response");
        }
        
        return json_decode($response, true);
    }
    
    public function isAvailable() {
        // Quick check to see if the API is responding
        try {
            $data = [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => 'test']],
                'max_tokens' => 1
            ];
            
            $response = $this->callAPI($data);
            return isset($response['choices']);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getServiceName() {
        return "Google Gemini 2.5 Flash Image (via OpenRouter)";
    }
    
    private function write_debug_log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [OpenRouterImage] $message\n";
        file_put_contents('data/debug_log.txt', $log_entry, FILE_APPEND);
    }
}
