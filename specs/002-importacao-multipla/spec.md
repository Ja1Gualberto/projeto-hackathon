# Feature Specification: Importação múltipla, comparação com o PDF e leitura blindada

**Feature Branch**: `002-importacao-multipla`

**Created**: 2026-11-02

**Status**: Draft

**Input**: User description: "Melhorias na tela de importação: importar vários PDFs de uma vez e,
ao clicar em 'Ler currículo', abrir uma modal informando que vários currículos foram importados e
perguntando se o usuário quer revisar um por um ou cadastrar direto, sem revisão manual. Na revisão,
permitir comparar o PDF com os dados do card (botão 'Abrir PDF' ou pré-visualização ao lado do card,
escolhendo a alternativa mais viável). Melhorar a leitura dos dados: currículos reais em layout de
colunas perderam nome completo, formação, projetos e telefone. Objetivo: produto excelente para o
pitch."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Leitura correta de currículos com layout elaborado (Priority: P1)

Como recrutador, ao enviar um currículo feito em ferramenta de design (colunas, barra lateral,
títulos estilizados), quero que o sistema preencha corretamente o nome completo, os contatos, a
formação, as experiências e os projetos, para não precisar redigitar o que está no PDF.

**Why this priority**: é o coração da promessa do produto ("o sistema lê o currículo"). Na
demonstração, um currículo com campos vazios ou nome cortado derruba a credibilidade. Sem esta
história, a importação em lote só multiplica erros.

**Independent Test**: enviar um currículo em duas colunas, com o texto gravado fora da ordem visual,
e conferir na revisão que cada campo traz o conteúdo da seção certa.

**Acceptance Scenarios**:

1. **Given** um currículo com o nome em duas linhas ("Maria" / "Clara Souza") na barra lateral,
   **When** ele é lido, **Then** o nome completo vem como "Maria Clara Souza".
2. **Given** um currículo cuja seção de formação se chama "Educação", **When** ele é lido, **Then**
   a formação traz as instituições e os cursos dessa seção.
3. **Given** um currículo em duas colunas, **When** ele é lido, **Then** o conteúdo de uma coluna
   não se mistura ao da outra e os títulos de seção ficam junto do próprio conteúdo.
4. **Given** itens rotulados "Projeto: ..." ou "Projetos: ..." dentro da seção de experiência,
   **When** o currículo é lido, **Then** eles aparecem em Projetos e as experiências seguintes
   continuam em Experiências.
5. **Given** um currículo com seção "Certificados", **When** ele é lido, **Then** os certificados
   aparecem em Experiências, junto do estágio ou emprego.
6. **Given** um telefone escrito "(69) 9 9988-7766", **When** o currículo é lido, **Then** o
   telefone vem como "(69) 99988-7766".
7. **Given** um parágrafo quebrado em várias linhas no PDF, **When** ele é lido, **Then** aparece
   como um único item, sem cortar palavras nem perder conteúdo.
8. **Given** uma frase de habilidade como "Experiência auxiliando alunos", **When** o currículo é
   lido, **Then** ela não é confundida com o título da seção de experiências.

---

### User Story 2 - Comparar o PDF com os dados durante a revisão (Priority: P1)

Como recrutador, ao revisar um currículo, quero ver o PDF ao lado dos dados lidos, para conferir e
corrigir sem trocar de aba.

**Why this priority**: é o que torna a revisão rápida e confiável, e é visualmente marcante no
pitch. Também sustenta o princípio de decisão humana: o recrutador confere a fonte.

**Independent Test**: importar um currículo e verificar que a tela de revisão mostra o PDF ao lado
do formulário em telas largas e oferece "Abrir PDF" em qualquer tamanho de tela.

**Acceptance Scenarios**:

1. **Given** a tela de revisão em um monitor ou projetor, **When** ela abre, **Then** o PDF enviado
   aparece ao lado do formulário e continua visível enquanto o recrutador rola os campos.
2. **Given** a tela de revisão em qualquer tamanho, **When** o recrutador usa "Abrir PDF", **Then**
   o documento abre em nova aba, como nas demais telas do sistema.
3. **Given** um visitante sem sessão ou uma importação de outra sessão, **When** tenta acessar o
   PDF temporário, **Then** o acesso é negado.
4. **Given** uma importação já confirmada ou descartada, **When** o PDF temporário é solicitado,
   **Then** ele não está mais disponível.

---

### User Story 3 - Importar vários currículos e escolher como prosseguir (Priority: P2)

Como recrutador com vários currículos em mãos, quero enviar todos de uma vez e decidir se reviso um
por um ou se cadastro direto, para ganhar tempo sem perder o controle.

