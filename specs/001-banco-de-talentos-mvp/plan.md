# Implementation Plan: Banco de Talentos SETIC — MVP

**Branch**: `001-banco-de-talentos-mvp` | **Date**: 2026-10-30 | **Spec**: [spec.md](./spec.md)

**Planning revision**: 2026-10-31 — AJAX progressivo distribuído pelas Phases 2–10.

**Atualização de andamento**: 2026-11-01 — sincronizado com as marcações e evidências de
`tasks.md` até a Phase 9 (US7/energia), incluindo T149–T166.

**Input**: Feature specification from `/specs/001-banco-de-talentos-mvp/spec.md`

**Nota**: este plano mantém as decisões de design e acompanha a execução. `tasks.md` é a fonte
de verdade das marcações; os resultados até a Phase 8 reproduzem os registros anteriores.
A Phase 9 inclui nova execução das suítes PHP/JS, Pint e build em cópia isolada sem `.env`.
A branch de trabalho atual é `main`; o
identificador da feature continua sendo `001-banco-de-talentos-mvp`.

## Summary

Sistema web para centralizar currículos (PDF) de servidores e estagiários da SETIC, extrair
automaticamente formação, experiências e competências, pesquisar com filtros combinados,
cadastrar vagas com competências e pesos, ranquear candidatos com percentual de aderência
explicado, acompanhar a seleção em kanban com triagem anônima, registrar anotações de entrevista
e cumprir a LGPD (consentimento, correção, desativação, exclusão e retenção). O diferencial de
IA (padrão de energia) é opcional e isolado.

**Abordagem técnica**: monólito Laravel 13 renderizado no servidor (Blade + Tailwind v4), no
fluxo **Rota → Form Request → Controller → Service → Repository → Model**. A extração de
currículo é **determinística**: `smalot/pdfparser` + regras + catálogo de competências com
sinônimos. A aderência é uma fórmula pura (`CalculadoraAderencia`), calculada na leitura, o que
dá recálculo imediato sem cache. A triagem anônima é garantida por um DTO por contexto
(`PerfilNaVagaDTO`), rotas por código e bloqueio do PDF (403). A participação na seleção é
"virtual" até a primeira movimentação. A IA fica atrás da interface `AnalisadorEnergia`, com
implementação nula padrão, e recebe apenas texto anonimizado. A demonstração é preparada com um
único comando e dados 100% fictícios.

**Usabilidade**: Blade continua sendo a fonte do HTML, com `fetch` nativo para atualizar
regiões das telas e enviar formulários sem perder preenchimento em erros. As mesmas rotas
negociam JSON interno com fragmentos Blade ou navegação HTML tradicional. Não será criada
SPA, API pública, dependência nova ou camada paralela de regras de negócio (R21, FR-038–FR-042).

## Andamento e validação registrados — 2026-11-01

**170 de 173 tarefas marcadas como concluídas (98,3%)**. Faltam T012 e T148, ambas do usuário, e
a decisão T173. Os cenários A–H foram validados em Chromium headless, sem internet; isso **não equivale ao
aceite final das histórias**: continuam pendentes o ensaio humano cronometrado, o `demo` real e
a revisão visual em projetor previstos na constituição.

| Phase | Escopo / tarefas | Marcação | Evidência registrada |
|---|---|---|---|
| 1 — Setup | T001–T012 | 11/12 | Dependências e testes de infraestrutura; T012 manual pendente; `demo` real não executado |
| 2 — Fundação | T013–T044, T149–T151 | 35/35 | Base: 28 testes PHP / 105 asserções, Pint, seed, build e fumaça HTTP de login; complemento AJAX validado junto da Phase 3 |
| 3 — US1: busca e painel | T045–T062, T152–T154 | 21/21 | Após complemento AJAX: 71 testes PHP / 368 asserções, 25 JS, Pint e build |
| 4 — US2: importação | T063–T079, T155–T156 | 19/19 | 131 testes PHP / 622 asserções, 32 JS, Pint, build e fumaça HTTP B |
| 5 — US3: vagas e ranking | T080–T095, T157–T158 | 18/18 | 175 testes PHP / 888 asserções, 38 JS, Pint, build e fumaça HTTP C |
| 6 — US4: triagem e kanban | T096–T112, T159–T160 | 19/19 | 222 testes PHP / 1175 asserções, 42 JS, Pint, build e fumaça HTTP D; drag-and-drop implementado |
| 7 — US5: anotações | T113–T121, T161–T162 | 11/11 | 240 testes PHP / 1296 asserções, 44 JS, Pint, build e fumaça HTTP E |
| 8 — US6: LGPD | T122–T129, T163–T164 | 10/10 | 262 testes PHP / 1517 asserções, 47 JS (também Node 22), Pint, rotas, `git diff --check` e build |
| 9 — US7: energia (opcional, incluída) | T130–T141, T165–T166 | 14/14 | 311 testes PHP / 1731 asserções, 61 JS (Node 22, inclui menu paralelo), Pint, rotas e build; API simulada; cenário G/manual ainda sem aceite |
| 10 — Polimento e validação final | T142–T148, T167–T173 | 12/14 | 353 testes PHP / 2147 asserções, 126 JS, Pint; A–H em Chromium headless sem internet e axe sem violações; T148 (ensaio humano e `demo` real) e T173 (dependência do Vite no `demo`) pendentes |

Os totais de testes são fotografias **cumulativas** de cada checkpoint, não devem ser somados.
A evidência mais recente é a da Phase 10, nas notas de `tasks.md`. As validações registram
execução isolada sem disponibilizar o `.env` aos comandos.

