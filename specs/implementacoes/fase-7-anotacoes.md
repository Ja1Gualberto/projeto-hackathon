# Phase 7 — US5: Anotações de entrevista (T113–T121, T161–T162)

**Feature**: [`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/) · **Data**: 2026-11-01 ·
**Rodadas**: R3/R4, trilhas A e B (a dupla estava offline). Implementada sobre as Phases 2–6.

## Resumo

O recrutador registra anotações de entrevista com texto livre. Autor e data são gravados
automaticamente. A vaga e as competências observadas são opcionais. Cada competência observada
passa a constar no perfil com a origem "observada em entrevista": uma origem de currículo ou de
revisão é promovida, nunca rebaixada. O histórico aparece do mais recente para o mais antigo,
com autor, data (dd/mm/aaaa hh:mm), vaga ou "Sem vaga associada" e as competências observadas.

Há dois pontos de registro:

- **Gestão do banco** (`/candidatos/{id}`): vaga opcional num select, inclusive "Sem vaga associada".
- **Perfil na vaga** (`/vagas/{vaga}/candidatos/{codigo}`): só com a identidade revelada nesta vaga.
  A anotação fica associada à vaga da rota. Em triagem, a anotação é negada com **403**, inclusive
  por requisição montada à mão. O perfil revelado mostra todas as anotações do candidato, de qualquer vaga.

Com JavaScript:

- registrar a anotação atualiza o histórico e as competências sem recarregar a página;
- no perfil na vaga, a aderência também é atualizada, pois depende das competências;
- o texto e as seleções só são limpos depois do sucesso, e o foco volta para o campo de texto;
- em erro de validação ou de rede, o rascunho permanece.

Sem JavaScript, o formulário funciona por PRG.

## Status da verificação

| Verificação | Resultado |
|---|---|
| Testes escritos antes do código | ✅ 18 testes PHP e 1 JS falhando antes da implementação |
| `php artisan test` | ✅ **240 testes, 1296 asserções** (222 anteriores + 18 novos em `AnotacaoEntrevistaTest`) |
| Testes JavaScript (`node --test`) | ✅ **44 testes**, sendo 2 novos em `tests/js/formularios.test.js` |
| Pint (`--format agent`) | ✅ Sem ajustes |
| Build Vite (container isolado, sem `.env`) | ✅ `app.js` 14,6 kB; classes novas geradas |
| Teste de fumaça HTTP, cenário E (container sem `.env`) | ✅ Ver detalhes abaixo |
| Revisão no navegador (E/H: limpeza só no sucesso, rascunho em falha, foco, teclado) | **Pendente** (ambiente sem navegador) |

Detalhes do teste de fumaça, com o banco semeado:

- o perfil de Bruno Peixoto na "Analista de Dados" (revelado pelo seeder) mostra a anotação
  semeada, o formulário e "Comunicação" observada;
- anotar pela vaga em JSON marcando "Liderança" → 200, "Anotação registrada.", regiões
  `historico-anotacoes` e `competencias-candidato`, e a origem `entrevista` gravada;
- anotar pelo banco sem vaga (HTML) → 302 para o perfil, "Sem vaga associada" no histórico, na
  ordem mais recente → mais antiga;
- anotar por requisição manual para um candidato em triagem → **403** e nada gravado;
- nenhum erro no log.

## O que foi entregue

| Tarefa | Entrega |
|---|---|
| T113, T161 | `tests/Feature/AnotacaoEntrevistaTest.php`: pela vaga (autor, vaga da rota, texto, competências), promoção de origem, 403 na triagem (HTML, JSON e headers manipulados), pelo banco com e sem vaga, ordem decrescente, triagem sem anotações e revelado em outra vaga com todas, limites do texto, vaga/competência inexistentes, vaga encerrada, formulário do banco, visitante, JSON pelo banco e pela vaga (regiões, persistência, texto escapado), 422 sem gravar e seeder |
| T114 | `AnotacaoRepositoryInterface` e `AnotacaoRepository` (`criar`, `historico`), vinculados no `RepositoryServiceProvider` |
| T115 | `app/Services/Anotacoes/AnotacaoService.php` (`registrar`, `registrarNaVaga`, `historico`, `vagasParaAssociar`, `vagaPorId`) |
| T116 | `app/Http/Requests/RegistrarAnotacaoRequest.php`, com a autorização da triagem em `authorize()` |
| T117–T118 | `AnotacaoController` (banco) e `ParticipacaoAnotacaoController` (vaga), HTML e JSON |
| T119 | `POST /candidatos/{candidato}/anotacoes` e `POST /vagas/{vaga}/candidatos/{codigo}/anotacoes` nos blocos `// US5 — anotações` |
| T120 | `anotacoes/_formulario` e `anotacoes/_historico`, incluídos em `candidatos/partials/_secao-anotacoes` e no perfil revelado (`participacoes/partials/_perfil`) |
| T121 | `database/seeders/VagaSeeder.php`: uma anotação para Bruno Peixoto e outra para Renata Barros na "Analista de Dados", com "Comunicação" observada, idempotente |
| T162 | `candidatos/partials/_competencias` (extraído do perfil do banco), `participacoes/partials/_competencias-na-vaga` (aderência + competências), regiões AJAX e `data-limpar-apos-sucesso` em `formularios.js` |

## Decisões e desvios

| Item | Decisão | Por quê |
|---|---|---|
| 403 da triagem | Em `RegistrarAnotacaoRequest::authorize`, antes da validação | Com o `abort_unless` no controller, uma requisição manual com texto inválido recebia 422 em vez de 403 |
| Vaga encerrada | Sem formulário no perfil na vaga; o POST responde 422 com "registre pela gestão do banco" | A vaga encerrada é somente consulta; a anotação continua possível pelo banco, com a vaga escolhida no select |
| Região `competencias-candidato` na vaga | Inclui a aderência, além das competências | Uma competência observada pode mudar o percentual; atualizar só as tags deixaria a aderência desatualizada na tela |
| Limpeza do formulário | Atributo genérico `data-limpar-apos-sucesso` em `formularios.js`: `reset()` só no sucesso, reaplica o filtro de chips e foca `[data-foco-apos-limpar]` | Reaproveita o transporte comum, sem módulo novo; o rascunho fica em qualquer erro |
| Vagas no select do banco | Todas, encerradas marcadas como "(encerrada)" | A anotação pode ser posterior ao encerramento |
| Histórico no perfil na vaga | Via `AnotacaoRepository::historico` (ordenado, com competências) | Antes vinha do relacionamento sem ordem garantida |
| Dados da tela do perfil | `VisibilidadeCandidatoService::telaDoPerfil` devolve perfil e catálogo (só quando o formulário aparece) | A página e o fragmento `perfil-na-vaga` da movimentação usam os mesmos dados |

## Como verificar

**Com Docker**:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\hackaton.ps1 demo
```

Depois, em http://localhost:8000:

1. **Vagas** → "Analista de Dados" → card de Bruno Peixoto (em Entrevista). O histórico já tem a
   anotação semeada, e "Comunicação" aparece como observada.
2. Registre uma anotação marcando "Liderança": ela entra no topo do histórico, com autor, data e
   vaga, e "Liderança" passa a constar como observada, sem recarregar a página.
3. Tente registrar com o texto vazio: o erro aparece e nada do que foi digitado se perde.
4. **Candidatos** → abra um perfil → registre uma anotação com "Sem vaga associada".
5. Abra um card em triagem: não há anotações nem formulário.

**Testes**: `.\hackaton.ps1 artisan test`. Testes JS: `node --test tests/js/*.test.js`
(Node 22).