**Why this priority**: é o ganho de produtividade mais visível para quem recebe currículos em
volume, mas depende de a leitura (US1) e a revisão (US2) estarem confiáveis.

**Independent Test**: enviar três PDFs, escolher "Revisar um por um" e percorrer a fila; repetir
escolhendo "Cadastrar sem revisão" e conferir o resultado de cada currículo.

**Acceptance Scenarios**:

1. **Given** a tela de importação, **When** o recrutador seleciona vários PDFs, **Then** vê quais
   arquivos foram selecionados antes de enviar.
2. **Given** um único PDF selecionado, **When** clica em "Ler currículo", **Then** vai direto para a
   revisão, como hoje, sem modal.
3. **Given** vários PDFs selecionados, **When** clica em "Ler currículo", **Then** uma modal informa
   quantos currículos foram lidos e pergunta se deseja revisar um por um ou cadastrar sem revisão.
4. **Given** a modal aberta, **When** escolhe "Revisar um por um", **Then** abre a revisão do
   primeiro currículo, com a indicação "Currículo 1 de N"; ao confirmar, segue para o próximo; ao
   terminar, vê o resumo do lote.
5. **Given** a modal aberta, **When** escolhe "Cadastrar sem revisão" sem informar o consentimento,
   **Then** nada é salvo e o sistema pede como e quando os candidatos consentiram.
6. **Given** a modal aberta, **When** informa o consentimento e confirma, **Then** os currículos em
   condições são cadastrados com os dados lidos, e o resumo mostra o que foi salvo e o que precisa
   de revisão, com o motivo.
7. **Given** um currículo do lote digitalizado, sem nome, sem área identificada ou com possível
   duplicidade, **When** o cadastro sem revisão é executado, **Then** esse currículo não é salvo nem
   sobrescreve cadastros existentes: fica pendente para revisão manual.
8. **Given** um currículo pendente no resumo do lote, **When** o recrutador o descarta, **Then** o
   PDF temporário é apagado e nada é salvo.

### Edge Cases

- Mais de 10 arquivos selecionados: o envio é recusado com mensagem clara e nenhum arquivo é guardado.
- Um dos arquivos não é PDF ou passa de 10 MB: o lote é recusado indicando qual arquivo corrigir.
- Dois PDFs do lote com o mesmo e-mail ou CPF: no cadastro sem revisão, o segundo fica pendente como
  possível duplicidade.
- Modal fechada sem escolher: o recrutador permanece no resumo do lote e pode escolher depois.
- Sessão expirada no meio do lote: o sistema explica que a importação não está mais disponível e
  oferece novo envio.
- Falha ao salvar um currículo do lote: os demais prosseguem e o que falhou fica pendente.
- PDF sem texto selecionável ou corrompido: continua com o preenchimento manual, com a pré-visualização
  disponível sempre que o navegador conseguir exibir o arquivo.
- Tela estreita: a pré-visualização dá lugar ao botão "Abrir PDF", sem rolagem horizontal.
- PDF cuja ordem visual não pode ser reconstruída: o sistema usa a ordem gravada no arquivo, como hoje.

## Requirements *(mandatory)*

### Functional Requirements

**Leitura do currículo**

- **FR-001**: O sistema MUST reconstruir a ordem visual de leitura do PDF (colunas, blocos lado a
  lado e textos fora de ordem no arquivo) antes de interpretar as seções.
- **FR-002**: O sistema MUST recuperar todo o texto selecionável visível, inclusive contatos que hoje
  se perdem na leitura.
- **FR-003**: Quando a reconstrução não for confiável, o sistema MUST recorrer à leitura pela ordem
  gravada no arquivo, sem falhar.
- **FR-004**: O sistema MUST identificar o nome completo mesmo quando dividido em mais de uma linha, e
  MUST NOT usar um cargo ou título de seção como nome.
- **FR-005**: O sistema MUST reconhecer telefones brasileiros com o nono dígito separado.
- **FR-006**: O sistema MUST reconhecer "Educação" e variações como seção de formação, e MUST NOT
  tratar como título de seção uma frase que apenas começa com a mesma palavra.
- **FR-007**: O sistema MUST levar para Projetos os itens rotulados "Projeto:"/"Projetos:" dentro de
  outras seções e, ao fim do item, voltar à seção em que estava.
- **FR-008**: O sistema MUST incluir a seção de certificados em Experiências.
- **FR-009**: O sistema MUST unir em um item as linhas quebradas de um mesmo parágrafo, associar datas
  e durações soltas ao item a que pertencem e dividir, sem perda de conteúdo, o que exceder o tamanho
  aceito por item.

**Comparação com o PDF**

- **FR-010**: A revisão MUST exibir a pré-visualização do PDF ao lado do formulário em telas largas,
  mantendo-a visível durante a rolagem.
