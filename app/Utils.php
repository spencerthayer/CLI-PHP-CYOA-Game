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

    /**
     * Get the current terminal width
     * @return int Width of the terminal
     */
    public static function getTerminalWidth() {
        // Try to detect terminal width using stty
        $width = 80; // Default fallback width
        
        if (PHP_OS_FAMILY !== 'Windows') {
            $output = [];
            $return_var = 0;
            exec('stty size 2>/dev/null', $output, $return_var);
            
            if ($return_var === 0 && !empty($output[0])) {
                $parts = explode(' ', trim($output[0]));
                if (count($parts) >= 2 && is_numeric($parts[1])) {
                    $width = (int)$parts[1];
                }
            }
        }
        
        return $width;
    }

    /**
     * Center a block of text in the terminal
     * @param string $text Text block to center
     * @return string Centered text block
     */
    public static function centerTextBlock($text) {
        $terminalWidth = self::getTerminalWidth();
        $lines = explode("\n", $text);
        $result = [];
        
        foreach ($lines as $line) {
            // Skip ANSI color codes when calculating visible length
            $visibleLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
            $lineLength = mb_strlen($visibleLine);
            
            if ($lineLength < $terminalWidth) {
                $paddingTotal = $terminalWidth - $lineLength;
                $leftPadding = intval($paddingTotal / 2);
                $result[] = str_repeat(' ', $leftPadding) . $line;
            } else {
                $result[] = $line;
            }
        }
        
        return implode("\n", $result);
    }

    /**
     * Add decorative flourishes around a text block
     * @param string $text Text to decorate
     * @param string $style Style of decoration ('simple', 'double', 'rounded', 'fancy')
     * @return string Decorated text
     */
    public static function addTextFlourishes($text, $style = 'simple') {
        $lines = explode("\n", $text);
        $maxLength = 0;
        
        // Find the maximum visible line length
        foreach ($lines as $line) {
            $visibleLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
            $maxLength = max($maxLength, mb_strlen($visibleLine));
        }
        
        // Select border characters based on style
        switch ($style) {
            case 'double':
                $hChar = '═';
                $vChar = '║';
                $tlChar = '╔';
                $trChar = '╗';
                $blChar = '╚';
                $brChar = '╝';
                break;
            case 'rounded':
                $hChar = '─';
                $vChar = '│';
                $tlChar = '╭';
                $trChar = '╮';
                $blChar = '╰';
                $brChar = '╯';
                break;
            case 'fancy':
                // Super ornate fancy border
                $topBorder = '╔' . str_repeat('═', $maxLength + 2) . '╗';
                $bottomBorder = '╚' . str_repeat('═', $maxLength + 2) . '╝';
                
                // Create the framed text with ornate elements
                $ornateResults = ["\033[33m" . '┏━━' . str_repeat('❧', ceil(($maxLength - 10) / 2)) . ' TALE ' . str_repeat('❧', floor(($maxLength - 10) / 2)) . '━━┓' . "\033[0m"];
                $ornateResults[] = $topBorder;
                
                foreach ($lines as $line) {
                    $visibleLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
                    $padding = $maxLength - mb_strlen($visibleLine);
                    $ornateResults[] = '║ ' . $line . str_repeat(' ', $padding) . ' ║';
                }
                
                $ornateResults[] = $bottomBorder;
                $ornateResults[] = "\033[33m" . '┗━━' . str_repeat('❧', floor($maxLength / 2)) . str_repeat('━', $maxLength % 2) . str_repeat('❧', floor($maxLength / 2)) . '━━┛' . "\033[0m";
                
                return implode("\n", $ornateResults);
                
            case 'simple':
            default:
                $hChar = '─';
                $vChar = '│';
                $tlChar = '┌';
                $trChar = '┐';
                $blChar = '└';
                $brChar = '┘';
                break;
        }
        
        // Early return for fancy style which builds its own result
        if ($style === 'fancy') {
            return;  // We already returned in the switch case
        }
        
        // Create top and bottom borders
        $topBorder = $tlChar . str_repeat($hChar, $maxLength + 2) . $trChar;
        $bottomBorder = $blChar . str_repeat($hChar, $maxLength + 2) . $brChar;
        
        // Create the framed text
        $result = [$topBorder];
        foreach ($lines as $line) {
            $visibleLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
            $padding = $maxLength - mb_strlen($visibleLine);
            $result[] = $vChar . ' ' . $line . str_repeat(' ', $padding) . ' ' . $vChar;
        }
        $result[] = $bottomBorder;
        
        return implode("\n", $result);
    }
}