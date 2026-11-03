<?php

namespace App\Providers;

use App\Services\Energia\AnalisadorEnergia;
use App\Services\Energia\AnalisadorEnergiaIndisponivel;
use App\Services\Energia\ClaudeAnalisadorEnergia;
use App\Services\Energia\OllamaAnalisadorEnergia;
use App\Services\Energia\OpenAiAnalisadorEnergia;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnalisadorEnergia::class, function (): AnalisadorEnergia {
            if (! config('banco_talentos.energia.habilitada')) {
                return new AnalisadorEnergiaIndisponivel;
            }

            return match (config('banco_talentos.energia.provedor')) {
                'ollama' => filled(config('services.ollama.url')) && filled(config('services.ollama.model')) ? new OllamaAnalisadorEnergia : new AnalisadorEnergiaIndisponivel,
                'openai' => filled(config('services.openai.key')) ? new OpenAiAnalisadorEnergia : new AnalisadorEnergiaIndisponivel,
                'anthropic' => filled(config('services.anthropic.key')) ? new ClaudeAnalisadorEnergia : new AnalisadorEnergiaIndisponivel,
                default => new AnalisadorEnergiaIndisponivel,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
