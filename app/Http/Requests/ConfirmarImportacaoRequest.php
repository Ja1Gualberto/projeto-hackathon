<?php

namespace App\Http\Requests;

/** Revisão do currículo importado: mesmos dados da correção, mais o modo de cadastro. */
class ConfirmarImportacaoRequest extends AtualizarCandidatoRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'modo' => ['required', 'in:novo,atualizar'],
            'candidato_existente_id' => ['nullable', 'required_if:modo,atualizar', 'integer', 'exists:candidatos,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'candidato_existente_id.required_if' => 'Escolha o cadastro que será atualizado.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'modo' => 'forma de cadastro',
            'candidato_existente_id' => 'cadastro existente',
        ];
    }
}
