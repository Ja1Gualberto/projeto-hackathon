<?php

namespace App\Http\Requests;

use App\Enums\CategoriaCompetencia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriarCompetenciaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nome'))) {
            $this->merge(['nome' => trim((string) preg_replace('/\s+/u', ' ', $this->input('nome')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:80', 'unique:competencias,nome'],
            'categoria' => ['required', Rule::enum(CategoriaCompetencia::class)],
            'importacao' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.unique' => 'Esta competência já está no catálogo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'competência',
            'categoria' => 'categoria',
        ];
    }
}
