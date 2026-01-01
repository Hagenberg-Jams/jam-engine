<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

use function file_exists;
use function file_put_contents;
use function is_dir;
use function glob;
use function is_array;
use function exec;
use function pathinfo;
use function str_replace;
use function preg_replace;
use function unlink;

/**
 * Command to convert all pixel images to a target format.
 *
 * This command:
 * - Converts all pixel images to any format supported by ImageMagick (webp, avif, jpg, png, etc.)
 * - Automatically detects if ImageMagick supports the target format
 * - Skips SVG files (vector graphics are preserved)
 * - Skips images that are already in the target format
 * - Updates all YAML files that reference the converted images
 * - Uses ImageMagick system-wide
 * - Future-proof: works with any format ImageMagick supports (e.g., AVIF, HEIC, JXL)
 */
class ConvertImagesCommand extends Command
{
    protected $signature = 'gamejam:convert-images 
                            {--format=webp : Target image format (webp, jpg, png, etc.)}
                            {--year= : Only convert images for a specific year}
                            {--dry-run : Show what would be converted without actually converting}';

    protected $description = 'Convert all pixel images to a target format and update references';

    protected array $convertedFiles = [];
    protected array $updatedYamlFiles = [];
    protected int $skippedCount = 0;
    protected int $convertedCount = 0;
    protected int $errorCount = 0;

