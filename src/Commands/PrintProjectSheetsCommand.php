<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;

use function file_exists;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

use function is_dir;
use function mkdir;
use function pathinfo;

use Symfony\Component\Yaml\Yaml;

/**
 * Command to generate printable PDF sheets for projects of a specific year.
 *
 * Each project gets its own A4 PDF with:
 * - Title
 * - Team name and members
 * - Description
 * - Controls
 * - Screenshots
 */
class PrintProjectSheetsCommand extends Command
{
    /** @var array<int, string> */
    protected $aliases = ['gamejam:print'];

    protected $signature = 'jam:print {year : The year of the jam} {--only= : Comma-separated list of project slugs to print (optional)}';

    protected $description = 'Generate printable PDF sheets for games';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $onlySlugs = $this->option('only');

        $this->info("Generating PDF sheets for {$year}...");
        $this->newLine();

        // Load projects YAML
        $yamlFile = base_path("_data/projects/{$year}.yaml");
        if (!file_exists($yamlFile)) {
            $this->error("Projects YAML file not found: {$yamlFile}");
            return 1;
        }

        $projects = Yaml::parseFile($yamlFile);
        if (!is_array($projects)) {
            $this->error('Invalid YAML structure');
            return 1;
        }

        // Filter projects if --only is specified
        if ($onlySlugs !== null && is_string($onlySlugs)) {
            $slugList = array_map('trim', explode(',', $onlySlugs));
            $projects = array_filter($projects, function ($entry) use ($slugList): bool {
                if (!is_array($entry) || !isset($entry['project']) || !is_array($entry['project'])) {
                    return false;
                }
                $projectName = $entry['project']['name'] ?? '';
                $slug = Str::slug(is_string($projectName) ? $projectName : '');
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

        foreach ($projects as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $project = $entry['project'] ?? [];
            $team = $entry['team'] ?? [];
            if (!is_array($project)) {
                continue;
            }
            $projectName = is_string($project['name'] ?? null) ? $project['name'] : 'Unknown Project';
            $projectSlug = Str::slug($projectName);

            $this->line("  Processing: {$projectName}");

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
     * Generate PDF for a single project
     *
     * @param array<mixed> $entry Parsed from YAML, not validated
     */
    protected function generatePdf(int $year, array $entry, string $outputDir): string
    {
        $project = is_array($entry['project'] ?? null) ? $entry['project'] : [];
        $team = is_array($entry['team'] ?? null) ? $entry['team'] : [];
        $projectName = is_string($project['name'] ?? null) ? $project['name'] : 'Unknown Project';
        $projectSlug = Str::slug($projectName);
        $description = is_string($project['description'] ?? null) ? $project['description'] : '';
        $controlsText = is_string($project['controls_text'] ?? null) ? $project['controls_text'] : null;
        $players = $project['players'] ?? 1;
        $images = is_array($entry['images'] ?? null) ? $entry['images'] : [];
        $headerImage = is_string($entry['headerimage'] ?? null) ? $entry['headerimage'] : '';
        $controls = is_array($project['controls'] ?? null) ? $project['controls'] : [];
        $downloads = is_array($entry['download'] ?? null) ? $entry['download'] : [];

        // Prepare image paths (use full images for PDF)
        $imagePaths = [];
        foreach ($images as $image) {
            if (!is_array($image) || !isset($image['file'])) {
                continue;
            }
            $imageFile = is_string($image['file']) ? $image['file'] : '';
            if ($imageFile !== '') {
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
        if (!empty($controls)) {
            $inputMethods = implode(', ', array_map(function ($control): string {
                return ucfirst(is_string($control) ? $control : '');
            }, $controls));
        }

        // Prepare platforms
        $platforms = null;
        if (!empty($downloads)) {
            // Collect distinct platforms in first-seen order, mirroring the previous
            // pluck()->unique()->values() chain but keeping the list typed as strings.
            $platformList = [];
            foreach ($downloads as $download) {
                $value = is_array($download) ? ($download['platform'] ?? null) : null;
                $platform = is_scalar($value) ? (string) $value : '';
                if (!in_array($platform, $platformList, true)) {
                    $platformList[] = $platform;
                }
            }
            $platforms = implode(', ', $platformList);
        }

        // Render HTML
        $html = View::make('pdf.project-sheet', [
            'year' => $year,
            'projectName' => $projectName,
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
        $pdfPath = $outputDir . '/' . $projectSlug . '.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        return pathinfo($pdfPath, PATHINFO_BASENAME);
    }
}
