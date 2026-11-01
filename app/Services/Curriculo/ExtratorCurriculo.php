<?php

namespace App\Services\Curriculo;

use App\DTOs\DadosExtraidosDTO;
use App\Enums\Area;
use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Extração determinística de currículos por regras + catálogo (research R4 e R5).
 * Não usa IA: o mesmo texto sempre gera o mesmo resultado, e a revisão humana corrige o resto.
 */
class ExtratorCurriculo
{
    private const LIMITE_ITEM = 297;

    private const LIMITE_ITENS = 20;

    private const LIMITE_SUGESTOES = 15;

    /** Cabeçalhos de seção já normalizados; a chave é o destino do conteúdo. */
    private const SECOES = [
        'formacoes' => ['formacao academica', 'formacao', 'escolaridade', 'educacao', 'graduacao'],
        'cursos' => ['cursos complementares', 'cursos', 'certificacoes', 'certificados'],
        'experiencias' => ['experiencia profissional', 'experiencias profissionais', 'experiencias', 'experiencia', 'historico profissional', 'atuacao profissional'],
        'projetos' => ['projetos', 'projeto', 'portfolio'],
        'competencias' => ['competencias tecnicas', 'competencias', 'habilidades', 'conhecimentos', 'tecnologias', 'skills', 'ferramentas'],
        'idiomas' => ['idiomas', 'idioma', 'linguas'],
        'outros' => ['resumo profissional', 'resumo', 'objetivo', 'perfil profissional', 'perfil', 'sobre mim', 'contato', 'dados pessoais', 'informacoes adicionais', 'atividades complementares', 'referencias'],
    ];

    /** Radicais de nível de idioma, do maior para o menor. */
    private const NIVEIS_IDIOMA = [
        3 => ['avancad', 'fluent', 'fluencia', 'nativ', 'proficien', 'advanced', 'native'],
        2 => ['intermediari', 'intermediate'],
        1 => ['basic', 'inician', 'elementar', 'beginner'],
    ];

    /** Formas de escrever cada grupo de idioma do catálogo. */
    private const NOMES_IDIOMA = [
        'ingles' => ['ingles', 'english'],
        'espanhol' => ['espanhol', 'spanish', 'castelhano'],
    ];

    /** Linhas de topo que não são nome de pessoa. */
    private const TITULOS_DOCUMENTO = ['curriculo', 'curriculum', 'curriculum vitae', 'curriculo vitae', 'resume'];

    /** Palavras soltas que não fazem sentido como sugestão de competência. */
    private const PALAVRAS_IGNORADAS = ['nivel', 'basico', 'intermediario', 'avancado', 'fluente', 'e', 'de', 'em', 'com', 'outros', 'etc'];

    /**
     * @var array{termos: list<array{competencia: Competencia, padroes: list<string>}>, idiomas: array<string, array{padrao: string, niveis: Collection<int, Competencia>}>}|null
     */
    private ?array $catalogo = null;

    public function __construct(private CompetenciaRepositoryInterface $competencias) {}

    public function extrair(string $texto): DadosExtraidosDTO
    {
        $linhas = $this->linhas($texto);

        if ($linhas === []) {
            return DadosExtraidosDTO::vazio();
        }

        $secoes = $this->separarSecoes($linhas);
        $textoCompleto = implode("\n", $linhas);
        $encontradas = $this->encontrarCompetencias($linhas);
        $area = $this->areaSugerida($encontradas);

        return new DadosExtraidosDTO(
            nome: $this->nome($secoes['cabecalho']),
            email: $this->email($textoCompleto),
            telefone: $this->telefone($textoCompleto),
            cpf: $this->cpf($textoCompleto),
            data_nascimento: $this->dataNascimento($linhas),
            formacoes: $this->itens([...$secoes['formacoes'], ...$secoes['cursos']]),
            experiencias: $this->itens($secoes['experiencias']),
            projetos: $this->itens($secoes['projetos']),
            links: $this->links($textoCompleto),
            competencias: array_values(array_map(fn (array $item): array => [
                'id' => $item['competencia']->id,
                'nome' => $item['competencia']->nome,
                'categoria' => $item['competencia']->categoria->value,
                'trecho' => $item['trecho'],
            ], $encontradas)),
            sugestoes_competencias: $this->sugestoes($secoes['competencias']),
            area_sugerida: $area?->value,
            experiencia_na_area: $this->experienciaNaArea($secoes['experiencias'], $area),
        );
    }

