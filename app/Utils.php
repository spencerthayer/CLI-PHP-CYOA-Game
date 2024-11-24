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
        $wrapped_text = wordwrap($text, 192, "\n", true);
        return str_replace(array_keys($color_codes), array_values($color_codes), $wrapped_text);
    }
    
    public static function showLoadingAnimation($message = "Generating image") {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameCount = count($frames);
        
        echo "\n";
        
        $pid = pcntl_fork();
        
        if ($pid == 0) {
            $i = 0;
            while (true) {
                echo "\r" . $frames[$i % $frameCount] . " $message...";
                usleep(100000);
                $i++;
                flush();
            }
        }
        
        return $pid;
    }
    
    public static function stopLoadingAnimation($pid) {
        if ($pid) {
            posix_kill($pid, SIGTERM);
            pcntl_wait($pid);
            echo "\r" . str_repeat(" ", 50) . "\r";
        }
    }
} 