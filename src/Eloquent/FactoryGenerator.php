<?php

namespace CharlGottschalk\LaravelRelix\Eloquent;

use CharlGottschalk\LaravelRelix\Schema\ColumnSchema;
use CharlGottschalk\LaravelRelix\Schema\DatabaseSchema;
use CharlGottschalk\LaravelRelix\Schema\TableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FactoryGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModelRegistry $models,
    ) {
    }

    /**
     * @return array{generated:int, skipped:list<string>, path:string}
     */
    public function generate(DatabaseSchema $schema): array
    {
        $path = base_path('database/factories');

        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        $generated = 0;
        $skipped = [];

        foreach ($schema->tables as $table) {
            $modelClass = $this->models->modelForTable($table->name);
            if (! $modelClass) {
                $skipped[] = $table->name . ' (no model)';
                continue;
            }

            $factoryClass = class_basename($modelClass) . 'Factory';
            $filePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $factoryClass . '.php';

            if ($this->files->exists($filePath)) {
                $skipped[] = $table->name . ' (factory exists)';
                continue;
            }

            $this->files->put($filePath, $this->renderFactory($modelClass, $table, $schema));
            $generated++;
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'path' => $path,
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function renderFactory(string $modelClass, TableSchema $table, DatabaseSchema $schema): string
    {
        $factoryClass = class_basename($modelClass) . 'Factory';
        $modelFqcn = '\\' . ltrim($modelClass, '\\');

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = 'namespace Database\\Factories;';
        $lines[] = '';
        $lines[] = 'use Illuminate\\Database\\Eloquent\\Factories\\Factory;';
        $lines[] = 'use Illuminate\\Support\\Facades\\Hash;';
        $lines[] = 'use Illuminate\\Support\\Str;';
        $lines[] = 'use ' . ltrim($modelFqcn, '\\') . ';';
        $lines[] = '';
        $lines[] = 'class ' . $factoryClass . ' extends Factory';
        $lines[] = '{';
        $lines[] = '    protected $model = ' . class_basename($modelClass) . '::class;';
        $lines[] = '';
        $lines[] = '    public function definition(): array';
        $lines[] = '    {';
        $lines[] = '        return [';

        foreach ($table->columns as $column) {
            if ($column->autoIncrement || $column->isPrimaryKey) {
                continue;
            }
            if (in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $expr = $this->expressionForColumn($table, $column, $schema);
            if ($column->nullable) {
                $expr = '$this->faker->boolean(10) ? null : ' . $expr;
            }

            $lines[] = "            '" . $column->name . "' => " . $expr . ',';
        }

        $lines[] = '        ];';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function expressionForColumn(TableSchema $table, ColumnSchema $column, DatabaseSchema $schema): string
    {
        $name = Str::lower($column->name);
        $type = $column->type;

        if ($column->foreignKey) {
            $foreignModel = $this->models->modelForTable($column->foreignKey->foreignTable);
            if ($foreignModel && method_exists($foreignModel, 'factory')) {
                return '\\' . ltrim($foreignModel, '\\') . '::factory()';
            }
        }

        if (str_contains($name, 'password')) {
            return "Hash::make('password')";
        }

        if (str_contains($name, 'token') || str_contains($name, 'api_key') || str_contains($name, 'secret')) {
            return 'Str::random(40)';
        }

        if ($name === 'email' || str_contains($name, 'email')) {
            return '$this->faker->unique()->safeEmail()';
        }

        if ($name === 'username' || str_contains($name, 'user_name')) {
            return '$this->faker->unique()->userName()';
        }

        if ($name === 'first_name') {
            return '$this->faker->firstName()';
        }

        if ($name === 'last_name') {
            return '$this->faker->lastName()';
        }

        if ($name === 'name') {
            return '$this->faker->name()';
        }

        if (str_contains($name, 'phone')) {
            return '$this->faker->phoneNumber()';
        }

        if (str_contains($name, 'address')) {
            return '$this->faker->streetAddress()';
        }

        if ($name === 'city') {
            return '$this->faker->city()';
        }

        if (in_array($name, ['state', 'province', 'region'], true)) {
            return '$this->faker->state()';
        }

        if (in_array($name, ['zip', 'zipcode', 'postal_code'], true)) {
            return '$this->faker->postcode()';
        }

        if ($name === 'country') {
            return '$this->faker->country()';
        }

        if (str_contains($name, 'url') || str_contains($name, 'website')) {
            return '$this->faker->url()';
        }

        if (str_contains($name, 'uuid')) {
            return '$this->faker->uuid()';
        }

        if (str_contains($name, 'slug')) {
            return 'Str::slug($this->faker->sentence(3))';
        }

        if (in_array($type, ['string', 'text'], true)) {
            return $type === 'text' ? '$this->faker->paragraph()' : '$this->faker->words(3, true)';
        }

        if (in_array($type, ['integer', 'bigint', 'smallint'], true) || str_contains($type, 'int')) {
            return '$this->faker->numberBetween(1, 10000)';
        }

        if (in_array($type, ['decimal', 'float'], true)) {
            return '$this->faker->randomFloat(2, 1, 10000)';
        }

        if ($type === 'boolean') {
            return '$this->faker->boolean()';
        }

        if (in_array($type, ['date', 'date_immutable'], true)) {
            return '$this->faker->date()';
        }

        if (in_array($type, ['datetime', 'datetimetz', 'datetime_immutable', 'datetimetz_immutable'], true)) {
            return '$this->faker->dateTimeBetween("-1 year", "now")';
        }

        if (in_array($type, ['json', 'jsonb'], true)) {
            return "['value' => \$this->faker->word()]";
        }

        return '$this->faker->word()';
    }
}
