# Feature Specification: Banco de Talentos SETIC — MVP

**Feature Branch**: `001-banco-de-talentos-mvp`

**Created**: 2026-10-30

**Status**: Draft

**Input**: User description: "Banco de talentos para a SETIC (Desafio 2 do Hackathon): centralizar
currículos em PDF de servidores e estagiários, extrair automaticamente formação, experiências e
competências, permitir busca e filtros por competência, área e tipo, cadastrar vagas com
competências e pesos, ranquear candidatos com percentual de aderência explicado, acompanhar a
seleção em kanban com triagem anônima, registrar anotações de entrevistas e respeitar a LGPD.
Diferencial: análise do padrão de energia com IA. Foco em um MVP simples, bonito e demonstrável
em um pitch de 8 minutos." — consolidado a partir de `anotacoes_projeto/`.

## Visão Geral

Hoje os currículos chegam por WhatsApp, e-mail, drives pessoais e listas do CIEE e ficam onde
caíram. Quando surge uma vaga, alguém precisa lembrar onde estão, abrir um por um e comparar de
cabeça. O Banco de Talentos reúne tudo em um só lugar e faz o trabalho pesado:

**Currículo entra → Sistema lê → Vaga cadastrada → Candidatos ranqueados → Seleção acompanhada**

O valor central, validado na reunião com a área cliente, é: **encontrar candidatos sem abrir
cada currículo, entender a correspondência com uma vaga e recuperar o que foi descoberto nas
entrevistas**.

### Roteiro da demonstração (referência para priorização)

1. Painel inicial: quantos talentos existem, servidores × estagiários, competências mais comuns.
2. Envio de um PDF → sistema preenche o cadastro sozinho → recrutador revisa e confirma.
3. Busca "Python + SQL, estagiários" → cards com tags e contagem de resultados.
4. Cadastro da vaga "Estagiário de Desenvolvimento" com pesos → triagem anônima ordenada por
   aderência, cada card com "80% — tem 4 de 5; falta inglês".
5. Move os 3 melhores para "Entrevista" → nomes aparecem → registra anotação da entrevista.
6. (Diferencial) Padrão de energia da vaga × perfil sugerido do candidato.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Encontrar talentos no banco com filtros (Priority: P1)

Como recrutador, quero pesquisar o banco de talentos por competências, área de atuação e tipo
(servidor ou estagiário), combinando filtros, para saber em segundos quantos e quais candidatos
têm o perfil que procuro — sem abrir nenhum currículo.

**Why this priority**: foi apontado pela área cliente como "a parte mais importante": ter o
banco e conseguir filtrar dentro dele. É o principal resultado que precisa funcionar na
demonstração e já entrega valor sozinho, com os dados fictícios pré-carregados.

**Independent Test**: com o banco pré-carregado de candidatos fictícios, aplicar os filtros
"Python" + "SQL" + "Estagiário" e verificar que somente os candidatos que atendem a todos os
critérios aparecem, com a contagem correta e cards resumidos.

**Acceptance Scenarios**:

1. **Given** o banco com candidatos fictícios, **When** o recrutador abre o sistema, **Then** vê
   um painel com o total de talentos, a divisão servidores × estagiários, o número de vagas
   abertas e as competências mais frequentes.
2. **Given** o banco carregado, **When** o recrutador seleciona as competências "Python" e
   "SQL", **Then** são listados apenas candidatos que possuem **ambas** e é exibida a quantidade
   encontrada (ex.: "7 talentos encontrados").
3. **Given** um resultado de busca, **When** o recrutador adiciona o filtro "Estagiário" e a
   área "Desenvolvimento", **Then** a lista e a contagem são atualizadas combinando todos os
   filtros.
4. **Given** a lista de resultados, **When** o recrutador observa os cards, **Then** cada card
   mostra o nome (na gestão do banco) ou apenas o código (dentro da triagem de uma vaga), tipo
   com indicação visual distinta
   para servidor e estagiário, área, indicação de experiência na área e tags das principais
   competências.
