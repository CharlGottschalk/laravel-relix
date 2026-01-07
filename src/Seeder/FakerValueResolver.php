<?php

namespace CharlGottschalk\LaravelRelix\Seeder;

use CharlGottschalk\LaravelRelix\Eloquent\ModelRegistry;
use CharlGottschalk\LaravelRelix\Schema\ColumnSchema;
use CharlGottschalk\LaravelRelix\Schema\DatabaseSchema;
use CharlGottschalk\LaravelRelix\Schema\ForeignKeySchema;
use CharlGottschalk\LaravelRelix\Schema\TableSchema;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FakerValueResolver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?ModelRegistry $models = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $rule
     */
    public function resolve(TableSchema $table, ColumnSchema $column, DatabaseSchema $schema, \Faker\Generator $faker, ?array $rule): mixed
    {
        if ($rule) {
            $strategy = $rule['strategy'] ?? null;

            if ($strategy === 'literal') {
                return $rule['value'] ?? null;
            }

            if ($strategy === 'hash') {
                $plain = (string) ($rule['value'] ?? 'password');

                return Hash::make($plain);
            }

            if ($strategy === 'fk') {
                $fk = $this->foreignKeyFromRuleOrInference($table, $column, $schema, $rule);
                if ($fk) {
                    $value = $this->foreignKeyValueFromSchema($fk);
                    if ($value !== null) {
                        return $value;
                    }

                    if ((bool) ($rule['create'] ?? false)) {
                        return $this->createForeignRecordIfPossible($fk->foreignTable, $fk->foreignColumn);
                    }
                }
            }

            if ($strategy === 'faker') {
                $method = $rule['method'] ?? null;
                $unique = (bool) ($rule['unique'] ?? false);
                $args = $rule['args'] ?? [];

                if (is_string($method)) {
                    $method = $this->normalizeFakerMethod($method, $faker);
                }

                if (is_string($method)) {
                    $f = $unique ? $faker->unique() : $faker;

                    if (! is_array($args)) {
                        $args = [];
                    }

                    try {
                        /** @phpstan-ignore-next-line dynamic formatter */
                        return $f->{$method}(...$args);
                    } catch (\Throwable) {
                        // Faker formatters are usually resolved via __call(), so method_exists() isn't reliable.
                        try {
                            if (method_exists($f, 'format')) {
                                return $f->format($method);
                            }
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                }
            }
        }

        if ($column->foreignKey) {
            return $this->foreignKeyValue($column, $faker);
        }

        $inferred = $this->inferForeignKey($column, $schema);
        if ($inferred) {
            return $this->foreignKeyValueFromSchema($inferred);
        }

        return $this->heuristic($table, $column, $faker);
    }

    public function fallback(TableSchema $table, ColumnSchema $column, \Faker\Generator $faker): mixed
    {
        if ($column->type === 'boolean') {
            return false;
        }

        if (str_contains($column->type, 'int')) {
            return 1;
        }

        if (in_array($column->type, ['decimal', 'float'], true)) {
            return 0;
        }

        return $this->heuristic($table, $column, $faker) ?? 'n/a';
    }

    private function foreignKeyValue(ColumnSchema $column, \Faker\Generator $faker): mixed
    {
        $fk = $column->foreignKey;
        if (! $fk) {
            return null;
        }

        $value = $this->foreignKeyValueFromSchema($fk);

        if ($value !== null) {
            return $value;
        }

        return $this->createForeignRecordIfPossible($fk->foreignTable, $fk->foreignColumn);
    }

    private function foreignKeyValueFromSchema(ForeignKeySchema $fk): mixed
    {
        return $this->connection->table($fk->foreignTable)->inRandomOrder()->value($fk->foreignColumn);
    }

    private function inferForeignKey(ColumnSchema $column, DatabaseSchema $schema): ?ForeignKeySchema
    {
        if (! Str::endsWith($column->name, '_id')) {
            return null;
        }

        $base = Str::beforeLast($column->name, '_id');
        if ($base === '') {
            return null;
        }

        $guessTable = Str::plural($base);
        $table = $schema->table($guessTable);
        if (! $table) {
            return null;
        }

        $pk = null;
        foreach ($table->columns as $col) {
            if ($col->isPrimaryKey) {
                $pk = $col->name;
                break;
            }
        }
        $pk ??= $table->column('id')?->name;
        $pk ??= 'id';

        return new ForeignKeySchema($guessTable, $pk);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function foreignKeyFromRuleOrInference(TableSchema $table, ColumnSchema $column, DatabaseSchema $schema, array $rule): ?ForeignKeySchema
    {
        $foreignTable = $rule['table'] ?? null;
        $foreignColumn = $rule['column'] ?? null;

        if (is_string($foreignTable) && $foreignTable !== '') {
            $foreignColumn = is_string($foreignColumn) && $foreignColumn !== '' ? $foreignColumn : 'id';

            if ($schema->table($foreignTable)) {
                return new ForeignKeySchema($foreignTable, $foreignColumn);
            }
        }

        return $this->inferForeignKey($column, $schema);
    }

    private function createForeignRecordIfPossible(string $foreignTable, string $foreignColumn): mixed
    {
        $models = $this->models;
        if (! $models) {
            return null;
        }

        $modelClass = $models->modelForTable($foreignTable);
        if (! $modelClass || ! method_exists($modelClass, 'factory')) {
            return null;
        }

        try {
            $model = $modelClass::factory()->create();
        } catch (\Throwable) {
            return null;
        }

        try {
            return $model->getAttribute($foreignColumn);
        } catch (\Throwable) {
            return null;
        }
    }

    private function heuristic(TableSchema $table, ColumnSchema $column, \Faker\Generator $faker): mixed
    {
        $name = Str::lower($column->name);
        $type = $column->type;

        if (
            Str::endsWith($name, '_at')
            || str_contains($name, '_timestamp')
            || in_array($type, ['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable'], true)
        ) {
            return $faker->dateTimeBetween('-1 year', 'now');
        }

        if (str_contains($name, 'password')) {
            return Hash::make('password');
        }

        if (str_contains($name, 'token') || str_contains($name, 'api_key') || str_contains($name, 'secret')) {
            return Str::random(40);
        }

        if ($name === 'email' || str_contains($name, 'email')) {
            return $faker->unique()->safeEmail();
        }

        if ($name === 'username' || str_contains($name, 'user_name')) {
            return $faker->unique()->userName();
        }

        if ($name === 'first_name') {
            return $faker->firstName();
        }

        if ($name === 'last_name') {
            return $faker->lastName();
        }

        if ($name === 'name') {
            return $faker->name();
        }

        if (str_contains($name, 'phone')) {
            return $faker->phoneNumber();
        }

        if (str_contains($name, 'address')) {
            return $faker->streetAddress();
        }

        if ($name === 'city') {
            return $faker->city();
        }

        if (in_array($name, ['state', 'province', 'region'], true)) {
            return $faker->state();
        }

        if (in_array($name, ['zip', 'zipcode', 'postal_code'], true)) {
            return $faker->postcode();
        }

        if ($name === 'country') {
            return $faker->country();
        }

        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return $faker->url();
        }

        if (str_contains($name, 'uuid')) {
            return $faker->uuid();
        }

        if (str_contains($name, 'slug')) {
            return Str::slug($faker->sentence(3));
        }

        if (in_array($type, ['string', 'text'], true)) {
            return $type === 'text' ? $faker->paragraph() : $faker->words(3, true);
        }

        if (in_array($type, ['integer', 'bigint', 'smallint'], true) || str_contains($type, 'int')) {
            return $faker->numberBetween(1, 10_000);
        }

        if (in_array($type, ['decimal', 'float'], true)) {
            return $faker->randomFloat(2, 1, 10_000);
        }

        if ($type === 'boolean') {
            return $faker->boolean();
        }

        if (in_array($type, ['date', 'date_immutable'], true)) {
            return $faker->date();
        }

        if (in_array($type, ['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable'], true)) {
            return $faker->dateTimeBetween('-1 year', 'now');
        }

        if (in_array($type, ['json', 'jsonb'], true)) {
            return json_encode(['value' => $faker->word()], JSON_UNESCAPED_SLASHES);
        }

        if (in_array($type, ['blob', 'binary'], true)) {
            return $faker->text(50);
        }

        return null;
    }

    private function normalizeFakerMethod(string $method, \Faker\Generator $faker): ?string
    {
        $method = trim($method);
        if ($method === '') {
            return null;
        }

        if (method_exists($faker, $method)) {
            return $method;
        }

        $key = strtolower($method);
        $aliases = [
            'datetime' => 'dateTime',
            'date_time' => 'dateTime',
            'date-time' => 'dateTime',
            'timestamp' => 'dateTime',
            'datetimebetween' => 'dateTimeBetween',
            'date_time_between' => 'dateTimeBetween',
            'date-time-between' => 'dateTimeBetween',
        ];

        $resolved = $aliases[$key] ?? null;
        if (is_string($resolved) && method_exists($faker, $resolved)) {
            return $resolved;
        }

        return $method;
    }
}
