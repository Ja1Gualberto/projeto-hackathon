<?php

namespace App\Http\Requests;

use App\Enums\EtapaSelecao;
use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anotação de entrevista, pela gestão do banco ou pelo perfil na vaga (data-model.md).
 */
class RegistrarAnotacaoRequest extends FormRequest
{
    /**
     * Pela vaga, só com a identidade revelada nesta vaga: em triagem responde 403 antes de validar,
     * inclusive em requisições montadas à mão (contracts/visibilidade-por-contexto.md).
     */
    public function authorize(VisibilidadeCandidatoService $visibilidade): bool
    {
        $vaga = $this->route('vaga');
        $candidato = $this->route('candidato');

        return ! $vaga instanceof Vaga
            || ($candidato instanceof Candidato && $visibilidade->podeRevelar($vaga, $candidato));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:5000'],
            'vaga_id' => ['nullable', 'integer', 'exists:vagas,id'],
            'competencias_observadas' => ['nullable', 'array'],
            'competencias_observadas.*' => ['integer', 'distinct', 'exists:competencias,id'],
            'contexto' => ['nullable', 'in:perfil,quadro'],
            'etapa' => [
                'nullable',
                Rule::prohibitedIf(! $this->route('vaga') instanceof Vaga || $this->input('contexto') !== 'quadro'),
                Rule::enum(EtapaSelecao::class)->only([EtapaSelecao::Aprovado, EtapaSelecao::Reprovado]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'texto.required' => 'Escreva a anotação antes de registrar.',
            'texto.max' => 'A anotação pode ter até 5000 caracteres.',
            'contexto.in' => 'A tela de origem da anotação é inválida.',
            'etapa.enum' => 'Escolha manter em entrevista, aprovar ou reprovar.',
            'etapa.prohibited' => 'A mudança de etapa pela anotação só está disponível no quadro da vaga.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vaga_id' => 'vaga',
            'competencias_observadas' => 'competências observadas',
            'competencias_observadas.*' => 'competência observada',
        ];
    }

    /**
     * Vaga escolhida no formulário do banco. Pela vaga, o controller usa a vaga da rota.
     */
    public function vagaId(): ?int
    {
        $vagaId = $this->validated('vaga_id');

        return $vagaId === null ? null : (int) $vagaId;
    }

    /**
     * @return list<int>
     */
    public function competenciasObservadas(): array
    {
        return array_values(array_map('intval', $this->validated('competencias_observadas') ?? []));
    }

    public function vemDoQuadro(): bool
    {
        return $this->validated('contexto') === 'quadro';
    }

    public function etapa(): ?EtapaSelecao
    {
        $etapa = $this->validated('etapa');

        return $etapa === null ? null : EtapaSelecao::from($etapa);
    }
}
