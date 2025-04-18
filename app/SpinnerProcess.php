<?php
// Usage: php SpinnerProcess.php <message>
$message = $argv[1] ?? 'Generating';
$frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
$frameCount = count($frames);
$i = 0;
while (true) {
    echo "\r" . $frames[$i % $frameCount] . " $message...";
    usleep(100000); // 100ms
    $i++;
}