**Fontes**: [tasks.md](./tasks.md) (notas das Phases 1–9 e complemento AJAX das Phases 2/3),
[fundação](../implementacoes/fase-2-fundacao.md),
[importação](../implementacoes/fase-4-importacao.md),
[vagas/ranking](../implementacoes/fase-5-vagas-ranking.md),
[triagem/kanban](../implementacoes/fase-6-triagem-kanban.md) e
[anotações](../implementacoes/fase-7-anotacoes.md). A evidência da Phase 8 está nas próprias
notas de `tasks.md`, assim como a da Phase 9.

### Pendências e próximo ponto de retomada

1. **T012 — usuário**: confirmar localmente a configuração prevista no quickstart. A IA não
   consulta nem altera o `.env`; a tarefa não pode ser marcada por inferência.
2. **T148 — usuário**: rodar `.\hackaton.ps1 demo` (com internet, pois reconstrói a imagem) e
   ensaiar o fluxo completo em ≤ 5 minutos, com e sem internet, em Chrome ou Edge, com revisão
   visual em projetor. SC-005 e SC-006 dependem desse ensaio. SC-007, SC-010 e SC-011 têm
   evidência automatizada em Chromium headless, registrada em `tasks.md`, sem aceite humano.
3. **T173 — decisão**: o `demo` depende do container `vite` no ar; parado, as telas ficam sem estilo
   ou respondem 500. Definir antes do pitch se isso muda.
4. **API real (opcional)**: a IA foi validada só com analisador falso, `Http::fake` e falha de
   conexão real. Habilitação e chave ficam a cargo do usuário, sem acesso da IA ao `.env`.

### Ajuste de organização solicitado após a Phase 3

Esta convenção está consolidada na estrutura abaixo e nas tarefas e deve ser seguida nas
próximas implementações, sem alterar os requisitos funcionais:

- Cada repository tem uma interface própria em
  `app/Repositories/Interfaces/{Entidade}RepositoryInterface.php` e implementação em
  `app/Repositories/{Entidade}Repository.php`, sem prefixo ou subpasta `Eloquent`.
  `RepositoryServiceProvider` registra os vínculos interface → implementação.
- As operações de candidato ficam centralizadas em `app/Services/CandidatoService.php`.
  `BuscaCandidatosService` foi incorporada a ela; o painel usa `app/Services/PainelService.php`.
  Controllers não executam consultas de candidato nem carregam seus relacionamentos diretamente.
- DTOs ficam em `app/DTOs/` e seus nomes terminam em `DTO`.
  `app/DTOs/FiltroCandidatosDTO.php` mantém os dados e o comportamento do filtro; não é uma
  service. A mesma convenção vale para os DTOs previstos nas próximas fases.
- Form Requests continuam responsáveis pela validação/autorização da entrada HTTP.
  `BuscarCandidatosRequest` transforma somente os dados validados no DTO; `CandidatoService`
  coordena a busca e delega as consultas ao repository. As respostas HTTP continuam nos controllers.

### Ajustes consolidados nas Phases 4–8

- `CompetenciaService` e `VagaService`, na raiz de `app/Services`, coordenam catálogo e vagas
  sem acesso direto dos controllers aos repositories. `Curriculo/NormalizadorTexto` é
  compartilhado pela extração e pelo catálogo.
- O escopo de retenção é `comRetencaoVencida()` (o acessor já usa `retencaoVencida`). Upload
  acima do limite de POST responde com página HTML 413 ou erro JSON, sem depender de flash
  antes da sessão. Importação expirada tem tela própria e resposta 404.
- Atualização por duplicidade exige correspondência de e-mail/CPF; mantém código, histórico
  e precedência da origem das competências. O PDF anterior só é removido após commit.
- `RankingVagaService::classificar` alimenta o quadro; `ItemRankingDTO` inclui `desativado`
  e `elegivel`. Mover para a mesma etapa não duplica histórico; retorno à triagem substitui
  a região completa e o título da aba nunca mantém o nome do candidato.
- Anotações na triagem recebem 403 em `RegistrarAnotacaoRequest::authorize`, antes da
  validação. Vaga encerrada não aceita edição, movimentação ou anotação pelo perfil na vaga;
  anotações continuam possíveis pela gestão do banco. Encerramento é idempotente.
- No perfil da vaga, `competencias-candidato` atualiza também a aderência. O formulário de
  anotação fica fora dos fragmentos substituídos e só é limpo após sucesso.
- LGPD reutiliza o cadastro/formulário de importação; campos comuns ficam em
  `AtualizarCandidatoRequest`. Exclusão e registro mínimo passam pelo repository, em
  transação coordenada por `ExclusaoCandidatoService`; o PDF é removido após commit.
  Desativar não renova retenção; contagem/filtro de retenção consideram somente ativos vencidos.

### Decisões e entrega da Phase 9 — 2026-11-01

- **Escopo:** US7 incluída por solicitação explícita; T130–T141 e T165–T166 implementadas.
  As Phases 1–8 foram preservadas e nenhuma tarefa da Phase 10 foi marcada por antecipação.
- **Integração:** `ClaudeAnalisadorEnergia` implementa o plano B de T001 via `Http` do Laravel,
  Messages API com `output_config.format`/`json_schema`, `effort=low` e `max_tokens=2000`.
  Documentação Laravel 13 e da API Anthropic consultada. Conexão: 3 s; resposta: timeout
  configurável, padrão 20 s. Não segue redirects nem repete o POST automaticamente (zero
  tentativas adicionais, dentro do máximo de uma previsto), evitando chamadas/custos duplicados.
  Recusa, resposta incompleta, JSON/esquema inválido, erro HTTP e conexão resultam em `null`.
  Logs guardam somente motivo/status, sem currículo, chave, corpo de resposta ou exceção.
