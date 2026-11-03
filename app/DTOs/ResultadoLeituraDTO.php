<?php

namespace App\DTOs;

/**
 * Resultado da leitura do texto de um PDF (contracts/extracao-curriculo.md).
 */
final readonly class ResultadoLeituraDTO
{
    public const LIDO = 'lido';

    public const SEM_TEXTO = 'sem_texto';

    public const CORROMPIDO = 'corrompido';

    /**
     * @param  'lido'|'sem_texto'|'corrompido'  $situacao
     * @param  ?string  $destaque  Texto de maior fonte da primeira página, candidato ao nome.
     */
    public function __construct(
        public string $situacao,
        public ?string $texto = null,
        public ?string $destaque = null,
    ) {}

    public static function lido(string $texto, ?string $destaque = null): self
    {
        return new self(self::LIDO, $texto, $destaque);
    }

    public static function semTexto(): self
    {
        return new self(self::SEM_TEXTO);
    }

    public static function corrompido(): self
    {
        return new self(self::CORROMPIDO);
    }

    public function foiLido(): bool
    {
        return $this->situacao === self::LIDO;
    }
}