    public function handle(): int
    {
        $targetFormat = strtolower($this->option('format') ?? 'webp');
        $yearFilter = $this->option('year');
        $dryRun = $this->option('dry-run');

        // Check ImageMagick
        exec('magick -version', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->error('ImageMagick is not installed or not available in PATH.');
            $this->info('Please install ImageMagick: https://imagemagick.org/script/download.php');
            return 1;
        }

        // Validate format by checking if ImageMagick supports it
        if (!$this->isFormatSupported($targetFormat)) {
            $this->error("ImageMagick does not support the format: {$targetFormat}");
            $this->info('Use "magick -list format" to see all supported formats.');
            return 1;
        }

        if ($dryRun) {
            $this->info('DRY RUN MODE - No files will be modified');
        }

        $this->info("Converting images to {$targetFormat} format...");
        $this->info(str_repeat("=", 80));

        // Process all YAML files
        $yamlFiles = glob('_data/games/games*.yaml');
        
        foreach ($yamlFiles as $yamlFile) {
            // Extract year from filename
            preg_match('/games(\d{4})\.yaml/', $yamlFile, $matches);
            $year = $matches[1] ?? null;
            
            if (!$year) {
                continue;
            }

            // Filter by year if specified
            if ($yearFilter && $year !== $yearFilter) {
                continue;
            }

            $this->info("\nProcessing year {$year}...");
            
            $data = Yaml::parseFile($yamlFile) ?? [];
            
            if (!is_array($data)) {
                continue;
            }

            $modified = false;

            foreach ($data as $index => $entry) {
                $gameName = $entry['game']['name'] ?? 'Unknown';
                
                // Process headerimage
                if (isset($entry['headerimage'])) {
                    $oldHeader = $entry['headerimage'];
                    $newHeader = $this->convertImageFile($year, $oldHeader, $targetFormat, $dryRun);
                    
                    if ($newHeader && $newHeader !== $oldHeader) {
                        $data[$index]['headerimage'] = $newHeader;
                        $modified = true;
                        if ($dryRun) {
                            $this->line("  [DRY RUN] {$gameName}: Header would be converted");
                        } else {
                            $this->line("  ✓ {$gameName}: Header converted");
                        }
                    }
                }

                // Process images
                if (isset($entry['images']) && is_array($entry['images'])) {
                    foreach ($entry['images'] as $imgIndex => $image) {
                        if (isset($image['file'])) {
                            $oldFile = $image['file'];
                            $newFile = $this->convertImageFile($year, $oldFile, $targetFormat, $dryRun);
                            
                            if ($newFile && $newFile !== $oldFile) {
                                $data[$index]['images'][$imgIndex]['file'] = $newFile;
                                $modified = true;
                            }
                        }

                        if (isset($image['thumb'])) {
                            $oldThumb = $image['thumb'];
                            $newThumb = $this->convertImageFile($year, $oldThumb, $targetFormat, $dryRun);
                            
                            if ($newThumb && $newThumb !== $oldThumb) {
                                $data[$index]['images'][$imgIndex]['thumb'] = $newThumb;
                                $modified = true;
                            }
                        }
                    }
                }
            }

            // Save updated YAML if modified
            if ($modified && !$dryRun) {
                $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                file_put_contents($yamlFile, $yamlContent);
                $this->info("  ✓ Updated {$yamlFile}");
                $this->updatedYamlFiles[] = $yamlFile;
            } elseif ($modified && $dryRun) {
                $this->info("  [DRY RUN] Would update {$yamlFile}");
            }
        }

        // Also check root _media directory for global images
        $this->info("\nProcessing root _media directory...");
        // Get all files (not just specific extensions - let ImageMagick handle format detection)
        $rootFiles = glob('_media/*');
        foreach ($rootFiles as $file) {
            if (is_dir($file)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // Skip non-image files, SVG, and ICO
            if (empty($ext) || $ext === 'svg' || $ext === 'ico' || $ext === 'manifest') {
                continue;
            }
            
            $this->convertImageFile(null, basename($file), $targetFormat, $dryRun, '_media');
        }

        // Update homepage.yaml if it exists
        $homepageYaml = '_data/homepage.yaml';
        if (file_exists($homepageYaml)) {
            $this->info("\nProcessing homepage.yaml...");
            $this->updateHomepageYaml($homepageYaml, $targetFormat, $dryRun);
        }

        // Summary
        $this->info("\n" . str_repeat("=", 80));
        $this->info("Conversion Summary:");
        $this->info("  Converted: {$this->convertedCount}");
        $this->info("  Skipped (already in target format): {$this->skippedCount}");
        $this->info("  Errors: {$this->errorCount}");
        if (!$dryRun) {
            $this->info("  Updated YAML files: " . count($this->updatedYamlFiles));
        }

        return $this->errorCount > 0 ? 1 : 0;
    }

    protected function isFormatSupported(string $format): bool
    {
        // Get list of supported formats from ImageMagick
        exec('magick -list format', $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Fallback: assume common formats are supported
            $commonFormats = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'heic', 'heif', 'jxl'];
            return in_array(strtolower($format), $commonFormats);
        }

        // Parse ImageMagick format list
        // Format list looks like: "     WEBP* WEBP      rw+   WebP Image Format"
        // We need to match the format name in the first column (may have * suffix)
        $formatUpper = strtoupper($format);
        $formatPattern = '/^\s*' . preg_quote($formatUpper, '/') . '\*?\s+/i';
        
        foreach ($output as $line) {
            // Skip header lines
            if (preg_match('/^Format\s+Module/i', $line) || preg_match('/^-+$/', $line)) {
                continue;
            }
            
            // Check if line starts with the format name (with optional * and spaces)
            if (preg_match($formatPattern, $line)) {
                return true;
            }
        }

        return false;
    }

    protected function convertImageFile(?string $year, string $filename, string $targetFormat, bool $dryRun, ?string $baseDir = null): ?string
    {
        // Skip SVG files (vector graphics)
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            return $filename;
        }

        // Normalize format for comparison (jpg/jpeg are equivalent)
        $normalizedExt = $this->normalizeFormat($ext);
        $normalizedTarget = $this->normalizeFormat($targetFormat);

        // Check if already in target format
        if ($normalizedExt === $normalizedTarget) {
            $this->skippedCount++;
            return $filename;
        }

        // Determine paths
        if ($year) {
            $mediaDir = "_media/{$year}";
        } else {
            $mediaDir = $baseDir ?? '_media';
        }

        $oldPath = "{$mediaDir}/{$filename}";
        
        if (!file_exists($oldPath)) {
            $this->warn("  ⚠ File not found: {$oldPath}");
            $this->errorCount++;
            return null;
        }

        // Generate new filename
        $pathInfo = pathinfo($filename);
        $newFilename = $pathInfo['filename'] . '.' . $targetFormat;
        $newPath = "{$mediaDir}/{$newFilename}";

