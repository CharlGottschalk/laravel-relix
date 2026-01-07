<?php

namespace CharlGottschalk\LaravelRelix\Rules;

class Ruleset
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?string $rawJson = null,
    ) {
    }

    public function tableCount(string $table): ?int
    {
        $count = $this->payload['tables'][$table]['count'] ?? null;

        return is_int($count) ? $count : null;
    }

    /**
     * @return list<string>
     */
    public function excludedTables(): array
    {
        $tables = $this->payload['exclude_tables'] ?? [];

        if (! is_array($tables)) {
            return [];
        }

        $filtered = [];
        foreach ($tables as $t) {
            if (is_string($t) && $t !== '') {
                $filtered[] = $t;
            }
        }

        $filtered = array_values(array_unique($filtered));
        sort($filtered);

        return $filtered;
    }

    public function hasTableRules(string $table): bool
    {
        $t = $this->payload['tables'][$table] ?? null;

        return is_array($t) && count($t) > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function columnRule(string $table, string $column): ?array
    {
        $rule = $this->payload['tables'][$table]['columns'][$column] ?? null;

        return is_array($rule) ? $rule : null;
    }
}
