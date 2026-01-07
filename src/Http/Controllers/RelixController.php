<?php

namespace CharlGottschalk\LaravelRelix\Http\Controllers;

use CharlGottschalk\LaravelRelix\RelixManager;
use CharlGottschalk\LaravelRelix\Llm\LlmClient;
use CharlGottschalk\LaravelRelix\Rules\RulesRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class RelixController
{
    public function index(RelixManager $relix): View
    {
        $schema = $relix->schema();

        return view('relix::index', [
            'schema' => $schema,
            'prompt' => $relix->prompt(),
            'rulesJson' => $relix->rules()->rawJson ?? "{\n  \"version\": 1,\n  \"tables\": {}\n}\n",
            'rulesPath' => $relix->rulesPath(),
            'llmEnabled' => config('relix.llm.enabled', false),
            'defaultCount' => (int) config('relix.defaults.count', 25),
            'connectionName' => $relix->connectionName() ?? config('database.default'),
            'databaseName' => $relix->databaseName(),
            'excludedTables' => $relix->excludedTables(),
        ]);
    }

    public function saveRules(Request $request, RelixManager $relix): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'string'],
        ]);

        try {
            $relix->saveRules($data['rules']);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['rules' => $e->getMessage()]);
        }

        return back()->with('status', 'Rules saved.');
    }

    public function generateSeeders(Request $request, RelixManager $relix): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:0'],
            'factories' => ['nullable', 'boolean'],
        ]);

        $count = $data['count'] ?? null;

        if ((bool) ($data['factories'] ?? false)) {
            $relix->generateFactories();
        }

        $result = $relix->generateSeeders($count);

        return back()->with('status', 'Generated ' . $result['generated'] . ' seeder(s) in ' . $result['path']);
    }

    public function seed(Request $request, RelixManager $relix): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:0'],
            'truncate' => ['nullable', 'boolean'],
        ]);

        $count = $data['count'] ?? null;
        $truncate = (bool) ($data['truncate'] ?? false);

        $result = $relix->seedDatabase($count, $truncate);

        return back()->with('status', 'Seeded ' . $result['seeded_tables'] . ' table(s).');
    }

    public function generateRulesWithLlm(Request $request, RelixManager $relix, LlmClient $llm, RulesRepository $rulesRepository): RedirectResponse
    {
        $data = $request->validate([
            'extra' => ['nullable', 'string', 'max:8000'],
            'save' => ['nullable', 'boolean'],
        ]);

        $extra = trim((string) ($data['extra'] ?? ''));
        $prompt = $relix->prompt();

        if ($extra !== '') {
            $prompt .= "\n\nAdditional project rules:\n" . $extra . "\n";
        }

        try {
            $rules = $llm->generateRulesJson($prompt);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['rules' => $e->getMessage()]);
        }

        $json = json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if ((bool) ($data['save'] ?? true)) {
            $rulesRepository->save($json);
            return back()->with('status', 'Generated rules via LLM and saved to ' . $rulesRepository->path() . '.');
        }

        return back()->withInput(['rules' => $json])->with('status', 'Generated rules via LLM (not saved).');
    }

    public function saveTableExclusions(Request $request, RelixManager $relix): RedirectResponse
    {
        $data = $request->validate([
            'excluded' => ['nullable', 'array'],
            'excluded.*' => ['string'],
        ]);

        $excluded = $data['excluded'] ?? [];
        $excluded = is_array($excluded) ? array_values($excluded) : [];

        $schemaTables = array_map(fn ($t) => $t->name, $relix->schema()->tables);
        $excluded = array_values(array_filter($excluded, fn (string $t) => in_array($t, $schemaTables, true)));
        sort($excluded);

        $relix->setExcludedTables($excluded);

        return back()->with('status', 'Saved table exclusions.');
    }
}
