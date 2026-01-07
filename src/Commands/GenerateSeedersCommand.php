<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\RelixManager;
use Illuminate\Console\Command;

class GenerateSeedersCommand extends Command
{
    protected $signature = 'relix:generate-seeders
        {--count= : Rows per table (overrides rules + defaults)}
        {--path= : Output path (defaults to database/seeders/Relix)}
        {--factories : Generate missing Eloquent factories first}
    ';

    protected $description = 'Generate Laravel seeder classes from the current database schema';

    public function handle(RelixManager $relix): int
    {
        $count = $this->option('count') !== null ? (int) $this->option('count') : null;
        $path = $this->option('path') ?: null;

        if ((bool) $this->option('factories')) {
            $relix->generateFactories();
        }

        $result = $relix->generateSeeders($count, $path);

        $this->info('Generated ' . $result['generated'] . ' seeder(s) in ' . $result['path']);

        if (! empty($result['skipped'])) {
            $this->warn('Skipped: ' . implode(', ', $result['skipped']));
        }

        return self::SUCCESS;
    }
}
