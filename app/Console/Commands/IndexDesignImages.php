<?php

namespace App\Console\Commands;

use App\Models\Design;
use App\Services\DesignImageMatchingService;
use Illuminate\Console\Command;

class IndexDesignImages extends Command
{
    protected $signature = 'designs:index-images {--design= : Index only one public design ID}';

    protected $description = 'Generate image fingerprints and vector index entries for marketplace designs';

    public function handle(DesignImageMatchingService $matching): int
    {
        $query = Design::query()
            ->listed()
            ->with('machineType.machine')
            ->when($this->option('design'), fn ($builder, $id) => $builder->where('public_id', $id));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No designs were found to index.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(50, function ($designs) use ($matching, $bar) {
            foreach ($designs as $design) {
                $matching->syncDesign($design);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('Design images indexed successfully.');

        return self::SUCCESS;
    }
}