        // Skip if target file already exists (shouldn't happen, but safety check)
        if (file_exists($newPath) && $newPath !== $oldPath) {
            $this->warn("  ⚠ Target file already exists: {$newPath}");
            return $newFilename;
        }

        if ($dryRun) {
            $this->line("  [DRY RUN] Would convert: {$filename} → {$newFilename}");
            $this->convertedCount++;
            return $newFilename;
        }

        // Convert with ImageMagick
        $cmd = 'magick';
        $cmd .= ' "' . str_replace('"', '\\"', $oldPath) . '"';
        
        // Set quality for lossy formats
        if ($this->isLossyFormat($targetFormat)) {
            $cmd .= ' -quality 90';
        }
        
        $cmd .= ' "' . str_replace('"', '\\"', $newPath) . '"';

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($newPath)) {
            $this->error("  ✗ Failed to convert: {$filename}");
            if (!empty($output)) {
                $this->error("    Output: " . implode("\n", $output));
            }
            $this->errorCount++;
            return null;
        }

        // Delete old file if conversion successful
        if ($oldPath !== $newPath) {
            @unlink($oldPath);
        }

        $this->convertedCount++;
        return $newFilename;
    }

    protected function normalizeFormat(string $format): string
    {
        // Normalize jpg/jpeg to a common format for comparison
        $format = strtolower($format);
        if ($format === 'jpg' || $format === 'jpeg') {
            return 'jpg';
        }
        return $format;
    }

    protected function isLossyFormat(string $format): bool
    {
        // Common lossy formats that benefit from quality setting
        $lossyFormats = ['webp', 'jpg', 'jpeg', 'avif', 'heic', 'heif', 'jxl'];
        return in_array(strtolower($format), $lossyFormats);
    }

    protected function updateHomepageYaml(string $yamlFile, string $targetFormat, bool $dryRun): void
    {
        $data = Yaml::parseFile($yamlFile) ?? [];
        
        if (!is_array($data)) {
            return;
        }

        $modified = false;

        // Update hero images
        if (isset($data['hero']['images']) && is_array($data['hero']['images'])) {
            foreach ($data['hero']['images'] as $index => $imageFile) {
                $newFile = $this->convertImageFile(null, $imageFile, $targetFormat, $dryRun, '_media');
                if ($newFile && $newFile !== $imageFile) {
                    $data['hero']['images'][$index] = $newFile;
                    $modified = true;
                }
            }
        }

        // Update about image
        if (isset($data['about']['image'])) {
            $oldImage = $data['about']['image'];
            $newImage = $this->convertImageFile(null, $oldImage, $targetFormat, $dryRun, '_media');
            if ($newImage && $newImage !== $oldImage) {
                $data['about']['image'] = $newImage;
                $modified = true;
            }
        }

        // Update sponsor logos (only pixel images, skip SVG)
        if (isset($data['sponsors']['items']) && is_array($data['sponsors']['items'])) {
            foreach ($data['sponsors']['items'] as $index => $sponsor) {
                if (isset($sponsor['logo'])) {
                    $oldLogo = $sponsor['logo'];
                    $ext = strtolower(pathinfo($oldLogo, PATHINFO_EXTENSION));
                    
                    // Skip SVG files
                    if ($ext === 'svg') {
                        continue;
                    }
                    
                    $newLogo = $this->convertImageFile(null, $oldLogo, $targetFormat, $dryRun, '_media');
                    if ($newLogo && $newLogo !== $oldLogo) {
                        $data['sponsors']['items'][$index]['logo'] = $newLogo;
                        $modified = true;
                    }
                }
            }
        }

        // Save updated YAML if modified
        if ($modified && !$dryRun) {
            $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            file_put_contents($yamlFile, $yamlContent);
            $this->info("  ✓ Updated {$yamlFile}");
            $this->updatedYamlFiles[] = $yamlFile;
        } elseif ($modified && $dryRun) {
            $this->info("  [DRY RUN] Would update {$yamlFile}");
        }
    }
}

