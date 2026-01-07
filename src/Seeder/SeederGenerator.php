<?php

namespace CharlGottschalk\LaravelRelix\Seeder;

use CharlGottschalk\LaravelRelix\Rules\Ruleset;
use CharlGottschalk\LaravelRelix\Schema\DatabaseSchema;
use CharlGottschalk\LaravelRelix\Schema\TableSchema;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class SeederGenerator
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Filesystem $files,
    ) {
    }

    /**
     * @return array{generated:int, skipped:list<string>, path:string}
     */
    public function generate(DatabaseSchema $schema, Ruleset $rules, ?int $count, ?string $outputPath): array
    {
        $path = $outputPath ?: base_path('database/seeders/Relix');
        $ignored = array_values(array_unique(array_merge(
            (array) $this->config->get('relix.ignore_tables', []),
            $rules->excludedTables(),
        )));
        sort($ignored);

        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        $generated = 0;
        $skipped = [];

        foreach ($schema->tables as $table) {
            if (in_array($table->name, $ignored, true)) {
                $skipped[] = $table->name;
                continue;
            }

            $className = $this->classNameForTable($table->name);
            $filePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $className . '.php';

            $this->files->put($filePath, $this->renderSeeder($table, $count, $rules));
            $generated++;
        }

        $this->files->put(
            rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'RelixDatabaseSeeder.php',
            $this->renderDatabaseSeeder($schema, $ignored),
        );

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'path' => $path,
        ];
    }

    private function classNameForTable(string $table): string
    {
        $base = Str::studly(Str::singular($table));

        return $base . 'Seeder';
    }

    private function renderSeeder(TableSchema $table, ?int $count, Ruleset $rules): string
    {
        $className = $this->classNameForTable($table->name);
        $tableName = $table->name;
        $defaultCount = $count ?? $rules->tableCount($table->name) ?? (int) $this->config->get('relix.defaults.count', 25);

        return <<<PHP
<?php

namespace Database\\Seeders\\Relix;

use Illuminate\\Database\\Seeder;
use CharlGottschalk\\LaravelRelix\\Seeder\\SeederRunner;
use CharlGottschalk\\LaravelRelix\\Rules\\RulesRepository;

class {$className} extends Seeder
{
    public function run(): void
    {
        \$rules = app(RulesRepository::class)->get();
        app(SeederRunner::class)->seedTable('{$tableName}', \$rules, {$defaultCount}, false);
    }
}

PHP;
    }

    /**
     * @param list<string> $ignored
     */
    private function renderDatabaseSeeder(DatabaseSchema $schema, array $ignored): string
    {
        $tables = array_values(array_filter(
            $schema->tables,
            fn (TableSchema $t) => ! in_array($t->name, $ignored, true),
        ));

        $tables = (new TableOrderer())->order($tables);

        $calls = [];
        foreach ($tables as $table) {
            $calls[] = '        $this->call(' . $this->classNameForTable($table->name) . '::class);';
        }

        $callsString = implode("\n", $calls);

        return <<<PHP
<?php

namespace Database\\Seeders\\Relix;

use Illuminate\\Database\\Seeder;

class RelixDatabaseSeeder extends Seeder
{
    public function run(): void
    {
{$callsString}
    }
}

PHP;
    }
}
