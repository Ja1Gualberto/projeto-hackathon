<?php

namespace App\Enums;

enum MotivoExclusao: string
{
    case PedidoTitular = 'pedido_titular';
    case Retencao = 'retencao';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::PedidoTitular => 'Pedido do titular',
            self::Retencao => 'Fim do prazo de retenção',
            self::Outro => 'Outro',
        };
    }
}
