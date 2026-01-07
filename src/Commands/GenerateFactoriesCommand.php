<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\RelixManager;
use Illuminate\Console\Command;

class GenerateFactoriesCommand extends Command
{
    protected $signature = 'relix:generate-factories';

    protected $description = 'Generate missing Eloquent model factories based on the current database schema';

    public function handle(RelixManager $relix): int
    {
        $result = $relix->generateFactories();

        $this->info('Generated ' . $result['generated'] . ' factory file(s) in ' . $result['path']);

        if (! empty($result['skipped'])) {
            $this->warn('Skipped: ' . implode(', ', $result['skipped']));
        }

        return self::SUCCESS;
    }
}
