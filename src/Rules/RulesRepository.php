<?php

namespace CharlGottschalk\LaravelRelix\Rules;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class RulesRepository
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Filesystem $files,
    ) {
    }

    public function path(): string
    {
        $configured = $this->config->get('relix.rules_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/relix/rules.json');
    }

    public function get(): Ruleset
    {
        $path = $this->path();

        if (! $this->files->exists($path)) {
            return new Ruleset(['version' => 1, 'tables' => []], null);
        }

        $json = (string) $this->files->get($path);
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            return new Ruleset(['version' => 1, 'tables' => []], $json);
        }

        $payload = $this->normalizePayload($payload);

        return new Ruleset($payload, $json);
    }

    public function getRequired(): Ruleset
    {
        $path = $this->path();

        if (! $this->files->exists($path)) {
            throw new RuntimeException('Missing rules file at ' . $path . '. Create it via the UI or save rules JSON first.');
        }

        $json = (string) $this->files->get($path);

        if (trim($json) === '') {
            throw new RuntimeException('Rules file is empty at ' . $path . '. Add rules JSON before running Relix.');
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Rules must be valid JSON in ' . $path . '.');
        }

        $payload = $this->normalizePayload($payload);

        if (! isset($payload['tables']) || ! is_array($payload['tables']) || count($payload['tables']) === 0) {
            throw new RuntimeException('Rules must define at least one table in `tables` (file: ' . $path . ').');
        }

        return new Ruleset($payload, $json);
    }

    public function save(string $json): void
    {
        if (trim($json) === '') {
            throw new RuntimeException('Rules file cannot be empty.');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Rules must be valid JSON.');
        }

        $payload = $this->normalizePayload($payload);

        if (count($payload['tables']) === 0) {
            throw new RuntimeException('Rules must define at least one table in `tables`.');
        }

        $this->writePayload($payload);
    }

    /**
     * @param list<string> $tables
     */
    public function setExcludedTables(array $tables): void
    {
        $rules = $this->get();
        $payload = $rules->payload;
        $payload['exclude_tables'] = $this->normalizeStringList($tables);

        if (! isset($payload['tables']) || ! is_array($payload['tables']) || count($payload['tables']) === 0) {
            throw new RuntimeException('Rules must define at least one table in `tables` before exclusions can be saved.');
        }

        $this->writePayload($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writePayload(array $payload): void
    {
        $payload = $this->normalizePayload($payload);

        $path = $this->path();
        $dir = dirname($path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0755, true);
        }

        $this->files->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (! isset($payload['version'])) {
            $payload['version'] = 1;
        }

        if (! isset($payload['tables']) || ! is_array($payload['tables'])) {
            $payload['tables'] = [];
        }

        if (! isset($payload['exclude_tables']) || ! is_array($payload['exclude_tables'])) {
            $payload['exclude_tables'] = [];
        }

        $payload['exclude_tables'] = $this->normalizeStringList($payload['exclude_tables']);

        return $payload;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}
