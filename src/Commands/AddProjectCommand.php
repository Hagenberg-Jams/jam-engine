<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use function array_filter;
use function array_map;
use function exec;
use function file_exists;
use function file_put_contents;
use function glob;
use function hash_file;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function is_array;
use function is_dir;
use function mkdir;
use function pathinfo;
use function preg_match;
use function scandir;
use function sort;

use Symfony\Component\Yaml\Yaml;

use function unlink;

/**
 * Command to add a new project to a jam year.
 *
 * This command:
 * - Interactively asks for all project data
 * - Processes images from _input/header/ and _input/screenshots/
 * - Processes download files from _input/download/
 * - Adds entry to projects YAML file
 * - Cleans up input directories on success
 */
class AddProjectCommand extends Command
{
    /** @var array<int, string> */
    protected $aliases = ['gamejam:add-game'];

    protected $signature = 'jam:add-project {--year= : Year of the jam}';

    protected $description = 'Add a new project to a jam year with image processing';

    protected string $inputHeaderDir;
    protected string $inputScreenshotsDir;
    protected string $inputDownloadDir;
    protected ?int $year = null;
    protected string $projectSlug = '';
    /** @var array<string> */
    protected array $processedFiles = [];

    public function handle(): int
    {
        $this->info('Adding a new project...');
        $this->newLine();

        // Setup input directories
        $this->inputHeaderDir = base_path('_input/header');
        $this->inputScreenshotsDir = base_path('_input/screenshots');
        $this->inputDownloadDir = base_path('_input/download');

        // Get year
        $this->year = $this->getYear();
        if ($this->year === null) {
            return 1;
        }

        // Validate YAML file exists
        $yamlFile = base_path("_data/projects/{$this->year}.yaml");
        if (!file_exists($yamlFile)) {
            if (!$this->confirm("Projects YAML file for year {$this->year} does not exist. Create it?", true)) {
                $this->error('Cannot proceed without YAML file.');
                return 1;
            }
            $this->createEmptyYamlFile($yamlFile);
        }

        // Validate images exist
        if (!$this->validateImages()) {
            return 1;
        }

        // Collect project data
        $gameData = $this->collectGameData();
        if ($gameData === null) {
            return 1;
        }

        $this->projectSlug = Str::slug($gameData['name']);

        // Check for duplicate project name
        if ($this->gameExists($gameData['name'])) {
            if (!$this->confirm("Project '{$gameData['name']}' already exists. Overwrite?", false)) {
                $this->info('Cancelled.');
                return 0;
            }
            $this->removeExistingGame($gameData['name']);
        }

        // Process images
        if (!$this->processImages($gameData)) {
            return 1;
        }

        // Process downloads
        if ($this->hasSection('downloads')) {
            $this->processDownloads($gameData);
        }

        // Add to YAML
        if (!$this->addToYaml($gameData)) {
            return 1;
        }

        // Clean up input directories on success
        $this->cleanupInputDirectories();

        $this->newLine();
        $projectName = is_string($gameData['name'] ?? null) ? $gameData['name'] : 'Unknown';
        $this->info("Successfully added project '{$projectName}' to {$this->year}!");
        $this->info("  - Images processed and moved to _media/{$this->year}/");
        $this->info("  - Project entry added to _data/projects/{$this->year}.yaml");

        return 0;
    }

    protected function getYear(): ?int
    {
        if ($this->option('year')) {
            $year = (int) $this->option('year');
            $yamlFile = base_path("_data/projects/{$year}.yaml");
            if (!file_exists($yamlFile)) {
                $this->warn("YAML file for year {$year} does not exist.");
            }
            return $year;
        }

        // Discover available years
        $files = glob(base_path('_data/jams/*.yaml')) ?: [];
        $years = [];

        foreach ($files as $file) {
            if (preg_match('/(\d{4})\.yaml$/', $file, $matches)) {
                $years[] = (int) $matches[1];
            }
        }

        if (empty($years)) {
            $this->error('No jam years found. Create one first with: php hyde jam:create-jam');
            return null;
        }

        sort($years);
        $yearChoices = array_map(fn($y) => (string) $y, $years);

        $selected = $this->choice('Select year', $yearChoices);

        return (int) $selected;
    }

