<?php

namespace App;

class GameState {
    private $config;
    private $conversation = [];
    private $conversation_history = [];
    private $mechanics_history = [];
    private $scene_data = null;
    private $character_stats;
    private $debug;
    private $last_check_result = null;
    private $last_used_model = null;
    
    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
        $this->character_stats = new CharacterStats($debug);
        $this->loadState();
        
        if ($this->debug) {
            write_debug_log("GameState initialized", [
                'conversation_count' => count($this->conversation),
                'conversation_history_count' => count($this->conversation_history),
                'mechanics_count' => count($this->mechanics_history),
                'character_stats' => $this->character_stats->getStats()
            ]);
        }
    }
    
    public function loadState() {
        if (file_exists($this->config['paths']['game_history_file'])) {
            $history_content = file_get_contents($this->config['paths']['game_history_file']);
            if ($history_content !== false) {
                $data = json_decode($history_content, true) ?: [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    write_debug_log("JSON decode error in loadState: " . json_last_error_msg());
                }
                $this->conversation = $data['conversation'] ?? [];
                $this->conversation_history = $data['conversation_history'] ?? [];
                $this->mechanics_history = $data['mechanics_history'] ?? [];
                if (isset($data['character_stats'])) {
                    $this->character_stats->setState($data['character_stats']);
                }
                if ($this->debug) {
                    write_debug_log("Game state loaded", [
                        'conversation_count' => count($this->conversation),
                        'conversation_history_count' => count($this->conversation_history),
                        'mechanics_count' => count($this->mechanics_history),
                        'has_character_stats' => isset($data['character_stats'])
                    ]);
                }
            }
        } else if ($this->debug) {
            write_debug_log("No save file found, starting fresh", [
                'save_file' => $this->config['paths']['game_history_file']
            ]);
        }
    }
    
    public function saveState() {
        $state = [
            'conversation' => $this->conversation,
            'conversation_history' => $this->conversation_history,
            'mechanics_history' => $this->mechanics_history,
            'character_stats' => $this->character_stats->getStats()
        ];
        
        if ($this->debug) {
            write_debug_log("Saving game state", [
                'conversation_count' => count($this->conversation),
                'conversation_history_count' => count($this->conversation_history),
                'mechanics_count' => count($this->mechanics_history),
                'character_stats' => $state['character_stats']
            ]);
        }
        
        $save_file_path = $this->config['paths']['game_history_file'];
        $data_dir = dirname($save_file_path);
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
            if ($this->debug) write_debug_log("Created directory for save file: " . $data_dir);
        }
        
        if (file_put_contents($save_file_path, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX) !== false) {
            if ($this->debug) write_debug_log("Game state saved successfully to " . $save_file_path);
        } else {
            if ($this->debug) write_debug_log("Failed to save game state to " . $save_file_path . ": Check permissions or disk space");
        }
        
        $this->updateConversationWithStats();
    }
    
    private function updateConversationWithStats() {
        if ($this->debug) write_debug_log("Updating conversation with stats");
        for ($i = count($this->conversation) - 1; $i >= 0; $i--) {
            $message = $this->conversation[$i];
            if (isset($message['role']) && $message['role'] === 'assistant' && isset($message['function_call'])) {
                if (isset($message['function_call']['name']) && $message['function_call']['name'] === 'GameResponse') {
                    if ($this->debug) write_debug_log("Found GameResponse in conversation");
                    $this->conversation[$i]['character_stats'] = $this->character_stats->getStats();
                    if ($this->debug) write_debug_log("Updated stats in message");
                    break;
                }
            }
        }
        if ($this->debug) write_debug_log("Conversation update completed");
    }
    
    public function getConversation() {
        return $this->conversation;
    }
    
    public function addMessage($role, $content, $function_call = null, $tool_calls = null) {
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time()
        ];
        
        if ($function_call) {
            $message['function_call'] = $function_call;
        }
        
        if ($tool_calls) {
            $message['tool_calls'] = $tool_calls;
            
            // CRITICAL: For assistant messages with tool_calls, extract the narrative
            // and store it DIRECTLY in content so it's available for conversation history
            if ($role === 'assistant') {
                $narrative_content = $this->extractNarrativeFromToolCalls($tool_calls);
                if (!empty($narrative_content)) {
                    $message['content'] = $narrative_content;
                    if ($this->debug) {
                        write_debug_log("Stored narrative in assistant content", [
                            'narrative_length' => strlen($narrative_content),
                            'preview' => substr($narrative_content, 0, 100)
                        ]);
                    }
                } else if ($this->debug) {
                    write_debug_log("WARNING: Failed to extract narrative from tool_calls", [
                        'tool_calls' => json_encode($tool_calls)
                    ]);
                }
            }
        }
        
        if ($this->debug) {
            write_debug_log("Adding message to conversation", [
                'role' => $role,
                'content_length' => strlen($message['content']),
                'content_preview' => substr($message['content'], 0, 100),
                'has_function_call' => !is_null($function_call),
                'has_tool_calls' => !is_null($tool_calls)
            ]);
        }
        
        $this->conversation[] = $message;
        $this->saveState();
    }
    
    /**
     * Extract narrative text from tool_calls for story continuity
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
                    // Return the clean narrative so the AI knows what happened in the story
                    return $args['narrative'];
                }
            }
        }
        
        return '';
    }
    
    public function addToHistory($role, $content, $mechanics = null) {
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time()
        ];
        
        if ($mechanics) {
            $message['mechanics'] = $mechanics;
            $this->mechanics_history[] = [
                'timestamp' => time(),
                'details' => $mechanics
            ];
        }
        
        $this->conversation_history[] = $message;
        
        if ($this->debug) {
            write_debug_log("Added message to history", [
                'role' => $role,
                'content_length' => strlen($content),
                'has_mechanics' => !empty($mechanics),
                'total_messages' => count($this->conversation_history),
                'total_mechanics' => count($this->mechanics_history)
            ]);
        }
        
        $this->saveState();
    }
    
    public function getConversationHistory($include_mechanics = false) {
        if (!$include_mechanics) {
            return array_map(function($msg) {
                return [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }, $this->conversation_history);
        }
        return $this->conversation_history;
    }
    
    public function getMechanicsHistory() {
        return $this->mechanics_history;
    }
    
    public function getCharacterStats() {
        return $this->character_stats;
    }
    
    public function saveCharacterStats($stats) {
        if ($this->debug) {
            write_debug_log("Saving character stats", [
                'old_stats' => $this->character_stats->getStats(),
                'new_stats' => $stats->getStats()
            ]);
        }
        
        $this->character_stats = $stats;
        $this->saveState();
    }
    
    public function getLastAssistantResponse() {
        for ($i = count($this->conversation) - 1; $i >= 0; $i--) {
            $message = $this->conversation[$i];
            if ($message['role'] === 'assistant' && isset($message['function_call'])) {
                $function_call = $message['function_call'];
                if ($function_call['name'] === 'GameResponse') {
                    return json_decode($function_call['arguments']);
                }
            }
        }
        return null;
    }

    public function getSceneData() {
        $last_response = $this->getLastAssistantResponse();
        if ($last_response && isset($last_response->narrative) && isset($last_response->options)) {
            return $last_response;
        }
        return null;
    }

    public function setLastCheckResult($result) {
        $this->last_check_result = $result;
        if ($this->debug) {
            write_debug_log("Set last check result", [
                'success' => $result['success'],
                'total' => $result['total'],
                'details' => $result['details']
            ]);
        }
    }

    public function getLastCheckResult() {
        return $this->last_check_result;
    }

    public function clearLastCheckResult() {
        $this->last_check_result = null;
    }
    
    public function setLastUsedModel($model) {
        $this->last_used_model = $model;
        if ($this->debug) {
            write_debug_log("Set last used model", ['model' => $model]);
        }
    }
    
    public function getLastUsedModel() {
        return $this->last_used_model;
    }
} 