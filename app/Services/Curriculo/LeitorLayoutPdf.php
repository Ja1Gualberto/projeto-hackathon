<?php

namespace App\Services\Curriculo;

use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\XObject\Form;
use Throwable;

/**
 * Reconstrói a ordem visual de leitura de um PDF a partir da posição do texto (specs/002, research
 * R1 e R2). Ferramentas de design gravam o texto agrupado por estilo, muitas vezes dentro de um
 * Form XObject: a ordem do arquivo não serve para interpretar colunas nem seções.
 *
 * @phpstan-type Trecho array{x: float, y: float, tamanho: float, largura: float, texto: string}
 * @phpstan-type Fragmento array{x0: float, x1: float, y: float, tamanho: float, texto: string}
 * @phpstan-type Linha array{y: float, tamanho: float, texto: string}
 */
class LeitorLayoutPdf
{
    /** Estimativa por baixo: errar para menos nunca fecha uma faixa livre real entre colunas. */
    private const LARGURA_CARACTERE = 0.42;

    private const DISTANCIA_ENTRE_FRAGMENTOS = 1.5;

    private const DISTANCIA_ESPACO_TRECHO = 0.25;

    private const DISTANCIA_ESPACO_CARACTERE = 0.9;

    private const TAMANHO_TRECHO_POR_CARACTERE = 2;

    private const TOLERANCIA_MESMA_LINHA = 0.25;

    private const COLUNA_MINIMA_PT = 12.0;

    private const COLUNA_MINIMA_FONTE = 1.5;

    private const LINHAS_MINIMAS_COLUNA = 2;

    private const PROFUNDIDADE_FORMULARIOS = 8;

    private const LIMITE_FRAGMENTOS_PAGINA = 2000;

    private const PROPORCAO_DESTAQUE = 1.2;

    private const LINHAS_DESTAQUE = 3;

    private const IDENTIDADE = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** @var array<int, true> Fontes já identificadas como sem larguras úteis, para não recalcular. */
    private array $fontesSemLargura = [];

    /**
     * Usa form feed entre colunas independentes e vertical tab entre blocos da mesma seção.
     *
     * @return array{texto: string, destaque: ?string}|null Nulo quando a ordem visual não é confiável.
     */
    public function ler(Document $documento): ?array
    {
        $textos = [];
        $destaque = null;
        $cabecalhoInicial = [];
        $this->fontesSemLargura = [];

        foreach (array_values($documento->getPages()) as $indice => $pagina) {
            if ((int) ($pagina->getDetails()['Rotate'] ?? 0) % 360 !== 0) {
                return null;
            }

            $fragmentos = $this->fragmentos($this->trechos($pagina));

            if (count($fragmentos) > self::LIMITE_FRAGMENTOS_PAGINA) {
                return null;
            }

            $linhas = $this->ordenar($fragmentos);

            if ($indice === 0) {
                $destaque = $this->destaque($linhas);
                $topo = $linhas;
                usort($topo, fn (array $a, array $b): int => $b['y'] <=> $a['y']);
                $cabecalhoInicial = array_column(array_slice($topo, 0, 3), 'texto');
            } else {
                $topo = $fragmentos === [] ? 0.0 : max(array_column($fragmentos, 'y'));
                $linhas = array_values(array_filter($linhas, fn (array $linha): bool => ! (
                    $linha['y'] >= $topo - 3 * $linha['tamanho']
                    && in_array($linha['texto'], $cabecalhoInicial, true)
                )));
            }

            foreach ($linhas as $linha) {
                if (preg_match('/^(?:pag(?:ina)?\.?\s*)?\d+\s*(?:\/|de)\s*\d+$/iu', $linha['texto']) !== 1) {
                    $textos[] = $linha['texto'];
                }
            }
        }

        return $textos === [] ? null : ['texto' => implode("\n", $textos), 'destaque' => $destaque];
    }

    /**
     * @return list<Trecho>
     */
    private function trechos(Page $pagina): array
    {
        $trechos = [];
        $this->percorrer($pagina, $this->conteudo($pagina), self::IDENTIDADE, $trechos);

        return $trechos;
    }

