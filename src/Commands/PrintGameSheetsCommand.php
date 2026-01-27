<?php

declare(strict_types=1);

namespace App\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;

use function file_exists;
use function is_dir;
use function mkdir;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

use function pathinfo;

use Symfony\Component\Yaml\Yaml;

/**
 * Command to generate printable PDF sheets for games of a specific year.
 *
 * Each game gets its own A4 PDF with:
 * - Title
 * - Team name and members
 * - Description
 * - Controls
 * - Screenshots
 */
class PrintGameSheetsCommand extends Command
{
    protected $signature = 'gamejam:print {year : The year of the Game Jam} {--only= : Comma-separated list of game slugs to print (optional)}';

    protected $description = 'Generate printable PDF sheets for games';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $onlySlugs = $this->option('only');

        $this->info("Generating PDF sheets for {$year}...");
        $this->newLine();

        // Load games YAML
        $yamlFile = base_path("_data/games/games{$year}.yaml");
        if (!file_exists($yamlFile)) {
            $this->error("Games YAML file not found: {$yamlFile}");
            return 1;
        }

        $games = Yaml::parseFile($yamlFile);
        if (!is_array($games)) {
            $this->error('Invalid YAML structure');
            return 1;
        }

        // Filter games if --only is specified
        if ($onlySlugs !== null && is_string($onlySlugs)) {
            $slugList = array_map('trim', explode(',', $onlySlugs));
            $games = array_filter($games, function ($entry) use ($slugList): bool {
                if (!is_array($entry) || !isset($entry['game']) || !is_array($entry['game'])) {
                    return false;
                }
                $gameName = $entry['game']['name'] ?? '';
                $slug = Str::slug(is_string($gameName) ? $gameName : '');
                return in_array($slug, $slugList, true);
            });
        }

        // Create output directory
        $outputDir = base_path("storage/pdfs/{$year}");
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info('Generating PDFs...');
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;

        foreach ($games as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $game = $entry['game'] ?? [];
            $team = $entry['team'] ?? [];
            if (!is_array($game)) {
                continue;
            }
            $gameName = is_string($game['name'] ?? null) ? $game['name'] : 'Unknown Game';
            $gameSlug = Str::slug($gameName);

            $this->line("  Processing: {$gameName}");

            try {
                $pdfPath = $this->generatePdf($year, $entry, $outputDir);
                $this->info("    ✓ Generated: {$pdfPath}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Success: {$successCount}");
        if ($errorCount > 0) {
            $this->error("Errors: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Generate PDF for a single game
     *
     * @param array<string, mixed> $entry
     */
    protected function generatePdf(int $year, array $entry, string $outputDir): string
    {
        $game = is_array($entry['game'] ?? null) ? $entry['game'] : [];
        $team = is_array($entry['team'] ?? null) ? $entry['team'] : [];
        $gameName = is_string($game['name'] ?? null) ? $game['name'] : 'Unknown Game';
        $gameSlug = Str::slug($gameName);
        $description = is_string($game['description'] ?? null) ? $game['description'] : '';
        $controlsText = is_string($game['controls_text'] ?? null) ? $game['controls_text'] : null;
        $players = $game['players'] ?? 1;
        $images = is_array($entry['images'] ?? null) ? $entry['images'] : [];
        $headerImage = is_string($entry['headerimage'] ?? null) ? $entry['headerimage'] : '';
        $controls = is_array($game['controls'] ?? null) ? $game['controls'] : [];
        $downloads = is_array($entry['download'] ?? null) ? $entry['download'] : [];

        // Prepare image paths (use full images for PDF)
        $imagePaths = [];
        foreach ($images as $image) {
            $imageFile = $image['file'] ?? '';
            if ($imageFile) {
                $fullPath = base_path("_media/{$year}/{$imageFile}");
                if (file_exists($fullPath)) {
                    $imagePaths[] = $fullPath;
                }
            }
        }

        // Limit to 3 screenshots for PDF
        $imagePaths = array_slice($imagePaths, 0, 3);

        // Prepare header image path
        $headerImagePath = null;
        if ($headerImage) {
            $headerPath = base_path("_media/{$year}/{$headerImage}");
            if (file_exists($headerPath)) {
                $headerImagePath = $headerPath;
            }
        }

        // Prepare input methods
        $inputMethods = null;
        if (!empty($controls) && is_array($controls)) {
            $inputMethods = implode(', ', array_map(function ($control): string {
                return ucfirst(is_string($control) ? $control : '');
            }, $controls));
        }

        // Prepare platforms
        $platforms = null;
        if (!empty($downloads) && is_array($downloads)) {
            $platformList = collect($downloads)->pluck('platform')->unique()->values()->toArray();
            if (!empty($platformList)) {
                $platforms = implode(', ', $platformList);
            }
        }

        // Render HTML
        $html = View::make('pdf.game-sheet', [
            'year' => $year,
            'gameName' => $gameName,
            'teamName' => $team['name'] ?? '',
            'teamMembers' => $team['members'] ?? [],
            'description' => $description,
            'controlsText' => $controlsText,
            'players' => $players,
            'imagePaths' => $imagePaths,
            'headerImagePath' => $headerImagePath,
            'inputMethods' => $inputMethods,
            'platforms' => $platforms,
        ])->render();

        // Generate PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF
        $pdfPath = $outputDir . '/' . $gameSlug . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        return pathinfo($pdfPath, PATHINFO_BASENAME);
    }
}
