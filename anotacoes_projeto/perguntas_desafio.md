## Oque foi constatado a partir da reuniao

- Recebe por PDF de varios jeitos(zap email cee)
- Nao conseguem organizar anotacoes sobre entrevistas de jeito centralizado e que consiga ser acessado posteriormente
- Tem necessidade de no cadastro das vagas estipular "valor" ou "peso" de uma competencia sento ela uma Hard Skill ou Softskills de acordo com a vaga
- O pesso das competencia e de cada vaga e definido de acordo com a demanda 

- Comentou sobre ser interssante um feedback via email para os canditos aprovados ou nao

- periodo de tempo pra desconsiderar um curriculo  (Dois anos para desativar curriculo). Nao e relevante pro mvp no momento 

- Leva em consideracao as competencias do cadidato pra destinalo a uma vaga 

- No contexto da setic quando e pra area de tecnologia a entrevista acontece sem dizer pra qual area o candidato vai

- Salvar os curriculos pra acesso posterior porem abstraindo-se dele e salvando no banco relacionando ao cadidato as sequintes coisas:
    1. Competencias
    2. Se tem ou nao experiencia
    3. Se tem projetos relacionados


- DEU COMO ENFASE PRINCIPAL JUNTAR OS CURRICULOS NUM BANCO SENDO POSSIVEL ACESSAR E FILTRAR POR CADIDATO E POR COMPETENCIA

---

### Recebimento e atualização dos currículos

  

1. Quantos currículos chegam por mês? Qual a proporção entre servidores e estagiários?

2. Por quais canais chegam (WhatsApp, CIEE, e-mail, presencial)? Qual é o mais problemático?

3. Em que formatos chegam (PDF, foto, Word, link do LinkedIn)? Há currículos escaneados ou em imagem?

4. O CIEE envia listas em que formato (PDF, planilha)? Com que frequência?

5. O candidato atualiza o currículo depois? Como vocês sabem que está desatualizado?

  

### Seleção, acesso e proteção dos dados

  

6. Quais competências pesam mais na escolha? Há competências eliminatórias e outras desejáveis?

7. Hoje, como vocês comparam dois candidatos? Existe algum critério informal que todo mundo usa mas não está escrito?

8. Quem define os requisitos da vaga? Eles costumam ser claros ou vagos?

9. Quantas pessoas participam de uma seleção? Quem precisa ver o andamento?

10. O que acontece com quem não foi escolhido? Vocês guardam para vagas futuras?

11. Por quanto tempo vocês acham razoável guardar um currículo? Há norma interna sobre isso?

12. Quem poderia acessar o banco de talentos?

13. A SETIC permite o uso de IA sobre currículos? Há restrição de enviar dados para serviços externos?

14. Se o sistema resolvesse uma única coisa, qual seria?

  

---

  

## Perguntas adicionais — foco em um MVP de curto prazo

  

O objetivo destas perguntas é fechar o menor escopo que demonstre o valor da solução no prazo disponível, identificando dependências, simplificações aceitáveis e funcionalidades que podem ficar para depois. As propostas abaixo precisam ser validadas com o cliente; não representam cortes já aprovados.

  

As perguntas em **negrito** devem ser priorizadas se o tempo da conversa for curto.

  

### 1. Prazo, entrega e critérios de sucesso — perguntar primeiro


2. Das funcionalidades descritas no desafio, quais precisam obrigatoriamente funcionar na apresentação e quais podem ficar como evolução futura?

3. **Qual fluxo completo precisamos demonstrar para vocês considerarem o MVP bem-sucedido?**

4. Se o prazo não permitir entregar tudo, o que podemos simplificar ou retirar primeiro?
  

### 2. Entrada dos currículos e leitura automática

  

7. **Podemos limitar o MVP ao envio manual de PDFs com texto selecionável, deixando fotos, documentos escaneados e Word para depois?**

8. É necessário que o próprio candidato envie o currículo ou um responsável pode fazer o cadastro no MVP?

9. Quais informações precisam ser extraídas automaticamente na primeira versão? Formação, experiências e competências são suficientes?

10. Se a leitura automática falhar ou identificar informações incorretas, podemos permitir revisão e correção manual, sem exigir que a extração acerte todos os casos?

11. Para a demonstração, podemos cadastrar os currículos individualmente e deixar importação em lote e tratamento de duplicidades para uma próxima etapa? Resposta

  

### 3. Vagas, competências e ranking

  

