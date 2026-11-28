<?php

namespace App\Console\Commands;

use App\Models\Design;
use App\Services\DesignImageMatchingService;
use Illuminate\Console\Command;

class IndexDesignImages extends Command
{
    protected $signature = 'designs:index-images
        {--design= : Index only one public design ID}
        {--failed : Retry only designs that currently have failed embeddings}
        {--delay= : Delay between designs in milliseconds}';

    protected $description = 'Generate local fingerprints and Gemini image embeddings for marketplace designs';

    public function handle(DesignImageMatchingService $matching): int
    {
        $query = Design::query()
            ->listed()
            ->with(['machine', 'machineType.machine'])
            ->when($this->option('design'), fn ($builder, $id) => $builder->where('public_id', $id))
            ->when($this->option('failed'), fn ($builder) => $builder->whereHas(
                'imageFingerprints',
                fn ($fingerprints) => $fingerprints->where('embedding_status', 'failed'),
            ));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No designs were found to index.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ready = 0;
        $failed = 0;
        $delay = $this->option('delay') !== null
            ? max(0, (int) $this->option('delay'))
            : max(0, (int) config('designhub.image_matching.index_delay_ms', 1000));

        $query->chunkById(50, function ($designs) use ($matching, $bar, &$ready, &$failed, $delay) {
            foreach ($designs as $design) {
                $matching->syncDesign($design);
                $design->load('imageFingerprints');

                if ($design->imageFingerprints->isEmpty() || $design->imageFingerprints->contains('embedding_status', 'failed')) {
                    $failed++;
                } else {
                    $ready++;
                }

                $bar->advance();

                if ($delay > 0) {
                    usleep($delay * 1000);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->line(sprintf('Processed: %d | Ready: %d | Failed: %d', $total, $ready, $failed));

        if ($failed > 0) {
            $this->warn('Some design images failed. Retry them with: php artisan designs:index-images --failed');

            return self::FAILURE;
        }

        $this->info('Design images indexed successfully.');

        return self::SUCCESS;
    }
}
