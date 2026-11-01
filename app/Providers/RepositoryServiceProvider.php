<?php

namespace App\Providers;

use App\Repositories\AnotacaoRepository;
use App\Repositories\CandidatoRepository;
use App\Repositories\CompetenciaRepository;
use App\Repositories\Interfaces\AnotacaoRepositoryInterface;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use App\Repositories\Interfaces\PainelRepositoryInterface;
use App\Repositories\Interfaces\ParticipacaoRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Repositories\PainelRepository;
use App\Repositories\ParticipacaoRepository;
use App\Repositories\VagaRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CandidatoRepositoryInterface::class, CandidatoRepository::class);
        $this->app->bind(CompetenciaRepositoryInterface::class, CompetenciaRepository::class);
        $this->app->bind(PainelRepositoryInterface::class, PainelRepository::class);
        $this->app->bind(VagaRepositoryInterface::class, VagaRepository::class);
        $this->app->bind(ParticipacaoRepositoryInterface::class, ParticipacaoRepository::class);
        $this->app->bind(AnotacaoRepositoryInterface::class, AnotacaoRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
