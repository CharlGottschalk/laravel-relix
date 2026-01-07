<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\Llm\LlmClient;
use CharlGottschalk\LaravelRelix\RelixManager;
use CharlGottschalk\LaravelRelix\Rules\RulesRepository;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateRulesWithLlmCommand extends Command
{
    protected $signature = 'relix:llm-rules
        {--print : Print rules JSON to stdout}
        {--exclude=* : Tables to exclude from the prompt (repeatable or comma-separated)}
    ';

    protected $description = 'Generate Relix rules JSON via an LLM provider (saves to rules file; optional print)';

    public function handle(RelixManager $relix, LlmClient $llm, RulesRepository $rulesRepository): int
    {
        $extraExcludedTables = $this->parseExcludeOption($this->option('exclude'));
        $prompt = $relix->prompt($extraExcludedTables);

        try {
            $rules = $llm->generateRulesJson($prompt);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $json = json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        try {
            $rulesRepository->save($json);
            $this->info('Saved rules to ' . $rulesRepository->path());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('print')) {
            $this->line($json);
        }

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
