# Requisitos principais do projeto

Considerando o desafio, a transcrição e suas anotações complementares, **o projeto deve priorizar um banco centralizado de candidatos, pesquisável por competências, que apoie a seleção conforme os critérios de cada vaga**.

Abaixo está a consolidação dos requisitos para servir de base a um futuro plano de implementação — sem entrar ainda em tecnologias ou tarefas de desenvolvimento.

## 1. Banco centralizado de candidatos — prioridade máxima

**Objetivo:** deixar de depender de currículos espalhados e da memória de quem os recebeu.

### Requisitos
- Importar currículos em PDF.
- Manter o arquivo original para consulta posterior.
- Extrair automaticamente as informações para um cadastro estruturado.
- Permitir revisar e corrigir os dados extraídos.
- Manter o candidato no banco independentemente de uma vaga específica, possibilitando seu aproveitamento em seleções futuras.

### Informações principais do cadastro
- Identificação e contato, com acesso restrito.
- Tipo: servidor ou estagiário.
- Área de atuação.
- Formação.
- Competências.
- Experiências identificadas.
- Indicação de experiência na área.
- Projetos relacionados e links, quando informados.
- Currículo original.

**Cuidado:** “não informado” deve ser diferente de “não possui”. A ausência de uma experiência ou projeto no PDF não comprova que o candidato não os tenha.

## 2. Busca e filtros — prioridade máxima

**Objetivo:** responder rapidamente a perguntas como “quais candidatos têm Python e SQL?”.

### Requisitos
- Filtrar por competência.
- Filtrar por área de atuação.
- Filtrar por servidor ou estagiário.
- Combinar filtros.
- Mostrar a quantidade de candidatos encontrados.
- Exibir um resumo com as principais competências.
- Permitir consultar o perfil completo, respeitando a triagem anônima.

A busca por nome pode existir na gestão autorizada do banco, **mas não deve revelar a identidade durante a triagem anônima**.

**Este é o principal resultado que precisa funcionar bem na demonstração.**

## 3. Cadastro de vagas com competências e pesos — prioridade alta

**Objetivo:** representar que uma mesma competência pode ter importâncias diferentes em vagas distintas.

### Requisitos
- Cadastrar a vaga, sua área e o tipo de candidato procurado.
- Associar competências à vaga.
- Diferenciar hard skills e soft skills.
- Definir um peso para cada competência naquela vaga.
- Permitir alterar esses critérios.

### Regra central
**O peso pertence à relação entre vaga e competência, não à competência isoladamente.**

Por exemplo: Python pode ter peso alto em uma vaga de desenvolvimento e peso baixo em outra.

Para o MVP, uma escala curta de pesos já atende à necessidade. Não é necessário criar um mecanismo complexo de configuração.

## 4. Aderência e ordenação por vaga — prioridade alta

**Objetivo:** facilitar a comparação inicial sem substituir a avaliação humana.

### Requisitos
- Comparar as competências do candidato com as solicitadas pela vaga.
- Considerar os pesos definidos.
- Mostrar os candidatos ordenados por aderência.
- Exibir o percentual e sua explicação.
- Mostrar competências identificadas e não identificadas.
- Respeitar o tipo de candidato procurado pela vaga.

Uma regra simples proposta para o MVP é:

> **Aderência = soma dos pesos das competências identificadas ÷ soma dos pesos das competências solicitadas × 100**

### Limites importantes
- O percentual mede correspondência com os critérios cadastrados, não a qualidade geral da pessoa.
- “Não identificado no currículo” não significa “não sabe”.
- Soft skills extraídas do currículo devem ser tratadas como **declaradas**, não comprovadas.
- Experiência e projetos não devem ganhar pontuação automaticamente sem uma regra definida.
- A aprovação ou rejeição continua sendo humana.

**Com suas anotações, os pesos passam a fazer parte do recorte recomendado**, em vez da simplificação anterior de considerar todas as competências igualmente.

## 5. Registro e recuperação das entrevistas — prioridade alta, com escopo pequeno