5. **Given** um card, **When** o recrutador o abre, **Then** vê o perfil completo estruturado:
   formação, experiências, competências (hard e soft, com a origem), projetos/links e
   anotações de entrevistas.
6. **Given** filtros que não retornam ninguém, **When** a busca é aplicada, **Then** é exibido
   um estado vazio amigável com sugestão para remover filtros.

---

### User Story 2 - Cadastrar currículo a partir do PDF com leitura automática (Priority: P1)

Como recrutador, quero enviar o PDF de um currículo e ver o sistema extrair sozinho a formação,
as experiências, as competências e os links, para revisar, corrigir se necessário e salvar no
banco — sem redigitar o currículo.

**Why this priority**: é a porta de entrada do banco e o momento "uau" do pitch ("ninguém
precisa digitar o currículo de novo"). Sem ela o banco depende de digitação manual.

**Independent Test**: enviar um PDF fictício com texto selecionável e verificar que o
formulário de revisão aparece pré-preenchido com as competências do catálogo presentes no
texto; confirmar e verificar que o candidato aparece nas buscas da História 1.

**Acceptance Scenarios**:

1. **Given** um PDF com texto selecionável, **When** o recrutador o envia informando o tipo
   (servidor ou estagiário), **Then** o sistema apresenta um formulário de revisão
   pré-preenchido com dados de identificação, formação, experiências, competências
   identificadas, área sugerida e links encontrados.
2. **Given** o formulário de revisão, **When** o recrutador corrige, adiciona ou remove
   competências e confirma, **Then** o candidato é salvo com os dados revisados e o PDF original
   fica guardado para consulta.
3. **Given** o formulário de revisão, **When** o recrutador tenta salvar sem registrar o
   consentimento do candidato (meio e data), **Then** o sistema impede o cadastro e explica o
   motivo.
4. **Given** competências extraídas do currículo, **When** são soft skills, **Then** ficam
   marcadas como "declaradas no currículo".
5. **Given** um PDF sem texto legível (ex.: digitalizado), **When** é enviado, **Then** o sistema
   informa que não conseguiu ler o conteúdo e oferece o preenchimento manual, mantendo o PDF.
6. **Given** um candidato já cadastrado com o mesmo e-mail ou CPF, **When** um novo PDF é
   enviado, **Then** o sistema alerta sobre possível duplicidade e permite atualizar o cadastro
   existente em vez de criar outro.

---

### User Story 3 - Cadastrar vaga com pesos e ver o ranking explicado (Priority: P2)

Como recrutador, quero cadastrar uma vaga informando área, tipo de candidato e as competências
pedidas com um peso para cada uma, para ver os candidatos elegíveis ordenados por aderência,
com o percentual e o motivo.

**Why this priority**: transforma o banco em ferramenta de decisão e é o coração do critério
"comparar". Depende de haver candidatos no banco (Histórias 1/2 ou seeder).

**Independent Test**: cadastrar a vaga "Estagiário de Desenvolvimento" com Python (peso 3),
SQL (3), Git (2), Inglês básico (1) e Trabalho em equipe (1) e verificar que os estagiários
aparecem ordenados pelo percentual calculado pela fórmula, cada um com a explicação.

**Acceptance Scenarios**:

1. **Given** o catálogo de competências, **When** o recrutador cadastra uma vaga com título,
   área, tipo de candidato (servidor, estagiário ou ambos) e competências com pesos
   1 (desejável), 2 (importante) ou 3 (essencial), separando hard e soft skills, **Then** a vaga
   é salva e fica aberta.
2. **Given** uma vaga aberta, **When** o recrutador a abre, **Then** vê os candidatos elegíveis
   (respeitando o tipo da vaga) ordenados do maior para o menor percentual de aderência.
3. **Given** um candidato no ranking, **When** o recrutador observa seu card, **Then** vê o
   percentual em formato visual e a explicação, ex.: "80% — tem 4 de 5 competências; não
   identificado: Inglês intermediário (peso 2)", distinguindo competências atendidas e não
   identificadas.
4. **Given** uma vaga existente, **When** o recrutador altera pesos ou competências, **Then** o
   ranking é recalculado imediatamente.
5. **Given** dois candidatos com o mesmo percentual, **When** o ranking é exibido, **Then** a
   ordem é estável e previsível (desempate por maior soma de pesos essenciais atendidos e, em
   seguida, por data de cadastro).

---

### User Story 4 - Triagem anônima e acompanhamento em kanban (Priority: P2)

Como recrutador, quero acompanhar a seleção de cada vaga em um quadro com as etapas Triagem,
Entrevista e Aprovado, com a triagem anônima, para escolher pelas competências sem vieses e só
conhecer a identidade a partir da entrevista.

**Why this priority**: cobre os critérios "acompanhar" e "triagem anônima" do desafio e é um
argumento forte de pitch (redução de vieses + LGPD). Depende da História 3.

**Independent Test**: abrir uma vaga, verificar que todos os cards de triagem mostram apenas
código, score e competências (sem nome, idade, foto, contatos ou acesso ao PDF); mover três
candidatos para Entrevista e verificar que os nomes aparecem somente para essa vaga.

**Acceptance Scenarios**:

1. **Given** uma vaga aberta, **When** o recrutador abre seu quadro, **Then** todos os
   candidatos elegíveis aparecem na coluna Triagem, ordenados por aderência, identificados apenas
   por código (ex.: CAND-0042).
2. **Given** um card em Triagem, **When** o recrutador abre o perfil, **Then** nome, idade, foto,
   contatos e o PDF original permanecem ocultos e inacessíveis, inclusive por link direto.
3. **Given** um card em Triagem, **When** o recrutador o move para Entrevista, **Then** a
   identidade é revelada para essa vaga e o sistema registra quem fez a mudança e quando.
4. **Given** um candidato em Entrevista na vaga A, **When** ele aparece na triagem da vaga B,
   **Then** continua anônimo na vaga B.
5. **Given** um candidato em Entrevista, **When** o recrutador o move para Aprovado ou de volta,
   **Then** a etapa é atualizada apenas para aquela vaga.
6. **Given** a busca por nome na gestão do banco, **When** o recrutador está no contexto de
   triagem de uma vaga, **Then** a busca não revela a identidade de candidatos em triagem.

---

### User Story 5 - Registrar e recuperar anotações de entrevista (Priority: P3)

Como recrutador, quero registrar anotações sobre a entrevista no perfil do candidato,
associadas (quando houver) a uma vaga, para que o que foi descoberto não se perca e sirva para
seleções futuras.

**Why this priority**: resolve uma dor relatada ("não tem um lugar que a gente guarda"), com
escopo pequeno. Depende de o candidato ter identidade revelada (História 4) ou ser acessado na
gestão do banco.

**Independent Test**: abrir um candidato em Entrevista, registrar uma anotação incluindo uma
soft skill observada, e verificar que ela aparece no histórico do perfil com autor, data e vaga,
inclusive quando o candidato é aberto a partir de outra vaga após revelação.

**Acceptance Scenarios**:

1. **Given** um candidato em Entrevista, **When** o recrutador registra uma anotação, **Then** ela
   é salva com autor, data e a vaga associada.
2. **Given** o perfil de um candidato, **When** o recrutador consulta o histórico, **Then** vê
   todas as anotações em ordem cronológica decrescente.
3. **Given** uma anotação, **When** o recrutador marca competências observadas na entrevista,
   **Then** essas competências passam a constar no perfil com a origem "observada em
   entrevista", distinta de "declarada no currículo".
4. **Given** uma entrevista feita antes da definição da área, **When** a anotação é registrada
   sem vaga, **Then** ela fica associada apenas ao candidato.

---

### User Story 6 - Direitos do titular e retenção (LGPD) (Priority: P3)

Como responsável pelo banco, quero consultar o consentimento de cada candidato, corrigir seus
dados e excluí-los definitivamente quando solicitado, e ver quais cadastros atingiram o prazo de
retenção, para cumprir a LGPD.

**Why this priority**: é restrição explícita do desafio e diferencial de confiança, mas no
pitch ocupa poucos segundos. A coleta do consentimento já ocorre na História 2.

**Independent Test**: abrir um candidato, verificar o registro de consentimento, solicitar a
exclusão e verificar que ele some de buscas, rankings e quadros, e que o PDF não é mais
acessível.

**Acceptance Scenarios**:

1. **Given** o perfil de um candidato, **When** o responsável o consulta, **Then** vê o meio e a
   data do consentimento e a data limite de retenção (2 anos após o cadastro ou a última
   atualização).
2. **Given** um pedido de exclusão, **When** o responsável confirma a exclusão definitiva,
   **Then** dados, PDF, competências, etapas e anotações do candidato são removidos e um registro
   mínimo, sem dados pessoais, documenta que a exclusão ocorreu.
3. **Given** cadastros cuja data de retenção venceu, **When** o responsável acessa o banco,
   **Then** eles aparecem sinalizados como "revisão de retenção pendente".
4. **Given** um candidato, **When** o responsável o desativa, **Then** ele deixa de aparecer em
   buscas e rankings, mas seus dados continuam existindo até a exclusão.

---

### User Story 7 - Padrão de energia com IA (diferencial) (Priority: P4)

Como recrutador, quero indicar na vaga o perfil comportamental desejado em três eixos
(dinâmico ↔ analítico, individual ↔ equipe, execução ↔ planejamento) e ver uma sugestão do
quanto cada candidato se aproxima desse perfil, para enriquecer a comparação além das
competências técnicas.

**Why this priority**: listado no desafio como desejável (não obrigatório); é o diferencial
visual e de inovação do pitch, mas não pode competir com o fluxo principal.

**Independent Test**: definir o padrão de energia de uma vaga, abrir candidatos no ranking e
verificar que cada um exibe um perfil sugerido nos três eixos, a proximidade com a vaga e uma
justificativa curta; desligar a IA e verificar que o restante do sistema continua funcionando.

**Acceptance Scenarios**:

1. **Given** o cadastro de vaga, **When** o recrutador posiciona os três eixos, **Then** o padrão
   de energia fica salvo na vaga.
2. **Given** um candidato com currículo lido, **When** a análise é executada, **Then** o sistema
   sugere sua posição nos três eixos com uma justificativa curta baseada em trechos do currículo.
3. **Given** uma vaga com padrão de energia, **When** o recrutador vê o ranking, **Then** cada
   candidato exibe a proximidade com o padrão como informação **separada** do percentual de
   aderência, sem alterar a ordenação principal.
4. **Given** a IA indisponível, **When** o recrutador usa o sistema, **Then** o padrão de energia
   aparece como "análise indisponível" e todas as demais funções seguem normais.
5. **Given** um perfil sugerido, **When** o recrutador discorda, **Then** pode ajustá-lo
   manualmente, ficando registrado que foi revisado por humano.

---

### Edge Cases

- **PDF digitalizado/sem texto ou corrompido**: mensagem clara, PDF mantido e preenchimento
  manual oferecido.
- **Arquivo que não é PDF ou muito grande**: rejeitado com mensagem amigável (limite de 10 MB).
- **Competência no currículo que não está no catálogo**: não é cadastrada automaticamente; aparece
  como sugestão para o recrutador incluir no catálogo ou ignorar.
- **Candidato sem nenhuma competência da vaga**: aparece no fim do ranking com 0% e a mensagem
  "nenhuma competência solicitada foi identificada no currículo" (não "não possui").
- **Vaga sem competências**: não pode ser salva.
- **Vaga do tipo "estagiário"**: servidores não aparecem no ranking nem no quadro, e vice-versa.
- **Candidato cadastrado após a criação da vaga**: passa a aparecer automaticamente na triagem
  das vagas abertas compatíveis.
- **Candidato movido de Entrevista de volta para Triagem**: volta a ficar anônimo naquela vaga.
- **Candidato excluído (LGPD) enquanto participa de seleções**: some de todos os quadros, e as
  vagas continuam funcionando.
- **Duplicidade**: mesmo e-mail ou CPF gera alerta antes de criar um novo cadastro.
- **Vaga encerrada**: deixa de aceitar movimentações, mas mantém o histórico para consulta.

## Requirements *(mandatory)*

### Functional Requirements

**Banco e busca (História 1)**

- **FR-001**: O sistema MUST manter um cadastro único de candidatos, independente de vagas,
  reaproveitável em seleções futuras.
- **FR-002**: Cada candidato MUST conter: identificação e contato (acesso restrito), tipo
  (servidor ou estagiário), área de atuação, formação, experiências, indicação de experiência na
  área ("sim" ou "não informado"), competências (hard/soft, com origem), projetos e links quando
  informados, PDF original e registro de consentimento.
- **FR-003**: O sistema MUST permitir filtrar candidatos por uma ou mais competências, por área
  de atuação, por tipo e por experiência na área, combinando os filtros (o candidato precisa
  atender a todos os filtros selecionados).
- **FR-004**: O sistema MUST exibir a quantidade de candidatos encontrados e um card-resumo por
  candidato com tipo, área, indicação de experiência e tags das principais competências.
- **FR-005**: O sistema MUST exibir um painel inicial com total de talentos, divisão servidores ×
  estagiários, vagas abertas e competências mais frequentes no banco.
- **FR-006**: O sistema MUST diferenciar visualmente servidores e estagiários em todas as listas,
  cards e quadros.
- **FR-007**: O sistema MUST diferenciar "não informado no currículo" de "não possui" em todas as
  telas e mensagens.

**Entrada de currículos (História 2)**

- **FR-008**: O sistema MUST aceitar o envio de currículos em PDF com texto selecionável (até
  10 MB) e guardar o arquivo original.
- **FR-009**: O sistema MUST extrair automaticamente do PDF: dados de identificação e contato,
  formação, experiências, competências pertencentes ao catálogo, área sugerida e links.
- **FR-010**: O sistema MUST apresentar os dados extraídos em um formulário de revisão editável
  antes de salvar.
- **FR-011**: O sistema MUST exigir o registro do consentimento do candidato (meio de obtenção e
  data) para concluir o cadastro.
- **FR-012**: O sistema MUST marcar soft skills extraídas do currículo como "declaradas" e as
  registradas em entrevista como "observadas".
- **FR-013**: O sistema MUST alertar sobre possível duplicidade (mesmo e-mail ou CPF) e permitir
  atualizar o cadastro existente.
- **FR-014**: O sistema MUST manter um catálogo padronizado de competências (hard e soft) usado
  na extração, nos filtros e nas vagas.

**Vagas e aderência (História 3)**

- **FR-015**: O sistema MUST permitir cadastrar e editar vagas com título, área, tipo de
  candidato (servidor, estagiário ou ambos), descrição curta e competências do catálogo.
- **FR-016**: Cada competência da vaga MUST ter um peso próprio daquela vaga: 1 (desejável),
  2 (importante) ou 3 (essencial). O peso pertence à relação vaga–competência.
- **FR-017**: O sistema MUST calcular a aderência de cada candidato elegível como: soma dos pesos
  das competências da vaga identificadas no candidato ÷ soma dos pesos de todas as competências
  da vaga × 100, arredondada para inteiro.
- **FR-018**: O sistema MUST exibir os candidatos elegíveis ordenados pela aderência, com o
  percentual em formato visual e a explicação com competências atendidas e não identificadas e
  seus pesos.
- **FR-019**: Experiência, projetos e padrão de energia MUST NOT alterar o percentual de
  aderência no MVP.
- **FR-020**: O sistema MUST recalcular o ranking sempre que a vaga ou o perfil do candidato
  forem alterados.

**Seleção e anonimato (História 4)**

- **FR-021**: Cada vaga MUST ter um quadro com as etapas fixas Triagem, Entrevista e Aprovado;
  todos os candidatos elegíveis entram em Triagem automaticamente.
- **FR-022**: A etapa MUST pertencer à participação do candidato naquela vaga; mudar a etapa em
  uma vaga não altera as demais.
- **FR-023**: Em Triagem, o sistema MUST ocultar nome, idade, foto, contatos e o PDF original,
  identificando o candidato por um código estável, e MUST bloquear o acesso a esses dados
  inclusive por acesso direto.
- **FR-024**: Ao mover o candidato para Entrevista ou Aprovado, o sistema MUST revelar a
  identidade apenas naquela vaga e registrar quem fez a movimentação e quando.
- **FR-025**: A busca por nome MUST existir apenas na gestão do banco e MUST NOT revelar
  identidade de candidatos dentro do contexto de triagem de uma vaga.

**Entrevistas (História 5)**

- **FR-026**: O sistema MUST permitir registrar anotações de entrevista com texto livre, autor e
  data automáticos, vaga opcional e competências observadas opcionais.
- **FR-027**: O sistema MUST exibir o histórico de anotações no perfil do candidato em ordem
  cronológica decrescente.

**LGPD e acesso (História 6)**

- **FR-028**: Somente usuários autenticados MUST acessar o sistema; o MVP tem um único perfil de
  recrutador.
- **FR-029**: O sistema MUST permitir corrigir os dados de um candidato a qualquer momento.
- **FR-030**: O sistema MUST permitir a exclusão definitiva de um candidato, removendo dados
  pessoais, PDF, competências, participações e anotações, e mantendo apenas um registro anônimo
  de que a exclusão ocorreu.
- **FR-031**: O sistema MUST permitir desativar um candidato (oculto de buscas e rankings) sem
  excluir seus dados.
- **FR-032**: O sistema MUST exibir a data limite de retenção de cada candidato (2 anos após o
  cadastro ou a última atualização) e sinalizar cadastros vencidos como "revisão de retenção
  pendente", sem exclusão automática.

**Padrão de energia (História 7 — diferencial)**

- **FR-033**: O sistema SHOULD permitir definir, por vaga, o padrão de energia desejado em três
  eixos: dinâmico ↔ analítico, individual ↔ equipe, execução ↔ planejamento.
- **FR-034**: O sistema SHOULD sugerir, a partir do currículo, a posição do candidato nos três
  eixos, com justificativa curta, editável pelo recrutador.
- **FR-035**: A proximidade com o padrão de energia SHOULD ser exibida separada do percentual de
  aderência, sem alterar a ordenação principal.
- **FR-036**: Se a análise automática estiver indisponível, o sistema MUST continuar operando
  normalmente e indicar "análise indisponível".

**Demonstração**

- **FR-037**: O sistema MUST oferecer uma carga de dados fictícios com ao menos 20 candidatos
  (servidores e estagiários de áreas variadas), 3 vagas de áreas distintas e PDFs de exemplo
  para o envio ao vivo.

### Key Entities *(include if feature involves data)*

- **Candidato**: pessoa no banco de talentos. Código anônimo estável, tipo (servidor/estagiário),
  área de atuação, dados de identificação e contato (restritos), formação, experiências,
  indicação de experiência na área, projetos/links, PDF original, status (ativo/desativado),
  data limite de retenção.
- **Competência**: item do catálogo padronizado, com nome, categoria (hard/soft) e sinônimos
  usados na leitura do currículo.
- **Competência do Candidato**: relação candidato–competência com a origem ("declarada no
  currículo", "observada em entrevista" ou "adicionada na revisão").
- **Vaga**: título, área, tipo de candidato aceito, descrição, situação (aberta/encerrada) e,
  opcionalmente, padrão de energia desejado.
- **Competência da Vaga**: relação vaga–competência com o peso (1, 2 ou 3) daquela vaga.
- **Participação na Seleção**: relação candidato–vaga com a etapa atual (Triagem, Entrevista,
  Aprovado) e o histórico de movimentações (quem, quando, de/para).
- **Anotação de Entrevista**: texto, autor, data, candidato, vaga opcional e competências
  observadas.
- **Consentimento**: meio de obtenção, data, responsável pelo registro.
- **Registro de Exclusão**: data e responsável pela exclusão, sem dados pessoais.
- **Perfil de Energia**: posição nos três eixos, justificativa, origem (sugerido automaticamente
  ou ajustado por humano) — existe para vagas (desejado) e candidatos (sugerido).
- **Recrutador**: usuário autenticado que opera o sistema.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: O recrutador encontra os candidatos que atendem a uma combinação de 2 competências
  + tipo em menos de 10 segundos, sem abrir nenhum PDF.
- **SC-002**: O cadastro de um currículo a partir do PDF (envio, revisão e confirmação) leva
  menos de 2 minutos, e ao menos 80% das competências do catálogo presentes nos PDFs de exemplo
  são identificadas automaticamente.
- **SC-003**: O ranking de uma vaga aparece em menos de 3 segundos após abri-la, e 100% dos
  percentuais exibidos vêm acompanhados de explicação.
- **SC-004**: Em 100% das telas do contexto de triagem, nenhum nome, idade, foto, contato ou PDF
  original de candidato em Triagem é visível ou acessível.
- **SC-005**: O fluxo completo do roteiro de demonstração (painel → envio de PDF → busca → vaga →
  triagem anônima → entrevista → anotação) é executado em até 5 minutos sem erros.
- **SC-006**: O ambiente de demonstração é preparado do zero com um único comando, já com os
  dados fictícios carregados.
- **SC-007**: Sem acesso à internet ou com a análise automática desligada, 100% das Histórias 1 a
  6 continuam funcionando.
- **SC-008**: Uma anotação de entrevista é encontrada a partir do perfil do candidato em até
  3 cliques.
- **SC-009**: Uma pessoa que nunca usou o sistema identifica corretamente, só olhando a tela, se
  um candidato é servidor ou estagiário e por que recebeu determinado percentual.

## Assumptions

- **Usuários**: equipe de recrutamento da SETIC/CTIC com um único perfil (recrutador); o
  candidato não acessa o sistema no MVP (pedidos de correção e exclusão são executados pelo
  recrutador).
- **Combinação de filtros**: competências selecionadas são combinadas com "E" (o candidato
  precisa ter todas), conforme a pergunta típica "quem sabe Python **e** SQL".
- **Experiência na área**: registrada como "sim" ou "não informado", sempre com a área de
  contexto; o cálculo de anos de experiência fica fora do MVP (considerado complexo pela área
  cliente).
- **Consentimento**: como o recrutador importa PDFs recebidos por outros canais, ele registra o
  meio (ex.: e-mail, formulário, declaração do CIEE) e a data em que o candidato consentiu; sem
  esse registro o cadastro não é concluído.
- **Retenção**: 2 anos (citado pela área cliente; regra a validar), com sinalização para revisão
  e sem exclusão automática no MVP.
- **Ranking**: considera todo o banco ativo, filtrado pelo tipo de candidato da vaga.
- **Pesos**: escala curta 1/2/3 atende ao MVP, conforme reunião.
- **Leitura de currículos**: apenas PDFs com texto selecionável; competências são reconhecidas a
  partir do catálogo (com sinônimos). A análise por IA, quando disponível, apenas melhora a
  sugestão e nunca é obrigatória.
- **Dados**: somente dados fictícios na demonstração.
- **Fora do escopo do MVP**: OCR, Word e importação em lote; portal do candidato; e-mail de
  feedback; desativação automática após 2 anos; cálculo de anos de experiência; avaliação
  automática de projetos; integrações com CIEE, WhatsApp, e-mail ou login institucional;
  permissões por setor; fluxos de seleção configuráveis.
