# Phase 2 — Fundação (T013–T044)

**Feature**: [`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/) · **Data**: 2026-10-31 ·
**Rodada**: R0 (trilhas A e B feitas por uma pessoa só)

## Resumo

A fundação que todas as histórias usam está implementada:

- esquema completo do banco (9 migrations, 11 tabelas);
- 12 enums de domínio, 8 models e 4 factories;
- catálogo com 46 competências e o recrutador de demonstração;
- gerador de PDF próprio para seeders e testes;
- login com sessão;
- design system: tokens Tailwind, 13 componentes Blade, 2 layouts e páginas de erro;
- JavaScript em módulos;
- rotas separadas por módulo.

As alterações estruturais do trabalho em dupla (rotas, seeders e JavaScript por módulo) já estão
aplicadas, então a R1 pode começar em paralelo.

## Status da verificação

Verificado em 2026-10-31, depois da instalação do Docker Desktop 4.97 (engine 29.9.2) e do build
da imagem `projeto-hackathon-app` (PHP 8.5.12).

| Verificação | Resultado |
|---|---|
| `php artisan test` | ✅ **28 testes, 105 asserções** (Infraestrutura 4, Autenticação 8, Fundação 11, Enums 5) |
| Pint (`--format agent`) | ✅ Só removeu o `use Route` sem uso de `routes/candidatos.php` e `routes/vagas.php` (correção aplicada) |
| `php artisan migrate:fresh --seed` | ✅ 9 migrations e 4 seeders em SQLite |
| `npm run build` (no build da imagem) | ✅ CSS, JS e fonte Instrument Sans empacotados (funciona offline) |
| Teste de fumaça HTTP (container descartável) | ✅ `/` redireciona para o login; senha errada mostra o erro; login correto abre o painel; 404 amigável; CSS servido |
| Revisão visual no navegador | Pendente (usuário) |

**Correção feita na verificação**: o primeiro `php artisan test` falhou em 19 testes com
`Class "Mockery" not found`. O `RefreshDatabase` usa `$this->artisan()`, que depende do
`mockery/mockery`, um pacote que o esqueleto padrão do Laravel traz e este projeto não tinha.
Ele foi adicionado como dependência de desenvolvimento (`^1.6`, que resolveu para 1.6.15, mais
`hamcrest/hamcrest-php`), sem alterar nenhum outro pacote do `composer.lock`.

## O que foi entregue

### Trilha A — banco e backend base

| Tarefa | Entrega |
|---|---|
| T013–T014 | 12 enums em `app/Enums/` com `rotulo()`. Extras úteis às histórias: `TipoCandidato::cor()` e `icone()`, `OrigemCompetencia::rotuloCurto()` e `prioridade()`, `TipoCandidatoVaga::aceita()`, `EtapaSelecao::revelaIdentidade()` |
| T015–T023 | 9 migrations com `2026_10_31_00000N_*`, exatamente como em [data-model.md](../001-banco-de-talentos-mvp/data-model.md): chaves estrangeiras, *cascades*, índices e unicidade |
| T024–T029 | Models `Competencia`, `Candidato`, `Vaga`, `Participacao`, `MovimentacaoEtapa`, `Anotacao`, `PerfilEnergia` e `RegistroExclusao`, com `#[Fillable]`/`#[Hidden]` (mesma convenção do `User`), casts de enums, relações tipadas e PHPDoc genérico |
| T030 | Factories de `Competencia`, `Candidato`, `Vaga` e `Anotacao`, com os estados pedidos |
| T031 | `Database\Seeders\Support\GeradorPdfCurriculo`: PDF 1.4 com Helvetica/WinAnsi, quebra a cada 48 linhas, `xref` correta e `gerarSemTexto()` |
| T032 | `CompetenciaSeeder` idempotente (`updateOrCreate`) com 33 hard skills (com área), 4 idiomas (grupo/nível) e 9 soft skills, além dos sinônimos normalizados |
| T033 | `UsuarioSeeder` (`recrutador@setic.demo` / `demo1234`), `CandidatoSeeder` e `VagaSeeder` como *stubs*, e `DatabaseSeeder` na ordem final |
| T034 | `bootstrap/app.php` com `redirectGuestsTo(login)` e `redirectUsersTo(painel)` |
| T041 | `LoginRequest` + `Auth\LoginController` (`create`/`store`/`destroy`), com a opção "Manter conectado" |
| T043 | `routes/web.php` (guest/auth, `throttle:5,1`, painel provisório) incluindo `routes/candidatos.php` e `routes/vagas.php` |
| T044 | `tests/Feature/AutenticacaoTest.php` (8 cenários) |

### Trilha B — interface base

| Tarefa | Entrega |
|---|---|
| T035 | Tokens no `@theme` de `resources/css/app.css`: `primaria`, `servidor` e `estagiario` (escalas 50–900), `score-alto`/`medio`/`baixo`, `superficie`, `fundo`, `borda`, `texto`, `texto-suave` e `radius-card`; fonte base de 17px; foco visível; fundo do `<dialog>` |
| T036 | Componentes `botao`, `card`, `badge`, `badge-tipo`, `tag-competencia` e `icone` (17 ícones) |
| T037 | Componentes `campo`, `modal`, `alerta`, `estado-vazio`, `cartao-estatistica`, `cabecalho-pagina` e `barra-score` |
| T038 | `layouts/app.blade.php` (barra lateral, link para pular ao conteúdo, usuário, botão Sair, mensagem `status`) e `layouts/visitante.blade.php` |
| T039 | `errors/403` ("Identidade protegida na triagem anônima") e `errors/404`, mais `errors/413` (ver desvios) |
| T040 | `resources/js/app.js` como ponto de entrada; `modulos/formularios.js` (autoenvio, modais, confirmação); `chips.js` e `kanban.js` como placeholders |
| T042 | `auth/login.blade.php` e `painel/index.blade.php` provisória |

### Extras (fora do texto das tarefas, necessários para a fundação funcionar)

- `tests/TestCase.php` chama `withoutVite()`, porque as views usam `@vite` e os testes não
  dependem de assets compilados.
- `tests/Feature/FundacaoTest.php` (11 cenários): código `CAND-NNNN`, retenção, elegibilidade,
  campos ocultos na serialização, exclusão em cascata, seeders (inclusive idempotência), gerador
  de PDF lido pelo `smalot/pdfparser`, página 404 e badge de tipo.
- `tests/Unit/EnumsDominioTest.php` (5 cenários) com as regras dos enums.
- `@fonts` nos dois layouts, para carregar a Instrument Sans configurada no `vite.config.js`. A
  view `welcome` usava essa diretiva.
- `@source '../../app/Enums/*.php'` no CSS, para o Tailwind gerar as classes que
  `TipoCandidato::cor()` devolve.
- Remoção de `resources/views/welcome.blade.php`, que ficou sem rota.

## Decisões e desvios

| Item | O que mudou | Por quê |
|---|---|---|
| T025 — escopo de retenção | Chama-se `comRetencaoVencida()`, não `retencaoVencida()` | `retencaoVencida` já é o acessor (`$candidato->retencao_vencida`); PHP não permite dois métodos com o mesmo nome |
| T034 — upload acima do limite | `PostTooLargeException` mostra `errors/413.blade.php`; não há redirecionamento com `withErrors` | A exceção é lançada pelo middleware global `ValidatePostSize`, antes de `StartSession`. A mensagem flash nunca seria salva. Arquivos entre 10 MB e o `post_max_size` continuam sendo barrados pela validação `max:10240` com mensagem no formulário |
| `DatabaseSeeder` | Saiu o trait `WithoutModelEvents` | Ele desligava os eventos de model, e o código `CAND-NNNN` e o slug das competências são gerados no `creating` |
| `Competencia::gerarSlug()` | `C#` vira `c-sharp` | `Str::slug('C#')` daria só `c` |
| `x-campo` | Prop extra `vazio` (texto da opção vazia do select) | Os selects de filtro e formulário precisam de "Todos" ou "Selecione" |
| Código `CAND-NNNN` | Laço até encontrar um código livre | Simples e suficiente para o MVP (10 mil códigos) |

## Referência rápida para a R1

**Models e escopos**

- `Candidato::ativos()`, `Candidato::elegiveisPara($vaga)` e `Candidato::comRetencaoVencida()`.
- Acessores do candidato: `idade`, `retencao_ate` e `retencao_vencida`.
- Relações do candidato:
  - `competencias()` (pivot `origem`);
  - `participacoes()`;
  - `anotacoes()`;
  - `perfilEnergia()`;
  - `consentimentoRegistradoPor()`.
- `Vaga::competencias()` (pivot `peso`), `estaAberta()` e `temPadraoEnergia()`.
- `Competencia::ehIdioma()`.
- `Anotacao::competenciasObservadas()`.

**Componentes**

| Componente | Uso |
|---|---|
| `<x-botao>` | `variante` (`primario`/`secundario`/`perigo`/`fantasma`), `tamanho` (`sm`/`md`/`lg`), `href` opcional, `type` |
| `<x-campo>` | `nome`, `rotulo`, `tipo` (`text`/`email`/`password`/`date`/`select`/`textarea`/`file`…), `valor`, `opcoes` (`valor => rótulo`), `vazio`, `ajuda`, `obrigatorio`; lê `old()` e mostra o erro |
| `<x-badge-tipo :tipo>` | Enum ou string de `TipoCandidato` |
| `<x-tag-competencia>` | `nome`, `origem` (enum ou string), `faltante`, `peso` |
| `<x-barra-score :percentual>` | Faixas ≥ 70, 40–69 e < 40 |
| `<x-modal id titulo>` | Slot `rodape`; abre com `data-abrir-modal="id"` |
| `<x-alerta tipo titulo>` | `info`/`sucesso`/`aviso`/`erro` |
| `<x-estado-vazio icone titulo texto>` | Slot de ações |
| `<x-cartao-estatistica rotulo valor detalhe icone>` | — |
| `<x-cabecalho-pagina titulo subtitulo>` | Slot de ações |
| `<x-icone nome>` | Nomes: alerta, busca, cadeado, check, documento, erro, formacao, grupo, info, painel, sair, seta, sucesso, upload, usuario, vaga, x |
| `<x-card>` | — |
| `<x-badge cor>` | `neutro`/`primaria`/`sucesso`/`aviso`/`perigo` |

**Views**: `@extends('layouts.app')` com `@section('titulo', '...')` e `@section('conteudo')`.

**JavaScript**:

- `form[data-autosubmit]`: envia o formulário ao alterar um campo. Para um campo que não deve
  disparar o envio, como o filtro de chips, envolva-o em `[data-sem-autoenvio]`.
- `form[data-confirmar="..."]`: pede confirmação antes de enviar.

## Arquivos

**Criados (68)**:

- `app/Enums/` (12)
- `app/Models/` (8)
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Requests/LoginRequest.php`
- `database/migrations/2026_10_31_*` (9)
- `database/factories/` (4)
- `database/seeders/` (4 + `Support/GeradorPdfCurriculo.php`)
- `resources/views/components/` (13)
- `resources/views/layouts/` (2)
- `resources/views/errors/` (3)
- `resources/views/auth/login.blade.php`
- `resources/views/painel/index.blade.php`
- `resources/js/modulos/` (3)
- `routes/candidatos.php`, `routes/vagas.php`
- `tests/Feature/AutenticacaoTest.php`, `tests/Feature/FundacaoTest.php`,
  `tests/Unit/EnumsDominioTest.php`

**Alterados (9)**: `bootstrap/app.php`, `composer.json` e `composer.lock` (+ `mockery/mockery`
dev), `database/seeders/DatabaseSeeder.php`, `resources/css/app.css`, `resources/js/app.js`,
`routes/web.php`, `tests/TestCase.php` e `specs/001-banco-de-talentos-mvp/tasks.md` (tarefas
marcadas `[X]` e notas).

**Removido (1)**: `resources/views/welcome.blade.php`.

## Como verificar

**Com Docker** (o código é copiado para a imagem, então reconstrua antes):

```powershell
.\hackaton.ps1 dev
.\hackaton.ps1 artisan test
.\hackaton.ps1 check
.\hackaton.ps1 artisan migrate:fresh --seed --force --no-interaction
```

O Pint rodado no container não altera os arquivos do host. Se o `check` apontar ajustes, rode o
Pint no host (`vendor/bin/pint --dirty --format agent`) ou aplique as correções à mão.

**Sem Docker** (PHP 8.5 + Composer no host):

```bash
composer install
php artisan test
vendor/bin/pint --dirty --format agent
php artisan migrate:fresh --seed
npm install --ignore-scripts && npm run build
php artisan serve
```

**Esperado**:

1. Todos os testes passam: `InfraestruturaTest`, `AutenticacaoTest`, `FundacaoTest` e `EnumsDominioTest`.
2. O seed cria 1 usuário e 46 competências.
3. Em `http://localhost:8000`, a raiz redireciona para o login.
4. Entrar com `recrutador@setic.demo` / `demo1234` mostra o layout com a barra lateral e o painel
   provisório.
5. Os itens Candidatos, Importar currículo e Vagas aparecem desabilitados até a R1/R2.

## Pontos confirmados na verificação

- **`@fonts` nos testes**: funciona com `withoutVite()`. As views renderizam nos testes.
- **Acentos no PDF gerado**: o `smalot/pdfparser` decodifica WinAnsi ("Análise" é lido
  corretamente), então o extrator da US2 pode confiar no texto lido.
- **Chaves estrangeiras no SQLite**: o *cascade* da exclusão funciona com a configuração padrão.
- **Fonte offline**: o build empacota a Instrument Sans em `public/build`, sem depender de CDN
  (adianta a verificação de T144).

## Próximos passos

Rodada **R1**:

- **Trilha A**: US1 (T045–T062), começando por T048–T051.
- **Trilha B**: US2 (T063–T079).

Os comandos prontos para a IA estão em
[tasks.md → Trabalho em dupla](../001-banco-de-talentos-mvp/tasks.md#trabalho-em-dupla-duas-trilhas-em-paralelo).
