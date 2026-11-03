<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdicionarTriagemRequest extends FormRequest
{
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
            'candidatos' => ['required', 'array', 'min:1', 'max:100'],
            'candidatos.*' => ['required', 'integer', 'distinct', Rule::exists('candidatos', 'id')->where('status', 'ativo')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'candidatos.required' => 'Selecione ao menos um candidato para adicionar à triagem.',
            'candidatos.*.exists' => 'Selecione apenas candidatos ativos do banco de talentos.',
        ];
    }
}
