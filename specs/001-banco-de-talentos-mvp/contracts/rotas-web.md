# Contrato: Rotas Web

**Feature**: `001-banco-de-talentos-mvp` | Aplicação web renderizada no servidor (HTML).
Todas as respostas são páginas Blade ou redirecionamentos com mensagem *flash*. Não há API JSON
pública no MVP.

## Convenções

- Todas as rotas, exceto `login`, ficam no grupo `auth`. Visitante → redireciona para `/login`.
- Formulários usam `@csrf` e *method spoofing* (`PUT`/`PATCH`/`DELETE`).
- Validação em Form Requests. Em caso de erro, redireciona de volta com `errors` e `old()`.
- Sucesso de escrita → redireciona com `session('status')` em português.
- Rotas do **contexto da vaga** recebem o candidato pelo `codigo` e usam
  `->withoutScopedBindings()` (ver [research R9](../research.md#r9-anonimização-por-contexto)).

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
| GET | `/vagas/{vaga}/candidatos/{candidato:codigo}` | `vagas.candidatos.show` | `ParticipacaoController@show` | — | Perfil no contexto da vaga (`PerfilNaVaga`); anônimo em triagem |
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
