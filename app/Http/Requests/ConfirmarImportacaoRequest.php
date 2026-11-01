<?php

namespace App\Http\Requests;

use App\Enums\Area;
use App\Enums\MeioConsentimento;
use App\Enums\TipoCandidato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da revisão de um currículo importado (data-model.md, FR-010 e FR-011).
 */
class ConfirmarImportacaoRequest extends FormRequest
{
    public const MENSAGEM_CONSENTIMENTO = 'Registre como e quando o candidato consentiu com o uso dos dados.';

    /** Textareas com um item por linha. */
    private const LISTAS = ['formacoes', 'experiencias', 'projetos', 'links'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach (self::LISTAS as $campo) {
            $valor = $this->input($campo);

            if ($valor === null || is_string($valor)) {
                $linhas = preg_split('/\R/u', (string) $valor) ?: [];
                $normalizados[$campo] = array_values(array_filter(array_map('trim', $linhas), fn (string $linha): bool => $linha !== ''));
            }
        }

        $cpf = $this->input('cpf');

        $this->merge([
            ...$normalizados,
            'cpf' => is_string($cpf) && trim($cpf) !== '' ? preg_replace('/\D/', '', $cpf) : null,
            'experiencia_na_area' => $this->boolean('experiencia_na_area'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'digits:11'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'tipo' => ['required', Rule::enum(TipoCandidato::class)],
            'area' => ['required', Rule::enum(Area::class)],
            'experiencia_na_area' => ['boolean'],
            'formacoes' => ['array', 'max:20'],
            'formacoes.*' => ['string', 'max:300'],
            'experiencias' => ['array', 'max:20'],
            'experiencias.*' => ['string', 'max:300'],
            'projetos' => ['array', 'max:20'],
            'projetos.*' => ['string', 'max:300'],
            'links' => ['array', 'max:10'],
            'links.*' => ['url:http,https', 'max:300'],
            'competencias' => ['nullable', 'array'],
            'competencias.*' => ['integer', 'distinct', 'exists:competencias,id'],
            'consentimento_meio' => ['required', Rule::enum(MeioConsentimento::class)],
            'consentimento_em' => ['required', 'date', 'before_or_equal:today'],
            'modo' => ['required', 'in:novo,atualizar'],
            'candidato_existente_id' => ['nullable', 'required_if:modo,atualizar', 'integer', 'exists:candidatos,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consentimento_meio.required' => self::MENSAGEM_CONSENTIMENTO,
            'consentimento_em.required' => self::MENSAGEM_CONSENTIMENTO,
            'links.*.url' => 'Informe links completos, começando com http:// ou https://.',
            'candidato_existente_id.required_if' => 'Escolha o cadastro que será atualizado.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de candidato',
            'experiencia_na_area' => 'experiência na área',
            'formacoes.*' => 'formação',
            'experiencias.*' => 'experiência',
            'projetos.*' => 'projeto',
            'links.*' => 'link',
            'competencias.*' => 'competência',
            'modo' => 'forma de cadastro',
            'candidato_existente_id' => 'cadastro existente',
        ];
    }
}
