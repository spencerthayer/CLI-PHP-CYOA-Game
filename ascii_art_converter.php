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

// Enhanced Configuration Array
$config = [
    'weights' => [
        'edge' => 0.4,       // Weight for edge strength
        'variance' => 0.4,   // Weight for local variance
        'gradient' => 0.8,   // Weight for average gradient
        'intensity' => 0.2   //Weight for mean intensity
    ],
    'random_factor' => 0,    // Factor to introduce randomness in character selection
    'region_size' => 10       // Size of the local region for analysis (5x5)
];

// Gamma correction function
function applyGamma($luminance, $gamma = 1.2) {
    return pow($luminance, 0.2 / $gamma);
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
        [0, 0, 0], [128, 0, 0], [0, 128, 0], [128, 128, 0],
        [0, 0, 128], [128, 0, 128], [0, 128, 128], [192, 192, 192],
        [128, 128, 128], [255, 0, 0], [0, 255, 0], [255, 255, 0],
        [0, 0, 255], [255, 0, 255], [0, 255, 255], [255, 255, 255]
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

// CharacterSelector Class
class CharacterSelector {
    private $alphachars;
    private $blockchars;
    private $progressivechars;
    private $shadechars; // Renamed from shadeblocks to shadechars for consistency
    private $config;
    private $charComplexity;

    public function __construct($config) {
        $this->config = $config;

        // Initialize character sets
        $this->alphachars = preg_split('//u', "`.-':_,^=;><+!rc*/z?sLTv)J7(|Fi{C}fI31tlu[neoZ5Yxjya]2ESwqkP6h9d4VpOGbUAKXHm8RD#\$Bg0MNWQ%&@", -1, PREG_SPLIT_NO_EMPTY);
        $this->blockchars = preg_split('//u', "▏▎▍▌▐▖▗▘▝▞▚▙▛▜▟", -1, PREG_SPLIT_NO_EMPTY);
        $this->progressivechars = preg_split('//u', "▁▂▃▄▅▆▇█", -1, PREG_SPLIT_NO_EMPTY);
        $this->shadechars = preg_split('//u', "░▒▓█", -1, PREG_SPLIT_NO_EMPTY); // Corrected property name

        // Preprocess character complexities
        $this->preprocessCharacterComplexities();
    }

    // Preprocess character complexities
    private function preprocessCharacterComplexities() {
        $this->charComplexity = [];

        foreach (['block', 'progressive', 'alpha', 'shade'] as $type) {
            $chars = $this->{$type . 'chars'};
            foreach ($chars as $char) {
                $this->charComplexity[$type][$char] = $this->calculateCharComplexity($char);
            }
        }
    }

    // Calculate character complexity
    private function calculateCharComplexity($char) {
        $complexity = 0;
        if (preg_match('/[#@%&]/', $char)) $complexity += 1.0;
        if (preg_match('/[A-Z]/', $char)) $complexity += 0.8;
        if (preg_match('/[a-z]/', $char)) $complexity += 0.5;
        if (preg_match('/[0-9]/', $char)) $complexity += 0.3;
        if (preg_match('/[^\w\s]/', $char)) $complexity += 0.7;
        // Add more rules as needed
        return $complexity;
    }

    // Calculate local entropy
    private function calculateLocalEntropy($samples) {
        // Discretize values into bins (convert floats to integers)
        $bins = 10; // Number of bins for histogram
        $discretized = array_map(function($value) use ($bins) {
            return (int)floor($value * ($bins - 1));
        }, $samples);

        // Create histogram
        $histogram = array_count_values($discretized);
        $total_samples = count($samples);

        // Calculate entropy using the histogram
        $entropy = 0.0;
        foreach ($histogram as $count) {
            $probability = $count / $total_samples;
            if ($probability > 0) {
                $entropy -= $probability * log($probability, 2);
            }
        }

        // Normalize entropy to [0,1] range
        $max_entropy = log($bins, 2); // Maximum possible entropy for given bins
        return $max_entropy > 0 ? ($entropy / $max_entropy) : 0;
    }

    // Calculate contrast
    private function calculateContrast($mean, $variance) {
        return sqrt($variance) / ($mean + 0.01); // Avoid division by zero
    }

    // Calculate gradient magnitude using Sobel operator
    private function calculateGradient($img, $x, $y, $width, $height) {
        $sobelX = [[-1, 0, 1], [-2, 0, 2], [-1, 0, 1]];
        $sobelY = [[-1, -2, -1], [0, 0, 0], [1, 2, 1]];

        $gradX = 0;
        $gradY = 0;

        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                $px = min(max($x + $i, 0), $width - 1);
                $py = min(max($y + $j, 0), $height - 1);

                $rgb = imagecolorat($img, $px, $py);
                $luminance = $this->calculateLuminance($rgb);

                $gradX += $luminance * $sobelX[$i + 1][$j + 1];
                $gradY += $luminance * $sobelY[$i + 1][$j + 1];
            }
        }

        return sqrt($gradX * $gradX + $gradY * $gradY);
    }

    // Calculate luminance using Rec. 709
    private function calculateLuminance($rgb) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        // Ensure luminance is in [0,1] range
        return max(0, min(1, $luminance));
    }

    // Analyze local region characteristics
    private function analyzeRegion($img, $x, $y, $width, $height, $region_size = 5) {
        $half_size = floor($region_size / 2);
        $samples = [];
        $gradients = [];
        $min_lum = 1.0;
        $max_lum = 0.0;

        // Multi-scale sampling for better feature detection
        for ($scale = 1; $scale <= 2; $scale++) {
            $step = $scale;
            for ($i = -$half_size; $i <= $half_size; $i += $step) {
                for ($j = -$half_size; $j <= $half_size; $j += $step) {
                    $px = min(max($x + $i, 0), $width - 1);
                    $py = min(max($y + $j, 0), $height - 1);

                    $rgb = imagecolorat($img, $px, $py);
                    $luminance = $this->calculateLuminance($rgb);

                    $samples[] = $luminance;
                    $gradients[] = $this->calculateGradient($img, $px, $py, $width, $height);

                    $min_lum = min($min_lum, $luminance);
                    $max_lum = max($max_lum, $luminance);
                }
            }
        }

        // Calculate statistics with bounds checking
        $mean = !empty($samples) ? array_sum($samples) / count($samples) : 0;
        $variance = $this->calculateVariance($samples, $mean);
        $entropy = $this->calculateLocalEntropy($samples);
        $contrast = ($max_lum - $min_lum) / (max($max_lum, 0.001)); // Avoid division by zero

        return [
            'edge' => !empty($gradients) ? max($gradients) : 0,
            'variance' => $variance,
            'gradient' => !empty($gradients) ? array_sum($gradients) / count($gradients) : 0,
            'intensity' => $mean,
            'entropy' => $entropy,
            'contrast' => $contrast
        ];
    }

    // Improved variance calculation with null checks
    private function calculateVariance($samples, $mean) {
        if (empty($samples)) {
            return 0;
        }

        $squared_diff_sum = array_reduce($samples, function($carry, $item) use ($mean) {
            return $carry + pow($item - $mean, 2);
        }, 0);

        return $squared_diff_sum / count($samples);
    }

    // Calculate character weights based on region analysis
    private function calculateCharacterWeights($analysis) {
        $weights = [
            'block' => 0,
            'progressive' => 0,
            'alpha' => 0,
            'shade' => 0
        ];

        // Edge detection weight
        $edge_strength = $analysis['edge'];
        $weights['block'] += $edge_strength * $this->config['weights']['edge'];

        // Variance-based weighting
        $variance = $analysis['variance'];
        $weights['alpha'] += $variance * $this->config['weights']['variance'];

        // Gradient-based weighting
        $gradient = $analysis['gradient'];
        $weights['progressive'] += $gradient * $this->config['weights']['gradient'];

        // Intensity-based weighting (favoring mid-intensity for shadechars)
        $intensity = $analysis['intensity'];
        $weights['shade'] += (1 - abs($intensity - 0.5) * 2) * $this->config['weights']['intensity'];

        // Normalize weights
        $total = array_sum($weights);
        if ($total > 0) {
            array_walk($weights, function(&$w) use ($total) {
                $w /= $total;
            });
        }

        return $weights;
    }

    // Select specific character based on weighted probability and analysis
    public function selectCharacter($img, $x, $y, $width, $height, $luminance) {
        $analysis = $this->analyzeRegion($img, $x, $y, $width, $height, $this->config['region_size']);
        $weights = $this->calculateCharacterWeights($analysis);

        // Weighted random selection of character set
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        $selected_set = 'shade'; // Default fallback

        foreach ($weights as $type => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                $selected_set = $type;
                break;
            }
        }

        // Get appropriate character set
        $chars = match($selected_set) {
            'block' => $this->blockchars,
            'progressive' => $this->progressivechars,
            // 'alpha' => $this->alphachars,
            default => $this->shadechars
        };

        // Apply slight randomness to character selection within the chosen set
        $random_factor = $this->config['random_factor'];
        $random_adjustment = (mt_rand() / mt_getrandmax() - 0.5) * $random_factor;
        $adjusted_luminance = max(0, min(1, $luminance + $random_adjustment));

        $index = (int)($adjusted_luminance * (count($chars) - 1));
        $index = max(0, min(count($chars) - 1, $index));

        return $chars[$index];
    }
}

