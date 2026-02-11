<?php

declare(strict_types=1);

namespace App\Commands;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

use function array_filter;
use function array_map;
use function file_exists;
use function glob;
use function hash_file;
use function is_dir;
use function mkdir;
use function pathinfo;
use function preg_match;
use function scandir;
use function str_replace;
use function str_starts_with;

use Illuminate\Console\Command;

use function is_file;
use function is_string;
use function rtrim;

/**
 * Command to synchronize game files between local games/ directory and Cloudflare R2 bucket.
 *
 * Supports:
 * - Upload: local → R2 (e.g. after add-game)
 * - Download: R2 → local (e.g. for new dev setup)
 * - Sync: bidirectional (R2 is master, local gets missing files, local uploads new/changed)
 *
 * Destructive operations (deletions) require confirmation.
 */
class SyncR2Command extends Command
{
    protected $signature = 'gamejam:sync-r2 
                            {--upload : Upload local games to R2}
                            {--download : Download games from R2 to local}
                            {--sync : Bidirectional sync (R2 is master)}
                            {--year= : Only sync games for a specific year}
                            {--dry-run : Show what would be done without making changes}
                            {--force : Skip confirmation prompts (use with caution)}';

    protected $description = 'Synchronize game files between local games/ directory and Cloudflare R2 bucket';

    protected ?S3Client $s3Client = null;
    protected string $localGamesDir;
    protected string $bucketName;
    protected ?int $year = null;

    /** @var array<string> Files that would be deleted (require confirmation) */
    protected array $filesToDelete = [];

