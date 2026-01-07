<?php

use CharlGottschalk\LaravelRelix\Http\Controllers\RelixController;
use CharlGottschalk\LaravelRelix\Http\Middleware\EnsureRelixUiEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(config('relix.ui.middleware', ['web']), [EnsureRelixUiEnabled::class]))
    ->prefix(config('relix.ui.path', 'relix'))
    ->name('relix.')
    ->group(function () {
        Route::get('/', [RelixController::class, 'index'])->name('index');
        Route::post('/generate-seeders', [RelixController::class, 'generateSeeders'])->name('generate-seeders');
        Route::post('/seed', [RelixController::class, 'seed'])->name('seed');
        Route::post('/rules', [RelixController::class, 'saveRules'])->name('rules.save');
        Route::post('/llm/generate-rules', [RelixController::class, 'generateRulesWithLlm'])->name('llm.generate-rules');
        Route::post('/tables/exclusions', [RelixController::class, 'saveTableExclusions'])->name('tables.exclusions.save');
    });
