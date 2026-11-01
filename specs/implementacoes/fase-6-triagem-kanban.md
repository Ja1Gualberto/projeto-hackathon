# Phase 6 — US4: Triagem anônima e kanban (T096–T112, T159–T160)

**Feature**: [`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/) · **Data**: 2026-11-01 ·
**Rodadas**: R2/R3, trilhas A e B (a dupla estava offline). Implementada sobre as Phases 2–5.

## Resumo

O quadro de cada vaga agora tem três colunas: Triagem, Entrevista e Aprovado. Todo candidato
elegível entra em Triagem automaticamente, ordenado pela aderência e identificado só pelo
código `CAND-NNNN`. Ao mover para Entrevista, a identidade aparece **apenas nessa vaga**; nas
demais o candidato continua anônimo. Cada movimentação registra quem moveu e quando.

O card e o perfil na vaga (`/vagas/{vaga}/candidatos/{codigo}`) recebem só DTOs:

- **Em triagem**: código, tipo, área, aderência explicada, formação, experiências, projetos,
  competências e "N links — visíveis a partir da entrevista". Os textos livres saem sem
  e-mails, URLs, CPF, telefones e o nome do candidato.
- **Revelado**: também nome, contatos, idade, links, as anotações já registradas (de qualquer
  vaga) e o botão "Abrir PDF".
- **Sempre**: o histórico de movimentações desta vaga e os botões de etapa.

O PDF no contexto da vaga responde **403** em triagem, inclusive por link direto. Vaga encerrada
fica somente para consulta, e candidatos desativados continuam visíveis (esmaecidos, sem ações)
em Entrevista/Aprovado.

Com JavaScript:

- os botões movem sem recarregar, e o servidor devolve a região inteira (`quadro-vaga` ou
  `perfil-na-vaga`) já no estado final;
- enquanto uma ação está pendente, o quadro não aceita outra;
- nada é movido ou revelado antes da resposta;
- o foco vai para o card movido (no quadro) ou para o título (no perfil);
- arrastar um card para outra coluna envia o mesmo formulário dos botões.

Sem JavaScript, os botões funcionam por formulário tradicional (PRG).

## Status da verificação

| Verificação | Resultado |
|---|---|
| Testes escritos antes do código | ✅ 46 testes PHP e 4 JS falhando antes da implementação |
| `php artisan test` | ✅ **222 testes, 1175 asserções** (175 anteriores + 47 novos: limpador 11, triagem anônima 9, PDF protegido 8, movimentação 19) |
| Testes JavaScript (`node --test`) | ✅ **42 testes**, sendo 4 novos em `tests/js/kanban.test.js` (botão, bloqueio, falha e arrastar e soltar) |
| Pint (`--format agent`) | ✅ Sem ajustes |
| Build Vite (container isolado, sem `.env`) | ✅ `app.js` 14,3 kB; variantes `aria-busy:` e `data-alvo-soltar:` geradas |
| Teste de fumaça HTTP, cenário D (container sem `.env`) | ✅ Ver detalhes abaixo |
| Revisão no navegador (D/H: arrastar, teclado, foco, rede lenta, sem JS, aba Network) | **Pendente** (ambiente sem navegador) |

Detalhes do teste de fumaça, com o banco semeado, na vaga "Analista de Dados":

- Triagem com 22 cards, sem e-mails nem links `/candidatos/N`; Bruno Peixoto e Renata Barros
  já em Entrevista (seeder);
- perfil de um card em triagem: "Identidade protegida", sem "Abrir PDF"; PDF direto → **403**;
- mover os 3 primeiros em JSON → "Rafael Monteiro está agora em Entrevista." etc.; o perfil
  mostra o nome e "Movido para Entrevista por Recrutadora Demo em …"; o PDF passa a abrir (200);
- voltar um deles à triagem pelo perfil em JSON → "CAND-3009 está agora em Triagem.", sem o nome
  no envelope; o PDF volta a 403;
- encerrar a vaga → mover responde 422 com a mensagem de somente consulta, e o quadro fica sem botões;
- quadro em 0,27 s; nenhum erro no log.

A revelação apenas na própria vaga foi verificada nos testes de feature (vaga B continua anônima
na página, no perfil e no JSON). No fumaça, os candidatos movidos eram estagiários e não
aparecem na vaga de suporte (só servidores).

## O que foi entregue

| Tarefa | Entrega |
|---|---|
| T096, T159 | `tests/Feature/TriagemAnonimaTest.php`: invariantes 1, 3, 4, 6, 9 e 10 no HTML e no JSON decodificado, limpeza dos textos, anotações só reveladas, 404 para quem não participa e a identidade de outro candidato revelado preservada no mesmo quadro |
| T097 | `tests/Feature/CurriculoProtegidoTest.php`: 403 (HTML e JSON), 200 em Entrevista/Aprovado, volta à triagem, outra vaga, arquivo ausente, código inexistente e visitante |
| T098, T159 | `tests/Feature/MovimentacaoEtapaTest.php`: participação e histórico, mesma etapa, etapa por vaga, bloqueios, colunas e ações, desativado esmaecido, vaga encerrada, histórico no perfil, JSON por `contexto`, 422 sem movimentar, visitante e seeder |
| T099 | `app/Services/Selecao/LimpadorTexto.php` e `tests/Unit/LimpadorTextoTest.php` |
| T100 | `app/DTOs/PerfilNaVagaDTO.php` (com `podeMover` e `desativado` além dos campos da tarefa) |
| T101 | `ParticipacaoRepositoryInterface` e `ParticipacaoRepository` (com `candidatosRevelados`), vinculados no `RepositoryServiceProvider` |
| T102 | `app/Services/Selecao/VisibilidadeCandidatoService.php` (`etapaNaVaga`, `podeRevelar`, `elegivel`, `participaDoQuadro`, `perfilNaVaga`) |
| T103 | `app/Services/Selecao/SelecaoService.php` (`quadro`, `moverEtapa`, `mensagemDeMovimentacao`) e `RankingVagaService::classificar` |
| T104, T160 | `app/Http/Requests/MoverEtapaRequest.php` (`etapa` e `contexto` em `quadro,perfil`) |
| T105–T107 | `ParticipacaoController`, `ParticipacaoEtapaController` (HTML e JSON) e `ParticipacaoCurriculoController` |
| T108 | 3 rotas no bloco `// US4 — seleção` de `routes/vagas.php`, com `withoutScopedBindings()` e código restrito a `CAND-NNNN` |
| T109 | `vagas/partials/_quadro` (3 colunas), `vagas/partials/_coluna` e `vagas/partials/_card-ranking` (nome só se revelado, link pelo código, botões, `draggable`, selo "Desativado") |
| T110, T160 | `participacoes/show` e a região `participacoes/partials/_perfil` |
| T111, T160 | `resources/js/modulos/kanban.js`: movimentação por botão com o transporte comum, bloqueio do quadro, foco no card movido e arrastar e soltar |
| T112 | `database/seeders/VagaSeeder.php`: Bruno Peixoto (S3) e Renata Barros (S9) em Entrevista na "Analista de Dados", via `moverPara`, idempotente |

## Decisões e desvios

| Item | Decisão | Por quê |
|---|---|---|
| Mensagem de sucesso | "{nome ou código} está agora em {Etapa}." em vez de "… movido para …" | Neutra em gênero e verdadeira também quando o candidato já estava na etapa |
| Mesma etapa | Não grava movimentação (o repository ignora) | Clique duplo ou reenvio não poluem o histórico; o seeder fica idempotente |
| Quem pode ser visto na vaga | Elegíveis e quem já está em entrevista/aprovado nesta vaga; os demais → 404 | Um servidor não deve ter perfil na vaga de estágio só porque alguém digitou o código |
| Retorno sem JavaScript | `contexto=perfil` volta ao perfil; sem `contexto`, ao quadro (padrão do contrato) | Quem moveu pelo perfil continua nele |
| Título da aba do perfil | Sempre "Candidato na vaga" | O nome ficaria no `<title>` depois de uma volta à triagem por AJAX |
| Anotações no perfil revelado | Lista somente leitura das anotações existentes | O DTO já as traz; o registro (formulário) é da US5, T120 |
| Botões da movimentação | Um formulário por card com botões `name="etapa"`; o JS envia esse formulário com a etapa da coluna ao soltar | Um único caminho de persistência para botão, sem JS e arrastar e soltar |
| Bloqueio e foco | `kanban.js` trata `form[data-mover-etapa]` (não `data-ajax`) para bloquear o quadro inteiro e focar o card movido | `formularios.js` só bloqueia o próprio formulário |
| Limpeza de telefones | Exige DDD (ou celular `9XXXX-XXXX`) | Evita apagar períodos como "2022-2023" |
| Domínios sem protocolo | Só com caminho e TLD em minúsculas (`github.com/usuario`) | Evita apagar "ASP.NET" e "Node.js" |
| Ranking das colunas | `RankingVagaService::classificar` ordena elegíveis e revelados juntos; `ranquear` continua igual | Mantém a mesma ordem em todas as colunas e os testes da Phase 5 |

## Como verificar

**Com Docker**:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\hackaton.ps1 demo
```

Depois, em http://localhost:8000 → **Vagas** → "Analista de Dados":

1. Na Triagem, os cards mostram só o código; Bruno Peixoto e Renata Barros já estão em Entrevista.
2. Abra um card em triagem: "Identidade protegida na triagem anônima", sem PDF. Acesse
   `…/curriculo` direto: página 403.
3. Mova três candidatos para Entrevista (botão ou arrastando o card): os nomes aparecem, o
   histórico mostra quem moveu e quando, e o PDF abre.
4. Abra outra vaga em que um deles seja elegível: ele continua anônimo lá.
5. Volte um deles para Triagem: fica anônimo de novo. Encerre a vaga: o quadro fica somente consulta.

**Testes**: `.\hackaton.ps1 artisan test`. Testes JS: `node --test tests/js/*.test.js`
(Node 22).
