<?php

namespace CharlGottschalk\LaravelRelix\Prompt;

use CharlGottschalk\LaravelRelix\Schema\DatabaseSchema;
use CharlGottschalk\LaravelRelix\Schema\TableSchema;

class PromptBuilder
{
    /**
     * @param list<string> $excludedTables
     */
    public static function build(DatabaseSchema $schema, array $excludedTables = []): string
    {
        $lines = [];
        $excludedTables = array_values(array_unique(array_filter($excludedTables, fn ($t) => is_string($t) && $t !== '')));
        sort($excludedTables);

        $pivotTables = [];
        foreach ($schema->tables as $table) {
            if (in_array($table->name, $excludedTables, true)) {
                continue;
            }
            if (self::isPivotTable($table)) {
                $pivotTables[] = $table->name;
            }
        }
        sort($pivotTables);

        $lines[] = 'You are helping generate database seeding rules for a Laravel project.';
        $lines[] = 'Return ONLY valid JSON matching this format:';
        $lines[] = '';
        $lines[] = '{';
        $lines[] = '  "version": 1,';
        $lines[] = '  "exclude_tables": ["<table_name>"],';
        $lines[] = '  "tables": {';
        $lines[] = '    "<table_name>": {';
        $lines[] = '      "count": 25,';
        $lines[] = '      "columns": {';
        $lines[] = '        "<column_name>": { "strategy": "faker", "method": "safeEmail", "unique": true }';
        $lines[] = '      }';
        $lines[] = '    }';
        $lines[] = '  }';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'Supported column strategies:';
        $lines[] = '- {"strategy":"faker","method":"...","unique":true|false,"args":[...]}';
        $lines[] = '- {"strategy":"literal","value":...}';
        $lines[] = '- {"strategy":"hash","value":"plain text password"}';
        $lines[] = '- {"strategy":"fk","table":"users","column":"id"}';
        $lines[] = '';
        $lines[] = 'Important: ALWAYS include "exclude_tables" in the returned JSON (use [] if none).';
        if (count($excludedTables)) {
            $lines[] = 'Set "exclude_tables" to exactly: ' . json_encode(array_values($excludedTables), JSON_UNESCAPED_SLASHES);
        }
        if (count($pivotTables)) {
            $lines[] = '';
            $lines[] = 'Pivot table guidance:';
            $lines[] = '- For pivot tables, do NOT set literal IDs for foreign keys.';
            $lines[] = '- For pivot tables, prefer rules like:';
            $lines[] = '  "pivot_table_name": { "count": 60, "columns": {} }';
            $lines[] = '- Detected pivot tables: ' . implode(', ', $pivotTables);
        }
        $lines[] = '';
        $lines[] = 'Database schema (tables -> columns -> type, nullable, foreign key):';
        $lines[] = '';

        foreach ($schema->tables as $table) {
            if (in_array($table->name, $excludedTables, true)) {
                continue;
            }
            $suffix = self::isPivotTable($table) ? ' (pivot)' : '';
            $lines[] = $table->name . ':' . $suffix;
            foreach ($table->columns as $col) {
                $suffix = [];
                $suffix[] = $col->type;
                if ($col->nullable) {
                    $suffix[] = 'nullable';
                }
                if ($col->foreignKey) {
                    $suffix[] = 'fk->' . $col->foreignKey->foreignTable . '.' . $col->foreignKey->foreignColumn;
                }
                $lines[] = '  - ' . $col->name . ' (' . implode(', ', $suffix) . ')';
            }
            $lines[] = '';
        }

        $lines[] = 'Guidelines:';
        $lines[] = '- Use realistic values (names, emails, addresses, phones).';
        $lines[] = '- For password columns, use {"strategy":"hash","value":"password"} unless specified.';
        $lines[] = '- For tokens, use faker bothify/regexify or random strings.';
        $lines[] = '- Keep referential integrity: foreign keys should point to existing rows.';
        $lines[] = '- Do NOT include per-table rules for tables listed in "exclude_tables".';
        $lines[] = '- IMPORTANT: consider BOTH the table name and column name when choosing faker methods.';
        $lines[] = '- Example: in "tags", the "name" column should be a tag-like label (words/slug), not a person name.';
        $lines[] = '- Example: in "categories", "name" should be a category label; in "roles", "name" should be a role label.';

        return implode("\n", $lines);
    }

    private static function isPivotTable(TableSchema $table): bool
    {
        $pk = array_values(array_filter($table->columns, fn ($c) => $c->isPrimaryKey));

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
}
