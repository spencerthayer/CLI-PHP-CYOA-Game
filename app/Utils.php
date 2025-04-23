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
        // Apply the color codes
        return str_replace(array_keys($color_codes), array_values($color_codes), $text);
    }
    
    /**
     * Properly wrap text with ANSI color support
     * @param string $text Text to wrap
     * @param int $width Width to wrap at
     * @return string Wrapped text
     */
    public static function wrapText($text, $width = 120) {
        // First, normalize any existing newlines to avoid strange formatting
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Replace multiple spaces with single spaces (except after periods)
        $text = preg_replace('/(?<!\.) +/', ' ', $text);
        
        // Split by existing paragraphs (empty lines)
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        
        foreach ($paragraphs as $paragraph) {
            // Remove existing single newlines within paragraphs
            $paragraph = str_replace("\n", " ", $paragraph);
            
            // Trim excess whitespace
            $paragraph = trim($paragraph);
            
            // Word wrap the paragraph
            $wrapped = wordwrap($paragraph, $width, "\n", false);
            $result[] = $wrapped;
        }
        
        // Join with double newlines between paragraphs
        return implode("\n\n", $result);
    }
    
    /**
     * Add padding to each line of text
     * @param string $text The text to pad
     * @param int $leftPadding Number of spaces for left padding
     * @param int $rightPadding Number of spaces for right padding
     * @return string Padded text
     */
    public static function addTextPadding($text, $leftPadding = 4, $rightPadding = 4) {
        // Split text into lines
        $lines = explode("\n", $text);
        
        // Add padding to each line
        foreach ($lines as &$line) {
            $line = str_repeat(' ', $leftPadding) . $line . str_repeat(' ', $rightPadding);
        }
        
        // Rejoin lines
        return implode("\n", $lines);
    }
    
    public static function showLoadingAnimation($context = null, $customMessage = null) {
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

        // Start spinner as a background process with /dev/tty as STDIN and STDOUT
        $cmd = "php " . escapeshellarg(__DIR__ . "/SpinnerProcess.php") . " " . escapeshellarg($message) . " < /dev/tty > /dev/tty 2>&1 & echo $!";
        $pid = (int) shell_exec($cmd);
        return $pid;
    }
    
    public static function stopLoadingAnimation($pid) {
        if ($pid) {
            // Kill the spinner process
            posix_kill($pid, SIGTERM);
            echo "\r" . str_repeat(" ", 50) . "\r";
        }
    }

    /**
     * Run a long operation with spinner and support for 'x' cancellation from the terminal.
     * @param callable $operation function that does the work; should return true on success, false on error/cancel
     * @param string $context 'audio' or 'image' (spinner message)
     * @param string $cancelFlag path to a flag file to signal cancellation to the closure
     * @return bool true if completed, false if cancelled
     */
    public static function runWithSpinnerAndCancellation(callable $operation, $context = null, $cancelFlag = null) {
        $spinner_pid = self::showLoadingAnimation($context);
        $sttySettings = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');
        stream_set_blocking(STDIN, false);
        $cancelled = false;
        try {
            $work_done = false;
            $result = null;
            while (!$work_done) {
                // Check for 'x' keypress
                $input = fread(STDIN, 1);
                if ($input !== false && strtolower($input) === 'x') {
                    posix_kill($spinner_pid, SIGTERM);
                    echo "\nGeneration cancelled by user.\n";
                    $cancelled = true;
                    if ($cancelFlag) {
                        // Create a flag file to signal cancellation to the closure
                        file_put_contents($cancelFlag, '1');
                    }
                    break;
                }
                // Try to run a step of the operation if not yet started
                if ($result === null) {
                    $result = $operation();
                    $work_done = true;
                }
                usleep(100000); // 100ms
            }
        } finally {
            if (!empty($sttySettings)) {
                shell_exec('stty ' . $sttySettings);
            } else {
                shell_exec('stty sane');
            }
            stream_set_blocking(STDIN, true);
            self::stopLoadingAnimation($spinner_pid);
            // Clean up cancel flag if it exists
            if ($cancelFlag && file_exists($cancelFlag)) {
                @unlink($cancelFlag);
            }
        }
        return !$cancelled && $result !== false;
    }
}