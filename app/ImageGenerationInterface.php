<?php

namespace App;

/**
 * Interface for image generation services
 */
interface ImageGenerationInterface {
    /**
     * Generate an image from a text prompt
     * 
     * @param string $prompt The text prompt for image generation
     * @param int $timestamp Timestamp for seeding and file naming
     * @param string $save_path Path where the image should be saved
     * @return bool True if successful, false otherwise
     */
    public function generateImage($prompt, $timestamp, $save_path);
    
    /**
     * Check if the service is available
     * 
     * @return bool True if the service is available
     */
    public function isAvailable();
    
    /**
     * Get the service name
     * 
     * @return string Service name for display
     */
    public function getServiceName();
}