    private function conteudo(Page $pagina): string
    {
        $conteudo = $pagina->get('Contents');

        if ($conteudo instanceof ElementArray) {
            return implode("\n", array_map(fn (PDFObject $parte): string => (string) $parte->getContent(), $conteudo->getContent()));
        }

        if (! $conteudo instanceof PDFObject) {
            return '';
        }

        $elementos = $conteudo->getHeader()->getElements();

        if (! is_numeric(key($elementos))) {
            return (string) $conteudo->getContent();
        }

        return implode("\n", array_map(function (mixed $parte): string {
            return (string) (method_exists($parte, 'getObject') ? $parte->getObject()->getContent() : $parte->getContent());
        }, $elementos));
    }

    /**
     * Interpreta os comandos de texto, acumulando a posição final de cada trecho na página.
     *
     * @param  array<int, float>  $matriz  Matriz de transformação vigente.
     * @param  list<Trecho>  $trechos
     * @param  list<int>  $emAndamento  Formulários em execução, para não seguir referências circulares.
     */
    private function percorrer(Page $recursos, string $conteudo, array $matriz, array &$trechos, array $emAndamento = []): void
    {
        $pilha = [];
        $matrizTexto = $matrizLinha = self::IDENTIDADE;
        $tamanho = 1.0;
        $entrelinha = 0.0;
        $fonte = null;

        foreach ($recursos->getSectionsText($conteudo) as $secao) {
            foreach ($recursos->getCommandsText($secao) as $comando) {
                $operador = $comando[PDFObject::OPERATOR];
                $valor = $comando[PDFObject::COMMAND];

                switch ($operador) {
                    case 'q':
                        $pilha[] = [$matriz, $tamanho, $fonte];
                        break;
                    case 'Q':
                        if ($pilha !== []) {
                            [$matriz, $tamanho, $fonte] = array_pop($pilha);
                        }
                        break;
                    case 'cm':
                        $matriz = $this->multiplicar($this->numeros($valor), $matriz);
                        break;
                    case 'BT':
                        $matrizTexto = $matrizLinha = self::IDENTIDADE;
                        break;
                    case 'Tm':
                        $matrizTexto = $matrizLinha = $this->numeros($valor);
                        break;
                    case 'TL':
                        $entrelinha = (float) $valor;
                        break;
                    case 'Td':
                    case 'TD':
                        $deslocamento = $this->numeros($valor);
                        $entrelinha = $operador === 'TD' ? -$deslocamento[1] : $entrelinha;
                        $matrizTexto = $matrizLinha = $this->multiplicar([1.0, 0.0, 0.0, 1.0, $deslocamento[0], $deslocamento[1]], $matrizLinha);
                        break;
                    case 'T*':
                        $matrizTexto = $matrizLinha = $this->multiplicar([1.0, 0.0, 0.0, 1.0, 0.0, -$entrelinha], $matrizLinha);
                        break;
                    case 'Tf':
                    case 'TF':
                        $partes = preg_split('/\s+/', trim((string) $valor)) ?: [];
                        $fonte = $recursos->getFont(ltrim((string) ($partes[0] ?? ''), '/'));
                        $tamanho = (float) ($partes[1] ?? 1);
                        break;
                    case "'":
                    case '"':
                        $matrizTexto = $matrizLinha = $this->multiplicar([1.0, 0.0, 0.0, 1.0, 0.0, -$entrelinha], $matrizLinha);
                        // no break
                    case 'Tj':
                    case 'TJ':
                        $texto = $this->decodificar($fonte, $operador === 'TJ' ? $valor : [$comando]);

                        if ($texto === '') {
                            break;
                        }

                        $final = $this->multiplicar($matrizTexto, $matriz);
                        $escala = hypot($final[2], $final[3]);
                        $largura = $this->largura($fonte, $texto, $tamanho);

                        // Texto girado (selos, marcas laterais) não participa da ordem de leitura.
                        if (abs($final[1]) < 0.01) {
                            $trechos[] = ['x' => $final[4], 'y' => $final[5], 'tamanho' => $tamanho * $escala, 'largura' => $largura * abs($final[0]), 'texto' => $texto];
                        }

                        // Mantém a ordem na mesma linha quando o arquivo não reposiciona o texto.
                        $matrizTexto = $this->multiplicar([1.0, 0.0, 0.0, 1.0, $largura, 0.0], $matrizTexto);
                        break;
                    case 'Do':
                        $this->percorrerFormulario($recursos, (string) $valor, $matriz, $trechos, $emAndamento);
                        break;
                }
            }
        }
    }

