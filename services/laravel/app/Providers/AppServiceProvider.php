<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

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

        // Auto-migrate and seed in local/dev
        if (app()->environment('local', 'development')) {
            $this->call(\Database\Seeders\DatabaseSeeder::class);
        }
        
        // Rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
