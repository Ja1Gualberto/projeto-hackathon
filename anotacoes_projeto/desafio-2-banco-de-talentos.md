# Desafio 2 · Banco de Talentos

**Hackathon SETIC — Superintendência Estadual de Tecnologia da Informação e Comunicação (SETIC)**

*Um só lugar para guardar currículos e achar a pessoa certa para cada vaga*

## O desafio

> Quando surge uma vaga, como encontrar e comparar rapidamente os servidores e estagiários com o perfil certo, se hoje os currículos estão espalhados por vários lugares?

A ideia é criar um banco de talentos: um sistema onde todos os currículos ficam guardados em um só lugar, organizados e fáceis de pesquisar. Quem está selecionando cadastra a vaga, e o sistema mostra quais candidatos têm o perfil mais próximo do que ela pede, separando servidores de estagiários.

## Como funciona hoje

Não existe banco de talentos. Cada currículo chega por um caminho diferente e fica onde caiu:

- no WhatsApp, enviado direto por quem está se candidatando;
- em drives pessoais de quem recebeu o arquivo;
- em listas enviadas pelo CIEE, no caso dos estagiários.

Na prática, quando aparece uma vaga, alguém precisa lembrar onde estão os currículos, abrir um por um e comparar de cabeça ou em anotações soltas.

## O problema

Com os currículos espalhados, a seleção fica lenta e depende da memória de quem recebeu cada arquivo. Três coisas ficam difíceis:

- **Encontrar:** não dá para pesquisar, por exemplo, “quem sabe Python e já trabalhou com banco de dados”.
- **Comparar:** cada currículo tem um formato, então colocar dois candidatos lado a lado dá trabalho.
- **Acompanhar:** não há uma visão única de quem está em cada etapa da seleção.

## Restrições

- **Dados pessoais (LGPD):** currículos têm dados pessoais de servidores e estagiários. A solução precisa pedir o consentimento do candidato, guardar os dados só pelo tempo necessário e permitir que ele peça a exclusão.

## Como seria o sucesso

Uma boa solução reúne os currículos em um só lugar e faz o trabalho pesado da seleção: ler, organizar, comparar e acompanhar. Na prática, o caminho seria este:

**1. Currículo entra → 2. Sistema lê → 3. Vaga cadastrada → 4. Candidatos ranqueados → 5. Seleção acompanhada**

- **Leitura automática do currículo:** a pessoa envia o PDF e o sistema extrai sozinho a formação, as experiências e as competências, transformando tudo em campos que podem ser pesquisados. Ninguém precisa digitar o currículo de novo.
- **Score de aderência à vaga:** a vaga lista as competências que pede. O sistema compara com as competências de cada candidato e mostra um percentual com o motivo. Exemplo: “80% — tem 4 das 5 competências; falta inglês intermediário”.
- **Visualização organizada:**
  - cards com um resumo de cada candidato e tags das principais competências;
  - filtros por competência, área e tipo (servidor ou estagiário);
  - kanban para acompanhar em que etapa cada candidato está: triagem, entrevista ou aprovado.
- **Triagem anônima:** na primeira etapa, nome, idade e foto ficam ocultos. Assim a escolha inicial se baseia só nas competências, o que reduz vieses. Os dados aparecem a partir da etapa de entrevista.

## Exemplo de uso

Uma área precisa de um estagiário de desenvolvimento. A pessoa responsável cadastra a vaga pedindo, por exemplo, Python, SQL, Git, inglês básico e trabalho em equipe. O sistema mostra em segundos os estagiários do banco ordenados pelo score, cada um com um card anônimo e a explicação do percentual. Ela move os três melhores para “entrevista” no kanban; só então os nomes aparecem.

## Desejável (diferencial, não obrigatório)

**Análise do padrão de energia com IA.** Além das competências técnicas, a vaga pode indicar o perfil comportamental que procura, chamado aqui de padrão de energia. A IA analisa o currículo (e outras informações que a equipe julgar adequadas) e indica o quanto o candidato se aproxima desse perfil.

O padrão de energia pode ser descrito por eixos como:

- mais dinâmico ou mais analítico;
- mais individual ou mais em equipe;
- mais voltado à execução ou ao planejamento.

Exemplo: uma vaga de suporte pode pedir alguém mais dinâmico, de equipe e de execução; uma vaga de arquitetura de sistemas, alguém mais analítico e de planejamento.

## Direcionamentos definidos

- **Público:** servidores e estagiários. O sistema precisa diferenciar os dois, por exemplo, com filtros e indicação visual.
- **Tecnologia:** livre, sem restrição de linguagem, banco ou ferramenta.

---

## Orientações gerais aplicáveis ao desafio

### Critérios de avaliação (comuns aos três desafios)

Os jurados vão observar:

1. Aplicabilidade real nos sistemas da SETIC.
2. Facilidade de integração com os sistemas existentes.
3. Clareza para o usuário final.
4. Qualidade da demonstração com dados de exemplo.

### Para todos os desafios: LGPD

Os três desafios lidam com dados pessoais. Toda solução deve respeitar a Lei Geral de Proteção de Dados (LGPD). Na demonstração, usem apenas dados fictícios.
