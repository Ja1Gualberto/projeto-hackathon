# Contrato: Visibilidade de Dados por Contexto (LGPD e Triagem Anônima)

**Feature**: `001-banco-de-talentos-mvp` | Princípio IV da constituição (inegociável), FR-023
a FR-025.

Este contrato define **o que cada tela pode receber** do servidor. A garantia é estrutural: no
contexto da vaga, as views recebem o DTO `PerfilNaVaga`, montado por
`VisibilidadeCandidatoService`. Campos não permitidos chegam **nulos**, e não apenas
escondidos por CSS ou template.

## Contextos

| Contexto | Onde | Quem |
|---|---|---|
| **Banco** | `/candidatos*`, painel | Recrutador autenticado (gestão autorizada do banco) |
| **Vaga · Triagem** | `/vagas/{vaga}` coluna Triagem e `/vagas/{vaga}/candidatos/{codigo}` com etapa `triagem` | Recrutador autenticado |
| **Vaga · Revelado** | Mesmas rotas com etapa `entrevista` ou `aprovado` **nesta vaga** | Recrutador autenticado |
| **IA externa** | Payload enviado ao `AnalisadorEnergia` | Serviço externo |

## Matriz de campos

| Campo | Banco | Vaga · Triagem | Vaga · Revelado | IA externa |
|---|---|---|---|---|
| Código `CAND-NNNN` | ✗ (não exibido) | ✓ | ✓ (secundário) | ✗ |
| Nome | ✓ | ✗ | ✓ | ✗ |
| E-mail, telefone | ✓ | ✗ | ✓ | ✗ |
| CPF | ✓ (mascarado `***.***.***-12`) | ✗ | ✗ | ✗ |
| Idade / data de nascimento | ✓ | ✗ | ✓ (idade) | ✗ |
| Foto | não armazenada (existe só dentro do PDF) | ✗ | — | ✗ |
| PDF original | ✓ | ✗ **403** | ✓ | ✗ |
| Links (GitHub, LinkedIn, portfólio) | ✓ | ✗ ("N links — visíveis a partir da entrevista") | ✓ | ✗ |
| Tipo, área, experiência na área | ✓ | ✓ | ✓ | ✗ |
| Formação, experiências, projetos | ✓ | ✓ (sem e-mails, telefones, CPF e URLs) | ✓ | ✓ (limpos) |
| Competências e origem | ✓ | ✓ | ✓ | ✓ (só nomes) |
| Aderência e explicação | — | ✓ | ✓ | ✗ |
| Perfil de energia | ✓ | ✓ | ✓ | — |
| Anotações de entrevista | ✓ | ✗ (podem citar o nome) | ✓ (todas, de qualquer vaga) | ✗ |
| Consentimento e retenção | ✓ | ✗ | ✗ | ✗ |
| Histórico de movimentações | ✓ | ✓ (desta vaga) | ✓ (desta vaga) | ✗ |
| Busca por nome | ✓ | ✗ (inexistente) | ✗ | — |

## Invariantes (cobertas por testes de feature)

1. Para um candidato em triagem na vaga A, as respostas de `GET /vagas/A` e
   `GET /vagas/A/candidatos/{codigo}` **não contêm** nome, e-mail, telefone, CPF nem
   `route('candidatos.show', $candidato)`.
2. `GET /vagas/A/candidatos/{codigo}/curriculo` → 403 em triagem; 200 com `application/pdf`
   após mover para Entrevista.
3. Mover para Entrevista na vaga A **não** revela o candidato na vaga B (invariante 1 vale para B).
4. Voltar para Triagem na vaga A volta a ocultar a identidade em A.
5. Toda movimentação grava `movimentacoes_etapa` com `user_id` e data.
6. A gestão do banco não exibe códigos `CAND-NNNN` (o banco e a triagem não podem ser
   cruzados pela interface).
7. O texto entregue ao `AnalisadorEnergia` não contém nome, e-mail, telefone, CPF nem URLs do
   candidato.
8. Visitante não autenticado não acessa nenhuma rota de candidatos, vagas ou PDFs.

## Limitação conhecida

Com um único perfil de recrutador (decisão de escopo da spec), quem acessa a gestão do banco vê
as identidades. O anonimato é garantido **dentro do contexto da triagem da vaga**, que é onde a
decisão inicial acontece. Papéis separados (quem triagem não acessa o banco) ficam como
evolução.
