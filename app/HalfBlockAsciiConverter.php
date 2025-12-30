<?php

namespace App;

/**
 * Half-Block ASCII Art Converter
 * 
 * Uses Unicode half-block characters (▀▄) with separate foreground and background
 * colors to effectively double the vertical resolution of ASCII art output.
 * Each terminal character cell displays two vertically stacked pixels.
 */
class HalfBlockAsciiConverter {
    
    // Upper half block character - foreground shows top pixel, background shows bottom pixel
    private const UPPER_HALF_BLOCK = '▀';
    
    // Lower half block character - foreground shows bottom pixel, background shows top pixel  
    private const LOWER_HALF_BLOCK = '▄';
    
    private $config;
    
    public function __construct($config = null) {
        $this->config = $config ?: [];
        // Don't call parent constructor - we don't need CharacterSelector
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
        
        // Use configured max dimensions
        $max_cols = isset($this->config['image']['max_width']) ? 
                    $this->config['image']['max_width'] : 
                    100;
        $max_terminal_rows = isset($this->config['image']['max_height']) ? 
                    $this->config['image']['max_height'] : 
                    50;
        
        // Calculate the image aspect ratio (width / height)
        $image_aspect = $orig_width / $orig_height;
        
        // With half-blocks, each terminal row displays 2 image pixels vertically
        // Terminal characters are roughly 2:1 (height:width), but half-blocks
        // give us 2 vertical pixels per character, so effective aspect is ~1:1
        // 
        // For a 16:9 image (aspect = 1.78):
        //   If we use 100 columns, we need 100/1.78 ≈ 56 image rows
        //   Since each terminal row = 2 image rows: 56/2 = 28 terminal rows
        //   Result: 100 cols × 28 terminal rows (displaying 100×56 pixels)
        
        // Calculate target dimensions
        // final_cols = image width in pixels (1 pixel = 1 column)
        // final_image_rows = image height in pixels (2 pixels = 1 terminal row)
        
        if ($image_aspect >= 1.0) {
            // Image is wider than tall (landscape) - constrain by width first
            $final_cols = $max_cols;
            $final_image_rows = round($final_cols / $image_aspect);
            $terminal_rows_needed = ceil($final_image_rows / 2);
            
            // Check if terminal rows exceed max
            if ($terminal_rows_needed > $max_terminal_rows) {
                $terminal_rows_needed = $max_terminal_rows;
                $final_image_rows = $terminal_rows_needed * 2;
                $final_cols = round($final_image_rows * $image_aspect);
            }
        } else {
            // Image is taller than wide (portrait) - constrain by height first
            $terminal_rows_needed = $max_terminal_rows;
            $final_image_rows = $terminal_rows_needed * 2;
            $final_cols = round($final_image_rows * $image_aspect);
            
            // Check if width exceeds max
            if ($final_cols > $max_cols) {
                $final_cols = $max_cols;
                $final_image_rows = round($final_cols / $image_aspect);
            }
        }
        
        // Ensure minimum dimensions
        $final_cols = max(1, $final_cols);
        $final_image_rows = max(2, $final_image_rows);
        
        // Ensure even number of image rows for proper half-block pairing
        if ($final_image_rows % 2 !== 0) {
            $final_image_rows++;
        }
        
        // Resize the image to the calculated dimensions
        $img = $this->resizeImage($img, $final_cols, $final_image_rows, $orig_width, $orig_height);
        
        $ascii_art = $this->processImage($img, $final_cols, $final_image_rows);
        return $this->centerAsciiArt($ascii_art);
    }
    
