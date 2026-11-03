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
            'todos_filtrados' => ['sometimes', 'boolean'],
            'candidatos' => [Rule::excludeIf($this->boolean('todos_filtrados')), 'required', 'array', 'min:1', 'max:100'],
            'candidatos.*' => ['required', 'integer', 'distinct', Rule::exists('candidatos', 'id')->where('status', 'ativo')],
            'competencias' => ['exclude_unless:todos_filtrados,1', 'nullable', 'array'],
            'competencias.*' => ['integer', 'exists:competencias,id'],
            'busca' => ['exclude_unless:todos_filtrados,1', 'nullable', 'string', 'max:100'],
            'excluidos' => ['exclude_unless:todos_filtrados,1', 'nullable', 'array', 'max:12'],
            'excluidos.*' => ['integer', 'distinct'],
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
