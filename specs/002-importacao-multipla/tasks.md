# Tasks: Importação múltipla, comparação com o PDF e leitura blindada

**Input**: Design documents from `/specs/002-importacao-multipla/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/importacao-lote.md, quickstart.md

**Tests**: incluídos. A constituição exige testes para regras críticas, e a leitura é sensível a regressões.

**Organization**: tarefas agrupadas por história de usuário, para cada uma ser implementada e testada isoladamente.

## Phase 1: Setup

- [X] T001 Em `Dockerfile`, subir `post_max_size` de `13M` para `110M` em `uploads.ini`, mantendo `upload_max_filesize=12M` (research R6)
- [X] T002 [P] Em `config/banco_talentos.php`, adicionar `importacao_max_arquivos` com valor `10`

---

## Phase 2: Foundational

**Purpose**: gerador de PDFs de teste com texto posicionado, usado pelas histórias.

- [X] T003 Em `database/seeders/Support/GeradorPdfCurriculo.php`, adicionar `gerarComPosicoes(array $trechos, bool $dentroDeFormulario = false)`, que grava cada trecho (`x`, `y`, `texto`, `tamanho`) na ordem recebida, opcionalmente dentro de um Form XObject

**Checkpoint**: gerador disponível para os testes.

---

## Phase 3: User Story 1 - Leitura correta de currículos com layout elaborado (Priority: P1) 🎯 MVP

**Goal**: currículos em colunas e fora de ordem no arquivo preenchem os campos certos.

**Independent Test**: enviar `demo-colunas.pdf` e conferir cada campo na revisão (quickstart, cenário A).

### Tests for User Story 1

- [X] T004 [P] [US1] Em `tests/Unit/LeitorPdfTest.php`, cobrir: duas colunas fora de ordem, Form XObject, datas à direita na linha da entrada, blocos lado a lado, `destaque` e recurso à ordem do arquivo
- [X] T005 [P] [US1] Em `tests/Unit/ExtratorCurriculoTest.php`, cobrir os casos de extração de `contracts/importacao-lote.md`

### Implementation for User Story 1

- [X] T006 [US1] Criar `app/Services/Curriculo/LeitorLayoutPdf.php`: percorrer comandos, matrizes e formulários; agrupar fragmentos; ordenar por XY-cut; devolver `texto` e `destaque` (research R1 a R3)
- [X] T007 [US1] Em `app/DTOs/ResultadoLeituraDTO.php`, adicionar `destaque: ?string`
- [X] T008 [US1] Em `app/Services/Curriculo/LeitorPdf.php`, usar o layout e recorrer ao `getText()` quando ele falhar ou recuperar menos de 80% dos caracteres visíveis (FR-003)
- [X] T009 [US1] Em `app/Services/Curriculo/ExtratorCurriculo.php`, aceitar `destaque`, validar o nome, unir nome em duas linhas e recusar cargos (FR-004)
- [X] T010 [US1] Em `app/Services/Curriculo/ExtratorCurriculo.php`, aceitar telefone com nono dígito separado (FR-005)
- [X] T011 [US1] Em `app/Services/Curriculo/ExtratorCurriculo.php`, restringir títulos a termos e qualificadores conhecidos, criar a seção `certificados` e tratar "Projeto:" embutido, com retorno à seção anterior (FR-006 a FR-008)
- [X] T012 [US1] Em `app/Services/Curriculo/ExtratorCurriculo.php`, reconstruir itens: unir linhas, associar datas e durações, dividir acima de 297 caracteres sem perda (FR-009)
- [X] T013 [US1] Em `app/Services/Curriculo/ImportacaoCurriculoService.php`, passar o `destaque` ao extrator
- [X] T014 [US1] Em `app/Console/Commands/GerarCurriculosDemoCommand.php`, gerar `demo-colunas.pdf`, fictício, e atualizar `tests/Feature/ImportacaoCurriculoTest.php`
- [X] T015 [US1] Conferir localmente os dois currículos reais de referência (SC-001), sem adicioná-los ao repositório

**Checkpoint**: leitura validada de forma independente.

---

## Phase 4: User Story 2 - Comparar o PDF com os dados durante a revisão (Priority: P1)

**Goal**: PDF ao lado do formulário, com "Abrir PDF" sempre disponível.

**Independent Test**: quickstart, cenário B.

### Tests for User Story 2

- [X] T016 [P] [US2] Em `tests/Feature/ImportacaoCurriculoTest.php`, cobrir: PDF `inline` com `private, no-store`, visitante redirecionado, outra sessão e importação encerrada com 404, revisão com `iframe` e "Abrir PDF"

### Implementation for User Story 2

- [X] T017 [US2] Em `routes/candidatos.php`, adicionar `GET /candidatos/importar/{importacao}/pdf` (`importacoes.pdf`) com `whereUuid`
- [X] T018 [US2] Em `app/Http/Controllers/ImportacaoCurriculoController.php`, adicionar `pdf()`, restrito à sessão (FR-012)
- [X] T019 [US2] Em `resources/views/importacoes/show.blade.php`, criar as duas colunas com pré-visualização fixa a partir de `xl`, "Abrir PDF" e os termos fora do catálogo abaixo do formulário, com o botão de confirmar associado por `form=` (FR-010, FR-011)

**Checkpoint**: comparação funcionando sem depender do lote.

---

## Phase 5: User Story 3 - Importar vários currículos e escolher como prosseguir (Priority: P2)

**Goal**: envio de até 10 PDFs, com modal, fila de revisão e cadastro sem revisão.

**Independent Test**: quickstart, cenários C e D.

### Tests for User Story 3

- [X] T020 [P] [US3] Criar `tests/Feature/ImportacaoLoteTest.php`: envio de 1 e de vários arquivos, limite de 10, arquivo inválido, modal, fila, pular, cadastro sem revisão com e sem consentimento, pendências com motivo, duplicidade (inclusive dentro do lote), descarte e isolamento por sessão
- [X] T021 [P] [US3] Em `tests/js/importacao.test.js`, cobrir a lista de arquivos selecionados e o limite no navegador

### Implementation for User Story 3

- [X] T022 [US3] Em `app/Http/Requests/ImportarCurriculoRequest.php`, validar `arquivos` (1 a `importacao_max_arquivos`) e `arquivos.*`, com as mensagens do contrato
- [X] T023 [US3] Criar `app/Http/Requests/CadastrarLoteRequest.php` com `consentimento_meio` e `consentimento_em`, usando as mesmas regras e mensagens da revisão
- [X] T024 [US3] Em `app/Services/Curriculo/ImportacaoCurriculoService.php`, adicionar o campo `lote` à importação (data-model)
- [X] T025 [US3] Criar `app/Services/Curriculo/LoteImportacaoService.php`: `criar`, `obter`, `resumo`, `posicao`, `motivoPendencia`, `marcarCadastrado`, `descartar` e `cadastrarSemRevisao` (data-model, aptidão)
- [X] T026 [US3] Em `app/Http/Controllers/ImportacaoCurriculoController.php`, tratar 1 ou vários arquivos em `store`, a posição do lote em `show` e adicionar `destroy`
- [X] T027 [US3] Em `app/Http/Controllers/ConfirmacaoImportacaoController.php`, marcar o item cadastrado e seguir para o próximo pendente ou para o resumo
- [X] T028 [US3] Criar `app/Http/Controllers/LoteImportacaoController.php` com `show` e `cadastrar`
- [X] T029 [US3] Em `routes/candidatos.php`, adicionar `importacoes.lotes.show`, `importacoes.lotes.cadastrar` e `importacoes.destroy`
- [X] T030 [US3] Em `resources/views/importacoes/create.blade.php`, usar `arquivos[]` com seleção múltipla e a lista de selecionados
- [X] T031 [P] [US3] Em `resources/js/modulos/importacao.js`, mostrar os arquivos selecionados e aplicar o limite
- [X] T032 [US3] Criar `resources/views/importacoes/partials/_escolha-lote.blade.php` e `resources/views/importacoes/lote.blade.php`, com modal, resumo e fallback sem JavaScript
- [X] T033 [US3] Em `resources/views/importacoes/show.blade.php`, mostrar "Currículo N de T", "Pular", "Voltar ao lote" e "Salvar e seguir para o próximo"

**Checkpoint**: lote completo nos dois caminhos.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T034 [P] Registrar a nova rota e o lote em `specs/001-banco-de-talentos-mvp/contracts/rotas-web.md` e `specs/001-banco-de-talentos-mvp/contracts/extracao-curriculo.md`, apontando para o contrato desta feature
- [X] T035 Reconstruir a imagem e executar o Pint nos arquivos alterados (`vendor/bin/pint --dirty --format agent`)
- [X] T036 Executar toda a suíte PHP e JS e o build de produção do frontend
- [X] T037 Validar `quickstart.md` no navegador, incluindo a revisão visual e os dois currículos reais

---

## Fora do plano original

- [X] T038 Isolar os testes do banco de desenvolvimento em `phpunit.xml` e `tests/TestCase.php`, e restaurar os dados de demonstração (research R7)
- [X] T039 Ensinar o parâmetro `{lote}` à verificação de CSRF em `tests/Feature/AjaxSegurancaTest.php`

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** → histórias.
- **US1** e **US2** são independentes e podem seguir em paralelo depois da Foundational.
- **US3** depende da tela de revisão de US2 (T033 edita o mesmo arquivo que T019) e se beneficia da leitura de US1.
- **Polish** depende de todas as histórias.

### Within Each User Story

Testes antes da implementação; serviços antes de controllers; controllers antes de views.

### Parallel Opportunities

- US1: T004 e T005.
- US3: T020, T021 e T031, em arquivos diferentes dos demais.

```text
# US1, testes em paralelo:
T004 tests/Unit/LeitorPdfTest.php
T005 tests/Unit/ExtratorCurriculoTest.php
```

## Implementation Strategy

1. **MVP**: Phases 1 a 3. A leitura correta já melhora a demonstração sozinha.
2. **Incremento**: US2. É a parte mais visual do pitch.
3. **Incremento**: US3. Só depois de a leitura estar confiável, para o lote não multiplicar erros.
4. Parar a cada checkpoint e validar a história isoladamente.
