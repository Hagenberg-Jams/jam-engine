<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use HagenbergJams\JamEngine\JamValidator;
use Illuminate\Console\Command;

class ValidateCommand extends Command
{
    /** @var string */
    protected $signature = 'jam:validate';

    /** @var string */
    protected $description = 'Check the site data, including that no topic is public too early';

    public function handle(): int
    {
        $result = (new JamValidator())->run();

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        if ($result->errors !== []) {
            return self::FAILURE;
        }

        $this->info('The site data is valid.');

        return self::SUCCESS;
    }
}
