<?php

declare(strict_types=1);

namespace App\Actions\PreBuildTasks;

use App\Services\OptimizeImagesService;
use Hyde\Framework\Actions\PreBuildTasks\TransferMediaAssets as BaseTransferMediaAssets;

/**
 * Extends Hyde's TransferMediaAssets to optimize images before copying.
 *
 * Runs OptimizeImagesService first (resize, compress, responsive variants),
 * then performs the normal media transfer.
 *
 * Replaces the framework's TransferMediaAssets when registered in build_tasks.
 */
class TransferMediaAssets extends BaseTransferMediaAssets
{
    public function handle(): void
    {
        $optimizer = new OptimizeImagesService();
        if ($optimizer->run()) {
            $optimized = $optimizer->getOptimized();
            if ($optimized !== []) {
                $this->info('Optimized ' . count($optimized) . ' image(s)');
            }
        } else {
            foreach ($optimizer->getErrors() as $error) {
                $this->warn($error);
            }
        }

        parent::handle();
    }
}
