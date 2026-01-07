<?php

namespace CharlGottschalk\LaravelRelix\Schema;

class TableSchema
{
    /**
     * @param list<ColumnSchema> $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
    ) {
    }

    public function column(string $name): ?ColumnSchema
    {
        foreach ($this->columns as $col) {
            if ($col->name === $name) {
                return $col;
            }
        }

        return null;
    }
}
