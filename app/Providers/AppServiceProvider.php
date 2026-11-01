<?php

namespace App\Providers;

use App\Services\Energia\AnalisadorEnergia;
use App\Services\Energia\AnalisadorEnergiaIndisponivel;
use App\Services\Energia\ClaudeAnalisadorEnergia;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnalisadorEnergia::class, fn (): AnalisadorEnergia => config('banco_talentos.energia.habilitada') && filled(config('services.anthropic.key'))
                ? new ClaudeAnalisadorEnergia
                : new AnalisadorEnergiaIndisponivel);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
