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

$scale = 8; // Adjust scale for resolution

// Comprehensive character set for better shading
$alphachars = " `.-':_,^=;><+!rc*/z?sLTv)J7(|Fi{C}fI31tlu[neoZ5Yxjya]2ESwqkP6h9d4VpOGbUAKXHm8RD#\$Bg0MNWQ%&@";
$blockchars = " ▁▂▃▄▅▆▇█▉▊▋▌▍▎▏▐▔▕▖▗▘▙▚▛▜▝▞▟░▒▓";
$chars = $blockchars;
$charsArray = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);

$cCount = count($charsArray);

$ascii_art = "";

for ($y = 0; $y <= $height - $scale; $y += $scale) {
    for ($x = 0; $x <= $width - $scale; $x += ($scale / 2)) {
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
