# Implementation Plan: Banco de Talentos SETIC — MVP

**Branch**: `001-banco-de-talentos-mvp` | **Date**: 2026-10-30 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-banco-de-talentos-mvp/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

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
(`PerfilNaVaga`), rotas por código e bloqueio do PDF (403). A participação na seleção é
"virtual" até a primeira movimentação. A IA fica atrás da interface `AnalisadorEnergia`, com
implementação nula padrão, e recebe apenas texto anonimizado. A demonstração é preparada com um
único comando e dados 100% fictícios.

## Technical Context

**Language/Version**: PHP 8.5 (restrição `^8.3` no Composer), Laravel 13 (`^13.17`); JavaScript
ES modules (Vite 8); Tailwind CSS v4.

**Primary Dependencies**: já instaladas — `laravel/framework`, `laravel-vite-plugin`,
`@tailwindcss/vite`. **Novas, sujeitas à aprovação** (ver *Complexity Tracking*):
`smalot/pdfparser` (leitura de PDF), `anthropic-ai/sdk` (História 7) e `phpunit/phpunit` (dev,
gates de teste).

**Storage**: SQLite (local e testes, em memória) / MySQL 8.4 (Docker); PDFs no disco privado
`curriculos` (`storage/app/private/curriculos`, volume `app-storage` no Docker); sessão
`database` para o fluxo de importação.

**Testing**: PHPUnit — testes de feature (HTTP, `RefreshDatabase`, `Storage::fake`, analisador
de energia falso) e unitários (calculadora de aderência, extrator, proximidade).

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
(R1–R20).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Restrição | Avaliação | Como o plano atende |
|---|---|---|
| **I. Demo-First** | ✅ Passa | Histórias = fatias verticais na ordem do roteiro; seeders deixam cada história demonstrável sozinha; comando único `demo` (R15); ensaio cronometrado no quickstart |
| **II. Simplicidade Radical** | ⚠️ Passa com justificativas | Etapas fixas, pesos 1–3, catálogo fechado, enums em vez de tabelas configuráveis (R19), sem SPA, Alpine ou bibliotecas de UI. Três dependências novas, regra de níveis de idioma e pastas padrão do Laravel justificadas em *Complexity Tracking* |
| **III. Design Apresentável** | ✅ Passa | Tokens Tailwind + componentes Blade (R16); servidor/estagiário com cor **e** texto; `barra-score`; estados vazio/carregando/erro em todas as telas; fonte base maior para projetor |
| **IV. LGPD e Triagem Anônima** (inegociável) | ✅ Passa | DTO por contexto, rotas por código, PDF 403 na triagem, identidade revelada por vaga (R8–R10, [visibilidade](./contracts/visibilidade-por-contexto.md)); consentimento obrigatório; correção, desativação, exclusão definitiva com registro mínimo; retenção visível (R14); disco privado (R20); só dados fictícios. Limitação de perfil único documentada e aceita pela spec |
| **V. IA como Diferencial** | ✅ Passa | Ranking 100% determinístico; extração sem IA; `AnalisadorEnergia` com implementação nula por padrão; falha → "análise indisponível"; sugestões editáveis; entrada anonimizada (R13) |
| **VI. Explicabilidade** | ✅ Passa | Explicação obrigatória em todo percentual; "não identificado" ≠ "não possui"; soft skills "declaradas" × "observadas"; movimentações sempre humanas e registradas (R7) |
| **Stack e arquitetura** | ✅ Passa | Laravel 13 + Blade + Tailwind v4; Services para regras (score, anonimização, extração); contratos dos repositories vinculados no `RepositoryServiceProvider` |
| **Segurança do `.env`** | ✅ Passa | Novas chaves só no `.env.example`; instruções para o usuário; nenhum passo do plano lê, cria ou altera o `.env` |
| **Gates de qualidade** | ✅ Planejados | Testes de feature para aderência, ocultação de identidade e bloqueio do PDF; Pint; fluxo manual com seeder; revisão visual |

**Resultado (pré-pesquisa)**: aprovado, com as justificativas registradas.
**Reavaliação pós-design (Fase 1)**: aprovado. O design não introduziu violações novas. A
decisão de participação virtual (R8) e o DTO de visibilidade (R9) *reduziram* a complexidade e o
risco de vazamento em relação às alternativas.

## Project Structure

### Documentation (this feature)

