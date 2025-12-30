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
    
    /**
     * Sanitize conversation messages for API - ensure all messages have readable content
     * Some models (especially via OpenRouter) don't properly read tool_calls in conversation history
     */
    private function sanitizeConversationForApi($conversation) {
        $sanitized = [];
        
        if ($this->debug) {
            write_debug_log("Sanitizing conversation", [
                'input_count' => count($conversation)
            ]);
        }
        
        foreach ($conversation as $index => $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            
            // Skip the initial system prompt - we add a fresh one in the API call
            // But KEEP skill check result system messages
            if ($role === 'system') {
                // Keep skill check results - they're critical for narrative branching
                if (strpos($content, '[SKILL CHECK RESULT:') !== false) {
                    // Convert skill check system message to a user context message
                    $sanitized[] = [
                        'role' => 'user',
                        'content' => $content
                    ];
                    if ($this->debug) {
                        write_debug_log("Kept skill check result as user context", [
                            'content' => substr($content, 0, 100)
                        ]);
                    }
                }
                continue;
            }
            
            // For assistant messages, ensure we have clean narrative content
            if ($role === 'assistant') {
                // Strip any wrapper prefixes if present
                $content = preg_replace('/^\[STORY CONTEXT:[^\]]*\]\s*/', '', $content);
                
                // If content is still empty but we have tool_calls, extract narrative from them
                if (empty(trim($content)) && isset($message['tool_calls'])) {
                    $narrative = $this->extractNarrativeFromToolCalls($message['tool_calls']);
                    if (!empty($narrative)) {
                        $content = $narrative;
                        if ($this->debug) {
                            write_debug_log("Extracted narrative from tool_calls", [
                                'index' => $index,
                                'narrative_length' => strlen($narrative)
                            ]);
                        }
                    }
                }
                
                // If still empty, try to get from function_call (legacy format)
                if (empty(trim($content)) && isset($message['function_call']['arguments'])) {
                    $args = $message['function_call']['arguments'];
                    if (is_string($args)) {
                        $args = json_decode($args, true);
                    }
                    if (is_array($args) && isset($args['narrative'])) {
                        $content = $args['narrative'];
                    }
                }
            }
            
            // Skip empty messages
            if (empty(trim($content))) {
                if ($this->debug) {
                    write_debug_log("Skipping empty message", [
                        'index' => $index,
                        'role' => $role
                    ]);
                }
                continue;
            }
            
            // Add sanitized message with just role and content
            $sanitized[] = [
                'role' => $role,
                'content' => $content
            ];
        }
        
        if ($this->debug) {
            write_debug_log("Sanitized conversation", [
                'output_count' => count($sanitized),
                'roles' => array_column($sanitized, 'role')
            ]);
        }
        
        return $sanitized;
    }
    
    /**
     * Build a story summary from the conversation to help maintain continuity
     * This is now minimal since context is injected into the user message
     */
    private function buildStorySummary($conversation) {
        // Context is now injected directly into the user message via injectStoryContext()
        // This function just provides basic instructions
        return "";
    }
    
    /**
     * Inject story context directly into the last user message
     * This ensures the AI sees the context even if it ignores conversation history
     * 
     * IMPORTANT: We now REPLACE the entire conversation with just the context-injected message
     * to prevent the model from getting confused by old messages
     */
    private function injectStoryContext($conversation) {
        if (count($conversation) < 2) {
            return $conversation;
        }
        
        // Find the last assistant message (previous scene) and last user message
        $last_assistant_idx = -1;
        $last_user_idx = -1;
        $skill_check_context = null;
        
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            if ($conversation[$i]['role'] === 'assistant' && $last_assistant_idx === -1) {
                $last_assistant_idx = $i;
            }
            if ($conversation[$i]['role'] === 'user') {
                // Check if this is a skill check result (converted from system)
                if (strpos($conversation[$i]['content'], '[SKILL CHECK RESULT:') !== false) {
                    $skill_check_context = $conversation[$i]['content'];
                } else if ($last_user_idx === -1) {
                    $last_user_idx = $i;
                }
            }
            if ($last_assistant_idx !== -1 && $last_user_idx !== -1) {
                break;
            }
        }
        
        // If we have both a previous scene and a user action after it, inject context
        if ($last_assistant_idx !== -1 && $last_user_idx !== -1 && $last_user_idx > $last_assistant_idx) {
            $previous_scene = $conversation[$last_assistant_idx]['content'];
            $user_action = $conversation[$last_user_idx]['content'];
            
            // Don't modify "start game" message
            if ($user_action === 'start game') {
                return $conversation;
            }
            
            // Build a SINGLE comprehensive message that contains ALL context
            // This replaces the entire conversation to prevent model confusion
            $context_parts = [];
            $context_parts[] = "╔════════════════════════════════════════════════════════════════════════════╗";
            $context_parts[] = "║  MANDATORY STORY CONTINUATION - DO NOT IGNORE THIS CONTEXT                ║";
            $context_parts[] = "╚════════════════════════════════════════════════════════════════════════════╝";
            $context_parts[] = "";
            $context_parts[] = "You are continuing an EXISTING story. The player is currently in this scene:";
            $context_parts[] = "";
            $context_parts[] = "▼▼▼ CURRENT SCENE START ▼▼▼";
            $context_parts[] = $previous_scene;
            $context_parts[] = "▲▲▲ CURRENT SCENE END ▲▲▲";
            $context_parts[] = "";
            
            // Add skill check result if present
            if ($skill_check_context) {
                $context_parts[] = "★ SKILL CHECK RESULT: " . $skill_check_context;
                $context_parts[] = "";
            }
            
            $context_parts[] = "▶ PLAYER'S CHOSEN ACTION: " . $user_action;
            $context_parts[] = "";
            $context_parts[] = "╔════════════════════════════════════════════════════════════════════════════╗";
            $context_parts[] = "║  YOUR RESPONSE MUST:                                                       ║";
            $context_parts[] = "║  1. Continue from the EXACT scene described above                         ║";
            $context_parts[] = "║  2. Describe the DIRECT result of the player's action                     ║";
            $context_parts[] = "║  3. Stay in the SAME location (do NOT teleport elsewhere)                 ║";
            $context_parts[] = "║  4. Keep the SAME characters/situation (do NOT introduce new scenarios)   ║";
            $context_parts[] = "╚════════════════════════════════════════════════════════════════════════════╝";
            $context_parts[] = "";
            $context_parts[] = "FORBIDDEN: Starting a new story, changing locations arbitrarily, ignoring the scene above.";
            
            $enhanced_message = implode("\n", $context_parts);
            
            // CRITICAL: Return ONLY the enhanced message, discarding old conversation history
            // This prevents the model from being distracted by previous messages
            $result = [
                [
                    'role' => 'user',
                    'content' => $enhanced_message
                ]
            ];
            
            if ($this->debug) {
                write_debug_log("Injected story context - REPLACED conversation with single message", [
                    'previous_scene_length' => strlen($previous_scene),
                    'user_action' => $user_action,
                    'has_skill_check' => !is_null($skill_check_context),
                    'final_message_length' => strlen($enhanced_message)
                ]);
            }
            
            return $result;
        }
        
        return $conversation;
    }
    
    /**
     * Extract narrative text from tool_calls
     */
    private function extractNarrativeFromToolCalls($tool_calls) {
        if (!is_array($tool_calls) || empty($tool_calls)) {
            return '';
        }
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['function']['arguments'])) {
                $args = $tool_call['function']['arguments'];
                if (is_string($args)) {
                    $args = json_decode($args, true);
                }
                if (is_array($args) && isset($args['narrative'])) {
                    // Return the narrative so the AI knows what happened in the story
                    return $args['narrative'];
                }
            }
        }
        
        return '';
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

        // Handle options format - convert new format to display format
        if (isset($function_args['options']) && is_array($function_args['options'])) {
            $formatted_options = [];
            foreach ($function_args['options'] as $option) {
                if (is_object($option) || is_array($option)) {
                    // New format with separated emoji, text, and skill_check
                    $opt = (array)$option;
                    $emoji = isset($opt['emoji']) ? $opt['emoji'] : '';
                    $text = isset($opt['text']) ? $opt['text'] : '';
                    $skill_check = isset($opt['skill_check']) ? $opt['skill_check'] : '';
                    
                    // Combine with proper spacing
                    $formatted_options[] = trim($emoji . ' ' . $text . ' ' . $skill_check);
                } else {
                    // Legacy format - keep as is
                    $formatted_options[] = $option;
                }
            }
            $function_args['options'] = $formatted_options;
        } else if (isset($function_args['options']) && !is_array($function_args['options'])) {
            // Old branching format
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
        $skill_check_narrative = "";
        
        if ($has_skill_check) {
            $attribute = $matches[1];
            $difficulty = intval($matches[2]);
            
            // Get the last check result that was already performed in game.php
            $check_result = $this->game_state->getLastCheckResult();
            
            if (!$check_result) {
                // Fallback: If no check result stored, perform it now (shouldn't normally happen)
                $stats = $this->game_state->getCharacterStats();
                
                if (in_array($attribute, ['Agility', 'Appearance', 'Charisma', 'Dexterity', 'Endurance', 'Intellect', 'Knowledge', 'Luck', 'Perception', 'Spirit', 'Strength', 'Vitality', 'Willpower', 'Wisdom'])) {
                    $check_result = $stats->skillCheck($attribute, $difficulty);
                    // Don't display here - it was already displayed in game.php
                } else {
                    $check_result = $stats->savingThrow($attribute, $difficulty);
                }
            }
            
            if ($check_result) {
                // Calculate the margin of success/failure for gradient feedback
                $total_roll = $check_result['roll'] + $check_result['modifier'];
                $margin = $total_roll - $difficulty;
                $severity = "";
                
                if ($check_result['success']) {
                    if ($margin >= 10) {
                        $severity = "CRITICAL SUCCESS";
                    } elseif ($margin >= 5) {
                        $severity = "GREAT SUCCESS";
                    } elseif ($margin >= 0) {
                        $severity = "SUCCESS";
                    }
                } else {
                    if ($margin <= -10) {
                        $severity = "CRITICAL FAILURE";
                    } elseif ($margin <= -5) {
                        $severity = "MAJOR FAILURE";
                    } else {
                        $severity = "FAILURE";
                    }
                }
                
                // Create a narrative description of the skill check result with gradient feedback
                $skill_check_narrative = "\n[SKILL CHECK RESULT: " . $attribute . " roll of " . 
                    $total_roll . " vs DC " . $difficulty . " - " . $severity . " (margin: " . 
                    ($margin >= 0 ? "+" : "") . $margin . ").\n" .
                    "IMPORTANT: The player's action " . ($check_result['success'] ? "SUCCEEDS" : "FAILS") . 
                    ". Write the narrative accordingly. " .
                    ($check_result['success'] 
                        ? ($margin >= 10 ? "This is an exceptional success - describe remarkable results beyond expectations." 
                           : ($margin >= 5 ? "This is a solid success - describe competent achievement with some bonus." 
                              : "This is a narrow success - describe barely making it work."))
                        : ($margin <= -10 ? "This is a catastrophic failure - describe terrible consequences and complications." 
                           : ($margin <= -5 ? "This is a significant failure - describe clear setbacks and problems." 
                              : "This is a close failure - describe falling just short with minor consequences."))
                    ) . "]\n";
            }
            
            if ($check_result) {
                $this->game_state->setLastCheckResult($check_result);
                
                // Add the skill check result to the conversation history
                if (!empty($skill_check_narrative)) {
                    $this->game_state->addMessage('system', $skill_check_narrative);
                }
            }
            
            if ($this->debug) {
                write_debug_log("Action Check Result", [
                    'type' => 'action_check',
                    'attribute' => $attribute,
                    'difficulty' => $difficulty,
                    'result' => $check_result,
                    'narrative_added' => $skill_check_narrative
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
                            'type' => 'object',
                            'properties' => [
                                'emoji' => [
                                    'type' => 'string',
                                    'description' => 'A single emoji representing the action',
                                    'maxLength' => 2
                                ],
                                'text' => [
                                    'type' => 'string',
                                    'description' => 'The action description WITHOUT emoji or numbering'
                                ],
                                'skill_check' => [
                                    'type' => 'string',
                                    'description' => 'The skill check in format [Attribute DC:XX]'
                                ]
                            ],
                            'required' => ['emoji', 'text', 'skill_check']
                        ],
                        'minItems' => 4,
                        'maxItems' => 4,
                        'description' => 'Exactly 4 options for the player to choose from. Each option is an object with emoji, text, and skill_check fields.'
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
        
        // Get the updated conversation after adding skill check result
        $raw_conversation = $has_skill_check ? $this->game_state->getConversation() : $conversation;
        
        // Sanitize conversation for API - ensure all messages have readable content
        // Some models don't properly read tool_calls in conversation history
        $updated_conversation = $this->sanitizeConversationForApi($raw_conversation);
        
        // CRITICAL: Inject story context directly into the last user message
        // This forces the AI to see the previous scene context even if it ignores conversation history
        $updated_conversation = $this->injectStoryContext($updated_conversation);
        
        // Build the system prompt - VERY strict about context following
        $system_prompt = "You are the narrator for 'The Dying Earth', a dark fantasy RPG.\n\n" .
            "ABSOLUTE RULES - VIOLATION MEANS FAILURE:\n" .
            "1. The user message contains a SCENE and an ACTION. You MUST continue from that EXACT scene.\n" .
            "2. Your narrative describes what happens when the player does that action IN THAT LOCATION.\n" .
            "3. NEVER start a new story. NEVER teleport to a different location. NEVER ignore the context.\n" .
            "4. The ACTION the player chose IS what happens next. Describe THAT action's consequences.\n" .
            "5. If a SKILL CHECK result is provided, your narrative MUST reflect that outcome.\n\n" .

            "VALID ATTRIBUTES FOR SKILL CHECKS (use ONLY these):\n" .
            "Agility, Charisma, Dexterity, Endurance, Intellect, Knowledge, Luck, Perception, Spirit, Strength, Vitality, Willpower, Wisdom\n" .
            "DO NOT use: Intelligence (use Intellect), Arcana (use Knowledge), Stealth (use Agility), Athletics (use Strength)\n\n" .

            "RESPONSE FORMAT (use GameResponse tool):\n" .
            "- narrative: 2-4 paragraphs describing the DIRECT result of the player's action in the SAME location\n" .
            "- options: 4 choices with emoji, text, skill_check [Attribute DC:X] using ONLY valid attributes above\n" .
            "- image: Short prompt for scene visualization\n\n" .

            "PLAYER: Health " . $stats->getStat('Health')['current'] . "/" . $stats->getStat('Health')['max'] .
            " | Sanity " . $stats->getStat('Sanity')['current'] . "/" . $stats->getStat('Sanity')['max'] . "\n";
        
        if ($this->debug) {
            write_debug_log("Sending to API", [
                'message_count' => count($updated_conversation),
                'messages_preview' => array_map(function($m) {
                    return [
                        'role' => $m['role'],
                        'content_length' => strlen($m['content']),
                        'content_preview' => substr($m['content'], 0, 200)
                    ];
                }, $updated_conversation)
            ]);
        }
        
        $data = [
            'model' => $this->provider_manager->getModel(),
            'messages' => array_merge(
                [
                    [
                        'role' => 'system',
                        'content' => $system_prompt
                    ]
                ],
                $updated_conversation
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