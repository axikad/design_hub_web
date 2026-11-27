<?php

namespace App\Console\Commands;

use App\Services\GoogleProductSearchIndex;
use Illuminate\Console\Command;
use Throwable;

class SetupGoogleImageSearch extends Command
{
    protected $signature = 'designs:setup-google-image-search';

    protected $description = 'Validate Google image-search configuration and create the Product Set';

    public function handle(GoogleProductSearchIndex $google): int
    {
        if (! $google->enabled()) {
            $this->error('Google image search is disabled or its configuration/credentials are incomplete.');

            return self::FAILURE;
        }

        try {
            $google->ensureProductSet();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Google Product Set is ready.');

        return self::SUCCESS;
    }
}
