# Research: Importação múltipla, comparação com o PDF e leitura blindada

Diagnóstico feito com os dois currículos reais citados no pedido, no leitor instalado
(`smalot/pdfparser` 2.13.1). Os arquivos não entram no repositório.

## R1. Causa dos erros de leitura

**Achado**: os PDFs vêm de ferramenta de design. O texto é gravado por estilo, não pela ordem visual,
dentro de um Form XObject e caractere a caractere. O `getText()` devolve, por exemplo, `OBJETIVOS`,
`FORMAÇÃO` e `EXPERIÊNCIAS` em sequência, com o conteúdo depois; e omite a linha do telefone de um dos
currículos. Por isso a seção de formação ficava vazia e o conteúdo caía nas seções erradas. `getDataTm()`
não ajuda: ignora texto dentro de Form XObject.

**Decision**: percorrer os comandos do conteúdo com `getSectionsText`/`getCommandsText`, rastreando a
matriz de transformação (`cm`, `q`/`Q`), a matriz de texto (`Tm`, `Td`, `TD`, `T*`) e os formulários
(`Do`), e decodificar com `Font::decodeText`.

**Rationale**: recupera 100% do texto dos dois currículos, com posição e tamanho, sem biblioteca nova.
Custo medido: cerca de 0,02 s por PDF.

**Alternatives considered**: `getDataTm()` (não vê formulários); `pdftotext -layout` ou OCR (dependência
nova no contêiner); IA (viola a extração offline e determinística).

## R2. Reconstrução da ordem de leitura

**Decision**: XY-cut recursivo sobre fragmentos de linha. Em cada bloco, procura uma faixa vertical livre
(colunas); se não houver, corta na maior distância entre linhas, o que preserva a ordem de cima para baixo.

- Uma coluna só é aceita com ao menos 2 linhas de cada lado que não sejam apenas datas. Assim, datas
  alinhadas à direita continuam na linha da entrada, em vez de virarem uma "coluna".
- Faixa mínima: 12 pt ou 1,5 vez o tamanho mediano da fonte.
- A largura de um trecho vem da fonte. Quando ela não a declara, é estimada por baixo (0,42 × tamanho ×
  caracteres), o que nunca fecha uma faixa livre real.

**Fallback**: se o layout falhar ou recuperar menos de 80% dos caracteres visíveis do `getText()`, usa a
ordem do arquivo (FR-003).

**Alternatives considered**: ordenar só por `y` mistura colunas; detectar colunas só no nível da página
não resolve blocos lado a lado dentro de uma coluna (certificados em duas colunas).

## R3. Nome completo

**Decision**: usar a maior fonte da primeira página como `destaque` (até 3 linhas seguidas, só se for ao
menos 20% maior que o corpo do texto) e validá-la como nome. Sem destaque válido, regra sobre o texto: uma
linha de uma palavra se une à seguinte, e linhas de cargo ("Técnico em...") ou títulos de seção são recusadas.

**Rationale**: cobre barra lateral em qualquer posição e nomes em duas linhas sem adivinhar por texto.

## R4. Seções e itens

**Decision**:

- Título com palavras extras só é aceito se elas forem qualificadores conhecidos ("Formação Acadêmica",
  "Conhecimentos Gerais"); "Experiência auxiliando alunos" deixa de ser título.
- "Projeto:"/"Projetos:" com conteúdo na linha é item embutido: vai para Projetos e volta à seção anterior
  quando a frase termina.
- Certificados entram em Experiências, a pedido do usuário; cursos complementares seguem em Formação.
- Linhas viram um item quando a anterior não fecha frase e a seguinte começa em minúscula, ou a anterior
  termina em vírgula, conector ou gerúndio, tem aspas/parênteses abertos ou, em texto corrido, ocupa quase
  toda a largura. "Cursando" não conta como gerúndio de continuação.
- Datas soltas vão para o fim do item aberto; durações ("Duração do Curso: 40 horas") fecham o item.
- Item acima de 297 caracteres é dividido em frases, sem cortar conteúdo.

## R5. Comparação com o PDF

**Decision**: pré-visualização em `<iframe>` ao lado do formulário a partir de 1280 px, com "Abrir PDF" sempre
visível. O `iframe` usa `loading="lazy"` e fica oculto abaixo de `xl`, então não é baixado em telas estreitas.

**Rationale**: o visualizador nativo do navegador dispensa biblioteca, e ver os dois lado a lado é mais
rápido e marcante no pitch do que trocar de aba. O botão cobre telas estreitas e navegadores sem visualizador.

**Alternatives considered**: só o botão (obriga a alternar abas); PDF.js (dependência e build novos, sem ganho
para a demonstração).

## R6. Lote e cadastro sem revisão

**Decision**: lote na sessão (`importacoes_lotes.{uuid}`), reaproveitando as importações existentes.
O envio redireciona para a página do lote, que abre a modal ao carregar (`x-modal` com `abrir-ao-carregar`).

**Rationale**: fechar a modal ou recarregar não perde o estado, e o fluxo sem JavaScript continua funcionando.

**LGPD**: o cadastro sem revisão pede meio e data do consentimento uma vez, para todo o lote, atribuídos ao
usuário atual. Duplicidade, leitura incerta ou falha de validação deixam o currículo pendente, com o motivo.

**Limite do servidor**: `post_max_size` sobe de 13 MB para 110 MB (10 × 10 MB mais a sobrecarga), mantendo
`upload_max_filesize` em 12 MB. Exige reconstruir a imagem.

**Alternatives considered**: enviar um arquivo por requisição (mais estados de falha parcial e sem fallback
sem JS); modal na própria tela de envio (fechá-la deixaria PDFs órfãos, sem caminho de volta).

## R7. Isolamento dos testes no contêiner

**Achado**: durante esta feature, `php artisan test` dentro do contêiner apagou o banco de desenvolvimento. O
`DB_CONNECTION=mysql` do Compose chega ao PHP em `$_SERVER`, que o Laravel lê antes de `$_ENV`, e o `<env force>`
do PHPUnit só altera `$_ENV` e `putenv`. O `RefreshDatabase` executou então `migrate:fresh` no MySQL.

**Decision**: declarar `<server>` em `phpunit.xml` para a conexão de testes, e recusar em `tests/TestCase.php`
qualquer execução fora do SQLite em memória, antes de o `RefreshDatabase` rodar.
