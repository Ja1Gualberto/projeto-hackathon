# Phase 5 — US3: Vagas com pesos e ranking explicado (T080–T095, T157–T158)

**Feature**: [`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/) · **Data**: 2026-11-01 ·
**Rodada**: R2, trilha A. Implementada sobre as Phases 2–4 já entregues.

## Resumo

O recrutador cadastra uma vaga com título, área, tipo de candidato aceito (servidor, estagiário
ou ambos), descrição e competências do catálogo, cada uma com peso 1 (desejável), 2 (importante)
ou 3 (essencial). Ao salvar, abre o quadro da vaga. Nele, a coluna Triagem lista os candidatos
elegíveis (ativos e do tipo aceito), ordenados pela aderência.

Cada card mostra:

- código anônimo, tipo e área;
- barra com o percentual;
- explicação, ex.: "80% — tem 4 de 5 competências; não identificado: Git (peso 2)";
- competências atendidas (com origem e peso) e não identificadas (com peso);
- experiência na área.

O card nunca mostra nome, contato ou link para o perfil do banco. O ranking é calculado a cada
leitura, então editar pesos ou perfis muda a ordem na próxima abertura (FR-020). A vaga pode ser
encerrada; o quadro continua disponível só para consulta.

Com JavaScript:

- criar e editar mostram os erros no formulário e só navegam ao quadro depois do sucesso;
- "Atualizar quadro" refaz a leitura sem recarregar a página (sem polling);
- "Encerrar vaga" substitui o quadro pela versão somente consulta e leva o foco ao título.

Sem JavaScript, tudo funciona por links e formulários tradicionais (PRG).

## Status da verificação

| Verificação | Resultado |
|---|---|
| Testes escritos antes do código | ✅ 43 testes PHP e o teste JS do quadro falhando antes da implementação |
| `php artisan test` | ✅ **175 testes, 888 asserções** (131 anteriores + 44 novos: calculadora 9, ranking 12, vagas 23) |
| Testes JavaScript (`node --test`) | ✅ **38 testes**, sendo 6 novos em `tests/js/kanban.test.js` e um caso novo em `chips.test.js` |
| Pint (`--format agent`) | ✅ Aplicado (2 arquivos ajustados: imports) |
| Build Vite (container isolado, sem `.env`) | ✅ `app.js` 12,6 kB; o seletor `:has()` do formulário foi gerado |
| Teste de fumaça HTTP, cenário C (container sem `.env`) | ✅ Ver detalhes abaixo |
| Revisão no navegador (C/H com e sem JS, teclado, foco, rede lenta) | **Pendente** (ambiente sem navegador) |

Detalhes do teste de fumaça, com o banco semeado:

- as 3 vagas do seeder aparecem em `/vagas`;
- criar sem competências em JSON → 422 com "Selecione ao menos uma competência para a vaga.";
- criar "Estagiário de Desenvolvimento" (Python 3, SQL 3, Git 2, Inglês básico 1, Trabalho em
  equipe 1) em JSON → `redirect` para o quadro;
- o quadro lista os 12 estagiários na ordem do roteiro (100, 90, 90, 80, 70, 60, 60, 30, 30, 10,
  10, 0), sem nenhum nome e sem links `/candidatos/N`, em 0,16 s (SC-003 pede < 3 s);
- o fragmento JSON de "Atualizar quadro" tem a mesma ordem da página;
- Git com peso 3 → os percentuais mudam (91, 91, 73…) na leitura seguinte;
- encerrar em JSON devolve o quadro "somente consulta", sem o botão Encerrar; a edição de vaga
  encerrada volta para o quadro;
- visitante em JSON recebe 401; nenhum erro no log.

## O que foi entregue

| Tarefa | Entrega |
|---|---|
| T080 | `tests/Unit/CalculadoraAderenciaTest.php`: fórmula, half up (67, 33 e 12,5 → 13), 0% e 100%, idiomas por nível, peso essencial, origem, ordem dos faltantes e singular |
| T081, T157 | `tests/Feature/RankingVagaTest.php`: elegibilidade por tipo, desativados, desempate completo, explicação em todos os cards, FR-020, candidato novo, anonimato no HTML e no JSON, JSON com a mesma ordem, 401/404 e o cenário C com os dados semeados |
| T082, T157 | `tests/Feature/VagaTest.php`: formulário, criação, validações (sem competências, peso, repetida, inexistente, campos), edição, encerramento, vaga encerrada, listagem, visitante, modos JSON e o seeder |
| T083 | `app/DTOs/ResultadoAderenciaDTO.php`, com `explicacao()` conforme research R7 |
| T084 | `app/Services/Aderencia/CalculadoraAderencia.php` |
| T085 | `app/DTOs/ItemRankingDTO.php` (sem o model nem o id do candidato) |
| T086 | `app/Repositories/Interfaces/VagaRepositoryInterface.php` e `app/Repositories/VagaRepository.php`, com o vínculo no `RepositoryServiceProvider` |
| T087 | `app/Services/Aderencia/RankingVagaService.php` |
| T088 | `app/Http/Requests/SalvarVagaRequest.php`, com `dadosVaga()` e `competenciasComPeso()` |
| T089–T090 | `VagaController` (`index`, `create`, `store`, `show`, `edit`, `update`) e `VagaStatusController`, HTML e JSON, e `app/Services/VagaService.php` |
| T091 | 7 rotas no bloco `// US3 — vagas` de `routes/vagas.php`, com `whereNumber('vaga')` |
| T092 | `vagas/partials/_formulario` (hard/soft, select de peso por competência, filtro e marcador do bloco de energia da US7), `vagas/create` e `vagas/edit` |
| T093 | `vagas/index`: cards com status, tipo aceito, nº de competências, elegíveis, entrevista e aprovados; estado vazio |
| T094 | `vagas/partials/_card-ranking`, `vagas/partials/_selo-energia` (vazio, para T140) e `vagas/show` |
| T095 | `database/seeders/VagaSeeder.php`: 3 vagas abertas, idempotente; "Estagiário de Desenvolvimento" fica para o pitch |
| T158 | `vagas/partials/_quadro` (região `quadro-vaga`), `data-ajax` em criar/editar/encerrar e "Atualizar quadro" em `resources/js/modulos/kanban.js` |

## Decisões e desvios

| Item | Decisão | Por quê |
|---|---|---|
| Nome dos selects de peso | `competencias[ID][peso]`; o request também aceita `competencias[ID]` e itens `{id, peso}` | Com o id como chave, o erro `competencias.ID.peso` aparece junto ao select certo, também em AJAX; o formato `{id, peso}` permite testar a regra `distinct` |
| `VagaService` | Novo, na raiz de `app/Services` | O plano não previa um service de vaga, e a arquitetura não permite o controller usar o repository; segue o padrão de `CandidatoService` |
| Arredondamento | `intdiv(200 × atendido + total, 2 × total)` | Half up exato, sem erro de ponto flutuante |
| Ordem na explicação | Faltantes e atendidas do maior para o menor peso, depois por nome | A explicação cita antes o que mais pesa |
| Idioma atendido por nível maior | A origem exibida é a da competência que atende; a exata tem preferência | "Inglês avançado" atende "Inglês básico" e mostra se foi declarada ou observada |
| Vaga encerrada | Não pode ser editada: `edit` volta ao quadro com o erro e `update` responde 422 em `vaga`. Encerrar de novo mantém a data original | "Somente consulta" (research R8); editar pesos depois de encerrar mudaria o histórico do ranking |
| Foco após Encerrar | `formularios.js` leva o foco a `[data-foco-apos-ajax]` (o título da vaga) quando o próprio formulário é substituído | Sem isso, o foco ia para o documento. A mudança é aditiva e não altera os outros formulários |
| Filtro de competências | `chips.js` aceita `data-chip-texto` | A linha da vaga contém as opções do select ("Desejável"…), que faziam o filtro casar com todas as competências |
| Destaque da linha com peso | Variante `has-[option:checked:not([value=''])]` do Tailwind v4 | Reage à escolha do peso sem JavaScript extra |
| Teste de renderização do erro | `followingRedirects()` em vez de uma GET separada | As asserções de sessão do teste consomem o *flash* antes da GET seguinte |

## Como verificar

**Com Docker**:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\hackaton.ps1 demo
```

Depois, em http://localhost:8000 → **Vagas**:

1. Confira as 3 vagas semeadas e abra "Analista de Dados": servidores e estagiários juntos, cada
   card com barra e explicação, sem nomes.
2. **Nova vaga** "Estagiário de Desenvolvimento", área Desenvolvimento, tipo Estagiário: Python
   (3), SQL (3), Git (2), Inglês básico (1), Trabalho em equipe (1). Salve: o quadro lista só
   estagiários, do maior para o menor percentual.
3. Tente salvar sem competências: o erro aparece no formulário, sem perder o que foi digitado.
4. **Editar vaga** → Git com peso 3 → salve: a ordem muda.
5. **Atualizar quadro** refaz a leitura sem recarregar; **Encerrar vaga** deixa o quadro
   "somente consulta".

**Testes**: `.\hackaton.ps1 artisan test`. Testes JS: `node --test tests/js/*.test.js`
(Node 22).
