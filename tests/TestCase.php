<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase apaga todas as tabelas: a suíte só pode rodar no banco em memória.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $conexao = $app['config']['database.default'];

        if ($conexao !== 'sqlite' || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new RuntimeException("Testes recusados: a conexão \"{$conexao}\" não é o SQLite em memória e seria apagada.");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // As views usam @vite; os testes não dependem dos assets compilados.
        $this->withoutVite();
    }
}
