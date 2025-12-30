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
        
        // Get terminal dimensions for bounds
        $terminal = $this->getTerminalSize();
        $terminal_width = $terminal['cols'];
        $terminal_height = $terminal['rows'] - 10; // Leave room for text below
        
        // Use configured max dimensions, don't limit by terminal size when piped
        // When running in a pipe (like our test), terminal detection fails and returns small values
        $max_cols = isset($this->config['image']['max_width']) ? 
                    $this->config['image']['max_width'] : 
                    100;
        $max_rows = isset($this->config['image']['max_height']) ? 
                    $this->config['image']['max_height'] : 
                    40;
        
        // Character aspect ratio (terminal chars are ~2.2x taller than wide)
        // This means we need ~2.2x more columns than rows to display a square
        $char_aspect_ratio = 2.2; 
        
        // Calculate the image aspect ratio
        $image_aspect = $orig_width / $orig_height;
        
        // For a 1:1 image to appear square in terminal, we need width = height * char_aspect_ratio
        // So for a square image: cols = rows * 2.2
        
        // Calculate what dimensions we need to preserve aspect ratio
        if ($image_aspect >= 1.0) {
            // Image is wider than tall or square
            // Try to use maximum width
            $final_cols = $max_cols;
            $final_rows = round($final_cols / ($char_aspect_ratio * $image_aspect));
            
            // Check if height exceeds max
            if ($final_rows > $max_rows) {
                $final_rows = $max_rows;
                $final_cols = round($final_rows * $char_aspect_ratio * $image_aspect);
            }
        } else {
            // Image is taller than wide
            // Try to use maximum height
            $final_rows = $max_rows;
            $final_cols = round($final_rows * $char_aspect_ratio * $image_aspect);
            
            // Check if width exceeds max
            if ($final_cols > $max_cols) {
                $final_cols = $max_cols;
                $final_rows = round($final_cols / ($char_aspect_ratio * $image_aspect));
            }
        }
        
        // Ensure minimum dimensions
        $final_cols = max(1, min($max_cols, $final_cols));
        $final_rows = max(1, min($max_rows, $final_rows));
        
        // Debug output (uncomment if needed)
        // error_log("[ASCII] Image: {$orig_width}x{$orig_height}, aspect: {$image_aspect}");
        // error_log("[ASCII] Max cols: {$max_cols}, Max rows: {$max_rows}");
        // error_log("[ASCII] Final dimensions: {$final_cols}x{$final_rows}");
        
        // Resize the image to the calculated dimensions
        $img = $this->resizeImage($img, $final_cols, $final_rows, $orig_width, $orig_height);
        
        $ascii_art = $this->processImage($img, $final_cols, $final_rows);
        return $this->centerAsciiArt($ascii_art);
    }
    
    protected function centerAsciiArt($ascii_art) {
        // Get terminal width for centering
        $terminal = $this->getTerminalSize();
        $terminal_width = $terminal['cols'];
        
        // Center each line of the ASCII art
        $lines = explode(PHP_EOL, $ascii_art);
        $centered_art = "";
        
        foreach ($lines as $line) {
            // Remove ANSI codes to get actual character count
            $clean_line = preg_replace('/\e\[[0-9;]*m/', '', $line);
            $line_length = mb_strlen($clean_line);
            
            if ($line_length > 0) {
                // Calculate padding for centering
                $padding = max(0, floor(($terminal_width - $line_length) / 2));
                $centered_art .= str_repeat(' ', $padding) . $line . PHP_EOL;
            } else {
                $centered_art .= PHP_EOL;
            }
        }
        
        return rtrim($centered_art);
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
            $stty_output = @shell_exec('stty size 2>/dev/null') ?? '';
            if (preg_match('/^(\d+)\s+(\d+)$/', trim($stty_output), $matches)) {
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
        
        // Process every pixel since we've already resized the image appropriately
        $step_x = 1;
        $step_y = 1;
        
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