<?php

namespace OVAC\IDoc;

class IDocLumenServiceProvider extends IDocServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
        $this->registerPublishing();

        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'idoc');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'idoc');

        if ($this->app->runningInConsole()) {
            $this->commands([
                IDocGeneratorCommand::class,
            ]);
        }
    }

    protected function registerRoutes()
    {
        app()->router->group($this->routeConfiguration(), function ($router) {
            require __DIR__ . '/../../resources/routes/lumen.php';
        });
    }

    protected function routeConfiguration()
    {
        return [
            'domain' => config('idoc.domain'),
            'prefix' => config('idoc.path'),
            'middleware' => config('idoc.middleware', []),
            'as' => 'idoc',
        ];
    }
}
