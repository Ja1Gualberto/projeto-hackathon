<?php

namespace App\Http\Requests;

use App\Enums\EtapaSelecao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Movimentação de etapa no quadro da vaga. `contexto` só escolhe uma das duas apresentações
 * autorizadas da mesma vaga (quadro ou perfil) e nunca muda permissões (contracts/rotas-web.md).
 */
class MoverEtapaRequest extends FormRequest
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
            'etapa' => ['required', Rule::enum(EtapaSelecao::class)],
            'contexto' => ['nullable', 'in:quadro,perfil'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'etapa.required' => 'Escolha a etapa de destino.',
            'etapa.enum' => 'Escolha Triagem, Entrevista ou Aprovado.',
            'contexto.in' => 'A tela de origem da movimentação é inválida.',
        ];
    }

    public function etapa(): EtapaSelecao
    {
        return EtapaSelecao::from($this->validated('etapa'));
    }

    public function vemDoPerfil(): bool
    {
        return $this->validated('contexto') === 'perfil';
    }
}
