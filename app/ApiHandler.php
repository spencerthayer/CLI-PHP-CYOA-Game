<?php

namespace App;

class ApiHandler {
    private $config;
    private $provider_manager;
    private $game_state;
    private $debug;

    public function __construct($config, $provider_manager, $game_state, $debug = false) {
        $this->config = $config;
        $this->provider_manager = $provider_manager;
        $this->game_state = $game_state;
        $this->debug = $debug;
    }
    
    private function processGameMechanics($narrative) {
        $stats = $this->game_state->getCharacterStats();
        $mechanics_log = [];
        $mechanics_applied = false;
        $last_check_result = null;

        // Process action-based checks [Attribute DC:difficulty]
        $narrative = preg_replace_callback(
            '/\[(\w+)\s+DC:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied, &$last_check_result) {
                $attribute = $matches[1];
                $difficulty = intval($matches[2]);
                
                // Handle different types of checks
                if (in_array($attribute, ['Agility', 'Appearance', 'Charisma', 'Dexterity', 'Endurance', 'Intellect', 'Knowledge', 'Luck', 'Perception', 'Spirit', 'Strength', 'Vitality', 'Willpower', 'Wisdom'])) {
                    $result = $stats->skillCheck($attribute, $difficulty);
                    $roll_text = sprintf(
                        "\n🎲 %s Check: %d + %d (modifier) = %d vs DC %d - %s!\n",
                        $attribute,
                        $result['roll'],
                        $result['modifier'],
                        $result['roll'] + $result['modifier'],
                        $difficulty,
                        $result['success'] ? "Success" : "Failure"
                    );
                } else {
                    $result = $stats->savingThrow($attribute, $difficulty);
                    $roll_text = sprintf(
                        "\n🎲 %s Save: %d + %d (modifier) = %d vs DC %d - %s!\n",
                        $attribute,
                        $result['roll'],
                        $result['modifier'],
                        $result['roll'] + $result['modifier'],
                        $difficulty,
                        $result['success'] ? "Success" : "Failure"
                    );
                }
                
                $mechanics_applied = true;
                $last_check_result = $result;
                
                if ($this->debug) {
                    write_debug_log("🎲 Action Check Result", [
                        'type' => 'action_check',
                        'attribute' => $attribute,
                        'roll_result' => $result,
                        'success' => $result['success']
                    ]);
                }
                
                return $roll_text;
            },
            $narrative
        );

