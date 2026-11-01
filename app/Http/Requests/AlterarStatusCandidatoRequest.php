<?php

namespace App\Http\Requests;

use App\Enums\StatusCandidato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlterarStatusCandidatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(StatusCandidato::class)]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status' => 'situação do candidato'];
    }
}
