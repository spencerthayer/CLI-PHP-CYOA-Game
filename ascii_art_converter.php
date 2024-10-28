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
$shadeblocks = "░▒▓█";
$chars = " ".$shadeblocks;
$charsArray = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);

$cCount = count($charsArray);

// ANSI grayscale color codes from 232 to 255
$ansi_grayscale = range(232, 255);
$gCount = count($ansi_grayscale);

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
        $colorIndex = (int)(($gCount - 1) * $luminance);

        // Add ANSI color escape code
        $ascii_art .= "\e[38;5;" . $ansi_grayscale[$colorIndex] . "m" . $charsArray[$charIndex];
    }
    $ascii_art .= "\e[0m" . PHP_EOL;  // Reset to default at the end of each line
}

echo $ascii_art;

?>
