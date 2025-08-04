<?php

namespace App\Providers;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ClientMiddleware;
use App\Http\Middleware\EditorMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('admin', AdminMiddleware::class);
        $router->aliasMiddleware('client', ClientMiddleware::class);
        $router->aliasMiddleware('editor', EditorMiddleware::class);
    }
}