- **Arquitetura:** `PerfilEnergiaService` coordena análise, ajuste, anonimização e proximidade.
  Upsert e seleção de candidatos ficam no `CandidatoRepository`, pela interface existente;
  nenhuma nova entidade, migration, dependência, worker ou pasta-base foi necessária.
  `AnalisarEnergiaCommand` seleciona ativos sem perfil; `--todos` também inclui origem `ia`,
  nunca `humano`. Atualização de energia não renova a retenção do cadastro.
- **Privacidade:** entrada da IA contém somente formação, experiências, projetos e nomes das
  competências limpos por `LimpadorTexto`. Não usa texto bruto, PDF, contatos, links ou código.
  A justificativa da IA é limpa antes de persistir; a manual é limpa antes da exibição na vaga.
  `PerfilNaVagaDTO` recebe `SugestaoEnergiaDTO`, origem, data e proximidade, não o model de energia
  com o id do candidato/revisor. O retorno à triagem também é testado no fragmento JSON.
- **UI:** mantém os tokens/componentes do design system; réguas de 1–5 e selo complementar,
  sem misturar energia e aderência. Vaga aceita todos os eixos ou nenhum, com “Não definir”.
  Perfil na vaga é somente leitura; análise e ajuste ficam no banco. Modal com fallback
  `noscript` e erros locais; sucesso troca apenas `secao-energia`, falha 503 mantém perfil
  anterior. Transporte genérico já atende loading, foco, duplo envio e ausência de replay;
  `ajax.js` não impõe um timeout menor e foi preservado.
- **Ranking:** ambos os caminhos (`ranquear` e `classificar` → quadro) preenchem a proximidade;
  a função de comparação não mudou. Repositories carregam `perfilEnergia` com as competências
  para evitar N+1. Selos refletem mudanças na próxima consulta explícita, sem sincronizar abas.
- **Evidências:** 46 testes PHP iniciais falharam antes do código; três regressões adicionais
  completam 49 testes novos. Suíte final: **311 PHP / 1731 asserções**, **61 JS em Node 22**,
  Pint e build Vite aprovados. `route:list --path=energia` confirma POST/PUT autenticados.
  Testes usam analisador falso/`Http::fake` e dados fictícios; não houve chamada real da API.
  O build apenas avisou sobre `fontaine` opcional, não instalado; compilação concluída.
  A primeira execução JS tinha 49 testes (incluindo dois novos da energia); a checagem final
  incluiu 12 testes das alterações paralelas de menu/layout, preservadas e fora do escopo US7.
- **Aceite pendente:** G/H em navegador real, API real opcional, offline, revisão visual e
  projetor. Testes automatizados não substituem esses gates nem aprovam SC-010/SC-011.

## Technical Context

**Language/Version**: PHP 8.5 (restrição `^8.3` no Composer), Laravel 13 (`^13.17`); JavaScript
ES modules (Vite 8); Tailwind CSS v4.

**Primary Dependencies**: já instaladas — `laravel/framework`, `laravel-vite-plugin`,
`@tailwindcss/vite`. **Novas, aprovadas em 2026-10-30** (ver *Complexity Tracking*):
`smalot/pdfparser` (leitura de PDF) e `phpunit/phpunit` (dev, gates de teste).
Instaladas no setup: PDFParser 2.13.1 e PHPUnit 13.4.5, conforme as notas de implementação.
`mockery/mockery` também foi adicionado em desenvolvimento na Phase 2, exigido por
`RefreshDatabase` (justificativa em *Complexity Tracking*).
O SDK `anthropic-ai/sdk` foi recusado; a História 7 usará o cliente HTTP nativo do Laravel.

**Storage**: SQLite (local e testes, em memória) / MySQL 8.4 (Docker); PDFs no disco privado
`curriculos` (`storage/app/private/curriculos`, volume `app-storage` no Docker); sessão
`database` para o fluxo de importação.

**Testing**: PHPUnit — testes de feature (HTTP, `RefreshDatabase`, `Storage::fake`, analisador
de energia falso) e unitários (calculadora de aderência, extrator, proximidade). Contratos AJAX
cobertos nas suítes de cada história; testes JavaScript pelo runner nativo do Node em `tests/js/`,
sem pacotes adicionais, e validação manual de foco, histórico, rede lenta e fallback sem JS.

**Frontend interaction**: `fetch` + `FormData`, `AbortController`, History API e delegação de
eventos. Debounce de 400 ms, descarte de respostas obsoletas, feedback acessível, bloqueio de
duplo envio, erros 422 junto aos campos e nenhuma repetição automática de escritas.

**Target Platform**: navegadores desktop (Chrome/Edge) e projetor ≥ 1280×720; servidor em
container Linux (`php:8.5-apache`) ou `php artisan serve` em Windows/Linux.

**Project Type**: aplicação web monolítica renderizada no servidor (MVC + Services +
Repositories).

**Performance Goals**: ranking da vaga em < 3 s (SC-003; alvo < 500 ms com 200 candidatos);
busca com filtros em < 1 s; leitura e extração de um PDF de 2 páginas em < 5 s.

**Constraints**: funcionamento offline para as Histórias 1–6 (SC-007); PDF com texto
selecionável ≤ 10 MB; interface em pt-BR; WCAG 2.1 AA nos elementos principais; anonimato na
triagem; o `.env` não é acessado por agentes de IA (configurações via `.env.example` e
instruções ao usuário).

