<?php

namespace App;

class ChunkyAsciiArtConverter extends AsciiArtConverter {
    // Extended character sets from the Chunky font organized by visual density and type
    private $chunkyMap = [
        // Blocks and shades (darkest to lightest)
        'blocks' => ['█', '▓', '▒', '░', '■', '□', '▄', '▌', '▐', '▀'],
        
        // Box drawing characters for edges and patterns
        'boxDrawing' => [
            '┌', '┐', '└', '┘', '├', '┤', '┬', '┴', '┼', '═', '║', '╔', '╗', '╚', '╝', 
            '╠', '╣', '╦', '╩', '╬', '╡', '╢', '╖', '╕', '╣', '╝', '╜', '╛', '┐',
            '└', '┴', '┬', '├', '─', '┼', '╞', '╟', '╚', '╔', '╩', '╦', '╠', '╧',
            '╨', '╤', '╥', '╙', '╘', '╒', '╓', '╫', '╪', '┘', '┌'
        ],
        
        // Symbols and special characters
        'symbols' => ['♥', '♦', '♣', '♠', '•', '◘', '○', '◙', '♂', '♀', '♪', '♫', '☼', 
                     '►', '◄', '↕', '‼', '¶', '§', '▬', '↨', '↑', '↓', '→', '←', '∟', '↔', '▲', '▼'],
        
        // Greek and math characters
        'greekMath' => ['α', 'ß', 'Γ', 'π', 'Σ', 'σ', 'µ', 'τ', 'Φ', 'Θ', 'Ω', 'δ', '∞', 'φ', 'ε', '∩', 
                        '≡', '±', '≥', '≤', '⌠', '⌡', '÷', '≈', '°', '∙', '·', '√', 'ⁿ', '²'],
        
        // Light characters (few pixels)
        'light' => [' ', '⌂', '`', '.', '\'', '"', ':', ';', '!', 'i', '|', 'I', 'l'],
        
        // Medium characters (some pixels)
        'medium' => ['(', ')', '[', ']', '{', '}', '?', '-', '_', '+', '~', '<', '>', '^', '*'],
        
        // Face characters
        'faces' => ['☺', '☻']
    ];
    
    // Sorted Chunky font glyphs by pixel density (from lowest to highest)
    // This will be populated in the constructor from the chunky_density_sorted.php file
    private $sortedChunkyGlyphs = [];
    
    private $blockSize;
    private $useExtendedChars;
    private $adaptiveMode;
    private $useDensitySortedGlyphs;
    
    /**
     * Constructor
     *
     * @param array|null $config Configuration parameters
     * @param int $blockSize Block size in pixels (default 8x8)
     * @param bool $useExtendedChars Whether to use extended characters
     * @param bool $adaptiveMode Use adaptive block sizing based on image features
     * @param bool $useDensitySortedGlyphs Use density-sorted glyphs (true) or categorized glyphs (false)
     */
    public function __construct($config = null, $blockSize = 8, $useExtendedChars = true, $adaptiveMode = false, $useDensitySortedGlyphs = true) {
        parent::__construct($config);
        $this->blockSize = $blockSize;
        $this->useExtendedChars = $useExtendedChars;
        $this->adaptiveMode = $adaptiveMode;
        $this->useDensitySortedGlyphs = $useDensitySortedGlyphs;
        
        // Initialize sorted glyphs array
        $this->initializeSortedGlyphs();
    }
    
    /**
     * Initialize the sorted glyphs array from the chunky_density_sorted.php file if it exists
     * Otherwise, generate a default sorted array
     */
    private function initializeSortedGlyphs() {
        $sortedGlyphsFile = __DIR__ . '/chunky_density_sorted.php';
        
        if (file_exists($sortedGlyphsFile)) {
            $this->sortedChunkyGlyphs = require($sortedGlyphsFile);
        } else {
            // Fallback to a simplified sorted list based on visual inspection
            $this->sortedChunkyGlyphs = $this->getDefaultSortedGlyphs();
        }
    }
    
