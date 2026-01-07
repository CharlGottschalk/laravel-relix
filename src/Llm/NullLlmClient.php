<?php

namespace CharlGottschalk\LaravelRelix\Llm;

use RuntimeException;

class NullLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $message,
    ) {
    }

    public function generateRulesJson(string $prompt): array
    {
        throw new RuntimeException($this->message);
    }
}
