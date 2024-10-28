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

// Function to get the RGB values of the 256 ANSI color palette
function getAnsiColorPalette() {
    $colors = [];
    
    // Standard colors (0–15)
    $base_colors = [
        [0, 0, 0], [128, 0, 0], [0, 128, 0], [128, 128, 0], [0, 0, 128], [128, 0, 128], [0, 128, 128], [192, 192, 192],
        [128, 128, 128], [255, 0, 0], [0, 255, 0], [255, 255, 0], [0, 0, 255], [255, 0, 255], [0, 255, 255], [255, 255, 255]
    ];

    // Colors 16-231: 6x6x6 color cube
    for ($r = 0; $r < 6; $r++) {
        for ($g = 0; $g < 6; $g++) {
            for ($b = 0; $b < 6; $b++) {
                $colors[] = [
                    $r == 0 ? 0 : 55 + 40 * $r,
                    $g == 0 ? 0 : 55 + 40 * $g,
                    $b == 0 ? 0 : 55 + 40 * $b
                ];
            }
        }
    }

    // Colors 232-255: grayscale ramp
    for ($i = 0; $i < 24; $i++) {
        $gray = 8 + $i * 10;
        $colors[] = [$gray, $gray, $gray];
    }

    return array_merge($base_colors, $colors);
}

// Precompute ANSI palette RGB values
$ansi_palette = getAnsiColorPalette();

// Function to calculate the Euclidean distance between two RGB colors
function colorDistance($rgb1, $rgb2) {
    return sqrt(pow($rgb1[0] - $rgb2[0], 2) + pow($rgb1[1] - $rgb2[1], 2) + pow($rgb1[2] - $rgb2[2], 2));
}

// Function to find the closest ANSI color for a given RGB value
function findClosestAnsiColor($r, $g, $b, $ansi_palette) {
    $closest = 0;
    $min_distance = PHP_FLOAT_MAX;

    foreach ($ansi_palette as $i => $color) {
        $distance = colorDistance([$r, $g, $b], $color);
        if ($distance < $min_distance) {
            $min_distance = $distance;
            $closest = $i;
        }
    }

    return $closest;
}

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

        // Find the closest ANSI color
        $ansiColor = findClosestAnsiColor($r, $g, $b, $ansi_palette);

        // Add ANSI color escape code
        $ascii_art .= "\e[38;5;" . $ansiColor . "m" . $charsArray[$charIndex];
    }
    $ascii_art .= "\e[0m" . PHP_EOL;  // Reset to default at the end of each line
}

echo $ascii_art;

?>
