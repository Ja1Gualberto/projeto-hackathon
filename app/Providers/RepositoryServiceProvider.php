<?php

namespace App\Providers;

use App\Repositories\CandidatoRepository;
use App\Repositories\CompetenciaRepository;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use App\Repositories\Interfaces\PainelRepositoryInterface;
use App\Repositories\PainelRepository;
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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
