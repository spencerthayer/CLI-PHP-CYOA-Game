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
        
        // Save with pretty printing for better readability
        file_put_contents(
            $this->config['paths']['game_history_file'], 
            json_encode($state, JSON_PRETTY_PRINT), 
            LOCK_EX
        );
        
        // After saving, update the conversation with the latest stats
        $this->updateConversationWithStats();
    }
    
    private function updateConversationWithStats() {
        // Find the last assistant message with a function call
        for ($i = count($this->conversation) - 1; $i >= 0; $i--) {
            $message = $this->conversation[$i];
            if (isset($message['role']) && $message['role'] === 'assistant' && isset($message['function_call'])) {
                if (isset($message['function_call']['name']) && $message['function_call']['name'] === 'GameResponse') {
                    // Update the character_stats in the message
                    $this->conversation[$i]['character_stats'] = $this->character_stats->getStats();
                    break;
                }
            }
        }
    }
    
    public function getConversation() {
        return $this->conversation;
    }
    
    public function addMessage($role, $content, $function_call = null) {
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time()
        ];
        
        if ($function_call) {
            $message['function_call'] = $function_call;
        }
        
        if ($this->debug) {
            write_debug_log("Adding message to conversation", [
                'role' => $role,
                'content_length' => strlen($content),
                'has_function_call' => !is_null($function_call)
            ]);
        }
        
        $this->conversation[] = $message;
        $this->saveState();
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
} 