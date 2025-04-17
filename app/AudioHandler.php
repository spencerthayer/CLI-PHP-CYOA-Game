<?php

namespace App;

use App\Utils;

class AudioHandler {
    private $config;
    private $debug;
    private $tmp_dir;

    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug = $debug;
        $this->tmp_dir = $config['paths']['tmp_dir'] ?? './tmp';

        // Ensure tmp directory exists
        if (!is_dir($this->tmp_dir)) {
            if (!mkdir($this->tmp_dir, 0755, true)) {
                throw new \Exception("Failed to create temporary directory: {$this->tmp_dir}");
            }
        }
    }

    private function write_debug_log($message, $context = null) {
        if (!$this->debug) return;
        write_debug_log('[AudioHandler] ' . $message, $context);
    }

    /**
     * Generates speech from text using Pollinations.AI and plays it.
     *
     * @param string $text The text to speak.
     * @return bool True on success, false on failure.
     */
    public function speakNarrative(string $text): bool {
        if (empty(trim($text))) {
            $this->write_debug_log("Skipping empty text for speech.");
            return false;
        }

        $voice = $this->config['audio']['voice'] ?? 'alloy'; // Default voice
        $model = $this->config['audio']['model'] ?? 'openai-audio';
        $apiUrl = 'https://text.pollinations.ai/';
        $audio_file = $this->tmp_dir . '/speech.mp3';

        $encoded_text = rawurlencode($text);
        $url = "{$apiUrl}{$encoded_text}?model={$model}&voice={$voice}";

        // Use curl to download the audio file
        $curl_cmd = sprintf('curl -s -L -o %s "%s"', escapeshellarg($audio_file), $url);
        $this->write_debug_log("Executing curl command", ['command' => $curl_cmd]);
        shell_exec($curl_cmd . ' 2>&1'); // Redirect stderr to stdout to potentially capture errors

        // Check if the file was created and is not empty
        if (!file_exists($audio_file) || filesize($audio_file) === 0) {
            $this->write_debug_log("Failed to download audio file or file is empty.", ['url' => $url, 'file' => $audio_file]);
            @unlink($audio_file); // Attempt to clean up empty file
            return false;
        }
        $this->write_debug_log("Audio file downloaded successfully.", ['file' => $audio_file, 'size' => filesize($audio_file)]);

        // Use afplay (macOS specific) to play the audio file
        // TODO: Make the player configurable or add checks for different OS
        $play_cmd = sprintf('afplay %s', escapeshellarg($audio_file));
        $this->write_debug_log("Executing play command", ['command' => $play_cmd]);
        shell_exec($play_cmd . ' 2>&1');

        // Clean up the audio file
        $rm_cmd = sprintf('rm %s', escapeshellarg($audio_file));
        $this->write_debug_log("Executing cleanup command", ['command' => $rm_cmd]);
        shell_exec($rm_cmd);

        return true;
    }
} 