    public function handle(): int
    {
        $this->localGamesDir = base_path('games');

        // Validate credentials
        if (!$this->validateCredentials()) {
            return 1;
        }

        // Determine operation mode
        $upload = (bool) $this->option('upload');
        $download = (bool) $this->option('download');
        $sync = (bool) $this->option('sync');
        $dryRun = (bool) $this->option('dry-run');

        if (!$upload && !$download && !$sync) {
            $this->error('Please specify --upload, --download, or --sync');
            $this->info('Examples:');
            $this->line('  php hyde gamejam:sync-r2 --upload --year=2025');
            $this->line('  php hyde gamejam:sync-r2 --download');
            $this->line('  php hyde gamejam:sync-r2 --sync');
            return 1;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get year filter
        $this->year = $this->option('year') ? (int) $this->option('year') : null;

        // Initialize S3 client
        if (!$this->initS3Client()) {
            return 1;
        }

        try {
            if ($upload) {
                return $this->handleUpload($dryRun);
            } elseif ($download) {
                return $this->handleDownload($dryRun);
            } elseif ($sync) {
                return $this->handleSync($dryRun);
            }
        } catch (AwsException $e) {
            $this->error('AWS/R2 Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function validateCredentials(): bool
    {
        $accountId = env('R2_ACCOUNT_ID');
        $accessKeyId = env('R2_ACCESS_KEY_ID');
        $secretAccessKey = env('R2_SECRET_ACCESS_KEY');
        $bucketName = env('R2_BUCKET_NAME');

        if (empty($accountId) || empty($accessKeyId) || empty($secretAccessKey) || empty($bucketName)) {
            $this->error('R2 credentials not configured in .env');
            $this->info('Required variables:');
            $this->line('  R2_ACCOUNT_ID');
            $this->line('  R2_ACCESS_KEY_ID');
            $this->line('  R2_SECRET_ACCESS_KEY');
            $this->line('  R2_BUCKET_NAME');
            $this->newLine();
            $this->info('Get these from: Cloudflare Dashboard → R2 → Manage R2 API Tokens');
            return false;
        }

        if (!is_string($bucketName)) {
            $this->error('R2_BUCKET_NAME must be a string');
            return false;
        }

        $this->bucketName = $bucketName;
        return true;
    }

    protected function initS3Client(): bool
    {
        $accountId = env('R2_ACCOUNT_ID');
        if (!is_string($accountId)) {
            $this->error('R2_ACCOUNT_ID must be a string');
            return false;
        }

        // Use EU-specific endpoint: https://{accountId}.eu.r2.cloudflarestorage.com
        $endpoint = env('R2_ENDPOINT', "https://{$accountId}.eu.r2.cloudflarestorage.com");

        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => 'auto', // R2 uses 'auto' region
                'endpoint' => $endpoint,
                'credentials' => [
                    'key' => env('R2_ACCESS_KEY_ID'),
                    'secret' => env('R2_SECRET_ACCESS_KEY'),
                ],
                'use_path_style_endpoint' => true, // Required for R2
            ]);

            // Test connection by listing bucket
            $this->s3Client->headBucket(['Bucket' => $this->bucketName]);
            return true;
        } catch (AwsException $e) {
            $this->error('Failed to connect to R2: ' . $e->getMessage());
            return false;
        }
    }

    protected function handleUpload(bool $dryRun): int
    {
        $this->info('Uploading local games to R2...');
        $this->newLine();

        $localFiles = $this->getLocalFiles();
        if (empty($localFiles)) {
            $this->warn('No local game files found');
            return 0;
        }

        $uploaded = 0;
        $skipped = 0;
        $errors = 0;

        $this->withProgressBar($localFiles, function (string $localPath) use (&$uploaded, &$skipped, &$errors, $dryRun) {
            // Normalize path separators (Windows uses backslashes)
            $normalizedLocalDir = str_replace('\\', '/', $this->localGamesDir);
            $normalizedLocalPath = str_replace('\\', '/', $localPath);
            $relativePath = str_replace($normalizedLocalDir . '/', '', $normalizedLocalPath);
            $key = "games/{$relativePath}";

            // Check if file exists in R2 and compare size/ETag
            $shouldUpload = true;
            if ($this->s3Client !== null) {
                try {
                    $exists = $this->s3Client->doesObjectExist($this->bucketName, $key);
                    if ($exists) {
                        $localSize = filesize($localPath);
                        $remoteInfo = $this->getRemoteFileInfo($key);
                        if ($remoteInfo !== null && $localSize === $remoteInfo['size']) {
                            // Files have same size, assume they're identical
                            // (For exact comparison, we'd need to download, but that's expensive)
                            $shouldUpload = false;
                            $skipped++;
                        }
                    }
                } catch (AwsException $e) {
                    // Continue with upload on error
                }
            }

            if ($shouldUpload) {
                if (!$dryRun && $this->s3Client !== null) {
                    try {
                        // Use SourceFile instead of Body to stream the file (avoids memory issues)
                        $this->s3Client->putObject([
                            'Bucket' => $this->bucketName,
                            'Key' => $key,
                            'SourceFile' => $localPath,
                            'ContentType' => 'application/zip',
                        ]);
                        $uploaded++;
                        // Free memory after upload
                        gc_collect_cycles();
                    } catch (AwsException $e) {
                        $this->newLine();
                        $this->error("Failed to upload {$relativePath}: " . $e->getMessage());
                        $errors++;
                    }
                } else {
                    if ($dryRun) {
                        $this->line("Would upload: {$relativePath}");
                        $uploaded++;
                    }
                }
            }
        });

        $this->newLine();
        $this->info("Upload complete: {$uploaded} uploaded, {$skipped} skipped, {$errors} errors");
        return $errors > 0 ? 1 : 0;
    }

    protected function handleDownload(bool $dryRun): int
    {
        $this->info('Downloading games from R2...');
        $this->newLine();

        $remoteFiles = $this->getRemoteFiles();
        if (empty($remoteFiles)) {
            $this->warn('No game files found in R2 bucket');
            return 0;
        }

        $downloaded = 0;
        $skipped = 0;
        $errors = 0;

        $this->withProgressBar($remoteFiles, function (string $key) use (&$downloaded, &$skipped, &$errors, $dryRun) {
            // Extract year and filename from key (e.g. "games/2025/file.zip")
            if (!preg_match('#^games/(\d{4})/(.+)$#', $key, $matches)) {
                return;
            }

            $year = (int) $matches[1];
            $filename = $matches[2];

            // Apply year filter if set
            if ($this->year !== null && $year !== $this->year) {
                return;
            }

            $localPath = "{$this->localGamesDir}/{$year}/{$filename}";

            // Check if local file exists and compare size
            $shouldDownload = true;
            if (file_exists($localPath)) {
                $localSize = filesize($localPath);
                $remoteInfo = $this->getRemoteFileInfo($key);
                if ($remoteInfo !== null && $localSize === $remoteInfo['size']) {
                    // Files have same size, assume they're identical
                    $shouldDownload = false;
                    $skipped++;
                }
            }

            if ($shouldDownload) {
                if (!$dryRun && $this->s3Client !== null) {
                    try {
                        // Ensure directory exists
                        $dir = dirname($localPath);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }

                        // Use SaveAs to stream directly to file (avoids memory issues)
                        $this->s3Client->getObject([
                            'Bucket' => $this->bucketName,
                            'Key' => $key,
                            'SaveAs' => $localPath,
                        ]);

                        $downloaded++;
                        // Free memory after download
                        gc_collect_cycles();
                    } catch (AwsException $e) {
                        $this->newLine();
                        $this->error("Failed to download {$key}: " . $e->getMessage());
                        $errors++;
                    }
                } else {
                    if ($dryRun) {
                        $this->line("Would download: {$year}/{$filename}");
                        $downloaded++;
                    }
                }
            }
        });

        $this->newLine();
        $this->info("Download complete: {$downloaded} downloaded, {$skipped} skipped, {$errors} errors");
        return $errors > 0 ? 1 : 0;
    }

