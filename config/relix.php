<?php

return [
    'connection' => env('DB_CONNECTION', null),

    'ignore_tables' => array_filter(array_map('trim', explode(',', env('RELIX_IGNORE_TABLES', 'migrations,failed_jobs,password_reset_tokens,cache,cache_locks,sessions,jobs,job_batches')))),

    'defaults' => [
        'count' => (int) env('RELIX_DEFAULT_COUNT', 25),
        'chunk_size' => (int) env('RELIX_CHUNK_SIZE', 250),
    ],

    'rules_path' => env('RELIX_RULES_PATH', null),

    'ui' => [
        'enabled' => env('RELIX_UI_ENABLED', true),
        'path' => env('RELIX_UI_PATH', 'relix'),
        'middleware' => ['web'],
        'require_local' => env('RELIX_UI_REQUIRE_LOCAL', true),
        'tailwind_cdn' => env('RELIX_UI_TAILWIND_CDN', true),
        'tailwind_cdn_url' => env('RELIX_UI_TAILWIND_CDN_URL', 'https://cdn.tailwindcss.com'),
    ],

    'llm' => [
        'enabled' => env('RELIX_LLM_ENABLED', false),
        'provider' => env('RELIX_LLM_PROVIDER', 'openai'),
        'timeout' => (int) env('RELIX_LLM_TIMEOUT', 60),

        'openai' => [
            'api_key' => env('OPENAI_API_KEY', env('RELIX_OPENAI_API_KEY')),
            'base_url' => env('OPENAI_BASE_URL', env('RELIX_OPENAI_BASE_URL', 'https://api.openai.com/v1')),
            'model' => env('RELIX_OPENAI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),
        ],
    ],

    'eloquent' => [
        'enabled' => env('RELIX_ELOQUENT_ENABLED', true),
        'prefer_factories' => env('RELIX_PREFER_FACTORIES', true),
        'generate_factories' => env('RELIX_GENERATE_FACTORIES', false),
    ],
];
