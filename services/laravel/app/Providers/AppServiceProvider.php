<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Config::set('database.connections.pgsql.username', 'diagrams');
        Config::set('database.connections.pgsql.database', 'diagrams_local');
        Config::set('database.connections.pgsql.password', 'secret');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'file');
    }
}
