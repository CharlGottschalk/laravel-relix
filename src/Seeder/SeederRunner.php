<?php

namespace CharlGottschalk\LaravelRelix\Seeder;

use CharlGottschalk\LaravelRelix\Eloquent\ModelRegistry;
use CharlGottschalk\LaravelRelix\Rules\Ruleset;
use CharlGottschalk\LaravelRelix\Schema\DatabaseSchema;
use CharlGottschalk\LaravelRelix\Schema\ColumnSchema;
use CharlGottschalk\LaravelRelix\Schema\TableSchema;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SeederRunner
{
    private FakerValueResolver $resolver;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Connection $connection,
        private readonly ?ModelRegistry $models = null,
    ) {
        $this->resolver = new FakerValueResolver($this->connection, $this->models);
    }

    /**
     * @param list<string>|null $onlyTables
     * @return array{seeded_tables:int, skipped:list<string>}
     */
    public function seed(DatabaseSchema $schema, Ruleset $rules, ?int $count, bool $truncate, ?array $onlyTables): array
    {
        $tables = $schema->tables;
        $ignored = array_values(array_unique(array_merge(
            (array) $this->config->get('relix.ignore_tables', []),
            $rules->excludedTables(),
        )));
        sort($ignored);

        if (is_array($onlyTables) && count($onlyTables)) {
            $tables = array_values(array_filter($tables, fn (TableSchema $t) => in_array($t->name, $onlyTables, true)));
        }

        if (count($ignored)) {
            $tables = array_values(array_filter($tables, fn (TableSchema $t) => ! in_array($t->name, $ignored, true)));
        }

        $sorted = (new TableOrderer())->order($tables);

        if ($truncate) {
            $this->truncateTables(array_map(fn (TableSchema $t) => $t->name, array_reverse($sorted)));
        }

        $seeded = 0;
        $skipped = array_values(array_intersect($ignored, array_map(fn (TableSchema $t) => $t->name, $schema->tables)));

        foreach ($sorted as $table) {
            $this->seedTable($table->name, $rules, $count, false, $schema);
            $seeded++;
        }

        return [
            'seeded_tables' => $seeded,
            'skipped' => $skipped,
        ];
    }

    public function seedTable(string $table, Ruleset $rules, ?int $count, bool $truncate, ?DatabaseSchema $schema = null): void
    {
        $schema ??= (new \CharlGottschalk\LaravelRelix\Schema\Introspector($this->connection))->introspect();
        $tableSchema = $schema->table($table);

        if (! $tableSchema) {
            return;
        }

        $rows = $count ?? $rules->tableCount($table) ?? (int) $this->config->get('relix.defaults.count', 25);

        if ($truncate) {
            $this->truncateTables([$table]);
        }

        if ($this->isCompositeFkPrimaryKeyPivot($tableSchema)) {
            $this->seedPivotTable($tableSchema, $schema, $rules, $rows);
            return;
        }

        if ($this->shouldPreferFactories() && ! $rules->hasTableRules($table)) {
            if ($this->seedWithFactoryIfPossible($table, $rows)) {
                return;
            }
        }

        $faker = \Faker\Factory::create();
        $chunkSize = (int) $this->config->get('relix.defaults.chunk_size', 250);

        $batch = [];
        for ($i = 0; $i < $rows; $i++) {
            $batch[] = $this->makeRow($tableSchema, $schema, $rules, $faker);

            if (count($batch) >= $chunkSize) {
                $this->connection->table($table)->insert($batch);
                $batch = [];
            }
        }

        if (count($batch)) {
            $this->connection->table($table)->insert($batch);
        }
    }

    private function isCompositeFkPrimaryKeyPivot(TableSchema $table): bool
    {
        $pk = array_values(array_filter($table->columns, fn (ColumnSchema $c) => $c->isPrimaryKey));

        if (count($pk) < 2) {
            return false;
        }

        foreach ($pk as $col) {
            if (! $col->foreignKey || $col->autoIncrement) {
                return false;
            }
        }

        return true;
    }

    private function seedPivotTable(TableSchema $table, DatabaseSchema $schema, Ruleset $rules, int $rows): void
    {
        $pkColumns = array_values(array_filter($table->columns, fn (ColumnSchema $c) => $c->isPrimaryKey));

        $valueSets = [];
        foreach ($pkColumns as $col) {
            $values = $this->pivotForeignKeyChoices($table->name, $col, $rules);
            if (count($values) === 0) {
                return;
            }
            $valueSets[$col->name] = $values;
        }

        $maxCombos = 1;
        foreach ($valueSets as $set) {
            $maxCombos *= max(1, count($set));
            if ($maxCombos > 1_000_000) {
                // Avoid huge numbers; we'll rely on insertOrIgnore to skip duplicates.
                break;
            }
        }

        if ($maxCombos > 0) {
            $rows = min($rows, $maxCombos);
        }

        $faker = \Faker\Factory::create();
        $chunkSize = (int) $this->config->get('relix.defaults.chunk_size', 250);

        $seen = [];
        $batch = [];
        $attempts = 0;
        $maxAttempts = max(50, $rows * 50);

        while (count($seen) < $rows && $attempts < $maxAttempts) {
            $attempts++;

            $row = [];
            foreach ($pkColumns as $col) {
                $choices = $valueSets[$col->name];
                $row[$col->name] = $choices[array_rand($choices)];
            }

            $key = implode('-', array_map(fn (ColumnSchema $c) => (string) $row[$c->name], $pkColumns));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            foreach ($table->columns as $column) {
                if (array_key_exists($column->name, $row)) {
                    continue;
                }
                if ($column->autoIncrement) {
                    continue;
                }
                if (in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }

                $rule = $rules->columnRule($table->name, $column->name);
                $value = $this->resolver->resolve($table, $column, $schema, $faker, $rule);

                if ($value === null && ! $column->nullable) {
                    $value = $this->resolver->fallback($table, $column, $faker);
                }

                $row[$column->name] = $value;
            }

            if ($table->column('created_at')) {
                $row['created_at'] = now();
            }
            if ($table->column('updated_at')) {
                $row['updated_at'] = now();
            }

            $batch[] = $row;

            if (count($batch) >= $chunkSize) {
                $this->insertPivotBatch($table->name, $batch);
                $batch = [];
            }
        }

        if (count($batch)) {
            $this->insertPivotBatch($table->name, $batch);
        }
    }

    /**
     * @return list<mixed>
     */
    private function pivotForeignKeyChoices(string $table, ColumnSchema $column, Ruleset $rules): array
    {
        $rule = $rules->columnRule($table, $column->name);

        if (is_array($rule) && ($rule['strategy'] ?? null) === 'literal') {
            $value = $rule['value'] ?? null;
            if ($value === null) {
                return [];
            }
            return [$value];
        }

        if (is_array($rule) && ($rule['strategy'] ?? null) === 'fk') {
            $foreignTable = $rule['table'] ?? null;
            $foreignColumn = $rule['column'] ?? null;

            if (! is_string($foreignTable) || $foreignTable === '') {
                return [];
            }

            $foreignColumn = is_string($foreignColumn) && $foreignColumn !== '' ? $foreignColumn : 'id';
            $values = $this->connection->table($foreignTable)->pluck($foreignColumn)->all();
            if (! is_array($values)) {
                return [];
            }

            return array_values(array_filter($values, fn ($v) => $v !== null));
        }

        $fk = $column->foreignKey;
        if (! $fk) {
            return [];
        }

        $values = $this->connection->table($fk->foreignTable)->pluck($fk->foreignColumn)->all();
        if (! is_array($values)) {
            return [];
        }

        $values = array_values(array_filter($values, fn ($v) => $v !== null));

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $batch
     */
    private function insertPivotBatch(string $table, array $batch): void
    {
        $qb = $this->connection->table($table);

        if (method_exists($qb, 'insertOrIgnore')) {
            $qb->insertOrIgnore($batch);
            return;
        }

        // Fallback: attempt insert; duplicates will throw.
        $qb->insert($batch);
    }

    private function shouldPreferFactories(): bool
    {
        if (! $this->config->get('relix.eloquent.enabled', true)) {
            return false;
        }

        return (bool) $this->config->get('relix.eloquent.prefer_factories', true);
    }

    private function seedWithFactoryIfPossible(string $table, int $rows): bool
    {
        $models = $this->models;
        if (! $models) {
            return false;
        }

        $modelClass = $models->modelForTable($table);
        if (! $modelClass) {
            return false;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            return false;
        }

        if (! method_exists($modelClass, 'factory')) {
            return false;
        }

        try {
            $modelClass::factory()->count($rows)->create();
        } catch (\Throwable $e) {
            throw new RuntimeException('Factory seeding failed for table [' . $table . '] using model [' . $modelClass . ']: ' . $e->getMessage(), 0, $e);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRow(TableSchema $table, DatabaseSchema $schema, Ruleset $rules, \Faker\Generator $faker): array
    {
        $row = [];

        foreach ($table->columns as $column) {
            if ($column->autoIncrement) {
                continue;
            }

            if (in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $rule = $rules->columnRule($table->name, $column->name);
            $value = $this->resolver->resolve($table, $column, $schema, $faker, $rule);

            if ($value === null && ! $column->nullable) {
                $value = $this->resolver->fallback($table, $column, $faker);
            }

            $row[$column->name] = $value;
        }

        if ($table->column('created_at')) {
            $row['created_at'] = now();
        }
        if ($table->column('updated_at')) {
            $row['updated_at'] = now();
        }

        return $row;
    }

    /**
     * @param list<string> $tables
     */
    private function truncateTables(array $tables): void
    {
        $driver = $this->connection->getDriverName();

        if ($driver === 'pgsql') {
            $wrapped = array_map(fn (string $t) => $this->connection->getQueryGrammar()->wrapTable($t), $tables);
            $this->connection->statement('TRUNCATE TABLE ' . implode(', ', $wrapped) . ' RESTART IDENTITY CASCADE');
            return;
        }

        if ($driver === 'mysql') {
            $this->connection->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $this->connection->statement('PRAGMA foreign_keys = OFF');
        }

        foreach ($tables as $table) {
            $this->connection->table($table)->truncate();
        }

        if ($driver === 'mysql') {
            $this->connection->statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            $this->connection->statement('PRAGMA foreign_keys = ON');
        }
    }
}
