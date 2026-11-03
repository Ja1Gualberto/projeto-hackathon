<?php

namespace App\Http\Requests;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\Area;
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
            'vaga_id' => ['nullable', 'integer', 'exists:vagas,id'],
            'area' => ['nullable', Rule::enum(Area::class)],
            'experiencia' => ['nullable', 'boolean'],
            'busca' => ['nullable', 'string', 'max:100'],
            'situacao' => ['nullable', 'in:ativos,desativados,retencao'],
            'por_pagina' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ];
    }

    public function filtro(): FiltroCandidatosDTO
    {
        return FiltroCandidatosDTO::fromArray($this->validated());
    }
}