**Scale/Scope**: demonstração com 24 candidatos e 3–4 vagas; 1–3 recrutadores; dimensionado
para até ~2 mil candidatos sem mudanças; ~14 telas; prazo de entrega **2026-11-03**.

Nenhum item ficou como NEEDS CLARIFICATION. As decisões estão em [research.md](./research.md)
(R1–R21).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Restrição | Avaliação | Como o plano atende |
|---|---|---|
| **I. Demo-First** | ✅ Passa | Histórias = fatias verticais na ordem do roteiro; seeders deixam cada história demonstrável sozinha; comando único `demo` (R15); ensaio cronometrado no quickstart |
| **II. Simplicidade Radical** | ⚠️ Passa com justificativas | Etapas fixas, pesos 1–3, catálogo fechado, enums em vez de tabelas configuráveis (R19), sem SPA, Alpine ou bibliotecas de UI. PDFParser, PHPUnit, suporte de testes Mockery, regra de níveis de idioma e pastas padrão do Laravel registrados em *Complexity Tracking* |
| **III. Design Apresentável** | ✅ Passa | Tokens Tailwind + componentes Blade (R16); servidor/estagiário com cor **e** texto; `barra-score`; estados vazio/carregando/erro; AJAX sem perda de foco/preenchimento, `aria-busy` e anúncios acessíveis (R21); fonte base maior para projetor |
| **IV. LGPD e Triagem Anônima** (inegociável) | ✅ Passa | DTO por contexto, rotas por código, PDF 403 na triagem, identidade revelada por vaga (R8–R10, [visibilidade](./contracts/visibilidade-por-contexto.md)); consentimento obrigatório; correção, desativação, exclusão definitiva com registro mínimo; retenção visível (R14); disco privado (R20); só dados fictícios. Limitação de perfil único documentada e aceita pela spec |
| **V. IA como Diferencial** | ✅ Passa | Ranking 100% determinístico; extração sem IA; `AnalisadorEnergia` com implementação nula por padrão; falha → "análise indisponível"; sugestões editáveis; entrada anonimizada (R13) |
| **VI. Explicabilidade** | ✅ Passa | Explicação obrigatória em todo percentual; "não identificado" ≠ "não possui"; soft skills "declaradas" × "observadas"; movimentações sempre humanas e registradas (R7) |
| **Stack e arquitetura** | ✅ Passa | Laravel 13 + Blade + Tailwind v4; Services para regras (score, anonimização, extração); contratos dos repositories vinculados no `RepositoryServiceProvider` |
| **Segurança do `.env`** | ✅ Passa | Novas chaves só no `.env.example`; instruções para o usuário; nenhum passo do plano lê, cria ou altera o `.env` |
| **Gates de qualidade** | ⚠️ Parcialmente validados | Testes de aderência, anonimato e PDF, suítes PHP/JS, Pint e build aprovados até a Phase 9; revisão no navegador/projetor, segurança transversal e ensaio final pendentes |

**Resultado (pré-pesquisa)**: aprovado, com as justificativas registradas.
**Reavaliação pós-design (Fase 1)**: aprovado. O design não introduziu violações novas. A
decisão de participação virtual (R8) e o DTO de visibilidade (R9) *reduziram* a complexidade e o
risco de vazamento em relação às alternativas.

**Reavaliação da revisão AJAX (2026-10-31)**: aprovada com justificativa de esforço em
*Complexity Tracking*. Sem dependências, entidades ou migrations novas; fallback HTML mantém
as histórias demonstráveis sem JS e sem internet (servidor local disponível). Autenticação,
CSRF e matriz de visibilidade se aplicam também a fragmentos/mensagens. Nenhum gate é adiado
ao polimento; cada integração AJAX tem testes e cenário manual na própria fase.

**Reavaliação de andamento (2026-11-01)**: evidências automatizadas registradas até a Phase 9,
sem novas dependências ou migrations nas Phases 4–9. A US7 usa o plano B aprovado e respeita
anonimato/decisão humana, com ajuste manual independente da IA. O desenho continua alinhado à
constituição, mas o aceite integral está **pendente**: não há registro de execução dos gates
manuais das histórias, e T167/T168/T148 seguem desmarcadas.
Não interpretar `[X]` de implementação como dispensa desses gates.

## Project Structure

### Documentation (this feature)

```text
specs/001-banco-de-talentos-mvp/
├── plan.md                       # Este arquivo
├── research.md                   # Fase 0 — decisões R1–R21
├── data-model.md                 # Fase 1 — tabelas, enums, estados, validações
├── quickstart.md                 # Fase 1 — preparo e roteiro de validação
├── contracts/
│   ├── rotas-web.md              # Rotas, requests, respostas e códigos
│   ├── visibilidade-por-contexto.md  # Matriz LGPD/anonimato + invariantes testáveis
│   ├── extracao-curriculo.md     # Leitura do PDF, DadosExtraidosDTO, estados da revisão
│   └── analise-energia.md        # Interface de IA, esquema JSON, falhas, proximidade
├── checklists/requirements.md    # (existente)
└── tasks.md                      # Fase 2 — gerado por /speckit-tasks
```

### Source Code (repository root)

A árvore reflete a estrutura implementada até a Phase 9, incluindo os services, DTO,
componente, controller, comando e testes de energia. Polimento/aceite final seguem pendentes.

