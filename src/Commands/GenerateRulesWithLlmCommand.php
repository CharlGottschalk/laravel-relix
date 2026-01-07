<?php

namespace CharlGottschalk\LaravelRelix\Commands;

use CharlGottschalk\LaravelRelix\Llm\LlmClient;
use CharlGottschalk\LaravelRelix\Prompt\PromptBuilder;
use CharlGottschalk\LaravelRelix\RelixManager;
use CharlGottschalk\LaravelRelix\Rules\RulesRepository;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateRulesWithLlmCommand extends Command
{
    protected $signature = 'relix:llm-rules
        {--save : Save rules to the configured rules path}
        {--print : Print rules JSON to stdout}
    ';

    protected $description = 'Generate Relix rules JSON via an LLM provider (optional save/print)';

    public function handle(RelixManager $relix, LlmClient $llm, RulesRepository $rulesRepository): int
    {
        $prompt = $relix->prompt();

        try {
            $rules = $llm->generateRulesJson($prompt);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $json = json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if ($this->option('save')) {
            $rulesRepository->save($json);
            $this->info('Saved rules to ' . $rulesRepository->path());
        }

        if ($this->option('print') || ! $this->option('save')) {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