    /**
     * @param  array<int, float>  $matriz
     * @param  list<Trecho>  $trechos
     * @param  list<int>  $emAndamento
     */
    private function percorrerFormulario(Page $recursos, string $valor, array $matriz, array &$trechos, array $emAndamento): void
    {
        $partes = preg_split('/\s+/', trim($valor)) ?: [];
        $formulario = $recursos->getXObject(trim((string) end($partes), '/ '));

        if (! $formulario instanceof Form
            || count($emAndamento) >= self::PROFUNDIDADE_FORMULARIOS
            || in_array(spl_object_id($formulario), $emAndamento, true)) {
            return;
        }

        $this->percorrer(
            $formulario,
            (string) $formulario->getContent(),
            $this->multiplicar($this->matrizDoFormulario($formulario), $matriz),
            $trechos,
            [...$emAndamento, spl_object_id($formulario)],
        );
    }

    /**
     * @return array<int, float>
     */
    private function matrizDoFormulario(Form $formulario): array
    {
        $valores = $formulario->has('Matrix') ? $formulario->get('Matrix')->getContent() : null;

        return is_array($valores) && count($valores) === 6
            ? array_map(fn (mixed $valor): float => (float) (is_object($valor) ? $valor->getContent() : $valor), array_values($valores))
            : self::IDENTIDADE;
    }

    /**
     * Sem a fonte não há como decodificar com segurança: o trecho fica de fora e, se fizer falta,
     * a leitura recorre à ordem do arquivo.
     *
     * @param  list<array<string, mixed>>  $comandos
     */
    private function decodificar(?Font $fonte, array $comandos): string
    {
        return $fonte === null ? '' : $fonte->decodeText($comandos);
    }

