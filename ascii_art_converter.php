<?php

// Check if an image file is passed via the command line
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

// Attempt to create an image from the file
$img = @imagecreatefromstring(file_get_contents($file));
if (!$img) {
    echo 'Unsupported image type or unable to open image.' . PHP_EOL;
    exit(1);
}

// Get the image dimensions
list($width, $height) = getimagesize($file);

// ASCII characters are typically taller than they are wide in most fonts
$char_aspect = 2.5; // Typical terminal character aspect ratio (height/width)
$scale = 2; // Base scale for sampling

// Character set for shading
$shadeblocks = "░▒▓█";
$chars = " " . $shadeblocks; // Include space for lighter areas
$charsArray = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);

$cCount = count($charsArray);

// Gamma correction function
function applyGamma($luminance, $gamma = 1.2) {
    return pow($luminance, 1 / $gamma);
}

// Function to convert RGB to XYZ
function rgb2xyz($r, $g, $b) {
    $r = $r / 255;
    $g = $g / 255;
    $b = $b / 255;

    if ($r > 0.04045) $r = pow((($r + 0.055) / 1.055), 2.4);
    else $r = $r / 12.92;

    if ($g > 0.04045) $g = pow((($g + 0.055) / 1.055), 2.4);
    else $g = $g / 12.92;

    if ($b > 0.04045) $b = pow((($b + 0.055) / 1.055), 2.4);
    else $b = $b / 12.92;

    $r = $r * 100;
    $g = $g * 100;
    $b = $b * 100;

    $x = $r * 0.4124 + $g * 0.3576 + $b * 0.1805;
    $y = $r * 0.2126 + $g * 0.7152 + $b * 0.0722;
    $z = $r * 0.0193 + $g * 0.1192 + $b * 0.9505;

    return [$x, $y, $z];
}

// Function to convert XYZ to LAB
function xyz2lab($x, $y, $z) {
    $ref_X = 95.047;
    $ref_Y = 100.000;
    $ref_Z = 108.883;

    $x = $x / $ref_X;
    $y = $y / $ref_Y;
    $z = $z / $ref_Z;

    if ($x > 0.008856) $x = pow($x, 1 / 3);
    else $x = (7.787 * $x) + (16 / 116);

    if ($y > 0.008856) $y = pow($y, 1 / 3);
    else $y = (7.787 * $y) + (16 / 116);

    if ($z > 0.008856) $z = pow($z, 1 / 3);
    else $z = (7.787 * $z) + (16 / 116);

    $l = (116 * $y) - 16;
    $a = 500 * ($x - $y);
    $b = 200 * ($y - $z);

    return [$l, $a, $b];
}

// Function to convert RGB to LAB
function rgb2lab($r, $g, $b) {
    list($x, $y, $z) = rgb2xyz($r, $g, $b);
    return xyz2lab($x, $y, $z);
}

// Function to calculate the Euclidean distance between two LAB colors
function labDistance($lab1, $lab2) {
    return sqrt(pow($lab1[0] - $lab2[0], 2) + pow($lab1[1] - $lab2[1], 2) + pow($lab1[2] - $lab2[2], 2));
}

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

// Precompute ANSI palette LAB values
$ansi_palette = getAnsiColorPalette();
$ansi_lab_palette = array_map(function($rgb) {
    return rgb2lab($rgb[0], $rgb[1], $rgb[2]);
}, $ansi_palette);

// Function to find the closest ANSI color for a given RGB value
function findClosestAnsiColor($r, $g, $b, $ansi_lab_palette) {
    $closest = 0;
    $min_distance = PHP_FLOAT_MAX;

    $lab1 = rgb2lab($r, $g, $b);

    foreach ($ansi_lab_palette as $i => $lab2) {
        $distance = labDistance($lab1, $lab2);
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

        // Calculate luminance and apply gamma correction
        $luminance = ($r + $g + $b) / (255 * 3);
        $luminance = applyGamma($luminance, 1.2); // Adjust the gamma as needed

        // Map luminance to character index
        $charIndex = (int)(($cCount - 1) * $luminance);

        // Find the closest ANSI color
        $ansiColor = findClosestAnsiColor($r, $g, $b, $ansi_lab_palette);

        // Add ANSI color escape code
        $ascii_art .= "\e[38;5;" . $ansiColor . "m" . $charsArray[$charIndex];
    }
    $ascii_art .= "\e[0m" . PHP_EOL;  // Reset to default at the end of each line
}

echo $ascii_art;

?>