```text
app/
├── Console/Commands/
│   ├── GerarCurriculosDemoCommand.php     # demo:curriculos (implementado)
│   └── AnalisarEnergiaCommand.php          # energia:analisar {--todos} (implementado)
├── DTOs/                                   # FiltroCandidatosDTO, ResultadoLeituraDTO, DadosExtraidosDTO,
│                                           # ResultadoAderenciaDTO, ItemRankingDTO, PerfilNaVagaDTO, SugestaoEnergiaDTO
├── Enums/                                  # TipoCandidato, TipoCandidatoVaga, Area, CategoriaCompetencia,
│                                           # OrigemCompetencia, StatusCandidato, StatusVaga, EtapaSelecao,
│                                           # PesoCompetencia, MeioConsentimento, OrigemPerfilEnergia, MotivoExclusao
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── PainelController.php
│   │   ├── CandidatoController.php         # index, show, edit, update, destroy
│   │   ├── CandidatoStatusController.php
│   │   ├── CurriculoController.php         # PDF na gestão do banco
│   │   ├── ImportacaoCurriculoController.php
│   │   ├── ConfirmacaoImportacaoController.php
│   │   ├── AnotacaoController.php
│   │   ├── CompetenciaController.php
│   │   ├── PerfilEnergiaController.php
│   │   ├── VagaController.php
│   │   ├── VagaStatusController.php
│   │   ├── ParticipacaoController.php      # perfil no contexto da vaga
│   │   ├── ParticipacaoEtapaController.php
│   │   ├── ParticipacaoCurriculoController.php
│   │   └── ParticipacaoAnotacaoController.php
│   └── Requests/                           # LoginRequest, BuscarCandidatosRequest, ImportarCurriculoRequest,
│                                           # ConfirmarImportacaoRequest, AtualizarCandidatoRequest,
│                                           # AlterarStatusCandidatoRequest, ExcluirCandidatoRequest,
│                                           # RegistrarAnotacaoRequest, CriarCompetenciaRequest,
│                                           # SalvarVagaRequest, MoverEtapaRequest, AjustarPerfilEnergiaRequest
├── Models/                                 # User, Candidato, Competencia, Vaga, Participacao, MovimentacaoEtapa,
│                                           # Anotacao, PerfilEnergia, RegistroExclusao
├── Repositories/
│   ├── Interfaces/                         # CandidatoRepositoryInterface, CompetenciaRepositoryInterface,
│   │                                       # PainelRepositoryInterface, VagaRepositoryInterface,
│   │                                       # ParticipacaoRepositoryInterface, AnotacaoRepositoryInterface
│   ├── CandidatoRepository.php
│   ├── CompetenciaRepository.php
│   ├── PainelRepository.php
│   ├── VagaRepository.php
│   ├── ParticipacaoRepository.php
│   └── AnotacaoRepository.php
├── Services/
│   ├── CandidatoService.php                # busca, catálogo, detalhamento, currículo, contagens e duplicidade
│   ├── CompetenciaService.php              # catálogo e inclusão de competências
│   ├── PainelService.php                   # resumo via CandidatoService e PainelRepositoryInterface
│   ├── VagaService.php                     # catálogo, CRUD, encerramento e dados do quadro
│   ├── Curriculo/                          # LeitorPdf, ExtratorCurriculo,
│   │                                       # NormalizadorTexto, ImportacaoCurriculoService, CadastroCandidatoService
│   ├── Aderencia/                          # CalculadoraAderencia, RankingVagaService
│   ├── Selecao/                            # SelecaoService, VisibilidadeCandidatoService, LimpadorTexto
│   ├── Anotacoes/                          # AnotacaoService
│   ├── Lgpd/                               # ExclusaoCandidatoService
│   └── Energia/                            # AnalisadorEnergia (interface), ClaudeAnalisadorEnergia,
│                                           # AnalisadorEnergiaIndisponivel, PerfilEnergiaService, ProximidadeEnergia
└── Providers/
    ├── AppServiceProvider.php              # vínculo de AnalisadorEnergia conforme a configuração
    └── RepositoryServiceProvider.php       # vínculos RepositoryInterface → Repository

bootstrap/app.php                           # alias de middleware, tratamento de PostTooLargeException
config/
├── banco_talentos.php                      # retencao_anos, limiar de texto, energia (habilitada, timeout)
├── filesystems.php                         # novo disco privado "curriculos"
└── services.php                            # anthropic (key, model)

database/
├── factories/                              # CandidatoFactory, CompetenciaFactory, VagaFactory, AnotacaoFactory…
├── migrations/                             # uma migration por tabela de data-model.md
└── seeders/
    ├── DatabaseSeeder.php
    ├── UsuarioSeeder.php · CompetenciaSeeder.php · CandidatoSeeder.php · VagaSeeder.php
    ├── Support/GeradorPdfCurriculo.php     # escritor mínimo de PDF de texto (seeders e testes)
    └── curriculos/                         # PDFs do envio ao vivo: demo-curriculo, demo-escaneado, demo-duplicado

lang/pt_BR/validation.php                   # mensagens e atributos em português

resources/
├── css/app.css                             # tokens do design system (@theme)
├── js/app.js                               # ponto de entrada; importa js/modulos/{formularios,chips,kanban,busca,importacao}.js
│                                           # ajax.js é compartilhado pelos módulos de interação
└── views/
    ├── layouts/app.blade.php · layouts/visitante.blade.php
    ├── components/                         # botao, card, badge, badge-tipo, tag-competencia, campo, modal,
    │                                       # barra-score, estado-vazio, alerta, cartao-estatistica,
    │                                       # cabecalho-pagina, eixos-energia, icone
    ├── auth/login.blade.php
    ├── painel/index.blade.php
    ├── candidatos/ (index, show, edit, partials/_formulario, partials/_card, partials/_resultados, partials/_competencias, partials/_secao-energia, partials/_formulario-energia)
    ├── importacoes/ (create, show, expirada)
    ├── anotacoes/ (_formulario, _historico)
    ├── vagas/ (index, create, edit, show, partials/_formulario, partials/_card-ranking, partials/_coluna, partials/_quadro)
    ├── participacoes/ (show, partials/_perfil, partials/_competencias-na-vaga)
    └── errors/ (403, 404, 413)             # identidade protegida, não encontrado e limite de upload

routes/
├── web.php                                 # login/logout, painel; inclui os arquivos abaixo no grupo auth
├── candidatos.php                          # rotas do banco, importação, LGPD, anotações e energia (blocos por história)
└── vagas.php                               # rotas de vagas e seleção (blocos por história)

tests/
├── TestCase.php
├── Unit/                                   # CalculadoraAderenciaTest, ExtratorCurriculoTest, ProximidadeEnergiaTest
├── js/                                     # ajax, busca, chips, formularios, importacao e kanban; runner nativo Node
└── Feature/                                # AutenticacaoTest, PainelTest, BuscaCandidatosTest, ImportacaoCurriculoTest,
                                            # VagaTest, RankingVagaTest, TriagemAnonimaTest, CurriculoProtegidoTest,
                                            # MovimentacaoEtapaTest, AnotacaoEntrevistaTest, LgpdTest, PerfilEnergiaTest, ClaudeAnalisadorEnergiaTest

Dockerfile                                  # + conf.d/uploads.ini (upload 12M / post 13M)
hackaton · hackaton.ps1                     # + comando "demo" (setup + migrate:fresh --seed --force)
.env.example                                # + APP_LOCALE=pt_BR, ENERGIA_IA_*, ANTHROPIC_*
phpunit.xml                                 # SQLite em memória, IA desligada
```