        // Process attribute modifications [MODIFY_ATTRIBUTE:attribute:amount]
        $narrative = preg_replace_callback(
            '/\[MODIFY_ATTRIBUTE:(\w+):([+-]?\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied) {
                $attribute = $matches[1];
                $amount = intval($matches[2]);
                $success = $stats->modifyAttribute($attribute, $amount);
                $mechanics_applied = true;
                
                $mechanics_log[] = [
                    'type' => 'attribute_modification',
                    'attribute' => $attribute,
                    'amount' => $amount,
                    'success' => $success
                ];
                
                return sprintf(
                    "[%s %+d to %s]",
                    $amount >= 0 ? "Gained" : "Lost",
                    abs($amount),
                    $attribute
                );
            },
            $narrative
        );

        // Process healing [HEAL:amount]
        $narrative = preg_replace_callback(
            '/\[HEAL:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied) {
                $amount = intval($matches[1]);
                $healed = $stats->heal($amount);
                $mechanics_applied = true;
                
                $mechanics_log[] = [
                    'type' => 'healing',
                    'amount_attempted' => $amount,
                    'amount_healed' => $healed,
                    'new_health' => $stats->getCurrentHealth()
                ];
                
                return sprintf("[Healed for %d Health]", $healed);
            },
            $narrative
        );

        // Process sanity restoration [RESTORE_SANITY:amount]
        $narrative = preg_replace_callback(
            '/\[RESTORE_SANITY:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied) {
                $amount = intval($matches[1]);
                $restored = $stats->restoreSanity($amount);
                $mechanics_applied = true;
                
                $mechanics_log[] = [
                    'type' => 'sanity_restoration',
                    'amount_attempted' => $amount,
                    'amount_restored' => $restored,
                    'new_sanity' => $stats->getCurrentSanity()
                ];
                
                return sprintf("[Restored %d Sanity]", $restored);
            },
            $narrative
        );

        // Process experience gain [GAIN_XP:amount]
        $narrative = preg_replace_callback(
            '/\[GAIN_XP:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied) {
                $amount = intval($matches[1]);
                $success = $stats->gainExperience($amount);
                $mechanics_applied = true;
                
                $mechanics_log[] = [
                    'type' => 'experience_gain',
                    'amount' => $amount,
                    'new_level' => $stats->getLevel()
                ];
                
                return sprintf("[Gained %d XP]", $amount);
            },
            $narrative
        );

        // Process sanity checks [SANITY_CHECK:difficulty]
        $narrative = preg_replace_callback(
            '/\[SANITY_CHECK:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied, &$last_check_result) {
                $difficulty = intval($matches[1]);
                $result = $stats->skillCheck('Sanity', $difficulty);
                $mechanics_applied = true;
                $last_check_result = $result;

                if (!$result['success']) {
                    $sanity_loss = rand(2, 4);
                    $old_sanity = $stats->getStat('Sanity')['current'];
                    $stats->modifyStat('Sanity', -$sanity_loss);
                    $new_sanity = $stats->getStat('Sanity')['current'];
                    
                    if ($this->debug) {
                        write_debug_log("Failed Sanity Check", [
                            'old_sanity' => $old_sanity,
                            'sanity_loss' => $sanity_loss,
                            'new_sanity' => $new_sanity
                        ]);
                    }

                    $mechanics_log[] = [
                        'type' => 'sanity_loss',
                        'amount' => $sanity_loss,
                        'old_value' => $old_sanity,
                        'new_value' => $new_sanity
                    ];

                    // Format the roll result for display in the narrative
                    return sprintf(
                        "\n🎲 Sanity Check: %d + %d (modifier) = %d vs DC %d - Failure! Lost %d Sanity points (Current: %d/%d)\n",
                        $result['roll'],
                        $result['modifier'],
                        $result['roll'] + $result['modifier'],
                        $difficulty,
                        $sanity_loss,
                        $new_sanity,
                        $stats->getStat('Sanity')['max']
                    );
                }

                return sprintf(
                    "\n🎲 Sanity Check: %d + %d (modifier) = %d vs DC %d - Success!\n",
                    $result['roll'],
                    $result['modifier'],
                    $result['roll'] + $result['modifier'],
                    $difficulty
                );
            },
            $narrative
        );

        // Process saving throws [SAVE:type:difficulty]
        $narrative = preg_replace_callback(
            '/\[SAVE:(\w+):(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied, &$last_check_result) {
                $type = $matches[1];
                $difficulty = intval($matches[2]);
                $result = $stats->savingThrow($type, $difficulty);
                $mechanics_applied = true;
                $last_check_result = $result;
                
                if ($this->debug) {
                    write_debug_log("🎲 Saving Throw Result", [
                        'type' => 'saving_throw',
                        'save_type' => $type,
                        'roll_result' => $result,
                        'success' => $result['success']
                    ]);
                }

                // Format the roll result for display in the narrative
                return sprintf(
                    "\n🎲 %s Save: %d + %d (modifier) = %d vs DC %d - %s!\n",
                    $type,
                    $result['roll'],
                    $result['modifier'],
                    $result['roll'] + $result['modifier'],
                    $difficulty,
                    $result['success'] ? "Success" : "Failure"
                );
            },
            $narrative
        );

        // Process skill checks [SKILL_CHECK:attribute:difficulty]
        $narrative = preg_replace_callback(
            '/\[SKILL_CHECK:(\w+):(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied, &$last_check_result) {
                $attribute = $matches[1];
                $difficulty = intval($matches[2]);
                $result = $stats->skillCheck($attribute, $difficulty);
                $mechanics_applied = true;
                $last_check_result = $result;
                
                if ($this->debug) {
                    write_debug_log("🎲 Skill Check Result", [
                        'type' => 'skill_check',
                        'attribute' => $attribute,
                        'roll_result' => $result,
                        'success' => $result['success']
                    ]);
                }

                // Format the roll result for display in the narrative
                $roll_text = sprintf(
                    "\n🎲 %s Check: %d + %d (modifier) + %d (proficiency) = %d vs DC %d - %s!\n",
                    $attribute,
                    $result['roll'],
                    $result['modifier'],
                    $result['proficiency'],
                    $result['roll'] + $result['modifier'] + $result['proficiency'],
                    $difficulty,
                    $result['success'] ? "Success" : "Failure"
                );
                
                return $roll_text;
            },
            $narrative
        );

        // Store the last check result for narrative branching
        if ($last_check_result) {
            $this->game_state->setLastCheckResult($last_check_result);
            if ($this->debug) {
                write_debug_log("🎭 Narrative Branch Decision", [
                    'check_type' => isset($last_check_result['save_type']) ? 'saving_throw' : 'skill_check',
                    'success' => $last_check_result['success'],
                    'total_roll' => $last_check_result['roll'] + $last_check_result['modifier'],
                    'difficulty' => $last_check_result['difficulty'],
                    'narrative_branch' => $last_check_result['success'] ? 'success_path' : 'failure_path'
                ]);
            }
        }

        // Process damage [DAMAGE:amount]
        $narrative = preg_replace_callback(
            '/\[DAMAGE:(\d+)\]/',
            function($matches) use ($stats, &$mechanics_log, &$mechanics_applied) {
                $damage = intval($matches[1]);
                $old_health = $stats->getCurrentHealth();
                $new_health = $stats->takeDamage($damage);
                $mechanics_applied = true;
                
                $mechanics_log[] = [
                    'type' => 'damage',
                    'amount' => $damage,
                    'old_health' => $old_health,
                    'new_health' => $new_health
                ];
                
                return sprintf("[Took %d damage. Health remaining: %d]", $damage, $new_health);
            },
            $narrative
        );

        // If any mechanics were processed, save the updated state
        if ($mechanics_applied) {
            $this->game_state->saveCharacterStats($stats);
            $this->game_state->addToHistory('system', 'mechanics', $mechanics_log);
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
            case 'attribute_modification':
                $pattern = sprintf('/(.{0,100})\[Gained \+%d to %s\](.{0,100})/s',
                    $mechanic['amount'], $mechanic['attribute']);
                break;
            case 'healing':
                $pattern = sprintf('/(.{0,100})\[Healed for %d Health\](.{0,100})/s',
                    $mechanic['amount_healed']);
                break;
            case 'sanity_restoration':
                $pattern = sprintf('/(.{0,100})\[Restored %d Sanity\](.{0,100})/s',
                    $mechanic['amount_restored']);
                break;
            case 'experience_gain':
                $pattern = sprintf('/(.{0,100})\[Gained %d XP\](.{0,100})/s',
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
    
    private function formatStatString($stats, $attributes) {
        $output = '';
        foreach ($attributes as $attr) {
            $stat = $stats->getStat($attr);
            $modifier = floor(($stat['current'] - 10) / 2);
            $output .= sprintf("%s: %d (modifier: %d)\n", 
                $attr, 
                $stat['current'], 
                $modifier
            );
        }
        return $output;
    }

    private function processApiResponse($response_data) {
        // Handle tool_calls format (OpenRouter only)
        $function_args = null;
        
        if (isset($response_data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            // OpenRouter/tools format
            $function_args = json_decode($response_data['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);
        } else {
            throw new \Exception("Unexpected API response format - no tool_calls found");
        }
        
        if (!$function_args) {
            throw new \Exception("Failed to parse function arguments from API response");
        }
        
        if ($this->debug) {
            write_debug_log("Parsed function arguments", $function_args);
        }
        
        // Process game mechanics in the narrative
        if (isset($function_args['narrative'])) {
            $function_args['narrative'] = $this->processGameMechanics($function_args['narrative']);
        }

        // Get the last check result
        $last_check = $this->game_state->getLastCheckResult();
        if ($last_check) {
            // If we have branching options based on success/failure
            if (isset($function_args['options']) && !is_array($function_args['options'])) {
                if (isset($function_args['options']['success']) && isset($function_args['options']['failure'])) {
                    // Select the appropriate branch based on the check result
                    $function_args['options'] = $last_check['success'] ? 
                        $function_args['options']['success'] : 
                        $function_args['options']['failure'];
                }
            }
            // Clear the check result after using it
            $this->game_state->clearLastCheckResult();
        }

        // Handle options format compatibility
        if (isset($function_args['options']) && !is_array($function_args['options'])) {
            $function_args['options'] = array_values((array)$function_args['options']);
        }
        
        if ($this->debug) {
            write_debug_log("Processed narrative with game mechanics", [
                'narrative_length' => strlen($function_args['narrative']),
                'options_format' => is_array($function_args['options']) ? 'legacy' : 'branching',
                'had_skill_check' => !is_null($last_check)
            ]);
        }
        
        return $function_args;
    }

    public function makeApiCall($conversation) {
        // Get the last user message to check for action-based skill checks
        $last_message = end($conversation);
        $has_skill_check = preg_match('/\[(\w+)\s+DC:(\d+)\]/', $last_message['content'], $matches);
        
        if ($has_skill_check) {
            // Pre-roll the skill check based on the action's DC
            $attribute = $matches[1];
            $difficulty = intval($matches[2]);
            $stats = $this->game_state->getCharacterStats();
            
            // Handle different types of checks
            if (in_array($attribute, ['Agility', 'Appearance', 'Charisma', 'Dexterity', 'Endurance', 'Intellect', 'Knowledge', 'Luck', 'Perception', 'Spirit', 'Strength', 'Vitality', 'Willpower', 'Wisdom'])) {
                $check_result = $stats->skillCheck($attribute, $difficulty);
                // Display the roll result
                echo "\n🎲 " . $attribute . " Check: " . $check_result['roll'] . " + " . $check_result['modifier'] . " (modifier) = " . 
                     $check_result['roll'] + $check_result['modifier'] . " vs DC " . $difficulty . " - " . ($check_result['success'] ? "Success!" : "Failure!") . "\n\n";
            } else {
                $check_result = $stats->savingThrow($attribute, $difficulty);
                // Display the roll result
                echo "\n🎲 " . $attribute . " Save: " . $check_result['roll'] . " + " . $check_result['modifier'] . " (modifier) = " . 
                     $check_result['roll'] + $check_result['modifier'] . " vs DC " . $difficulty . " - " . ($check_result['success'] ? "Success!" : "Failure!") . "\n\n";
            }
            
            $this->game_state->setLastCheckResult($check_result);
            
            if ($this->debug) {
                write_debug_log("Action Check Result", [
                    'type' => 'action_check',
                    'attribute' => $attribute,
                    'difficulty' => $difficulty,
                    'result' => $check_result
                ]);
            }
        }

        // Get current stats for the system message
        $stats = $this->game_state->getCharacterStats();
        $current_stats = $stats->getStats();
        
        $attributes = [
            'Agility', 'Appearance', 'Charisma', 'Dexterity', 
            'Endurance', 'Intellect', 'Knowledge', 'Luck', 
            'Perception', 'Spirit', 'Strength', 'Vitality', 
            'Willpower', 'Wisdom'
        ];
        $formattedStats = $this->formatStatString($stats, $attributes);

        // Build the API request data
        $provider = $this->provider_manager->getProvider();
        
        // Define the function/tool schema
        $function_schema = [
            'name' => 'GameResponse',
            'description' => 'Response from the game, containing narrative description and available options. The narrative should be immersive but should NOT include the options list in the narrative - options will be displayed separately.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'narrative' => [
                        'type' => 'string',
                        'description' => 'A rich, atmospheric description of the current scene and its events. Do not include the options list in this field.'
                    ],
                    'options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string'
                        ],
                        'minItems' => 4,
                        'maxItems' => 4,
                        'description' => 'Exactly 4 options for the player to choose from. Each option should be a string that may include an emoji and optional skill check in format [Attribute DC:XX]'
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
        ];
        
        $data = [
            'model' => $this->provider_manager->getModel(),
            'messages' => array_merge(
                [
                    [
                        'role' => 'system',
                        'content' => "You are narrating a dark fantasy RPG game. Provide immersive narrative descriptions but DO NOT include the options list in the narrative - options will be displayed separately. Each action choice MUST include a skill check in the format [Attribute DC:difficulty], where difficulty is between " . $this->config['difficulty_range']['min'] . " and " . $this->config['difficulty_range']['max'] . ", representing a range from trivial to nearly impossible challenges. Use emojis to enhance the presentation of choices." . 
                            ($has_skill_check ? "\nLAST SKILL CHECK RESULT: " . $attribute . " Check " . ($check_result['success'] ? "SUCCEEDED" : "FAILED") . 
                            " (Rolled: " . $check_result['roll'] . " + " . $check_result['modifier'] . " = " . $check_result['roll'] + $check_result['modifier'] . " vs DC " . $difficulty . ")" : "") .
                            "\n\nThe player's current stats are:\n" .
                            "Primary Attributes:\n" .
                            $formattedStats .
                            "Derived Stats:\n" .
                            "Health: " . $stats->getStat('Health')['current'] . "/" . $stats->getStat('Health')['max'] . "\n" .
                            "Focus: " . $stats->getStat('Focus')['current'] . "/" . $stats->getStat('Focus')['max'] . "\n" .
                            "Stamina: " . $stats->getStat('Stamina')['current'] . "/" . $stats->getStat('Stamina')['max'] . "\n" .
                            "Sanity: " . $stats->getStat('Sanity')['current'] . "/" . $stats->getStat('Sanity')['max'] . "\n" .
                            "\nLevel: " . $stats->getLevel() . "\n" .
                            "Experience: " . $stats->getExperience() . "\n"
                    ]
                ],
                $conversation
            )
        ];
        
        // Always use tools format (OpenRouter is our only provider)
        $data['tools'] = [
            [
                'type' => 'function',
                'function' => $function_schema
            ]
        ];
        $data['tool_choice'] = [
            'type' => 'function',
            'function' => ['name' => 'GameResponse']
        ];
        
        // Add any provider-specific extra parameters
        $extra_params = $this->provider_manager->getExtraBodyParams();
        if (!empty($extra_params)) {
            $data = array_merge($data, $extra_params);
        }

        if ($this->debug) {
            write_debug_log("Making API call", [
                'provider' => $this->provider_manager->getProvider(),
                'model' => $data['model'],
                'conversation_length' => count($conversation),
                'has_skill_check' => $has_skill_check
            ]);
        }

        $ch = curl_init($this->provider_manager->getChatUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->provider_manager->getHeaders());
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['api']['timeout'] ?? 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            if ($this->debug) {
                write_debug_log("API call failed - cURL error", [
                    'error' => $curl_error
                ]);
            }
            throw new \Exception("API call failed: " . $curl_error);
        }

        if ($http_code !== 200) {
            if ($this->debug) {
                write_debug_log("API call failed", [
                    'http_code' => $http_code,
                    'response' => $response
                ]);
            }
            
            // Try to parse error message
            $error_data = json_decode($response, true);
            $error_message = $error_data['error']['message'] ?? $response;
            
            // Provide helpful error messages for common issues
            if ($http_code === 401) {
                throw new \Exception("Authentication failed. Please check your API key.");
            } else if ($http_code === 429) {
                throw new \Exception("Rate limit exceeded. Please wait a moment and try again.");
            } else if ($http_code === 404) {
                throw new \Exception("Model not found: " . $this->provider_manager->getModel() . ". Please check your model selection.");
            }
            
            throw new \Exception("API call failed with HTTP code $http_code: $error_message");
        }

        $response_data = json_decode($response, true);
        if ($this->debug) {
            write_debug_log("Raw API response", $response_data);
        }

        return $this->processApiResponse($response_data);
    }
} 