    /**
     * Get the complete set of Chunky font glyphs in their original order
     */
    private function getChunkyFontGlyphs(): array
    {
        return preg_split('//u', <<<CHUNKY
☺☻♥♦♣♠•◘○◙♂♀♪♫☼
►◄↕‼¶§▬↨↑↓→←∟↔▲▼
 !"#$%&'()*+,-./
0123456789:;<=>?
@ABCDEFGHIJKLMNO
PQRSTUVWXYZ[\]^_
`abcdefghijklmno
pqrstuvwxyz{|}~⌂
ÇüéâäàåçêëèïîìÄÅ
ÉæÆôöòûùÿÖÜ¢£¥₧ƒ
áíóúñÑªº¿⌐¬½¼¡«»
░▒▓│┤╡╢╖╕╣║╗╝╜╛┐
└┴┬├─┼╞╟╚╔╩╦╠═╬╧
╨╤╥╙╘╒╓╫╪┘┌█▄▌▐▀
αßΓπΣσµτΦΘΩδ∞φε∩
≡±≥≤⌠⌡÷≈°∙·√ⁿ²■□
CHUNKY, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    /**
     * Get a default sorted array of glyphs based on visual density (fallback if chunky_density_sorted.php is not available)
     */
    private function getDefaultSortedGlyphs(): array
    {
        // Simple default sorting based on visual inspection
        // Space followed by light characters, then medium, then dense
        return [
            ' ', '.', '`', '\'', ':', ';', '"', '!', 'i', 'I', 'l', '|', '-', '_', '·',
            '¸', '˛', ',', '°', '∙', '·', 'ˆ', 'ˇ', '˘', '˙', '˚', '˜', '´', '̦',
            'j', '(', ')', '[', ']', '{', '}', '<', '>', '/', '\\', '∞', '≈', '≠', '≤', '≥',
            '+', '=', '×', '÷', '±', '∓', '∂', '∇', '∑', '∏', '∕', '√', '∛', '∜',
            '*', '#', '@', '&', '%', 'o', 'O', '◘', '○', '◙', '☼', '♀', '♂', '♪', '♫',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e',
            'f', 'g', 'h', 'k', 'm', 'n', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
            'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '◄', '►', '▲', '▼',
            '↑', '↓', '←', '→', '↔', '↕', '↖', '↗', '↘', '↙', '∟', '⌂', '☺', '☻', '♥',
            '♦', '♣', '♠', '•', '╜', '╛', '╞', '╟', '╚', '╔', '╩', '╦', '╠', '═', '╬',
            '╧', '╨', '╤', '╥', '╙', '╘', '╒', '╓', '╫', '╪', '┘', '┌', '┐', '└', '┴',
            '┬', '├', '┤', '┼', '│', '─', '┤╡', '╢', '╖', '╕', '╣', '║', '╗', '╝', '▀',
            '▄', '▌', '▐', '░', '▒', '▓', '■', '█'
        ];
    }
    
    /**
     * Overrides the parent processImage method to use Chunky font characters
     */
    protected function processImage($img, $width, $height) {
        $ascii_art = "";
        $scale = 1.0; // Adjusted for square characters
        $char_aspect = 1.0; // Chunky font is 8x8 so nearly square
        
        // Get ANSI color palette
        $ansi_palette = $this->getAnsiColorPalette();
        $ansi_lab_palette = array_map(function($rgb) {
            return $this->rgb2lab($rgb[0], $rgb[1], $rgb[2]);
        }, $ansi_palette);
        
        // Adjust bounds to prevent out-of-bounds access
        $max_x = $width - 1;
        $max_y = $height - 1;
        
        // Calculate image texture complexity for adaptive block sizing
        $complexity = 0;
        if ($this->adaptiveMode) {
            $complexity = $this->calculateImageComplexity($img, $width, $height);
        }
        
        for ($y = 0; $y <= $max_y;) {
            $row = "";
            
            // Adaptive block size based on local complexity
            $current_step_y = $this->getAdaptiveBlockSize($y, $complexity);
            $y_int = min((int)$y, $max_y);
            
            for ($x = 0; $x <= $max_x;) {
                // Adaptive block size based on local complexity
                $current_step_x = $this->getAdaptiveBlockSize($x, $complexity);
                $x_int = min((int)$x, $max_x);
                
                if ($x_int >= $width || $y_int >= $height) {
                    continue;
                }
                
                // Calculate region metrics
                $region_metrics = $this->calculateRegionMetrics(
                    $img, $x_int, $y_int, $width, $height, $current_step_x, $current_step_y
                );
                
                // Get average color for ANSI coloring
                $avg_rgb = $region_metrics['avg_color'];
                $ansiColor = $this->findClosestAnsiColor($avg_rgb[0], $avg_rgb[1], $avg_rgb[2], $ansi_lab_palette);
                
                // Select appropriate character based on region metrics
                $char = $this->selectChunkyCharacter($region_metrics);
                
                $row .= "\e[38;5;" . $ansiColor . "m" . $char;
                
                $x += $current_step_x;
            }
            
            if (!empty($row)) {
                $ascii_art .= $row . "\e[0m" . PHP_EOL;
            }
            
            $y += $current_step_y;
        }
        
        return $ascii_art;
    }
    
