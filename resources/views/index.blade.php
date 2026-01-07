@php
    /** @var \CharlGottschalk\LaravelRelix\Schema\DatabaseSchema $schema */
@endphp

<x-relix::layout :connection-name="$connectionName" title="Relix">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-slate-200">AI Prompt</div>
                        <div class="text-xs text-slate-400">Ask an LLM for seeding rules; paste the rules below.</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                data-relix-copy="relix-ai-prompt"
                                class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 shadow-sm hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400/20">
                            Copy
                        </button>
                    </div>
                </div>
                <textarea id="relix-ai-prompt" readonly rows="10" class="w-full rounded-lg border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-100 shadow-sm">{{ $prompt }}</textarea>
            </div>

            @if ($llmEnabled)
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="mb-3">
                    <div class="text-sm font-medium text-slate-200">Generate With LLM (currently OpenAI only)</div>
                    <div class="text-xs text-slate-400">Optional: call the configured LLM provider and save rules automatically.</div>
                </div>
                <form method="post" action="{{ route('relix.llm.generate-rules') }}" class="space-y-3">
                    @csrf
                    <label class="block text-xs text-slate-300">
                        Extra instructions (optional)
                        <textarea name="extra" rows="5" class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-100 shadow-sm outline-none ring-1 ring-transparent focus:border-fuchsia-500 focus:ring-fuchsia-500/40">{{ old('extra') }}</textarea>
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-300">
                        <input type="checkbox" name="save" value="1" checked class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-fuchsia-500 focus:ring-fuchsia-500/40" />
                        Save to rules file
                    </label>
                    <div class="flex items-center justify-end">
                        <button class="rounded-md bg-fuchsia-500 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-fuchsia-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-500/40">
                            Generate rules via LLM
                        </button>
                    </div>
                    <div class="text-xs text-slate-500">
                        Enable with <span class="font-mono">RELIX_LLM_ENABLED=true</span> and set <span class="font-mono">OPENAI_API_KEY</span> (or <span class="font-mono">RELIX_OPENAI_API_KEY</span>).
                    </div>
                </form>
            </div>
            @endif

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="mb-3">
                    <div class="text-sm font-medium text-slate-200">Rules (JSON)</div>
                    <div class="text-xs text-slate-400">Saved to <span class="font-mono">{{ $rulesPath }}</span></div>
                </div>
                <form method="post" action="{{ route('relix.rules.save') }}" class="space-y-3">
                    @csrf
                    <textarea name="rules" rows="12" class="w-full rounded-lg border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-100 shadow-sm outline-none ring-1 ring-transparent focus:border-slate-600 focus:ring-slate-400/20">{{ old('rules', $rulesJson) }}</textarea>
                    <div class="flex items-center justify-end gap-2">
                        <button class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 shadow-sm hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400/20">
                            Save rules
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="mb-3">
                    <div class="text-sm font-medium text-slate-200">Actions</div>
                    <div class="text-xs text-slate-400">Uses your current `.env` database connection.</div>
                </div>

                <div class="space-y-4">
                    <form method="post" action="{{ route('relix.generate-seeders') }}" class="rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                        @csrf
                        <div class="mb-3 text-xs font-medium text-slate-200">Generate seeders</div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex flex-col items-start gap-3">
                                <label class="text-xs text-slate-300">
                                    Count
                                    <input name="count" value="{{ old('count', $defaultCount) }}" class="mt-1 w-24 rounded-md border border-slate-700 bg-slate-950 px-2 py-1 font-mono text-sm text-slate-100 shadow-sm outline-none ring-1 ring-transparent focus:border-indigo-500 focus:ring-indigo-500/40" />
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-300">
                                    <input type="checkbox" name="factories" value="1" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-indigo-500 focus:ring-indigo-500/40" />
                                    Generate missing factories
                                </label>
                            </div>
                            <button class="rounded-md bg-indigo-500 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                                Generate seeders
                            </button>
                        </div>
                    </form>

                    <form method="post" action="{{ route('relix.seed') }}" class="rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                        @csrf
                        <div class="mb-3 text-xs font-medium text-slate-200">Seed database</div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex flex-col items-start gap-3">
                                <label class="text-xs text-slate-300">
                                    Count
                                    <input name="count" value="{{ old('count', $defaultCount) }}" class="mt-1 w-24 rounded-md border border-slate-700 bg-slate-950 px-2 py-1 font-mono text-sm text-slate-100 shadow-sm outline-none ring-1 ring-transparent focus:border-emerald-500 focus:ring-emerald-500/40" />
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-300">
                                    <input type="checkbox" name="truncate" value="1" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-emerald-500/40" />
                                    Truncate tables
                                </label>
                            </div>
                            <button class="rounded-md bg-emerald-500 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                                Seed now
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="mb-3">
                    <div class="text-sm font-medium text-slate-200">Schema</div>
                    <div class="text-xs text-slate-400">
                        {{ count($schema->tables) }} tables detected
                        @if ($databaseName)
                            • DB: <span class="font-mono text-slate-300">{{ $databaseName }}</span>
                        @endif
                    </div>
                </div>

                <form method="post" action="{{ route('relix.tables.exclusions.save') }}" class="space-y-4">
                    @csrf
                    <div class="space-y-4">
                        @foreach ($schema->tables as $table)
                            @php($isExcluded = in_array($table->name, $excludedTables, true))
                            <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                                <div class="mb-2 flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <div class="font-mono text-sm text-slate-100">{{ $table->name }}</div>
                                            <div class="text-xs text-slate-500">{{ count($table->columns) }} cols</div>
                                        </div>
                                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-300">
                                            <input type="checkbox" name="excluded[]" value="{{ $table->name }}" @checked($isExcluded) class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-rose-500 focus:ring-rose-500/40" />
                                            Exclude from seeding
                                        </label>
                                    </div>
                                    @if ($isExcluded)
                                        <div class="rounded-md border border-rose-500/30 bg-rose-500/10 px-2 py-1 text-xs text-rose-200">
                                            excluded
                                        </div>
                                    @endif
                                </div>
                                <div class="space-y-1">
                                    @foreach ($table->columns as $col)
                                        <div class="flex items-center justify-between gap-3 text-xs">
                                            <div class="font-mono text-slate-200">{{ $col->name }}</div>
                                            <div class="text-slate-400">
                                                <span class="font-mono">{{ $col->type }}</span>
                                                @if ($col->nullable) <span class="text-slate-500">nullable</span> @endif
                                                @if ($col->foreignKey) <span class="text-indigo-300">fk</span> @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-end">
                        <button class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-100 shadow-sm hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400/20">
                            Save exclusions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function setButtonState(button, label) {
                const original = button.getAttribute('data-relix-label') || button.textContent;
                if (!button.getAttribute('data-relix-label')) {
                    button.setAttribute('data-relix-label', original);
                }
                button.textContent = label;
                window.clearTimeout(button.__relixTimer);
                button.__relixTimer = window.setTimeout(() => {
                    button.textContent = original;
                }, 1200);
            }

            async function copyText(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    return;
                }

                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            document.addEventListener('click', async (e) => {
                const button = e.target.closest('[data-relix-copy]');
                if (!button) return;
                const targetId = button.getAttribute('data-relix-copy');
                const target = document.getElementById(targetId);
                if (!target) return;

                try {
                    await copyText(target.value ?? target.textContent ?? '');
                    setButtonState(button, 'Copied');
                } catch (err) {
                    setButtonState(button, 'Failed');
                }
            });
        })();
    </script>
</x-relix::layout>
