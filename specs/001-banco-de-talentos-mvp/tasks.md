---

description: "Lista de tarefas do MVP do Banco de Talentos SETIC"
---

# Tasks: Banco de Talentos SETIC — MVP

**Input**: Design documents from `/specs/001-banco-de-talentos-mvp/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: incluídos. A constituição (*Quality Gates*) exige testes de feature para cálculo de
aderência, ocultação de identidade na triagem e bloqueio do PDF. Os demais testes listados no
plan.md cobrem as regras críticas de cada história. Escreva cada teste antes da implementação
da história e confirme que ele falha.

**Organization**: tarefas agrupadas por história de usuário, para que cada uma possa ser
implementada, testada e demonstrada de forma independente.

**Revisão AJAX — 2026-10-31**: T001–T148 e suas marcações foram preservadas. T149–T168
acrescentam trabalho **pendente** nas Phases 2–10; os IDs novos seguem a ordem de execução,
sem renumerar o histórico. As descrições anteriores de redirecionamento continuam válidas
para o modo tradicional; as novas integrações acrescentam o modo JSON do contrato de rotas.
Executar T149–T154 antes de avançar às histórias ainda não implementadas.

## Regras para quem for implementar (pessoa ou IA)

- **Nunca** leia, crie, altere ou exclua o arquivo `.env`, nem rode comandos que façam isso
  (`key:generate`, `./hackaton setup`, `composer run setup`). Novas variáveis vão só no
  `.env.example`; o usuário copia para o próprio `.env` (ver `CLAUDE.md`).
- Crie arquivos com `php artisan make:* --no-interaction` (model, migration, controller,
  request, factory, seeder, command, test, enum, class).
- Arquitetura obrigatória: **Rota → Form Request → Controller → Service → Repository → Model**.
  Regras de negócio ficam em Services; os vínculos contrato → implementação ficam em
  `app/Providers/RepositoryServiceProvider.php`.
- **Ajuste solicitado após a Phase 3:** interfaces em
  `app/Repositories/Interfaces/{Entidade}RepositoryInterface.php`, uma por repository;
  implementações em `app/Repositories/{Entidade}Repository.php`, sem `Eloquent`.
  Operações de candidato em `app/Services/CandidatoService.php`. DTOs em `app/DTOs/`, com
  sufixo `DTO` (ex.: `FiltroCandidatosDTO`), inclusive nas próximas fases. As descrições abaixo
  seguem esta convenção; a validação continua em Form Requests. Services dependem das interfaces,
  não das implementações dos repositories.
- PHP: chaves em todas as estruturas de controle, tipos de retorno explícitos, promoção de
  propriedades no construtor, chaves de enum em TitleCase, PHPDoc com *array shapes*.
- Interface e mensagens em **português do Brasil**.
- AJAX via `fetch` nativo, sem dependência nova, conforme FR-038–FR-042, research R21 e
  `contracts/rotas-web.md`. Manter `web`/`auth`, CSRF, Form Requests, DTOs e fallback HTML.
  Não marcar tarefa AJAX como pronta só porque o formulário tradicional funciona.
- Ao terminar cada fase, rode `vendor/bin/pint --dirty --format agent` e `php artisan test`.
- No Docker, o código é **copiado** para a imagem: Composer e Pint rodados no container não
  alteram os arquivos do host. Rode `composer require` no host (PHP 8.5 + Composer 2) ou com
  `docker run --rm -v "${PWD}:/app" -w /app composer:2 require <pacote> --ignore-platform-reqs`.
  Depois de mudar código, rode `./hackaton dev` (ou `.\hackaton.ps1 dev`) para reconstruir.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: pode rodar em paralelo (arquivos diferentes, sem dependência de tarefa incompleta)
- **[Story]**: história a que a tarefa pertence (US1…US7)

## Path Conventions

Projeto Laravel único na raiz: `app/`, `config/`, `database/`, `lang/`, `resources/`,
`routes/`, `tests/`.

---

## Trabalho em dupla (duas trilhas em paralelo)

O trabalho é feito por duas pessoas **ao mesmo tempo**, em duas trilhas:

- **Trilha A — "Banco, Vagas e Seleção"**: esquema, models, busca, ranking e kanban.
- **Trilha B — "Interface, Currículo e LGPD"**: design system, importação de PDF, LGPD,
  anotações e IA.

Em cada **rodada**, as duas trilhas trabalham em histórias diferentes. As rodadas terminam em
um **ponto de sincronização** (S0…S4): as duas branches são mescladas no `main`, a suíte
inteira roda e as duas pessoas voltam a partir do `main` atualizado.

### Rodadas

| Rodada | Trilha A | Trilha B | Sincronização |
|---|---|---|---|
| **R0 — Fundação** | Esquema e backend base: T013–T034, T041, T043, T044 | Design system e telas base: T035–T040, T042 | **S0**: login, layout e `migrate:fresh --seed` funcionando |
| **R1 — P1** | **US1** busca e painel: T045–T062. Fazer **primeiro** T048–T052 (DTO, repositories e `CandidatoService`) e enviar ao `main` cedo | **US2** importação de PDF: T063–T079. Começar por T063–T066, T070–T072 e T076, que não dependem da trilha A; T067–T069 usam os repositories de T048/T050 e `CandidatoService` de T052 | **S1**: cenários A e B do quickstart |
| **R2 — P2 + LGPD** | **US3** vagas e ranking: T080–T095 | **US6** LGPD: T122–T129. Preparação da US4: T099, T100 e os testes T096–T098, escritos contra os contratos e falhando até a trilha A implementar | **S2**: cenários C e F |
| **R3 — P2 + P3** | **US4** kanban e triagem anônima: T101–T112, fazendo os testes T096–T098 passarem | **US5** pelo banco: T113–T117 e T120 (só a inclusão em `_secao-anotacoes`; os casos de T113 pelo contexto da vaga passam só depois da R4). **US7** sem tocar em ranking ou cards: T130–T139 e T141 | **S3**: cenários D e E (parte do banco) e G (parte do perfil) |
| **R4 — Fechamento** | **US5** pela vaga: T118, T119 (só o bloco de `routes/vagas.php`), a inclusão dos *partials* de `anotacoes/` em `participacoes/show` (parte de T120) e T121; depois T146–T147 | **US7** no ranking: T140; depois T142–T145 | **S4**: T148 juntos (ensaio online e offline) |

**Complemento AJAX às rodadas** (incluído nos mesmos checkpoints, não adiado para R4):

| Rodada | Trilha A | Trilha B | Ordem / gate |
|---|---|---|---|
| Retomada de R0/R1 | US1: T152–T154 | Fundação: T149–T151; depois US2: T155–T156 | T149–T151 compartilhadas antes de qualquer integração; T149–T154 antes de avançar às fases seguintes |
| R2 | US3: T157–T158 | US6: T163–T164 | Ambas reutilizam transporte pronto e validam contrato HTML/JSON |
| R3 | US4: T159–T160 | US5 pelo banco: T161–T162 (parte do banco) | Alterações no perfil da vaga só depois de S3 |
| R4 | US5 pela vaga: completar T161–T162; segurança: T167 | US7: T165–T166 depois de T140; validação: T168 | T167/T168 antes do ensaio T148; US7 pode ser cortada junto com sua integração |

**Cronograma**: R0 no sábado 31/10 de manhã · R1 no sábado 31/10, tarde e noite · R2 no
domingo 01/11 de manhã · R3 no domingo 01/11, tarde e noite · R4 na segunda 02/11 ·
apresentação na terça 03/11. Se uma trilha terminar antes, ela adianta tarefas de acabamento
**da própria trilha** ou revisa o PR da outra; não pega arquivos da outra trilha.

### Dono de cada arquivo

Cada arquivo tem um dono. O outro só edita com combinação prévia, e de forma aditiva.

| Área | Trilha A | Trilha B |
|---|---|---|
| Banco e domínio | `app/Enums/*`, `database/migrations/*`, `app/Models/*`, `database/factories/*`, `database/seeders/*` (exceto `curriculos/`) | `database/seeders/curriculos/*` (T079) |
| Repositories (e suas interfaces em `Repositories/Interfaces/`) | `CandidatoRepository`, `CompetenciaRepository`, `PainelRepository`, `VagaRepository`, `ParticipacaoRepository` | `AnotacaoRepository` |
| DTOs (`app/DTOs/`) | `FiltroCandidatosDTO`, `ResultadoAderenciaDTO`, `ItemRankingDTO` | `ResultadoLeituraDTO`, `DadosExtraidosDTO`, `PerfilNaVagaDTO`, `SugestaoEnergiaDTO` |
| Services | `CandidatoService`, `PainelService`, `Aderencia/`, `Selecao/SelecaoService`, `Selecao/VisibilidadeCandidatoService` | `Curriculo/`, `Lgpd/`, `Anotacoes/`, `Energia/`, `Selecao/LimpadorTexto` |
| Controllers e requests | Painel, Candidato (`index`/`show`), Curriculo, Vaga, VagaStatus, Participacao* (exceto Anotacao) e os requests deles | Importacao, ConfirmacaoImportacao, Competencia, Anotacao, ParticipacaoAnotacao, CandidatoStatus, PerfilEnergia, `CandidatoController@edit/update/destroy` (US6) e os requests deles |
| Views | `painel/`, `candidatos/index`, `candidatos/show`, `candidatos/partials/_card`, `vagas/*` (exceto `_selo-energia` e o bloco de energia de `_formulario`), `participacoes/show` | `layouts/`, `components/*`, `auth/`, `errors/`, `importacoes/*`, `candidatos/partials/_formulario`, `candidatos/edit`, `candidatos/partials/_secao-*`, `anotacoes/*`, `vagas/partials/_selo-energia` |
| Front-end | `resources/js/modulos/kanban.js`, `resources/js/modulos/busca.js`, `tests/js/busca.test.js` | `resources/css/app.css`, `resources/js/app.js`, `resources/js/modulos/ajax.js`, `resources/js/modulos/formularios.js`, `resources/js/modulos/chips.js`, `tests/js/ajax.test.js` |
| Rotas | `routes/web.php`; em `routes/candidatos.php` e `routes/vagas.php`, os blocos `// US1`, `// US3` e `// US4` | Nos mesmos arquivos, os blocos `// US2`, `// US5`, `// US6` e `// US7` |
| Testes | testes de US1, US3 e US4 (exceto T096–T098) | testes de US2, US5, US6 e US7 e T096–T098 |

**Arquivos compartilhados (só edição aditiva, uma linha por vez)**:
`app/Providers/RepositoryServiceProvider.php` (cada trilha adiciona o seu `bind` no fim),
`app/Providers/AppServiceProvider.php` (só a trilha B, em T137) e `lang/pt_BR/validation.php`
(acrescentar atributos no fim). Os blocos `// USn` das rotas também são aditivos.

**Edições cruzadas já previstas** (feitas pela trilha indicada, depois do *sync* anterior,
quando o dono já terminou aquele arquivo):

- T129 (B) mexe em `CandidatoRepositoryInterface`, `CandidatoRepository`, `CandidatoService`, `PainelService`, `candidatos/index` e `_card`, da US1 (A).
- T139 (B) mexe em `SalvarVagaRequest`, `VagaRepository` e no bloco de energia de `vagas/partials/_formulario`, da US3 (A).
- T140 (B) mexe em `RankingVagaService`, da US3 (A), e só pode começar depois de S3.
- T150 (B) registra previamente o módulo `busca.js` em `app.js`; T154 (A) preenche esse módulo
  após o sync da fundação. APIs de `ajax.js`/`formularios.js` ficam estáveis para as duas trilhas.
- T162 (B) extrai `candidatos/partials/_competencias.blade.php` e inclui no perfil do banco
  após T061; a parte em `participacoes/show` e `participacoes/partials/_perfil` ocorre após S3.
- T164 (B) ajusta a região de situação no perfil do banco após T061, preservando os includes
  de anotações/energia. T166 (B) não altera o perfil da vaga antes de S3 e não usa endpoints
  do banco para carregar identidade no contexto de triagem.
- T167 (A) usa `tests/Feature/AjaxSegurancaTest.php`; T168 (B) usa os testes JS existentes e
  o quickstart após estabilizar os módulos. Mudanças em arquivos da outra trilha são combinadas.

### Combinados para não travar um ao outro

1. **Contrato antes de código**: quem é dono de um componente usado pela outra trilha
   (repositories em T048/T050, `CandidatoService` em T052, `PerfilNaVagaDTO` em T100,
   `LimpadorTexto` em T099) compartilha o contrato com as assinaturas desta lista **logo no
   começo** da rodada. Para repositories, cria a interface em `Repositories/Interfaces/` e
   envia ao `main` antes da implementação; para DTOs, compartilha a classe em `app/DTOs/`.
2. **Sem novas migrations depois da R0**: todo o esquema está em T015–T023. Se faltar uma
   coluna, a trilha A cria a migration e avisa antes.
3. **Links para rotas que ainda não existem**: no layout e nas telas, use
   `Route::has('nome') ? route('nome') : '#'` até a rota existir.
4. **Git**: uma branch por trilha e rodada (`a/r1-us1-busca`, `b/r1-us2-importacao`…);
   commits pequenos; `git pull --rebase origin main` pelo menos a cada 2 horas; antes de
   mesclar, rode `php artisan test` e `vendor/bin/pint --dirty --format agent`. No ponto de
   sincronização, mescle primeiro a branch que mexe em menos arquivos compartilhados.
5. **Marcação**: ao concluir uma tarefa, marque `[X]` **na própria branch**. Cada trilha só
   marca as suas tarefas, então os conflitos no `tasks.md` ficam triviais.

### Como pedir para a IA implementar uma trilha

Cada pessoa roda o `/speckit-implement` restringindo às suas tarefas da rodada, por exemplo:

```text
/speckit-implement Implemente SOMENTE as tarefas T045–T062 (Trilha A, Rodada R1) de
specs/001-banco-de-talentos-mvp/tasks.md. Siga a tabela "Dono de cada arquivo": não edite
arquivos da Trilha B. Comece por T048–T051. Pare ao fim da rodada e não marque tarefas de
outras rodadas.
```

**Ao usar os exemplos acima após a revisão:** acrescente explicitamente as tarefas AJAX da
rodada (US1: T152–T154; US2: T155–T156), após T149–T151. Os intervalos antigos sozinhos não
entregam o novo checkpoint de usabilidade.

```text
/speckit-implement Implemente SOMENTE as tarefas T063–T079 (Trilha B, Rodada R1) de
specs/001-banco-de-talentos-mvp/tasks.md. Siga a tabela "Dono de cada arquivo": não edite
arquivos da Trilha A. Comece pelas tarefas que não dependem de T048/T050.
```

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependências aprovadas, runner de testes, configuração e ambiente de demonstração.

- [X] T001 Confirmar com o responsável a aprovação de `phpunit/phpunit` (dev), `smalot/pdfparser` e `anthropic-ai/sdk` e registrar a decisão com data na seção *Complexity Tracking* de `specs/001-banco-de-talentos-mvp/plan.md`. Se `anthropic-ai/sdk` for recusada, anotar que a US7 (T135) usará o plano B com `Illuminate\Support\Facades\Http`, sem mudar o contrato `AnalisadorEnergia`
- [X] T002 Instalar o runner de testes com `composer require --dev phpunit/phpunit --no-interaction` (versão resolvida pelo Composer para o Laravel 13), atualizando `composer.json` e `composer.lock`
- [X] T003 Instalar as dependências de runtime aprovadas em T001 com `composer require smalot/pdfparser anthropic-ai/sdk --no-interaction` (omitir `anthropic-ai/sdk` se recusada), atualizando `composer.json` e `composer.lock`
- [X] T004 Criar `phpunit.xml` com as suítes `Unit` (`tests/Unit`) e `Feature` (`tests/Feature`) e as variáveis `APP_ENV=testing`, `APP_LOCALE=pt_BR`, `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `ENERGIA_IA_HABILITADA=false`; criar `tests/TestCase.php` (abstract, estende `Illuminate\Foundation\Testing\TestCase`) e as pastas `tests/Unit/` e `tests/Feature/` com `.gitkeep`
- [X] T005 [P] Atualizar `.env.example`: `APP_NAME="Banco de Talentos SETIC"`, `APP_LOCALE=pt_BR`, `APP_FALLBACK_LOCALE=pt_BR`, `APP_FAKER_LOCALE=pt_BR` e um bloco comentado "Padrão de energia (IA, opcional)" com `ENERGIA_IA_HABILITADA=false`, `ENERGIA_IA_TIMEOUT=20`, `ANTHROPIC_API_KEY=` e `ANTHROPIC_MODEL=claude-opus-5-5` (**não** tocar no `.env`)
- [X] T006 [P] No `Dockerfile` (estágio `dependencies`, após a instalação das extensões), criar `/usr/local/etc/php/conf.d/uploads.ini` com `upload_max_filesize=12M` e `post_max_size=13M` (research R18)
- [X] T007 [P] Adicionar o comando `demo` ao script `hackaton`: entrada `"demo|Prepara o ambiente de demonstração do zero (setup + migrate:fresh --seed)"` em `COMMANDS` e função `cmd_demo() { cmd_setup "$@"; cmd_artisan migrate:fresh --seed --force --no-interaction; info "Demonstração pronta. Acesse com recrutador@setic.demo / demo1234."; }`
- [X] T008 [P] Adicionar o comando `demo` equivalente em `hackaton.ps1`, seguindo a estrutura existente do script (registro na lista de comandos + função que chama o setup e depois `artisan migrate:fresh --seed --force --no-interaction`)
- [X] T009 [P] Criar `config/banco_talentos.php` retornando `retencao_anos` (`2`), `upload_max_kb` (`10240`), `minimo_caracteres_texto` (`80`) e `energia` => [`habilitada` => `(bool) env('ENERGIA_IA_HABILITADA', false)`, `timeout` => `(int) env('ENERGIA_IA_TIMEOUT', 20)`]
- [X] T010 [P] Em `config/services.php`, adicionar `'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5')]`; em `config/filesystems.php`, adicionar o disco `curriculos` (`driver` local, `root` => `storage_path('app/private/curriculos')`, `visibility` => `private`, `serve` => `false`, `throw` => `false`) (research R20)
- [X] T011 [P] Criar `lang/pt_BR/validation.php` com as mensagens em português das regras usadas no projeto (`required`, `required_if`, `required_with`, `string`, `max`, `min`, `email`, `url`, `date`, `before`, `before_or_equal`, `boolean`, `integer`, `between`, `in`, `enum`, `exists`, `distinct`, `array`, `file`, `mimes`, `uploaded`, `accepted`, `digits`) e o bloco `attributes` (`nome`, `email`, `telefone`, `cpf`, `data_nascimento` => "data de nascimento", `consentimento_meio` => "meio do consentimento", `consentimento_em` => "data do consentimento", `arquivo` => "currículo em PDF", `titulo` => "título", `area` => "área", `tipo_candidato` => "tipo de candidato", `competencias` => "competências", `texto` => "anotação", `etapa`, `motivo`)
- [ ] T012 Ação manual do **usuário** (a IA não executa): no próprio `.env`, adicionar ou ajustar `APP_LOCALE=pt_BR`, `APP_FAKER_LOCALE=pt_BR`, `ENERGIA_IA_HABILITADA=false`, `ANTHROPIC_API_KEY=` e `ANTHROPIC_MODEL=claude-opus-5-5`, conforme `specs/001-banco-de-talentos-mvp/quickstart.md` §1

**Notas da implementação da Phase 1 (2026-10-30):** PHPUnit 13.4.5 e PDFParser 2.13.1
instalados com scripts automáticos do Composer desativados para não carregar o `.env`.
Adicionados o autoload de `Tests`, `.env.testing` sem segredos e testes de infraestrutura
(configuração, privacidade do disco e traduções), que falharam antes da implementação e
passaram depois. T012 permanece manual; os comandos reais de `demo` não foram executados.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: esquema completo do banco, models, catálogo, autenticação e design system. Todo
o esquema de [data-model.md](./data-model.md) é criado aqui para evitar dependências de
migration entre histórias.

**⚠️ CRITICAL**: nenhuma história pode começar antes desta fase terminar.

### Enums

- [X] T013 [P] Criar os enums *string-backed* com método `rotulo(): string` em `app/Enums/`: `TipoCandidato` (`Servidor='servidor'` → "Servidor", `Estagiario='estagiario'` → "Estagiário"); `Area` (`Desenvolvimento='desenvolvimento'`, `Infraestrutura='infraestrutura'` → "Infraestrutura e Redes", `Suporte='suporte'` → "Suporte Técnico", `Dados='dados'` → "Dados e BI", `Produto='produto'` → "Produto e Análise de Sistemas", `Seguranca='seguranca'` → "Segurança da Informação", `Design='design'` → "Design e UX"); `StatusCandidato` (`Ativo='ativo'`, `Desativado='desativado'`); `MeioConsentimento` (`Email='email'` → "E-mail", `Formulario='formulario'` → "Formulário", `DeclaracaoCiee='declaracao_ciee'` → "Declaração do CIEE", `Presencial='presencial'`, `Outro='outro'`); `OrigemCompetencia` (`Curriculo='curriculo'` → "Declarada no currículo", `Revisao='revisao'` → "Adicionada na revisão", `Entrevista='entrevista'` → "Observada em entrevista", com método `prioridade(): int` 1/2/3 para a precedência `entrevista` > `revisao` > `curriculo`); `CategoriaCompetencia` (`Hard='hard'` → "Técnica (hard skill)", `Soft='soft'` → "Comportamental (soft skill)"). `TipoCandidato` também ganha `cor(): string` com a classe do token visual
- [X] T014 [P] Criar em `app/Enums/`: `TipoCandidatoVaga` (`Servidor='servidor'`, `Estagiario='estagiario'`, `Ambos='ambos'` → "Servidores e estagiários", com `aceita(TipoCandidato $tipo): bool`); `StatusVaga` (`Aberta='aberta'`, `Encerrada='encerrada'`); `EtapaSelecao` (`Triagem='triagem'`, `Entrevista='entrevista'`, `Aprovado='aprovado'`, com `revelaIdentidade(): bool`, verdadeiro para Entrevista e Aprovado); `PesoCompetencia` (*int-backed*: `Desejavel=1` → "Desejável", `Importante=2` → "Importante", `Essencial=3` → "Essencial"); `OrigemPerfilEnergia` (`Ia='ia'` → "Sugerido pela IA", `Humano='humano'` → "Revisado por humano"); `MotivoExclusao` (`PedidoTitular='pedido_titular'` → "Pedido do titular", `Retencao='retencao'` → "Fim do prazo de retenção", `Outro='outro'`)

### Migrations (criar nesta ordem, para os timestamps respeitarem as chaves estrangeiras)

- [X] T015 Migration `create_competencias_table` em `database/migrations/`: `id`; `nome` string(80) único; `slug` string(90) único; `categoria` string indexado; `area` string nullable; `sinonimos` json; `grupo` string(40) nullable; `nivel` unsignedTinyInteger nullable; timestamps
- [X] T016 Migration `create_candidatos_table`: `id`; `codigo` string(9) único; `tipo` string; `area` string indexado; `status` string default `ativo`; índice composto (`status`, `tipo`); `nome` string(150); `email` string(150) nullable indexado; `telefone` string(30) nullable; `cpf` char(11) nullable indexado; `data_nascimento` date nullable; `formacoes`, `experiencias`, `projetos`, `links` json; `experiencia_na_area` boolean default false; `texto_curriculo` longText nullable; `curriculo_arquivo` string nullable; `curriculo_nome_original` string nullable; `curriculo_tamanho` unsignedInteger nullable; `consentimento_meio` string; `consentimento_em` date; `consentimento_registrado_por` foreignId → users nullable `nullOnDelete`; `dados_atualizados_em` timestamp; timestamps
- [X] T017 Migration `create_candidato_competencia_table`: `id`; `candidato_id` FK `cascadeOnDelete`; `competencia_id` FK `restrictOnDelete`; `origem` string; timestamps; único (`candidato_id`, `competencia_id`); índice (`competencia_id`, `candidato_id`)
- [X] T018 Migration `create_vagas_table`: `id`; `titulo` string(120); `area` string; `tipo_candidato` string; `descricao` text nullable; `status` string default `aberta` indexado; `energia_dinamico_analitico`, `energia_individual_equipe`, `energia_execucao_planejamento` unsignedTinyInteger nullable; `criada_por` foreignId → users nullable `nullOnDelete`; `encerrada_em` timestamp nullable; timestamps
- [X] T019 Migration `create_competencia_vaga_table`: `id`; `vaga_id` FK `cascadeOnDelete`; `competencia_id` FK `restrictOnDelete`; `peso` unsignedTinyInteger; timestamps; único (`vaga_id`, `competencia_id`)
- [X] T020 Migration `create_participacoes_table`, criando também `movimentacoes_etapa` no mesmo arquivo. `participacoes`: `id`; `vaga_id` FK `cascadeOnDelete`; `candidato_id` FK `cascadeOnDelete`; `etapa` string; timestamps; único (`vaga_id`, `candidato_id`); índice (`vaga_id`, `etapa`). `movimentacoes_etapa`: `id`; `participacao_id` FK `cascadeOnDelete`; `user_id` FK nullable `nullOnDelete`; `de` string; `para` string; `created_at` timestamp
- [X] T021 Migration `create_anotacoes_table`, criando também `anotacao_competencia`. `anotacoes`: `id`; `candidato_id` FK `cascadeOnDelete`; `vaga_id` FK nullable `nullOnDelete`; `user_id` FK nullable `nullOnDelete`; `texto` text; timestamps. `anotacao_competencia`: `anotacao_id` FK `cascadeOnDelete`; `competencia_id` FK `restrictOnDelete`; chave primária composta
- [X] T022 Migration `create_perfis_energia_table`: `id`; `candidato_id` FK único `cascadeOnDelete`; `dinamico_analitico`, `individual_equipe`, `execucao_planejamento` unsignedTinyInteger; `justificativa` text; `origem` string; `revisado_por` FK → users nullable `nullOnDelete`; `analisado_em` timestamp; timestamps
- [X] T023 Migration `create_registros_exclusao_table`: `id`; `user_id` FK nullable `nullOnDelete`; `motivo` string; `excluido_em` timestamp (sem timestamps padrão; **nenhuma** coluna com dado pessoal, código ou id do candidato)

### Models e factories

- [X] T024 [P] Model `app/Models/Competencia.php`: `$fillable`; casts `categoria` → `CategoriaCompetencia`, `area` → `Area`, `sinonimos` → `array`; slug gerado do nome no evento `creating`; relações `candidatos()` (belongsToMany, pivot `candidato_competencia` com `origem` e timestamps) e `vagas()` (belongsToMany, pivot `competencia_vaga` com `peso`); método `ehIdioma(): bool` (`grupo` e `nivel` preenchidos)
- [X] T025 [P] Model `app/Models/Candidato.php`: `$fillable`; `$hidden` = `nome`, `email`, `telefone`, `cpf`, `data_nascimento`, `links`, `texto_curriculo`, `curriculo_arquivo`; casts dos enums (`tipo`, `area`, `status`, `consentimento_meio`), `array` (`formacoes`, `experiencias`, `projetos`, `links`), `boolean`, `date`/`datetime`; no `creating`, gerar `codigo` `CAND-NNNN` com 4 dígitos **aleatórios** (`random_int`, `str_pad`) repetindo até ser único, e preencher `dados_atualizados_em = now()` se vazio; acessores `idade` (?int), `retencaoAte` (`dados_atualizados_em` + `config('banco_talentos.retencao_anos')` anos), `retencaoVencida` (bool); escopos com `#[Scope]` ou `scope*`: `ativos()`, `elegiveisPara(Vaga $vaga)` (ativo e, se `tipo_candidato` ≠ `ambos`, `tipo` igual), `retencaoVencida()`; relações `competencias()` (pivot `origem`, timestamps), `participacoes()`, `anotacoes()`, `perfilEnergia()` (hasOne), `consentimentoRegistradoPor()` (belongsTo User)
- [X] T026 [P] Model `app/Models/Vaga.php`: `$fillable`; casts `area` → `Area`, `tipo_candidato` → `TipoCandidatoVaga`, `status` → `StatusVaga`, `encerrada_em` → datetime; relações `competencias()` (belongsToMany com pivot `peso`), `participacoes()`, `anotacoes()`, `criador()` (belongsTo User, `criada_por`); métodos `estaAberta(): bool` e `temPadraoEnergia(): bool` (os três eixos preenchidos)
- [X] T027 [P] Models `app/Models/Participacao.php` (`protected $table = 'participacoes'`; cast `etapa` → `EtapaSelecao`; relações `vaga()`, `candidato()`, `movimentacoes()`) e `app/Models/MovimentacaoEtapa.php` (`$table = 'movimentacoes_etapa'`; `const UPDATED_AT = null`; casts `de`/`para` → `EtapaSelecao`; relações `participacao()`, `usuario()`)
- [X] T028 [P] Model `app/Models/Anotacao.php`: `$table = 'anotacoes'`; relações `candidato()`, `vaga()`, `autor()` (belongsTo User, `user_id`), `competenciasObservadas()` (belongsToMany Competencia, pivot `anotacao_competencia`, sem timestamps)
- [X] T029 [P] Models `app/Models/PerfilEnergia.php` (`$table = 'perfis_energia'`; cast `origem` → `OrigemPerfilEnergia`, `analisado_em` → datetime; relações `candidato()`, `revisor()`) e `app/Models/RegistroExclusao.php` (`$table = 'registros_exclusao'`; `$timestamps = false`; cast `motivo` → `MotivoExclusao`, `excluido_em` → datetime; relação `usuario()`)
- [X] T030 [P] Factories em `database/factories/`: `CompetenciaFactory` (estados `hard()`, `soft()`, `idioma(string $grupo, int $nivel)`), `CandidatoFactory` (dados `fake()` em pt_BR; estados `servidor()`, `estagiario()`, `desativado()`, `retencaoVencida()`, com `dados_atualizados_em` há mais de 2 anos; `consentimento_meio`/`consentimento_em` sempre preenchidos), `VagaFactory` (estados `encerrada()`, `paraEstagiarios()`, `paraServidores()`), `AnotacaoFactory`

### Seeders e dados de base

- [X] T031 Criar `database/seeders/Support/GeradorPdfCurriculo.php` (namespace `Database\Seeders\Support`), um escritor mínimo de PDF 1.4 **sem dependências**: `gerar(array $linhas): string` recebe as linhas do currículo e devolve os bytes do PDF (fonte padrão Helvetica com `/Encoding /WinAnsiEncoding`, texto convertido com `mb_convert_encoding($linha, 'Windows-1252', 'UTF-8')`, escape de `\`, `(` e `)`, quebra de página a cada ~48 linhas, tabela `xref` com offsets corretos e `trailer`); `gerarSemTexto(): string` devolve um PDF válido só com retângulos desenhados (sem operadores de texto), para simular um currículo digitalizado (research R15)
- [X] T032 Criar `database/seeders/CompetenciaSeeder.php` com o catálogo (≈46 itens, campo `area` só para hard skills):
  - **Hard + área**: Python, PHP, Laravel, JavaScript, TypeScript, React, Vue.js, Node.js, Java, C#, .NET, API REST, Testes automatizados e Git (desenvolvimento); SQL, MySQL, PostgreSQL, Power BI e Excel Avançado (dados); Docker, Linux, Windows Server, Active Directory, Redes de Computadores e Cloud (AWS/Azure) (infraestrutura); Windows, Suporte a Hardware e ITIL (suporte); Segurança da Informação (seguranca); Scrum, UML e Levantamento de Requisitos (produto); Figma (design).
  - **Idiomas** (hard, sem área): Inglês básico, Inglês intermediário e Inglês avançado (`grupo` `ingles`, `nivel` 1/2/3); Espanhol básico (`grupo` `espanhol`, `nivel` 1).
  - **Soft**: Trabalho em equipe, Comunicação, Proatividade, Organização, Resolução de problemas, Liderança, Atendimento ao público, Aprendizado contínuo e Pensamento analítico.
  - **Sinônimos** em minúsculas e sem acento, para o casamento do extrator. Ex.: SQL ← `sql`, `mysql`, `postgresql`, `postgres`, `sql server`, `oracle`, `banco de dados relacional` (assim "PostgreSQL" implica SQL); MySQL ← `mysql`, `mariadb`; PostgreSQL ← `postgresql`, `postgres`; JavaScript ← `javascript`, `js`, `ecmascript`; Node.js ← `node.js`, `nodejs`, `node`; C# ← `c#`, `csharp`; .NET ← `.net`, `dotnet`, `asp.net`; Git ← `git`, `github`, `gitlab`, `versionamento`; Trabalho em equipe ← `trabalho em equipe`, `trabalhar em equipe`, `colaboracao`, `colaborativo`; Comunicação ← `comunicacao`, `comunicativo`, `comunicativa`; Pensamento analítico ← `pensamento analitico`, `analitico`, `analitica`.
  - Usar `updateOrCreate` por `nome`, para o seeder ser idempotente.
- [X] T033 Criar `database/seeders/UsuarioSeeder.php` (recrutador `name` "Recrutadora Demo", `email` `recrutador@setic.demo`, senha `demo1234` com hash) e reescrever `database/seeders/DatabaseSeeder.php` chamando, já na ordem final, `UsuarioSeeder`, `CompetenciaSeeder`, `CandidatoSeeder` e `VagaSeeder`. Criar `CandidatoSeeder` e `VagaSeeder` agora como *stubs* vazios (`run()` sem conteúdo), para nenhuma história precisar editar o `DatabaseSeeder` (evita conflito entre as trilhas)

### Infraestrutura HTTP, autenticação e design system

- [X] T034 Em `bootstrap/app.php`: no `withMiddleware`, `$middleware->redirectGuestsTo(fn () => route('login'))` e `redirectUsersTo(fn () => route('painel'))`; no `withExceptions`, registrar o render de `Illuminate\Http\Exceptions\PostTooLargeException`, que redireciona `back()` com `withErrors(['arquivo' => 'O arquivo excede o limite de 10 MB.'])`
- [X] T035 [P] Definir os tokens do design system no `@theme` de `resources/css/app.css` (research R16): `--color-primaria-*` (azul institucional, escala 50–900), `--color-servidor-*` (índigo), `--color-estagiario-*` (âmbar), `--color-score-alto` (verde), `--color-score-medio` (âmbar), `--color-score-baixo` (neutro/cinza — **não** usar vermelho), superfícies e bordas neutras, `--radius-card`, e tamanho de fonte base de 17px para legibilidade em projetor. Todos os pares texto/fundo com contraste WCAG AA
- [X] T036 [P] Criar componentes Blade anônimos em `resources/views/components/`: `botao.blade.php` (variantes `primario`, `secundario`, `perigo`, `fantasma`; tamanhos; renderiza `<a>` quando recebe `href`), `card.blade.php`, `badge.blade.php`, `badge-tipo.blade.php` (recebe `TipoCandidato`; cor **e** texto "Servidor"/"Estagiário"), `tag-competencia.blade.php` (nome + origem opcional: "declarada", "observada" ou "revisada"; variante `faltante` mostrando o peso), `icone.blade.php` (SVGs inline por nome: busca, upload, usuario, vaga, check, x, cadeado, seta, sair, alerta)
- [X] T037 [P] Criar componentes Blade anônimos em `resources/views/components/`: `campo.blade.php` (label, `input`/`select`/`textarea` por prop `tipo`, mensagem de `@error`, `old()`), `modal.blade.php` (`<dialog>` com título, conteúdo e rodapé), `alerta.blade.php` (variantes `info`, `sucesso`, `aviso`, `erro`), `estado-vazio.blade.php` (ícone, título, texto, slot de ação), `cartao-estatistica.blade.php` (rótulo, valor grande, detalhe), `cabecalho-pagina.blade.php` (título, subtítulo, slot de ações), `barra-score.blade.php` (percentual 0–100 com barra colorida pela faixa ≥ 70 / 40–69 / < 40 e número grande ao lado; `aria-label` "Aderência de N%")
- [X] T038 Criar `resources/views/layouts/app.blade.php` (barra lateral com navegação Painel, Candidatos, Importar currículo e Vagas; topo com o nome do usuário e botão Sair via `POST /logout`; área de mensagens `session('status')` com `x-alerta`; `@vite`; `<title>` com o nome da página) e `resources/views/layouts/visitante.blade.php` (centralizado, para o login). Depende de T036 e T037
- [X] T039 [P] Criar `resources/views/errors/403.blade.php` ("Identidade protegida na triagem anônima", com explicação e botão de voltar) e `resources/views/errors/404.blade.php` (mensagem amigável em português)
- [X] T040 [P] Criar `resources/js/app.js` apenas como ponto de entrada que importa `./modulos/formularios.js`, `./modulos/chips.js` e `./modulos/kanban.js` (os dois últimos criados agora vazios, com um comentário indicando a tarefa que os preenche: T062 e T111). Em `resources/js/modulos/formularios.js`: (1) formulários com `data-autosubmit` enviam ao alterar qualquer campo (com *debounce* de 400 ms para texto); (2) botões com `data-abrir-modal="id"` chamam `showModal()` no `<dialog>` correspondente e `data-fechar-modal` o fecham; (3) formulários com `data-confirmar="mensagem"` pedem confirmação antes do envio
- [X] T041 Criar `app/Http/Requests/LoginRequest.php` (`email` required|email, `password` required|string) e `app/Http/Controllers/Auth/LoginController.php` com `create()` (view de login), `store(LoginRequest)` (`Auth::attempt` + `session()->regenerate()`; em caso de falha, volta com o erro "E-mail ou senha inválidos." em `email`) e `destroy()` (logout, `invalidate`, `regenerateToken`, redireciona para `login`)
- [X] T042 Criar `resources/views/auth/login.blade.php` (layout visitante, nome do sistema, campos e-mail e senha com `x-campo`, botão Entrar, aviso "Ambiente de demonstração — dados fictícios")
- [X] T043 Criar `routes/candidatos.php` e `routes/vagas.php` (só com `<?php` e `use Illuminate\Support\Facades\Route;`) e reescrever `routes/web.php`, incluindo os dois arquivos com `require` **dentro** do grupo `auth`. Cada história adiciona suas rotas no arquivo do módulo, em um bloco com comentário próprio (`// USn — ...`), para as trilhas não editarem as mesmas linhas. Em `routes/web.php`: grupo `guest` com `GET /login` (`login`) e `POST /login` (`login.store`, middleware `throttle:5,1`); grupo `auth` com `POST /logout` (`logout`) e `GET /` (`painel`, temporariamente `Route::view('/', 'painel.index')` com `resources/views/painel/index.blade.php` provisória, substituída em T057). Remover a rota `welcome`
- [X] T044 Criar `tests/Feature/AutenticacaoTest.php`: visitante em `/` é redirecionado para `/login`; login válido do usuário da factory redireciona para `painel`; login inválido volta com erro e continua visitante; logout encerra a sessão

### Complemento — fundação AJAX (FR-041/FR-042)

- [X] T149 Criar `tests/js/ajax.test.js` com o runner nativo do Node e mocks de `fetch`/DOM mínimos, sem dependências: resposta JSON válida, 422 com erros por campo, 401/419/403/404/413/503, rede e HTML inesperado; nenhuma repetição automática de escrita; controles liberados após falha; bloqueio de envio duplicado; recusa de `redirect` de outra origem. Escrever antes de T150 e executar com `node --test tests/js/ajax.test.js`
- [X] T150 Criar `resources/js/modulos/ajax.js` para `fetch` de mesma origem e contrato de `contracts/rotas-web.md`; integrar em `resources/js/modulos/formularios.js` por `data-ajax`, com delegação de eventos, serialização antes de bloquear controles, `_token`/`_method`, erros locais, confirmação, loading e proteção contra duplo envio. Não reenviar escritas automaticamente nem aplicar atualização otimista. Preservar submit tradicional nos formulários não marcados/sem suporte; impedir que `data-autosubmit` dispare um segundo envio. Em `resources/js/app.js`, registrar também `./modulos/busca.js`, criado como placeholder para T154. Depende de T149
- [X] T151 Ajustar `bootstrap/app.php` para erros JSON controlados quando `Accept: application/json` (401, 403, 404, 419, 413 e 503), sem alterar o comportamento HTML já implementado, inclusive a página 413; manter o 422 padrão de Form Requests e não desabilitar proteção CSRF. Em `resources/views/layouts/app.blade.php` e `resources/views/components/campo.blade.php`, preparar região persistente de anúncios `aria-live`, resumo e erros por campo, `aria-invalid`/`aria-describedby` e foco no primeiro erro. Definir em `ajax.js` o mapa fechado de regiões do contrato; validar o envelope inteiro antes de aplicar fragmentos. Depende de T150; os testes HTTP serão acrescentados em T152 e nas histórias seguintes

**Checkpoint**: `php artisan migrate:fresh --seed` roda sem erros; o login funciona e mostra o
layout; `php artisan test` passa.

**Gate adicional da revisão AJAX**: T149–T151 implementadas e cobertas por testes de
transporte/formulários e erros HTTP. Não foi necessário refazer o esquema. As verificações
históricas abaixo continuam separadas da validação do complemento AJAX.

**Notas da implementação da Phase 2 (2026-10-31):** as duas trilhas (A e B) foram feitas por uma
pessoa só. O relatório completo está em
[specs/implementacoes/fase-2-fundacao.md](../implementacoes/fase-2-fundacao.md). Desvios em
relação ao texto das tarefas:

- **T025**: o escopo de retenção chama-se `comRetencaoVencida()`, porque `retencaoVencida` já é
  o nome do acessor (`$candidato->retencao_vencida`).
- **T034**: o upload acima do `post_max_size` mostra a página `errors/413.blade.php` em vez de
  redirecionar com `withErrors`. A exceção acontece antes de a sessão iniciar, então a mensagem
  flash se perderia.

**Verificação (2026-10-31, Docker)**: 28 testes passando (105 asserções), Pint aplicado,
`migrate:fresh --seed` e `npm run build` funcionando, e teste de fumaça HTTP do login aprovado.
Para os testes rodarem, foi adicionado `mockery/mockery` (dev), exigido pelo `RefreshDatabase`.

---

## Phase 3: User Story 1 — Encontrar talentos no banco com filtros (Priority: P1) 🎯 MVP

**Goal**: painel inicial e busca com filtros combinados (competências com **E**, área, tipo,
experiência), contagem, cards e perfil completo, sobre os dados fictícios pré-carregados.

**Independent Test**: com o banco semeado, filtrar Python + SQL + Estagiário e ver apenas
candidatos com todos os critérios, a contagem correta e os cards (quickstart, cenário A).

### Tests for User Story 1 ⚠️

- [X] T045 [P] [US1] Criar `tests/Feature/BuscaCandidatosTest.php`: filtro por duas competências retorna só quem tem **ambas**; combinar `tipo` e `area` restringe a lista; filtro `experiencia=1` só retorna `experiencia_na_area = true`; candidato `desativado` nunca aparece; filtro por "Inglês básico" inclui quem tem "Inglês avançado" (mesmo `grupo`, `nivel` ≥); a resposta mostra "N talentos encontrados"; filtros sem resultado mostram o estado vazio com "Remover filtros"; `busca` por parte do nome encontra o candidato; os cards **não** exibem o `codigo` `CAND-`
- [X] T046 [P] [US1] Criar `tests/Feature/PainelTest.php`: o painel mostra o total de candidatos ativos, a contagem de servidores e de estagiários, o número de vagas com status `aberta` e as competências mais frequentes em ordem decrescente

### Implementation for User Story 1

- [X] T047 [US1] Criar `database/seeders/CandidatoSeeder.php` com 24 candidatos fictícios (nomes, e-mails `@exemplo.com.br`, telefones e cidades inventados; `consentimento_meio` variado; `consentimento_em` nos últimos 6 meses) (o *stub* já está registrado em `DatabaseSeeder` por T033). Para cada um, gerar o PDF com `GeradorPdfCurriculo` (linhas: nome, contato, "FORMAÇÃO", itens, "EXPERIÊNCIA PROFISSIONAL", itens, "PROJETOS", itens, "COMPETÊNCIAS", lista separada por vírgulas, "IDIOMAS"), gravá-lo em `Storage::disk('curriculos')` como `{uuid}.pdf` e preencher `curriculo_arquivo`, `curriculo_nome_original`, `curriculo_tamanho` e `texto_curriculo`. Competências com origem `curriculo`. **Estagiários** (vaga do roteiro: Python 3, SQL 3, Git 2, Inglês básico 1, Trabalho em equipe 1 = 10):
  - E1 (desenvolvimento): Python, SQL, Git, Inglês intermediário, Trabalho em equipe, PHP, Laravel → 100%
  - E2 (desenvolvimento): Python, SQL, Git, Inglês básico, JavaScript → 90%
  - E3 (desenvolvimento): Python, SQL, Git, Trabalho em equipe, React → 90% (empate com E2)
  - E4 (desenvolvimento): Python, SQL, Inglês avançado, Trabalho em equipe, Comunicação → 80%
  - E5 (dados): Python, SQL, Power BI, Excel Avançado, Pensamento analítico → 60%
  - E6 (desenvolvimento): Python, SQL, Docker → 60%
  - E7 (desenvolvimento): Python, SQL, Linux, Inglês básico → 70%
  - E8 (desenvolvimento): JavaScript, Git, Trabalho em equipe, Figma → 30%
  - E9 (infraestrutura): Linux, Redes de Computadores, Suporte a Hardware, Trabalho em equipe → 10%
  - E10 (suporte): Atendimento ao público, Windows, Comunicação → 0%
  - E11 (design): Figma, Comunicação, Inglês básico → 10%
  - E12 (dados): SQL, Excel Avançado, Organização → 30%

  **Servidores**:
  - S1 (suporte): ITIL, Windows Server, Active Directory, Atendimento ao público, Comunicação
  - S2 (infraestrutura): Linux, Docker, Redes de Computadores, Cloud (AWS/Azure)
  - S3 (dados): SQL, PostgreSQL, Power BI, Python
  - S4 (desenvolvimento): PHP, Laravel, MySQL, Git, JavaScript, Scrum
  - S5 (produto): Scrum, Levantamento de Requisitos, UML, Comunicação, Liderança
  - S6 (segurança): Segurança da Informação, Linux, Redes de Computadores
  - S7 (desenvolvimento): Java, SQL, Git, API REST, Testes automatizados
  - S8 (suporte): Suporte a Hardware, Windows, Atendimento ao público, Proatividade
  - S9 (dados): Python, SQL, Excel Avançado, Pensamento analítico
  - S10 (design): Figma, Comunicação, Organização
  - S11 (infraestrutura): Windows Server, Active Directory, Redes de Computadores, ITIL
  - S12 (desenvolvimento): C#, .NET, SQL, Git, Inglês intermediário

  `experiencia_na_area = true` em cerca de metade (E1, E4, E7 e a maioria dos servidores). Três servidores (S6, S10, S11) com `dados_atualizados_em` há 2 anos e 2 meses (retenção pendente). `created_at` crescente na ordem da lista (desempate estável). E3 usa o e-mail `ana.lima@exemplo.com.br`, reutilizado no PDF de duplicidade (T079)
- [X] T048 [P] [US1] Criar `app/Repositories/Interfaces/CompetenciaRepositoryInterface.php` e `app/Repositories/CompetenciaRepository.php` com: `todasAgrupadasPorCategoria(): array` (`['hard' => Collection, 'soft' => Collection]`, ordenadas por nome); `idsQueAtendem(int $competenciaId): array<int>` (o próprio id + ids do mesmo `grupo` com `nivel` ≥, para idiomas); `buscarPorIds(array $ids): Collection`
- [X] T049 [P] [US1] Criar `app/DTOs/FiltroCandidatosDTO.php` (classe `final readonly`: `array $competencias` (ids), `?TipoCandidato $tipo`, `?Area $area`, `bool $somenteComExperiencia`, `?string $busca`, `string $situacao` (`ativos` por padrão), com o construtor estático `fromArray(array $validados)` e `comCompetenciasEquivalentes(array $equivalencias): self`)
- [X] T050 [US1] Criar `app/Repositories/Interfaces/CandidatoRepositoryInterface.php` e `app/Repositories/CandidatoRepository.php` com: `buscar(FiltroCandidatosDTO $filtro, int $porPagina = 12): LengthAwarePaginator` (um `whereHas('competencias', fn ($q) => $q->whereIn('competencias.id', $idsQueAtendem))` **por** competência escolhida = semântica E; `tipo`, `area` e `experiencia_na_area`; `busca` com `like` em `nome` ou `email`; situação `ativos` → `status = ativo`; eager load de `competencias`; `orderBy('nome')`; `withQueryString()`); `detalhar(Candidato $candidato): Candidato`; `contarAtivosPorTipo(): array`; `topCompetencias(int $limite = 8): Collection` (nome + total entre ativos); `encontrarPorEmailOuCpf(?string $email, ?string $cpf): ?Candidato`
- [X] T051 [US1] Registrar `CandidatoRepositoryInterface` → `CandidatoRepository` e `CompetenciaRepositoryInterface` → `CompetenciaRepository` em `app/Providers/RepositoryServiceProvider.php`
- [X] T052 [US1] Criar `app/Services/CandidatoService.php`, dependendo de `CandidatoRepositoryInterface` e `CompetenciaRepositoryInterface`: `buscar(FiltroCandidatosDTO $filtro): LengthAwarePaginator` resolve os ids equivalentes via `CompetenciaRepositoryInterface::idsQueAtendem` e delega ao repository de candidatos; centralizar também catálogo da busca, detalhamento, consulta de currículo, contagens e consulta de duplicidade
- [X] T053 [P] [US1] Criar `app/Services/PainelService.php` com `resumo(): array{totalTalentos:int, servidores:int, estagiarios:int, vagasAbertas:int, topCompetencias:Collection, retencaoPendente:int}` (`retencaoPendente` fica 0 até a US6), consultando candidatos via `CandidatoService` e vagas abertas via `PainelRepositoryInterface`; criar `app/Repositories/Interfaces/PainelRepositoryInterface.php` e `app/Repositories/PainelRepository.php` para essa contagem e registrar o vínculo interface → implementação no `RepositoryServiceProvider`
- [X] T054 [US1] Criar `app/Http/Requests/BuscarCandidatosRequest.php`: `competencias` nullable|array, `competencias.*` integer|exists:competencias,id, `tipo` nullable `Rule::enum(TipoCandidato::class)`, `area` nullable `Rule::enum(Area::class)`, `experiencia` nullable|boolean, `busca` nullable|string|max:100, `situacao` nullable|in:ativos,desativados,retencao; método `filtro(): FiltroCandidatosDTO`, criado somente a partir dos dados validados
- [X] T055 [US1] Criar `app/Http/Controllers/PainelController.php` (invokable, usa `PainelService`) e `resources/views/painel/index.blade.php` definitiva: 4 `x-cartao-estatistica` (Talentos, Servidores, Estagiários, Vagas abertas), lista "Competências mais frequentes" em barras horizontais só com CSS e atalhos para Importar currículo e Nova vaga
- [X] T056 [US1] Criar `app/Http/Controllers/CandidatoController.php` com `index(BuscarCandidatosRequest)` (filtros, resultado paginado, catálogo agrupado para o formulário de filtros) e `show(Candidato $candidato)`; delegar busca, catálogo e detalhamento a `CandidatoService`, que usa o repository para o eager load de competências, anotações com autor e vaga e perfil de energia
- [X] T057 [US1] Criar `app/Http/Controllers/CurriculoController.php` com `show(Candidato $candidato)`: consultar `CandidatoService::obterCurriculo`, que retorna `null` se `curriculo_arquivo` for nulo ou o arquivo não existir (resposta 404); senão devolver a resposta HTTP do PDF com `Content-Type: application/pdf`, `Cache-Control: private, no-store` e disposição `inline`
- [X] T058 [US1] Em `routes/web.php`, trocar a rota provisória `/` por `PainelController` (`painel`); em `routes/candidatos.php`, no bloco `// US1 — gestão do banco`, adicionar `GET /candidatos` (`candidatos.index`), `GET /candidatos/{candidato}` (`candidatos.show`) e `GET /candidatos/{candidato}/curriculo` (`candidatos.curriculo`), conforme `contracts/rotas-web.md`. Restringir **sempre** `{candidato}` com `->whereNumber('candidato')`, para que a ordem das rotas da US2 (`/candidatos/importar`) não importe
- [X] T059 [P] [US1] Criar `resources/views/candidatos/partials/_card.blade.php`: nome, `x-badge-tipo`, área, "Experiência na área: sim" ou "Experiência na área: não informado" (FR-007), até 5 `x-tag-competencia` e "+N"; o card inteiro leva a `candidatos.show`; **sem** o código `CAND-`
- [X] T060 [US1] Criar `resources/views/candidatos/index.blade.php`: `x-cabecalho-pagina` com o botão "Importar currículo"; formulário GET com `data-autosubmit` contendo campo de busca por nome, selects de tipo e área, *checkbox* de experiência e a lista de competências em dois grupos (Técnicas / Comportamentais) como *chips* de *checkbox*, com um campo "filtrar competências"; título "N talentos encontrados"; grade responsiva de `_card`; paginação; `x-estado-vazio` com link "Remover filtros" para `candidatos.index`
- [X] T061 [US1] Criar `resources/views/candidatos/show.blade.php`: cabeçalho (nome, `x-badge-tipo`, área, idade se houver, e-mail, telefone, CPF mascarado `***.***.***-NN`); seções Formação, Experiências, Competências (separadas em hard e soft, cada `x-tag-competencia` com a origem), Projetos, Links e Currículo original (botão "Abrir PDF" para `candidatos.curriculo`); listas vazias mostram "Não informado no currículo" (nunca "não possui"); as seções de outras histórias entram por `@include` de três *partials* criados agora como placeholders ("Em breve"): `resources/views/candidatos/partials/_secao-anotacoes.blade.php` (US5), `_secao-lgpd.blade.php` (US6) e `_secao-energia.blade.php` (US7). As outras histórias editam só o *partial* delas, nunca o `show.blade.php`
- [X] T062 [US1] Em `resources/js/modulos/chips.js`, implementar o filtro de *chips*: um input com `data-filtrar-chips="#alvo"` oculta os *chips* cujo texto (sem acento, minúsculo) não contém o termo digitado

### Complemento — AJAX da US1

- [X] T152 [US1] Ampliar `tests/Feature/BuscaCandidatosTest.php` antes de T153: mesmos filtros/contagens/paginação em HTML e JSON; JSON 200 com `resultados-candidatos` sem layout completo e sem códigos CAND; query de paginação preservada, estado vazio, 422 em filtro inválido e 401 para visitante em JSON (HTML continua redirecionando). Criar `tests/js/busca.test.js` para debounce de 400 ms, invalidar resposta desde a edição, cancelamento de GET, descarte de respostas fora de ordem, reset de `page`, URL só confirmada com resultado, `popstate` e preservação do campo/foco. Depende de T149–T151
- [X] T153 [US1] Extrair contagem/cards/paginação/vazio de `resources/views/candidatos/index.blade.php` para `resources/views/candidatos/partials/_resultados.blade.php`, com região `resultados-candidatos` fora do formulário. Em `app/Http/Controllers/CandidatoController.php`, negociar JSON 200 com HTML desse partial ou página completa, usando o mesmo `CandidatoService`, filtros validados e paginação; resposta privada sem cache. Depende de T152; preservar T045–T062
- [X] T154 [US1] Implementar `resources/js/modulos/busca.js` e marcar o formulário/links em `resources/views/candidatos/index.blade.php` e `resources/views/candidatos/partials/_resultados.blade.php`: atualizar via AJAX ao filtrar, limpar ou paginar; texto com 400 ms e selects/checkboxes imediatos; invalidar consulta anterior antes do debounce, usar `AbortController` e sequência; manter foco/cursor e resultado anterior em falha; sincronizar URL com `replaceState` nos filtros e `pushState` na paginação, restaurando formulário/resultados em `popstate`. Manter botão Aplicar/links reais sem JS; anúncios e `aria-busy`. Depende de T153; executar os testes de T152 e cenário A/H do quickstart

**Checkpoint**: cenário A do quickstart funciona; `BuscaCandidatosTest` e `PainelTest` passam.

**Gate AJAX**: T152–T154 implementadas; validação manual dos cenários A/H e aceite de SC-010
continuam pendentes por ausência de navegador neste ambiente. A implementação histórica abaixo
comprova a busca tradicional; o complemento está registrado separadamente a seguir.

**Complemento AJAX das Phases 2/3 (2026-10-31):** escopo restrito a T149–T154, sem novas
dependências, migrations ou integrações das fases seguintes. Testes JS escritos antes do
transporte, e testes HTTP/JS de busca antes da integração; falhas iniciais confirmadas.
Transporte valida todo o envelope e destinos/regiões antes de aplicar HTML, preserva CSRF e
method spoofing, bloqueia duplo envio e não repete escritas. Erros JSON controlados em
português, com proteção de detalhes mesmo em debug; 422 padrão preservado e página HTML 413
mantida. Busca compartilha o partial Blade entre HTML/JSON, com `private, no-store`,
debounce de 400 ms, invalidação imediata, cancelamento/sequência, paginação e histórico.
Campos ficam fora da região substituída; erros mantêm valores e focam o campo após desbloqueio.
Validação automatizada: **71 testes PHP / 368 asserções e 25 testes JavaScript** (também em
Node 22), `vendor/bin/pint --dirty --format agent` aprovado e build Vite concluído.
Suítes, Pint e build são executados sem disponibilizar o `.env` aos comandos; o Vite usa
`envDir` temporário vazio. **Gate manual pendente:** revisar A/H no navegador (teclado, foco,
rede lenta, voltar/avançar, sem JS e sem internet com servidor local acessível); SC-010/SC-011
não foram declarados aprovados. T155–T168 permanecem pendentes.

**Implementação da Phase 3 (2026-10-31):** T045–T062 implementadas, sem avançar às fases
seguintes. Interface de catálogo institucional com Instrument Sans, tokens e componentes da
fundação preservados; filtros laterais no desktop, grade responsiva e perfil em ficha. A busca
combina competências com E e respeita os níveis de idioma. Seeder idempotente com 24 pessoas
fictícias (12 de cada tipo), PDFs privados e 7 estagiários com Python e SQL.

- `FiltroCandidatosDTO` mantém os ids selecionados e carrega também os grupos de ids equivalentes
  resolvidos pelo service, preservando a assinatura do repository.
- Adicionados `PainelRepositoryInterface` e `PainelRepository` para a contagem de vagas abertas
  não acessar o model diretamente no service. Não antecipa o CRUD/repository de vagas da US3.
- `situacao` é validada conforme o contrato, mas os filtros de desativados/retenção e o
  indicador de retenção permanecem para T129; os atalhos de funcionalidades futuras ficam
  desabilitados e os três partials do perfil mostram “Em breve”. O seeder de vagas continua stub
  até T095, portanto um banco com somente esta fase mostra 0 vagas abertas, não 3.
- Testes de busca/painel escritos antes da implementação e falhas esperadas confirmadas.
  Verificação final: **56 testes PHP / 222 asserções**, teste JavaScript dos chips aprovado,
  `vendor/bin/pint --dirty --format agent`, build Vite e `route:list` aprovados.
  PHP executado em Docker com `.env.testing`, SQLite em memória e armazenamento privado
  temporário. Dependências existentes sincronizadas com os lockfiles, sem scripts do Composer
  e sem novas dependências. Fonte empacotada em `public/build`. A checagem final do build usou
  a API do Vite com `envDir` apontando para um diretório temporário vazio, para impedir a carga
  automática de arquivos de ambiente pelo Vite/plugin Laravel. O `.env` não foi alterado.
- **Gate manual pendente:** ambiente sem navegador para revisão visual real. Conferir cenário A,
  layout em 1280×720 e celular e navegação por teclado antes de aceitar o checkpoint visual.

**Revisão de organização solicitada pelo usuário:** repositories renomeados conforme a
convenção acima; `CandidatoService` concentra busca, catálogo usado na busca, detalhamento,
consulta de currículo, contagens e consulta de duplicidade. O DTO foi mantido e movido para
`app/DTOs/FiltroCandidatosDTO.php`. `PainelService` passou para a raiz de `app/Services` e
consulta os dados de candidato via `CandidatoService`. Não houve mudança de rotas, layout,
regras de filtro ou esquema do banco.
Validação da refatoração: **59 testes PHP / 239 asserções**, Pint e `route:list` aprovados.

---

## Phase 4: User Story 2 — Cadastrar currículo a partir do PDF com leitura automática (Priority: P1)

**Goal**: enviar um PDF, extrair os dados automaticamente, revisar, registrar o consentimento e
salvar (novo ou atualização de duplicado), mantendo o PDF original.

**Independent Test**: enviar `database/seeders/curriculos/demo-curriculo.pdf`, ver a revisão
pré-preenchida com as competências do catálogo, confirmar e encontrar o candidato na busca da
US1 (quickstart, cenário B).

### Tests for User Story 2 ⚠️

- [X] T063 [P] [US2] Criar `tests/Unit/ExtratorCurriculoTest.php` (com `RefreshDatabase` + `CompetenciaSeeder`, porque o extrator lê o catálogo) com todos os casos da tabela "Casos de teste de referência" de `contracts/extracao-curriculo.md`, mais e-mail, telefone, links, separação de seções e área sugerida; um texto de referência com 10 competências do catálogo deve ter ≥ 8 identificadas (SC-002)
- [X] T064 [P] [US2] Criar `tests/Feature/ImportacaoCurriculoTest.php` (`Storage::fake('curriculos')`; PDFs via `UploadedFile::fake()->createWithContent('cv.pdf', (new GeradorPdfCurriculo)->gerar([...]))`): upload válido redireciona para a revisão pré-preenchida; arquivo `.txt` é rejeitado; arquivo acima de 10240 KB é rejeitado; PDF sem texto (`gerarSemTexto()`) mostra o aviso de leitura manual; confirmar sem `consentimento_meio`/`consentimento_em` falha com a mensagem do FR-011; confirmação válida cria o candidato com `codigo` `CAND-NNNN`, PDF movido para fora de `tmp/` e competências com origem `curriculo`; e-mail já existente mostra o alerta de duplicidade e o modo `atualizar` atualiza o cadastro existente sem criar outro e sem rebaixar competência de origem `entrevista`

### Implementation for User Story 2

- [X] T065 [P] [US2] Criar `app/DTOs/ResultadoLeituraDTO.php` (`final readonly`: `string $situacao` (`lido`, `sem_texto` ou `corrompido`), `?string $texto`) e `app/Services/Curriculo/LeitorPdf.php` com `extrairTexto(string $caminhoAbsoluto): ResultadoLeituraDTO`, usando `Smalot\PdfParser\Parser`: captura `\Throwable` → `corrompido`; texto com menos de `config('banco_talentos.minimo_caracteres_texto')` caracteres não brancos → `sem_texto`
- [X] T066 [P] [US2] Criar `app/DTOs/DadosExtraidosDTO.php` (`final readonly`) com os campos exatos de `contracts/extracao-curriculo.md` (`nome`, `email`, `telefone`, `cpf`, `data_nascimento`, `formacoes`, `experiencias`, `projetos`, `links`, `competencias`, `sugestoes_competencias`, `area_sugerida`, `experiencia_na_area`), PHPDoc com *array shapes*, `toArray()`, `fromArray()` e `vazio()`
- [X] T067 [US2] Criar `app/Services/Curriculo/ExtratorCurriculo.php` com `extrair(string $texto): DadosExtraidosDTO`, seguindo as 6 etapas de `research.md` R4 e as regras de R5: normalização com `Str::ascii` + minúsculas; regex de e-mail, telefone BR, CPF (com validação dos dígitos verificadores), data de nascimento e links; nome pela heurística da primeira linha; divisão por cabeçalhos de seção; casamento de cada nome/sinônimo do catálogo (via `CompetenciaRepositoryInterface`) com fronteiras `(?<![a-z0-9])…(?![a-z0-9])` e `preg_quote`, guardando o trecho; idiomas por nível na mesma linha ("inglês" sem nível → Inglês básico); termos da seção de competências fora do catálogo → `sugestoes_competencias`; área sugerida pela área mais frequente das hard skills encontradas; `experiencia_na_area` verdadeiro quando a seção de experiência contém competência da área sugerida
- [X] T068 [US2] Criar `app/Services/Curriculo/ImportacaoCurriculoService.php`: `importar(UploadedFile $arquivo, TipoCandidato $tipo): string` (grava em `curriculos/tmp/{uuid}.pdf` no disco `curriculos`, chama `LeitorPdf` e `ExtratorCurriculo` e guarda na sessão `importacoes.{uuid}` = `['arquivo', 'nome_original', 'tamanho', 'tipo', 'situacao', 'texto', 'dados' => DadosExtraidosDTO::toArray()]`, devolvendo o uuid); `obter(string $uuid): ?array`; `duplicado(?string $email, ?string $cpf): ?Candidato` (via `CandidatoService::encontrarPorEmailOuCpf`); `descartar(string $uuid): void`
- [X] T069 [US2] Criar `app/Services/Curriculo/CadastroCandidatoService.php` com `confirmar(array $importacao, array $dadosRevisados, User $recrutador): Candidato`, em `DB::transaction`. Modo `novo`: cria o candidato, move o PDF de `tmp/` para `{uuid}.pdf`, grava `consentimento_registrado_por` e anexa as competências (origem `curriculo` se vieram da extração, `revisao` se acrescentadas na revisão). Modo `atualizar`: sobrescreve os campos revisados do candidato existente, troca o PDF (apaga o anterior **depois do commit** com `DB::afterCommit`), mescla as competências respeitando `OrigemCompetencia::prioridade()` (nunca rebaixa) e renova `dados_atualizados_em`, mantendo `codigo`, participações e anotações. Também `atualizar(Candidato $candidato, array $dados): Candidato` para a edição manual (usado na US6)
- [X] T070 [P] [US2] Criar `app/Http/Requests/ImportarCurriculoRequest.php`: `arquivo` required|file|mimes:pdf|max:10240; `tipo` required `Rule::enum(TipoCandidato::class)`; mensagem personalizada "Envie um arquivo PDF de até 10 MB."
- [X] T071 [P] [US2] Criar `app/Http/Requests/ConfirmarImportacaoRequest.php`. No `prepareForValidation`: remover não dígitos do `cpf` e transformar os *textareas* `formacoes`, `experiencias`, `projetos` e `links` (uma linha por item) em arrays sem linhas vazias. Regras de `data-model.md`: `nome` required|string|max:150; `email` nullable|email|max:150; `telefone` nullable|string|max:30; `cpf` nullable|digits:11; `data_nascimento` nullable|date|before:today; `tipo` required enum; `area` required enum; `experiencia_na_area` boolean; `formacoes.*`, `experiencias.*` e `projetos.*` string|max:300; `links.*` url|max:300; `competencias` array, `competencias.*` integer|exists:competencias,id; `consentimento_meio` required enum `MeioConsentimento`; `consentimento_em` required|date|before_or_equal:today; `modo` required|in:novo,atualizar; `candidato_existente_id` required_if:modo,atualizar|exists:candidatos,id. Mensagem de consentimento: "Registre como e quando o candidato consentiu com o uso dos dados."
- [X] T072 [P] [US2] Criar `app/Http/Requests/CriarCompetenciaRequest.php` (`nome` required|string|max:80|unique:competencias,nome; `categoria` required enum `CategoriaCompetencia`) e `app/Http/Controllers/CompetenciaController.php` com `store()`, que cria a competência (`sinonimos` = [nome normalizado]) e volta com "Competência adicionada ao catálogo."
- [X] T073 [US2] Criar `app/Http/Controllers/ImportacaoCurriculoController.php` com `create()` (formulário), `store(ImportarCurriculoRequest)` (chama `importar` e redireciona para `importacoes.show`) e `show(string $importacao)` (404 com link "Enviar novamente" se não existir na sessão; passa os dados extraídos, o duplicado eventual, a situação de leitura e o catálogo agrupado para a view)
- [X] T074 [US2] Criar `app/Http/Controllers/ConfirmacaoImportacaoController.php` com `store(ConfirmarImportacaoRequest, string $importacao)`: 404 se a importação não existir; chama `CadastroCandidatoService::confirmar`, limpa a sessão e redireciona para `candidatos.show` com "Candidato salvo no banco de talentos."
- [X] T075 [US2] Em `routes/candidatos.php`, no bloco `// US2 — importação`, adicionar `GET /candidatos/importar` (`importacoes.create`), `POST /candidatos/importar` (`importacoes.store`), `GET /candidatos/importar/{importacao}` (`importacoes.show`, `whereUuid`), `POST /candidatos/importar/{importacao}/confirmar` (`importacoes.confirmar`) e `POST /competencias` (`competencias.store`)
- [X] T076 [P] [US2] Criar `resources/views/importacoes/create.blade.php`: área de envio com o input de arquivo (`accept="application/pdf"`), escolha de tipo (Servidor/Estagiário) em dois cartões grandes com as cores do tipo, texto "PDF com texto selecionável, até 10 MB" e botão "Ler currículo"
- [X] T077 [US2] Criar `resources/views/candidatos/partials/_formulario.blade.php`, reutilizável na revisão e na edição (US6): identificação e contato; tipo, área e experiência na área; *textareas* de formação, experiências, projetos e links (uma linha por item); competências em *chips* de *checkbox* agrupados (hard/soft) com o filtro de *chips*, mostrando o trecho de origem quando houver; bloco "Consentimento do candidato" (select de meio, data e texto explicativo LGPD)
- [X] T078 [US2] Criar `resources/views/importacoes/show.blade.php` (tela de revisão): `x-alerta` de acordo com a situação (`sem_texto`: "Não conseguimos ler o texto deste PDF (parece digitalizado). Preencha os dados manualmente."; `corrompido`: "O arquivo parece corrompido."); alerta de duplicidade com o nome do cadastro existente e radios `modo` ("Atualizar cadastro existente", marcado por padrão, ou "Criar novo mesmo assim") + `candidato_existente_id` oculto; `_formulario` pré-preenchido; soft skills marcadas como "declaradas no currículo"; *chips* de `sugestoes_competencias` com mini-formulário para `competencias.store` ("Adicionar ao catálogo") ou "Ignorar"; botão "Confirmar e salvar"
- [X] T079 [US2] Criar o comando `app/Console/Commands/GerarCurriculosDemoCommand.php` (`demo:curriculos`), que grava com `GeradorPdfCurriculo` três arquivos em `database/seeders/curriculos/`: `demo-curriculo.pdf` (estagiária fictícia de desenvolvimento, coluna única, com Python, SQL, Git, Inglês intermediário, Trabalho em equipe, Comunicação, um termo fora do catálogo "Kubernetes" e um link do GitHub), `demo-escaneado.pdf` (`gerarSemTexto()`) e `demo-duplicado.pdf` (mesmo e-mail `ana.lima@exemplo.com.br` de E3). Rodar o comando e versionar os três PDFs. Opcional: a equipe pode trocar `demo-curriculo.pdf` por uma versão diagramada em editor de texto, **em coluna única**, desde que testada no fluxo de importação

**Checkpoint**: cenário B do quickstart funciona; `ExtratorCurriculoTest` e
`ImportacaoCurriculoTest` passam; a US1 continua funcionando.

### AJAX da US2 — incluir no checkpoint da fase

- [X] T155 [US2] Ampliar `tests/Feature/ImportacaoCurriculoTest.php` antes de T156: upload/confirmar com `Accept: application/json` devolvem `redirect` correto após persistência; erros 422 não criam candidato; importação expirada retorna 404; `competencias.store` devolve HTML só do catálogo, sem expor a revisão inteira; nome de competência com conteúdo HTML é escapado, duplicidade retorna 422; modo HTML mantém PRG. Depende de T151 e T064
- [X] T156 [US2] Integrar AJAX em `app/Http/Controllers/ImportacaoCurriculoController.php`, `ConfirmacaoImportacaoController.php`, `CompetenciaController.php`, `resources/views/importacoes/create.blade.php`, `resources/views/importacoes/show.blade.php` e `resources/views/candidatos/partials/_formulario.blade.php`: envio de PDF/confirmar com `FormData`, loading, erro local e navegação só no sucesso; competência adicionada retorna `catalogo-competencias`, atualizando opções sem perder dados editados ou seleções (mesclar apenas novas opções). Manter mini-formulários fora do formulário principal, sem aninhamento; não repetir upload/confirmação após falha de rede e não reenviar ao incluir competência. Depende de T155, T073–T078 e T150; validar B/H com e sem JS

**Implementação da Phase 4 (2026-10-31):** T063–T079 e T155–T156 implementadas por uma pessoa
(trilha B inteira), sem avançar às fases seguintes. Relatório completo em
[specs/implementacoes/fase-4-importacao.md](../implementacoes/fase-4-importacao.md).
Testes escritos antes do código e falhas iniciais confirmadas: **59 PHP e 7 JS falhando**.

- **Leitura e extração**: `LeitorPdf` (`smalot/pdfparser`), `ExtratorCurriculo` determinístico,
  `NormalizadorTexto` compartilhado com o catálogo e DTOs `ResultadoLeituraDTO` e
  `DadosExtraidosDTO`.
- **Importação em duas etapas**: `ImportacaoCurriculoService`, com PDF em `tmp/` privado e a
  sessão `importacoes.{uuid}`, e `CadastroCandidatoService`, com modos novo e atualizar,
  origem que nunca é rebaixada e PDF anterior apagado após o commit.
- **Catálogo**: `CompetenciaService`, com slug único garantido no model.
- **Rotas**: bloco `// US2` de `routes/candidatos.php`.
- **AJAX**: upload e confirmação devolvem `redirect` com `fragments: {}`.
  `competencias.store` devolve só `catalogo-competencias`, mesclado no cliente por
  `data-ajax-mesclar` (`mesclarFragmento` em `ajax.js`); o evento `ajax:sucesso` alimenta
  `modulos/importacao.js`.
- **Erros por grupo**: rádios e checkboxes de um grupo mostram um único aviso de erro AJAX.
- **PDFs de demonstração**: gerados por `php artisan demo:curriculos` e versionados em
  `database/seeders/curriculos/`.
- **Desvios**:
  - o modo `atualizar` exige que o cadastro escolhido corresponda ao e-mail/CPF do currículo
    (lido ou revisado);
  - a importação expirada mostra `importacoes/expirada` com "Enviar novamente";
  - sugestões só vêm da seção de competências do PDF;
  - `x-campo` passou a aceitar listas (textarea) e erros `campo.*`;
  - o teste da Phase 3 que usava `/candidatos/importar` como caminho não numérico foi trocado
    por `/candidatos/ana-lima`, porque a rota agora existe.
- **Validação**: **131 testes PHP / 622 asserções e 32 testes JavaScript**, Pint aplicado,
  imagem Docker reconstruída (build Vite ok) e teste de fumaça HTTP do cenário B aprovado em
  container sem `.env`. O fluxo testado:
  - upload por AJAX → revisão com 6/6 competências do PDF marcadas;
  - "Kubernetes" sugerido e área "Desenvolvimento" sugerida;
  - FR-011 bloqueia sem consentimento;
  - cadastro salvo e encontrado na busca;
  - importação concluída responde 404;
  - avisos de duplicidade e de PDF digitalizado aparecem.
- **Gate manual pendente**: revisar B/H num navegador real (com e sem JS, teclado, foco
  após incluir sugestão, rede lenta).

---

## Phase 5: User Story 3 — Cadastrar vaga com pesos e ver o ranking explicado (Priority: P2)

**Goal**: CRUD de vaga com competências hard/soft e peso 1–3; ranking dos elegíveis por
aderência, com percentual visual e explicação.

**Independent Test**: criar "Estagiário de Desenvolvimento" com Python (3), SQL (3), Git (2),
Inglês básico (1) e Trabalho em equipe (1) e ver só estagiários, ordenados pela fórmula, cada
um com a explicação (quickstart, cenário C).

### Tests for User Story 3 ⚠️

- [ ] T080 [P] [US3] Criar `tests/Unit/CalculadoraAderenciaTest.php`: fórmula `round(Σ atendidos ÷ Σ total × 100)` (ex.: pesos 3, 3, 2, 1, 1 atendendo todos menos o de peso 2 → 80); arredondamento *half up* (2 de 3 com pesos iguais → 67); 0% com a mensagem "nenhuma competência solicitada foi identificada no currículo"; 100% com "tem todas as N competências"; "Inglês avançado" atende "Inglês básico", mas "Inglês básico" não atende "Inglês intermediário"; `pesoEssencialAtendido` soma só os pesos 3; o texto da explicação segue `research.md` R7 ("80% — tem 4 de 5 competências; não identificado: Git (peso 2)")
- [ ] T081 [P] [US3] Criar `tests/Feature/RankingVagaTest.php`: vaga `estagiario` não lista servidores (e o inverso); `ambos` lista os dois tipos; desativados não aparecem; a ordem segue percentual ↓, peso essencial atendido ↓, `created_at` ↑ e `id` ↑; 100% dos cards têm explicação; alterar o peso de uma competência muda a ordem na próxima abertura (FR-020); candidato criado depois da vaga aparece no ranking
- [ ] T082 [P] [US3] Criar `tests/Feature/VagaTest.php`: criar uma vaga válida redireciona para o quadro e a vaga fica `aberta`; vaga sem competências é rejeitada; peso fora de {1, 2, 3} é rejeitado; competência repetida é rejeitada; a edição atualiza competências e pesos; encerrar define `status = encerrada` e `encerrada_em`

### Implementation for User Story 3

- [ ] T083 [P] [US3] Criar `app/DTOs/ResultadoAderenciaDTO.php` (`final readonly`: `int $percentual`, `int $pesoAtendido`, `int $pesoTotal`, `int $pesoEssencialAtendido`, `array $atendidas` (lista de `{nome, peso, origem: OrigemCompetencia}`), `array $naoIdentificadas` (lista de `{nome, peso}`), método `explicacao(): string`, conforme `research.md` R7)
- [ ] T084 [US3] Criar `app/Services/Aderencia/CalculadoraAderencia.php`, classe pura sem acesso a banco, com `calcular(Collection $requisitos, Collection $competenciasDoCandidato): ResultadoAderenciaDTO`. `$requisitos`: Competencias com `pivot->peso`. `$competenciasDoCandidato`: Competencias com `pivot->origem`. Um requisito é atendido pelo mesmo id ou, se `ehIdioma()`, por competência do mesmo `grupo` com `nivel` ≥
- [ ] T085 [P] [US3] Criar `app/DTOs/ItemRankingDTO.php` (`final readonly`: `string $codigo`, `TipoCandidato $tipo`, `Area $area`, `bool $experienciaNaArea`, `ResultadoAderenciaDTO $resultado`, `EtapaSelecao $etapa`, `?string $nome` (nulo, exceto quando a identidade for revelada na US4), `?int $proximidadeEnergia` (preenchido na US7)). **Não** guarda o model `Candidato` nem o id
- [ ] T086 [US3] Criar `app/Repositories/Interfaces/VagaRepositoryInterface.php` e `app/Repositories/VagaRepository.php` (`listar(): Collection` com contagem por etapa; `criar(array $dados, array $competenciasComPeso, User $criador): Vaga`; `atualizar(Vaga $vaga, array $dados, array $competenciasComPeso): Vaga` com `sync` dos pesos; `encerrar(Vaga $vaga): void`; `candidatosElegiveis(Vaga $vaga): Collection`, com escopo `elegiveisPara`, eager load de `competencias` e ordenação por `created_at` e `id`) e registrar `VagaRepositoryInterface` → `VagaRepository` em `app/Providers/RepositoryServiceProvider.php`
- [ ] T087 [US3] Criar `app/Services/Aderencia/RankingVagaService.php` com `ranquear(Vaga $vaga): Collection<ItemRankingDTO>`: carrega os requisitos da vaga e os elegíveis via `VagaRepositoryInterface`, calcula cada `ResultadoAderenciaDTO`, ordena por percentual ↓, `pesoEssencialAtendido` ↓, `created_at` ↑ e `id` ↑ e monta `ItemRankingDTO` com `etapa = Triagem` e `nome = null`
- [ ] T088 [US3] Criar `app/Http/Requests/SalvarVagaRequest.php`. No `prepareForValidation`, converter `competencias[ID] = peso` (selects com opção vazia "Não usar") em uma lista `[{id, peso}]` sem itens vazios. Regras: `titulo` required|string|max:120; `area` required enum; `tipo_candidato` required enum `TipoCandidatoVaga`; `descricao` nullable|string|max:1000; `competencias` required|array|min:1; `competencias.*.id` required|distinct|exists:competencias,id; `competencias.*.peso` required|in:1,2,3. Mensagem: "Selecione ao menos uma competência para a vaga."
- [ ] T089 [US3] Criar `app/Http/Controllers/VagaController.php` com `index`, `create`, `store` (redireciona para `vagas.show` com "Vaga criada."), `show(Vaga $vaga)` (usa `RankingVagaService`), `edit` e `update`
- [ ] T090 [US3] Criar `app/Http/Controllers/VagaStatusController.php` com `update(Vaga $vaga)`, que encerra a vaga e volta com "Vaga encerrada. O quadro fica disponível para consulta."
- [ ] T091 [US3] Em `routes/vagas.php`, no bloco `// US3 — vagas`, adicionar `GET /vagas` (`vagas.index`), `GET /vagas/criar` (`vagas.create`), `POST /vagas` (`vagas.store`), `GET /vagas/{vaga}` (`vagas.show`), `GET /vagas/{vaga}/editar` (`vagas.edit`), `PUT /vagas/{vaga}` (`vagas.update`) e `PATCH /vagas/{vaga}/status` (`vagas.status`)
- [ ] T092 [US3] Criar `resources/views/vagas/partials/_formulario.blade.php` (título, área, tipo de candidato, descrição; duas seções "Competências técnicas (hard skills)" e "Competências comportamentais (soft skills)", cada competência com um select de peso `—`/1 Desejável/2 Importante/3 Essencial e o filtro de *chips*; espaço reservado para o padrão de energia (US7)), `resources/views/vagas/create.blade.php` e `resources/views/vagas/edit.blade.php`
- [ ] T093 [US3] Criar `resources/views/vagas/index.blade.php`: cards de vaga com título, área, tipo aceito (`x-badge`), status, contagem de candidatos elegíveis e botão "Nova vaga"; `x-estado-vazio` quando não houver vagas
- [ ] T094 [US3] Criar `resources/views/vagas/partials/_card-ranking.blade.php` (código, `x-badge-tipo`, área, `x-barra-score`, `explicacao()`, tags atendidas com origem e tags `faltante` com peso, experiência na área; **sem** nome, contato ou link para `candidatos.show`) com um `@include('vagas.partials._selo-energia', ['item' => $item])` apontando para um *partial* vazio criado agora (preenchido em T140 pela outra trilha), e `resources/views/vagas/show.blade.php` (cabeçalho da vaga com competências e pesos, ações Editar/Encerrar e a lista ranqueada "Triagem")
- [ ] T095 [US3] Criar `database/seeders/VagaSeeder.php` (o *stub* já está registrado por T033) com 3 vagas abertas: "Analista de Suporte Técnico" (servidor, suporte: Atendimento ao público 3, Windows 3, ITIL 2, Comunicação 2, Proatividade 1); "Analista de Dados" (ambos, dados: SQL 3, Power BI 3, Python 2, Pensamento analítico 1, Inglês intermediário 1); "Estagiário de Infraestrutura" (estagiário, infraestrutura: Linux 3, Redes de Computadores 3, Suporte a Hardware 2, Trabalho em equipe 1). A vaga "Estagiário de Desenvolvimento" **não** é semeada (é criada ao vivo no pitch)

**Checkpoint**: cenário C do quickstart funciona; `CalculadoraAderenciaTest`,
`RankingVagaTest` e `VagaTest` passam.

### AJAX da US3 — incluir no checkpoint da fase

- [ ] T157 [US3] Ampliar `tests/Feature/VagaTest.php` e `tests/Feature/RankingVagaTest.php` antes de T158: criar/editar em JSON com `redirect`, 422 sem escrita inválida, GET do quadro com fragmento e mesma ordenação/explicação do HTML, encerramento em JSON reflete somente consulta e sem ações; ausência de identidade na triagem também no HTML decodificado do envelope. Depende de T151 e T080–T082
- [ ] T158 [US3] Integrar modo JSON em `app/Http/Controllers/VagaController.php` e `VagaStatusController.php`; extrair `resources/views/vagas/partials/_quadro.blade.php` de `resources/views/vagas/show.blade.php`, englobando cabeçalho/status/ações/ranking. Marcar formulários em `resources/views/vagas/create.blade.php`, `resources/views/vagas/edit.blade.php` e ação Encerrar com `data-ajax`; criar ação explícita Atualizar quadro (link GET real interceptado), sem polling. Criar/editar navega após sucesso; atualizar/encerrar substitui `quadro-vaga` via transporte comum e preserva o fallback HTML. Depende de T157 e T089–T094; validar C/H

---

## Phase 6: User Story 4 — Triagem anônima e acompanhamento em kanban (Priority: P2)

**Goal**: quadro Triagem / Entrevista / Aprovado por vaga, com triagem anônima, identidade
revelada por vaga e histórico de movimentações.

**Independent Test**: os cards de triagem mostram só código, score e competências; o PDF
responde 403; ao mover três candidatos para Entrevista, os nomes aparecem só nessa vaga
(quickstart, cenário D).

### Tests for User Story 4 ⚠️

- [ ] T096 [P] [US4] Criar `tests/Feature/TriagemAnonimaTest.php`, cobrindo as invariantes 1, 3, 4 e 6 de `contracts/visibilidade-por-contexto.md`: `GET /vagas/{A}` e `GET /vagas/{A}/candidatos/{codigo}` em triagem não contêm `nome`, `email`, `telefone` e `cpf` do candidato nem `route('candidatos.show', $candidato)` (`assertDontSee`); depois de mover para Entrevista na vaga A, o nome aparece em A e **não** na vaga B; ao voltar para Triagem, o nome some de A; itens de experiência com e-mail ou URL aparecem sem eles na triagem; anotações não aparecem no perfil em triagem
- [ ] T097 [P] [US4] Criar `tests/Feature/CurriculoProtegidoTest.php` (invariantes 2 e 8): `GET /vagas/{vaga}/candidatos/{codigo}/curriculo` responde 403 em triagem e 200 com `Content-Type: application/pdf` em Entrevista e em Aprovado; visitante é redirecionado para o login em `candidatos.curriculo` e na rota da vaga; código inexistente responde 404
- [ ] T098 [P] [US4] Criar `tests/Feature/MovimentacaoEtapaTest.php`: mover cria `participacoes` e uma `movimentacoes_etapa` com `user_id`, `de` e `para` (invariante 5); mover numa vaga não altera a etapa em outra (FR-022); vaga encerrada rejeita a movimentação com mensagem de erro; candidato desativado não pode ser movido; candidato inelegível (tipo diferente) não pode ser movido; `etapa` inválida é rejeitada

### Implementation for User Story 4

- [ ] T099 [P] [US4] Criar `app/Services/Selecao/LimpadorTexto.php` com `limpar(string $texto, array $termosProibidos = []): string`, que remove e-mails, telefones, CPFs (com ou sem máscara), URLs e os termos informados (os nomes do candidato, sem diferenciar maiúsculas e acentos), trocando-os por "[oculto]"
- [ ] T100 [P] [US4] Criar `app/DTOs/PerfilNaVagaDTO.php` (`final readonly`): `string $codigo`, `bool $identidadeRevelada`, `EtapaSelecao $etapa`, `TipoCandidato $tipo`, `Area $area`, `bool $experienciaNaArea`, `array $formacoes`, `array $experiencias`, `array $projetos`, `Collection $competencias`, `ResultadoAderenciaDTO $resultado`, `int $quantidadeLinks`, `Collection $movimentacoes`; e, **nulos** quando não revelado, `?string $nome`, `?string $email`, `?string $telefone`, `?int $idade`, `?array $links`, `?Collection $anotacoes`, `bool $temCurriculo`
- [ ] T101 [US4] Criar `app/Repositories/Interfaces/ParticipacaoRepositoryInterface.php` e `app/Repositories/ParticipacaoRepository.php` (`etapasPorCandidato(Vaga $vaga): array<int, EtapaSelecao>`; `encontrar(Vaga $vaga, Candidato $candidato): ?Participacao`; `moverPara(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): Participacao`, com upsert + `MovimentacaoEtapa` em transação; `historico(Vaga $vaga, Candidato $candidato): Collection`) e registrar `ParticipacaoRepositoryInterface` → `ParticipacaoRepository` no `RepositoryServiceProvider`
- [ ] T102 [US4] Criar `app/Services/Selecao/VisibilidadeCandidatoService.php`: `etapaNaVaga(Vaga $vaga, Candidato $candidato): EtapaSelecao` (sem participação → Triagem); `podeRevelar(Vaga $vaga, Candidato $candidato): bool` (`etapa->revelaIdentidade()`); `perfilNaVaga(Vaga $vaga, Candidato $candidato): PerfilNaVagaDTO`, preenchendo identidade, contatos, idade, links e anotações **somente** quando revelado e passando formação, experiências e projetos pelo `LimpadorTexto` quando não revelado (matriz de `contracts/visibilidade-por-contexto.md`)
- [ ] T103 [US4] Criar `app/Services/Selecao/SelecaoService.php`: `quadro(Vaga $vaga): array{triagem: Collection<ItemRankingDTO>, entrevista: Collection<ItemRankingDTO>, aprovado: Collection<ItemRankingDTO>}`, usando `RankingVagaService` + `ParticipacaoRepositoryInterface::etapasPorCandidato` (Triagem = elegíveis sem participação ou em `triagem`; Entrevista e Aprovado incluem desativados com participação, marcados para exibição esmaecida; `nome` preenchido só nas etapas reveladas); `moverEtapa(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): void`, que lança `ValidationException` com mensagem amigável se a vaga estiver encerrada, o candidato desativado ou não elegível
- [ ] T104 [US4] Criar `app/Http/Requests/MoverEtapaRequest.php` (`etapa` required `Rule::enum(EtapaSelecao::class)`)
- [ ] T105 [US4] Criar `app/Http/Controllers/ParticipacaoController.php` com `show(Vaga $vaga, Candidato $candidato)`, que renderiza `participacoes.show` **só** com o `PerfilNaVagaDTO` (nunca passar o model `Candidato` à view)
- [ ] T106 [US4] Criar `app/Http/Controllers/ParticipacaoEtapaController.php` com `update(MoverEtapaRequest, Vaga $vaga, Candidato $candidato)`: chama `SelecaoService::moverEtapa` e volta para `vagas.show` com "CAND-NNNN movido para Entrevista." (ou o nome, se a etapa revelar)
- [ ] T107 [US4] Criar `app/Http/Controllers/ParticipacaoCurriculoController.php` com `show(Vaga $vaga, Candidato $candidato)`: `abort_unless($visibilidade->podeRevelar($vaga, $candidato), 403)`; depois, a mesma resposta `inline`/`no-store` de T057
- [ ] T108 [US4] Em `routes/vagas.php`, no bloco `// US4 — seleção`, adicionar `GET /vagas/{vaga}/candidatos/{candidato:codigo}` (`vagas.candidatos.show`), `PATCH /vagas/{vaga}/candidatos/{candidato:codigo}/etapa` (`vagas.candidatos.etapa`) e `GET /vagas/{vaga}/candidatos/{candidato:codigo}/curriculo` (`vagas.candidatos.curriculo`), **todas** com `->withoutScopedBindings()` (research R9)
- [ ] T109 [US4] Transformar `resources/views/vagas/show.blade.php` em quadro de 3 colunas (Triagem, Entrevista, Aprovado) usando o novo `resources/views/vagas/partials/_coluna.blade.php` (título, contagem e estado vazio por coluna). Atualizar `_card-ranking.blade.php`: mostrar o nome só quando `$item->nome` não for nulo (código como texto secundário); link do card para `vagas.candidatos.show` (pelo código); botões de movimentação (Triagem → "Mover para Entrevista"; Entrevista → "Aprovar" e "Voltar para Triagem"; Aprovado → "Voltar para Entrevista") com formulário `PATCH`; `draggable="true"` e `data-codigo`; selo "Desativado" esmaecido e sem ações; vaga encerrada sem botões e com o aviso "Vaga encerrada — somente consulta"
- [ ] T110 [US4] Criar `resources/views/participacoes/show.blade.php` a partir do `PerfilNaVagaDTO`: em triagem, cabeçalho com `x-icone cadeado` e "Identidade protegida na triagem anônima", código, tipo, área, aderência explicada, formação, experiências, projetos, competências e "N links — visíveis a partir da entrevista"; quando revelado, também nome, contatos, idade, links, botão "Abrir PDF" (`vagas.candidatos.curriculo`) e espaço reservado para as anotações (US5); sempre o histórico de movimentações da vaga ("Movido para Entrevista por {usuário} em dd/mm/aaaa hh:mm") e os botões de movimentação
- [ ] T111 [US4] Em `resources/js/modulos/kanban.js`, implementar arrastar e soltar (HTML5 Drag and Drop) no quadro: soltar um card numa coluna `[data-etapa]` envia o mesmo formulário `PATCH` do card com o valor da etapa de destino pelo transporte AJAX de T160; sem JavaScript, os botões continuam funcionando. Executar depois de T160; esta melhoria continua opcional e seu corte não adia o AJAX obrigatório por botão
- [ ] T112 [US4] Em `database/seeders/VagaSeeder.php`, mover via `ParticipacaoRepositoryInterface::moverPara` (usuário do `UsuarioSeeder`) S3 e S9 para Entrevista na vaga "Analista de Dados", para o quadro semeado já mostrar candidatos revelados

**Checkpoint**: cenário D do quickstart funciona; `TriagemAnonimaTest`,
`CurriculoProtegidoTest` e `MovimentacaoEtapaTest` passam (gates da constituição).

### AJAX da US4 — incluir no checkpoint da fase

- [ ] T159 [US4] Ampliar `tests/Feature/MovimentacaoEtapaTest.php` e `tests/Feature/TriagemAnonimaTest.php` antes de T160: botão AJAX persiste uma movimentação e retorna quadro/perfil conforme `contexto`; 422 para etapa/contexto inválidos, vaga encerrada, candidato desativado/inelegível; JSON e fragmentos respeitam anonimato por vaga, inclusive mensagens e retorno à triagem; não aceitar seletor/view arbitrário do cliente. Testar o candidato afetado no envelope decodificado, sem confundir identidades de outros candidatos já revelados. Depende de T151, T098 e T158
- [ ] T160 [US4] Em `app/Http/Requests/MoverEtapaRequest.php`, validar `contexto` opcional em `quadro,perfil`; em `app/Http/Controllers/ParticipacaoEtapaController.php`, devolver JSON com `quadro-vaga` ou `perfil-na-vaga` renderizado após persistência via Services/DTOs. Extrair `resources/views/participacoes/partials/_perfil.blade.php` e incluir em `resources/views/participacoes/show.blade.php`; ajustar `resources/views/vagas/partials/_quadro.blade.php`, `_coluna.blade.php` e `_card-ranking.blade.php` para botões AJAX, estado pendente e contagens. Em `resources/js/modulos/kanban.js`, usar o transporte comum e bloquear novas ações do quadro enquanto pendente; não mover/revelar otimisticamente, substituir toda a região afetada e restaurar foco. Retorno à triagem remove também identidade/anotações do perfil, sem HTML oculto residual. Depende de T159 e T101–T110; validar D/H

---

## Phase 7: User Story 5 — Registrar e recuperar anotações de entrevista (Priority: P3)

**Goal**: anotações com autor e data automáticos, vaga opcional e competências observadas, com
histórico no perfil.

**Independent Test**: registrar uma anotação para um candidato em Entrevista marcando uma soft
skill observada e vê-la no histórico com autor, data e vaga, também ao abrir o candidato por
outra vaga em que ele esteja revelado (quickstart, cenário E).

### Tests for User Story 5 ⚠️

- [ ] T113 [P] [US5] Criar `tests/Feature/AnotacaoEntrevistaTest.php`: anotação pela vaga (candidato em Entrevista) grava `user_id`, `vaga_id` e o texto; pela vaga com candidato em triagem responde 403; pela gestão do banco sem vaga grava `vaga_id = null`; competência observada passa a ter origem `entrevista` no candidato (promovendo uma origem `curriculo`); o histórico aparece em ordem decrescente de `created_at`; o perfil em triagem não mostra anotações; o perfil revelado em outra vaga mostra todas; `texto` vazio ou com mais de 5000 caracteres é rejeitado

### Implementation for User Story 5

- [ ] T114 [P] [US5] Criar `app/Repositories/Interfaces/AnotacaoRepositoryInterface.php` e `app/Repositories/AnotacaoRepository.php` (`criar(Candidato $candidato, ?Vaga $vaga, User $autor, string $texto, array $competenciasObservadas): Anotacao`; `historico(Candidato $candidato): Collection`, com autor, vaga e competências e ordem decrescente) e registrar `AnotacaoRepositoryInterface` → `AnotacaoRepository` no `RepositoryServiceProvider`
- [ ] T115 [US5] Criar `app/Services/Anotacoes/AnotacaoService.php` com `registrar(Candidato $candidato, ?Vaga $vaga, User $autor, string $texto, array $competenciasObservadas): Anotacao` em `DB::transaction`: cria a anotação e o pivot `anotacao_competencia` e faz o upsert em `candidato_competencia` com origem `entrevista` (promove, nunca rebaixa, via `OrigemCompetencia::prioridade()`)
- [ ] T116 [US5] Criar `app/Http/Requests/RegistrarAnotacaoRequest.php`: `texto` required|string|max:5000; `vaga_id` nullable|exists:vagas,id; `competencias_observadas` nullable|array; `competencias_observadas.*` integer|exists:competencias,id
- [ ] T117 [US5] Criar `app/Http/Controllers/AnotacaoController.php` com `store(RegistrarAnotacaoRequest, Candidato $candidato)` (contexto do banco; vaga opcional vinda do request), que volta com "Anotação registrada."
- [ ] T118 [US5] Criar `app/Http/Controllers/ParticipacaoAnotacaoController.php` com `store(RegistrarAnotacaoRequest, Vaga $vaga, Candidato $candidato)`: `abort_unless(podeRevelar, 403)`; a vaga é a da rota (ignora `vaga_id` do request)
- [ ] T119 [US5] Adicionar `POST /candidatos/{candidato}/anotacoes` (`candidatos.anotacoes.store`) em `routes/candidatos.php` (bloco `// US5 — anotações`) e `POST /vagas/{vaga}/candidatos/{candidato:codigo}/anotacoes` (`vagas.candidatos.anotacoes.store`, com `->withoutScopedBindings()`) em `routes/vagas.php` (bloco `// US5 — anotações`)
- [ ] T120 [US5] Criar `resources/views/anotacoes/_formulario.blade.php` (textarea, select opcional de vaga só no contexto do banco, *chips* de competências observadas) e `resources/views/anotacoes/_historico.blade.php` (cada item com autor, data dd/mm/aaaa hh:mm, vaga ou "Sem vaga associada", texto e tags "observada em entrevista"; estado vazio "Nenhuma anotação ainda") e incluí-los em `resources/views/candidatos/partials/_secao-anotacoes.blade.php` e, só quando revelado, em `resources/views/participacoes/show.blade.php`
- [ ] T121 [US5] Em `database/seeders/VagaSeeder.php`, registrar via `AnotacaoService` uma anotação para S3 e outra para S9 na vaga "Analista de Dados" (texto fictício sobre a entrevista, competência observada "Comunicação")

**Checkpoint**: cenário E do quickstart funciona; `AnotacaoEntrevistaTest` passa.

### AJAX da US5 — incluir no checkpoint da fase

- [ ] T161 [US5] Ampliar `tests/Feature/AnotacaoEntrevistaTest.php` antes de T162: JSON válido retorna histórico e competências atualizados, persistindo autor/vaga/origem; texto inválido gera 422; triagem gera 403 mesmo com contexto/headers manipulados; texto livre é escapado no fragmento; formulário HTML segue funcionando. Parte do banco depende de T151/T113; parte da vaga depende também de T160
- [ ] T162 [US5] Integrar JSON em `app/Http/Controllers/AnotacaoController.php` e `ParticipacaoAnotacaoController.php`; marcar `resources/views/anotacoes/_formulario.blade.php` e regiões em `_historico.blade.php`/`resources/views/candidatos/partials/_secao-anotacoes.blade.php`. Extrair `resources/views/candidatos/partials/_competencias.blade.php` do perfil e reutilizar no contexto da vaga só com os dados autorizados; atualizar includes em `resources/views/candidatos/show.blade.php` e `resources/views/participacoes/partials/_perfil.blade.php`. Retornar `historico-anotacoes` e `competencias-candidato`; limpar texto/seleções apenas após sucesso, não substituir formulários com rascunho em erro. Depende de T161 e T117–T120; parte da vaga somente após T160/S3; validar E/H

---

## Phase 8: User Story 6 — Direitos do titular e retenção (LGPD) (Priority: P3)

**Goal**: consultar o consentimento, corrigir dados, desativar e reativar, excluir em definitivo
com registro mínimo e sinalizar retenção vencida.

**Independent Test**: abrir um candidato, ver o consentimento e a retenção, excluir e conferir
que ele sumiu de buscas, rankings e quadros e que o PDF não abre mais (quickstart, cenário F).

### Tests for User Story 6 ⚠️

- [ ] T122 [P] [US6] Criar `tests/Feature/LgpdTest.php`: o perfil mostra o meio e a data do consentimento e "Retenção até" (`dados_atualizados_em` + 2 anos); editar atualiza os dados e renova `dados_atualizados_em`; desativar oculta da busca e do ranking, e reativar devolve; o filtro `situacao=desativados` lista os desativados; `situacao=retencao` lista só os vencidos, com o selo "revisão de retenção pendente"; o painel conta a retenção pendente; a exclusão definitiva apaga o candidato, `candidato_competencia`, `participacoes`, `movimentacoes_etapa`, `anotacoes`, `anotacao_competencia` e `perfis_energia`, remove o arquivo do disco `curriculos` (`Storage::fake`) e cria **um** `registros_exclusao` com `user_id` e `motivo`, sem dado pessoal; a vaga em que ele participava continua abrindo; excluir sem `confirmacao` é rejeitado

### Implementation for User Story 6

- [ ] T123 [US6] Criar `app/Http/Requests/AtualizarCandidatoRequest.php` (mesmas regras de identificação, perfil, competências e consentimento de `ConfirmarImportacaoRequest`, sem `modo` e `candidato_existente_id`; mesmo `prepareForValidation`); adicionar `edit(Candidato $candidato)` e `update(AtualizarCandidatoRequest, Candidato $candidato)` em `app/Http/Controllers/CandidatoController.php` (usa `CadastroCandidatoService::atualizar`, competências novas com origem `revisao`, volta com "Dados do candidato atualizados."); criar `resources/views/candidatos/edit.blade.php` reutilizando `_formulario`
- [ ] T124 [US6] Criar `app/Http/Requests/AlterarStatusCandidatoRequest.php` (`status` required enum `StatusCandidato`) e `app/Http/Controllers/CandidatoStatusController.php` com `update()`, que altera o status e volta com "Candidato desativado. Ele não aparece em buscas e rankings, mas os dados continuam guardados." ou "Candidato reativado."
- [ ] T125 [US6] Criar `app/Services/Lgpd/ExclusaoCandidatoService.php` com `excluir(Candidato $candidato, MotivoExclusao $motivo, User $responsavel): void`: guarda o caminho do PDF, em `DB::transaction` apaga o candidato (as FKs `cascadeOnDelete` removem os vínculos) e cria `RegistroExclusao` (`user_id`, `motivo`, `excluido_em = now()`); apaga o arquivo do disco `curriculos` **depois do commit**
- [ ] T126 [US6] Criar `app/Http/Requests/ExcluirCandidatoRequest.php` (`motivo` required enum `MotivoExclusao`; `confirmacao` accepted) e o método `destroy(ExcluirCandidatoRequest, Candidato $candidato)` em `CandidatoController`, que redireciona para `candidatos.index` com "Dados do candidato excluídos definitivamente."
- [ ] T127 [US6] Em `routes/candidatos.php`, no bloco `// US6 — LGPD`, adicionar `GET /candidatos/{candidato}/editar` (`candidatos.edit`), `PUT /candidatos/{candidato}` (`candidatos.update`), `PATCH /candidatos/{candidato}/status` (`candidatos.status`) e `DELETE /candidatos/{candidato}` (`candidatos.destroy`)
- [ ] T128 [US6] Em `resources/views/candidatos/partials/_secao-lgpd.blade.php`, preencher a seção "Consentimento e retenção" (meio, data, responsável, "Retenção até dd/mm/aaaa" e o selo "revisão de retenção pendente" quando vencida) e as ações Editar, Desativar/Reativar e "Excluir definitivamente" (`x-modal` explicando o que será apagado, select de motivo, *checkbox* de confirmação e botão `perigo`)
- [ ] T129 [US6] Completar a US1 com a LGPD: em `CandidatoRepository::buscar`, tratar `situacao` `desativados` (`status = desativado`) e `retencao` (ativos com retenção vencida); adicionar `contarRetencaoVencida(): int` a `CandidatoRepositoryInterface`, implementar em `CandidatoRepository` e expor via `CandidatoService` para uso em `PainelService` (cartão "Retenção pendente" com link para `candidatos.index?situacao=retencao`); em `resources/views/candidatos/index.blade.php`, adicionar o seletor de situação; em `_card.blade.php`, adicionar o selo de retenção pendente

**Checkpoint**: cenário F do quickstart funciona; `LgpdTest` passa.

### AJAX da US6 — incluir no checkpoint da fase

- [ ] T163 [US6] Ampliar `tests/Feature/LgpdTest.php` antes de T164: correção JSON retorna navegação ao perfil; status JSON persiste alteração e devolve regiões atualizadas; exclusão JSON exige motivo/confirmação e retorna navegação à lista apenas após excluir; consulta JSON com situação/retenção mantém filtros e ocultação dos desativados; mesmos resultados de persistência no modo HTML. Depende de T122, T151 e T153
- [ ] T164 [US6] Integrar JSON em `app/Http/Controllers/CandidatoController.php` (`update`/`destroy`) e `CandidatoStatusController.php`; ajustar `resources/views/candidatos/edit.blade.php`, `resources/views/candidatos/show.blade.php` e `resources/views/candidatos/partials/_secao-lgpd.blade.php` para formulário AJAX, confirmação explícita da exclusão, regiões `situacao-candidato`/`secao-lgpd` e erros preservando campos. Correção/exclusão navega no sucesso; status atualiza regiões locais, sem promessa de sincronização entre abas. Em `resources/views/candidatos/index.blade.php`, incorporar situação/retenção ao fluxo de T154, sem listener ou busca duplicados. Sem retry automático, sobretudo na exclusão; validar F/H. Depende de T163, T123–T129 e T154

---

## Phase 9: User Story 7 — Padrão de energia com IA (diferencial) (Priority: P4)

**Goal**: padrão desejado nos 3 eixos na vaga, sugestão da IA para o candidato com
justificativa, ajuste manual e proximidade separada da aderência, com o sistema funcionando
normalmente sem IA.

**Independent Test**: definir os eixos numa vaga, analisar candidatos e ver eixos,
justificativa e proximidade separada no ranking; desligar a IA e ver "análise indisponível"
sem afetar o resto (quickstart, cenário G).

### Tests for User Story 7 ⚠️

- [ ] T130 [P] [US7] Criar `tests/Unit/ProximidadeEnergiaTest.php`: vaga (2, 4, 2) × candidato (3, 4, 1) = 83; perfis iguais = 100; extremos opostos (1, 1, 1) × (5, 5, 5) = 0
- [ ] T131 [P] [US7] Criar `tests/Feature/PerfilEnergiaTest.php`, com um analisador falso vinculado via `$this->app->instance(AnalisadorEnergia::class, ...)`: análise com sucesso grava `perfis_energia` com `origem = ia`; o analisador recebe um texto **sem** nome, e-mail, telefone, CPF ou URLs do candidato (invariante 7); analisador devolvendo `null` mostra "Análise indisponível no momento." e nada é gravado; o ajuste manual grava `origem = humano` e `revisado_por`; a ordem do ranking com e sem padrão de energia é a mesma (FR-035); a vaga salva os 3 eixos juntos e rejeita só 1 ou 2 preenchidos; com `ENERGIA_IA_HABILITADA=false`, o container resolve `AnalisadorEnergiaIndisponivel`

### Implementation for User Story 7

- [ ] T132 [P] [US7] Criar `app/DTOs/SugestaoEnergiaDTO.php` (`final readonly`: `int $dinamicoAnalitico`, `int $individualEquipe`, `int $execucaoPlanejamento`, `string $justificativa`) e a interface `app/Services/Energia/AnalisadorEnergia.php` com `analisar(string $textoAnonimizado): ?SugestaoEnergiaDTO`, conforme `contracts/analise-energia.md`
- [ ] T133 [P] [US7] Criar `app/Services/Energia/AnalisadorEnergiaIndisponivel.php`, implementando `AnalisadorEnergia` e sempre devolvendo `null`
- [ ] T134 [P] [US7] Criar `app/Services/Energia/ProximidadeEnergia.php` com `calcular(array $vaga, array $candidato): int` = `round((1 − Σ|Δ| ÷ 12) × 100)` (3 eixos de 1 a 5)
- [ ] T135 [US7] Criar `app/Services/Energia/ClaudeAnalisadorEnergia.php`, implementando `AnalisadorEnergia` com o SDK oficial `anthropic-ai/sdk` (`Anthropic\Client`, chave de `config('services.anthropic.key')`, modelo de `config('services.anthropic.model')`), `messages->create` com o *system prompt* fixo em pt-BR descrito no contrato, `outputConfig` com `'format' => ['type' => 'json_schema', 'schema' => <esquema de contracts/analise-energia.md>]` e esforço baixo, e `maxTokens` 2000. Antes de escrever o código, confirmar na documentação do SDK PHP os nomes das opções de *timeout* (`config('banco_talentos.energia.timeout')`) e de novas tentativas (máximo 1). Ler o primeiro bloco `text`, fazer `json_decode`, validar os eixos entre 1 e 5 e truncar a justificativa em 500 caracteres. Devolver `null` em qualquer exceção do SDK (`Anthropic\Core\Exceptions\APIStatusException` e erros de conexão), em `stopReason === 'refusal'` ou em JSON inválido, registrando `Log::warning` **sem** o conteúdo do currículo. *Plano B (se T001 recusou o SDK)*: mesma classe usando `Http::withHeaders([...])->timeout(...)->post('https://api.anthropic.com/v1/messages', ...)`, com o mesmo contrato
- [ ] T136 [US7] Criar `app/Services/Energia/PerfilEnergiaService.php`: `textoAnonimizado(Candidato $candidato): string` (formações, experiências, projetos e nomes das competências passados pelo `LimpadorTexto` com os termos do nome; **nunca** nome, contatos, nascimento, links ou PDF); `analisar(Candidato $candidato): ?PerfilEnergia` (chama o analisador e faz upsert com `origem = ia` e `analisado_em = now()`, ou devolve `null`); `ajustar(Candidato $candidato, array $eixos, ?string $justificativa, User $revisor): PerfilEnergia` (`origem = humano`)
- [ ] T137 [US7] Em `app/Providers/AppServiceProvider.php` (`register`), vincular `AnalisadorEnergia` a `ClaudeAnalisadorEnergia` quando `config('banco_talentos.energia.habilitada')` for verdadeiro **e** `config('services.anthropic.key')` estiver preenchida; nos outros casos, a `AnalisadorEnergiaIndisponivel`
- [ ] T138 [US7] Criar `app/Http/Requests/AjustarPerfilEnergiaRequest.php` (`dinamico_analitico`, `individual_equipe` e `execucao_planejamento` required|integer|between:1,5; `justificativa` nullable|string|max:500) e `app/Http/Controllers/PerfilEnergiaController.php` com `store(Candidato $candidato)` (analisar; falha → volta com "Análise indisponível no momento.") e `update(AjustarPerfilEnergiaRequest, Candidato $candidato)`; adicionar em `routes/candidatos.php` (bloco `// US7 — energia`) `POST /candidatos/{candidato}/energia` (`candidatos.energia.analisar`) e `PUT /candidatos/{candidato}/energia` (`candidatos.energia.update`)
- [ ] T139 [US7] Em `app/Http/Requests/SalvarVagaRequest.php`, adicionar `energia.dinamico_analitico`, `energia.individual_equipe` e `energia.execucao_planejamento` nullable|integer|between:1,5 com `required_with` cruzado (todos ou nenhum) e persistir nas colunas `energia_*` em `VagaRepository`; em `resources/views/vagas/partials/_formulario.blade.php`, adicionar a seção opcional "Padrão de energia desejado" com três controles de 5 posições (Dinâmico ↔ Analítico, Individual ↔ Equipe, Execução ↔ Planejamento) e a opção "Não definir"
- [ ] T140 [US7] Criar o componente `resources/views/components/eixos-energia.blade.php` (três réguas de 1 a 5 com o rótulo dos extremos, marcador do candidato e, opcionalmente, marcador da vaga); exibir em `candidatos/partials/_secao-energia.blade.php` e `participacoes/show.blade.php` (eixos, justificativa, origem, data, botões "Analisar com IA" e "Ajustar manualmente" com `x-modal`, estados "Não analisado" e "Análise indisponível"); em `RankingVagaService`, preencher `ItemRankingDTO::proximidadeEnergia` com `ProximidadeEnergia` quando a vaga tiver padrão e o candidato tiver perfil (sem alterar a ordenação); em `vagas/partials/_selo-energia.blade.php`, mostrar o selo "Energia: N% de proximidade" separado da barra de aderência (o `_card-ranking` já inclui esse *partial* desde T094). Fazer **depois** da sincronização S3, quando a trilha A já terminou T109
- [ ] T141 [US7] Criar `app/Console/Commands/AnalisarEnergiaCommand.php` (`energia:analisar {--todos}`): sem `--todos`, analisa só candidatos ativos sem perfil; com `--todos`, reanalisa os de origem `ia` (nunca sobrescreve `humano`); barra de progresso e resumo "N analisados, M indisponíveis"; se a IA estiver desligada, avisar e encerrar com sucesso

**Checkpoint**: cenário G do quickstart funciona com a IA ligada e desligada;
`ProximidadeEnergiaTest` e `PerfilEnergiaTest` passam.

### AJAX da US7 — incluir no checkpoint se a história for implementada

- [ ] T165 [US7] Ampliar `tests/Feature/PerfilEnergiaTest.php` antes de T166: analisar/ajustar em JSON retorna `secao-energia` com origem/justificativa escapada; erro de eixos retorna 422; analisador indisponível retorna 503 sem apagar perfil anterior; nenhuma identidade/contato enviado à IA; ranking mantém fórmula e ordenação. Depende de T131/T151; usar analisador falso, sem API externa
- [ ] T166 [US7] Integrar modo JSON em `app/Http/Controllers/PerfilEnergiaController.php` e `resources/views/candidatos/partials/_secao-energia.blade.php` no perfil do banco, com carregamento, bloqueio de duplo envio, feedback de indisponibilidade e ajuste manual; sucesso substitui `secao-energia`, 503 preserva o perfil anterior. Não criar worker, polling, dependência ou endpoint de identidade do banco no contexto de triagem; selos da vaga refletem mudanças na próxima atualização explícita do quadro. Ajustar `resources/js/modulos/ajax.js` somente se o transporte precisar de configuração de tempo limite maior que o orçamento total da chamada de IA (incluindo a tentativa já prevista), sem retry da escrita. Depende de T165/T138/T140 e S3; validar G/H

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: acabamento visual, acessibilidade, verificação final e ensaio do pitch.

- [ ] T142 Revisar todas as telas em `resources/views/` quanto aos estados vazio, de erro e de carregamento (botões de envio com `data-carregando` desabilitados e mostrando "Processando…" no envio do PDF e na análise de IA, implementado em `resources/js/modulos/formularios.js`) e às microinterações leves (transições Tailwind em hover/foco e no surgimento de alertas)
- [ ] T143 [P] Verificar o contraste WCAG 2.1 AA dos pares de cor definidos em `resources/css/app.css` (texto sobre `servidor`, `estagiario`, `primaria` e faixas de score), o foco visível em todos os elementos interativos, os `label`s dos campos e a navegação do quadro por teclado (botões de movimentação)
- [ ] T144 [P] Rodar `npm run build` sem internet e verificar se a fonte Instrument Sans configurada em `vite.config.js` fica empacotada em `public/build`; se depender de CDN, garantir que a pilha `ui-sans-serif, system-ui` em `resources/css/app.css` mantenha a aparência aceitável offline
- [ ] T145 [P] Atualizar `README.md` com a seção "Demonstração" (comando `demo`, credenciais `recrutador@setic.demo` / `demo1234`, `php artisan demo:curriculos`, `php artisan energia:analisar` e o link para `specs/001-banco-de-talentos-mvp/quickstart.md`)
- [ ] T146 Rodar `vendor/bin/pint --format agent` e corrigir o que for apontado em `app/`, `database/`, `routes/` e `tests/`
- [ ] T147 Rodar `php artisan test` (suítes Unit e Feature) e garantir que todos os testes passam, em especial `CalculadoraAderenciaTest`, `RankingVagaTest`, `TriagemAnonimaTest` e `CurriculoProtegidoTest` (gates da constituição)
- [ ] T148 Executar os cenários A a G de `specs/001-banco-de-talentos-mvp/quickstart.md` a partir de um ambiente limpo (`demo`) e fazer o ensaio cronometrado (≤ 5 min, SC-005) duas vezes: com internet e com o Wi-Fi desligado (SC-007). Registrar os problemas encontrados como novas tarefas neste arquivo

### Validação transversal AJAX — antes do ensaio T148

- [ ] T167 Criar `tests/Feature/AjaxSegurancaTest.php` para contrato transversal nas rotas já implementadas: 401 JSON versus redirect HTML, proteção contra requisição forjada efetivamente habilitada nos testes (não confiar no bypass padrão do ambiente de teste), 419/403 conforme origem/token, 404 JSON e respostas privadas sem cache; preservar validação/anonimização em fragmentos. Usar factories, sem alterar `.env` ou adicionar dependências. Executar também as suítes ampliadas T152/T155/T157/T159/T161/T163 e T165 se US7 incluída. Depende das integrações entregues e T151
- [ ] T168 Completar `tests/js/ajax.test.js` e `tests/js/busca.test.js` com regressões das integrações (delegação após substituir HTML, falha ambígua sem replay, limpeza somente após sucesso e resposta obsoleta descartada); executar `node --test tests/js/*.test.js`. Validar e registrar o resultado do cenário H em `specs/001-banco-de-talentos-mvp/quickstart.md`: foco/teclado, histórico, rede lenta, erros, duplo clique, anonimato após retorno à triagem e fluxo sem JS, além de sem internet com servidor local acessível. Depende de T167 e das histórias incluídas; antes de T148; se não houver navegador disponível, registrar gate manual pendente, não declarar SC-010/SC-011 aprovados

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sem dependências. T001 vem antes de T002/T003; T012 é manual e pode
  acontecer a qualquer momento antes de rodar a aplicação localmente.
- **Foundational (Phase 2)**: depende da Setup. **Bloqueia todas as histórias.** Ordem
  interna: enums (T013–T014) → migrations (T015–T023, nessa ordem) → models (T024–T029) →
  factories e seeders (T030–T033); T034–T043 em paralelo com os models quando possível; T038
   depende de T036–T037; T044 por último.
- **Complemento AJAX das fases concluídas**: T149 → T150 → T151 → T152 → T153 → T154.
  Fazer antes de novas integrações, preservando tarefas já marcadas. IDs T149–T168 foram
  adicionados por revisão, não significam uma Phase 11 nem execução só depois de T148.
- **User Stories (Phases 3–9)**: todas dependem da Foundational. Ordem recomendada:
  P1 (US1 → US2) → P2 (US3 → US4) → P3 (US5, US6) → P4 (US7).
- **Polish (Phase 10)**: depois das histórias desejadas.
- **Integrações AJAX por história**: escrever o teste adicional antes da integração da mesma
  fase: T155 → T156 (US2), T157 → T158 (US3), T159 → T160 (US4), T161 → T162 (US5),
  T163 → T164 (US6), T165 → T166 (US7). Cada par exige T151 e as tarefas originais da
  história indicadas na descrição; US4 também exige T158, US6 exige T154 e US5 pela vaga
  exige T160. T167 → T168 → T148 fecham os gates transversais.

### User Story Dependencies

| História | Depende de | Observação |
|---|---|---|
| US1 (P1) | Foundational | MVP; usa o catálogo e os candidatos semeados |
| US2 (P1) | Foundational | Reaproveita `CandidatoRepositoryInterface` (T050), o catálogo agrupado (T048) e `CandidatoService` (T052); se começar em paralelo à US1, fazer T048–T052 primeiro |
| US3 (P2) | Foundational + T048 (`idsQueAtendem`/catálogo) | Testável só com o seeder; não depende da US2 |
| US4 (P2) | US3 (ranking, `ItemRankingDTO`, quadro) | Transforma `vagas/show` no kanban |
| US5 (P3) | US4 (contexto revelado, `PerfilNaVagaDTO`) | A parte "pelo banco" (T117) só depende da US1 |
| US6 (P3) | US1 (perfil e busca); T069 da US2 (`atualizar`) | Exclusão testável com factories |
| US7 (P4) | US3 (ranking e formulário de vaga); T099 da US4 (`LimpadorTexto`) | Primeiro corte se faltar tempo |

### Within Each User Story

- Testes primeiro (devem falhar) → DTOs/repositories → services → requests → controllers →
  rotas → views → seeders.
- Histórias concluídas não podem quebrar: rode a suíte inteira em cada checkpoint.

### Parallel Opportunities

- Setup: T005–T011 em paralelo, depois de T002–T004.
- Foundational: T013 ∥ T014; T024–T030 em paralelo depois das migrations; T035, T036, T037,
  T039 e T040 em paralelo (frontend) enquanto outra pessoa faz models e seeders.
- US1: T045 ∥ T046 ∥ T048 ∥ T049 ∥ T053 ∥ T059.
- US2: T063 ∥ T064 ∥ T065 ∥ T066 ∥ T070 ∥ T071 ∥ T072 ∥ T076. O extrator (T067) pode
  começar logo após a Foundational, em paralelo com a US1.
- US3: T080 ∥ T081 ∥ T082 ∥ T083 ∥ T085. A `CalculadoraAderencia` (T084) é pura e pode ser
  feita em paralelo desde a Foundational.
- US4: T096 ∥ T097 ∥ T098 ∥ T099 ∥ T100.
- US5 e US6 não disputam `candidatos/show.blade.php`: cada uma edita só o seu *partial*
  `_secao-*` (T061).
- US7: T130–T134 em paralelo.
- Após T151, testes adicionais de histórias distintas podem ser preparados em paralelo nos
  arquivos de cada trilha, respeitando pré-requisitos. Integrações US2/US3 e US3/US6 podem
  correr nas rodadas previstas; não editar `ajax.js`, `app.js` ou layout em paralelo sem sync.

---

## Parallel Example: User Story 1

```bash
# Testes da US1 juntos:
Task: "T045 BuscaCandidatosTest em tests/Feature/BuscaCandidatosTest.php"
Task: "T046 PainelTest em tests/Feature/PainelTest.php"

# Peças independentes da US1 juntas:
Task: "T048 CompetenciaRepositoryInterface em app/Repositories/Interfaces/CompetenciaRepositoryInterface.php e CompetenciaRepository em app/Repositories/CompetenciaRepository.php"
Task: "T049 FiltroCandidatosDTO em app/DTOs/FiltroCandidatosDTO.php"
Task: "T053 PainelService em app/Services/PainelService.php, PainelRepositoryInterface em app/Repositories/Interfaces/ e PainelRepository em app/Repositories/"
Task: "T059 card do candidato em resources/views/candidatos/partials/_card.blade.php"
```

## Parallel Example: User Story 4

```bash
Task: "T096 TriagemAnonimaTest em tests/Feature/TriagemAnonimaTest.php"
Task: "T097 CurriculoProtegidoTest em tests/Feature/CurriculoProtegidoTest.php"
Task: "T098 MovimentacaoEtapaTest em tests/Feature/MovimentacaoEtapaTest.php"
Task: "T099 LimpadorTexto em app/Services/Selecao/LimpadorTexto.php"
Task: "T100 PerfilNaVagaDTO em app/DTOs/PerfilNaVagaDTO.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Fase 1 (Setup) → Fase 2 (Foundational).
2. Fase 3 (US1): banco semeado, painel e busca com filtros; incluir T149–T154 para AJAX.
3. **PARAR E VALIDAR**: cenário A do quickstart. Já demonstra "a parte mais importante" para a
   área cliente.

### Incremental Delivery (alinhado ao cronograma do plan.md)

| Quando | Entrega | Demonstrável |
|---|---|---|
| Sex 30/10 | Fase 1 (concluída) | Infraestrutura e testes de base |
| Sáb 31/10 | R0 + R1: Fase 2, US1 ∥ US2 | Busca + importação de PDF (todo o P1) |
| Dom 01/11 | R2 + R3: US3 ∥ US6, depois US4 ∥ US5/US7 | Ranking + LGPD + kanban anônimo |
| Seg 02/11 | R4: fechamento de US5/US7 + Polish | Roteiro completo do pitch |
| Ter 03/11 | Ensaio final (T148) e apresentação | — |

**Cortes, se faltar tempo (nesta ordem)**: US7 inteira → T111 (arrastar e soltar) →
T144/T145 → refinamentos visuais de T142. Nunca cortar testes de gate, anonimato ou
consentimento.

**Revisão de esforço AJAX**: antecipar T149–T154, depois integrar em cada fase, sem reservar
tudo para o polimento. Adiar drag-and-drop e refinamentos visuais opcionais para acomodar a
melhoria; não cortar AJAX por botão, validação acessível, fallback HTML, segurança ou testes.
Se US7 for cortada, T165–T166 também saem do checkpoint, sem bloquear as Histórias 1–6.

### Parallel Team Strategy

A divisão completa entre as duas pessoas, com rodadas, donos de arquivos e pontos de
sincronização, está em [Trabalho em dupla](#trabalho-em-dupla-duas-trilhas-em-paralelo).
Resumo: trilha A em US1 → US3 → US4; trilha B em design system → US2 → US6 → US5/US7.

---

## Notes

- `[P]` = arquivos diferentes, sem dependência pendente.
- `[USn]` liga a tarefa à história da spec, para rastreabilidade.
- Cada história deve funcionar e ser demonstrável sozinha no seu checkpoint.
- Confirme que os testes falham antes de implementar.
- Faça commit a cada tarefa ou grupo lógico.
- Evite: tarefas vagas, conflitos no mesmo arquivo, dependências cruzadas que quebrem a
  independência das histórias.
- Em caso de dúvida sobre uma regra, a fonte de verdade é, nesta ordem: `CLAUDE.md` (restrição
  do `.env`) → `.specify/memory/constitution.md` → `spec.md` → `contracts/` →
  `data-model.md` → `research.md`.