    protected function validateImages(): bool
    {
        $headerFiles = $this->getImageFiles($this->inputHeaderDir);
        $screenshotFiles = $this->getImageFiles($this->inputScreenshotsDir);

        if ($this->hasSection('video') && $headerFiles === [] && $screenshotFiles === []) {
            $this->error('Please place at least a header image in _input/header/, it is used as the preview of the video.');
            return false;
        }

        if (empty($headerFiles)) {
            $this->error("No header image found in {$this->inputHeaderDir}");
            $this->error('Please place the header image in _input/header/');
            return false;
        }

        if (count($headerFiles) > 1) {
            $this->warn("Multiple header images found. Using first: " . basename($headerFiles[0]));
        }

        if (empty($screenshotFiles) && !$this->hasSection('gallery')) {
            $this->info("Found header image: " . basename($headerFiles[0]));
            return true;
        }

        if (empty($screenshotFiles) && !$this->hasSection('video')) {
            $this->error("No screenshot images found in {$this->inputScreenshotsDir}");
            $this->error('Please place screenshot images in _input/screenshots/');
            return false;
        }

        $this->info("Found header image: " . basename($headerFiles[0]));
        $this->info("Found " . count($screenshotFiles) . " screenshot(s)");

        return true;
    }

    /**
     * @return array<string>
     */
    protected function getImageFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        $files = array_filter($files, 'is_file');
        // Sort alphabetically to ensure consistent order
        sort($files);
        return $files;
    }

    /**
     * @return array{name: string, players: string|null, controls: array<string>|null, controlsText: string|null, videos: array<int, array{youtube: string}>, description: string, teamName: string, teamMembers: array<string>, winner: string}|null
     */
    protected function collectGameData(): ?array
    {
        $nameInput = $this->ask('Project name');
        $name = is_string($nameInput) ? $nameInput : '';
        if (empty($name)) {
            $this->error('Project name is required');
            return null;
        }

        $players = null;
        $controls = null;
        $controlsText = null;

        if ($this->hasSection('info')) {
            $playersInput = $this->ask('Number of players (e.g., "1", "2-4", "3-8")', '1');
            $players = $this->parsePlayersInput(is_string($playersInput) ? $playersInput : '1');
            $controls = $this->askForControls();
        }

        if ($this->hasSection('controls')) {
            $controlsText = $this->askForControlsText();
        }

        $videos = $this->hasSection('video') ? $this->askForVideos() : [];
        $description = $this->askForDescription();
        $teamNameInput = $this->ask('Team name');
        $teamName = is_string($teamNameInput) ? $teamNameInput : '';
        $teamMembers = $this->askForTeamMembers();
        $winner = $this->askForWinner();

        return [
            'name' => $name,
            'players' => $players,
            'controls' => $controls,
            'controlsText' => $controlsText,
            'videos' => $videos,
            'description' => $description,
            'teamName' => $teamName,
            'teamMembers' => $teamMembers,
            'winner' => $winner,
        ];
    }

    protected function hasSection(string $section): bool
    {
        $sections = config('jam.project.sections', []);

        return is_array($sections) && in_array($section, $sections, true);
    }

    /**
     * @return array<int, array{youtube: string}>
     */
    protected function askForVideos(): array
    {
        $videos = [];

        while (true) {
            $input = $this->ask('YouTube link or ID (leave empty when finished)');
            $id = is_string($input) ? $this->parseYoutubeId($input) : '';

            if ($id === '') {
                return $videos;
            }

            $videos[] = ['youtube' => $id];
        }
    }

    protected function parseYoutubeId(string $input): string
    {
        $input = trim($input);

        if (preg_match('#(?:youtu\.be/|[?&]v=|/embed/|/shorts/)([\w-]{6,20})#', $input, $matches) === 1) {
            return $matches[1];
        }

        return $input;
    }

    /**
     * @return array<string>
     */
    protected function askForControls(): array
    {
        $options = ['keyboard', 'mouse', 'gamepad', 'touch'];
        $this->info('Controls (select all that apply):');
        foreach ($options as $i => $option) {
            $this->line("  " . ($i + 1) . ". {$option}");
        }

        $input = $this->ask('Enter numbers separated by commas (e.g., 1,2)', '1');
        $inputString = is_string($input) ? $input : '1';
        $selectedNumbers = array_map('trim', explode(',', $inputString));
        $selected = [];

        foreach ($selectedNumbers as $num) {
            $index = (int) $num - 1;
            if ($index >= 0 && $index < count($options)) {
                $selected[] = $options[$index];
            }
        }

        return empty($selected) ? ['keyboard'] : $selected;
    }

    protected function askForControlsText(): ?string
    {
        $this->info('Controls text (optional - detailed control instructions):');
        $this->info('Enter the controls text (multiline). Type "---END---" on a new line when finished, or press Enter to skip:');
        $this->newLine();

        $lines = [];
        $firstLine = $this->ask('', '');
        if (empty($firstLine) || $firstLine === '---END---') {
            return null;
        }
        $lines[] = is_scalar($firstLine) ? (string) $firstLine : '';

        while (true) {
            $line = $this->ask('', '');
            if ($line === '---END---') {
                break;
            }
            if (!empty($line)) {
                $lines[] = is_scalar($line) ? (string) $line : '';
            }
        }

        $text = implode("\n", $lines);
        return empty(trim($text)) ? null : $text;
    }

    protected function askForDescription(): string
    {
        $this->info('Enter project description (multiline). Type "---END---" on a new line when finished:');
        $this->newLine();

        $lines = [];
        while (true) {
            $line = $this->ask('', '');
            if ($line === '---END---') {
                break;
            }
            $lines[] = is_scalar($line) ? (string) $line : '';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string>
     */
    protected function askForTeamMembers(): array
    {
        $this->info('Enter team members (comma-separated, semicolon-separated, markdown list format, or one per line). Type "---END---" when finished:');
        $this->info('Note: Markdown list format "- Name" is supported (the "- " prefix will be automatically removed)');
        $this->newLine();

        $input = '';
        while (true) {
            $line = $this->ask('', '');
            if (!is_string($line)) {
                continue;
            }
            if ($line === '---END---') {
                break;
            }
            if (!empty($line)) {
                $input .= ($input ? "\n" : '') . $line;
            }
        }

        // Parse members intelligently
        $members = $this->parseTeamMembers($input);

        // Show preview and allow correction
        $this->newLine();
        $this->info('Detected ' . count($members) . ' team member(s):');
        foreach ($members as $i => $member) {
            $this->line("  " . ($i + 1) . ". {$member}");
        }

        if (!$this->confirm('Is this correct?', true)) {
            $this->info('Please re-enter team members:');
            return $this->askForTeamMembers();
        }

        return $members;
    }

    protected function parsePlayersInput(string $input): string
    {
        $input = trim($input);

        // Check if it's a range (e.g., "3-8", "2-4")
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $input, $matches)) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];
            if ($min > 0 && $max >= $min) {
                return "{$min}-{$max}";
            }
        }

        // Check if it's a single number
        $single = (int) $input;
        if ($single > 0) {
            return (string) $single;
        }

        // Default to 1 if invalid
        return '1';
    }

    /**
     * @return array<string>
     */
    protected function parseTeamMembers(string $input): array
    {
        // Try different separators
        $separators = ["\n", ';', ','];
        $members = [];

        foreach ($separators as $sep) {
            $parts = explode($sep, $input);
            if (count($parts) > 1) {
                $members = array_map('trim', $parts);
                break;
            }
        }

        // If no separator found, treat as single member
        if (empty($members)) {
            $trimmed = trim($input);
            if (!empty($trimmed)) {
                $members = [$trimmed];
            }
        }

        // Clean up: remove empty entries, normalize whitespace, remove markdown list prefixes
        $members = array_filter(array_map(function (string $m): string {
            $m = trim($m);
            // Remove markdown list prefixes: "- ", "* ", "+ " (for unordered lists)
            $m = preg_replace('/^[-*+]\s+/', '', $m) ?? $m;
            return preg_replace('/\s+/', ' ', $m) ?? $m;
        }, $members));

        return array_values($members);
    }

    protected function askForWinner(): string
    {
        $isWinner = $this->confirm('Is this a winning project?', false);

        if (!$isWinner) {
            return 'no';
        }

        $awards = config('jam.awards', []);
        $available = is_array($awards) ? implode(', ', array_keys($awards)) : '';
        $award = $this->ask("Awards, comma-separated ({$available})", '');

        return is_string($award) && $award !== '' ? $award : 'no';
    }

    /**
     * @param array<string, mixed> $gameData
     */
    protected function processImages(array &$gameData): bool
    {
        $this->info('Processing images...');

        $mediaDir = base_path("_media/{$this->year}");
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        // Process header image (1920x520)
        $headerFiles = $this->getImageFiles($this->inputHeaderDir);
        $headerFile = $headerFiles[0];
        $headerOutput = "{$this->projectSlug}_header.webp";
        $headerPath = "{$mediaDir}/{$headerOutput}";

        if (!$this->processHeaderImage($headerFile, $headerPath)) {
            return false;
        }

        $gameData['headerimage'] = $headerOutput;
        $this->processedFiles[] = $headerFile;

        // Process screenshots (already sorted alphabetically by getImageFiles)
        $screenshotFiles = $this->getImageFiles($this->inputScreenshotsDir);

        $images = [];
        foreach ($screenshotFiles as $index => $screenshotFile) {
            $imageNum = $index + 1;
            $fullOutput = "{$this->projectSlug}_image{$imageNum}_full.webp";
            $thumbOutput = "{$this->projectSlug}_image{$imageNum}_thumb.webp";
            $fullPath = "{$mediaDir}/{$fullOutput}";
            $thumbPath = "{$mediaDir}/{$thumbOutput}";

            if (!$this->processImage($screenshotFile, $fullPath, false)) {
                return false;
            }

            // Create thumbnail from full image
            if (!$this->createThumbnail($fullPath, $thumbPath)) {
                return false;
            }

            $images[] = [
                'file' => $fullOutput,
                'thumb' => $thumbOutput,
            ];

            $this->processedFiles[] = $screenshotFile;
        }

        $gameData['images'] = $images;

        $this->info("Processed " . count($images) . " screenshot(s)");

        return true;
    }

    protected function processHeaderImage(string $inputPath, string $outputPath): bool
    {
        // Get image dimensions
        $imageInfo = @getimagesize($inputPath);
        if ($imageInfo === false) {
            $this->error("Failed to read image: {$inputPath}");
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $targetWidth = 1920;
        $targetHeight = 520;
        $targetAspectRatio = $targetWidth / $targetHeight; // 1920/520 = 3.6923...
        $currentAspectRatio = $width / $height;

        // Check if image is already the correct size
        if ($width === $targetWidth && $height === $targetHeight) {
            // Image is already correct size, just convert to WebP
            $cmd = 'magick';
            $cmd .= ' "' . str_replace('"', '\\"', $inputPath) . '"';
            $cmd .= ' -quality 90';
            $cmd .= ' -format webp';
            $cmd .= ' "' . str_replace('"', '\\"', $outputPath) . '"';

            exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($outputPath)) {
                return true;
            }
        }

        // Calculate crop geometry if aspect ratio differs
        $cropGeometry = '';
        if (abs($currentAspectRatio - $targetAspectRatio) > 0.01) {
            // Aspect ratios differ, need to crop
            if ($currentAspectRatio > $targetAspectRatio) {
                // Image is wider than target, crop horizontally
                $newWidth = (int) ($height * $targetAspectRatio);
                $xOffset = (int) (($width - $newWidth) / 2);
                $cropGeometry = "{$newWidth}x{$height}+{$xOffset}+0";
            } else {
                // Image is taller than target, crop vertically
                $newHeight = (int) ($width / $targetAspectRatio);
                $yOffset = (int) (($height - $newHeight) / 2);
                $cropGeometry = "{$width}x{$newHeight}+0+{$yOffset}";
            }
        }

        // Build ImageMagick command
        $cmd = 'magick';
        $cmd .= ' "' . str_replace('"', '\\"', $inputPath) . '"';

        if (!empty($cropGeometry)) {
            $cmd .= " -crop {$cropGeometry}";
        }

        $cmd .= " -resize {$targetWidth}x{$targetHeight}";
        $cmd .= ' -quality 90';
        $cmd .= ' -format webp';
        $cmd .= ' "' . str_replace('"', '\\"', $outputPath) . '"';

        // Execute ImageMagick
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            $this->error("Failed to process header image: {$inputPath}");
            $this->error("ImageMagick command: {$cmd}");
            if (!empty($output)) {
                $this->error("Output: " . implode("\n", $output));
            }
            return false;
        }

        return true;
    }

    protected function processImage(string $inputPath, string $outputPath, bool $isHeader): bool
    {
        // Get image dimensions
        $imageInfo = @getimagesize($inputPath);
        if ($imageInfo === false) {
            $this->error("Failed to read image: {$inputPath}");
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $targetAspectRatio = 16 / 9;
        $currentAspectRatio = $width / $height;

        // Calculate crop geometry if needed
        $cropGeometry = '';
        if ($currentAspectRatio > $targetAspectRatio) {
            // Image is wider than 16:9, crop horizontally
            $newWidth = (int) ($height * $targetAspectRatio);
            $xOffset = (int) (($width - $newWidth) / 2);
            $cropGeometry = "{$newWidth}x{$height}+{$xOffset}+0";
        } elseif ($currentAspectRatio < $targetAspectRatio) {
            // Image is taller than 16:9, crop vertically
            $newHeight = (int) ($width / $targetAspectRatio);
            $yOffset = (int) (($height - $newHeight) / 2);
            $cropGeometry = "{$width}x{$newHeight}+0+{$yOffset}";
        }

        // Build ImageMagick command
        $cmd = 'magick';
        $cmd .= ' "' . str_replace('"', '\\"', $inputPath) . '"';

        if (!empty($cropGeometry)) {
            $cmd .= " -crop {$cropGeometry}";
        }

        $cmd .= ' -resize 1920x1080';
        $cmd .= ' -quality 90';
        $cmd .= ' -format webp';
        $cmd .= ' "' . str_replace('"', '\\"', $outputPath) . '"';

        // Execute ImageMagick
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            $this->error("Failed to process image: {$inputPath}");
            $this->error("ImageMagick command: {$cmd}");
            if (!empty($output)) {
                $this->error("Output: " . implode("\n", $output));
            }
            return false;
        }

        return true;
    }

    protected function createThumbnail(string $fullPath, string $thumbPath): bool
    {
        $cmd = 'magick';
        $cmd .= ' "' . str_replace('"', '\\"', $fullPath) . '"';
        $cmd .= ' -thumbnail 400x225';
        $cmd .= ' -quality 90';
        $cmd .= ' -format webp';
        $cmd .= ' "' . str_replace('"', '\\"', $thumbPath) . '"';

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($thumbPath)) {
            $this->error("Failed to create thumbnail: {$thumbPath}");
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $gameData
     */
    protected function processDownloads(array &$gameData): void
    {
        if (!is_dir($this->inputDownloadDir)) {
            return;
        }

        // Find all ZIP files in download directory
        $downloadFiles = glob($this->inputDownloadDir . '/*.{zip,ZIP}', GLOB_BRACE) ?: [];
        if (empty($downloadFiles)) {
            return;
        }

        $this->info('Processing download files...');
        $this->newLine();

        $downloads = [];
        $downloadsDir = base_path("games/{$this->year}");
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        // Platform options for selection
        $platformOptions = [
            '1' => 'Windows',
            '2' => 'Linux',
            '3' => 'macOS',
            '4' => 'Web',
        ];

        foreach ($downloadFiles as $downloadFile) {
            $originalFileName = basename($downloadFile);

            // Ask for platform interactively
            $this->info("File: {$originalFileName}");
            $this->line("Select platform:");
            foreach ($platformOptions as $key => $platform) {
                $this->line("  {$key}. {$platform}");
            }

            $selected = $this->ask('Platform (1-4)', '1');
            $selectedKey = is_string($selected) ? $selected : '1';
            $platform = $platformOptions[$selectedKey] ?? $platformOptions['1'];

            // Generate new filename: {slug}-{Platform}.zip
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $newFileName = "{$this->projectSlug}-{$platform}.{$extension}";
            $targetPath = "{$downloadsDir}/{$newFileName}";

            // Check if target file already exists
            if (file_exists($targetPath)) {
                if (!$this->confirm("File {$newFileName} already exists. Overwrite?", false)) {
                    $this->warn("Skipping {$originalFileName}");
                    continue;
                }
            }

            // Copy file to projects directory with new name
            if (!copy($downloadFile, $targetPath)) {
                $this->warn("Failed to copy download file: {$originalFileName}");
                continue;
            }

            // Calculate checksum
            $checksum = hash_file('sha256', $targetPath);
            if ($checksum === false) {
                $this->warn("Failed to calculate checksum for: {$newFileName}");
                @unlink($targetPath);
                continue;
            }

            $downloads[] = [
                'file' => $newFileName,
                'platform' => $platform,
                'checksum' => $checksum,
            ];

            $this->info("  ✓ Processed: {$originalFileName} → {$newFileName} ({$platform})");
            $this->processedFiles[] = $downloadFile;
            $this->newLine();
        }

        if (!empty($downloads)) {
            $gameData['download'] = $downloads;
            $this->info("Processed " . count($downloads) . " download file(s)");
        }
    }

    protected function gameExists(string $projectName): bool
    {
        $yamlFile = base_path("_data/projects/{$this->year}.yaml");
        $data = Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $entry) {
            if (!is_array($entry) || !isset($entry['project']) || !is_array($entry['project'])) {
                continue;
            }
            if (isset($entry['project']['name']) && is_string($entry['project']['name']) && $entry['project']['name'] === $projectName) {
                return true;
            }
        }

        return false;
    }

    protected function removeExistingGame(string $projectName): void
    {
        $yamlFile = base_path("_data/projects/{$this->year}.yaml");
        $data = Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            return;
        }

        $data = array_filter($data, function (mixed $entry) use ($projectName): bool {
            if (!is_array($entry) || !isset($entry['project']) || !is_array($entry['project'])) {
                return true;
            }
            if (!isset($entry['project']['name']) || !is_string($entry['project']['name'])) {
                return true;
            }
            return $entry['project']['name'] !== $projectName;
        });

        $yamlContent = Yaml::dump(array_values($data), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($yamlFile, $yamlContent);
    }

    /**
     * @param array<string, mixed> $gameData
     */
    protected function addToYaml(array $gameData): bool
    {
        $yamlFile = base_path("_data/projects/{$this->year}.yaml");
        $data = Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        // Build YAML entry
        $entry = [
            'project' => array_filter([
                'name' => $gameData['name'],
                'players' => $gameData['players'] ?? null,
                'controls' => $gameData['controls'] ?? null,
                'controls_text' => $gameData['controlsText'] ?? null,
                'videos' => ($gameData['videos'] ?? []) ?: null,
                'description' => $gameData['description'],
            ], fn(mixed $value): bool => $value !== null),
            'team' => [
                'name' => $gameData['teamName'],
                'members' => $gameData['teamMembers'],
            ],
            'winner' => $gameData['winner'],
            'headerimage' => $gameData['headerimage'],
            'images' => $gameData['images'],
        ];

        if (isset($gameData['download'])) {
            $entry['download'] = $gameData['download'];
        }

        // Add new entry
        $data[] = $entry;

        // Sort all entries alphabetically by project name (case-insensitive)
        usort($data, function (mixed $a, mixed $b): int {
            $nameA = (is_array($a) && isset($a['project']) && is_array($a['project']) && isset($a['project']['name'])) ? (is_string($a['project']['name']) ? $a['project']['name'] : '') : '';
            $nameB = (is_array($b) && isset($b['project']) && is_array($b['project']) && isset($b['project']['name'])) ? (is_string($b['project']['name']) ? $b['project']['name'] : '') : '';
            return strcasecmp($nameA, $nameB);
        });

        // Write YAML with proper formatting
        $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($yamlFile, $yamlContent);

        return true;
    }

    protected function createEmptyYamlFile(string $file): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "# Projects for {$this->year} jam\n";
        $content .= "# Add project entries below using: php hyde jam:add-project\n";
        $content .= "\n";

        file_put_contents($file, $content);
        $this->info("Created empty YAML file: {$file}");
    }

    protected function cleanupInputDirectories(): void
    {
        $this->info('Cleaning up input directories...');

        // Remove processed files
        foreach ($this->processedFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // Remove empty directories (keep directory structure)
        $dirs = [$this->inputHeaderDir, $this->inputScreenshotsDir, $this->inputDownloadDir];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $files = scandir($dir);
                if ($files !== false && count($files) <= 2) { // Only . and ..
                    // Directory is empty, but we keep it
                }
            }
        }

        $this->info('Cleanup complete.');
    }
}
