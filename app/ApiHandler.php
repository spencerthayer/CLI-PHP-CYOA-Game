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
        $mechanics_log = [];
        
        // Process skill checks [SKILL_CHECK:attribute:difficulty]
        $narrative = preg_replace_callback(
            '/\[SKILL_CHECK:(\w+):(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log) {
                $attribute = $matches[1];
                $difficulty = intval($matches[2]);
                $result = $stats->skillCheck($attribute, $difficulty);
                
                $mechanics_log[] = [
                    'type' => 'skill_check',
                    'attribute' => $attribute,
                    'difficulty' => $difficulty,
                    'roll' => $result['roll'],
                    'modifier' => $result['modifier'] ?? 0,
                    'total' => $result['total'],
                    'success' => $result['success']
                ];
                
                return sprintf(
                    "[%s on %s check (DC %d): %s]",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $attribute,
                    $difficulty,
                    $result['details']
                );
            },
            $narrative
        );

        // Process saving throws [SAVE:type:difficulty]
        $narrative = preg_replace_callback(
            '/\[SAVE:(\w+):(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log) {
                $type = $matches[1];
                $difficulty = intval($matches[2]);
                $result = $stats->savingThrow($type, $difficulty);
                
                $mechanics_log[] = [
                    'type' => 'saving_throw',
                    'save_type' => $type,
                    'difficulty' => $difficulty,
                    'roll' => $result['roll'],
                    'modifier' => $result['modifier'] ?? 0,
                    'total' => $result['total'],
                    'success' => $result['success']
                ];
                
                return sprintf(
                    "[%s on %s save (DC %d): %s]",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $type,
                    $difficulty,
                    $result['details']
                );
            },
            $narrative
        );

        // Process sanity checks [SANITY_CHECK:difficulty]
        $narrative = preg_replace_callback(
            '/\[SANITY_CHECK:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log) {
                $difficulty = intval($matches[1]);
                $result = $stats->sanityCheck($difficulty);
                
                $mechanics_log[] = [
                    'type' => 'sanity_check',
                    'difficulty' => $difficulty,
                    'roll' => $result['roll'],
                    'modifier' => $result['modifier'] ?? 0,
                    'total' => $result['total'],
                    'success' => $result['success'],
                    'sanity_loss' => $result['sanityLoss'] ?? 0,
                    'current_sanity' => $result['currentSanity']
                ];
                
                return sprintf(
                    "[%s on Sanity check (DC %d): %s%s]",
                    $result['success'] ? "SUCCESS" : "FAILURE",
                    $difficulty,
                    $result['details'],
                    !$result['success'] ? sprintf(". Lost %d Sanity points", $result['sanityLoss']) : ""
                );
            },
            $narrative
        );

        // Process combat attacks [ATTACK:attribute:difficulty]
        $narrative = preg_replace_callback(
            '/\[ATTACK:(\w+):(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log) {
                $attribute = $matches[1];
                $difficulty = intval($matches[2]);
                $result = $stats->skillCheck($attribute, $difficulty);
                
                $mechanics_log[] = [
                    'type' => 'attack',
                    'attribute' => $attribute,
                    'difficulty' => $difficulty,
                    'roll' => $result['roll'],
                    'modifier' => $result['modifier'] ?? 0,
                    'total' => $result['total'],
                    'success' => $result['success']
                ];
                
                return sprintf(
                    "[%s on %s attack (DC %d): %s]",
                    $result['success'] ? "HIT" : "MISS",
                    $attribute,
                    $difficulty,
                    $result['details']
                );
            },
            $narrative
        );

        // Process damage [DAMAGE:amount]
        $narrative = preg_replace_callback(
            '/\[DAMAGE:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log) {
                $damage = intval($matches[1]);
                $old_hp = $stats->getCurrentHP();
                $new_hp = $stats->takeDamage($damage);
                
                $mechanics_log[] = [
                    'type' => 'damage',
                    'amount' => $damage,
                    'old_hp' => $old_hp,
                    'new_hp' => $new_hp
                ];
                
                return sprintf("[Took %d damage. HP remaining: %d]", $damage, $new_hp);
            },
            $narrative
        );

        if ($this->debug && !empty($mechanics_log)) {
            write_debug_log("Game mechanics processed", [
                'mechanics_count' => count($mechanics_log),
                'mechanics_details' => $mechanics_log,
                'narrative_length_before' => strlen($narrative),
                'narrative_length_after' => strlen($narrative)
            ]);
            
            // Log the narrative context around each mechanic
            foreach ($mechanics_log as $mechanic) {
                $context = $this->extractNarrativeContext($narrative, $mechanic);
                write_debug_log("Mechanic context", [
                    'type' => $mechanic['type'],
                    'before' => $context['before'],
                    'result' => $context['result'],
                    'after' => $context['after']
                ]);
            }
        }

        return $narrative;
    }

    private function extractNarrativeContext($narrative, $mechanic) {
        // Extract about 100 characters before and after the mechanic result
        $pattern = '';
        switch ($mechanic['type']) {
            case 'skill_check':
                $pattern = sprintf('/(.{0,100})\[(?:SUCCESS|FAILURE) on %s check \(DC %d\)[^\]]+\](.{0,100})/s',
                    $mechanic['attribute'], $mechanic['difficulty']);
                break;
            case 'saving_throw':
                $pattern = sprintf('/(.{0,100})\[(?:SUCCESS|FAILURE) on %s save \(DC %d\)[^\]]+\](.{0,100})/s',
                    $mechanic['save_type'], $mechanic['difficulty']);
                break;
            case 'attack':
                $pattern = sprintf('/(.{0,100})\[(?:HIT|MISS) on %s attack \(DC %d\)[^\]]+\](.{0,100})/s',
                    $mechanic['attribute'], $mechanic['difficulty']);
                break;
            case 'sanity_check':
                $pattern = sprintf('/(.{0,100})\[(?:SUCCESS|FAILURE) on Sanity check \(DC %d\)[^\]]+\](.{0,100})/s',
                    $mechanic['difficulty']);
                break;
            case 'damage':
                $pattern = sprintf('/(.{0,100})\[Took %d damage[^\]]+\](.{0,100})/s',
                    $mechanic['amount']);
                break;
        }
        
        if (preg_match($pattern, $narrative, $matches)) {
            return [
                'before' => trim($matches[1]),
                'result' => $mechanic,
                'after' => trim($matches[2])
            ];
        }
        
        return [
            'before' => '',
            'result' => $mechanic,
            'after' => ''
        ];
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