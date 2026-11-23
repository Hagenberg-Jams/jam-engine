<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use Aws\S3\S3Client;
use FilesystemIterator;
use HagenbergJams\JamEngine\Submission;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Downloads the uploads of the teams from the private submission bucket to submissions/{year}/{team}/.
 *
 * For each team it takes the latest file of each platform (or every file with --all), the README.md and the
 * screenshots, and puts the README.md into each ZIP file. `jam:add-project --submission={team}` then adds the project.
 */
class FetchSubmissionsCommand extends Command
{
    protected $signature = 'jam:fetch-submissions
                            {--year= : Year of the jam, default: the latest}
                            {--team=* : Only these teams, by the name of their folder}
                            {--all : Every version, not only the latest of each platform}
                            {--bucket= : The bucket, default: R2_SUBMISSIONS_BUCKET or <project>-submissions}
                            {--from= : A local folder with the layout of the bucket, instead of R2}';

    protected $description = 'Download the submissions of the teams and put the README.md into their ZIP files';

    protected ?S3Client $client = null;
    protected string $bucket = '';
    protected ?string $from = null;

    public function handle(): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : Submission::year();
        if ($year === null) {
            $this->error('No jam found in _data/jams.');

            return 1;
        }

        $from = $this->option('from');
        $this->from = is_string($from) && $from !== '' ? rtrim($from, '/\\') : null;
        if ($this->from === null && !$this->connect()) {
            return 1;
        }

        $teams = [];
        foreach ($this->list("{$year}/") as $key => $size) {
            $parts = explode('/', $key);
            if (count($parts) >= 3) {
                $teams[$parts[1]][implode('/', array_slice($parts, 2))] = $size;
            }
        }

        $only = array_map(fn(mixed $team): string => is_scalar($team) ? (string) $team : '', (array) $this->option('team'));
        if ($only !== []) {
            $teams = array_intersect_key($teams, array_flip($only));
        }
        ksort($teams);

        if ($teams === []) {
            $this->warn("No submissions found for {$year}.");

            return 0;
        }

        $rows = [];
        foreach ($teams as $team => $files) {
            $rows[] = $this->fetchTeam($year, (string) $team, $files);
        }

        $this->table(['Team', 'Title', 'Files', 'Screenshots'], $rows);
        $this->info("Saved to submissions/{$year}/. Add a project with: php hyde jam:add-project --year={$year} --submission=<team>");

