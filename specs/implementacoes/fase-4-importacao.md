# Phase 4 — US2: Importação de currículo em PDF (T063–T079, T155–T156)

**Feature**: [`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/) · **Data**: 2026-10-31 ·
**Rodada**: R1, trilha B. Implementada por uma pessoa só, sobre as Phases 2 e 3 já entregues.

## Resumo

O recrutador envia o PDF e informa o tipo (servidor ou estagiário). O sistema lê o texto e
extrai, por regras e pelo catálogo:

- identificação e contato;
- formação, experiências, projetos e links;
- competências, com o trecho de origem;
- área sugerida e experiência na área.

Termos fora do catálogo viram sugestões. Tudo aparece numa tela de revisão editável. O cadastro
só é salvo depois que o recrutador registra o consentimento (FR-011). Se o e-mail ou o CPF
coincidirem com um cadastro existente, o recrutador escolhe entre atualizar esse cadastro e
criar outro. Na atualização, a origem de competências observadas em entrevista nunca é
rebaixada.

Com JavaScript, o envio, a confirmação e a inclusão de sugestões funcionam por AJAX:

- envio e confirmação só navegam quando o servidor confirma o sucesso;
- a inclusão de sugestões mescla só a nova opção, sem perder o que foi editado na revisão.

Sem JavaScript, tudo funciona por formulário tradicional (PRG).

## Status da verificação

| Verificação | Resultado |
|---|---|
| Testes escritos antes do código | ✅ 59 testes PHP e 7 JS falhando antes da implementação |
| `php artisan test` | ✅ **131 testes, 622 asserções** (71 anteriores + 60 novos: extrator 26, leitor de PDF 5, DTO 3, importação 26) |
| Testes JavaScript (`node --test`) | ✅ **32 testes**, sendo 7 novos em `tests/js/importacao.test.js` |
| Pint (`--format agent`) | ✅ Aplicado (3 arquivos ajustados: imports e aspas) |
| Build Vite (na reconstrução da imagem) | ✅ `app.js` 11,5 kB / `app.css` 65 kB |
| Teste de fumaça HTTP, cenário B (container sem `.env`) | ✅ Ver detalhes abaixo |
| Revisão no navegador (B/H com e sem JS, teclado, foco, rede lenta) | **Pendente** (ambiente sem navegador) |

Detalhes do teste de fumaça:

- upload AJAX de `demo-curriculo.pdf` → revisão com **6/6** competências do PDF marcadas;
- "Kubernetes" aparece como sugestão, e a área "Desenvolvimento" vem sugerida;
- sem consentimento, o cadastro é bloqueado; com consentimento, o candidato é salvo;
- o candidato aparece na busca, e a importação concluída responde 404;
- `demo-duplicado.pdf` mostra o alerta de duplicidade;
- `demo-escaneado.pdf` mostra o aviso de leitura manual.

## O que foi entregue

| Tarefa | Entrega |
|---|---|
| T063 | `tests/Unit/ExtratorCurriculoTest.php`: todos os casos de referência do contrato, mais contato, links, seções, área, trecho, normalização de nome e SC-002 (≥ 8 de 10). Também `tests/Unit/LeitorPdfTest.php` e `tests/Unit/DTOs/DadosExtraidosDTOTest.php` |
| T064, T155 | `tests/Feature/ImportacaoCurriculoTest.php`: fluxo HTML e JSON completo (upload, rejeições, sem texto, corrompido, consentimento, criação, duplicidade, atualização sem rebaixar origem, cadastro errado recusado, expirada, visitante, catálogo e PDFs de demonstração) |
| T065 | `app/DTOs/ResultadoLeituraDTO.php` (`lido`/`sem_texto`/`corrompido`) e `app/Services/Curriculo/LeitorPdf.php` (arquivo ilegível ou ausente → `corrompido`; menos de 80 caracteres → `sem_texto`) |
| T066 | `app/DTOs/DadosExtraidosDTO.php`, com os campos exatos do contrato, `toArray`/`fromArray`/`vazio` e os auxiliares `idsCompetencias()` e `trechosPorCompetencia()` |
| T067 | `app/Services/Curriculo/ExtratorCurriculo.php` (ver regras abaixo) e `NormalizadorTexto.php` |
| T068 | `app/Services/Curriculo/ImportacaoCurriculoService.php`: `importar`, `obter`, `duplicado`, `descartar`, `marcarCompetenciaAdicionada` e `dadosRevisao` |
| T069 | `app/Services/Curriculo/CadastroCandidatoService.php`: `confirmar` (novo/atualizar) e `atualizar` para a correção manual da US6 |
| T070–T072 | `ImportarCurriculoRequest`, `ConfirmarImportacaoRequest` (listas por linha, CPF normalizado, links só `http(s)`), `CriarCompetenciaRequest`, `CompetenciaController` e `app/Services/CompetenciaService.php` |
| T073–T074 | `ImportacaoCurriculoController` (`create`/`store`/`show`) e `ConfirmacaoImportacaoController`, ambos HTML e JSON |
| T075 | 5 rotas no bloco `// US2 — importação` de `routes/candidatos.php` (`whereUuid` na revisão e na confirmação) |
| T076–T078 | `importacoes/create` (área de envio + cartões de tipo), `candidatos/partials/_formulario` (reutilizável na US6), `candidatos/partials/_catalogo-competencias` (região AJAX), `importacoes/show` (revisão) e `importacoes/expirada` |
| T079 | `app/Console/Commands/GerarCurriculosDemoCommand.php` (`demo:curriculos`) e os 3 PDFs versionados em `database/seeders/curriculos/` |
| T156 | `mesclarFragmento()` em `ajax.js`, `data-ajax-mesclar` e o evento `ajax:sucesso` em `formularios.js`, e `modulos/importacao.js` ("Ignorar" e foco na nova opção) |

### Regras do extrator (research R4/R5)

- **Normalização**: minúsculas e sem acento (`Str::ascii`), aplicada linha a linha. O trecho
  guardado é a linha original.
- **Seções**: por cabeçalhos conhecidos, inclusive com conteúdo na mesma linha
  ("Conhecimentos: Python, SQL"). "Cursos complementares" entram em formação. Linhas só com URL
  saem de projetos.
- **Competências**: nome e sinônimos do catálogo, com fronteiras
  `(?<![a-z0-9.])…(?![a-z0-9])`. Exemplos:
  - "Node.js" não aciona "js"/JavaScript;
  - "C#" e ".NET" são reconhecidos;
  - "PostgreSQL" também implica SQL.
- **Idiomas**: detectados pelo grupo (inglês/english, espanhol/spanish), com o nível na mesma
  linha (avançado/fluente/intermediário/básico). Sem nível, vale o básico. Fica o maior nível
  encontrado.
- **Identificação**:
  - e-mail;
  - telefone BR, normalizado como `(DD) NNNNN-NNNN`;
  - CPF, só com dígitos verificadores válidos e escrito com pontuação ou após "CPF";
  - data de nascimento perto de "nasc…";
  - nome: primeira linha de 2 a 6 palavras antes de qualquer seção, convertida para nome
    próprio se vier em caixa alta.
- **Links**: em ordem de aparição. GitHub, GitLab e LinkedIn sem `https://` são completados.
- **Sugestões**: termos da seção de competências que não existem no catálogo, sem repetições;
  palavras de nível são ignoradas.
- **Área e experiência**: a área sugerida é a mais frequente entre as hard skills. Há
  "experiência na área" quando a seção de experiência cita uma competência dessa área.
- **Limites**: itens com até 300 caracteres e no máximo 20 por lista, para a revisão nunca
  nascer inválida.

## Decisões e desvios

| Item | Decisão | Por quê |
|---|---|---|
| Modo `atualizar` | O cadastro escolhido precisa ter o mesmo e-mail ou CPF do currículo (lido ou revisado); senão, 422 em `candidato_existente_id` | Impede que um id adulterado sobrescreva os dados de outra pessoa |
| Competências no modo `atualizar` | União das atuais com as revisadas; a origem só sobe (`entrevista` > `revisao` > `curriculo`) | Segue "mescla sem rebaixar" do contrato; remover competências continua sendo feito na edição (US6) |
| Arquivo definitivo | Cópia de `tmp/` para `{uuid}.pdf` antes da transação. Se a transação falhar, a cópia é apagada; depois do commit, apagam-se o temporário e o PDF anterior | Nunca deixa um candidato apontando para arquivo inexistente |
| Importação expirada (GET) | Página própria `importacoes/expirada` (404) com "Enviar novamente" | Pedido de T073; a 404 genérica só oferece "Ir para o início" |
| Sugestões | Só da seção de competências do PDF | Evita transformar frases de experiência em sugestões; o caso de referência foi testado com o cabeçalho "COMPETÊNCIAS" |
| Sugestão incluída sem JavaScript | Volta para a revisão já marcada (via sessão), mas edições ainda não salvas do formulário principal se perdem | É o limite do modo sem JS com mini-formulário separado; com JS nada se perde (mescla) |
| Slug de competência | Sufixo `-2`, `-3`… quando dois nomes geram o mesmo slug | Nomes incluídos pelo recrutador podiam violar a unicidade |
| `x-campo` | Aceita listas (`old()` de textarea vira array) e mostra erros `campo.*` | Necessário para os textareas "um item por linha" e para `links.0` |
| Erros AJAX de rádios/checkboxes | Um único aviso por grupo, reaproveitando `[data-erro-campo]` | Antes, cada opção do grupo recebia o próprio aviso |
| Teste da Phase 3 | `test_rota_de_perfil_aceita_apenas_id_numerico` usa `/candidatos/ana-lima` e confirma que `/candidatos/importar` abre a importação | A rota passou a existir de propósito nesta fase |

## Correção pós-entrega (2026-11-01): erro 500 no upload pelo Docker

- **Sintoma**: ao enviar um PDF, a tela mostrava "O servidor não confirmou a operação. Confira o
  estado antes de tentar novamente." (mensagem genérica do `ajax.js` para erro 500).
- **Causa**: o log registrava `UnableToCreateDirectory` em
  `storage/app/private/curriculos/tmp`. O wrapper rodava `php artisan` como **root**, e o
  `CandidatoSeeder` criava `curriculos/` como root com permissão `700`. O Apache roda como
  **www-data** e não conseguia criar `tmp/`. Esta é a primeira fase em que a aplicação web grava
  nesse disco. O teste de fumaça não pegou o problema porque semeava com `docker exec -u www-data`.
- **Correção**: `hackaton` e `hackaton.ps1` passam a rodar `artisan` e `tinker` como www-data.
  `demo` devolve `storage` ao www-data (`chown`) antes do `migrate:fresh --seed`, e
  `key:generate` continua como root, como antes. `shell`, `exec`, `php`, `composer` e `pint`
  não mudaram.
- **Volumes criados antes da correção**: rode uma vez
  `.\hackaton.ps1 exec chown -R www-data:www-data storage` (ou `.\hackaton.ps1 demo`). Isso já
  foi aplicado no ambiente local em 2026-11-01; o upload voltou a responder 200.

## Arquivos

**Criados (29)**:

- `app/DTOs/ResultadoLeituraDTO.php`, `app/DTOs/DadosExtraidosDTO.php`
- `app/Services/Curriculo/` (`LeitorPdf`, `NormalizadorTexto`, `ExtratorCurriculo`,
  `ImportacaoCurriculoService`, `CadastroCandidatoService`)
- `app/Services/CompetenciaService.php`
- `app/Http/Requests/` (`ImportarCurriculoRequest`, `ConfirmarImportacaoRequest`,
  `CriarCompetenciaRequest`)
- `app/Http/Controllers/` (`ImportacaoCurriculoController`, `ConfirmacaoImportacaoController`,
  `CompetenciaController`)
- `app/Console/Commands/GerarCurriculosDemoCommand.php`
- `resources/views/importacoes/` (`create`, `show`, `expirada`)
- `resources/views/candidatos/partials/` (`_formulario`, `_catalogo-competencias`)
- `resources/js/modulos/importacao.js`
- `database/seeders/curriculos/` (`demo-curriculo.pdf`, `demo-escaneado.pdf`,
  `demo-duplicado.pdf`)
- `tests/Unit/ExtratorCurriculoTest.php`, `tests/Unit/LeitorPdfTest.php`,
  `tests/Unit/DTOs/DadosExtraidosDTOTest.php`, `tests/Feature/ImportacaoCurriculoTest.php` e
  `tests/js/importacao.test.js`

**Alterados**:

- repositories e interfaces de Candidato e Competência: `encontrar`, `criar`, `atualizar`,
  `origensDasCompetencias` e `sincronizarCompetencias`; `criar` de competência;
- `app/Models/Competencia.php` (slug único);
- `routes/candidatos.php`;
- `resources/views/components/campo.blade.php`;
- `resources/js/app.js`, `resources/js/modulos/ajax.js` e `resources/js/modulos/formularios.js`;
- `tests/Feature/BuscaCandidatosTest.php`;
- `specs/001-banco-de-talentos-mvp/tasks.md`.

## Como verificar

**Com Docker**:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\hackaton.ps1 demo
```

Depois, em http://localhost:8000 → **Importar currículo**:

1. Envie `database/seeders/curriculos/demo-curriculo.pdf` como **Estagiário**. A revisão traz:
   - Larissa Mendes;
   - Python, SQL, Git, Inglês intermediário, Trabalho em equipe e Comunicação marcados;
   - "Kubernetes" como sugestão;
   - área "Desenvolvimento".
2. Clique em **Adicionar ao catálogo** em "Kubernetes". A sugestão some e a nova opção aparece
   marcada no catálogo, sem perder o que você editou.
3. Tente **Confirmar e salvar** sem consentimento: o sistema bloqueia. Preencha o meio e a data
   e confirme: o perfil abre, e o candidato aparece na busca.
4. Envie `demo-duplicado.pdf`: aparece o alerta "Possível cadastro duplicado" (Ana Lima) com
   "Atualizar cadastro existente".
5. Envie `demo-escaneado.pdf`: aparece o aviso de PDF digitalizado e o formulário fica em
   branco para preenchimento manual.

Os PDFs já estão versionados. Para regenerá-los, rode `php artisan demo:curriculos` fora do
Docker ou no container de testes com as pastas montadas. Dentro da imagem, o código pertence ao
root e não pode ser gravado pelo artisan, que roda como www-data.

**Testes**: `.\hackaton.ps1 artisan test`. Testes JS: `node --test tests/js/*.test.js`
(Node 22).

## Observações para as próximas fases

- **US6** (edição): reutilize `candidatos/partials/_formulario` e
  `CadastroCandidatoService::atualizar()`. As competências mantidas conservam a origem.
- **US5** (anotações): competências observadas devem passar pela mesma precedência de origem
  (`OrigemCompetencia::prioridade()`).
- Fora do escopo: a listagem da US1 mostra "1 talentos encontrados" no singular. É um ajuste de
  texto em `candidatos/partials/_resultados.blade.php`.