**Structure Decision**: projeto único Laravel, mantendo as pastas já existentes
(`app/Http`, `app/Models`, `app/Services`, `app/DTOs`, `app/Repositories`,
`app/Repositories/Interfaces`, `resources/views/layouts`). DTOs usam o sufixo `DTO` e ficam
fora de Services. Interfaces dos repositories usam o sufixo `RepositoryInterface`; as
implementações ficam na raiz de `app/Repositories`, sem prefixo `Eloquent`. `CandidatoService`
e `PainelService`, assim como `CompetenciaService` e `VagaService`, ficam na raiz de
`app/Services`; os demais services são agrupados por módulo
de domínio em subpastas. As pastas novas (`app/Enums`, `app/Console/Commands`, `lang/`, `tests/`,
`database/seeders/Support`, `database/seeders/curriculos`) são convenções padrão do Laravel e
estão registradas em *Complexity Tracking*.

## Estratégia de Entrega

Ordem guiada pela prioridade da spec e pelo roteiro do pitch. Cada incremento termina
demonstrável e passa pelos gates (testes, Pint, fluxo manual, revisão visual).

| # | Incremento | Conteúdo | Pronto quando |
|---|---|---|---|
| 0 | **Fundação** | Aprovação e instalação das dependências; PHPUnit; enums; migrations; models e factories; disco `curriculos`; `lang/pt_BR`; login; layout, tokens e componentes Blade; seeders de usuário e catálogo; comando `demo`; ajuste de upload no Dockerfile | Login funciona, layout aplicado, `demo` sobe o ambiente com dados |
| 1 | **US1 — Busca e painel (P1)** | `CandidatoSeeder` com PDFs gerados; painel; listagem com filtros combinados (E), contagem, cards, estado vazio; perfil completo | Cenário A do quickstart; `BuscaCandidatosTest` |
| 2 | **US2 — Importação de PDF (P1)** | `LeitorPdf`, `ExtratorCurriculo`, fluxo em duas etapas, revisão, consentimento, duplicidade, PDF ilegível, sugestões de catálogo; PDFs de demonstração | Cenário B; `ExtratorCurriculoTest`, `ImportacaoCurriculoTest` |
| 3 | **US3 — Vagas e ranking (P2)** | CRUD de vaga com pesos hard/soft; `CalculadoraAderencia`; `RankingVagaService`; cards com explicação; `VagaSeeder` | Cenário C; `CalculadoraAderenciaTest`, `RankingVagaTest` |
| 4 | **US4 — Kanban e triagem anônima (P2)** | Quadro de 3 colunas; `PerfilNaVagaDTO`; rotas por código; PDF 403; movimentação com histórico; vaga encerrada; drag-and-drop (polimento) | Cenário D; `TriagemAnonimaTest`, `CurriculoProtegidoTest`, `MovimentacaoEtapaTest` |
| 5 | **US5 — Anotações (P3)** | Anotações com vaga opcional e competências observadas; histórico | Cenário E; `AnotacaoEntrevistaTest` |
| 6 | **US6 — LGPD (P3)** | Consentimento e retenção no perfil; filtro de retenção; desativar e reativar; exclusão definitiva com registro | Cenário F; `LgpdTest` |
| 7 | **US7 — Energia (P4)** | Eixos na vaga; `AnalisadorEnergia` + cliente HTTP do Laravel; ajuste manual; proximidade; comando `energia:analisar` | Cenário G; `PerfilEnergiaTest`, `ProximidadeEnergiaTest` |
| 8 | **Polimento e ensaio** | Revisão visual em projetor, microinterações, estados de carregamento, ensaio cronometrado online e offline | SC-005 cumprido em ≤ 5 min |

