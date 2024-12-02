<?php

namespace App;

class GameState {
    private $config;
    private $conversation = [];
    private $scene_data = null;
    private $character_stats;
    private $debug;
    
    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
        $this->character_stats = new CharacterStats($debug);
        if ($this->debug) {
            write_debug_log("GameState initialized with debug mode", [
                'debug' => $debug,
                'config' => $config
            ]);
        }
        $this->loadState();
    }
    
    public function loadState() {
        if (file_exists($this->config['paths']['game_history_file'])) {
            $history_content = file_get_contents($this->config['paths']['game_history_file']);
            if ($history_content !== false) {
                $data = json_decode($history_content, true) ?: [];
                $this->conversation = $data['conversation'] ?? [];
                if (isset($data['character_stats'])) {
                    $this->character_stats->loadState($data['character_stats']);
                }
                if ($this->debug) {
                    write_debug_log("Game state loaded", [
                        'conversation_count' => count($this->conversation),
                        'has_character_stats' => isset($data['character_stats'])
                    ]);
                }
            }
        }
    }
    
    public function saveState() {
        $state = [
            'conversation' => $this->conversation,
            'character_stats' => $this->character_stats->getState()
        ];
        
        if ($this->debug) {
            write_debug_log("Saving game state", [
                'conversation_count' => count($this->conversation),
                'character_stats' => $state['character_stats']
            ]);
        }
        
        file_put_contents(
            $this->config['paths']['game_history_file'], 
            json_encode($state), 
            LOCK_EX
        );
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
    
    public function getCharacterStats() {
        return $this->character_stats;
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
} 