<?php

declare(strict_types=1);

namespace App\Commands;

use function array_filter;
use function exec;
use function file_exists;
use function file_put_contents;
use function getimagesize;
use function glob;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function is_array;
use function is_dir;
use function mkdir;
use function pathinfo;
use function preg_match;

use Symfony\Component\Yaml\Yaml;

/**
 * Command to add a new sponsor to the homepage.
 *
 * This command:
 * - Interactively asks for sponsor name and URL
 * - Searches for graphics in _input/sponsor
 * - Asks which graphic to use if multiple are found
 * - Resizes and converts pixel images to WebP with ImageMagick (max height 64)
 * - Copies SVG files to _media (preserved as vector)
 * - Updates homepage.yaml sponsors section
 * - Copies the logo to _media
 */
class AddSponsorCommand extends Command
{
    protected $signature = 'gamejam:add-sponsor';

    protected $description = 'Add a new sponsor with logo processing';

    protected string $inputDir;

    protected string $mediaDir;

    protected string $homepageYaml;

    public function handle(): int
    {
        $this->info('Adding a new sponsor...');
        $this->newLine();

        $this->inputDir = base_path('_input/sponsor');
        $this->mediaDir = base_path('_media');
        $this->homepageYaml = base_path('_data/homepage.yaml');

        if (!file_exists($this->homepageYaml)) {
            $this->error('homepage.yaml not found.');
            return 1;
        }

        $name = $this->ask('Sponsor name');
        if (!is_string($name) || trim($name) === '') {
            $this->error('Sponsor name is required.');
            return 1;
        }
        $name = trim($name);

        $url = $this->ask('Sponsor URL', 'https://');
        if (!is_string($url) || trim($url) === '' || $url === 'https://') {
            $this->error('Sponsor URL is required.');
            return 1;
        }
        $url = trim($url);

        // Ensure URL has protocol
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $graphics = $this->findGraphics();
        if (empty($graphics)) {
            $this->error("No graphics found in {$this->inputDir}");
            $this->info('Please place the sponsor logo (jpg, png, webp, svg) in _input/sponsor/');
            return 1;
        }

        $selectedPath = $this->selectGraphic($graphics);
        if ($selectedPath === null) {
            $this->info('Cancelled.');
            return 0;
        }

        $slug = Str::slug($name);
        $ext = strtolower(pathinfo($selectedPath, PATHINFO_EXTENSION));
        $isSvg = $ext === 'svg';

        if ($isSvg) {
            $outputFilename = "sponsor_{$slug}.svg";
        } else {
            $outputFilename = "sponsor_{$slug}.webp";
        }

        $outputPath = "{$this->mediaDir}/{$outputFilename}";

        if (!$this->processLogo($selectedPath, $outputPath, $isSvg)) {
            return 1;
        }

        $width = 200;
        $height = 64;
        if (file_exists($outputPath)) {
            $dims = $this->getImageDimensions($outputPath);
            if ($dims !== null) {
                $width = $dims[0];
                $height = $dims[1];
            }
        }

        $sponsorEntry = [
            'name' => $name,
            'url' => $url,
            'logo' => $outputFilename,
            'width' => $width,
            'height' => $height,
        ];

        if (!$this->addToHomepage($sponsorEntry)) {
            return 1;
        }

        $this->newLine();
        $this->info("Successfully added sponsor '{$name}'!");
        $this->info("  - Logo: {$outputFilename} → _media/");
        $this->info("  - Entry added to _data/homepage.yaml");

        return 0;
    }

    /**
     * @return array<string>
     */
    protected function findGraphics(): array
    {
        if (!is_dir($this->inputDir)) {
            mkdir($this->inputDir, 0755, true);
            return [];
        }

        $files = glob($this->inputDir . '/*.{jpg,jpeg,png,webp,svg,JPG,JPEG,PNG,WEBP,SVG}', GLOB_BRACE) ?: [];
        $files = array_filter($files, 'is_file');
        sort($files);

        return $files;
    }

