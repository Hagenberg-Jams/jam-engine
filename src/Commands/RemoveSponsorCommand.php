<?php

declare(strict_types=1);

namespace App\Commands;

use function array_search;
use function array_values;
use function file_exists;
use function file_put_contents;

use Illuminate\Console\Command;

use function is_array;

use Symfony\Component\Yaml\Yaml;

use function unlink;

/**
 * Command to remove a sponsor from the homepage.
 *
 * This command:
 * - Lists sponsors from homepage.yaml
 * - Asks which sponsor to remove
 * - Removes the entry from homepage.yaml
 * - Deletes the logo file from _media
 */
class RemoveSponsorCommand extends Command
{
    protected $signature = 'gamejam:remove-sponsor';

    protected $description = 'Remove a sponsor from the homepage';

    protected string $homepageYaml;

    protected string $mediaDir;

    public function handle(): int
    {
        $this->info('Removing a sponsor...');
        $this->newLine();

        $this->homepageYaml = base_path('_data/homepage.yaml');
        $this->mediaDir = base_path('_media');

        if (!file_exists($this->homepageYaml)) {
            $this->error('homepage.yaml not found.');
            return 1;
        }

        $data = Yaml::parseFile($this->homepageYaml) ?? [];

        if (!is_array($data) || !isset($data['sponsors']) || !is_array($data['sponsors']) || !isset($data['sponsors']['items']) || !is_array($data['sponsors']['items'])) {
            $this->error('No sponsors found in homepage.yaml.');
            return 1;
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['sponsors']['items'];
        if (empty($items)) {
            $this->error('No sponsors to remove.');
            return 1;
        }

        $choices = [];
        foreach ($items as $index => $sponsor) {
            if (!is_array($sponsor) || !isset($sponsor['name'])) {
                continue;
            }
            $name = is_string($sponsor['name']) ? $sponsor['name'] : 'Unknown';
            $logoVal = $sponsor['logo'] ?? null;
            $logo = $logoVal !== null && is_string($logoVal) ? ' (' . $logoVal . ')' : '';
            $choices[(string) $index] = $name . $logo;
        }

        if (empty($choices)) {
            $this->error('No valid sponsors found.');
            return 1;
        }

        $choiceLabels = array_values($choices);
        $selected = $this->choice('Which sponsor do you want to remove?', $choiceLabels);
        $selectedIndex = array_search($selected, $choiceLabels, true);

        if ($selectedIndex === false) {
            $this->info('Cancelled.');
            return 0;
        }

        $sponsor = $items[(int) $selectedIndex] ?? null;
        if (!is_array($sponsor)) {
            $this->error('Invalid sponsor.');
            return 1;
        }

        $logo = isset($sponsor['logo']) && is_string($sponsor['logo']) ? $sponsor['logo'] : null;
        $name = is_string($sponsor['name'] ?? null) ? $sponsor['name'] : 'Unknown';

        if (!$this->confirm("Remove '{$name}'? This will also delete the logo file.", true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $newItems = [];
        foreach ($items as $index => $item) {
            if ((int) $index !== (int) $selectedIndex) {
                $newItems[] = $item;
            }
        }

        $data['sponsors']['items'] = $newItems;

        $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($this->homepageYaml, $yamlContent);

        if ($logo !== null) {
            $logoPath = "{$this->mediaDir}/{$logo}";
            if (file_exists($logoPath)) {
                if (@unlink($logoPath)) {
                    $this->info("Deleted logo: {$logo}");
                } else {
                    $this->warn("Could not delete logo file: {$logoPath}");
                }
            } else {
                $this->warn("Logo file not found: {$logoPath}");
            }
        }

        $this->newLine();
        $this->info("Successfully removed sponsor '{$name}'.");

        return 0;
    }
}
