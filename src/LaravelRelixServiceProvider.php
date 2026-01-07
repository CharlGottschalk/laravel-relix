<?php

namespace CharlGottschalk\LaravelRelix;

use CharlGottschalk\LaravelRelix\Commands\GenerateSeedersCommand;
use CharlGottschalk\LaravelRelix\Commands\GenerateFactoriesCommand;
use CharlGottschalk\LaravelRelix\Commands\GenerateRulesWithLlmCommand;
use CharlGottschalk\LaravelRelix\Commands\PrintLlmPromptCommand;
use CharlGottschalk\LaravelRelix\Commands\SeedDatabaseCommand;
use CharlGottschalk\LaravelRelix\Eloquent\FactoryGenerator;
use CharlGottschalk\LaravelRelix\Eloquent\ModelRegistry;
use CharlGottschalk\LaravelRelix\Llm\LlmClient;
use CharlGottschalk\LaravelRelix\Llm\NullLlmClient;
use CharlGottschalk\LaravelRelix\Llm\OpenAiClient;
use CharlGottschalk\LaravelRelix\Rules\RulesRepository;
use CharlGottschalk\LaravelRelix\Seeder\SeederRunner;
use Illuminate\Support\ServiceProvider;

class LaravelRelixServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/relix.php', 'relix');

        $this->app->singleton(RelixManager::class, function ($app) {
            return new RelixManager(
                $app['config'],
                $app['files'],
            );
        });

        $this->app->bind(RulesRepository::class, function ($app) {
            return new RulesRepository($app['config'], $app['files']);
        });

        $this->app->singleton(ModelRegistry::class, function ($app) {
            return new ModelRegistry($app['files']);
        });

        $this->app->bind(FactoryGenerator::class, function ($app) {
            return new FactoryGenerator($app['files'], $app->make(ModelRegistry::class));
        });

        $this->app->bind(SeederRunner::class, function ($app) {
            $connectionName = $app['config']->get('relix.connection');
            $connection = $app['db']->connection($connectionName);

            return new SeederRunner($app['config'], $connection, $app->make(ModelRegistry::class));
        });

        $this->app->bind(LlmClient::class, function ($app) {
            if (! $app['config']->get('relix.llm.enabled', false)) {
                return new NullLlmClient('Relix LLM is disabled. Set RELIX_LLM_ENABLED=true.');
            }

            $provider = $app['config']->get('relix.llm.provider', 'openai');

            if ($provider === 'openai') {
                return new OpenAiClient($app['config']);
            }

            return new NullLlmClient('Unsupported Relix LLM provider: ' . (string) $provider);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'relix');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->publishes([
            __DIR__ . '/../config/relix.php' => config_path('relix.php'),
        ], 'relix-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/relix'),
        ], 'relix-views');

        $this->publishes([
            __DIR__ . '/../resources/assets' => public_path('vendor/relix'),
        ], 'relix-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFactoriesCommand::class,
                GenerateSeedersCommand::class,
                GenerateRulesWithLlmCommand::class,
                PrintLlmPromptCommand::class,
                SeedDatabaseCommand::class,
            ]);
        }
    }
}
