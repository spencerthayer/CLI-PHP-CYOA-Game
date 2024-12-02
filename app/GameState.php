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