<?php

namespace CharlGottschalk\LaravelRelix;

use CharlGottschalk\LaravelRelix\Rules\RulesRepository;
use CharlGottschalk\LaravelRelix\Schema\Introspector;
use CharlGottschalk\LaravelRelix\Eloquent\FactoryGenerator;
use CharlGottschalk\LaravelRelix\Seeder\SeederGenerator;
use CharlGottschalk\LaravelRelix\Seeder\SeederRunner;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class RelixManager
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Filesystem $files,
    ) {
    }

    public function connectionName(): ?string
    {
        return $this->config->get('relix.connection');
    }

    public function schema(): Schema\DatabaseSchema
    {
        $connection = DB::connection($this->connectionName());

        return (new Introspector($connection))->introspect();
    }

    public function rules(): Rules\Ruleset
    {
        return (new RulesRepository($this->config, $this->files))->get();
    }

    /**
     * @return list<string>
     */
    public function excludedTables(): array
    {
        $configIgnored = (array) $this->config->get('relix.ignore_tables', []);
        $rulesIgnored = $this->rules()->excludedTables();

        $all = array_merge($configIgnored, $rulesIgnored);
        $filtered = [];

        foreach ($all as $t) {
            if (is_string($t)) {
                $t = trim($t);
                if ($t !== '') {
                    $filtered[] = $t;
                }
            }
        }

        $filtered = array_values(array_unique($filtered));
        sort($filtered);

        return $filtered;
    }

    /**
     * @param list<string> $tables
     */
    public function setExcludedTables(array $tables): void
    {
        (new RulesRepository($this->config, $this->files))->setExcludedTables($tables);
    }

    public function rulesPath(): string
    {
        return (new RulesRepository($this->config, $this->files))->path();
    }

    public function saveRules(string $json): void
    {
        (new RulesRepository($this->config, $this->files))->save($json);
    }

    public function generateSeeders(?int $count = null, ?string $outputPath = null): array
    {
        $schema = $this->schema();
        $rules = $this->rules();

        $generator = new SeederGenerator($this->config, $this->files);

        return $generator->generate($schema, $rules, $count, $outputPath);
    }

    public function generateFactories(): array
    {
        $schema = $this->schema();

        return app(FactoryGenerator::class)->generate($schema);
    }

    public function seedDatabase(?int $count = null, bool $truncate = false, ?array $tables = null): array
    {
        $schema = $this->schema();
        $rules = $this->rules();
        $connection = DB::connection($this->connectionName());

        $runner = new SeederRunner($this->config, $connection);

        return $runner->seed($schema, $rules, $count, $truncate, $tables);
    }

    public function prompt(): string
    {
        $schema = $this->schema();

        return Prompt\PromptBuilder::build($schema, $this->excludedTables());
    }

    public function databaseName(): ?string
    {
        try {
            return DB::connection($this->connectionName())->getDatabaseName();
        } catch (\Throwable) {
            return null;
        }
    }
}