- **FR-011**: A revisão MUST oferecer "Abrir PDF" em nova aba em qualquer tamanho de tela.
- **FR-012**: O PDF temporário MUST ser acessível apenas por usuário autenticado na mesma sessão da
  importação, sem armazenamento em cache, e deixar de existir ao confirmar ou descartar.

**Importação múltipla**

- **FR-013**: A tela de importação MUST aceitar de 1 a 10 PDFs por envio, cada um com até 10 MB, e
  mostrar os arquivos selecionados.
- **FR-014**: O tipo de candidato escolhido MUST valer para todos os currículos do envio, podendo ser
  ajustado na revisão manual.
- **FR-015**: Com um único PDF, o sistema MUST manter o fluxo atual, direto para a revisão.
- **FR-016**: Com vários PDFs, o sistema MUST abrir, após a leitura, uma modal com a quantidade lida,
  quantos estão aptos ao cadastro direto e as opções "Revisar um por um" e "Cadastrar sem revisão".
- **FR-017**: A revisão um por um MUST indicar a posição na fila ("Currículo 2 de 5"), permitir pular
  um currículo, seguir para o próximo pendente ao confirmar e terminar no resumo do lote.
- **FR-018**: O cadastro sem revisão MUST exigir um único registro de consentimento (meio e data),
  aplicado a todos os cadastrados e atribuído ao usuário atual.
- **FR-019**: O cadastro sem revisão MUST salvar somente currículos lidos, com nome e área
  identificados, que passem nas mesmas validações da revisão e não tenham possível duplicidade; os
  demais MUST ficar pendentes com o motivo, sem sobrescrever cadastros existentes.
- **FR-020**: O sistema MUST mostrar um resumo do lote com a situação de cada currículo, link para o
  perfil dos cadastrados e, para os pendentes, as ações "Revisar" e "Descartar".
- **FR-021**: Um lote MUST ficar restrito à sessão de quem o enviou e nenhum dado MUST ir para o banco
  antes da confirmação ou do cadastro sem revisão.

### Key Entities

- **Lote de importação**: conjunto temporário de currículos enviados juntos, restrito à sessão do
  recrutador. Guarda a ordem dos arquivos e a situação de cada um.
- **Item do lote**: um currículo enviado, com nome do arquivo e situação (pendente ou cadastrado).
  Quando cadastrado, aponta para o candidato; quando pendente, pode trazer o motivo.
- **Importação**: entidade temporária já existente. Mantém o PDF, o texto lido e os dados extraídos
  até a confirmação ou o descarte.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Nos dois currículos reais usados como referência, todos os campos apontados como
  incorretos (nome, telefone, formação, projetos e experiências) vêm preenchidos corretamente, sem
  edição manual.
- **SC-002**: Em um currículo em duas colunas, 100% das seções trazem somente conteúdo da própria seção.
- **SC-003**: Todos os currículos que eram lidos corretamente continuam com o mesmo resultado (nenhuma
  regressão nos casos de referência existentes).
- **SC-004**: O recrutador envia 5 currículos e conclui o cadastro sem revisão em menos de 1 minuto.
- **SC-005**: O recrutador compara o PDF com os dados sem sair da tela de revisão em monitores e
  projetores a partir de 1280 px de largura.
- **SC-006**: A leitura de um lote de 10 currículos típicos termina em menos de 15 segundos.
- **SC-007**: Nenhum candidato é cadastrado sem consentimento registrado em nenhum dos fluxos.

## Assumptions

- **Escopo**: a importação em lote era fora do MVP "salvo decisão explícita"; o pedido do usuário é
  essa decisão. OCR e Word continuam fora.
- **Alternativa escolhida**: pré-visualização ao lado do formulário como recurso principal, com "Abrir
  PDF" como recurso sempre disponível. O custo é baixo porque o próprio navegador exibe o arquivo, e a
  comparação é mais rápida e marcante no pitch do que trocar de aba.
- **LGPD**: "sem revisão manual" não dispensa o consentimento, regra inegociável do projeto. Por isso
  ele é pedido uma vez, na modal, para todos os currículos do lote.
- **Duplicidade**: o cadastro sem revisão nunca atualiza nem cria cadastro em caso de possível
  duplicidade; a decisão fica com o recrutador.
- **Certificados**: ficam em Experiências, conforme pedido. Cursos complementares continuam em
  Formação, como já funcionava.
- **Limite**: 10 arquivos por envio, suficiente para o uso e a demonstração e seguro para a sessão.
- **Dados da demonstração**: testes e seeders usam apenas dados fictícios. Os currículos reais servem
  só para conferência local e não entram no repositório.
- **Dependências**: nenhuma biblioteca nova; a leitura por posição usa o leitor de PDF já instalado.
