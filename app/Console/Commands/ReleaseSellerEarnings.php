<?php

namespace App\Console\Commands;

use App\Services\MarketplacePaymentSettings;
use App\Services\SellerEarningReleaseService;
use Illuminate\Console\Command;

class ReleaseSellerEarnings extends Command
{
    protected $signature = 'marketplace:release-earnings
                            {--environment= : Payment environment to process (test or live)}
                            {--limit=500 : Maximum earnings to process in this run}';

    protected $description = 'Move eligible seller earnings from pending to available balance';

    public function handle(
        SellerEarningReleaseService $releaseService,
        MarketplacePaymentSettings $settings,
    ): int {
        $environment = (string) ($this->option('environment') ?: $settings->environment());
        $limit = (int) $this->option('limit');

        if (! in_array($environment, ['test', 'live'], true)) {
            $this->error('Environment must be test or live.');

            return self::INVALID;
        }

        if ($limit < 1 || $limit > 5000) {
            $this->error('Limit must be between 1 and 5000.');

            return self::INVALID;
        }

        $released = $releaseService->releaseEligible($environment, $limit);

        $this->info("Released {$released} {$environment} seller earning(s).");

        return self::SUCCESS;
    }
}
