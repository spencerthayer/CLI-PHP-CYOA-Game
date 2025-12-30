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
    
    /**
     * Check if we're running in an interactive terminal
     * @return bool True if interactive terminal, false otherwise
     */
    public static function isInteractiveTerminal() {
        // Check if STDIN is a TTY
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return false;
        }
        
        // posix_isatty is the most reliable check
        if (function_exists('posix_isatty')) {
            return posix_isatty(STDIN) && posix_isatty(STDOUT);
        }
        
        // Fallback: check if /dev/tty is available
        return @is_readable('/dev/tty');
    }
    
    public static function showLoadingAnimation($context = null, $customMessage = null) {
        // Skip spinner if not in an interactive terminal
        if (!self::isInteractiveTerminal()) {
            return null;
        }
        
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
        if ($pid && $pid > 0) {
            // Kill the spinner process
            @posix_kill($pid, SIGTERM);
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
        // If not in an interactive terminal, just run the operation directly
        if (!self::isInteractiveTerminal()) {
            $result = $operation();
            return $result !== false;
        }
        
        $spinner_pid = self::showLoadingAnimation($context);
        $sttySettings = @shell_exec('stty -g 2>/dev/null');
        @shell_exec('stty -icanon -echo 2>/dev/null');
        stream_set_blocking(STDIN, false);
        $cancelled = false;
        try {
            $work_done = false;
            $result = null;
            while (!$work_done) {
                // Check for 'x' keypress
                $input = @fread(STDIN, 1);
                if ($input !== false && strtolower($input) === 'x') {
                    if ($spinner_pid) {
                        posix_kill($spinner_pid, SIGTERM);
                    }
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
                @shell_exec('stty ' . $sttySettings . ' 2>/dev/null');
            } else {
                @shell_exec('stty sane 2>/dev/null');
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
                // Super ornate fancy border inspired by vintage ANSI art with muted gray colors for grimdark theme
                $colorCodes = [
                    // More muted, darker colors appropriate for a grimdark theme
                    'dark_gray' => "\033[90m",
                    'light_gray' => "\033[37m",
                    'mid_gray' => "\033[2;37m", // Dimmed white/gray
                    'dark_blue' => "\033[2;34m", // Dimmed blue
                    'dark_cyan' => "\033[2;36m", // Dimmed cyan
                    'dark_magenta' => "\033[2;35m", // Dimmed magenta
                    'darker_gray' => "\033[30;1m", // Bright black
                    'dark_red' => "\033[2;31m", // Dimmed red
                    'muted_white' => "\033[2;97m", // Dimmed bright white 
                    'reset' => "\033[0m"
                ];
                
                // Decorative elements
                $decorative_chars = ['⩢', '⩕', '⩓', '⩔', '⩖', '⩠'];
                $corner_tl = '╔';
                $corner_tr = '╗';
                $corner_bl = '╚';
                $corner_br = '╝';
                $h_edge = '═';
                $v_edge = '║';
                
                // Create colorful top decoration
                $top_pattern = '';
                $pattern_length = $maxLength + 4;
                for ($i = 0; $i < $pattern_length; $i++) {
                    $char_index = $i % count($decorative_chars);
                    $color_index = $i % 6; // Cycle through 6 colors
                    $color = array_values($colorCodes)[$color_index];
                    $top_pattern .= $color . $decorative_chars[$char_index] . $colorCodes['reset'];
                }
                
                // Build the bottom pattern similarly but with different colors
                $bottom_pattern = '';
                for ($i = 0; $i < $pattern_length; $i++) {
                    $char_index = ($i + 3) % count($decorative_chars); // Offset for variation
                    $color_index = (5 - ($i % 6)); // Reverse color order
                    $color = array_values($colorCodes)[$color_index];
                    $bottom_pattern .= $color . $decorative_chars[$char_index] . $colorCodes['reset'];
                }
                
                // Generate side decorations
                $left_decoration = $colorCodes['dark_blue'] . '⫷⟕' . $colorCodes['reset'];
                $right_decoration = $colorCodes['dark_blue'] . '⟖⫸' . $colorCodes['reset'];
                
                // Create fancy top border
                $top_border_decoration = $colorCodes['dark_gray'] . $top_pattern . $colorCodes['reset'];
                $top_border = $colorCodes['dark_cyan'] . $corner_tl . str_repeat($h_edge, $maxLength + 2) . $corner_tr . $colorCodes['reset'];
                
                // Create fancy bottom border
                $bottom_border = $colorCodes['dark_cyan'] . $corner_bl . str_repeat($h_edge, $maxLength + 2) . $corner_br . $colorCodes['reset'];
                $bottom_border_decoration = $colorCodes['dark_gray'] . $bottom_pattern . $colorCodes['reset'];
                
                // Create the framed text with ornate elements
                $ornateResults = [$top_border_decoration, $top_border];
                
                foreach ($lines as $line) {
                    $visibleLine = preg_replace('/\033\[[0-9;]*m/', '', $line);
                    $padding = $maxLength - mb_strlen($visibleLine);
                    
                    // Add colorful side decorations and vertical borders
                    $ornateResults[] = $left_decoration . ' ' . 
                                      $colorCodes['dark_cyan'] . $v_edge . $colorCodes['reset'] . 
                                      ' ' . $line . str_repeat(' ', $padding) . ' ' . 
                                      $colorCodes['dark_cyan'] . $v_edge . $colorCodes['reset'] . 
                                      ' ' . $right_decoration;
                }
                
                $ornateResults[] = $bottom_border;
                $ornateResults[] = $bottom_border_decoration;
                
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