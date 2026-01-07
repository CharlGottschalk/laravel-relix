<?php

namespace CharlGottschalk\LaravelRelix\Schema;

class ColumnSchema
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $autoIncrement,
        public readonly bool $isPrimaryKey,
        public readonly ?ForeignKeySchema $foreignKey = null,
    ) {
    }
}
