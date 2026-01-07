<?php

namespace CharlGottschalk\LaravelRelix\Schema;

class DatabaseSchema
{
    /**
     * @param list<TableSchema> $tables
     */
    public function __construct(
        public readonly array $tables,
    ) {
    }

    public function table(string $name): ?TableSchema
    {
        foreach ($this->tables as $table) {
            if ($table->name === $name) {
                return $table;
            }
        }

        return null;
    }
}
