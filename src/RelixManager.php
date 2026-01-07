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

    public function rulesRequired(): Rules\Ruleset
    {
        return (new RulesRepository($this->config, $this->files))->getRequired();
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
        $rules = $this->rulesRequired();

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
        $rules = $this->rulesRequired();
        $connection = DB::connection($this->connectionName());

        $runner = new SeederRunner($this->config, $connection);

        return $runner->seed($schema, $rules, $count, $truncate, $tables);
    }

    /**
     * @param list<string> $extraExcludedTables
     */
    public function prompt(array $extraExcludedTables = []): string
    {
        $schema = $this->schema();

        $excluded = array_merge($this->excludedTables(), $extraExcludedTables);

        $excluded = array_values(array_filter(array_map(function ($t) {
            return is_string($t) ? trim($t) : '';
        }, $excluded), fn (string $t) => $t !== ''));
        $excluded = array_values(array_unique($excluded));
        sort($excluded);

        return Prompt\PromptBuilder::build($schema, $excluded);
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
