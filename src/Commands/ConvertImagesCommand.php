<?php

declare(strict_types=1);

namespace App\Commands;

use function exec;
use function file_exists;
use function file_put_contents;
use function glob;

use Illuminate\Console\Command;

use function is_array;
use function is_dir;
use function pathinfo;
use function str_replace;

use Symfony\Component\Yaml\Yaml;

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

    /** @var array<string> */
    protected array $convertedFiles = [];
    /** @var array<string> */
    protected array $updatedYamlFiles = [];
    protected int $skippedCount = 0;
    protected int $convertedCount = 0;
    protected int $errorCount = 0;

    public function handle(): int
    {
        $formatOption = $this->option('format');
        $targetFormat = strtolower(is_string($formatOption) ? $formatOption : 'webp');
        $yearFilter = $this->option('year');
        $dryRunOption = $this->option('dry-run');
        $dryRun = is_bool($dryRunOption) ? $dryRunOption : false;

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

        // STEP 1: Process all games YAML files (years)
        $yamlFiles = glob('_data/games/games*.yaml') ?: [];

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
                if (!is_array($entry) || !isset($entry['game']) || !is_array($entry['game'])) {
                    continue;
                }
                $gameName = is_string($entry['game']['name'] ?? null) ? $entry['game']['name'] : 'Unknown';

                // Process headerimage
                if (isset($entry['headerimage']) && is_string($entry['headerimage'])) {
                    $oldHeader = $entry['headerimage'];
                    $newHeader = $this->convertImageFile($year, $oldHeader, $targetFormat, $dryRun);

                    if ($newHeader && $newHeader !== $oldHeader && is_array($data[$index] ?? null)) {
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
                    $imageCount = 0;
                    $thumbCount = 0;

                    foreach ($entry['images'] as $imgIndex => $image) {
                        if (!is_array($image)) {
                            continue;
                        }
                        if (isset($image['file']) && is_string($image['file'])) {
                            $oldFile = $image['file'];
                            $newFile = $this->convertImageFile($year, $oldFile, $targetFormat, $dryRun);

                            if ($newFile && $newFile !== $oldFile && is_array($data[$index] ?? null) && is_array($data[$index]['images'] ?? null) && is_array($data[$index]['images'][$imgIndex] ?? null)) {
                                $data[$index]['images'][$imgIndex]['file'] = $newFile;
                                $modified = true;
                                $imageCount++;
                            }
                        }

                        if (isset($image['thumb']) && is_string($image['thumb'])) {
                            $oldThumb = $image['thumb'];
                            $newThumb = $this->convertImageFile($year, $oldThumb, $targetFormat, $dryRun);

                            if ($newThumb && $newThumb !== $oldThumb && is_array($data[$index] ?? null) && is_array($data[$index]['images'] ?? null) && is_array($data[$index]['images'][$imgIndex] ?? null)) {
                                $data[$index]['images'][$imgIndex]['thumb'] = $newThumb;
                                $modified = true;
                                $thumbCount++;
                            }
                        }
                    }

                    // Show summary for images/thumbs if any were converted
                    if ($imageCount > 0 || $thumbCount > 0) {
                        $summary = [];
                        if ($imageCount > 0) {
                            $summary[] = "{$imageCount} image(s)";
                        }
                        if ($thumbCount > 0) {
                            $summary[] = "{$thumbCount} thumbnail(s)";
                        }
                        if (count($summary) > 0) {
                            if ($dryRun) {
                                $this->line("  [DRY RUN] {$gameName}: " . implode(', ', $summary) . " would be converted");
                            } else {
                                $this->line("  ✓ {$gameName}: " . implode(', ', $summary) . " converted");
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

        // STEP 2: Process all other YAML files in _data/ directory
        $this->info("\nProcessing other YAML files in _data/...");
        $this->processAllYamlFiles($targetFormat, $dryRun);

        // STEP 3: Process all Blade templates
        $this->info("\nProcessing Blade templates...");
        $this->processBladeTemplates($targetFormat, $dryRun);

        // STEP 4: Process root _media directory (LAST - after all references are updated)
        $this->info("\nProcessing root _media directory...");
        // Only process known image file extensions
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'jxl', 'bmp', 'tiff', 'tif'];
        $rootFiles = glob('_media/*') ?: [];
        foreach ($rootFiles as $file) {
            if (is_dir($file)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // Only process known image formats, skip SVG, ICO, CSS, JS, manifest, etc.
            if (empty($ext) || !in_array($ext, $imageExtensions)) {
                continue;
            }

            $filename = basename($file);
            // Skip favicons, app icons, and 404 images (they should stay PNG for compatibility or are not used)
            $filenameLower = strtolower($filename);
            $skipPatterns = [
                'favicon-16x16', 'favicon-32x32', 'apple-touch-icon',
                'android-chrome-192x192', 'android-chrome-512x512',
                '404.png', '404_text.png',
            ];
            $skipFile = false;
            foreach ($skipPatterns as $pattern) {
                if (str_contains($filenameLower, $pattern)) {
                    $skipFile = true;
                    break;
                }
            }

            if (!$skipFile) {
                $this->convertImageFile(null, $filename, $targetFormat, $dryRun, '_media');
            }
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
            // Fallback: assume common formats are supported when ImageMagick version check fails
            $commonFormats = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'heic', 'heif', 'jxl'];
            return in_array(strtolower($format), $commonFormats, true);
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

        // Additional safety check: skip known non-image file types
        $nonImageExtensions = ['css', 'js', 'json', 'xml', 'html', 'htm', 'txt', 'md', 'manifest', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf'];
        if (in_array($ext, $nonImageExtensions)) {
            return $filename;
        }

        // Skip favicons, app icons, and 404 images (they should stay PNG for compatibility or are not used)
        $filenameLower = strtolower($filename);
        $skipPatterns = [
            'favicon-16x16', 'favicon-32x32', 'apple-touch-icon',
            'android-chrome-192x192', 'android-chrome-512x512',
            '404.png', '404_text.png',
        ];
        foreach ($skipPatterns as $pattern) {
            if (str_contains($filenameLower, $pattern)) {
                return $filename;
            }
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

        // Check if file already exists in target format (may have been converted previously)
        $pathInfo = pathinfo($filename);
        $targetFile = $pathInfo['filename'] . '.' . $targetFormat;
        $targetPath = "{$mediaDir}/{$targetFile}";

        // If target file already exists, return it (file was already converted)
        if (file_exists($targetPath) && $targetPath !== $oldPath) {
            $this->skippedCount++;
            return $targetFile;
        }

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

        // Delete old file ONLY if conversion was successful AND paths differ
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
        if (isset($data['hero']) && is_array($data['hero']) && isset($data['hero']['images']) && is_array($data['hero']['images'])) {
            foreach ($data['hero']['images'] as $index => $imageFile) {
                if (!is_string($imageFile)) {
                    continue;
                }
                $newFile = $this->convertImageFile(null, $imageFile, $targetFormat, $dryRun, '_media');
                if ($newFile && $newFile !== $imageFile) {
                    $data['hero']['images'][$index] = $newFile;
                    $modified = true;
                }
            }
        }

        // Update about image
        if (isset($data['about']['image']) && is_string($data['about']['image'])) {
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
                if (!is_array($sponsor) || !isset($sponsor['logo']) || !is_string($sponsor['logo'])) {
                    continue;
                }
                $oldLogo = $sponsor['logo'];
                $ext = strtolower(pathinfo($oldLogo, PATHINFO_EXTENSION));

                // Skip SVG files
                if ($ext === 'svg') {
                    continue;
                }

                $newLogo = $this->convertImageFile(null, $oldLogo, $targetFormat, $dryRun, '_media');
                if ($newLogo && $newLogo !== $oldLogo && is_array($data['sponsors'] ?? null) && is_array($data['sponsors']['items'] ?? null) && is_array($data['sponsors']['items'][$index] ?? null)) {
                    $data['sponsors']['items'][$index]['logo'] = $newLogo;
                    $modified = true;
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

    protected function processAllYamlFiles(string $targetFormat, bool $dryRun): void
    {
        // Find all YAML files in _data/ directory (excluding games subdirectory which is already processed)
        $yamlFiles1 = glob('_data/*.yaml') ?: [];
        $yamlFiles2 = glob('_data/*.yml') ?: [];
        $yamlFiles = array_merge($yamlFiles1, $yamlFiles2);

        foreach ($yamlFiles as $yamlFile) {
            // Skip games files (already processed)
            if (strpos($yamlFile, '_data/games/') !== false) {
                continue;
            }

            $this->info("  Processing {$yamlFile}...");
            $this->processYamlFile($yamlFile, $targetFormat, $dryRun);
        }
    }

    protected function processYamlFile(string $yamlFile, string $targetFormat, bool $dryRun): void
    {
        $data = Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            return;
        }

        $modified = false;

        // Recursively process all values in the YAML structure
        $data = $this->processYamlData($data, $targetFormat, $dryRun, $yamlFile, $modified);

        // Save updated YAML if modified
        if ($modified && !$dryRun) {
            $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            file_put_contents($yamlFile, $yamlContent);
            $this->info("    ✓ Updated {$yamlFile}");
            $this->updatedYamlFiles[] = $yamlFile;
        } elseif ($modified && $dryRun) {
            $this->info("    [DRY RUN] Would update {$yamlFile}");
        }
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $data
     * @return array<string, mixed>|array<int, mixed>
     */
    protected function processYamlData(array $data, string $targetFormat, bool $dryRun, string $yamlFile, bool &$modified): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->processYamlData($value, $targetFormat, $dryRun, $yamlFile, $modified);
            } elseif (is_string($value)) {
                // Check if this looks like an image filename
                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'jxl', 'bmp', 'tiff', 'tif'];

                if (in_array($ext, $imageExtensions, true)) {
                    // Try to convert (for root _media files, year is null)
                    $newValue = $this->convertImageFile(null, $value, $targetFormat, $dryRun, '_media');
                    if ($newValue && $newValue !== $value) {
                        $data[$key] = $newValue;
                        $modified = true;
                    }
                }
            }
        }

        return $data;
    }

    protected function processBladeTemplates(string $targetFormat, bool $dryRun): void
    {
        $bladeFiles1 = glob('_pages/**/*.blade.php') ?: [];
        $bladeFiles2 = glob('resources/views/**/*.blade.php') ?: [];
        $bladeFiles = array_merge($bladeFiles1, $bladeFiles2);

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'jxl', 'bmp', 'tiff', 'tif'];

        // List of known image files in _media root that should be converted
        // (exclude favicons and icons that should stay PNG)
        $knownRootImages = [
            'gamejam_about', 'gamejam_footer', 'gamejam_header', 'gamejam_index_1',
            'gamejam_index_2', 'gamejam_index_3', 'gamejam_video', 'video_bg',
            'sponsor_freistaedter', 'gamejam_logo_fhooe', 'gamejam_logo_pie',
            '404', '404_text',
        ];

        foreach ($bladeFiles as $bladeFile) {
            $content = file_get_contents($bladeFile);
            if ($content === false) {
                continue;
            }
            $originalContent = $content;
            $modified = false;

            // Find all image references in the file
            // Pattern: matches image filenames with extensions in quotes or as strings
            // Also matches URLs like /media/filename.jpg
            $pattern = '/(["\']?)(\/media\/)?([a-zA-Z0-9_-]+\.(?:' . implode('|', $imageExtensions) . '))(\1|["\'])/i';

            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                if (count($match) < 5) {
                    continue;
                }
                // preg_match_all returns fixed structure
                $quote1 = is_string($match[1] ?? null) ? $match[1] : '';
                $mediaPath = is_string($match[2] ?? null) ? $match[2] : '';
                $imageFile = is_string($match[3] ?? null) ? $match[3] : '';
                $quote2 = is_string($match[4] ?? null) ? $match[4] : '';

                if ($imageFile === '') {
                    continue;
                }

                $ext = strtolower(pathinfo($imageFile, PATHINFO_EXTENSION));

                // Skip SVG and ICO
                if ($ext === 'svg' || $ext === 'ico') {
                    continue;
                }

                // Skip favicons and small icons (they should stay PNG)
                if (preg_match('/^(favicon|apple-touch-icon|android-chrome)/i', $imageFile)) {
                    continue;
                }

                // Check if file exists in _media root OR if it's already converted
                $pathInfo = pathinfo($imageFile);
                $targetFile = $pathInfo['filename'] . '.' . $targetFormat;

                // Build the original match string for replacement
                $originalMatch = $match[0] ?? '';

                if (file_exists("_media/{$imageFile}")) {
                    $newFile = $this->convertImageFile(null, $imageFile, $targetFormat, $dryRun, '_media');

                    if ($newFile && $newFile !== $imageFile) {
                        // Replace with same structure
                        $newMatch = $quote1 . $mediaPath . $newFile . $quote2;
                        $content = str_replace($originalMatch, $newMatch, $content);
                        $modified = true;
                    }
                } elseif (file_exists("_media/{$targetFile}") && $targetFile !== $imageFile) {
                    // File already converted, just update reference
                    $newMatch = $quote1 . $mediaPath . $targetFile . $quote2;
                    $content = str_replace($originalMatch, $newMatch, $content);
                    $modified = true;
                }
            }

            // Save if modified
            if ($modified && !$dryRun) {
                file_put_contents($bladeFile, $content);
                $this->info("  ✓ Updated {$bladeFile}");
            } elseif ($modified && $dryRun) {
                $this->info("  [DRY RUN] Would update {$bladeFile}");
            }
        }
    }
}
