<?php

namespace App\Http\Requests;

use App\Enums\TipoCandidato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportarCurriculoRequest extends FormRequest
{
    private const MENSAGEM_ARQUIVO = 'Envie um arquivo PDF de até 10 MB.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:'.config('banco_talentos.upload_max_kb')],
            'tipo' => ['required', Rule::enum(TipoCandidato::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'Selecione o currículo em PDF.',
            'arquivo.file' => self::MENSAGEM_ARQUIVO,
            'arquivo.uploaded' => self::MENSAGEM_ARQUIVO,
            'arquivo.mimes' => self::MENSAGEM_ARQUIVO,
            'arquivo.max' => self::MENSAGEM_ARQUIVO,
            'tipo.required' => 'Informe se o candidato é servidor ou estagiário.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de candidato',
        ];
    }
}
