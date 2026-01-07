<?php

namespace CharlGottschalk\LaravelRelix\Llm;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient implements LlmClient
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function generateRulesJson(string $prompt): array
    {
        $apiKey = (string) $this->config->get('relix.llm.openai.api_key');
        $baseUrl = rtrim((string) $this->config->get('relix.llm.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) $this->config->get('relix.llm.openai.model', 'gpt-4o-mini');
        $timeout = (int) $this->config->get('relix.llm.timeout', 60);

        if ($apiKey === '') {
            throw new RuntimeException('Missing OpenAI API key. Set OPENAI_API_KEY or RELIX_OPENAI_API_KEY.');
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($apiKey)
            ->post($baseUrl . '/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => 'Return ONLY valid JSON. No Markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('OpenAI request failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI response missing message content.');
        }

        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            throw new RuntimeException('OpenAI returned non-JSON content.');
        }

        return $payload;
    }
}
