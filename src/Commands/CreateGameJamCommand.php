<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\GameJamData;

use function file_exists;
use function file_put_contents;
use function mkdir;
use function preg_match;
use function glob;

/**
 * Command to create a new Game Jam year with metadata file and empty games YAML.
 *
 * This command interactively asks for all required data and creates:
 * - `_data/jams/{year}.md` with front matter
 * - `_data/games/games{year}.yaml` (empty or with comment)
 */
class CreateGameJamCommand extends Command
{
    protected $signature = 'gamejam:create-jam';

    protected $description = 'Create a new Game Jam year with metadata files';

    public function handle(): int
    {
        $this->info('Creating a new Game Jam...');
        $this->newLine();

        // Get year
        $year = $this->askForYear();
        if ($year === null) {
            return 1;
        }

        // Check if year already exists
        $jamFile = base_path("_data/jams/{$year}.md");
        if (file_exists($jamFile)) {
            if (!$this->confirm("Jam for year {$year} already exists. Overwrite?", false)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Get all required data
        $title = $this->ask("Title (default: '{$year}')", (string) $year);
        $topic = $this->ask('Topic/Theme of the Game Jam');
        $startDate = $this->askForDate('Start date', 'YYYY-MM-DD');
        $endDate = $this->askForDate('End date', 'YYYY-MM-DD');
        $hours = (int) $this->ask('Duration in hours (e.g., 36, 48)', '36');
        $logo = $this->ask('Logo filename (e.g., gamejam2024.svg)', "gamejam{$year}.svg");

        // Create jam MD file
        $this->createJamFile($year, $title, $topic, $startDate, $endDate, $hours, $logo);

        // Create empty games YAML file
        $yamlFile = base_path("_data/games/games{$year}.yaml");
        if (!file_exists($yamlFile)) {
            $this->createGamesYamlFile($year);
        } else {
            $this->warn("Games YAML file already exists: {$yamlFile}");
        }

        // Update navigation configuration
        $this->updateNavigationConfig($year);

        $this->newLine();
        $this->info("Successfully created Game Jam {$year}!");
        $this->info("  - Jam metadata: _data/jams/{$year}.md");
        $this->info("  - Games data: _data/games/games{$year}.yaml");
        $this->info("  - Navigation updated (latest_jam set to {$year})");

        return 0;
    }

    protected function askForYear(): ?int
    {
        while (true) {
            $input = $this->ask('Year (4 digits, e.g., 2025)');

            if (!is_numeric($input)) {
                $this->error('Year must be numeric');
                continue;
            }

            $year = (int) $input;

            if ($year < 2000 || $year > 2100) {
                $this->error('Year must be between 2000 and 2100');
                continue;
            }

            return $year;
        }
    }

    protected function askForDate(string $label, string $format): string
    {
        while (true) {
            $input = $this->ask("{$label} ({$format})");

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) !== 1) {
                $this->error("Date must be in format {$format}");
                continue;
            }

            return $input;
        }
    }

    protected function createJamFile(int $year, string $title, string $topic, string $startDate, string $endDate, int $hours, string $logo): void
    {
        $content = "---\n";
        $content .= "title: \"{$title}\"\n";
        $content .= "topic: \"{$topic}\"\n";
        $content .= "startdate: {$startDate}\n";
        $content .= "enddate: {$endDate}\n";
        $content .= "hours: {$hours}\n";
        $content .= "data: games{$year}\n";
        $content .= "logo: \"{$logo}\"\n";
        $content .= "---\n";

        $file = base_path("_data/jams/{$year}.md");
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, $content);
        $this->info("Created jam metadata file: {$file}");
    }

    protected function createGamesYamlFile(int $year): void
    {
        $file = base_path("_data/games/games{$year}.yaml");
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "# Games for {$year} Game Jam\n";
        $content .= "# Add game entries below using: php hyde gamejam:add-game\n";
        $content .= "\n";

        file_put_contents($file, $content);
        $this->info("Created empty games YAML file: {$file}");
    }

    protected function updateNavigationConfig(int $year): void
    {
        // Get all available years (including the one we just created)
        $allYears = GameJamData::getAvailableYears();
        
        if (empty($allYears)) {
            $this->warn("No years found, cannot determine latest jam");
            return;
        }

        // Find the highest year
        $highestYear = max($allYears);

        // Only update latest_jam if the new year is the highest
        if ($year < $highestYear) {
            $this->info("Year {$year} is not the latest. Latest year is {$highestYear}. Not updating latest_jam.");
            // Still update Hyde config exclude list
            $this->updateHydeConfig($year);
            return;
        }

        // Update config/gamejam.php - latest_jam
        $configFile = base_path('config/gamejam.php');
        if (!file_exists($configFile)) {
            $this->warn("Config file not found: {$configFile}");
            return;
        }

        $content = file_get_contents($configFile);
        if ($content === false) {
            $this->warn("Failed to read config file");
            return;
        }

        // Update latest_jam only if this is the highest year
        $content = preg_replace(
            "/'latest_jam' => env\('GAMEJAM_LATEST_JAM', '\d+'\),/",
            "'latest_jam' => env('GAMEJAM_LATEST_JAM', '{$year}'),",
            $content
        );

        file_put_contents($configFile, $content);
        $this->info("Updated config/gamejam.php: latest_jam = {$year} (highest year)");

        // Update Hyde config
        $this->updateHydeConfig($year);
    }

    protected function updateHydeConfig(int $year): void
    {
        // Update config/hyde.php - add year to exclude list
        $hydeConfigFile = base_path('config/hyde.php');
        if (!file_exists($hydeConfigFile)) {
            $this->warn("Hyde config file not found: {$hydeConfigFile}");
            return;
        }

        $hydeContent = file_get_contents($hydeConfigFile);
        if ($hydeContent === false) {
            $this->warn("Failed to read Hyde config file");
            return;
        }

        // Check if year is already in exclude list
        if (str_contains($hydeContent, "'{$year}'")) {
            $this->info("Year {$year} already in Hyde navigation exclude list");
            return;
        }

        // Find the exclude array and add the year
        // Pattern: 'exclude' => [ ... '2024', ... ],
        $pattern = "/(\s+)'exclude' => \[(\s+)(.*?)(\s+)\],/s";
        
        if (preg_match($pattern, $hydeContent, $matches)) {
            // Extract the existing years
            $existingContent = $matches[3];
            // Add new year before the closing bracket, maintaining formatting
            $newContent = $existingContent . "            '{$year}',\n";
            $replacement = $matches[1] . "'exclude' => [" . $matches[2] . $newContent . $matches[4] . "],";
            $hydeContent = preg_replace($pattern, $replacement, $hydeContent);
            
            file_put_contents($hydeConfigFile, $hydeContent);
            $this->info("Updated config/hyde.php: added {$year} to navigation exclude list");
        } else {
            $this->warn("Could not find exclude array in Hyde config file");
        }
    }
}

