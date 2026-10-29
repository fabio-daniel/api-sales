<?php

namespace App\Providers;

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
    public function boot(): void
    {
        // Algunos hostings usan serialize_precision=17, lo que convierte 150.53 en
        // 150.530000000000001136868377216160297393798828125 dentro del JSON.
        // Con -1 PHP devuelve el decimal más corto que representa el número (150.53).
        ini_set('serialize_precision', '-1');
    }
}