    /**
     * Agrupa os trechos em linhas e cada linha em fragmentos separados por um vão horizontal.
     *
     * @param  list<Trecho>  $trechos
     * @return list<Fragmento>
     */
    private function fragmentos(array $trechos): array
    {
        usort($trechos, fn (array $a, array $b): int => $b['y'] <=> $a['y']);

        $fragmentos = [];

        foreach ($this->agruparLinhas($trechos) as $linha) {
            usort($linha, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
            $atual = null;
            $anterior = '';

            foreach ($linha as $trecho) {
                $vao = $atual === null ? 0.0 : $trecho['x'] - $atual['x1'];

                $marcador = $atual !== null && preg_match('/^[•▪◦●○■►➢✓\x{F0B2}]$/u', trim($atual['texto'])) === 1;

                if ($atual !== null && $vao > ($marcador ? 3 : self::DISTANCIA_ENTRE_FRAGMENTOS) * $trecho['tamanho']) {
                    $fragmentos[] = $atual;
                    $atual = null;
                }

                if ($atual === null) {
                    $atual = ['x0' => $trecho['x'], 'x1' => $trecho['x'], 'y' => $trecho['y'], 'tamanho' => $trecho['tamanho'], 'texto' => ''];
                } elseif ($this->precisaDeEspaco($anterior, $trecho['texto'], $vao, $trecho['tamanho'])) {
                    $atual['texto'] .= ' ';
                }

                $atual['texto'] .= $trecho['texto'];
                $atual['x1'] = max($atual['x1'], $trecho['x'] + $trecho['largura']);
                $atual['tamanho'] = max($atual['tamanho'], $trecho['tamanho']);
                $anterior = $trecho['texto'];
            }

            if ($atual !== null) {
                $fragmentos[] = $atual;
            }
        }

        return array_values(array_filter($fragmentos, fn (array $fragmento): bool => trim($fragmento['texto']) !== ''));
    }

    private function precisaDeEspaco(string $anterior, string $seguinte, float $vao, float $tamanho): bool
    {
        if (preg_match('/\s$/u', $anterior) === 1 || preg_match('/^\s/u', $seguinte) === 1) {
            return false;
        }

        // Texto gravado caractere a caractere (ou em pares) tem largura real maior que a estimativa.
        $porCaractere = min(mb_strlen($anterior), mb_strlen($seguinte)) <= self::TAMANHO_TRECHO_POR_CARACTERE;

        return $vao > ($porCaractere ? self::DISTANCIA_ESPACO_CARACTERE : self::DISTANCIA_ESPACO_TRECHO) * $tamanho;
    }

    /**
     * XY-cut recursivo: separa colunas quando há faixa vertical livre; senão, corta na maior
     * distância entre linhas, o que preserva a ordem de cima para baixo.
     *
     * @param  list<Fragmento>  $fragmentos
     * @return list<Linha>
     */
    private function ordenar(array $fragmentos, bool $separarColunas = true): array
    {
        usort($fragmentos, fn (array $a, array $b): int => $b['y'] <=> $a['y']);
        $linhas = $this->agruparLinhas($fragmentos);

        if (count($linhas) <= 1) {
            return array_map(fn (array $linha): array => $this->unirLinha($linha), $linhas);
        }

        $tabela = $this->ordenarTabela($linhas, $separarColunas);

        if ($tabela !== null) {
            return $tabela;
        }

        $coluna = $this->corteVertical($fragmentos);
        $titulo = $this->corteDeTitulo($linhas);

        if ($titulo !== null) {
            [$inicio, $fim] = $titulo;

            return [
                ...$this->ordenar(array_merge([], ...array_slice($linhas, 0, $inicio)), $separarColunas),
                $this->unirLinha($linhas[$inicio]),
                ...$this->ordenar(array_merge(...array_slice($linhas, $inicio + 1, $fim - $inicio - 1)), false),
                ...$this->ordenar(array_merge([], ...array_slice($linhas, $fim)), $separarColunas),
            ];
        }

        if ($coluna !== null) {
            return [
                ...$this->ordenar(array_values(array_filter($fragmentos, fn (array $fragmento): bool => $fragmento['x0'] < $coluna)), $separarColunas),
                ['y' => 0.0, 'tamanho' => 0.0, 'texto' => $separarColunas ? "\f" : "\v"],
                ...$this->ordenar(array_values(array_filter($fragmentos, fn (array $fragmento): bool => $fragmento['x0'] >= $coluna)), $separarColunas),
            ];
        }

        $corte = 1;
        $maiorDistancia = -1.0;

        foreach (array_keys($linhas) as $indice) {
            if ($indice === 0) {
                continue;
            }

            $distancia = $linhas[$indice - 1][0]['y'] - $linhas[$indice][0]['y'];

            if ($distancia > $maiorDistancia) {
                [$corte, $maiorDistancia] = [$indice, $distancia];
            }
        }

        return [
            ...$this->ordenar(array_merge(...array_slice($linhas, 0, $corte)), $separarColunas),
            ...$this->ordenar(array_merge(...array_slice($linhas, $corte)), $separarColunas && ! (
                count($linhas[$corte - 1]) === 1 && $this->pareceTitulo($linhas[$corte - 1][0]['texto'])
            )),
        ];
    }

    /**
     * Títulos centralizados pertencem às duas colunas abaixo, não à coluna em que o texto começa.
     *
     * @param  list<list<Fragmento>>  $linhas
     * @return array{int, int}|null Início e fim exclusivo da seção compartilhada.
     */
    private function corteDeTitulo(array $linhas): ?array
    {
        foreach ($linhas as $indice => $linha) {
            $item = $linha[0];

            if (count($linha) !== 1 || ! isset($linhas[$indice + 1]) || ! $this->pareceTitulo($item['texto'])) {
                continue;
            }

            $espacoAcima = $indice === 0 || $linhas[$indice - 1][0]['y'] - $item['y'] >= 1.5 * $item['tamanho'];
            $espacoAbaixo = 1.2 * $item['tamanho'] <= $item['y'] - $linhas[$indice + 1][0]['y'];

            if (! $espacoAcima || ! $espacoAbaixo) {
                continue;
            }

            $fim = $indice + 1;

            while (isset($linhas[$fim]) && ! (count($linhas[$fim]) === 1 && $this->pareceTitulo($linhas[$fim][0]['texto']))) {
                $fim++;
            }

            $abaixo = array_merge(...array_slice($linhas, $indice + 1, $fim - $indice - 1));

            if ($abaixo === []) {
                continue;
            }

            $coluna = $this->corteVertical($abaixo);

            if ($coluna === null) {
                continue;
            }

            $esquerda = min(array_column($abaixo, 'x0'));
            $limiteEsquerdo = max(array_column(array_filter($abaixo, fn (array $fragmento): bool => $fragmento['x0'] < $coluna), 'x1'));
            $abrangeColunas = ($item['x0'] < $coluna && $item['x1'] > $limiteEsquerdo)
                || ($indice === 0 && abs($item['x0'] - $esquerda) <= $item['tamanho']);

            if ($abrangeColunas) {
                return [$indice, $fim];
            }
        }

        return null;
    }

    private function pareceTitulo(string $texto): bool
    {
        return preg_match('/^(?:formacao|educacao|escolaridade|certificados?|certificacoes|cursos|habilidades|competencias|experiencias?|projetos?|portfolio)\b/', NormalizadorTexto::normalizar($texto)) === 1
            && mb_strlen(trim($texto)) <= 60;
    }

    /**
     * Uma tabela com cargo/período e atividades é lida por registro, não por coluna inteira.
     * O espaço entre registros delimita as células, inclusive quando elas têm várias linhas.
     *
     * @param  list<list<Fragmento>>  $linhas
     * @return list<Linha>|null
     */
    private function ordenarTabela(array $linhas, bool $separarColunas): ?array
    {
        foreach ($linhas as $indice => $linha) {
            $titulos = array_map(fn (array $item): string => NormalizadorTexto::normalizar($item['texto']), $linha);

            if (count($linha) < 2
                || array_intersect($titulos, ['cargo', 'cargo e periodo', 'empresa', 'periodo']) === []
                || array_intersect($titulos, ['atividades', 'descricao', 'responsabilidades']) === []) {
                continue;
            }

            $fim = $indice + 1;

            while (isset($linhas[$fim]) && ! (count($linhas[$fim]) === 1 && $this->pareceTitulo($linhas[$fim][0]['texto']))) {
                $fim++;
            }

            $registros = [];
            $atual = [];

            foreach (array_slice($linhas, $indice + 1, $fim - $indice - 1) as $linhaRegistro) {
                if ($atual !== [] && $atual[array_key_last($atual)]['y'] - $linhaRegistro[0]['y'] > 2 * $linhaRegistro[0]['tamanho']) {
                    $registros[] = $atual;
                    $atual = [];
                }

                array_push($atual, ...$linhaRegistro);
            }

            if ($atual !== []) {
                $registros[] = $atual;
            }

            $resultado = $this->ordenar(array_merge([], ...array_slice($linhas, 0, $indice)), $separarColunas);

            foreach ($registros as $registro) {
                $ordenadas = $this->ordenar($registro, false);
                $textos = array_filter(array_column($ordenadas, 'texto'), fn (string $texto): bool => $texto !== "\v");
                $resultado[] = ['y' => $registro[0]['y'], 'tamanho' => max(array_column($registro, 'tamanho')), 'texto' => implode(' ', $textos)];
            }

            return [...$resultado, ...$this->ordenar(array_merge([], ...array_slice($linhas, $fim)), $separarColunas)];
        }

        return null;
    }

    /**
     * Posição da faixa livre mais larga que separa duas colunas de verdade: cada lado precisa de
     * linhas além de datas, para datas alinhadas à direita continuarem na linha da entrada.
     *
     * @param  list<Fragmento>  $fragmentos
     */
    private function corteVertical(array $fragmentos): ?float
    {
        if ($fragmentos === []) {
            return null;
        }

        usort($fragmentos, fn (array $a, array $b): int => $a['x0'] <=> $b['x0']);

        $tamanhos = array_column($fragmentos, 'tamanho');
        sort($tamanhos);
        $faixaMinima = max(self::COLUNA_MINIMA_PT, self::COLUNA_MINIMA_FONTE * $tamanhos[(int) (count($tamanhos) / 2)]);

        $melhorCorte = null;
        $melhorFaixa = 0.0;
        $direitaMaxima = $fragmentos[0]['x1'];

        foreach ($fragmentos as $indice => $fragmento) {
            $faixa = $fragmento['x0'] - $direitaMaxima;

            if ($faixa >= $faixaMinima && $faixa > $melhorFaixa
                && $this->temLinhasDeColuna(array_slice($fragmentos, 0, $indice))
                && $this->temLinhasDeColuna(array_slice($fragmentos, $indice))) {
                [$melhorCorte, $melhorFaixa] = [$fragmento['x0'], $faixa];
            }

            $direitaMaxima = max($direitaMaxima, $fragmento['x1']);
        }

        return $melhorCorte;
    }

    /**
     * @param  list<Fragmento>  $fragmentos
     */
    private function temLinhasDeColuna(array $fragmentos): bool
    {
        $conteudo = array_values(array_filter(
            $fragmentos,
            fn (array $fragmento): bool => ! NormalizadorTexto::ehSomenteData($fragmento['texto']),
        ));
        usort($fragmentos, fn (array $a, array $b): int => $b['y'] <=> $a['y']);

        return $conteudo !== [] && count($this->agruparLinhas($fragmentos)) >= self::LINHAS_MINIMAS_COLUNA;
    }

    /**
     * @template T of array{y: float, tamanho: float}
     *
     * @param  list<T>  $itens  Em ordem decrescente de y.
     * @return list<list<T>>
     */
    private function agruparLinhas(array $itens): array
    {
        $linhas = [];
        $atual = [];

        foreach ($itens as $item) {
            if ($atual !== []) {
                $tolerancia = max(1.0, self::TOLERANCIA_MESMA_LINHA * min($item['tamanho'], $atual[0]['tamanho']));

                if (abs($atual[0]['y'] - $item['y']) > $tolerancia) {
                    $linhas[] = $atual;
                    $atual = [];
                }
            }

            $atual[] = $item;
        }

        if ($atual !== []) {
            $linhas[] = $atual;
        }

        return $linhas;
    }

    /**
     * @param  list<Fragmento>  $linha
     * @return Linha
     */
    private function unirLinha(array $linha): array
    {
        usort($linha, fn (array $a, array $b): int => $a['x0'] <=> $b['x0']);
        $texto = implode(' ', array_map(fn (array $fragmento): string => trim($fragmento['texto']), $linha));

        return [
            'y' => $linha[0]['y'],
            'tamanho' => max(array_column($linha, 'tamanho')),
            'texto' => trim((string) preg_replace('/\s+/u', ' ', $texto)),
        ];
    }

    /**
     * Texto de maior fonte da primeira página, candidato ao nome da pessoa. Só vale quando se
     * destaca do corpo do texto e ocupa poucas linhas seguidas.
     *
     * @param  list<Linha>  $linhas
     */
    private function destaque(array $linhas): ?string
    {
        if ($linhas === []) {
            return null;
        }

        $linhas = array_values(array_filter($linhas, fn (array $linha): bool => ! in_array($linha['texto'], ["\f", "\v"], true)));
        $tamanhos = array_column($linhas, 'tamanho');
        sort($tamanhos);
        $maior = end($tamanhos);

        if ($maior < self::PROPORCAO_DESTAQUE * $tamanhos[intdiv(count($tamanhos) - 1, 2)]) {
            return null;
        }

        $selecionadas = [];

        foreach ($linhas as $linha) {
            if ($linha['tamanho'] >= $maior - 0.5) {
                $selecionadas[] = $linha['texto'];
            } elseif ($selecionadas !== []) {
                break;
            }
        }

        return count($selecionadas) <= self::LINHAS_DESTAQUE ? implode(' ', $selecionadas) : null;
    }

    /**
     * Usa as larguras declaradas pela fonte; quando ela não as oferece, recorre à estimativa.
     */
    private function largura(?Font $fonte, string $texto, float $tamanho): float
    {
        $estimativa = mb_strlen($texto) * $tamanho * self::LARGURA_CARACTERE;

        if ($fonte === null || isset($this->fontesSemLargura[spl_object_id($fonte)])) {
            return $estimativa;
        }

        try {
            $detalhes = $fonte->getDetails();
            $largura = isset($detalhes['FirstChar'], $detalhes['LastChar'], $detalhes['Widths'])
                ? $fonte->calculateTextWidth($texto, $faltantes)
                : null;
        } catch (Throwable) {
            $largura = null;
        }

        if ($largura === null || $largura <= 0.0 || $faltantes !== []) {
            $this->fontesSemLargura[spl_object_id($fonte)] = true;

            return $estimativa;
        }

        return $largura * $tamanho / 1000;
    }

    /**
     * @return array<int, float>
     */
    private function numeros(mixed $valor): array
    {
        $numeros = array_map('floatval', preg_split('/\s+/', trim((string) $valor)) ?: []);

        return array_pad($numeros, 6, 0.0);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     * @return array<int, float>
     */
    private function multiplicar(array $a, array $b): array
    {
        return [
            $a[0] * $b[0] + $a[1] * $b[2],
            $a[0] * $b[1] + $a[1] * $b[3],
            $a[2] * $b[0] + $a[3] * $b[2],
            $a[2] * $b[1] + $a[3] * $b[3],
            $a[4] * $b[0] + $a[5] * $b[2] + $b[4],
            $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
        ];
    }
}
