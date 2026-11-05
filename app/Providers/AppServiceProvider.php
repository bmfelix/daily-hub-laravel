<?php

namespace App\Providers;

use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every page needs the module list for the menu.
        View::composer('*', fn ($view) => $view->with('menuModules', array_filter(
            $this->app->make(ModuleRegistry::class)->all(),
            fn ($module) => $module->inMenu,
        )));
    }
}
