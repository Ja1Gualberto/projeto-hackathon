<?php

namespace App\Http\Requests;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\Area;
use App\Enums\TipoCandidato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarCandidatosRequest extends FormRequest
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
            'competencias' => ['nullable', 'array'],
            'competencias.*' => ['integer', 'exists:competencias,id'],
            'tipo' => ['nullable', Rule::enum(TipoCandidato::class)],
            'area' => ['nullable', Rule::enum(Area::class)],
            'experiencia' => ['nullable', 'boolean'],
            'busca' => ['nullable', 'string', 'max:100'],
            'situacao' => ['nullable', 'in:ativos,desativados,retencao'],
        ];
    }

    public function filtro(): FiltroCandidatosDTO
    {
        return FiltroCandidatosDTO::fromArray($this->validated());
    }
}
