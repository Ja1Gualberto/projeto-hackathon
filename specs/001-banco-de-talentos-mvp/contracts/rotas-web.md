# Contrato: Rotas Web

**Feature**: `001-banco-de-talentos-mvp` | Aplicação web renderizada no servidor (HTML).
Navegação tradicional recebe páginas Blade ou redirecionamentos com mensagem *flash*.
Conforme revisão de 2026-10-31, as rotas indicadas abaixo também respondem JSON interno para
AJAX progressivo, com HTML renderizado pelo Blade. Não há API JSON pública no MVP.

## Convenções

- Todas as rotas, exceto `login`, ficam no grupo `auth`. Visitante → redireciona para `/login`.
- Formulários usam `@csrf` e *method spoofing* (`PUT`/`PATCH`/`DELETE`).
- Validação em Form Requests. Em caso de erro, redireciona de volta com `errors` e `old()`.
- Sucesso de escrita → redireciona com `session('status')` em português.
- As duas convenções acima descrevem o modo tradicional; com `Accept: application/json`,
  aplicar o contrato AJAX abaixo, sem redirecionar erros de validação.
- Rotas do **contexto da vaga** recebem o candidato pelo `codigo` e usam
  `->withoutScopedBindings()` (ver [research R9](../research.md#r9-anonimização-por-contexto)).

## Contrato AJAX progressivo (FR-038–FR-042)

### Transporte, negociação e segurança

- Reutilizar as rotas e os métodos desta documentação, sob `web` e `auth`; não criar rotas
  paralelas de API. `fetch` usa credenciais de mesma origem e `Accept: application/json`.
  Formulários enviam `FormData` com `_token` e `_method` quando aplicável; não definir
  manualmente `Content-Type` multipart. GET usa parâmetros na query, sem corpo.
- Sucesso retorna **200** com `{message: string|null, fragments: object, redirect: string|null}`.
  `fragments` mapeia identificadores estáveis da tabela abaixo para HTML Blade autorizado;
  objeto vazio quando houver apenas navegação. `redirect`, quando preenchido, é URL de mesma
  origem gerada por rota nomeada; o cliente verifica a origem antes de navegar. Navegação
  tradicional conserva os resultados e redirecionamentos descritos nas tabelas de rotas.
- Fragmentos são definidos pelo servidor e mapeados no cliente para regiões conhecidas;
  o request não pode escolher uma view, caminho ou seletor arbitrário. Texto do usuário continua
  escapado pelo Blade; mensagens são inseridas como texto, sem executar scripts retornados.
- JSON e páginas com dados pessoais usam `Cache-Control: private, no-store`; sem persistir
  respostas em localStorage ou cache de aplicação. A escolha de formato não modifica autorização
  nem regras de anonimização. Usar os mesmos DTOs de contexto das páginas completas.

### Respostas de erro

| Status AJAX | Significado e comportamento do cliente |
|---|---|
| 422 | `{message, errors: {campo: string[]}}`; exibir junto aos campos e resumo acessível; manter valores e focar o primeiro erro |
| 401 | Sessão ausente/expirada; avisar e oferecer login, sem navegar automaticamente perdendo o formulário |
| 419 | Token/sessão inválido; pedir atualização consciente da página; não reenviar a escrita |
| 403 | Acesso negado ou origem recusada; mensagem amigável, sem dados protegidos |
| 404 | Recurso inexistente/importação expirada; oferecer retorno ou novo envio, preservando o preenchimento quando possível |
| 413 | PDF acima do limite de transporte; mensagem em português mesmo se a resposta não for JSON |
| 503 | IA indisponível; `{message: "Análise indisponível no momento."}`; manter perfil anterior e permitir ajuste manual |
| 5xx, rede ou resposta inesperada | Preservar formulário e estado confirmado, liberar controles e informar falha; nunca inserir HTML de erro/login como fragmento |

Erros HTTP controlados retornam JSON com `message`, sem stack trace ou dados pessoais.
Respostas fora do contrato são tratadas como falha, mesmo que o HTTP seja 200. Não repetir
automaticamente POST/PUT/PATCH/DELETE: em falha ambígua, oferecer GET para conferir o estado
antes de nova tentativa. Cancelamento de `fetch` não significa cancelamento da operação no servidor.

### Operações e regiões por fase

| Phase / história | Rotas em modo AJAX | Regiões de sucesso / navegação |
|---|---|---|
| 3 / US1 | `GET candidatos.index` com os mesmos filtros e `page` | `resultados-candidatos`: contagem, cards, paginação e vazio; formulário de filtros fica fora da região |
| 4 / US2 | `POST importacoes.store`, `POST importacoes.confirmar` | `redirect` para revisão ou perfil; erro permanece na tela atual |
| 4 / US2 | `POST competencias.store` | `catalogo-competencias`: incluir nova opção sem apagar campos ou seleções da revisão; sem formulário aninhado |
| 5 / US3 | `POST vagas.store`, `PUT vagas.update` | `redirect` para `vagas.show`; validação permanece no formulário |
| 5 / US3 | `GET vagas.show`, `PATCH vagas.status` | `quadro-vaga`: cabeçalho/status, ações e ranking; na Phase 6 inclui as três colunas e contagens; atualização por ação explícita, sem polling |
| 6 / US4 | `PATCH vagas.candidatos.etapa` | `quadro-vaga` quando enviado pelo quadro; `perfil-na-vaga` quando enviado pelo perfil, incluindo identidade, ações e histórico |
| 7 / US5 | `POST candidatos.anotacoes.store`, `POST vagas.candidatos.anotacoes.store` | `historico-anotacoes` e `competencias-candidato`; limpar o formulário só após sucesso, manter contexto autorizado |
| 8 / US6 | `PUT candidatos.update`, `DELETE candidatos.destroy` | `redirect` para perfil ou listagem; exclusão exige motivo e confirmação; nunca repetir automaticamente |
| 8 / US6 | `PATCH candidatos.status` | `situacao-candidato` e `secao-lgpd`; filtros de situação/retenção reutilizam `resultados-candidatos` |
| 9 / US7 | `POST candidatos.energia.analisar`, `PUT candidatos.energia.update` | `secao-energia` no perfil do banco; os selos no quadro são atualizados na próxima leitura de `vagas.show` |

Para a movimentação, o formulário inclui `contexto=quadro` ou `contexto=perfil`, validado por
`MoverEtapaRequest`; o padrão sem JavaScript continua voltando para `vagas.show`. O parâmetro
só seleciona uma das duas apresentações autorizadas **da mesma vaga**, nunca muda permissões.
Botão e drag-and-drop usam o mesmo envio. Só aplicar o HTML após confirmação do servidor;
ao voltar à triagem, não manter nome, contato, links ou anotações em DOM oculto ou mensagens.

### Comportamento da interface

- Busca: debounce de **400 ms** em texto; selects/checkboxes imediatos; remover `page` ao mudar
  filtros. Invalidar a consulta anterior desde a edição, abortar GET anterior e ignorar respostas
  obsoletas. Preservar foco, cursor e campos; atualizar somente `resultados-candidatos`.
- Confirmar URL e resultado juntos. Alteração de filtros usa `replaceState`, paginação usa
  `pushState`; `popstate` sincroniza formulário e resultados sem criar nova entrada. Abrir ou
  recarregar a URL funciona pelo GET tradicional. Falha na busca mantém a última URL confirmada.
- Usar `aria-busy` e uma região de anúncio `aria-live` fora dos fragmentos. Formulários pendentes
  bloqueiam novas submissões e mostram “Processando…”, preservando os valores enviados (criar
  `FormData` antes de desabilitar controles). Restaurar controles em sucesso ou erro.
- Com AJAX indisponível antes de qualquer envio, manter links/formulários tradicionais. Falha
  durante uma escrita não aciona fallback automático. Preservar diálogos de confirmação e
  comportamentos de teclado, com delegação de eventos após atualizações parciais.

## Autenticação

| Método | URI | Nome | Controller | Request | Resultado |
|---|---|---|---|---|---|
| GET | `/login` | `login` | `Auth\LoginController@create` | — | Tela de login (layout visitante) |
| POST | `/login` | `login.store` | `Auth\LoginController@store` | `LoginRequest` | Sucesso → `painel`; falha → volta com "E-mail ou senha inválidos." `throttle:5,1` |
| POST | `/logout` | `logout` | `Auth\LoginController@destroy` | — | Encerra a sessão → `login` |

## Painel (US1)

| Método | URI | Nome | Controller | Resultado |
|---|---|---|---|---|
| GET | `/` | `painel` | `PainelController` (invokable) | Totais, servidores × estagiários, vagas abertas, top 8 competências, retenção pendente (FR-005) |

## Gestão do banco de talentos (US1, US2, US5, US6)

| Método | URI | Nome | Controller | Request | Resultado |
|---|---|---|---|---|---|
| GET | `/candidatos` | `candidatos.index` | `CandidatoController@index` | `BuscarCandidatosRequest` | Cards + contagem; filtros abaixo |
| GET | `/candidatos/importar` | `importacoes.create` | `ImportacaoCurriculoController@create` | — | Formulário de envio do PDF + tipo |
| POST | `/candidatos/importar` | `importacoes.store` | `ImportacaoCurriculoController@store` | `ImportarCurriculoRequest` | Extrai → redireciona para a revisão |
| GET | `/candidatos/importar/{importacao}` | `importacoes.show` | `ImportacaoCurriculoController@show` | — | Revisão pré-preenchida; alerta de duplicidade; aviso se sem texto. `{importacao}` = UUID da sessão; inexistente → 404 |
| POST | `/candidatos/importar/{importacao}/confirmar` | `importacoes.confirmar` | `ConfirmacaoImportacaoController@store` | `ConfirmarImportacaoRequest` | Cria ou atualiza o candidato → `candidatos.show` |
| GET | `/candidatos/{candidato}` | `candidatos.show` | `CandidatoController@show` | — | Perfil completo, consentimento, retenção, anotações, PDF, ações |
| GET | `/candidatos/{candidato}/editar` | `candidatos.edit` | `CandidatoController@edit` | — | Formulário de correção (FR-029) |
| PUT | `/candidatos/{candidato}` | `candidatos.update` | `CandidatoController@update` | `AtualizarCandidatoRequest` | Salva e renova `dados_atualizados_em` |
| PATCH | `/candidatos/{candidato}/status` | `candidatos.status` | `CandidatoStatusController@update` | `AlterarStatusCandidatoRequest` | Ativa/desativa (FR-031) |
| DELETE | `/candidatos/{candidato}` | `candidatos.destroy` | `CandidatoController@destroy` | `ExcluirCandidatoRequest` | Exclusão definitiva → `candidatos.index` (FR-030) |
| GET | `/candidatos/{candidato}/curriculo` | `candidatos.curriculo` | `CurriculoController@show` | — | PDF `inline`, `no-store`; sem arquivo → 404 |
| POST | `/candidatos/{candidato}/anotacoes` | `candidatos.anotacoes.store` | `AnotacaoController@store` | `RegistrarAnotacaoRequest` | Anotação com vaga opcional |
| POST | `/candidatos/{candidato}/energia` | `candidatos.energia.analisar` | `PerfilEnergiaController@store` | — | Roda a IA; indisponível → volta com "Análise indisponível no momento." |
| PUT | `/candidatos/{candidato}/energia` | `candidatos.energia.update` | `PerfilEnergiaController@update` | `AjustarPerfilEnergiaRequest` | Ajuste manual (origem `humano`) |
| POST | `/competencias` | `competencias.store` | `CompetenciaController@store` | `CriarCompetenciaRequest` | Inclui sugestão no catálogo → volta para a revisão |

### Parâmetros de busca (`GET /candidatos`)

| Parâmetro | Tipo | Semântica |
|---|---|---|
| `competencias[]` | ids | **E** entre as escolhidas (FR-003); idiomas por grupo/nível ≥ |
| `tipo` | `servidor`/`estagiario` | igualdade |
| `area` | `Area` | igualdade |
| `experiencia` | `1` | só "experiência na área: sim" |
| `busca` | string ≤ 100 | nome ou e-mail (só na gestão do banco — FR-025) |
| `situacao` | `ativos` (padrão), `desativados`, `retencao` | status ou retenção vencida |
| `page` | int | 12 cards por página, `withQueryString()` |

Resposta: "N talentos encontrados", cards (nome, `badge-tipo`, área, experiência, até 5 tags,
selo de retenção) e estado vazio com "Remover filtros" quando não houver resultado.

## Vagas e seleção (US3, US4, US5, US7)

| Método | URI | Nome | Controller | Request | Resultado |
|---|---|---|---|---|---|
| GET | `/vagas` | `vagas.index` | `VagaController@index` | — | Lista com status, tipo e contagem por etapa |
| GET | `/vagas/criar` | `vagas.create` | `VagaController@create` | — | Formulário: dados, competências hard/soft com peso 1–3 e energia opcional |
| POST | `/vagas` | `vagas.store` | `VagaController@store` | `SalvarVagaRequest` | → `vagas.show` |
| GET | `/vagas/{vaga}` | `vagas.show` | `VagaController@show` | — | Quadro: Triagem (ranking anônimo), Entrevista e Aprovado |
| GET | `/vagas/{vaga}/editar` | `vagas.edit` | `VagaController@edit` | — | Edição; o ranking é recalculado na próxima leitura (FR-020) |
| PUT | `/vagas/{vaga}` | `vagas.update` | `VagaController@update` | `SalvarVagaRequest` | → `vagas.show` |
| PATCH | `/vagas/{vaga}/status` | `vagas.status` | `VagaStatusController@update` | — | Encerra a vaga |
| GET | `/vagas/{vaga}/candidatos/{candidato:codigo}` | `vagas.candidatos.show` | `ParticipacaoController@show` | — | Perfil no contexto da vaga (`PerfilNaVagaDTO`); anônimo em triagem |
| PATCH | `/vagas/{vaga}/candidatos/{candidato:codigo}/etapa` | `vagas.candidatos.etapa` | `ParticipacaoEtapaController@update` | `MoverEtapaRequest` | Move e registra a movimentação; vaga encerrada / desativado / inelegível → volta com erro |
| GET | `/vagas/{vaga}/candidatos/{candidato:codigo}/curriculo` | `vagas.candidatos.curriculo` | `ParticipacaoCurriculoController@show` | — | **403 em triagem**; PDF em entrevista/aprovado |
| POST | `/vagas/{vaga}/candidatos/{candidato:codigo}/anotacoes` | `vagas.candidatos.anotacoes.store` | `ParticipacaoAnotacaoController@store` | `RegistrarAnotacaoRequest` | **403 em triagem**; vaga associada automaticamente |

### Conteúdo do card de ranking (Triagem)

`codigo`, `badge-tipo`, área, `barra-score` com percentual, explicação textual, tags de
competências atendidas (com origem) e não identificadas (com peso), indicação de experiência
na área e, se houver, proximidade de energia como selo separado. Botão "Mover para Entrevista".
**Nunca** contém nome, contato, idade, links, PDF ou link para `/candidatos/{id}`.

## Códigos de resposta relevantes

| Situação | Resposta |
|---|---|
| Visitante em rota protegida | 302 → `/login` |
| PDF ou anotação no contexto da vaga com candidato em triagem | 403 com página amigável ("Identidade protegida na triagem anônima") |
| Código de candidato inexistente | 404 |
| Arquivo acima do `post_max_size` | 302 de volta com "O arquivo excede o limite de 10 MB." |
| Importação expirada (UUID fora da sessão) | 404 com link "Enviar novamente" |
