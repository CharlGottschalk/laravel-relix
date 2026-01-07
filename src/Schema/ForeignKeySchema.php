<?php

namespace CharlGottschalk\LaravelRelix\Schema;

class ForeignKeySchema
{
    public function __construct(
        public readonly string $foreignTable,
        public readonly string $foreignColumn,
    ) {
    }
}
