<?php

namespace App;

require_once __DIR__ . '/ImageGenerationInterface.php';
require_once __DIR__ . '/pollinators.php';

/**
 * Adapter for Pollinations.ai service implementing ImageGenerationInterface
 */
class PollinatorsAdapter implements ImageGenerationInterface {
    private $service;
    private $debug;
    
    public function __construct($config, $debug = false) {
        $model = $config['pollinations']['model'] ?? 'turbo';
        $this->service = new PollinatorsService($debug, $model);
        $this->debug = $debug;
    }
    
    public function generateImage($prompt, $timestamp, $save_path) {
        return $this->service->generateImage($prompt, $timestamp, $save_path);
    }
    
    public function isAvailable() {
        return $this->service->isAvailable();
    }
    
    public function getServiceName() {
        return "Pollinations.ai (Free)";
    }
}
