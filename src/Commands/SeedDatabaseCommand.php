<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\RelixManager;
use Illuminate\Console\Command;

class SeedDatabaseCommand extends Command
{
    protected $signature = 'relix:seed
        {--count= : Rows per table (overrides rules + defaults)}
        {--truncate : Truncate tables before seeding}
        {--tables=* : Only seed these tables (repeatable)}
    ';

    protected $description = 'Seed the database directly from the current schema (no file generation)';

    public function handle(RelixManager $relix): int
    {
        $count = $this->option('count') !== null ? (int) $this->option('count') : null;
        $truncate = (bool) $this->option('truncate');
        $tables = $this->option('tables');
        $tables = is_array($tables) && count($tables) ? array_values($tables) : null;

        $result = $relix->seedDatabase($count, $truncate, $tables);

        $this->info('Seeded ' . $result['seeded_tables'] . ' table(s)');

        if (! empty($result['skipped'])) {
            $this->warn('Skipped: ' . implode(', ', $result['skipped']));
        }

        return self::SUCCESS;
    }
}
