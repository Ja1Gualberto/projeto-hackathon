<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustarPerfilEnergiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'dinamico_analitico' => ['required', 'integer', 'between:1,5'],
            'individual_equipe' => ['required', 'integer', 'between:1,5'],
            'execucao_planejamento' => ['required', 'integer', 'between:1,5'],
            'justificativa' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array{dinamico_analitico: int, individual_equipe: int, execucao_planejamento: int} */
    public function eixos(): array
    {
        return [
            'dinamico_analitico' => (int) $this->validated('dinamico_analitico'),
            'individual_equipe' => (int) $this->validated('individual_equipe'),
            'execucao_planejamento' => (int) $this->validated('execucao_planejamento'),
        ];
    }
}
