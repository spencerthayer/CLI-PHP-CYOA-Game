<?php

if (isset($argv[1]) && strlen($argv[1])) {
    $file = $argv[1];
} else {
    echo 'Please specify an image file.' . PHP_EOL;
    exit(1);
}

// Check if the file exists
if (!file_exists($file)) {
    echo 'File not found: ' . $file . PHP_EOL;
    exit(1);
}

$img = @imagecreatefromstring(file_get_contents($file));
if (!$img) {
    echo 'Unsupported image type or unable to open image.' . PHP_EOL;
    exit(1);
}

list($width, $height) = getimagesize($file);

// ASCII characters are typically taller than they are wide in most fonts
// We'll use this to adjust our sampling to maintain aspect ratio
$char_aspect = 2.5; // Typical terminal character aspect ratio (height/width)
$scale = 2; // Base scale for sampling

// Comprehensive character set for better shading
$alphachars = " `.-':_,^=;><+!rc*/z?sLTv)J7(|Fi{C}fI31tlu[neoZ5Yxjya]2ESwqkP6h9d4VpOGbUAKXHm8RD#\$Bg0MNWQ%&@";
$blockchars = " ░▒▓█";
$morechars = " ▁▂▃▄▅▆▇█▉▊▋▌▍▎▏▐▔▕▖▗▘▙▚▛▜▝▞▟░▒▓";
$chars = $blockchars;
$charsArray = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);

$cCount = count($charsArray);

$ascii_art = "";

// Adjust x_scale to account for character aspect ratio
$x_scale = $scale;
$y_scale = $scale * $char_aspect;

for ($y = 0; $y <= $height - $y_scale; $y += $y_scale) {
    for ($x = 0; $x <= $width - $x_scale; $x += $x_scale) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Calculate luminance
        $luminance = ($r + $g + $b) / (255 * 3);
        // Map luminance to character index
        $charIndex = (int)(($cCount - 1) * $luminance);
        $ascii_art .= $charsArray[$charIndex];
    }
    $ascii_art .= PHP_EOL;
}

echo $ascii_art;

?>