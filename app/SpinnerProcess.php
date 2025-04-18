<?php
// Usage: php SpinnerProcess.php <message>

// Enable signal handling
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() {
        echo "\r" . str_repeat(' ', 80) . "\r";
        passthru('stty sane');
        exit(0);
    });
}

$message = $argv[1] ?? 'Generating';
$frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
$frameCount = count($frames);
$i = 0;

// Check if STDIN is a terminal
$stdin_is_tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : false;

if ($stdin_is_tty) {
    // Save current terminal settings
    $sttySettings = shell_exec('stty -g');
    // Set terminal to cbreak mode and disable echo
    shell_exec('stty -icanon -echo');
    stream_set_blocking(STDIN, false);
    echo "(Press 'x' to cancel)\n";

    try {
        while (true) {
            echo "\r" . str_repeat(' ', 80) . "\r";
            echo $frames[$i % $frameCount] . " $message...";
            fflush(STDOUT);
            usleep(100000); // 100ms
            $i++;
            $input = fread(STDIN, 1);
            if ($input !== false && strtolower($input) === 'x') {
                echo "\r" . str_repeat(' ', 80) . "\r";
                echo "Generation cancelled by user.\n";
                break;
            }
        }
    } finally {
        // Restore terminal settings
        if (!empty($sttySettings)) {
            shell_exec('stty ' . $sttySettings);
        } else {
            shell_exec('stty sane');
        }
        stream_set_blocking(STDIN, true);
    }
} else {
    // Not a terminal: fallback spinner (no keypress detection)
    echo "(Cancellation unavailable: not running in a terminal)\n";
    while (true) {
        echo "\r" . str_repeat(' ', 80) . "\r";
        echo $frames[$i % $frameCount] . " $message...";
        fflush(STDOUT);
        usleep(100000); // 100ms
        $i++;
    }
}
