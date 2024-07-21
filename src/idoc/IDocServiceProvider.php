<?php

namespace OVAC\IDoc;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IDocServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
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
     * Get the iDoc route group configuration array.
     *
     * @return array
     */
    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../../resources/routes/idoc.php', 'idoc');
        });
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
        }
    }

    /**
     * Get the iDoc route group configuration array.
     *
     * @return array
     */
    protected function routeConfiguration()
    {
        return [
            'domain' => config('idoc.domain', null),
            'prefix' => config('idoc.path'),
            'middleware' => 'idoc',
            'as' => 'idoc.',
        ];
    }

    /**
     * Register the API doc commands.
     *
     * @return void
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
}
