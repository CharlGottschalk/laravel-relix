# Laravel Relix

Generate Laravel seeders from your existing database schema by producing a rules JSON file—typically generated from an LLM prompt (via the UI or CLI) and then used by Relix to generate seeder classes or seed directly.

> [!WARNING]
> Laravel Relix is currently in **beta**. Expect breaking changes and unfinished features.

## Table of contents

- [Install](#install)
- [Rules format (JSON)](#rules-format-json)
- [Commands](#commands)
- [Using the generated `RelixDatabaseSeeder`](#using-the-generated-relixdatabaseseeder)
- [UI](#ui)
- [LLM (optional)](#llm-optional)
- [Eloquent factories (optional)](#eloquent-factories-optional)
- [Config](#config)
- [Tailwind setup](#tailwind-setup)

## Install

```bash
composer require charlgottschalk/laravel-relix
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=relix-config
```

This publishes package assets to `public/vendor/relix` (optional; the UI will still display the built-in logo without publishing).

## Rules format (JSON)

Relix requires a rules file, which cannot be empty (define at least one table under `tables`).

Rules are stored at `storage/app/relix/rules.json` (or `RELIX_RULES_PATH`).

Example:

```json
{
  "version": 1,
  "exclude_tables": ["migrations"],
  "tables": {
    "users": {
      "count": 50,
      "columns": {
        "email": { "strategy": "faker", "method": "safeEmail", "unique": true },
        "password": { "strategy": "hash", "value": "password" },
        "role": { "strategy": "literal", "value": "user" },
        "organization_id": { "strategy": "fk", "table": "organizations", "column": "id"}
      }
    }
  }
}
```

## Commands

- Print the LLM prompt (copy/paste into your provider):
  - `php artisan relix:llm-prompt`
  - Optional: `--exclude=cache,cache_locks` - or add to config `ignore_tables`
- Generate rules JSON from your database schema (using configured LLM):
  - `php artisan relix:llm-rules`
  - Optional: `--exclude=cache,cache_locks` - or add to config `ignore_tables`
- Generate missing Eloquent factories:
  - `php artisan relix:generate-factories`
- Generate seeder classes:
  - `php artisan relix:generate-seeders --count=25`
  - Add `--factories` to generate missing factories first
  - Output defaults to `database/seeders/Relix`
- Seed directly (no files generated):
  - `php artisan relix:seed --count=25`
  - Add `--truncate` to wipe tables first (driver-specific behavior)

## Using the generated `RelixDatabaseSeeder`

After generating seeders, Relix also generates `Database\\Seeders\\Relix\\RelixDatabaseSeeder`.

To run it from your main `DatabaseSeeder`, add:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Relix\RelixDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RelixDatabaseSeeder::class);
    }
}
```

## UI

Enabled by default in `local` only.

- Visit `/{RELIX_UI_PATH}` (default: `/relix`)
- Save rules JSON, generate seeders, or seed immediately
  - Exclude tables
  - Optional: generate rules via LLM if enabled

![Relix UI](ui.jpg)

Config: `config/relix.php`

## LLM (optional)
 Generate rules JSON from your database schema using a Large Language Model (LLM).

> [!NOTE]
> Currently only OpenAi is supported as LLM provider.

Relix supports both workflows:
- Copy the prompt and paste rules JSON manually (default)
- Call an LLM provider to generate rules JSON

Prompt:

```bash
php artisan relix:llm-prompt
```

Env:

```bash
RELIX_LLM_ENABLED=true
RELIX_LLM_PROVIDER=openai
OPENAI_API_KEY=...
```

Optional:

```bash
RELIX_OPENAI_MODEL=gpt-4o-mini
RELIX_OPENAI_BASE_URL=https://api.openai.com/v1
```

## Eloquent factories (optional)

If `RELIX_ELOQUENT_ENABLED=true` and a table has a matching Eloquent model with a factory, Relix will prefer seeding via `Model::factory()` (unless you have explicit rules for that table).

## Config

Relix config lives in `config/relix.php` (publish with `php artisan vendor:publish --tag=relix-config`).

- `connection` (`DB_CONNECTION`): Database connection name to introspect/seed; defaults to Laravel’s default connection.
- `rules_path` (`RELIX_RULES_PATH`): Where rules JSON is stored; defaults to `storage/app/relix/rules.json`.
- `ignore_tables` (`RELIX_IGNORE_TABLES`): Comma-separated tables to always exclude from prompt generation, seeding, and seeder generation. This is applied in addition to any `exclude_tables` in your rules file. Defaults include typical framework tables like `migrations`, `cache`, `sessions`, etc.

**Defaults**
- `defaults.count` (`RELIX_DEFAULT_COUNT`): Default rows per table when a table has no `count` in rules.
- `defaults.chunk_size` (`RELIX_CHUNK_SIZE`): Batch insert size when inserting rows directly (non-factory seeding).

**UI**
- `ui.enabled` (`RELIX_UI_ENABLED`): Enable/disable the UI routes.
- `ui.path` (`RELIX_UI_PATH`): URL prefix for the UI (default `relix`).
- `ui.middleware`: Middleware stack for UI routes (default `web`).
- `ui.require_local` (`RELIX_UI_REQUIRE_LOCAL`): Restrict UI access to local environment.
- `ui.tailwind_cdn` (`RELIX_UI_TAILWIND_CDN`): Load Tailwind via CDN (convenient for local).
- `ui.tailwind_cdn_url` (`RELIX_UI_TAILWIND_CDN_URL`): Tailwind CDN URL.

**LLM**
- `llm.enabled` (`RELIX_LLM_ENABLED`): Enable/disable LLM features (currently OpenAI).
- `llm.provider` (`RELIX_LLM_PROVIDER`): LLM provider name (currently only `openai` is supported).
- `llm.timeout` (`RELIX_LLM_TIMEOUT`): HTTP timeout (seconds) for LLM requests.
- `llm.openai.api_key` (`OPENAI_API_KEY` or `RELIX_OPENAI_API_KEY`): API key used for OpenAI requests.
- `llm.openai.base_url` (`OPENAI_BASE_URL` or `RELIX_OPENAI_BASE_URL`): Base URL for OpenAI API.
- `llm.openai.model` (`RELIX_OPENAI_MODEL` or `OPENAI_MODEL`): Model name for rules generation.

**Eloquent**
- `eloquent.enabled` (`RELIX_ELOQUENT_ENABLED`): Enable seeding via Eloquent factories when possible.
- `eloquent.prefer_factories` (`RELIX_PREFER_FACTORIES`): Prefer factories when no explicit table rules exist.
- `eloquent.generate_factories` (`RELIX_GENERATE_FACTORIES`): Auto-generate missing factories when generating seeders (if supported by your schema/models).

## Tailwind setup

Relix views use Tailwind utility classes.

By default, the Relix UI will load Tailwind via CDN for convenience in local/dev:

```bash
RELIX_UI_TAILWIND_CDN=true
```

If you want to use your app's compiled Tailwind instead, disable the CDN and ensure your Tailwind build includes the vendor views:

```bash
RELIX_UI_TAILWIND_CDN=false
```

Then add this to your `tailwind.config.js` `content`:

```js
content: [
  "./vendor/charlgottschalk/laravel-relix/resources/views/**/*.blade.php",
],
```
