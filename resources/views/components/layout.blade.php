<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $title ?? 'Relix' }}</title>

    @if (config('relix.ui.tailwind_cdn', true))
        <script>
            tailwind = {
                config: {
                    darkMode: 'class',
                }
            }
        </script>
        <script src="{{ config('relix.ui.tailwind_cdn_url', 'https://cdn.tailwindcss.com') }}"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<div class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <img src="{{ asset('vendor/relix/logo.png') }}" alt="Relix logo" class="h-20 w-auto" />
        </div>
        <div class="text-xs text-slate-400">
            Connection: <span class="font-mono">{{ $connectionName }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200 shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200 shadow-sm">
            <div class="font-medium">Fix the following:</div>
            <ul class="mt-2 list-disc pl-6">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{ $slot }}
</div>
</body>
</html>
