<?php

namespace App\Http\Requests;

use App\Enums\MeioConsentimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cadastro sem revisão: um único registro de consentimento vale para todo o lote. */
class CadastrarLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'consentimento_meio' => ['required', Rule::enum(MeioConsentimento::class)],
            'consentimento_em' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'consentimento_meio.required' => AtualizarCandidatoRequest::MENSAGEM_CONSENTIMENTO,
            'consentimento_em.required' => AtualizarCandidatoRequest::MENSAGEM_CONSENTIMENTO,
        ];
    }
}
