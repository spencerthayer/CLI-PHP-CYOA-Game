<?php

namespace App;

class CharacterSelector {
    private $alphachars;
    private $blockchars;
    private $progressivechars;
    private $shadechars;
    private $braillechars;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->initializeCharacterSets();
    }
    
    private function initializeCharacterSets() {
        // Initialize block characters for strong edges and geometric shapes
        $this->blockchars = [
            ['char' => '▀', 'weight' => 0.5],
            ['char' => '▄', 'weight' => 0.5],
            ['char' => '▚', 'weight' => 0.5],
            ['char' => '▞', 'weight' => 0.5],
            ['char' => '░', 'weight' => 0.25],
        ];
        
        // Progressive characters for smooth gradients
        $this->progressivechars = [
            ['char' => '▁', 'weight' => 0.125],
            ['char' => '▃', 'weight' => 0.375],
            ['char' => '▅', 'weight' => 0.625],
            ['char' => '▇', 'weight' => 0.875],
            ['char' => '░', 'weight' => 0.25],
        ];

        // Alpha characters ordered by visual density
        $alphaOrder = "`.'\",:;!i|Il()[]{}?-_+~<>iv^*";
        $this->alphachars = array_map(function($char) use ($alphaOrder) {
            $weight = (mb_strpos($alphaOrder, $char) + 1) / mb_strlen($alphaOrder);
            return ['char' => $char, 'weight' => $weight];
        }, preg_split('//u', $alphaOrder, -1, PREG_SPLIT_NO_EMPTY));

        // Shade characters for smooth areas
        $this->shadechars = [
            ['char' => ' ', 'weight' => 0.0],
            ['char' => '░', 'weight' => 0.25],
            ['char' => '▒', 'weight' => 0.5],
            ['char' => '▓', 'weight' => 0.75],
            ['char' => '█', 'weight' => 1.0]
        ];

        // Braille character groups with weights
        $brailleGroups = [
            ['chars' => '⠂⠄⡀⢀⠈⠐⠠⡀⠂', 'weight' => 0.33],
            ['chars' => '⠃⠉⠘⠰⢁⣀⠤', 'weight' => 0.44],
            ['chars' => '⠇⠋⢃⢉⠋⠙⠸⠴⠦⠇⠴⠜⠱', 'weight' => 0.55],
            ['chars' => '⢇⠹⠼⠧⠏⠵⡇⡙⡇', 'weight' => 0.66],
            ['chars' => '⠽⠟⢧⠻⡛⡗', 'weight' => 0.77],
            ['chars' => '⠿⠿', 'weight' => 0.88],
            ['chars' => '⢿⣯⡿', 'weight' => 1.0]
        ];

        $this->braillechars = [];
        foreach ($brailleGroups as $group) {
            $chars = preg_split('//u', $group['chars'], -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                $this->braillechars[] = ['char' => $char, 'weight' => $group['weight']];
            }
        }
    }

    public function selectCharacter($img, $x, $y, $width, $height, $luminance) {
        $analysis = $this->analyzeRegion($img, $x, $y, $width, $height, $this->config['region_size'] ?? 4);
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
            'alpha' => $this->alphachars,
            'braille' => $this->braillechars,
            default => $this->shadechars
        };

        // Apply slight randomness to character selection
        $random_factor = $this->config['random_factor'] ?? 0.05;
        $random_adjustment = (mt_rand() / mt_getrandmax() - 0.5) * $random_factor;
        $adjusted_luminance = max(0, min(1, $luminance + $random_adjustment));

        // Find the character with the closest weight
        $best_char = $chars[0]['char'];
        $min_diff = abs($chars[0]['weight'] - $adjusted_luminance);
        foreach ($chars as $char_data) {
            $diff = abs($char_data['weight'] - $adjusted_luminance);
            if ($diff < $min_diff) {
                $min_diff = $diff;
                $best_char = $char_data['char'];
            }
        }

        return $best_char;
    }

    private function analyzeRegion($img, $x, $y, $width, $height, $region_size = 5) {
        $half_size = (int)floor($region_size / 2);
        $samples = [];
        $gradients = [];
        $min_lum = 1.0;
        $max_lum = 0.0;

        // Multi-scale sampling for better feature detection
        for ($scale = 1; $scale <= 2; $scale++) {
            $step = $scale;
            for ($i = -$half_size; $i <= $half_size; $i += $step) {
                for ($j = -$half_size; $j <= $half_size; $j += $step) {
                    $px = (int)min(max($x + $i, 0), $width - 1);
                    $py = (int)min(max($y + $j, 0), $height - 1);

                    $rgb = imagecolorat($img, $px, $py);
                    $luminance = $this->calculateLuminance($rgb);

                    $samples[] = $luminance;
                    $gradients[] = $this->calculateGradient($img, $px, $py, $width, $height);

                    $min_lum = min($min_lum, $luminance);
                    $max_lum = max($max_lum, $luminance);
                }
            }
        }

        // Calculate statistics
        $mean = !empty($samples) ? array_sum($samples) / count($samples) : 0;
        $variance = $this->calculateVariance($samples, $mean);
        $entropy = $this->calculateLocalEntropy($samples);
        $contrast = ($max_lum - $min_lum) / (max($max_lum, 0.001));

        return [
            'edge' => !empty($gradients) ? max($gradients) : 0,
            'variance' => $variance,
            'gradient' => !empty($gradients) ? array_sum($gradients) / count($gradients) : 0,
            'intensity' => $mean,
            'entropy' => $entropy,
            'contrast' => $contrast
        ];
    }

    private function calculateCharacterWeights($analysis) {
        $weights = [
            'block' => 0,
            'progressive' => 0,
            'alpha' => 0,
            'shade' => 0,
            'braille' => 0
        ];
    
        // Reduce edge influence
        $edge_strength = $analysis['edge'] * 0.5;
        $weights['progressive'] += $edge_strength * ($this->config['weights']['edge'] ?? 0.4);
    
        // Reduce variance influence
        $variance = $analysis['variance'] * 0.5;
        $weights['block'] += $variance * ($this->config['weights']['variance'] ?? 0.4);
    
        // Moderate gradient influence
        $gradient = $analysis['gradient'];
        $weights['progressive'] += $gradient * ($this->config['weights']['gradient'] ?? 0.8);
    
        // Boost shading influence
        $intensity = $analysis['intensity'];
        $weights['shade'] += ((1 - abs($intensity - 0.5)) * 1.5) * ($this->config['weights']['intensity'] ?? 0.2);
    
        // Add braille weight calculation
        if ($analysis['intensity'] < 0.4 && $analysis['variance'] > 0.1) {
            $weights['braille'] += 0.6;
            $weights['shade'] *= 0.2;
        }
    
        // Normalize weights
        $total = array_sum($weights);
        if ($total > 0) {
            array_walk($weights, function(&$w) use ($total) {
                $w /= $total;
            });
        }
    
        // Give additional boost to shade weights after normalization
        $weights['shade'] *= 1.2;
    
        return $weights;
    }

    private function calculateLuminance($rgb) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }

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

    private function calculateVariance($samples, $mean) {
        if (empty($samples)) {
            return 0;
        }

        $squared_diff_sum = array_reduce($samples, function($carry, $item) use ($mean) {
            return $carry + pow($item - $mean, 2);
        }, 0);

        return $squared_diff_sum / count($samples);
    }

    private function calculateLocalEntropy($samples) {
        $bins = 10;
        $discretized = array_map(function($value) use ($bins) {
            return (int)floor($value * ($bins - 1));
        }, $samples);

        $histogram = array_count_values($discretized);
        $total_samples = count($samples);

        $entropy = 0.0;
        foreach ($histogram as $count) {
            $probability = $count / $total_samples;
            if ($probability > 0) {
                $entropy -= $probability * log($probability, 2);
            }
        }

        $max_entropy = log($bins, 2);
        return $max_entropy > 0 ? ($entropy / $max_entropy) : 0;
    }
} 