    /**
     * @return list<string>
     */
    private function linhas(string $texto): array
    {
        $linhas = preg_split('/\R/u', $texto) ?: [];
        $linhas = array_map(fn (string $linha): string => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $linha)), $linhas);

        return array_values(array_filter($linhas, fn (string $linha): bool => $linha !== ''));
    }

    /**
     * Divide o texto pelos cabeçalhos conhecidos. Linhas antes do primeiro cabeçalho formam o
     * bloco de identificação ("cabecalho"). Aceita cabeçalho com conteúdo na mesma linha
     * ("Conhecimentos: Python, SQL").
     *
     * @param  list<string>  $linhas
     * @return array<string, list<string>>
     */
    private function separarSecoes(array $linhas): array
    {
        $secoes = array_fill_keys(['cabecalho', ...array_keys(self::SECOES)], []);
        $atual = 'cabecalho';

        foreach ($linhas as $linha) {
            $cabecalho = $this->identificarCabecalho($linha);

            if ($cabecalho === null) {
                $secoes[$atual][] = $linha;

                continue;
            }

            [$atual, $resto] = $cabecalho;

            if ($resto !== '') {
                $secoes[$atual][] = $resto;
            }
        }

        return $secoes;
    }

    /**
     * @return array{0: string, 1: string}|null Seção e o conteúdo que veio na mesma linha.
     */
    private function identificarCabecalho(string $linha): ?array
    {
        $normalizada = NormalizadorTexto::normalizar($linha);
        $resto = '';

        if (preg_match('/^([a-z ]{3,40}?)\s*:\s*(.*)$/', $normalizada, $partes) === 1) {
            $candidato = trim($partes[1]);
            $resto = $partes[2] === '' ? '' : trim(Str::after($linha, ':'));
            $permitePrefixo = false;
        } else {
            $candidato = trim($normalizada, " :.-–—\t");
            $permitePrefixo = str_word_count($candidato) <= 3;
        }

        foreach (self::SECOES as $secao => $prefixos) {
            foreach ($prefixos as $prefixo) {
                if ($candidato === $prefixo || ($permitePrefixo && str_starts_with($candidato, $prefixo.' '))) {
                    return [$secao, $resto];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $cabecalho
     */
    private function nome(array $cabecalho): ?string
    {
        foreach (array_slice($cabecalho, 0, 5) as $linha) {
            $normalizada = NormalizadorTexto::normalizar($linha);

            if (in_array($normalizada, self::TITULOS_DOCUMENTO, true)
                || preg_match('/[@\d\/]|https?|www\./i', $linha) === 1
                || preg_match("/^[\p{L}][\p{L}'’.-]*(?:\s+[\p{L}][\p{L}'’.-]*){1,5}$/u", $linha) !== 1) {
                continue;
            }

            $nome = mb_strtoupper($linha) === $linha ? $this->capitalizarNome($linha) : $linha;

            return Str::limit($nome, 150, '');
        }

        return null;
    }

    private function capitalizarNome(string $nome): string
    {
        $palavras = explode(' ', mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE));

        foreach ($palavras as $indice => $palavra) {
            if ($indice > 0 && in_array(mb_strtolower($palavra), ['de', 'da', 'do', 'das', 'dos', 'e'], true)) {
                $palavras[$indice] = mb_strtolower($palavra);
            }
        }

        return implode(' ', $palavras);
    }

    private function email(string $texto): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $texto, $encontrado) !== 1
            || filter_var($encontrado[0], FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return Str::limit(mb_strtolower($encontrado[0]), 150, '');
    }

    /**
     * Aceita só CPF com dígitos verificadores válidos, escrito com pontuação ou logo após "CPF",
     * para não confundir com telefones ou outros números.
     */
    private function cpf(string $texto): ?string
    {
        preg_match_all('/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/', $texto, $encontrados, PREG_OFFSET_CAPTURE);

        foreach ($encontrados[0] as [$valor, $posicao]) {
            $pontuado = strpbrk($valor, '.-') !== false;
            $rotulado = stripos(substr($texto, max(0, $posicao - 12), min(12, $posicao)), 'cpf') !== false;
            $digitos = preg_replace('/\D/', '', $valor);

            if (($pontuado || $rotulado) && $this->cpfValido($digitos)) {
                return $digitos;
            }
        }

        return null;
    }

    private function cpfValido(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;

            for ($indice = 0; $indice < $posicao; $indice++) {
                $soma += (int) $cpf[$indice] * ($posicao + 1 - $indice);
            }

            $digito = ((10 * $soma) % 11) % 10;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    private function telefone(string $texto): ?string
    {
        $semDocumentos = preg_replace([
            '/(?<!\d)\d{3}\.\d{3}\.\d{3}-\d{2}(?!\d)/',
            '/cpf\W{0,3}\d{11}/i',
            '/\d{1,2}\/\d{1,2}\/\d{4}/',
        ], ' ', $texto);

        if (preg_match('/(?<!\d)(?:\+?55[\s.-]?)?\(?(\d{2})\)?[\s.-]?(9?\d{4})[\s.-]?(\d{4})(?!\d)/', (string) $semDocumentos, $partes) !== 1) {
            return null;
        }

        return "({$partes[1]}) {$partes[2]}-{$partes[3]}";
    }

    /**
     * @param  list<string>  $linhas
     */
    private function dataNascimento(array $linhas): ?string
    {
        foreach ($linhas as $linha) {
            if (! str_contains(NormalizadorTexto::normalizar($linha), 'nasc')
                || preg_match('/(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})/', $linha, $partes) !== 1) {
                continue;
            }

            [$dia, $mes, $ano] = [(int) $partes[1], (int) $partes[2], (int) $partes[3]];

            if ($ano > 1900 && checkdate($mes, $dia, $ano) && sprintf('%04d-%02d-%02d', $ano, $mes, $dia) < now()->toDateString()) {
                return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function links(string $texto): array
    {
        preg_match_all(
            '~https?://[^\s<>"\'()]+|(?<![\w.@/])(?:www\.)?(?:github\.com|gitlab\.com|linkedin\.com/in|behance\.net)/[^\s<>"\'()]+~i',
            $texto,
            $encontrados,
        );

        $links = [];

        foreach ($encontrados[0] as $link) {
            $link = rtrim($link, '.,;:!?');
            $link = preg_match('~^https?://~i', $link) === 1 ? $link : 'https://'.$link;

            if (strlen($link) <= 300 && filter_var($link, FILTER_VALIDATE_URL) !== false && ! in_array(mb_strtolower($link), array_map('mb_strtolower', $links), true)) {
                $links[] = $link;
            }
        }

        return array_slice($links, 0, 10);
    }

    /**
     * @param  list<string>  $linhas
     * @return list<string>
     */
    private function itens(array $linhas): array
    {
        $itens = [];

        foreach ($linhas as $linha) {
            $item = trim((string) preg_replace('/^(?:[-–—•*·▪◦●○■►➢✓]|\d{1,2}[.)])\s+/u', '', $linha));

            if ($item === '' || preg_match('~^(?:https?://|www\.)\S+$|^(?:github\.com|gitlab\.com|linkedin\.com)/\S+$~i', $item) === 1) {
                continue;
            }

            $itens[] = Str::limit($item, self::LIMITE_ITEM);
        }

        return array_slice(array_values(array_unique($itens)), 0, self::LIMITE_ITENS);
    }

    /**
     * @param  list<string>  $linhas
     * @return array<int, array{competencia: Competencia, trecho: string}> Indexado pelo id da competência.
     */
    private function encontrarCompetencias(array $linhas): array
    {
        $catalogo = $this->catalogo();
        $normalizadas = array_map(fn (string $linha): string => NormalizadorTexto::normalizar($linha), $linhas);
        $encontradas = [];

        foreach ($catalogo['termos'] as ['competencia' => $competencia, 'padroes' => $padroes]) {
            foreach ($normalizadas as $indice => $linha) {
                foreach ($padroes as $padrao) {
                    if (preg_match($padrao, $linha) === 1) {
                        $encontradas[$competencia->id] = ['competencia' => $competencia, 'trecho' => Str::limit($linhas[$indice], 200)];

                        continue 3;
                    }
                }
            }
        }

        foreach ($catalogo['idiomas'] as ['padrao' => $padrao, 'niveis' => $niveis]) {
            $escolhida = null;
            $trecho = '';

            foreach ($normalizadas as $indice => $linha) {
                if (preg_match($padrao, $linha) !== 1) {
                    continue;
                }

                $nivel = $this->nivelIdioma($linha);
                $competencia = $niveis->last(fn (Competencia $item): bool => $item->nivel <= $nivel) ?? $niveis->first();

                if ($escolhida === null || $competencia->nivel > $escolhida->nivel) {
                    [$escolhida, $trecho] = [$competencia, Str::limit($linhas[$indice], 200)];
                }
            }

            if ($escolhida !== null) {
                $encontradas[$escolhida->id] = ['competencia' => $escolhida, 'trecho' => $trecho];
            }
        }

        uasort($encontradas, fn (array $a, array $b): int => strnatcasecmp($a['competencia']->nome, $b['competencia']->nome));

        return $encontradas;
    }

    private function nivelIdioma(string $linhaNormalizada): int
    {
        foreach (self::NIVEIS_IDIOMA as $nivel => $radicais) {
            foreach ($radicais as $radical) {
                if (preg_match('/(?<![a-z])'.preg_quote($radical, '/').'/', $linhaNormalizada) === 1) {
                    return $nivel;
                }
            }
        }

        return 1;
    }

    /**
     * Área mais frequente entre as hard skills encontradas; empate segue a ordem do enum.
     *
     * @param  array<int, array{competencia: Competencia, trecho: string}>  $encontradas
     */
    private function areaSugerida(array $encontradas): ?Area
    {
        $contagem = [];

        foreach ($encontradas as ['competencia' => $competencia]) {
            if ($competencia->categoria === CategoriaCompetencia::Hard && $competencia->area !== null) {
                $contagem[$competencia->area->value] = ($contagem[$competencia->area->value] ?? 0) + 1;
            }
        }

        if ($contagem === []) {
            return null;
        }

        $maior = max($contagem);

        foreach (Area::cases() as $area) {
            if (($contagem[$area->value] ?? 0) === $maior) {
                return $area;
            }
        }

        return null;
    }

    /**
     * "Sim" quando a seção de experiência cita uma competência da área sugerida;
     * caso contrário, "não informado".
     *
     * @param  list<string>  $experiencias
     */
    private function experienciaNaArea(array $experiencias, ?Area $area): bool
    {
        if ($area === null || $experiencias === []) {
            return false;
        }

        foreach ($this->encontrarCompetencias($experiencias) as ['competencia' => $competencia]) {
            if ($competencia->area === $area) {
                return true;
            }
        }

        return false;
    }

    /**
     * Termos da seção de competências que não existem no catálogo. Só viram sugestão:
     * o recrutador decide se inclui no catálogo.
     *
     * @param  list<string>  $linhas
     * @return list<string>
     */
    private function sugestoes(array $linhas): array
    {
        $sugestoes = [];
        $vistos = [];

        foreach ($linhas as $linha) {
            $semMarcador = (string) preg_replace('/^(?:[-–—•*·▪◦●○■►➢✓])\s*/u', '', $linha);

            foreach (preg_split('/\s*(?:[,;|•·]|\s[-–—]\s|\s+e\s+)\s*/u', $semMarcador) ?: [] as $termo) {
                $termo = trim($termo, " \t.:()");
                $normalizado = NormalizadorTexto::normalizar($termo);

                if ($normalizado === '' || isset($vistos[$normalizado]) || mb_strlen($termo) < 2 || mb_strlen($termo) > 40
                    || str_word_count($normalizado) > 4 || preg_match('/^[\d\W]+$/', $normalizado) === 1
                    || in_array($normalizado, self::PALAVRAS_IGNORADAS, true)) {
                    continue;
                }

                $vistos[$normalizado] = true;

                if ($this->encontrarCompetencias([$termo]) === []) {
                    $sugestoes[] = $termo;
                }
            }
        }

        return array_slice($sugestoes, 0, self::LIMITE_SUGESTOES);
    }

    /**
     * Catálogo compilado em expressões regulares uma única vez por instância.
     *
     * @return array{termos: list<array{competencia: Competencia, padroes: list<string>}>, idiomas: array<string, array{padrao: string, niveis: Collection<int, Competencia>}>}
     */
    private function catalogo(): array
    {
        if ($this->catalogo !== null) {
            return $this->catalogo;
        }

        $agrupado = $this->competencias->todasAgrupadasPorCategoria();
        $todas = collect([...$agrupado['hard'], ...$agrupado['soft']]);
        $termos = [];

        foreach ($todas->reject(fn (Competencia $competencia): bool => $competencia->ehIdioma()) as $competencia) {
            $variacoes = array_unique(array_filter(array_map(
                fn (string $termo): string => NormalizadorTexto::normalizar($termo),
                [$competencia->nome, ...($competencia->sinonimos ?? [])],
            )));

            $termos[] = [
                'competencia' => $competencia,
                'padroes' => array_values(array_map(
                    fn (string $termo): string => '/(?<![a-z0-9.])'.preg_quote($termo, '/').'(?![a-z0-9])/',
                    $variacoes,
                )),
            ];
        }

        $idiomas = [];

        foreach ($todas->filter(fn (Competencia $competencia): bool => $competencia->ehIdioma())->groupBy('grupo') as $grupo => $niveis) {
            $nomes = array_map(fn (string $nome): string => preg_quote($nome, '/'), self::NOMES_IDIOMA[$grupo] ?? [$grupo]);

            $idiomas[$grupo] = [
                'padrao' => '/(?<![a-z0-9])(?:'.implode('|', $nomes).')(?![a-z0-9])/',
                'niveis' => $niveis->sortBy('nivel')->values(),
            ];
        }

        return $this->catalogo = ['termos' => $termos, 'idiomas' => $idiomas];
    }
}
