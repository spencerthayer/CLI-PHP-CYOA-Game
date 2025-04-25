<?php

namespace App;

class AsciiArtConverter {
    private $config;
    private CharacterSelector $selector;
    
    public function __construct($config = null) {
        $this->config = $config ?: [
            'weights' => [
                'edge' => 0.4,
                'variance' => 0.4,
                'gradient' => 0.8,
                'intensity' => 0.2
            ],
            'random_factor' => 0.05,
            'region_size' => 4
        ];
        
        // Ensure region_size is set
        if (!isset($this->config['region_size'])) {
            $this->config['region_size'] = 4;
        }
        
        $this->selector = new CharacterSelector($this->config);
    }
    
    public function convertImage($file) {
        if (!file_exists($file)) {
            throw new \Exception('File not found: ' . $file);
        }
        
        $img = @imagecreatefromstring(file_get_contents($file));
        if (!$img) {
            throw new \Exception('Unsupported image type or unable to open image.');
        }
        
        // Get original image dimensions
        list($orig_width, $orig_height) = getimagesize($file);
        
        // Get terminal dimensions and calculate scaling
        $terminal = $this->getTerminalSize();
        $max_cols = $terminal['cols'] - 2;
        $max_rows = $terminal['rows'] - 1;
        
        // Calculate scaling factors
        $char_aspect = 2.5;
        $width_scale = $max_cols / $orig_width;
        $height_scale = ($max_rows * $char_aspect) / $orig_height;
        $scale_factor = min($width_scale, $height_scale, 1);
        
        // Calculate new dimensions
        $width = max(1, floor($orig_width * $scale_factor));
        $height = max(1, floor($orig_height * $scale_factor));
        
        // Resize image if needed
        if ($scale_factor < 1) {
            $img = $this->resizeImage($img, $width, $height, $orig_width, $orig_height);
        }
        
        return $this->processImage($img, $width, $height);
    }
    
    protected function getTerminalSize() {
        $size = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Default size for Windows if unable to detect
            $size['rows'] = 24;
            $size['cols'] = 80;
            
            // Try to get actual window size using mode CON
            $output = [];
            exec('mode CON', $output);
            foreach ($output as $line) {
                if (preg_match('/^\s*Lines:\s*(\d+)/', $line, $matches)) {
                    $size['rows'] = (int)$matches[1];
                }
                if (preg_match('/^\s*Columns:\s*(\d+)/', $line, $matches)) {
                    $size['cols'] = (int)$matches[1];
                }
            }
        } else {
            // For Unix-like systems
            if (preg_match('/^(\d+)\s+(\d+)$/', trim(shell_exec('stty size 2>/dev/null')), $matches)) {
                $size['rows'] = (int)$matches[1];
                $size['cols'] = (int)$matches[2];
            } else {
                // Default size if unable to detect
                $size['rows'] = 24;
                $size['cols'] = 80;
            }
        }
        
        return $size;
    }
    
    protected function processImage($img, $width, $height) {
        $ascii_art = "";
        $scale = 1.25;
        $char_aspect = 2.5;
        $half_region = floor($this->config['region_size'] / 3);
        
        // Calculate step sizes
        $step_x = max(1, (int)floor($scale * $half_region));
        $step_y = max(1, (int)floor($scale * $char_aspect * $half_region));
        
        // Get ANSI color palette
        $ansi_palette = $this->getAnsiColorPalette();
        $ansi_lab_palette = array_map(function($rgb) {
            return $this->rgb2lab($rgb[0], $rgb[1], $rgb[2]);
        }, $ansi_palette);
        
        // Adjust bounds to prevent out-of-bounds access
        $max_x = $width - 1;
        $max_y = $height - 1;
        
        for ($y = 0; $y <= $max_y; $y += $step_y) {
            $y_int = min((int)$y, $max_y);
            for ($x = 0; $x <= $max_x; $x += $step_x) {
                $x_int = min((int)$x, $max_x);
                
                // Ensure we're within bounds
                if ($x_int >= $width || $y_int >= $height) {
                    continue;
                }
                
                $rgb = imagecolorat($img, $x_int, $y_int);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                $luminance = $this->applyGamma($luminance, 1.2);
                
                $char = $this->selector->selectCharacter($img, $x_int, $y_int, $width, $height, $luminance);
                $ansiColor = $this->findClosestAnsiColor($r, $g, $b, $ansi_lab_palette);
                
                $ascii_art .= "\e[38;5;" . $ansiColor . "m" . $char;
            }
            $ascii_art .= "\e[0m" . PHP_EOL;
        }
        
        return $ascii_art;
    }
    
    protected function resizeImage($img, $width, $height, $orig_width, $orig_height) {
        $resized = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG/GIF
        imagecolortransparent($resized, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
        imagedestroy($img);
        
        return $resized;
    }
    
    protected function getAnsiColorPalette() {
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
    
    protected function findClosestAnsiColor($r, $g, $b, $ansi_lab_palette) {
        $lab1 = $this->rgb2lab($r, $g, $b);
        $closest = 0;
        $min_distance = PHP_FLOAT_MAX;

        foreach ($ansi_lab_palette as $i => $lab2) {
            $distance = $this->labDistance($lab1, $lab2);
            if ($distance < $min_distance) {
                $min_distance = $distance;
                $closest = $i;
            }
        }

        return $closest;
    }
    
    protected function rgb2lab($r, $g, $b) {
        list($x, $y, $z) = $this->rgb2xyz($r, $g, $b);
        return $this->xyz2lab($x, $y, $z);
    }
    
    protected function rgb2xyz($r, $g, $b) {
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

        return [
            $r * 0.4124 + $g * 0.3576 + $b * 0.1805,
            $r * 0.2126 + $g * 0.7152 + $b * 0.0722,
            $r * 0.0193 + $g * 0.1192 + $b * 0.9505
        ];
    }
    
    protected function xyz2lab($x, $y, $z) {
        $ref_X = 95.047;
        $ref_Y = 100.000;
        $ref_Z = 108.883;

        $x = $x / $ref_X;
        $y = $y / $ref_Y;
        $z = $z / $ref_Z;

        if ($x > 0.008856) $x = pow($x, 1/3);
        else $x = (7.787 * $x) + (16/116);

        if ($y > 0.008856) $y = pow($y, 1/3);
        else $y = (7.787 * $y) + (16/116);

        if ($z > 0.008856) $z = pow($z, 1/3);
        else $z = (7.787 * $z) + (16/116);

        return [
            (116 * $y) - 16,
            500 * ($x - $y),
            200 * ($y - $z)
        ];
    }
    
    protected function labDistance($lab1, $lab2) {
        return sqrt(
            pow($lab1[0] - $lab2[0], 2) +
            pow($lab1[1] - $lab2[1], 2) +
            pow($lab1[2] - $lab2[2], 2)
        );
    }
    
    protected function applyGamma($luminance, $gamma = 1.2) {
        return pow($luminance, 0.2 / $gamma);
    }
} 