    /**
     * Process image using half-block characters for doubled vertical resolution
     */
    protected function processImage($img, $width, $height) {
        $ascii_art = "";
        
        // Pre-compute ANSI color palette in LAB color space for accurate matching
        $ansi_palette = $this->getAnsiColorPalette();
        $ansi_lab_palette = array_map(function($rgb) {
            return $this->rgb2lab($rgb[0], $rgb[1], $rgb[2]);
        }, $ansi_palette);
        
        // Process 2 rows at a time - each pair becomes one terminal row
        for ($y = 0; $y < $height; $y += 2) {
            $row = "";
            
            for ($x = 0; $x < $width; $x++) {
                // Get top pixel color
                $topRgb = imagecolorat($img, $x, $y);
                $topR = ($topRgb >> 16) & 0xFF;
                $topG = ($topRgb >> 8) & 0xFF;
                $topB = $topRgb & 0xFF;
                
                // Get bottom pixel color (use top color if at edge)
                if ($y + 1 < $height) {
                    $bottomRgb = imagecolorat($img, $x, $y + 1);
                    $bottomR = ($bottomRgb >> 16) & 0xFF;
                    $bottomG = ($bottomRgb >> 8) & 0xFF;
                    $bottomB = $bottomRgb & 0xFF;
                } else {
                    // Use top color for bottom if we're at the edge
                    $bottomR = $topR;
                    $bottomG = $topG;
                    $bottomB = $topB;
                }
                
                // Find closest ANSI colors for both pixels
                $topAnsi = $this->findClosestAnsiColor($topR, $topG, $topB, $ansi_lab_palette);
                $bottomAnsi = $this->findClosestAnsiColor($bottomR, $bottomG, $bottomB, $ansi_lab_palette);
                
                // Use upper half block: foreground = top color, background = bottom color
                // Format: \e[38;5;{fg}m\e[48;5;{bg}m▀
                $row .= "\e[38;5;" . $topAnsi . "m\e[48;5;" . $bottomAnsi . "m" . self::UPPER_HALF_BLOCK;
            }
            
            // Reset colors at end of each row
            $ascii_art .= $row . "\e[0m" . PHP_EOL;
        }
        
        return $ascii_art;
    }
    
    /**
     * Center the ASCII art in the terminal
     */
    protected function centerAsciiArt($ascii_art) {
        $terminal = $this->getTerminalSize();
        $terminal_width = $terminal['cols'];
        
        $lines = explode(PHP_EOL, $ascii_art);
        $centered_art = "";
        
        foreach ($lines as $line) {
            // Remove ANSI codes to get actual character count
            $clean_line = preg_replace('/\e\[[0-9;]*m/', '', $line);
            $line_length = mb_strlen($clean_line);
            
            if ($line_length > 0) {
                $padding = max(0, floor(($terminal_width - $line_length) / 2));
                $centered_art .= str_repeat(' ', $padding) . $line . PHP_EOL;
            } else {
                $centered_art .= PHP_EOL;
            }
        }
        
        return rtrim($centered_art);
    }
    
    /**
     * Get terminal dimensions
     */
    protected function getTerminalSize() {
        $size = [];
        
        if (PHP_OS_FAMILY === 'Windows') {
            $size['rows'] = 24;
            $size['cols'] = 80;
            
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
            $stty_output = @shell_exec('stty size 2>/dev/null') ?? '';
            if (preg_match('/^(\d+)\s+(\d+)$/', trim($stty_output), $matches)) {
                $size['rows'] = (int)$matches[1];
                $size['cols'] = (int)$matches[2];
            } else {
                $size['rows'] = 24;
                $size['cols'] = 80;
            }
        }
        
        return $size;
    }
    
    /**
     * Resize image using high-quality resampling
     */
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
    
    /**
     * Get the full ANSI 256-color palette
     */
    protected function getAnsiColorPalette() {
        $colors = [];

        // Standard colors (0-15)
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
    
    /**
     * Find the closest ANSI color using LAB color space for perceptual accuracy
     */
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
    
    /**
     * Convert RGB to LAB color space
     */
    protected function rgb2lab($r, $g, $b) {
        list($x, $y, $z) = $this->rgb2xyz($r, $g, $b);
        return $this->xyz2lab($x, $y, $z);
    }
    
    /**
     * Convert RGB to XYZ color space
     */
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
    
    /**
     * Convert XYZ to LAB color space
     */
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
    
    /**
     * Calculate Euclidean distance between two LAB colors
     */
    protected function labDistance($lab1, $lab2) {
        return sqrt(
            pow($lab1[0] - $lab2[0], 2) +
            pow($lab1[1] - $lab2[1], 2) +
            pow($lab1[2] - $lab2[2], 2)
        );
    }
}