### AJAX obrigatório dentro das fases de implementação

Esta tabela complementa os incrementos acima e usa a numeração **Phase** de `tasks.md`
(não a numeração de incrementos 0–8). Preservar T001–T148 e seus estados; as novas tarefas
T149–T168 foram distribuídas nas fases, em ordem de dependência. No estado atual,
T149–T168 estão implementadas.

| Phase / incremento | Entrega AJAX | Tarefas / estado em 2026-11-01 | Critério adicional de pronto |
|---|---|---|---|
| 1 / setup | Sem pacote novo ou alteração de ambiente | — | Reutilizar Laravel/Node instalados |
| 2 / fundação | Transporte `fetch`, formulários progressivos, JSON/erros e anúncios acessíveis | T149–T151 — implementadas | Testes JS de transporte; fallback HTML preservado |
| 3 / US1 | Busca, filtros, limpar filtros e paginação parciais; URL/histórico, foco e concorrência | T152–T154 — implementadas | Cenário A + SC-010, contrato de busca HTML/JSON |
| 4 / US2 | Upload e confirmação assíncronos com navegação no sucesso; inclusão de competência sem perder revisão | T155–T156 — implementadas | Cenário B sem perda de campos/seleções em 422 ou inclusão no catálogo |
| 5 / US3 | Criar/editar vaga com validação local; atualizar ranking e encerrar vaga sem recarregar | T157–T158 — implementadas | Cenário C; HTML e JSON usam a mesma fórmula e ordenação |
| 6 / US4 | Mover por botão via AJAX; atualizar quadro/perfil, contagens, identidade e histórico | T159–T160 — implementadas | Cenário D; anonimato verificado no JSON/HTML e retorno à triagem |
| 7 / US5 | Registrar anotação e atualizar histórico/competências, limpando somente após sucesso | T161–T162 — implementadas | Cenário E no banco e na vaga revelada; triagem continua 403 |
| 8 / US6 | Correção com erros locais; desativar/reativar parcial; excluir confirmado com navegação após sucesso | T163–T164 — implementadas | Cenário F; filtros de retenção continuam no fluxo AJAX da US1 |
| 9 / US7 | Analisar/ajustar energia no perfil do banco, com loading e indisponibilidade sem bloquear a tela | T165–T166 — implementadas | Cenário G; falha mantém perfil anterior e ranking principal; gate manual pendente |
| 10 / polimento | Segurança, falhas, acessibilidade, sem JS e ensaio completo | T167–T168 — implementadas | SC-010/SC-011 com evidência automatizada; nenhuma escrita repetida automaticamente; ensaio T148 pendente |

**Retomada atual**: não refazer T149–T172 nem as histórias US1–US7. Resta o ensaio T148, a cargo
do usuário, conforme a seção de andamento. A coluna de critérios continua representando o aceite
exigido, não uma declaração de aprovação manual.

**Limites**: navegação principal/login/logout/PDF continuam tradicionais. Sucesso de upload,
confirmação, criação/edição e exclusão pode navegar por `redirect`; não é uma SPA. Ações locais
atualizam fragmentos conhecidos do servidor, sem serializar models. Falhas ambíguas de escrita
exigem conferir estado por GET, não fallback/retry automático. Não há polling, fila nova ou
atualização entre abas; os demais quadros refletem mudanças na próxima leitura explícita.

**Contrato e validação**: [rotas-web](./contracts/rotas-web.md),
[visibilidade](./contracts/visibilidade-por-contexto.md), cenários A–H do quickstart. Modelo de
dados permanece inalterado.

**Cronograma atualizado até a apresentação (ter 03/11/2026)**:

| Dia | Foco |
|---|---|
| Sex 30/10 | Setup implementado (T001–T011); T012 manual ainda pendente |
| Sáb 31/10 | Fundação, US1, complemento AJAX T149–T154 e US2 implementados e testados; gates de navegador pendentes |
| Dom 01/11 | US3, US4, US5 (banco e vaga), US6 e US7 implementadas, incluindo AJAX; validação automatizada até Phase 9 registrada |
| Seg 02/11 | Meta: validar A–H no navegador; conferir API real opcional; fechar polimento e T167/T168; ensaio T148 após os gates |
| Ter 03/11 | Meta: ensaio final e apresentação; não há aceite antecipado |

