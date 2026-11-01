<?php

namespace App\Services\Selecao;

use Illuminate\Support\Str;

/**
 * Limpa os textos livres exibidos na triagem anônima (formação, experiências e projetos):
 * troca e-mails, URLs, CPFs, telefones e os termos informados (o nome do candidato) por
 * "[oculto]" (research R9, contracts/visibilidade-por-contexto.md).
 */
class LimpadorTexto
{
    public const MARCADOR = '[oculto]';

    /**
     * Ordem importa: e-mail antes de URL e CPF antes de telefone.
     */
    private const PADROES = [
        'email' => '/[\p{L}0-9._%+-]+@[\p{L}0-9-]+(?:\.[\p{L}0-9-]+)*\.\p{L}{2,}/u',
        'url' => '~\b(?:https?://|www\.)[^\s<>"]*[^\s<>".,;:!?)\]]~iu',
        // Domínio com caminho sem protocolo (github.com/usuario). TLD em minúsculas, para "ASP.NET/C#" ficar.
        'dominio' => '~\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:com|net|org|dev|io|me|app|br)(?:\.br)?/[^\s<>"]*[^\s<>".,;:!?)\]]~u',
        'cpf' => '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/',
        // Com DDD; exige 8 ou 9 dígitos depois dele, para períodos como "2022-2023" ficarem.
        'telefone' => '/(?:\+55\s?)?(?:\(\d{2}\)|(?<!\d)\d{2})\s?9?\d{4}[-\s]?\d{4}(?!\d)/',
        'celular' => '/(?<!\d)9\d{4}-\d{4}(?!\d)/',
    ];

    /** Letra sem acento => classe com as variantes acentuadas (com /iu, vale também para maiúsculas). */
    private const VARIANTES = [
        'a' => '[aáàâãä]', 'e' => '[eéèêë]', 'i' => '[iíìîï]', 'o' => '[oóòôõö]',
        'u' => '[uúùûü]', 'c' => '[cç]', 'n' => '[nñ]',
    ];

    /**
     * @param  list<string>  $termosProibidos  Removidos como palavras inteiras, sem diferenciar maiúsculas e acentos.
     */
    public function limpar(string $texto, array $termosProibidos = []): string
    {
        $texto = (string) preg_replace(array_values(self::PADROES), self::MARCADOR, $texto);

        foreach ($this->ordenarTermos($termosProibidos) as $termo) {
            $texto = (string) preg_replace('/(?<![\p{L}\p{N}])'.$this->padraoDoTermo($termo).'(?![\p{L}\p{N}])/iu', self::MARCADOR, $texto);
        }

        return $texto;
    }

    /**
     * Termos mais longos primeiro: o nome completo sai inteiro antes das partes.
     *
     * @param  list<string>  $termos
     * @return list<string>
     */
    private function ordenarTermos(array $termos): array
    {
        $termos = array_values(array_filter(array_map(fn (string $termo): string => trim($termo), $termos), fn (string $termo): bool => $termo !== ''));
        usort($termos, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $termos;
    }

    private function padraoDoTermo(string $termo): string
    {
        $padrao = '';

        foreach (mb_str_split(Str::lower(Str::ascii($termo))) as $caractere) {
            $padrao .= match (true) {
                isset(self::VARIANTES[$caractere]) => self::VARIANTES[$caractere],
                trim($caractere) === '' => '\s+',
                default => preg_quote($caractere, '/'),
            };
        }

        return $padrao;
    }
}
