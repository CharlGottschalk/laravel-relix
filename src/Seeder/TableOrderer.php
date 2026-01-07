<?php

namespace CharlGottschalk\LaravelRelix\Seeder;

use CharlGottschalk\LaravelRelix\Schema\TableSchema;
use Illuminate\Support\Str;

class TableOrderer
{
    /**
     * @param list<TableSchema> $tables
     * @return list<TableSchema>
     */
    public function order(array $tables): array
    {
        $byName = [];
        foreach ($tables as $t) {
            $byName[$t->name] = $t;
        }

        $deps = [];
        foreach ($tables as $t) {
            $deps[$t->name] = [];
            foreach ($t->columns as $col) {
                if ($col->foreignKey) {
                    $deps[$t->name][$col->foreignKey->foreignTable] = true;
                    continue;
                }

                // Heuristic fallback when DB constraints aren't introspected:
                // user_id -> users, blog_post_id -> blog_posts, etc.
                if (Str::endsWith($col->name, '_id')) {
                    $base = Str::beforeLast($col->name, '_id');
                    $guess = Str::plural($base);
                    if ($guess !== '' && isset($byName[$guess])) {
                        $deps[$t->name][$guess] = true;
                    }
                }
            }
            unset($deps[$t->name][$t->name]);
        }

        $incoming = [];
        foreach ($deps as $t => $ds) {
            $incoming[$t] = count($ds);
        }

        $queue = [];
        foreach ($incoming as $t => $n) {
            if ($n === 0) {
                $queue[] = $t;
            }
        }

        $ordered = [];
        while (count($queue)) {
            $node = array_shift($queue);
            $ordered[] = $node;

            foreach ($deps as $t => $ds) {
                if (isset($ds[$node])) {
                    unset($deps[$t][$node]);
                    $incoming[$t]--;
                    if ($incoming[$t] === 0) {
                        $queue[] = $t;
                    }
                }
            }
        }

        // Cycle fallback: append remaining in original order.
        $remaining = array_diff(array_keys($byName), $ordered);
        foreach ($remaining as $t) {
            $ordered[] = $t;
        }

        return array_values(array_map(fn (string $name) => $byName[$name], $ordered));
    }
}
