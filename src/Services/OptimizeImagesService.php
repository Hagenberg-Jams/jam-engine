<?php

declare(strict_types=1);

namespace App\Services;

use Hyde\Hyde;
use Symfony\Component\Yaml\Yaml;

use function exec;
use function file_exists;
use function getimagesize;
use function pathinfo;

/**
 * Optimizes images at build time: generates responsive variants for gallery/hero images.
 *
 * Creates 400w and 800w WebP variants for srcset. Source files are never modified.
 */
class OptimizeImagesService
{
    private const QUALITY = 80;
    private const RESPONSIVE_WIDTHS = [400, 800];

    /** @var array<string> */
    private array $optimized = [];

    /** @var array<string> */
    private array $errors = [];

    public function run(): bool
    {
        $images = $this->getImagesToOptimize();
        if ($images === []) {
            return true;
        }

        if (!$this->isImageMagickAvailable()) {
            return true; // Skip silently if ImageMagick not available
        }

        foreach ($images as $filename) {
            $this->optimizeImage($filename);
        }

        return $this->errors === [];
    }

    /** @return array<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string> */
    public function getOptimized(): array
    {
        return $this->optimized;
    }

    /** @return array<string> */
    private function getImagesToOptimize(): array
    {
        $images = [];
        $mediaDir = Hyde::path(Hyde::getMediaDirectory());

        // Gallery images from homepage
        $homepagePath = Hyde::path('_data/homepage.yaml');
        if (file_exists($homepagePath)) {
            $data = Yaml::parseFile($homepagePath) ?? [];
            if (isset($data['about']['gallery']) && is_array($data['about']['gallery'])) {
                foreach ($data['about']['gallery'] as $item) {
                    $file = is_array($item) ? ($item['image'] ?? '') : $item;
                    if (is_string($file) && $file !== '' && $this->isOptimizableFormat($file)) {
                        $images[] = ltrim($file, '/');
                    }
                }
            }
            // Hero images
            if (isset($data['hero']['images']) && is_array($data['hero']['images'])) {
                foreach ($data['hero']['images'] as $file) {
                    if (is_string($file) && $file !== '' && $this->isOptimizableFormat($file)) {
                        $images[] = ltrim($file, '/');
                    }
                }
            }
        }

        return array_unique(array_filter($images, fn (string $f) => file_exists("{$mediaDir}/{$f}")));
    }

    private function isOptimizableFormat(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif'], true);
    }

    private function optimizeImage(string $filename): void
    {
        $mediaDir = Hyde::path(Hyde::getMediaDirectory());
        $path = "{$mediaDir}/{$filename}";

        if (!file_exists($path)) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        $width = $info[0];
        $height = $info[1];
        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['filename'];

        // Only create responsive variants; never modify source files to avoid corruption
        foreach (self::RESPONSIVE_WIDTHS as $targetWidth) {
            if ($width <= $targetWidth) {
                continue;
            }
            $variantName = "{$baseName}-{$targetWidth}w.webp";
            $variantPath = "{$mediaDir}/{$variantName}";
            if ($this->resizeImage($path, $variantPath, $targetWidth)) {
                $this->optimized[] = $variantName;
            }
        }
    }

    private function resizeImage(string $inputPath, string $outputPath, int $maxWidth): bool
    {
        $inputPath = realpath($inputPath) ?: $inputPath;
        $quality = self::QUALITY;
        // Use -thumbnail Nx (not -resize Nx0) - Nx0 produces 1x1 corrupt output on Windows
        $cmd = sprintf(
            'magick "%s" -thumbnail %dx -quality %d "%s"',
            str_replace('"', '\\"', $inputPath),
            $maxWidth,
            $quality,
            str_replace('"', '\\"', $outputPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            $this->errors[] = "Failed to resize: {$inputPath}";
            return false;
        }

        return true;
    }

    private function isImageMagickAvailable(): bool
    {
        exec('magick -version', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if responsive variants exist for a base image.
     * Variants are always .webp (e.g. gamejam_about_1-400w.webp).
     *
     * @return array{400?: string, 800?: string}
     */
    public static function getResponsiveVariants(string $baseFilename): array
    {
        $pathInfo = pathinfo($baseFilename);
        $baseName = $pathInfo['filename'];
        $mediaDir = Hyde::path(Hyde::getMediaDirectory());

        $variants = [];
        foreach (self::RESPONSIVE_WIDTHS as $w) {
            $variant = "{$baseName}-{$w}w.webp";
            if (file_exists("{$mediaDir}/{$variant}")) {
                $variants[$w] = $variant;
            }
        }

        return $variants;
    }
}
