<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\RelixManager;
use Illuminate\Console\Command;

class PrintLlmPromptCommand extends Command
{
    protected $signature = 'relix:llm-prompt
        {--exclude=* : Tables to exclude from the prompt (repeatable or comma-separated)}
    ';

    protected $description = 'Print the LLM prompt for generating Relix rules (copy/paste into your provider)';

    public function handle(RelixManager $relix): int
    {
        $extraExcludedTables = $this->parseExcludeOption($this->option('exclude'));
        $prompt = $relix->prompt($extraExcludedTables);

        $this->line($prompt);

        return self::SUCCESS;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function parseExcludeOption(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }

            foreach (explode(',', $item) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $out[] = $piece;
                }
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}