```text
specs/001-banco-de-talentos-mvp/
├── plan.md                       # Este arquivo
├── research.md                   # Fase 0 — decisões R1–R20
├── data-model.md                 # Fase 1 — tabelas, enums, estados, validações
├── quickstart.md                 # Fase 1 — preparo e roteiro de validação
├── contracts/
│   ├── rotas-web.md              # Rotas, requests, respostas e códigos
│   ├── visibilidade-por-contexto.md  # Matriz LGPD/anonimato + invariantes testáveis
│   ├── extracao-curriculo.md     # Leitura do PDF, DadosExtraidos, estados da revisão
│   └── analise-energia.md        # Interface de IA, esquema JSON, falhas, proximidade
├── checklists/requirements.md    # (existente)
└── tasks.md                      # Fase 2 — gerado por /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   └── AnalisarEnergiaCommand.php          # energia:analisar {--todos}
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
│   ├── Contracts/                          # CandidatoRepository, CompetenciaRepository, VagaRepository,
│   │                                       # ParticipacaoRepository, AnotacaoRepository
│   └── Eloquent/                           # Eloquent*Repository (implementações)
├── Services/
│   ├── Curriculo/                          # LeitorPdf, ResultadoLeitura, ExtratorCurriculo, DadosExtraidos,
│   │                                       # ImportacaoCurriculoService, CadastroCandidatoService, LimpadorTexto
│   ├── Busca/                              # FiltroCandidatos, BuscaCandidatosService
│   ├── Aderencia/                          # CalculadoraAderencia, ResultadoAderencia, RankingVagaService, ItemRanking
│   ├── Selecao/                            # SelecaoService, VisibilidadeCandidatoService, PerfilNaVaga
│   ├── Anotacoes/                          # AnotacaoService
│   ├── Lgpd/                               # ExclusaoCandidatoService
│   ├── Painel/                             # PainelService
│   └── Energia/                            # AnalisadorEnergia (contrato), SugestaoEnergia, ClaudeAnalisadorEnergia,
│                                           # AnalisadorEnergiaIndisponivel, PerfilEnergiaService, ProximidadeEnergia
└── Providers/
    ├── AppServiceProvider.php              # vínculo de AnalisadorEnergia conforme a configuração
    └── RepositoryServiceProvider.php       # vínculos contrato → Eloquent

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
├── js/app.js                               # autoenvio de filtros, chips, drag-and-drop, <dialog>
└── views/
    ├── layouts/app.blade.php · layouts/visitante.blade.php
    ├── components/                         # botao, card, badge, badge-tipo, tag-competencia, campo, modal,
    │                                       # barra-score, estado-vazio, alerta, cartao-estatistica,
    │                                       # cabecalho-pagina, eixos-energia, icone
    ├── auth/login.blade.php
    ├── painel/index.blade.php
    ├── candidatos/ (index, show, edit, partials/_formulario, partials/_card)
    ├── importacoes/ (create, show)
    ├── vagas/ (index, create, edit, show, partials/_formulario, partials/_card-ranking, partials/_coluna)
    ├── participacoes/show.blade.php
    └── errors/403.blade.php                # "Identidade protegida na triagem anônima"

routes/web.php                              # rotas de contracts/rotas-web.md

tests/
├── TestCase.php
├── Unit/                                   # CalculadoraAderenciaTest, ExtratorCurriculoTest, ProximidadeEnergiaTest
└── Feature/                                # AutenticacaoTest, PainelTest, BuscaCandidatosTest, ImportacaoCurriculoTest,
                                            # VagaTest, RankingVagaTest, TriagemAnonimaTest, CurriculoProtegidoTest,
                                            # MovimentacaoEtapaTest, AnotacaoEntrevistaTest, LgpdTest, PerfilEnergiaTest

Dockerfile                                  # + conf.d/uploads.ini (upload 12M / post 13M)
hackaton · hackaton.ps1                     # + comando "demo" (setup + migrate:fresh --seed --force)
.env.example                                # + APP_LOCALE=pt_BR, ENERGIA_IA_*, ANTHROPIC_*
phpunit.xml                                 # SQLite em memória, IA desligada
```

