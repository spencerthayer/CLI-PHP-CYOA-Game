<?php

namespace App;

class ApiHandler {
    private $config;
    private $api_key;
    private $game_state;
    private $debug;

    public function __construct($config, $api_key, $game_state, $debug = false) {
        $this->config = $config;
        $this->api_key = $api_key;
        $this->game_state = $game_state;
        $this->debug = $debug;
    }
    
    private function processGameMechanics($narrative) {
        $stats = $this->game_state->getCharacterStats();
        
        // Process skill checks [SKILL_CHECK:attribute:difficulty]
        $narrative = preg_replace_callback(
            '/\[SKILL_CHECK:(\w+):(\d+)\]/',
            function($matches) use ($stats) {
                $result = $stats->skillCheck($matches[1], intval($matches[2]));
                return sprintf(
                    "[%s on %s check (DC %d): %s]",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $matches[1],
                    $matches[2],
                    $result['details']
                );
            },
            $narrative
        );

        // Process saving throws [SAVE:type:difficulty]
        $narrative = preg_replace_callback(
            '/\[SAVE:(\w+):(\d+)\]/',
            function($matches) use ($stats) {
                $result = $stats->savingThrow($matches[1], intval($matches[2]));
                return sprintf(
                    "[%s on %s save (DC %d): %s]",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $matches[1],
                    $matches[2],
                    $result['details']
                );
            },
            $narrative
        );

        // Process sanity checks [SANITY_CHECK:difficulty]
        $narrative = preg_replace_callback(
            '/\[SANITY_CHECK:(\d+)\]/',
            function($matches) use ($stats) {
                $result = $stats->sanityCheck(intval($matches[1]));
                $message = sprintf(
                    "[%s on Sanity check (DC %d): %s",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $matches[1],
                    $result['details']
                );
                if (!$result['success']) {
                    $message .= sprintf(". Lost %d Sanity points", $result['sanityLoss']);
                }
                return $message . "]";
            },
            $narrative
        );

        // Process combat attacks [ATTACK:attribute:difficulty]
        $narrative = preg_replace_callback(
            '/\[ATTACK:(\w+):(\d+)\]/',
            function($matches) use ($stats) {
                $result = $stats->skillCheck($matches[1], intval($matches[2]));
                return sprintf(
                    "[%s on %s attack (DC %d): %s]",
                    $result['success'] ? "HIT" : "MISS",
                    $matches[1],
                    $matches[2],
                    $result['details']
                );
            },
            $narrative
        );

        // Process damage [DAMAGE:amount]
        $narrative = preg_replace_callback(
            '/\[DAMAGE:(\d+)\]/',
            function($matches) use ($stats) {
                $damage = intval($matches[1]);
                $remainingHP = $stats->takeDamage($damage);
                return sprintf("[Took %d damage. HP remaining: %d]", $damage, $remainingHP);
            },
            $narrative
        );

        return $narrative;
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
                                        'description' => 'A descriptive prompt for generating an 8-bit style image of the current scene',
                                        'maxLength' => 64
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

        if ($this->debug) {
            write_debug_log("Making API call", [
                'model' => $data['model'],
                'conversation_length' => count($conversation)
            ]);
        }

        $ch = curl_init($this->config['api']['chat_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            if ($this->debug) {
                write_debug_log("API call failed", [
                    'http_code' => $http_code,
                    'response' => $response
                ]);
            }
            throw new \Exception("API call failed with HTTP code $http_code: $response");
        }

        $response_data = json_decode($response, true);
        if ($this->debug) {
            write_debug_log("Raw API response", $response_data);
        }

        if (isset($response_data['choices'][0]['message']['function_call'])) {
            $function_args = json_decode($response_data['choices'][0]['message']['function_call']['arguments'], true);
            
            if ($this->debug) {
                write_debug_log("Parsed function arguments", $function_args);
            }
            
            // Process game mechanics in the narrative
            if (isset($function_args['narrative'])) {
                $function_args['narrative'] = $this->processGameMechanics($function_args['narrative']);
            }
            
            if ($this->debug) {
                write_debug_log("Processed narrative with game mechanics", [
                    'narrative_length' => strlen($function_args['narrative'])
                ]);
            }
            
            return $function_args;
        }

        if ($this->debug) {
            write_debug_log("Unexpected API response format", $response_data);
        }
        throw new \Exception("Unexpected API response format");
    }
} 