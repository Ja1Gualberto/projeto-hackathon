<!--
Sync Impact Report
- Version change: (template) → 1.0.0
- Modified principles: todos os placeholders substituídos (constituição inicial)
  - [PRINCIPLE_1_NAME] → I. Demo-First: o pitch de 8 minutos manda
  - [PRINCIPLE_2_NAME] → II. Simplicidade Radical (YAGNI)
  - [PRINCIPLE_3_NAME] → III. Design Apresentável e Consistente
  - [PRINCIPLE_4_NAME] → IV. LGPD e Triagem Anônima por Padrão (INEGOCIÁVEL)
  - [PRINCIPLE_5_NAME] → V. IA como Diferencial, Nunca como Dependência
  - Adicionado: VI. Explicabilidade e Decisão Humana
- Added sections: Restrições Técnicas e de Escopo; Fluxo de Desenvolvimento e Quality Gates
- Removed sections: nenhuma
- Templates requiring updates:
  - .specify/templates/plan-template.md ✅ compatível (Constitution Check lê este arquivo em runtime)
  - .specify/templates/spec-template.md ✅ compatível
  - .specify/templates/tasks-template.md ✅ compatível
- Follow-up TODOs:
  - Confirmar a data exata da apresentação (assumido 2026-11-03, "dia 3" citado na reunião).
-->
# Banco de Talentos SETIC Constitution

## Core Principles

### I. Demo-First: o pitch de 8 minutos manda

O produto existe para ser demonstrado, de ponta a ponta, em um pitch de 8 minutos
(com 3 a 5 minutos de demonstração ao vivo).

- Toda funcionalidade MUST servir ao fluxo principal:
  **PDF → leitura automática → banco pesquisável → vaga com pesos → ranking explicado →
  triagem anônima → kanban/entrevista com registro**.
- O fluxo principal MUST funcionar sem falhas com os dados fictícios do seeder, a partir de um
  ambiente limpo, com um único comando de preparação.
- Uma funcionalidade que não aparece na demonstração nem fortalece o pitch MUST NOT ser
  priorizada antes do fluxo principal estar pronto.
- Cada história de usuário MUST ser demonstrável isoladamente (fatia vertical) e caber em até
  60 segundos de apresentação.

**Racional:** os jurados avaliam aplicabilidade, clareza para o usuário e qualidade da
demonstração com dados de exemplo. Um fluxo curto e impecável vale mais que muitas telas
incompletas.

### II. Simplicidade Radical (YAGNI)

- MUST-se escolher sempre a solução mais simples que atende ao requisito do desafio.
- Etapas de seleção são fixas (triagem, entrevista, aprovado); pesos usam escala curta (1 a 3);
  competências vêm de um catálogo padronizado. Configurações genéricas MUST NOT ser criadas.
- Sem novas dependências, serviços ou pastas-base sem justificativa registrada no plano
  (seção *Complexity Tracking*).
- Ficam fora do MVP, salvo decisão explícita: OCR/Word/importação em lote, portal do candidato,
  e-mails/notificações, cálculo de anos de experiência, integrações (CIEE, WhatsApp, login
  institucional), fluxos configuráveis.
- Nova funcionalidade só entra no escopo se outra de esforço equivalente sair ou for adiada.

**Racional:** o prazo é de hackathon; complexidade consome o tempo do núcleo e aumenta o
risco de a demonstração falhar.

### III. Design Apresentável e Consistente

- A interface MUST parecer um produto real: visual moderno, limpo, com hierarquia clara,
  espaçamento generoso e tipografia consistente.
- MUST existir um pequeno design system (cores, tipografia, componentes Blade reutilizáveis:
  botão, card, badge/tag, input, modal, barra de score) aplicado em todas as telas.
- Servidores e estagiários MUST ter diferenciação visual imediata (cor/badge), e o score de
  aderência MUST ser visual (barra ou anel de progresso + percentual).
- Toda tela MUST ter estados de vazio, carregamento e erro amigáveis, em português claro.
- Contraste e legibilidade MUST atender WCAG 2.1 AA nos elementos principais; a interface MUST
  funcionar bem em projetor (fontes legíveis, sem dependência de detalhes minúsculos).
- Microinterações (transições, feedback de ação) SHOULD ser usadas com moderação para dar
  sensação de produto polido.

**Racional:** "clareza para o usuário final" é critério de avaliação, e a primeira impressão
visual define a percepção dos jurados no pitch.

### IV. LGPD e Triagem Anônima por Padrão (INEGOCIÁVEL)

- Na etapa de triagem, nome, idade, foto, contatos e o PDF original MUST ficar ocultos; o
  candidato é identificado apenas por um código (ex.: `CAND-0042`).
- A identidade só MUST ser revelada a partir da etapa de entrevista, **por vaga** — estar em
  entrevista em uma vaga não revela a identidade nas demais.
