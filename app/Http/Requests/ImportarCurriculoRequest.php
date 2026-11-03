<?php

namespace App\Http\Requests;

use App\Enums\TipoCandidato;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImportarCurriculoRequest extends FormRequest
{
    private const MENSAGEM_ARQUIVO = 'Envie um arquivo PDF de até 10 MB.';

    private const MENSAGEM_ARQUIVO_NO_LOTE = 'envie um arquivo PDF de até 10 MB.';

    private const REGRAS_DO_ARQUIVO = ['required', 'file', 'uploaded', 'mimes', 'max'];

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
            'arquivos' => ['required', 'array', 'max:'.config('banco_talentos.importacao_max_arquivos')],
            'arquivos.*' => ['required', 'file', 'mimes:pdf', 'max:'.config('banco_talentos.upload_max_kb')],
            'tipo' => ['required', Rule::enum(TipoCandidato::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivos.required' => 'Selecione ao menos um currículo em PDF.',
            'arquivos.array' => 'Selecione ao menos um currículo em PDF.',
            'arquivos.max' => 'Envie no máximo '.config('banco_talentos.importacao_max_arquivos').' currículos por vez.',
            ...$this->mensagensPorArquivo(),
            'tipo.required' => 'Informe se o candidato é servidor ou estagiário.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'arquivos' => 'currículos em PDF',
            'arquivos.*' => 'currículo em PDF',
            'tipo' => 'tipo de candidato',
        ];
    }

    /**
     * Em um lote, a mensagem indica qual arquivo precisa ser corrigido.
     *
     * @return array<string, string>
     */
    private function mensagensPorArquivo(): array
    {
        $arquivos = Arr::wrap($this->file('arquivos'));
        $mensagens = [];

        foreach ($arquivos as $indice => $arquivo) {
            $mensagem = count($arquivos) > 1 && $arquivo instanceof UploadedFile
                ? Str::limit($arquivo->getClientOriginalName(), 80).': '.self::MENSAGEM_ARQUIVO_NO_LOTE
                : self::MENSAGEM_ARQUIVO;

            foreach (self::REGRAS_DO_ARQUIVO as $regra) {
                $mensagens["arquivos.{$indice}.{$regra}"] = $mensagem;
            }
        }

        return $mensagens;
    }
}
