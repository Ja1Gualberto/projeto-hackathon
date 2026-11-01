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
     */
    public function __construct(
        public string $situacao,
        public ?string $texto = null,
    ) {}

    public static function lido(string $texto): self
    {
        return new self(self::LIDO, $texto);
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