// Initialize character selector
$selector = new CharacterSelector($config);

// Main processing loop
$ascii_art = "";

// Define the size of the local region for analysis (e.g., 5x5)
$region_size = $config['region_size'];
$region_size = floor($region_size / 2);

// Calculate step sizes based on scale and region size
$step_x = $scale;
$step_y = ( $scale * $char_aspect );

// Ensure step sizes are at least 1 to prevent infinite loops
$step_x = max(1, $step_x);
$step_y = max(1, $step_y);

for ($y = 0; $y <= $height - $region_size; $y += $step_y) {
    for ($x = 0; $x <= $width - $region_size; $x += $step_x) {
        // Get RGB values
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Calculate luminance using Rec. 709 and apply gamma correction
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        $luminance = applyGamma($luminance, 1.2); // Adjust the gamma as needed

        // Select character using the advanced system
        $char = $selector->selectCharacter($img, $x, $y, $width, $height, $luminance);

        // Find the closest ANSI color
        $ansiColor = findClosestAnsiColor($r, $g, $b, $ansi_lab_palette);

        // Add ANSI color escape code and the selected character
        $ascii_art .= "\e[38;5;" . $ansiColor . "m" . $char;
    }
    $ascii_art .= "\e[0m" . PHP_EOL; // Reset to default at the end of each line
}

// Output the ASCII art
echo $ascii_art;

?>
