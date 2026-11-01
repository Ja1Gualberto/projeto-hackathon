<?php

namespace App\Http\Requests;

use App\Enums\Area;
use App\Enums\TipoCandidatoVaga;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de criação e edição de vaga (data-model.md, FR-015/FR-016).
 */
class SalvarVagaRequest extends FormRequest
{
    public const MENSAGEM_SEM_COMPETENCIAS = 'Selecione ao menos uma competência para a vaga.';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O formulário envia um select por competência, `competencias[ID][peso]` (ou `competencias[ID]`),
     * com a opção vazia "Não usar". Vira uma lista `{id, peso}` sem as não usadas, mantendo o id
     * como chave para cada erro apontar o select certo (`competencias.ID.peso`). Itens que já
     * chegam como `{id, peso}` são mantidos como vieram.
     */
    protected function prepareForValidation(): void
    {
        $competencias = $this->input('competencias');

        if (! is_array($competencias)) {
            return;
        }

        $normalizadas = [];

        foreach ($competencias as $chave => $valor) {
            $peso = is_array($valor) ? ($valor['peso'] ?? null) : $valor;

            if ($peso === null || $peso === '') {
                continue;
            }

            $normalizadas[$chave] = [
                'id' => is_array($valor) && array_key_exists('id', $valor) ? $valor['id'] : $chave,
                'peso' => $peso,
            ];
        }

        $this->merge(['competencias' => $normalizadas]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:120'],
            'area' => ['required', Rule::enum(Area::class)],
            'tipo_candidato' => ['required', Rule::enum(TipoCandidatoVaga::class)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'competencias' => ['required', 'array', 'min:1'],
            'competencias.*.id' => ['required', 'integer', 'distinct', 'exists:competencias,id'],
            'competencias.*.peso' => ['required', 'in:1,2,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'competencias.required' => self::MENSAGEM_SEM_COMPETENCIAS,
            'competencias.array' => self::MENSAGEM_SEM_COMPETENCIAS,
            'competencias.min' => self::MENSAGEM_SEM_COMPETENCIAS,
            'competencias.*.peso.in' => 'Escolha o peso 1 (desejável), 2 (importante) ou 3 (essencial).',
            'competencias.*.id.distinct' => 'A mesma competência foi escolhida mais de uma vez.',
            'competencias.*.id.exists' => 'Escolha competências do catálogo.',
            'competencias.*.id.integer' => 'Escolha competências do catálogo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_candidato' => 'tipo de candidato',
            'descricao' => 'descrição',
            'competencias.*.id' => 'competência',
            'competencias.*.peso' => 'peso',
        ];
    }

    /**
     * @return array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: ?string}
     */
    public function dadosVaga(): array
    {
        return [
            'titulo' => $this->validated('titulo'),
            'area' => Area::from($this->validated('area')),
            'tipo_candidato' => TipoCandidatoVaga::from($this->validated('tipo_candidato')),
            'descricao' => $this->validated('descricao'),
        ];
    }

    /**
     * @return array<int, int> Id da competência => peso.
     */
    public function competenciasComPeso(): array
    {
        return collect($this->validated('competencias'))
            ->mapWithKeys(fn (array $competencia): array => [(int) $competencia['id'] => (int) $competencia['peso']])
            ->all();
    }
}