12. **Podemos usar uma lista predefinida de competências nas vagas e nos currículos, evitando inicialmente interpretar qualquer descrição livre?**

13. Como sao calculadas o peso das competências ? !!!!!!!!

14. O ranking deve considerar todo o banco ou apenas candidatos vinculados àquela vaga? Quem faz esse vínculo?

15. vc ve a necessidade de uma comparacao entre candidatos?

  

### 4. Acompanhamento e anonimato

  

18. Podemos manter apenas as etapas fixas “triagem”, “entrevista” e “aprovado”, sem configuração de fluxos?

19. Precisamos de um kanban com arrastar e soltar ou uma troca de etapa por botão atende ao MVP?

20. A etapa de seleção deve ser independente por vaga, permitindo que a mesma pessoa participe de seleções diferentes?

21. **Durante a triagem anônima, o PDF original também deve ficar indisponível, já que pode revelar nome, foto e contatos?**

22. Quem pode mover um candidato para entrevista e, consequentemente, liberar sua identidade? Precisamos registrar quem realizou essa ação?

  

### 5. Dependências e proteção dos dados

  

23. **Existe alguma integração externa sem a qual o MVP não será aceito, ou podemos operar de forma independente de CIEE, WhatsApp e sistemas internos?**

24. Caso alguma integração seja obrigatória, quem fornecerá documentação, acesso de teste e suporte, e até quando?

25. Quem disponibilizará os currículos fictícios, as vagas de exemplo e os resultados esperados para validarmos a demonstração?

26. Haverá infraestrutura, internet e orçamento disponíveis para hospedagem e eventual serviço de IA? Podemos evitar depender desses serviços durante a apresentação?

27. Para o MVP, contas internas de recrutadores são suficientes ou é obrigatório integrar o acesso ao login institucional?

28. **Se um responsável cadastrar um currículo recebido por outro canal, como será obtido e registrado o consentimento exigido pelo desafio?**

29. Quem aprovará o aviso de privacidade, o prazo de retenção e o procedimento de exclusão antes de qualquer uso com dados reais?

30. O pedido de exclusão pode ser atendido por um responsável no MVP ou o candidato precisa conseguir realizá-lo sozinho?

  

### 6. Cortes explícitos de escopo

  

31. **Podemos deixar a análise de “padrão de energia” totalmente fora do MVP, inclusive sem telas ou integrações preparatórias?**

32. Podemos deixar para depois notificações, agendamento de entrevistas, mensagens aos candidatos e relatórios gerenciais?

33. Podemos adiar portal do candidato, atualização autônoma de currículo e recuperação de senha desse público?

34. Podemos começar com um único fluxo de seleção e um perfil de recrutador, sem permissões diferentes por setor, caso isso atenda à confidencialidade necessária?

35. **Depois de aprovarmos o escopo, podemos combinar que toda nova funcionalidade exigirá retirar outra ou ficará para depois da apresentação?**

  

## Proposta de recorte do MVP — sujeita à validação

  

| Manter no fluxo principal | Simplificar, com aprovação | Deixar para depois |

|---|---|---|

| Upload e leitura de currículo | Apenas PDF com texto | OCR, Word e importação em lote |

| Cadastro de vagas e competências | Campos mínimos e competências padronizadas | Regras complexas e pesos configuráveis |

| Ranking com explicação | Cálculo determinístico simples, sem exigir IA | Recomendações avançadas |

| Diferenciação entre servidor e estagiário | Tipo informado no cadastro | Integrações institucionais |

| Cards, filtros e acompanhamento | Etapas fixas; validar necessidade de arrastar e soltar | Fluxos personalizáveis |

| Triagem anônima | Bloquear também acesso ao original na triagem | Anonimização automática do PDF |

| Demonstração com dados fictícios | Poucos casos representativos | Migração do acervo real |

| Proteção dos dados | Controles proporcionais ao uso previsto | Não tratar LGPD como item dispensável |

  

**Decisão central:** separar “MVP demonstrável” de “sistema pronto para operar com dados reais”. A segunda opção traz dependências de acesso, infraestrutura, privacidade e operação que podem inviabilizar um prazo de hackathon. A demonstração deve utilizar apenas dados fictícios, conforme o desafio.

  

Ao encerrar a conversa, registrar o fluxo mínimo aprovado, o que ficará fora, os responsáveis e prazos de cada dependência e quem validará a entrega. Funcionalidades novas devem substituir outra de esforço compatível, mediante aprovação, ou entrar como evolução futura.
