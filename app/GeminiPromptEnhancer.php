<?php

namespace App;

/**
 * Use Gemini via OpenRouter to enhance image generation prompts
 * This creates more detailed, better prompts for any image generation service
 */
class GeminiPromptEnhancer {
    private $provider_manager;
    private $debug;
    
    public function __construct($provider_manager, $debug = false) {
        $this->provider_manager = $provider_manager;
        $this->debug = $debug;
    }
    
    /**
     * Enhance a simple prompt into a detailed image generation prompt
     * 
     * @param string $basic_prompt The basic prompt from the game
     * @param string $style The desired art style
     * @return string Enhanced prompt ready for image generation
     */
    public function enhancePrompt($basic_prompt, $style = "8-bit pixel art") {
        // Build the enhancement request
        $system_prompt = "You are an expert at creating detailed image generation prompts. "
                       . "Transform the given scene description into a concise, visually rich prompt "
                       . "optimized for image generation. Focus on visual elements, composition, lighting, "
                       . "and mood. Keep it under 200 characters for best results.";
        
        $user_prompt = "Create an image generation prompt for this scene in {$style} style:\n\n"
                     . "{$basic_prompt}\n\n"
                     . "Requirements:\n"
                     . "- Include specific visual details\n"
                     . "- Mention lighting and atmosphere\n"
                     . "- Keep it concise (under 200 characters)\n"
                     . "- Focus on what's visually striking\n"
                     . "- Output ONLY the prompt, no explanations";
        
        try {
            // Make a quick API call to enhance the prompt
            $data = [
                'model' => $this->provider_manager->getModel(),
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 100
            ];
            
            $ch = curl_init($this->provider_manager->getChatUrl());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->provider_manager->getHeaders());
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Quick timeout
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                $response_data = json_decode($response, true);
                if (isset($response_data['choices'][0]['message']['content'])) {
                    $enhanced = trim($response_data['choices'][0]['message']['content']);
                    
                    if ($this->debug) {
                        write_debug_log("[PromptEnhancer] Enhanced prompt", [
                            'original' => substr($basic_prompt, 0, 100) . '...',
                            'enhanced' => $enhanced
                        ]);
                    }
                    
                    return $enhanced;
                }
            }
        } catch (\Exception $e) {
            if ($this->debug) {
                write_debug_log("[PromptEnhancer] Enhancement failed", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Fallback: return a simplified version of the original
        return $style . " " . substr($basic_prompt, 0, 150);
    }
}
