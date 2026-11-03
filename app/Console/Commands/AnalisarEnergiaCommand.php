<?php

namespace App\Console\Commands;

use App\Services\Energia\AnalisadorEnergia;
use App\Services\Energia\AnalisadorEnergiaIndisponivel;
use App\Services\Energia\PerfilEnergiaService;
use Illuminate\Console\Command;

class AnalisarEnergiaCommand extends Command
{
    protected $signature = 'energia:analisar {--todos : Reanalisa também os perfis de IA, preservando os humanos}';

    protected $description = 'Sugere padrões de energia para candidatos ativos sem sobrescrever revisões humanas';

    public function handle(PerfilEnergiaService $energia, AnalisadorEnergia $analisador): int
    {
        if (! config('banco_talentos.energia.habilitada') || $analisador instanceof AnalisadorEnergiaIndisponivel) {
            $this->components->warn('Análise indisponível: IA desligada ou provedor não configurado. O restante do sistema continua funcionando.');

            return self::SUCCESS;
        }

        $candidatos = $energia->candidatosParaAnalise((bool) $this->option('todos'));
        $barra = $this->output->createProgressBar($candidatos->count());
        $analisados = 0;
        $indisponiveis = 0;
        $barra->start();
        foreach ($candidatos as $candidato) {
            if ($energia->analisar($candidato) === null) {
                $indisponiveis++;
            } else {
                $analisados++;
            }
            $barra->advance();
        }
        $barra->finish();
        $this->newLine();
        $this->components->info("{$analisados} analisados, {$indisponiveis} indisponíveis");

        return self::SUCCESS;
    }
}
