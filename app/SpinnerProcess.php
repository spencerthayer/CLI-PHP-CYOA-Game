<?php
// Usage: php SpinnerProcess.php <message>

// Enable signal handling
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() {
        // Clear the spinner line and exit
        echo "\r" . str_repeat(' ', 40) . "\r";
        exit(0);
    });
}

$message = $argv[1] ?? 'Generating';
$frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
$frameCount = count($frames);
$i = 0;
while (true) {
    echo "\r" . $frames[$i % $frameCount] . " $message...";
    usleep(100000); // 100ms
    $i++;
}
