<?php
/**
 * Chunky ASCII Art Converter CLI Tool
 * 
 * This script converts images to ASCII art using the Chunky font characters
 * 
 * Usage: php chunky.php <image_file> [options]
 * 
 * Options:
 *   -b, --block-size <size>     Size of image blocks (default: 8)
 *   -s, --simple                Use simple character set
 *   -a, --adaptive              Use adaptive block sizing
 *   -c, --categorized           Use categorized characters instead of density-sorted
 *   -o, --output <file>         Save output to file instead of terminal
 *   -h, --help                  Display this help message
 * 
 * Example: php chunky.php image.png -b 6 -a
 */

// Autoload classes
require_once __DIR__ . '/app/AsciiArtConverter.php';
require_once __DIR__ . '/app/CharacterSelector.php';
require_once __DIR__ . '/app/ChunkyAsciiArtConverter.php';

use App\ChunkyAsciiArtConverter;

// Function to parse command line arguments
function parseArguments($argv) {
    $options = [
        'image_file' => null,
        'block_size' => 8,
        'simple_mode' => false,
        'adaptive_mode' => false,
        'categorized_mode' => false,
        'output_file' => null,
        'help' => false
    ];
    
    // Skip the first argument (script name)
    array_shift($argv);
    
    // If no arguments provided, display help
    if (empty($argv)) {
        $options['help'] = true;
        return $options;
    }
    
    // The first argument is the image file
    if (!empty($argv) && substr($argv[0], 0, 1) !== '-') {
        $options['image_file'] = array_shift($argv);
    }
    
    // Parse the rest of the arguments
    $i = 0;
    while ($i < count($argv)) {
        $arg = $argv[$i];
        
        switch ($arg) {
            case '-b':
            case '--block-size':
                if (isset($argv[$i + 1]) && is_numeric($argv[$i + 1])) {
                    $options['block_size'] = (int)$argv[$i + 1];
                    $i += 2;
                } else {
                    echo "Error: Block size value required after {$arg}\n";
                    exit(1);
                }
                break;
            
            case '-s':
            case '--simple':
                $options['simple_mode'] = true;
                $i++;
                break;
            
            case '-a':
            case '--adaptive':
                $options['adaptive_mode'] = true;
                $i++;
                break;
                
            case '-c':
            case '--categorized':
                $options['categorized_mode'] = true;
                $i++;
                break;
            
            case '-o':
            case '--output':
                if (isset($argv[$i + 1])) {
                    $options['output_file'] = $argv[$i + 1];
                    $i += 2;
                } else {
                    echo "Error: Output file path required after {$arg}\n";
                    exit(1);
                }
                break;
            
            case '-h':
            case '--help':
                $options['help'] = true;
                $i++;
                break;
            
            default:
                echo "Unknown option: {$arg}\n";
                $i++;
                break;
        }
    }
    
    return $options;
}

// Display help message
function displayHelp() {
    echo "Chunky ASCII Art Converter\n";
    echo "-------------------------\n\n";
    echo "Convert images to ASCII art using the Chunky font character set.\n\n";
    echo "Usage: php chunky.php <image_file> [options]\n\n";
    echo "Options:\n";
    echo "  -b, --block-size <size>     Size of image blocks (default: 8)\n";
    echo "  -s, --simple                Use simple character set\n";
    echo "  -a, --adaptive              Use adaptive block sizing\n";
    echo "  -c, --categorized           Use categorized characters instead of density-sorted\n";
    echo "  -o, --output <file>         Save output to file instead of terminal\n";
    echo "  -h, --help                  Display this help message\n\n";
    echo "Examples:\n";
    echo "  php chunky.php image.png                    # Basic conversion\n";
    echo "  php chunky.php image.png -b 6 -a            # Adaptive blocks at size 6\n";
    echo "  php chunky.php image.png -c                 # Use categorized character selection\n";
    echo "  php chunky.php image.png -o output.txt      # Save to file\n";
}

// Create tools directory for utilities if it doesn't exist
if (!is_dir('tools')) {
    mkdir('tools', 0755, true);
}

// Parse command line arguments
$options = parseArguments($argv);

// Display help if requested or if no image file provided
if ($options['help'] || $options['image_file'] === null) {
    displayHelp();
    exit(0);
}

// Validate block size
if ($options['block_size'] < 1) {
    echo "Warning: Invalid block size, using default (8)\n";
    $options['block_size'] = 8;
}

// Validate image file
if (!file_exists($options['image_file'])) {
    echo "Error: File not found: {$options['image_file']}\n";
    exit(1);
}

// Check for density sorter
$densitySorterScript = __DIR__ . '/tools/generateChunkyGlyphDensity.php';
$densitySortedFile = __DIR__ . '/app/chunky_density_sorted.php';

// Generate density-sorted glyphs if using density mode and file doesn't exist
if (!$options['categorized_mode'] && !file_exists($densitySortedFile) && file_exists($densitySorterScript)) {
    echo "Generating density-sorted Chunky glyphs...\n";
    try {
        // Execute the density sorter script
        $result = shell_exec("php {$densitySorterScript} 2>&1");
        if (!file_exists($densitySortedFile)) {
            echo "Warning: Failed to generate density-sorted glyphs. Using categorized mode.\n";
            echo $result;
            $options['categorized_mode'] = true;
        }
    } catch (Exception $e) {
        echo "Warning: " . $e->getMessage() . "\n";
        echo "Using categorized mode instead.\n";
        $options['categorized_mode'] = true;
    }
}

try {
    // Create converter with appropriate settings
    $converter = new ChunkyAsciiArtConverter(
        null, 
        $options['block_size'], 
        !$options['simple_mode'],
        $options['adaptive_mode'],
        !$options['categorized_mode']
    );
    
    // Convert image to ASCII art
    $ascii_art = $converter->convertImage($options['image_file']);
    
    // Output to file or terminal
    if ($options['output_file']) {
        file_put_contents($options['output_file'], $ascii_art);
        echo "ASCII art saved to {$options['output_file']}\n";
    } else {
        echo $ascii_art;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 