**Structure Decision**: projeto único Laravel, mantendo as pastas já existentes
(`app/Http`, `app/Models`, `app/Services`, `app/Repositories/{Contracts,Eloquent}`,
`resources/views/layouts`). Os services são agrupados por módulo de domínio em subpastas. As
pastas novas (`app/Enums`, `app/Console/Commands`, `lang/`, `tests/`,
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
| 4 | **US4 — Kanban e triagem anônima (P2)** | Quadro de 3 colunas; `PerfilNaVaga`; rotas por código; PDF 403; movimentação com histórico; vaga encerrada; drag-and-drop (polimento) | Cenário D; `TriagemAnonimaTest`, `CurriculoProtegidoTest`, `MovimentacaoEtapaTest` |
| 5 | **US5 — Anotações (P3)** | Anotações com vaga opcional e competências observadas; histórico | Cenário E; `AnotacaoEntrevistaTest` |
| 6 | **US6 — LGPD (P3)** | Consentimento e retenção no perfil; filtro de retenção; desativar e reativar; exclusão definitiva com registro | Cenário F; `LgpdTest` |
| 7 | **US7 — Energia (P4)** | Eixos na vaga; `AnalisadorEnergia` + SDK; ajuste manual; proximidade; comando `energia:analisar` | Cenário G; `PerfilEnergiaTest`, `ProximidadeEnergiaTest` |
| 8 | **Polimento e ensaio** | Revisão visual em projetor, microinterações, estados de carregamento, ensaio cronometrado online e offline | SC-005 cumprido em ≤ 5 min |

**Cronograma sugerido até a apresentação (ter 03/11/2026)**:

| Dia | Foco |
|---|---|
| Sex 30/10 | Incremento 0 (fundação + design system) |
| Sáb 31/10 | Incrementos 1 e 2 (P1 completas) |
| Dom 01/11 | Incrementos 3 e 4 (P2 completas) |
| Seg 02/11 | Incrementos 5 e 6, polimento e ensaio; incremento 7 só se o restante estiver pronto |
| Ter 03/11 | Ensaio final e apresentação |

**Paralelização possível**: design system e views (frontend) em paralelo com migrations,
services e testes (backend). O extrator (US2) e a calculadora de aderência (US3) são classes
puras e podem ser desenvolvidos e testados em paralelo desde o primeiro dia.

**Riscos principais e mitigação**:

| Risco | Mitigação |
|---|---|
| Extração imprecisa em PDFs com layout complexo | PDFs de demonstração em coluna única, testados antes; revisão humana; sinônimos no catálogo |
| Vazamento de identidade na triagem | DTO com campos nulos + testes de invariantes ([visibilidade](./contracts/visibilidade-por-contexto.md)) |
| Rede instável no pitch | Histórias 1–6 offline; perfis de energia pré-gravados com `energia:analisar` |
| Prazo curto | Ordem P1 → P2 → P3 → P4; US7 e drag-and-drop são os primeiros cortes |
| Dependências não aprovadas | PDF: sem alternativa viável sem dependência (bloqueia US2 — decidir no incremento 0); IA: plano B com o `Http` do Laravel, sem mudar o contrato; testes: obrigatórios pela constituição |

## Complexity Tracking

> Desvios do princípio II (Simplicidade) que precisam de justificativa.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Dependência nova `phpunit/phpunit` (dev) | Os gates da constituição exigem testes de feature para aderência, anonimato e bloqueio do PDF; o projeto não tem runner de testes | Não testar viola o gate; Pest traz mais pacotes sem ganho para o MVP |
| Dependência nova `smalot/pdfparser` | FR-008/FR-009 exigem ler o texto do PDF; o PHP não tem parser nativo | `pdftotext`/poppler exige binário no Docker **e** no Windows; IA para leitura viola o funcionamento offline e envia dados pessoais |
| Dependência nova `anthropic-ai/sdk` | História 7 (diferencial do pitch) com saída estruturada e tratamento de erros tipado, isolada atrás de `AnalisadorEnergia` e desligada por padrão | Chamada HTTP manual com o `Http` do Laravel é viável e fica como plano B se a dependência não for aprovada, mas exige manter à mão o formato da API |
| Colunas `grupo`/`nivel` em `competencias` e regra "nível ≥" para idiomas | O exemplo do desafio pede "inglês básico/intermediário"; sem a regra, quem é fluente apareceria como "falta inglês básico", o que desmoraliza a explicação no pitch | Idioma sem nível perde a informação pedida pelo desafio; níveis para todas as competências seriam generalização desnecessária |
| Pastas novas: `app/Enums`, `app/Console/Commands`, `lang/`, `tests/`, `database/seeders/Support`, `database/seeders/curriculos` | Convenções padrão do Laravel (`make:enum`, `make:command`, `lang:publish`, `make:test`) e local natural dos dados de demonstração | Colocar enums, comandos e traduções em pastas existentes quebraria as convenções do framework e dificultaria a manutenção |
| Escritor mínimo de PDF próprio (`GeradorPdfCurriculo`) | 24 candidatos semeados precisam de PDF (FR-002) e os testes precisam de PDFs com texto conhecido | `dompdf` seria mais uma dependência; versionar dezenas de PDFs binários exige refazê-los a cada mudança nos dados |
