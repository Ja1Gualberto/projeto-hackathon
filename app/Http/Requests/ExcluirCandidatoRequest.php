<?php

namespace App\Http\Requests;

use App\Enums\MotivoExclusao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExcluirCandidatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', Rule::enum(MotivoExclusao::class)],
            'confirmacao' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirmacao.accepted' => 'Confirme que deseja excluir definitivamente os dados do candidato.'];
    }
}
