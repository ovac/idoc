<?php

namespace OVAC\IDoc;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IDocServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap package services: register routes, views, translations,
     * commands and define the iDoc middleware group.
     */
    public function boot()
    {
        // Define the named middleware group used by all iDoc routes (docs + chat)
        // Keep group definition declarative; apply removals at grouping time.
        Route::middlewareGroup('idoc', config('idoc.middleware', []));

        $this->registerRoutes();
        $this->registerPublishing();

        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'idoc');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'idoc');

        if ($this->app->runningInConsole()) {
            $this->commands([
                IDocGeneratorCommand::class,
                IDocCustomConfigGeneratorCommand::class,
            ]);
        }
    }

    /**
     * Register package HTTP routes (core docs and optional chat).
     */
    protected function registerRoutes()
    {
        $this->registerCoreRoutes();
        $this->registerChatRoutes();
    }

    /**
     * Register the core documentation routes (index + info) within the iDoc group.
     */
    protected function registerCoreRoutes(): void
    {
        // Group core docs with domain+prefix+middleware and name prefix.
        // Apply any global removals at the group level using withoutMiddleware.
        Route::domain(config('idoc.domain', null))
            ->prefix(config('idoc.path'))
            ->middleware('idoc')
            ->name('idoc.')
            ->withoutMiddleware((array) config('idoc.remove_middleware', []))
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../../resources/routes/idoc.php');
            });
    }

    /**
     * Register the optional chat route. We group it with the same domain/prefix
     * and middleware, but deliberately do not apply a name prefix so consumers
     * can use an exact custom route name via config('idoc.chat.route').
     */
    protected function registerChatRoutes(): void
    {
        if (!config('idoc.chat.enabled', false)) {
            return;
        }
        $chatRoutes = __DIR__ . '/../../resources/routes/chat.php';
        if (!is_file($chatRoutes)) {
            return;
        }
        // Use same domain/prefix, but compute an effective chat middleware list
        // based on the iDoc group minus any global and chat-specific removals.
        // This ensures aliases like 'web' that are nested inside 'idoc' are
        // truly excluded for the chat route(s).
        $chatGroup = $this->buildChatGroupMiddleware();

        Route::domain(config('idoc.domain', null))
            ->prefix(config('idoc.path'))
            ->middleware($chatGroup)
            ->group(function () use ($chatRoutes) {
                $this->loadRoutesFrom($chatRoutes);
            });
    }

    /**
     * Compute the middleware list for the chat route group by taking the
     * configured iDoc group and removing any entries listed in either
     * idoc.remove_middleware (global) or idoc.chat.remove_middleware (chat-specific).
     *
     * Notes
     * - Supports removing by alias (eg. 'web') or class name.
     * - Also supports removing by base name (before ':') so 'throttle' removes
     *   'throttle:60,1'.
     */
    protected function buildChatGroupMiddleware(): array
    {
        $base = (array) config('idoc.middleware', []);
        $remove = array_merge(
            (array) config('idoc.remove_middleware', []),
            (array) config('idoc.chat.remove_middleware', [])
        );
        // Normalize
        $remove = array_values(array_filter(array_map(function ($v) {
            return ltrim(strtolower((string) $v), '\\');
        }, $remove)));

        $filtered = [];
        foreach ($base as $mw) {
            $full = ltrim(strtolower((string) $mw), '\\');
            $baseName = strstr($full, ':', true) ?: $full;
            // Exclude if exact match or base-name match
            if (in_array($full, $remove, true) || in_array($baseName, $remove, true)) {
                continue;
            }
            $filtered[] = $mw;
        }
        return $filtered;
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/lang' => $this->resourcePath('lang/vendor/idoc'),
            ], 'idoc-language');

            $this->publishes([
                __DIR__ . '/../../resources/views' => $this->resourcePath('views/vendor/idoc'),
            ], 'idoc-views');

            $this->publishes([
                __DIR__ . '/../../config/idoc.php' => app()->basePath() . '/config/idoc.php',
            ], 'idoc-config');

            // Optionally allow publishing of package routes for customization
            $this->publishes([
                __DIR__ . '/../../resources/routes' => app()->basePath() . '/routes/vendor/idoc',
            ], 'idoc-routes');

            // Publish default prompts so apps can customize the chat system prompt
            $this->publishes([
                __DIR__ . '/../../resources/prompts' => app()->basePath() . '/resources/vendor/idoc/prompts',
            ], 'idoc-prompts');
        }
    }

    // Note: we use the fluent Route API for grouping rather than returning
    // an array of options, so we can call withoutMiddleware() at group time
    // and keep all logic in one readable place.

    /**
     * Register the API doc commands and merge package config.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/idoc.php', 'idoc');
    }

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public function resourcePath($path = '')
    {
        return app()->basePath() . '/resources' . ($path ? '/' . $path : $path);
    }

    // Helpers removed: we now apply global removals at group time with
    // ->withoutMiddleware(config('idoc.remove_middleware')), and chat-specific
    // removals at the route level inside resources/routes/chat.php.
}