        return 0;
    }

    /**
     * @param array<string, int> $files Paths inside the folder of the team, with their size
     * @return array<int, string|int>
     */
    protected function fetchTeam(int $year, string $team, array $files): array
    {
        $target = base_path("submissions/{$year}/{$team}");

        // What an earlier run fetched, so that large files are not downloaded again. A ZIP file changes its size
        // when the README goes in, so the size in the bucket is remembered here.
        $manifestFile = "{$target}/.fetched.json";
        $manifest = is_file($manifestFile) ? (array) json_decode((string) file_get_contents($manifestFile), true) : [];

        $get = function (string $file, string $path) use ($year, $team, $files, &$manifest): bool {
            $key = "{$year}/{$team}/{$file}";
            $small = str_ends_with($key, '.md') || str_ends_with($key, '.json');
            if (!$small && ($manifest[$key] ?? null) === $files[$file] && is_file($path)) {
                return true;
            }

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            if (!$this->fetch($key, $path, $files[$file])) {
                return false;
            }
            $manifest[$key] = $files[$file];

            return true;
        };

        $readme = isset($files['README.md']) && $get('README.md', "{$target}/README.md") ? "{$target}/README.md" : null;
        $title = $readme !== null ? Submission::parseReadme((string) file_get_contents($readme))['title'] : '';
        // The category the team chose, for jam:add-project.
        if (isset($files['category.json'])) {
            $get('category.json', "{$target}/category.json");
        }

        $screenshots = 0;
        $current = isset($files['screenshots/current.json']) && $get('screenshots/current.json', "{$target}/screenshots/current.json")
            ? json_decode((string) file_get_contents("{$target}/screenshots/current.json"), true) : null;
        foreach (is_array($current) ? array_values((array) ($current['files'] ?? [])) : [] as $index => $file) {
            $file = is_string($file) ? $file : '';
            if ($file !== '' && isset($files["screenshots/{$file}"])) {
                $path = sprintf('%s/screenshots/%02d.%s', $target, $index + 1, pathinfo($file, PATHINFO_EXTENSION));
                $screenshots += $get("screenshots/{$file}", $path) ? 1 : 0;
            }
        }

        $fetched = [];
        $uploads = array_keys(array_filter($files, fn(string $file): bool => str_starts_with($file, 'uploads/'), ARRAY_FILTER_USE_KEY));
        foreach ($this->choose($uploads) as $file) {
            $path = "{$target}/{$file}";
            if (!$get($file, $path)) {
                continue;
            }
            // The README goes in once, and again only when the team uploaded a new one.
            $readmeKey = "readme-in:{$year}/{$team}/{$file}";
            $readmeHash = $readme !== null ? (string) sha1_file($readme) : '';
            if ($readme !== null && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip' && ($manifest[$readmeKey] ?? null) !== $readmeHash) {
                $this->addReadme($path, $readme);
                $manifest[$readmeKey] = $readmeHash;
            }
            $fetched[] = basename($file);
        }

        if (is_dir($target)) {
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT) . "\n");
        }

        return [$team, $title !== '' ? $title : '(no README)', $fetched !== [] ? implode("\n", $fetched) : '–', $screenshots];
    }

    /**
     * The latest version of each platform, or every file with --all.
     *
     * @param array<int, string> $files
     * @return array<int, string>
     */
    protected function choose(array $files): array
    {
        if ($this->option('all')) {
            return $files;
        }

        $latest = [];
        foreach ($files as $file) {
            $info = self::describe(basename($file));
            if ($info === null) {
                continue;
            }
            $current = $latest[$info['platform']] ?? null;
            if ($current === null || version_compare($info['version'], $current['version'], '>')) {
                $latest[$info['platform']] = ['version' => $info['version'], 'file' => $file];
            }
        }

        return array_values(array_map(fn(array $entry): string => $entry['file'], $latest));
    }

    /**
     * Reads platform and version from a file name: {Team}_{Title}_{Platform}_{version}.zip for games,
     * {Team}_{Title}_{version}.mp4 for videos.
     *
     * @return array{platform: string, version: string}|null
     */
    public static function describe(string $file): ?array
    {
        if (preg_match('/^(?<rest>.+)_(?<version>\d+\.\d+\.\d+)\.[a-z0-9]+$/i', $file, $match) !== 1) {
            return null;
        }

        $parts = explode('_', $match['rest']);
        $platform = Submission::kind() === 'game' && count($parts) >= 3 ? (string) end($parts) : 'video';

        return ['platform' => $platform, 'version' => $match['version']];
    }

    /** Puts the README.md into the root of the ZIP file, in place of one that is there. */
    protected function addReadme(string $zip, string $readme): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->warn('The PHP zip extension is missing, so the README.md stays next to ' . basename($zip) . '.');

            return;
        }

        $archive = new ZipArchive();
        if ($archive->open($zip) !== true) {
            $this->warn('Cannot open ' . basename($zip) . ', so the README.md stays next to it.');

            return;
        }

        $archive->addFromString('README.md', (string) file_get_contents($readme));
        $archive->close();
    }

    // Storage: R2, or a local folder with the layout of the bucket, for tests or files downloaded by hand

    /** @return array<string, int> */
    protected function list(string $prefix): array
    {
        $found = [];

        if ($this->from !== null) {
            $base = "{$this->from}/{$prefix}";
            if (is_dir($base)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $file) {
                    /** @var SplFileInfo $file */
                    $found[$prefix . str_replace('\\', '/', substr($file->getPathname(), strlen($base)))] = (int) $file->getSize();
                }
            }

            return $found;
        }

        foreach ($this->client?->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket, 'Prefix' => $prefix]) ?? [] as $page) {
            foreach ((array) ($page['Contents'] ?? []) as $object) {
                if (is_array($object) && is_string($object['Key'] ?? null)) {
                    $found[$object['Key']] = is_numeric($object['Size'] ?? null) ? (int) $object['Size'] : 0;
                }
            }
        }

        return $found;
    }

    protected function fetch(string $key, string $path, int $size): bool
    {
        if ($this->from !== null) {
            return copy("{$this->from}/{$key}", $path);
        }

        try {
            $this->line("  {$key} (" . number_format($size / 1048576, 1) . ' MB)');
            $this->client?->getObject(['Bucket' => $this->bucket, 'Key' => $key, 'SaveAs' => $path]);

            return true;
        } catch (Throwable $error) {
            $this->warn("Cannot download {$key}: {$error->getMessage()}");

            return false;
        }
    }

    protected function connect(): bool
    {
        $account = env('R2_ACCOUNT_ID');
        $key = env('R2_ACCESS_KEY_ID');
        $secret = env('R2_SECRET_ACCESS_KEY');

        if (!is_string($account) || $account === '' || !is_string($key) || $key === '' || !is_string($secret) || $secret === '') {
            $this->error('Set R2_ACCOUNT_ID, R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY in .env, with an R2 token that can read the submission bucket.');

            return false;
        }

        $bucket = $this->option('bucket') ?: env('R2_SUBMISSIONS_BUCKET');
        if (!is_string($bucket) || $bucket === '') {
            $website = config('jam.website');
            $host = (string) parse_url(is_string($website) ? $website : '', PHP_URL_HOST);
            $bucket = str_replace('.', '-', $host) . '-submissions';
        }
        $this->bucket = $bucket;

        $endpoint = env('R2_ENDPOINT');
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => is_string($endpoint) && $endpoint !== '' ? $endpoint : "https://{$account}.eu.r2.cloudflarestorage.com",
            'credentials' => ['key' => $key, 'secret' => $secret],
            'use_path_style_endpoint' => true,
        ]);

        try {
            $this->client->headBucket(['Bucket' => $this->bucket]);
        } catch (Throwable $error) {
            $this->error("Cannot read the bucket {$this->bucket}: {$error->getMessage()}");

            return false;
        }

        $this->info("Reading the bucket {$this->bucket}");

        return true;
    }
}
