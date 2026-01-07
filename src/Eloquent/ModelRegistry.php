<?php

namespace CharlGottschalk\LaravelRelix\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModelRegistry
{
    /** @var array<string, class-string<Model>>|null */
    private ?array $tableToModel = null;

    public function __construct(
        private readonly Filesystem $files,
    ) {
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelForTable(string $table): ?string
    {
        $map = $this->tableToModel ??= $this->buildMap();

        return $map[$table] ?? null;
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function buildMap(): array
    {
        $paths = array_values(array_filter([
            app_path('Models'),
            app_path(),
        ], fn (string $p) => is_dir($p)));

        $files = [];
        foreach ($paths as $path) {
            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $map = [];
        foreach ($files as $path) {
            $fqcn = $this->classFromFile($path);
            if (! $fqcn || ! class_exists($fqcn)) {
                continue;
            }

            if (! is_subclass_of($fqcn, Model::class)) {
                continue;
            }

            /** @var Model $instance */
            $instance = new $fqcn();
            $table = $instance->getTable();
            $map[$table] = $fqcn;
        }

        return $map;
    }

    private function classFromFile(string $path): ?string
    {
        $contents = $this->files->get($path);

        $namespace = null;
        if (preg_match('/^namespace\\s+([^;]+);/m', $contents, $m)) {
            $namespace = trim($m[1]);
        }

        if (! preg_match('/^class\\s+([A-Za-z0-9_]+)/m', $contents, $m)) {
            return null;
        }

        $class = $m[1];

        if ($namespace) {
            return $namespace . '\\' . $class;
        }

        // Rare, but handle no-namespace classes under app/ by guessing app namespace.
        $appNamespace = app()->getNamespace();
        return Str::finish($appNamespace, '\\') . $class;
    }
}