    /**
     * Get adaptive block size based on position and complexity
     */
    private function getAdaptiveBlockSize($position, $complexity) {
        if (!$this->adaptiveMode) {
            return $this->blockSize;
        }
        
        // Adjust block size based on image complexity
        // More complex images get smaller blocks for more detail
        $min_size = 4;
        $max_size = 12;
        
        // Invert complexity - higher complexity = smaller blocks
        $size_factor = 1 - $complexity;
        
        // Calculate block size between min and max
        $adaptive_size = $min_size + ($max_size - $min_size) * $size_factor;
        
        return max($min_size, min($max_size, (int)round($adaptive_size)));
    }
    
    /**
     * Calculate image complexity (0.0 to 1.0)
     */
    private function calculateImageComplexity($img, $width, $height) {
        $sample_count = 100; // Number of sample points
        $total_edge_strength = 0;
        
        // Sample random points in the image
        for ($i = 0; $i < $sample_count; $i++) {
            $x = mt_rand(1, $width - 2);
            $y = mt_rand(1, $height - 2);
            
            $edge_strength = $this->calculateEdgeStrength($img, $x, $y, $x + 1, $y + 1);
            $total_edge_strength += $edge_strength;
        }
        
        // Normalize to 0-1 range
        $avg_edge_strength = $total_edge_strength / $sample_count;
        $normalized = min(1.0, $avg_edge_strength * 5); // Scale factor to get a good range
        
        return $normalized;
    }
    
