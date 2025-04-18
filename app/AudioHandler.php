<?php

namespace App;

use App\Utils;

class AudioHandler {
    private $config;
    private $debug;
    private $tmp_dir;
    private $max_text_length = 5120;
    private $voice = 'ash';

    public function __construct($config, $debug = false) {
        $this->config = $config;
        $this->debug  = $debug;
        $this->tmp_dir = $config['paths']['tmp_dir'] ?? './speech';

        if (!is_dir($this->tmp_dir)) {
            if (!mkdir($this->tmp_dir, 0755, true)) {
                throw new \Exception("Failed to create speech directory: {$this->tmp_dir}");
            }
        }

        $abs = realpath($this->tmp_dir);
        $this->write_debug_log("Speech dir: {$abs}", [
            'writable' => is_writable($this->tmp_dir) ? 'yes' : 'no',
            'readable' => is_readable($this->tmp_dir) ? 'yes' : 'no'
        ]);
    }

    private function write_debug_log($msg, $ctx = null) {
        if (!$this->debug) return;
        write_debug_log('[AudioHandler] '.$msg, $ctx);
    }

    private function preprocessText(string $txt): string {
        $txt = preg_replace('/\033\[[0-9;]*m/', '', $txt);
        $txt = preg_replace('/\[\/?\w+\]/', '', $txt);
        $txt = preg_replace('/\s+/', ' ', $txt);
        $txt = preg_replace('/([.!?])(\w)/', '$1 $2', $txt);
        $txt = preg_replace('/[\x00-\x1F\x7F]/', '', $txt);
        return trim($txt);
    }

    private function getAudioPlayerCommand(string $file): ?string {
        $os = PHP_OS; $f = escapeshellarg($file);
        if (stripos($os,'DAR')===0) {
            foreach (['afplay','mpg123','mpg321','mplayer'] as $p) {
                if (shell_exec("which $p 2>/dev/null")) {
                    return "$p $f";
                }
            }
            return "/usr/bin/afplay $f";
        }
        if (stripos($os,'WIN')===0) {
            return "powershell -c (New-Object Media.SoundPlayer $f).PlaySync()";
        }
        foreach (['mpg123','mpg321','mplayer','aplay','ffplay'] as $p) {
            if ($path = trim(shell_exec("which $p 2>/dev/null"))) {
                return "$path $f";
            }
        }
        return null;
    }

    private function splitTextIntoChunks(string $txt): array {
        if (strlen($txt) <= $this->max_text_length) {
            return [$txt];
        }
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $txt, -1, PREG_SPLIT_NO_EMPTY);
        $cur = '';
        foreach ($sentences as $s) {
            if (strlen($cur) + strlen($s) + 1 > $this->max_text_length) {
                $chunks[] = $cur;
                $cur = $s;
            } else {
                $cur .= ($cur? ' ' : '') . $s;
            }
        }
        if ($cur) $chunks[] = $cur;
        return $chunks;
    }

    private function ensureSafeUrlLength(string $txt): string {
        $base = 'https://text.pollinations.ai/';
        $params = '?model=openai-audio&voice=' . rawurlencode($this->config['audio']['voice'] ?? $this->voice);
        $urlLen = strlen($base) + strlen(rawurlencode($txt)) + strlen($params);
        if ($urlLen > 2000) {
            return substr($txt, 0, $this->max_text_length - 3) . '...';
        }
        return $txt;
    }

    /**
     * Main TTS via GET
     */
    public function speakNarrative(string $text): bool {
        if (!trim($text)) {
            $this->write_debug_log("Empty text, skipping");
            return false;
        }

        $voice = $this->voice;
        $chunks = $this->splitTextIntoChunks($this->preprocessText($text));
        $ok = true;

        foreach ($chunks as $i => $chunk) {
            $chunk = $this->ensureSafeUrlLength($chunk);
            $ts    = time();
            $file  = "{$this->tmp_dir}/speech_{$ts}_{$i}.mp3";

            // wrap in Say: "…"
            $prompt = 'Say: "' . $chunk . '"';
            $url    = "https://text.pollinations.ai/"
                    . rawurlencode($prompt)
                    . "?model=openai-audio&voice=" . rawurlencode($voice);

            $loading_pid = Utils::showLoadingAnimation('audio');

            $this->write_debug_log("Fetching chunk {$i}", [
                'url'         => substr($url,0,200).'...',
                'chunk_length'=> strlen($chunk)
            ]);

            // cURL GET
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_VERBOSE        => $this->debug
            ]);
            $data = curl_exec($ch);
            $err  = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            Utils::stopLoadingAnimation($loading_pid);

            if ($err || $code !== 200 || !$data) {
                $this->write_debug_log("Failed chunk {$i}", [
                    'http_code' => $code,
                    'curl_err'  => $err,
                    'data_len'  => strlen($data)
                ]);
                $ok = false;
                continue;
            }

            file_put_contents($file, $data);
            $this->write_debug_log("Saved chunk {$i}", ['file'=>$file,'size'=>filesize($file)]);

            if ($cmd = $this->getAudioPlayerCommand($file)) {
                shell_exec("$cmd 2>&1");
            } else {
                $this->write_debug_log("No player for chunk {$i}");
                $ok = false;
            }

            // Optionally clean up:
            // @unlink($file);
        }

        return $ok;
    }

    /**
     * Quick test of GET-based TTS
     */
    public function testAudioGeneration(): void {
        $this->write_debug_log("Testing TTS GET endpoint");
        $this->speakNarrative("This is a quick test of Pollinations.AI text to speech.");
    }
} 