**Trabalho em dupla**: a implementação é dividida em duas trilhas paralelas. A **trilha A**
("Banco, Vagas e Seleção") faz esquema, US1, US3 e US4. A **trilha B** ("Interface, Currículo
e LGPD") faz design system, US2, US6, US5 e US7. São cinco rodadas, com pontos de
sincronização no `main`. Para evitar conflitos de merge, cada arquivo tem um dono, e o plano
inclui três ajustes estruturais:

- rotas separadas por módulo (`routes/candidatos.php`, `routes/vagas.php`), com blocos por
  história;
- seções do perfil do candidato em *partials* por história;
- JavaScript em módulos.

Rodadas, donos de arquivos e combinados de Git estão em
[tasks.md → Trabalho em dupla](./tasks.md#trabalho-em-dupla-duas-trilhas-em-paralelo).
Essa divisão permanece como referência de coordenação, não como lista de trabalho ainda por
fazer: US5 já foi fechada nos dois contextos e T111 já foi implementada. Os relatos das
Phases 6/7 registram execução das duas trilhas com a dupla offline.

**Riscos principais e mitigação**:

| Risco | Mitigação |
|---|---|
| Extração imprecisa em PDFs com layout complexo | PDFs de demonstração em coluna única, testados antes; revisão humana; sinônimos no catálogo |
| Vazamento de identidade na triagem | DTO com campos nulos + testes de invariantes ([visibilidade](./contracts/visibilidade-por-contexto.md)) |
| Rede instável no pitch | Histórias 1–6 offline; perfis de energia pré-gravados com `energia:analisar` |
| Prazo curto | US1–US7 e drag-and-drop implementados; não acrescentar escopo antes dos gates de segurança e ensaio; energia pode ser demonstrada com ajuste manual/perfis pré-gravados |
| AJAX aumenta o esforço e pode perder estado | Transporte único e integrações T149–T166 já entregues; validar navegador/fallback e segurança transversal, sem SPA/polling |
| Respostas fora de ordem ou rede falha após gravar | Invalidar GET ao editar filtros, cancelar/descartar leitura antiga; confirmar interface só após sucesso; conferir estado por GET antes de repetir escrita |
| Dados revelados retidos após retorno à triagem | Substituir quadro/perfil completo com DTO do estado final; testar fragmentos e mensagens; não cachear respostas pessoais |
| Desvio das dependências aprovadas | PDFParser e PHPUnit já instalados; Mockery registrado como suporte aos testes; manter IA com `Http` do Laravel, sem instalar SDK recusado nem adicionar dependências sem aprovação |
| Confundir tarefa implementada com história aceita | Manter gates manuais A–F/H explícitos; T167/T168/T148 continuam pendentes apesar das suítes aprovadas |

## Complexity Tracking

> Desvios do princípio II (Simplicidade) que precisam de justificativa.

**Decisão do responsável — 2026-10-30 (T001):** aprovadas a instalação de
`phpunit/phpunit` como dependência de desenvolvimento e de `smalot/pdfparser` em runtime.
Recusada a instalação de `anthropic-ai/sdk`: a US7 (T135, integração com Claude) usará o
plano B com `Illuminate\Support\Facades\Http`, sem alterar o contrato `AnalisadorEnergia`.
Esta decisão substitui a escolha do SDK em research R13 e no contrato de análise de energia;
a integração foi implementada na Phase 9, sem novas dependências.

**Registro da implementação da Phase 2 — 2026-10-31:** `mockery/mockery` foi adicionado como
dependência de desenvolvimento para executar os testes com `RefreshDatabase`. Esta revisão
documenta a dependência já instalada; não instala pacotes nem presume nova aprovação.

**Decisão do responsável — 2026-10-31:** prever AJAX nas fases, conforme FR-038–FR-042.
É refinamento dos fluxos existentes, não novo módulo de produto. Para acomodar o esforço,
adiar refinamentos visuais não essenciais de T142 e drag-and-drop (T111) se necessário;
movimentação por botão com AJAX, acessibilidade, segurança e testes permanecem obrigatórios.
US7 continua sendo o primeiro corte de história se o prazo exigir; seu AJAX só é exigido se
a própria história for implementada.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Dependência nova `phpunit/phpunit` (dev) | Os gates da constituição exigem testes de feature para aderência, anonimato e bloqueio do PDF; o projeto não tem runner de testes | Não testar viola o gate; Pest traz mais pacotes sem ganho para o MVP |
| Dependência de testes `mockery/mockery` (dev, adicionada na Phase 2) | Suporte exigido por `RefreshDatabase`, conforme verificação registrada da fundação | Remover o suporte quebraria os testes existentes; não justifica acrescentar outro runner |
| Dependência nova `smalot/pdfparser` | FR-008/FR-009 exigem ler o texto do PDF; o PHP não tem parser nativo | `pdftotext`/poppler exige binário no Docker **e** no Windows; IA para leitura viola o funcionamento offline e envia dados pessoais |
| Integração de IA sem `anthropic-ai/sdk` (recusada em 2026-10-30) | História 7 isolada atrás de `AnalisadorEnergia` e desligada por padrão; cliente HTTP do Laravel já disponível | Plano B aprovado: `Illuminate\Support\Facades\Http`, mantendo manualmente o formato da API e sem nova dependência |
| Colunas `grupo`/`nivel` em `competencias` e regra "nível ≥" para idiomas | O exemplo do desafio pede "inglês básico/intermediário"; sem a regra, quem é fluente apareceria como "falta inglês básico", o que desmoraliza a explicação no pitch | Idioma sem nível perde a informação pedida pelo desafio; níveis para todas as competências seriam generalização desnecessária |
| Pastas novas: `app/Enums`, `app/Console/Commands`, `lang/`, `tests/`, `database/seeders/Support`, `database/seeders/curriculos` | Convenções padrão do Laravel (`make:enum`, `make:command`, `lang:publish`, `make:test`) e local natural dos dados de demonstração | Colocar enums, comandos e traduções em pastas existentes quebraria as convenções do framework e dificultaria a manutenção |
| Escritor mínimo de PDF próprio (`GeradorPdfCurriculo`) | 24 candidatos semeados precisam de PDF (FR-002) e os testes precisam de PDFs com texto conhecido | `dompdf` seria mais uma dependência; versionar dezenas de PDFs binários exige refazê-los a cada mudança nos dados |
| AJAX progressivo em cada fase (R21) | Melhora foco na busca, preservação de dados e ações repetidas do fluxo principal, conforme solicitação do responsável | Recarregar sempre interrompe a interação; SPA/framework reativo acrescenta dependências; limitar a fragmentos Blade e adiar polimento opcional compensa o esforço |
