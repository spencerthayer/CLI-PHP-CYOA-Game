<?php

namespace App;

class GameState {
    private $config;
    private $conversation = [];
    private $scene_data = null;
    
    public function __construct($config) {
        $this->config = $config;
        $this->loadState();
    }
    
    public function loadState() {
        if (file_exists($this->config['paths']['game_history_file'])) {
            $history_content = file_get_contents($this->config['paths']['game_history_file']);
            if ($history_content !== false) {
                $this->conversation = json_decode($history_content, true) ?: [];
            }
        }
    }
    
    public function saveState() {
        file_put_contents(
            $this->config['paths']['game_history_file'], 
            json_encode($this->conversation), 
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
        
        $this->conversation[] = $message;
        $this->saveState();
    }
} 