    protected function handleSync(bool $dryRun): int
    {
        $this->info('Synchronizing games (R2 is master)...');
        $this->newLine();

        // Step 1: Download missing/changed files from R2
        $this->info('Step 1: Downloading from R2...');
        $downloadResult = $this->handleDownload($dryRun);
        $this->newLine();

        // Step 2: Upload new/changed files to R2
        $this->info('Step 2: Uploading to R2...');
        $uploadResult = $this->handleUpload($dryRun);
        $this->newLine();

        // Step 3: Check for files that exist locally but not in R2
        $this->info('Step 3: Checking for orphaned local files...');
        $localFiles = $this->getLocalFiles();
        $remoteFiles = $this->getRemoteFiles();
        $remoteKeys = array_map(fn($key) => str_replace('games/', '', $key), $remoteFiles);

        foreach ($localFiles as $localPath) {
            // Normalize path separators (Windows uses backslashes)
            $normalizedLocalDir = str_replace('\\', '/', $this->localGamesDir);
            $normalizedLocalPath = str_replace('\\', '/', $localPath);
            $relativePath = str_replace($normalizedLocalDir . '/', '', $normalizedLocalPath);
            if (!in_array($relativePath, $remoteKeys, true)) {
                $this->filesToDelete[] = $relativePath;
            }
        }

        if (!empty($this->filesToDelete)) {
            $this->warn('Found ' . count($this->filesToDelete) . ' local file(s) not in R2:');
            foreach ($this->filesToDelete as $file) {
                $this->line("  - {$file}");
            }

            if (!$dryRun) {
                if ($this->option('force') || $this->confirm('Delete these local files?', false)) {
                    foreach ($this->filesToDelete as $file) {
                        $fullPath = "{$this->localGamesDir}/{$file}";
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                            $this->line("Deleted: {$file}");
                        }
                    }
                } else {
                    $this->info('Skipped deletion');
                }
            }
        } else {
            $this->info('No orphaned local files found');
        }

        return ($downloadResult !== 0 || $uploadResult !== 0) ? 1 : 0;
    }

    /**
     * Get all local game files (optionally filtered by year).
     *
     * @return array<string>
     */
    protected function getLocalFiles(): array
    {
        $files = [];

        if (!is_dir($this->localGamesDir)) {
            return $files;
        }

        $items = scandir($this->localGamesDir);
        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore' || $item === '.gitkeep') {
                continue;
            }

            $itemPath = "{$this->localGamesDir}/{$item}";

            if (is_file($itemPath) && str_ends_with(strtolower($item), '.zip')) {
                // File in root (no year folder)
                if ($this->year === null) {
                    $files[] = $itemPath;
                }
            } elseif (is_dir($itemPath) && preg_match('/^\d{4}$/', $item)) {
                // Year folder
                $year = (int) $item;
                if ($this->year !== null && $year !== $this->year) {
                    continue;
                }

                $yearFiles = glob("{$itemPath}/*.zip") ?: [];
                $files = array_merge($files, $yearFiles);
            }
        }

        return $files;
    }

    /**
     * Get all remote game files from R2 (optionally filtered by year).
     *
     * @return array<string>
     */
    /**
     * Get all remote game files from R2 (optionally filtered by year).
     *
     * @return array<string>
     */
    protected function getRemoteFiles(): array
    {
        $files = [];
        $prefix = 'games/';

        if ($this->s3Client === null) {
            return $files;
        }

        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $prefix,
            ]);

            if (!isset($result['Contents']) || !is_array($result['Contents'])) {
                return $files;
            }

            foreach ($result['Contents'] as $object) {
                if (!is_array($object)) {
                    continue;
                }

                $key = isset($object['Key']) && is_string($object['Key']) ? $object['Key'] : '';
                if (empty($key) || !str_starts_with($key, $prefix)) {
                    continue;
                }

                // Extract year from key (e.g. "games/2025/file.zip")
                if (preg_match('#^games/(\d{4})/#', $key, $matches)) {
                    $year = (int) $matches[1];
                    if ($this->year !== null && $year !== $this->year) {
                        continue;
                    }
                } elseif ($this->year !== null) {
                    // Skip files not in year folders if year filter is set
                    continue;
                }

                $files[] = $key;
            }
        } catch (AwsException $e) {
            $this->error('Failed to list R2 objects: ' . $e->getMessage());
        }

        return $files;
    }

    /**
     * Get remote file info (size and ETag) for comparison.
     *
     * @return array{size: int, etag: string}|null
     */
    protected function getRemoteFileInfo(string $key): ?array
    {
        if ($this->s3Client === null) {
            return null;
        }

        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
            ]);

            $size = isset($result['ContentLength']) && is_int($result['ContentLength']) ? $result['ContentLength'] : 0;
            $etag = isset($result['ETag']) && is_string($result['ETag']) ? trim($result['ETag'], '"') : '';

            return [
                'size' => $size,
                'etag' => $etag,
            ];
        } catch (AwsException $e) {
            return null;
        }
    }
}
