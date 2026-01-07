<?php

namespace CharlGottschalk\LaravelRelix\Llm;

interface LlmClient
{
    /**
     * @return array<string, mixed>
     */
    public function generateRulesJson(string $prompt): array;
}
