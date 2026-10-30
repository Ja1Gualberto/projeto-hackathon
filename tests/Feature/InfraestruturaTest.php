<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InfraestruturaTest extends TestCase
{
    public function test_usa_limites_do_banco_de_talentos_com_ia_desligada(): void
    {
        $this->assertSame(2, config('banco_talentos.retencao_anos'));
        $this->assertSame(10240, config('banco_talentos.upload_max_kb'));
        $this->assertSame(80, config('banco_talentos.minimo_caracteres_texto'));
        $this->assertFalse(config('banco_talentos.energia.habilitada'));
        $this->assertSame(20, config('banco_talentos.energia.timeout'));
    }

    public function test_resolve_curriculos_fora_da_pasta_publica_sem_urls_temporarias(): void
    {
        $disk = Storage::disk('curriculos');

        $this->assertSame(storage_path('app/private/curriculos/curriculo.pdf'), $disk->path('curriculo.pdf'));
        $this->assertSame('private', $disk->getConfig()['visibility']);
        $this->assertFalse($disk->providesTemporaryUrls());
        $this->assertNull($this->app['router']->getRoutes()->getByName('storage.curriculos'));
    }

    public function test_traduz_campos_obrigatorios_e_atributos_para_portugues(): void
    {
        $validator = Validator::make([], [
            'nome' => ['required'],
            'arquivo' => ['required'],
            'consentimento_em' => ['required'],
        ]);

        $this->assertSame('O campo nome é obrigatório.', $validator->errors()->first('nome'));
        $this->assertSame('O campo currículo em PDF é obrigatório.', $validator->errors()->first('arquivo'));
        $this->assertSame('O campo data do consentimento é obrigatório.', $validator->errors()->first('consentimento_em'));
    }

    public function test_traduz_limites_de_texto_listas_numeros_e_arquivos(): void
    {
        $validator = Validator::make([
            'titulo' => 'Título extenso',
            'competencias' => [1, 2],
            'peso' => 4,
            'arquivo' => UploadedFile::fake()->create('curriculo.pdf', 10241, 'application/pdf'),
        ], [
            'titulo' => ['string', 'max:5'],
            'competencias' => ['array', 'max:1'],
            'peso' => ['integer', 'between:1,3'],
            'arquivo' => ['file', 'max:10240'],
        ]);

        $this->assertSame('O campo título não pode ter mais de 5 caracteres.', $validator->errors()->first('titulo'));
        $this->assertSame('O campo competências não pode ter mais de 1 itens.', $validator->errors()->first('competencias'));
        $this->assertSame('O campo peso deve estar entre 1 e 3.', $validator->errors()->first('peso'));
        $this->assertSame('O campo currículo em PDF não pode ser maior que 10240 kilobytes.', $validator->errors()->first('arquivo'));
    }
}
