# Registro de implementações

Esta pasta guarda um relatório por fase implementada da feature
[`001-banco-de-talentos-mvp`](../001-banco-de-talentos-mvp/): o que foi feito, os arquivos
criados ou alterados, as decisões e os desvios em relação ao
[`tasks.md`](../001-banco-de-talentos-mvp/tasks.md) e como verificar.

O `tasks.md` continua sendo a fonte de verdade do andamento (tarefas marcadas com `[X]`). Estes
relatórios explicam o *como* e o *porquê* de cada entrega, para quem continuar o trabalho.

| Fase | Tarefas | Data | Relatório | Verificação |
|---|---|---|---|---|
| Phase 1 — Setup | T001–T011 (T012 é manual) | 2026-10-30 | Notas no `tasks.md` | Testes de infraestrutura passando |
| Phase 2 — Foundational | T013–T044 | 2026-10-31 | [fase-2-fundacao.md](./fase-2-fundacao.md) | ✅ 28 testes, Pint, seed e build ok (revisão visual pendente) |
| Phase 3 — US1 Busca e painel | T045–T062, T149–T154 | 2026-10-31 | Notas no `tasks.md` | ✅ Testes, Pint e build ok (revisão visual pendente) |
| Phase 4 — US2 Importação de PDF | T063–T079, T155–T156 | 2026-10-31 | [fase-4-importacao.md](./fase-4-importacao.md) | ✅ 131 testes PHP, 32 JS, Pint, build e fumaça HTTP ok (revisão no navegador pendente) |
| Phase 5 — US3 Vagas e ranking | T080–T095, T157–T158 | 2026-11-01 | [fase-5-vagas-ranking.md](./fase-5-vagas-ranking.md) | ✅ 175 testes PHP, 38 JS, Pint, build e fumaça HTTP ok (revisão no navegador pendente) |

Para uma nova fase, crie `fase-N-<nome>.md` seguindo a mesma estrutura e acrescente uma linha
nesta tabela.