- O registro do candidato MUST conter o status de consentimento (como e quando foi obtido),
  e o sistema MUST permitir correção e exclusão definitiva dos dados a pedido do candidato.
  Desativar não equivale a excluir.
- MUST existir política de retenção visível (padrão: revisão após 2 anos); a automação do prazo
  pode ficar para depois, mas a regra não pode ser ignorada.
- Currículos e anotações MUST ser acessíveis apenas a usuários autenticados.
- Somente dados fictícios MUST ser usados no seeder e na demonstração.

**Racional:** é restrição explícita do desafio e um diferencial de confiança perante a SETIC.

### V. IA como Diferencial, Nunca como Dependência

- Recursos de IA (leitura inteligente do currículo, padrão de energia, recomendações) MUST
  ter fallback determinístico: se a IA estiver indisponível, sem chave ou sem internet, o
  sistema continua funcionando (extração por regras/catálogo, ranking por fórmula).
- O ranking principal MUST ser determinístico e reprodutível; a IA pode complementar, nunca
  substituir a fórmula de aderência.
- Saídas de IA MUST ser apresentadas como sugestões revisáveis e editáveis por humanos.
- A demonstração MUST poder rodar offline sem perder o fluxo principal.

**Racional:** a IA impressiona no pitch, mas uma falha de rede ao vivo não pode derrubar a
apresentação; além disso, há incerteza sobre o envio de dados pessoais a serviços externos.

### VI. Explicabilidade e Decisão Humana

- Todo percentual de aderência MUST vir acompanhado do motivo: competências atendidas,
  competências não identificadas e o peso de cada uma (ex.: "80% — tem 4 de 5; falta inglês").
- Fórmula padrão: **aderência = Σ pesos das competências identificadas ÷ Σ pesos solicitados
  × 100**.
- "Não identificado no currículo" MUST ser exibido de forma distinta de "não possui"; soft
  skills extraídas do currículo são **declaradas**, não comprovadas.
- Aprovação e reprovação MUST ser sempre ações humanas; o sistema apenas ordena e explica.

**Racional:** transparência reduz vieses, gera confiança do avaliador e é argumento forte de
pitch para o setor público.

## Restrições Técnicas e de Escopo

- **Stack:** Laravel 13 (PHP 8.5), Blade + Tailwind CSS v4 via Vite, banco relacional
  (SQLite local ou MySQL via Docker). Mudanças de dependências exigem aprovação.
- **Arquitetura:** seguir o fluxo já adotado no projeto —
  **Rota → Form Request → Controller → Service → Repository → Model**. Regras de negócio
  (score, anonimização, extração) vivem em Services; vínculos de contratos em
  `RepositoryServiceProvider`.
- **Dados de demonstração:** seeders com dados fictícios realistas (mínimo ~20 candidatos,
  mistura de servidores e estagiários, 3 vagas de áreas distintas e PDFs de exemplo).
- **Segurança do ambiente:** o arquivo `.env` MUST NOT ser lido, criado ou alterado por agentes
  de IA (ver `CLAUDE.md`); configurações necessárias são documentadas em `.env.example` e
  executadas pelo usuário.
- **Idioma:** interface, mensagens e documentação de produto em português do Brasil.
- **Prazo:** entrega e apresentação previstas para 2026-11-03.

## Fluxo de Desenvolvimento e Quality Gates

- O desenvolvimento segue o Spec Kit: `/speckit-specify` → `/speckit-clarify` (opcional) →
  `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`. Especificações ficam em `specs/`.
- Implementação por prioridade de história (P1 → P2 → P3); cada história concluída MUST deixar
  a aplicação demonstrável.
- **Gates obrigatórios antes de considerar uma história pronta:**
  1. Testes de feature cobrindo as regras críticas: cálculo de aderência, ocultação de
     identidade na triagem e bloqueio do PDF original.
  2. `vendor/bin/pint --dirty --format agent` sem pendências.
  3. Fluxo da história executado manualmente no navegador com os dados do seeder.
  4. Revisão visual: a tela segue o design system e é legível em projetor.
- Testes são exigidos para regras de negócio críticas; testes exaustivos de UI não são
  exigidos no MVP.

## Governance

- Esta constituição prevalece sobre outras práticas do projeto, exceto as restrições de
  segurança do `.env` em `CLAUDE.md`, que têm precedência absoluta.
- Todo `plan.md` MUST passar pelo *Constitution Check*; violações exigem justificativa na seção
  *Complexity Tracking* ou ajuste do plano.
- Emendas exigem: descrição da mudança, motivo, atualização da versão e da data de emenda.
- Versionamento semântico: MAJOR para remoção/redefinição de princípio; MINOR para princípio ou
  seção nova; PATCH para ajustes de redação.
- Orientações de execução do dia a dia ficam em `CLAUDE.md`/`AGENTS.md` e em `.ai/rules`,
  quando existir.

**Version**: 1.0.0 | **Ratified**: 2026-10-30 | **Last Amended**: 2026-10-30