    /**
     * @param array<string> $graphics
     */
    protected function selectGraphic(array $graphics): ?string
    {
        if (count($graphics) === 1) {
            $this->info('Using: ' . basename($graphics[0]));
            return $graphics[0];
        }

        $choices = array_map('basename', $graphics);
        $selected = $this->choice('Which graphic should be used?', $choices);

        foreach ($graphics as $path) {
            if (basename($path) === $selected) {
                return $path;
            }
        }

        return null;
    }

    protected function processLogo(string $inputPath, string $outputPath, bool $isSvg): bool
    {
        if ($isSvg) {
            if (!copy($inputPath, $outputPath)) {
                $this->error("Failed to copy SVG: {$inputPath}");
                return false;
            }
            $this->info('SVG copied to _media (vector preserved).');
            return true;
        }

        exec('magick -version', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->error('ImageMagick is not installed or not available in PATH.');
            return false;
        }

        $cmd = 'magick';
        $cmd .= ' "' . str_replace('"', '\\"', $inputPath) . '"';
        $cmd .= ' -resize "x64>"';
        $cmd .= ' -quality 90';
        $cmd .= ' "' . str_replace('"', '\\"', $outputPath) . '"';

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            $this->error("Failed to process image: {$inputPath}");
            return false;
        }

        $this->info('Image resized and converted to WebP.');

        return true;
    }

    /**
     * @return array{0: int, 1: int}|null [width, height]
     */
    protected function getImageDimensions(string $path): ?array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $output = [];
            exec('magick identify -format "%w %h" "' . str_replace('"', '\\"', $path) . '"', $output);
            if (!empty($output) && preg_match('/^(\d+)\s+(\d+)$/', trim($output[0]), $m)) {
                return [(int) $m[1], (int) $m[2]];
            }
            return null;
        }

        $info = @getimagesize($path);
        if ($info !== false) {
            return [$info[0], $info[1]];
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $existingItems
     */
    protected function askInsertPosition(array $existingItems): int
    {
        if (empty($existingItems)) {
            return 0;
        }

        $this->info('Current sponsors:');
        foreach ($existingItems as $index => $item) {
            if (is_array($item) && isset($item['name'])) {
                $name = is_string($item['name']) ? $item['name'] : 'Unknown';
                $this->line('  ' . ($index + 1) . '. ' . $name);
            }
        }

        $this->newLine();
        $choices = ['At the beginning'];
        foreach ($existingItems as $item) {
            if (is_array($item) && isset($item['name'])) {
                $name = is_string($item['name']) ? $item['name'] : 'Unknown';
                $choices[] = 'After ' . $name;
            }
        }

        $selected = $this->choice('Where should the new sponsor be inserted?', $choices);
        if (is_string($selected)) {
            $index = array_search($selected, $choices, true);
            if ($index !== false) {
                return $index;
            }
        }

        return count($existingItems);
    }

    /**
     * @param array{name: string, url: string, logo: string, width: int, height: int} $sponsor
     */
    protected function addToHomepage(array $sponsor): bool
    {
        $data = Yaml::parseFile($this->homepageYaml) ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data['sponsors']) || !is_array($data['sponsors'])) {
            $data['sponsors'] = [
                'title' => 'Our Sponsors',
                'description' => "Hagenberg Game Jam wouldn't be possible without the support of our partners.",
                'items' => [],
            ];
        }

        if (!isset($data['sponsors']['items']) || !is_array($data['sponsors']['items'])) {
            $data['sponsors']['items'] = [];
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['sponsors']['items'];
        $position = $this->askInsertPosition($items);

        array_splice($items, $position, 0, [$sponsor]);
        $data['sponsors']['items'] = $items;

        $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($this->homepageYaml, $yamlContent);

        return true;
    }
}
