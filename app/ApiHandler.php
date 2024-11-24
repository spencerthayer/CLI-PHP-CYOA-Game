<?php

namespace App;

class ApiHandler {
    private $config;
    private $api_key;
    
    public function __construct($config, $api_key) {
        $this->config = $config;
        $this->api_key = $api_key;
    }
    
    public function makeApiCall($conversation) {
        $data = [
            'model' => $this->config['api']['model'],
            'messages' => $conversation,
            'max_tokens' => $this->config['api']['max_tokens'],
            'temperature' => $this->config['api']['temperature'],
            'functions' => [
                [
                    'name' => 'GameResponse',
                    'description' => 'Response from the game, containing the narrative, options, and image prompt.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'narrative' => [
                                'type' => 'string',
                                'description' => 'The main story text describing the current scene'
                            ],
                            'options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ],
                                'minItems' => 4,
                                'maxItems' => 4,
                                'description' => 'Exactly 4 options for the player to choose from'
                            ],
                            'image' => [
                                'type' => 'object',
                                'properties' => [
                                    'prompt' => [
                                        'type' => 'string',
                                        'description' => 'A descriptive prompt for generating an 8-bit style image of the current scene'
                                    ]
                                ],
                                'required' => ['prompt']
                            ]
                        ],
                        'required' => ['narrative', 'options', 'image']
                    ]
                ]
            ],
            'function_call' => ['name' => 'GameResponse']
        ];

        $ch = curl_init($this->config['api']['chat_url']);
        if ($ch === false) {
            throw new \Exception("Failed to initialize cURL");
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
            ]
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new \Exception("API request failed: " . curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            throw new \Exception("API returned non-200 status code: " . $http_code);
        }

        curl_close($ch);
        
        return json_decode($response);
    }
} 