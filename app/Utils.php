<?php

namespace App;

class Utils {
    public static function colorize($text) {
        $color_codes = [
            '[red]' => "\033[31m",
            '[/red]' => "\033[0m",
            '[green]' => "\033[32m",
            '[/green]' => "\033[0m",
            '[yellow]' => "\033[33m",
            '[/yellow]' => "\033[0m",
            '[blue]' => "\033[34m",
            '[/blue]' => "\033[0m",
            '[cyan]' => "\033[36m",
            '[/cyan]' => "\033[0m",
            '[bold]' => "\033[1m",
            '[/bold]' => "\033[22m",
        ];
        $wrapped_text = self::wrapText($text);
        return str_replace(array_keys($color_codes), array_values($color_codes), $wrapped_text);
    }
    
    public static function wrapText($text, $width = 192) {
        return wordwrap($text, $width, "\n", true);
    }
    
    public static function showLoadingAnimation($context = null, $customMessage = null) {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameCount = count($frames);

        // Determine the message based on context or use custom
        if ($customMessage) {
            $message = $customMessage;
        } elseif ($context === 'audio') {
            $message = "Generating audio";
        } elseif ($context === 'image') {
            $message = "Generating image";
        } else {
            $message = "Generating";
        }

        echo "\n";

        // Simple non-blocking animation
        for ($i = 0; $i < 10; $i++) {
            echo "\r" . $frames[$i % $frameCount] . " $message...";
            usleep(100000); // 100ms delay
            flush();
        }

        return null;
    }
    
    public static function stopLoadingAnimation($pid) {
        if ($pid) {
            echo "\r" . str_repeat(" ", 50) . "\r";
        }
    }
} 