    /**
     * Calculate various metrics for a region of the image
     */
    private function calculateRegionMetrics($img, $x, $y, $width, $height, $step_x, $step_y) {
        $metrics = [
            'brightness' => 0,
            'variance' => 0,
            'edge_strength' => 0,
            'gradient_x' => 0,
            'gradient_y' => 0,
            'pattern' => 'none',
            'avg_color' => [0, 0, 0],
            'edge_direction' => 'none',
            'texture' => 'smooth'
        ];
        
        $total_r = 0;
        $total_g = 0;
        $total_b = 0;
        $pixel_count = 0;
        $brightness_values = [];
        $edge_directions = ['horizontal' => 0, 'vertical' => 0, 'diagonal' => 0];
        
        // Calculate region bounds
        $x_end = min($x + $step_x, $width);
        $y_end = min($y + $step_y, $height);
        
        // Process each pixel in the region
        for ($py = $y; $py < $y_end; $py++) {
            for ($px = $x; $px < $x_end; $px++) {
                if ($px >= $width || $py >= $height) continue;
                
                $rgb = imagecolorat($img, $px, $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                $total_r += $r;
                $total_g += $g;
                $total_b += $b;
                
                $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                $brightness_values[] = $brightness;
                $metrics['brightness'] += $brightness;
                
                // Calculate gradients if not at the edge
                if ($px < $width - 1 && $py < $height - 1) {
                    $rgb_right = imagecolorat($img, $px + 1, $py);
                    $rgb_down = imagecolorat($img, $px, $py + 1);
                    $rgb_diag = imagecolorat($img, $px + 1, $py + 1);
                    
                    $br_right = (0.299 * (($rgb_right >> 16) & 0xFF) + 0.587 * (($rgb_right >> 8) & 0xFF) + 0.114 * ($rgb_right & 0xFF)) / 255;
                    $br_down = (0.299 * (($rgb_down >> 16) & 0xFF) + 0.587 * (($rgb_down >> 8) & 0xFF) + 0.114 * ($rgb_down & 0xFF)) / 255;
                    $br_diag = (0.299 * (($rgb_diag >> 16) & 0xFF) + 0.587 * (($rgb_diag >> 8) & 0xFF) + 0.114 * ($rgb_diag & 0xFF)) / 255;
                    
                    $grad_x = abs($brightness - $br_right);
                    $grad_y = abs($brightness - $br_down);
                    $grad_diag = abs($brightness - $br_diag);
                    
                    $metrics['gradient_x'] += $grad_x;
                    $metrics['gradient_y'] += $grad_y;
                    
                    // Accumulate edge directions
                    $edge_directions['horizontal'] += $grad_y;
                    $edge_directions['vertical'] += $grad_x;
                    $edge_directions['diagonal'] += $grad_diag;
                }
                
                $pixel_count++;
            }
        }
        
        if ($pixel_count > 0) {
            // Calculate average brightness and color
            $metrics['brightness'] /= $pixel_count;
            $metrics['avg_color'] = [
                $total_r / $pixel_count,
                $total_g / $pixel_count,
                $total_b / $pixel_count
            ];
            
            // Calculate variance
            foreach ($brightness_values as $value) {
                $metrics['variance'] += pow($value - $metrics['brightness'], 2);
            }
            $metrics['variance'] /= $pixel_count;
            
            // Detect patterns in the region
            $metrics['pattern'] = $this->detectPattern($brightness_values, $step_x, $step_y);
            
            // Calculate edge strength using simple gradient
            $metrics['edge_strength'] = ($metrics['gradient_x'] + $metrics['gradient_y']) / (2 * $pixel_count);
            
            // Determine dominant edge direction
            if (max($edge_directions) > 0.1) {
                $max_direction = array_search(max($edge_directions), $edge_directions);
                $metrics['edge_direction'] = $max_direction;
            }
            
            // Determine texture type
            if ($metrics['variance'] > 0.05) {
                $metrics['texture'] = 'rough';
            } else if ($metrics['edge_strength'] > 0.2) {
                $metrics['texture'] = 'edgy';
            }
        }
        
        return $metrics;
    }
    
    /**
     * Calculate edge strength in a region
     */
    private function calculateEdgeStrength($img, $x, $y, $x_end, $y_end) {
        $edge_strength = 0;
        $count = 0;
        
        for ($py = $y; $py < $y_end - 1; $py++) {
            for ($px = $x; $px < $x_end - 1; $px++) {
                // Skip if out of bounds
                if ($px >= imagesx($img) || $py >= imagesy($img)) continue;
                
                // Get current pixel and neighbors
                $pixel = imagecolorat($img, $px, $py);
                $pixel_right = imagecolorat($img, $px + 1, $py);
                $pixel_down = imagecolorat($img, $px, $py + 1);
                
                // Calculate brightness
                $b1 = (((($pixel >> 16) & 0xFF) * 0.299) + ((($pixel >> 8) & 0xFF) * 0.587) + (($pixel & 0xFF) * 0.114)) / 255;
                $b2 = (((($pixel_right >> 16) & 0xFF) * 0.299) + ((($pixel_right >> 8) & 0xFF) * 0.587) + (($pixel_right & 0xFF) * 0.114)) / 255;
                $b3 = (((($pixel_down >> 16) & 0xFF) * 0.299) + ((($pixel_down >> 8) & 0xFF) * 0.587) + (($pixel_down & 0xFF) * 0.114)) / 255;
                
                // Calculate gradient magnitude
                $grad_x = abs($b1 - $b2);
                $grad_y = abs($b1 - $b3);
                $edge_strength += sqrt($grad_x * $grad_x + $grad_y * $grad_y);
                $count++;
            }
        }
        
        return ($count > 0) ? $edge_strength / $count : 0;
    }
    
    /**
     * Detect patterns in a region based on brightness values
     */
    private function detectPattern($brightness_values, $step_x, $step_y) {
        // Simple pattern detection based on brightness distribution
        if (count($brightness_values) < 4) {
            return 'none';
        }
        
        // Sort brightness values to check distribution
        sort($brightness_values);
        $min = $brightness_values[0];
        $max = end($brightness_values);
        $mid = $brightness_values[floor(count($brightness_values) / 2)];
        
        // Check for high contrast
        if ($max - $min > 0.7) {
            // Check if bright area is larger than dark area
            $bright_count = 0;
            $dark_count = 0;
            $threshold = ($max + $min) / 2;
            
            foreach ($brightness_values as $val) {
                if ($val > $threshold) {
                    $bright_count++;
                } else {
                    $dark_count++;
                }
            }
            
            if ($bright_count > $dark_count * 3) {
                return 'mostly_bright';
            } else if ($dark_count > $bright_count * 3) {
                return 'mostly_dark';
            } else {
                return 'high_contrast';
            }
        } else if ($max - $min < 0.2) {
            return 'flat';
        } else {
            return 'gradient';
        }
    }
    
    /**
     * Select appropriate Chunky font character based on region metrics
     * This method uses either density-sorted glyphs or the traditional categorized approach
     */
    private function selectChunkyCharacter($metrics) {
        // If not using extended characters, fall back to basic shade characters
        if (!$this->useExtendedChars) {
            return $this->selectBasicShade($metrics['brightness']);
        }
        
        // Use density-sorted glyphs if requested
        if ($this->useDensitySortedGlyphs && !empty($this->sortedChunkyGlyphs)) {
            return $this->selectDensitySortedCharacter($metrics);
        }
        
        // Otherwise use the traditional categorized approach
        return $this->selectCategorizedCharacter($metrics);
    }
    
    /**
     * Select a character from the density-sorted glyph array based on brightness
     */
    private function selectDensitySortedCharacter($metrics) {
        $brightness = $metrics['brightness'];
        $edge_strength = $metrics['edge_strength'];
        $variance = $metrics['variance'];
        
        // Apply minor randomization to avoid repeating patterns
        $randomness = 0.03; // 3% randomness
        $adjustedBrightness = max(0, min(1, $brightness + (mt_rand(-100, 100) / 100) * $randomness));
        
        // Invert brightness because our sorted array goes from light to dark
        $inverseBrightness = 1 - $adjustedBrightness;
        
        // Select character by direct mapping to the sorted array
        $index = (int)round($inverseBrightness * (count($this->sortedChunkyGlyphs) - 1));
        
        // Ensure index is within bounds
        $index = max(0, min(count($this->sortedChunkyGlyphs) - 1, $index));
        
        return $this->sortedChunkyGlyphs[$index];
    }
    
    /**
     * Select a character using the categorized approach based on region metrics
     */
    private function selectCategorizedCharacter($metrics) {
        $brightness = $metrics['brightness'];
        $variance = $metrics['variance'];
        $edge_strength = $metrics['edge_strength'];
        $edge_direction = $metrics['edge_direction'];
        
        // Character selection based on metrics
        if ($edge_strength > 0.25) {
            // For strong edges, use box drawing characters based on edge direction
            if ($edge_direction === 'horizontal') {
                return $this->getRandomChar(['═', '─', '━', '╍']);
            } else if ($edge_direction === 'vertical') {
                return $this->getRandomChar(['║', '│', '┃', '╏']);
            } else {
                return $this->getRandomChar($this->chunkyMap['boxDrawing']);
            }
        } else if ($variance > 0.1) {
            // For high variance areas (textured)
            if ($brightness < 0.3) {
                return $this->getRandomChar(array_merge($this->chunkyMap['blocks'], $this->chunkyMap['symbols']));
            } else if ($brightness < 0.7) {
                return $this->getRandomChar(array_merge($this->chunkyMap['medium'], $this->chunkyMap['greekMath']));
            } else {
                return $this->getRandomChar(array_merge($this->chunkyMap['light'], $this->chunkyMap['faces']));
            }
        } else {
            // For smoother areas, select based on brightness
            return $this->selectBasicShade($brightness);
        }
    }
    
    /**
     * Select a basic shade character based on brightness level
     */
    private function selectBasicShade($brightness) {
        if ($brightness < 0.1) {
            return '█';
        } else if ($brightness < 0.3) {
            return '▓';
        } else if ($brightness < 0.5) {
            return '▒';
        } else if ($brightness < 0.8) {
            return '░';
        } else {
            return ' ';
        }
    }
    
    /**
     * Get a random character from a character array
     */
    private function getRandomChar($charArray) {
        return $charArray[array_rand($charArray)];
    }
    
    /**
     * Convert an image directly using the ChunkyAsciiArtConverter
     */
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
        
        // Calculate scaling factors (using square characters)
        $width_scale = $max_cols / $orig_width;
        $height_scale = $max_rows / $orig_height;
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
}