**Objetivo:** preservar informações que hoje se perdem depois da entrevista.

### Requisitos
- Registrar uma anotação sobre a entrevista.
- Identificar autor e data.
- Associar a anotação ao candidato e, quando houver, à vaga.
- Consultar esse histórico posteriormente.
- Registrar descobertas sobre experiência, competências, projetos e portfólio.

Para o MVP, **um campo de observações com histórico básico é suficiente**. Não são necessários questionários complexos, transcrição ou avaliação automática.

Como suas anotações indicam que algumas entrevistas ocorrem antes da definição da área, o cadastro do candidato não deve depender de sua destinação definitiva a uma vaga.

## 6. Acompanhamento da seleção — versão mínima exigida pelo fluxo do desafio

### Requisitos
- Acompanhar as etapas: triagem, entrevista e aprovado.
- Permitir mudar a etapa do candidato.
- Manter a etapa vinculada à participação naquela vaga.

**Regra importante:** estar em entrevista para uma vaga não deve alterar a situação do candidato em todas as outras.

O desafio propõe um kanban, mas ele pode ser simples. Automações e interações elaboradas não devem consumir o prazo do núcleo.

## 7. Triagem anônima e proteção dos dados — requisitos transversais

Esses pontos não devem ser descartados por falta de ênfase na reunião, pois constam no desafio.

### Requisitos
- Ocultar nome, idade, foto e contatos na triagem.
- Identificar o candidato por um código nessa etapa.
- Revelar a identidade a partir da entrevista.
- Bloquear também o acesso ao PDF original durante a triagem anônima.
- Restringir currículos e anotações a usuários autorizados.
- Prever consentimento do candidato, correção e solicitação de exclusão.
- Definir uma política de retenção.
- Usar somente dados fictícios na demonstração.

**Sobre os dois anos:** o prazo aparece nas suas anotações complementares e deve ser validado como regra de negócio. A automação desse prazo pode ficar para depois, mas a retenção não deve ser simplesmente ignorada. Além disso, **desativar um cadastro não equivale a excluir seus dados**.

## 8. Funcionalidades para depois do núcleo

| Funcionalidade | Encaminhamento |
|---|---|
| Feedback por e-mail | Registrar como evolução mencionada nas suas anotações. |
| Desativação automática após dois anos | Validar a regra antes de automatizar. |
| Cálculo de anos de experiência | Fora do MVP; a própria conversa apontou a complexidade. |
| Avaliação automática de projetos | Fora do MVP; guardar informações e observações já atende ao uso relatado. |
| Recomendação por IA | Diferencial, não dependência do ranking. |
| Análise de “padrão de energia” | Desejável no desafio, não prioridade. |
| Integração com WhatsApp, e-mail ou CIEE | Os canais de origem não implicam necessidade de integração no MVP. |

## 9. Definições pendentes antes do plano de implementação

Poucas decisões precisam ser fechadas para evitar retrabalho:

1. **Experiência em qual área?** O campo deve ter contexto, evitando um “tem experiência” genérico.
2. **Como combinar filtros de competências?** Exigir todas as selecionadas ou aceitar qualquer uma?
3. **Como registrar soft skills?** Diferenciar declaração no currículo de observação na entrevista.
4. **Como obter consentimento quando um funcionário importa o PDF?** A ação do funcionário não representa automaticamente o consentimento do candidato.
5. **O que acontece após dois anos?** Desativação, revisão, renovação ou exclusão?

## Síntese para orientar o futuro plano

O fluxo principal deve ser:

**PDF → extração e revisão → banco pesquisável → vaga com competências e pesos → aderência explicada → triagem anônima → entrevista com registro.**

O MVP estará bem direcionado se demonstrar que a equipe consegue **encontrar candidatos sem abrir cada currículo, entender a correspondência com uma vaga e recuperar o que foi descoberto nas entrevistas**. Esse é o valor principal; o restante deve apoiar esse caminho, não competir com ele.
