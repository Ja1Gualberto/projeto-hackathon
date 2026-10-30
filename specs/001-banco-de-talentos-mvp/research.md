# Research: Banco de Talentos SETIC — MVP

**Feature**: `001-banco-de-talentos-mvp` | **Date**: 2026-10-30 | **Plan**: [plan.md](./plan.md)

Este documento resolve as incertezas técnicas do *Technical Context* e registra cada decisão no
formato **Decisão / Racional / Alternativas consideradas**. Nenhum item permanece como
"NEEDS CLARIFICATION".

---

## R1. Arquitetura da interface

- **Decisão**: aplicação monolítica Laravel renderizada no servidor com Blade + Tailwind CSS v4
  (Vite), formulários HTML tradicionais e um `resources/js/app.js` pequeno, em JavaScript puro,
  para melhorias progressivas (autoenvio de filtros, filtro de chips, arrastar e soltar no kanban,
  `<dialog>` de confirmação).
- **Racional**: é o stack já instalado e exigido pela constituição; não adiciona dependências;
  funciona offline; cada tela é testável com testes de feature HTTP.
- **Alternativas consideradas**: Livewire/Inertia/Vue (novas dependências e curva de
  aprendizado no prazo de hackathon); Alpine.js (conveniente, mas é dependência nova para ganho
  pequeno — `<dialog>` nativo e ~150 linhas de JS cobrem o necessário).

## R2. Framework de testes

- **Decisão**: adicionar `phpunit/phpunit` como dependência de desenvolvimento (versão compatível
  com Laravel 13, resolvida pelo `composer require --dev`), com `phpunit.xml`, `tests/TestCase.php`
  e as pastas `tests/Feature` e `tests/Unit` do padrão Laravel. Banco SQLite em memória
  (`RefreshDatabase`), `Storage::fake('curriculos')` e implementação falsa de `AnalisadorEnergia`.
- **Racional**: o projeto foi entregue "sem testes iniciais" e não tem runner instalado, mas a
  constituição exige testes de feature para aderência, ocultação de identidade e bloqueio do PDF
  antes de considerar uma história pronta. PHPUnit é o padrão do Laravel e é uma única
  dependência.
- **Alternativas consideradas**: Pest (mais pacotes; `composer.json` já permite o plugin, mas não
  há ganho funcional para o MVP); não testar (viola o gate da constituição).

## R3. Leitura de texto do PDF

- **Decisão**: `smalot/pdfparser` (PHP puro), encapsulado em `App\Services\Curriculo\LeitorPdf`
  (`extrairTexto(string $caminho): ?string`). Exceções de arquivo corrompido são capturadas e
  tratadas como "não foi possível ler". Texto com menos de 80 caracteres não brancos é tratado como
  "sem texto legível" (PDF digitalizado).
- **Racional**: funciona igual no container Linux e em Windows sem Docker, sem binários de
  sistema; atende PDFs com texto selecionável (escopo do MVP). Isolar a biblioteca em uma classe
  permite testar a extração sobre texto puro.
- **Alternativas consideradas**: `spatie/pdf-to-text` + `pdftotext` (poppler) — melhor fidelidade
  de layout, mas exige binário no Dockerfile **e** no host Windows; IA para leitura do PDF — viola
  o princípio V (funcionamento offline) e envia dados pessoais para fora.
- **Risco**: layouts em duas colunas podem embaralhar a ordem do texto. Mitigação: PDFs de
  demonstração em coluna única, testados antes do pitch; a revisão humana corrige o restante.

## R4. Extração estruturada (regras + catálogo)

- **Decisão**: `ExtratorCurriculo::extrair(string $texto): DadosExtraidos`, determinístico, em
  etapas:
  1. **Normalização**: cópia em minúsculas e sem acentos (`Str::ascii`) para casamento; o texto
     original é mantido para exibição.
  2. **Identificação**: e-mail (regex), telefone brasileiro, CPF (com validação dos dígitos
     verificadores), data de nascimento (`dd/mm/aaaa` perto de "nascimento"), links (`https?://`,
     `github.com/…`, `linkedin.com/in/…`) e nome (primeira linha com 2–6 palavras alfabéticas que
     não seja título de seção, e-mail ou telefone).
  3. **Seções**: divisão por cabeçalhos conhecidos (formação/escolaridade, experiência
     profissional, projetos, competências/habilidades/conhecimentos, idiomas, cursos). Cada linha
     não vazia de uma seção vira um item de `formacoes`, `experiencias` ou `projetos`.
  4. **Competências**: para cada item do catálogo e cada sinônimo, regex com fronteiras que
     aceitam símbolos (`(?<![a-z0-9])c\#(?![a-z0-9])`, `node\.js`, `.net`), no texto inteiro.
     Cada achado guarda o trecho em que apareceu (usado na revisão). Um sinônimo pode implicar
     mais de uma competência (ex.: "PostgreSQL" → PostgreSQL e SQL).
  5. **Idiomas**: "inglês" + nível na mesma linha (básico/intermediário/avançado/fluente) →
     competência do nível correspondente; "inglês" sem nível → "Inglês básico".
  6. **Sugestões**: termos da seção de competências (separados por vírgula, ponto e vírgula ou
     marcador) que não casam com o catálogo viram `sugestoes_competencias` — nunca são
     cadastrados automaticamente.
- **Racional**: atende FR-009/FR-014 e a meta de ≥ 80% das competências do catálogo
  identificadas nos PDFs de exemplo (SC-002) sem IA; é reprodutível e testável com strings.
- **Alternativas consideradas**: IA para extração (dependência de rede e envio de dados
  pessoais); NLP/NER em PHP (sem bibliotecas maduras; complexidade desproporcional).

## R5. Área sugerida e "experiência na área"

- **Decisão**: cada competência *hard* do catálogo tem uma área principal opcional. A área
  sugerida é a mais frequente entre as competências encontradas (empate → ordem do enum).
  "Experiência na área" é sugerida como **sim** quando a seção de experiência existe e contém ao
  menos uma competência da área sugerida; caso contrário, **não informado**. O recrutador confirma
  as duas na revisão.
- **Racional**: regra simples, explicável e alinhada à reunião ("só colocar se tem experiência ou
  não na área"); anos de experiência ficam fora do MVP.
- **Alternativas consideradas**: dicionário de palavras-chave por área em configuração (mais um
  artefato para manter); cálculo de anos (fora do escopo, considerado complexo pela área
  cliente).

## R6. Fluxo de importação em duas etapas

- **Decisão**: `POST /candidatos/importar` valida o arquivo, grava-o em
  `curriculos/tmp/{uuid}.pdf`, roda a extração e guarda o resultado na sessão
  (`importacoes.{uuid}`), redirecionando para `GET /candidatos/importar/{uuid}` (tela de revisão,
  padrão PRG). A confirmação (`POST /candidatos/importar/{uuid}/confirmar`) cria o candidato ou
  atualiza o existente (modo `novo`/`atualizar`), move o PDF para `curriculos/{uuid}.pdf` e limpa
  a sessão. A checagem de duplicidade (e-mail ou CPF) ocorre ao montar a revisão e é refeita na
  confirmação.
- **Racional**: a revisão sobrevive a recarregamento e a erros de validação; nenhum candidato
  "rascunho" é criado sem consentimento (FR-011); o PDF ilegível também segue para revisão
  manual (edge case).
- **Alternativas consideradas**: tabela de importações (persistência a mais sem necessidade);
  renderizar a revisão direto no POST (quebra no F5 e na volta de validação).
- **Observação**: arquivos temporários de importações abandonadas podem ser limpos por um
  comando simples depois do MVP; não afetam a demonstração.

## R7. Cálculo de aderência, desempate e níveis de idioma

- **Decisão**: `CalculadoraAderencia` (classe pura, sem banco) recebe os requisitos da vaga
  (competência + peso) e o conjunto de competências do candidato e devolve `ResultadoAderencia`
  com `percentual`, `pesoAtendido`, `pesoTotal`, `pesoEssencialAtendido`, `atendidas[]` (com
  origem) e `naoIdentificadas[]` (com peso).
  - Fórmula (FR-017): `round(Σ pesos atendidos ÷ Σ pesos da vaga × 100)`, arredondamento
    *half up* para inteiro.
  - Atendimento: o candidato tem a mesma competência **ou**, para competências com `grupo` e
    `nivel` (somente idiomas), uma do mesmo grupo com nível ≥ ao pedido (quem tem "Inglês
    avançado" atende "Inglês básico").
  - Toda origem conta (declarada, adicionada na revisão, observada em entrevista); a origem é
    exibida na explicação.
  - Ordenação (US3-5): percentual ↓, peso essencial atendido ↓, data de cadastro ↑, id ↑.
  - Explicação: "80% — tem 4 de 5 competências; não identificado: Inglês intermediário (peso 2)";
    100% → "tem todas as 5 competências"; 0% → "nenhuma competência solicitada foi identificada
    no currículo".
- **Racional**: implementa literalmente a fórmula da constituição; o cálculo é feito na leitura,
  então qualquer alteração de vaga ou perfil se reflete imediatamente (FR-020) sem cache a
  invalidar. Para centenas de candidatos o custo em PHP é desprezível (SC-003).
- **Alternativas consideradas**: calcular em SQL (menos legível, difícil de explicar e testar);
  armazenar o score (exige recálculo em gatilhos); tratar idiomas como competência única sem
  nível (o exemplo do desafio fala em "inglês intermediário"); níveis para todas as competências
  (YAGNI).

## R8. Participação na seleção (triagem "virtual")

- **Decisão**: a coluna **Triagem** de uma vaga é calculada: todos os candidatos elegíveis
  (ativos e do tipo aceito pela vaga) sem participação ou com participação em `triagem`. A linha
  em `participacoes` é criada na primeira movimentação (upsert por `vaga_id + candidato_id`), e
  cada mudança gera um registro em `movimentacoes_etapa` (quem, quando, de/para).
- **Racional**: novos candidatos aparecem automaticamente nas vagas abertas compatíveis (edge
  case) sem sincronização; trocar o tipo da vaga ou desativar candidatos não deixa linhas
  órfãs; a etapa pertence à relação candidato–vaga (FR-022).
- **Alternativas consideradas**: materializar participações ao criar vaga/candidato (precisa
  manter sincronia em vários eventos e tratar mudanças de tipo).
- **Regras derivadas**: vaga encerrada não aceita movimentações e o quadro fica somente leitura;
  candidato desativado sai da Triagem, mas permanece visível (esmaecido, sem ações) em
  Entrevista/Aprovado para preservar o histórico; candidato excluído some por `cascade`.

## R9. Anonimização por contexto

- **Decisão**:
  - O contexto da vaga usa rotas pelo **código** (`/vagas/{vaga}/candidatos/{candidato:codigo}`),
    nunca pelo id; as telas de triagem não contêm links para `/candidatos/{id}`.
  - `VisibilidadeCandidatoService::perfilNaVaga(Vaga, Candidato): PerfilNaVaga` monta um DTO
    somente leitura. Em `triagem`, os campos de identidade, contato, idade, links, PDF e
    anotações ficam **nulos** — a view não recebe o dado, então não há como "vazar" por descuido
    de template. A matriz completa está em [contracts/visibilidade-por-contexto.md](./contracts/visibilidade-por-contexto.md).
  - O PDF no contexto da vaga (`…/curriculo`) responde **403** em triagem, inclusive por acesso
    direto (FR-023).
  - Itens livres exibidos na triagem (formação, experiências, projetos) passam por limpeza de
    e-mails, telefones, CPF e URLs.
  - O `Candidato` declara `$hidden` para os campos pessoais, como defesa adicional em
    serialização.
- **Racional**: cumpre o princípio IV (inegociável) com garantia estrutural e testável
  (`assertDontSee` de nome/e-mail/telefone e 403 no PDF).
- **Limitação conhecida (aceita pela spec)**: com um único perfil de recrutador, a gestão do
  banco mostra nomes. O anonimato vale **dentro do contexto da triagem**, e a gestão do banco
  nunca exibe os códigos de triagem, o que impede o cruzamento pela interface. Separação por
  papéis fica para depois do MVP.
- **Rotas aninhadas**: como a participação é virtual, essas rotas usam
  `->withoutScopedBindings()` (o Laravel limitaria o filho ao relacionamento do pai e devolveria
  404 para quem ainda está em triagem).

## R10. Código anônimo do candidato

- **Decisão**: código estável por candidato no formato `CAND-NNNN`, com 4 dígitos **aleatórios**
  (não sequenciais) e únicos, gerado na criação. Exibido apenas no contexto das vagas.
- **Racional**: segue a entidade da spec ("código anônimo estável"); aleatório para não revelar
  ordem de cadastro; não aparece na gestão do banco.
- **Alternativas consideradas**: código por participação (mais forte contra reconhecimento entre
  vagas, mas exige participações materializadas — fica como endurecimento futuro); sequencial
  (revela ordem de chegada).

## R11. Autenticação

- **Decisão**: login próprio e mínimo com sessão (`Auth::attempt`), `LoginController`
  (create/store/destroy), `throttle:5,1` no POST, todas as demais rotas no grupo `auth`, sem
  cadastro público nem recuperação de senha. Usuário recrutador criado pelo seeder.
- **Racional**: FR-028 pede apenas usuários autenticados com perfil único; o Laravel já traz
  tudo que é necessário sem starter kit.
- **Alternativas consideradas**: Breeze/Fortify (dependências e telas a mais); login
  institucional (fora do escopo).

## R12. Kanban

- **Decisão**: três colunas fixas; a movimentação principal é por **botão** em cada card
  (formulário `PATCH …/etapa`), que funciona sem JavaScript e é testável. Arrastar e soltar com
  HTML5 Drag and Drop em JS puro, enviando o mesmo formulário, é melhoria progressiva de
  polimento.
- **Racional**: a reunião deixou em aberto se arrastar e soltar é necessário; o botão garante a
  demonstração e o drag-and-drop dá o efeito visual se houver tempo.
- **Alternativas consideradas**: biblioteca de kanban (dependência para algo simples).

## R13. Padrão de energia com IA (História 7)

- **Decisão**:
  - Contrato `App\Services\Energia\AnalisadorEnergia` com
    `analisar(string $textoAnonimizado): ?SugestaoEnergia` (retorna `null` quando indisponível).
  - Implementações: `ClaudeAnalisadorEnergia`, usando o **SDK oficial `anthropic-ai/sdk`** e a
    Messages API com saída estruturada (`outputConfig` com `json_schema`), e
    `AnalisadorEnergiaIndisponivel`, usada quando a IA está desligada ou sem chave. O vínculo fica
    no `AppServiceProvider`, conforme a configuração.
  - Modelo configurável por `ANTHROPIC_MODEL`, padrão `claude-opus-5-5`, com esforço baixo
    (tarefa de classificação curta). Tempo limite curto e no máximo 1 nova tentativa. Qualquer
    exceção do SDK, `stop_reason = refusal` ou JSON fora do esquema → `null` →
    "análise indisponível".
  - **Entrada anonimizada**: o texto enviado é montado a partir de formação, experiências,
    projetos e competências, já limpos de nome, e-mail, telefone, CPF e URLs. Dados de identidade
    nunca são enviados.
  - Execução síncrona sob demanda (botão "Analisar com IA" no perfil) e comando
    `php artisan energia:analisar` para processar em lote os candidatos sem perfil (rodar uma
    vez, com internet, antes do pitch; o resultado fica no banco e a demonstração segue offline).
  - Ajuste manual pelo recrutador grava origem `humano` (US7-5).
  - Proximidade separada da aderência: `round((1 − Σ|vaga − candidato| ÷ 12) × 100)` nos 3
    eixos de 1 a 5. Não entra na ordenação (FR-035).
- **Racional**: atende o princípio V (IA isolada atrás de interface, desligável, sistema segue
  normal) e reduz o risco LGPD ao enviar apenas conteúdo profissional anonimizado. O SDK oficial
  evita montar à mão cabeçalhos e formatos da API.
- **Alternativas consideradas**: chamadas HTTP diretas com o `Http` do Laravel (zero
  dependência e fácil de simular com `Http::fake`, mas formatos mantidos à mão — continua viável
  como plano B se a dependência não for aprovada, sem mudar o contrato); heurística por
  palavras-chave como "IA" (pouco convincente no pitch); fila assíncrona (exige worker rodando no
  ambiente de demonstração).
- **Detalhes a confirmar na implementação** (documentação do SDK PHP): nome das opções de tempo
  limite e de novas tentativas do cliente e o *binding* do parâmetro de *fallback* em caso de
  recusa. Se não estiverem documentados, tratar a recusa como "análise indisponível".

## R14. LGPD: exclusão, desativação e retenção

- **Decisão**:
  - **Exclusão definitiva** (`ExclusaoCandidatoService`): em transação, apaga o candidato (o
    `cascadeOnDelete` remove competências, participações, movimentações, anotações e o perfil de
    energia), apaga o PDF do disco **depois do commit** e grava `registros_exclusao` (data,
    responsável, motivo), sem dado pessoal nem código.
  - **Desativação**: `status = desativado`, oculto de buscas, rankings e triagem; reversível.
  - **Retenção**: `dados_atualizados_em` é atualizado no cadastro, na edição e na reimportação.
    `retencao_ate = dados_atualizados_em + 2 anos` (configurável em
    `config/banco_talentos.php`). Vencidos recebem o selo "revisão de retenção pendente", com
    filtro na listagem e contador no painel. Não há exclusão automática.
  - **Consentimento**: meio, data e responsável obrigatórios na confirmação da importação.
- **Racional**: cobre FR-029 a FR-032 e o princípio IV com o mínimo de mecanismos.
- **Alternativas consideradas**: *soft delete* (não é exclusão de verdade); criptografia de
  campos em repouso (dificulta a busca de duplicidade por e-mail/CPF; fica como evolução, já que
  o acesso exige autenticação e a demonstração usa dados fictícios); tarefa agendada de
  retenção (a regra de 2 anos ainda precisa ser validada).

## R15. Dados de demonstração e preparo em um comando

- **Decisão**:
  - Seeders: `UsuarioSeeder` (recrutador de demonstração), `CompetenciaSeeder` (~45 itens com
    sinônimos, áreas e níveis de idioma), `CandidatoSeeder` (24 candidatos fictícios: 12
    estagiários e 12 servidores em 6 áreas, montados para que a vaga do roteiro produza uma
    distribuição variada de percentuais e ao menos 7 estagiários com Python **e** SQL),
    `VagaSeeder` (3 vagas de áreas distintas: Suporte, Dados e Infraestrutura, com alguns
    candidatos já em Entrevista e com anotações). A vaga "Estagiário de Desenvolvimento" é
    criada **ao vivo** no pitch.
  - PDFs dos candidatos semeados gerados na hora por `Database\Seeders\Support\GeradorPdfCurriculo`,
    um escritor mínimo de PDF de texto (fonte Helvetica, WinAnsi) sem dependências, também
    usado nos testes.
  - PDFs para o envio ao vivo, versionados em `database/seeders/curriculos/`: um currículo bem
    formatado (coluna única), um sem texto (digitalizado) e um com e-mail de candidato já
    existente (duplicidade).
  - Novo comando `demo` nos wrappers (`./hackaton demo` e `.\hackaton.ps1 demo`) = `setup` +
    `migrate:fresh --seed --force`. Sem Docker: `php artisan migrate:fresh --seed`.
- **Racional**: SC-006 (um único comando, já com dados) e FR-037; dados 100% fictícios
  (princípio IV); o mesmo gerador alimenta seeders e testes.
- **Alternativas consideradas**: `dompdf` para gerar PDFs (dependência); 24 PDFs binários
  versionados (manutenção manual sempre que os dados mudarem); candidatos semeados sem PDF
  (contraria FR-002).
- **Observação**: por regra do projeto, a IA não executa `./hackaton setup` nem nada que crie
  ou altere o `.env`. O comando é editado no script e executado pelo usuário.

## R16. Design system

- **Decisão**: tokens no `@theme` do `resources/css/app.css` (cor primária, cores de
  **servidor** e **estagiário**, cores de score, superfícies, raios, tamanhos de fonte com
  base maior para projetor) e componentes Blade anônimos em `resources/views/components/`:
  `botao`, `card`, `badge`, `tag-competencia`, `badge-tipo`, `campo` (input/select/textarea com
  label e erro), `modal` (`<dialog>`), `barra-score` (barra + percentual + cor por faixa),
  `estado-vazio`, `alerta`, `cartao-estatistica`, `cabecalho-pagina`, `eixos-energia`, `icone`
  (SVG inline). Layouts `layouts/app.blade.php` (barra lateral + topo) e
  `layouts/visitante.blade.php` (login).
- **Racional**: princípio III (produto com cara de real, consistente, legível em projetor) sem
  bibliotecas de UI.
- **Regras visuais**: servidor e estagiário com cor **e** rótulo de texto (não só cor, por
  acessibilidade); faixas de score: ≥ 70 verde, 40–69 âmbar, < 40 neutro (score baixo não é
  "erro", então não se usa vermelho); contraste AA verificado nos pares de cor do tema.
- **Fonte offline**: o `vite.config.js` carrega Instrument Sans via Bunny. Verificar no build se
  a fonte fica empacotada; se depender de CDN em tempo de execução, a pilha `ui-sans-serif,
  system-ui` já é o fallback offline aceitável.

## R17. Idioma e mensagens

- **Decisão**: `APP_LOCALE=pt_BR` e `APP_FAKER_LOCALE=pt_BR` no `.env.example`; arquivo
  `lang/pt_BR/validation.php` com as regras usadas e os nomes de atributos em português;
  mensagens de domínio direto nas views e nos services.
- **Racional**: o idioma da interface é obrigatório pela constituição; o Laravel não traz
  traduções em português.
- **Alternativas consideradas**: pacote `laravel-lang` (dependência); `messages()` em cada Form
  Request (repetitivo).
- **Ação do usuário**: quem já tem `.env` deve ajustar essas chaves manualmente (a IA não
  altera o `.env`).

## R18. Limite de upload (10 MB)

- **Decisão**: validação `file|mimes:pdf|max:10240`; no Dockerfile, um
  `conf.d/uploads.ini` com `upload_max_filesize=12M` e `post_max_size=13M`; tratamento de
  `PostTooLargeException` em `bootstrap/app.php`, redirecionando com mensagem amigável. Sem
  Docker, o quickstart orienta ajustar o `php.ini` local.
- **Racional**: o PHP vem com limite padrão de 2 MB. Sem esse ajuste, PDFs maiores falham antes
  da validação, com erro genérico.

## R19. Enums de domínio em PHP

- **Decisão**: *backed enums* em `app/Enums` (`TipoCandidato`, `TipoCandidatoVaga`, `Area`,
  `CategoriaCompetencia`, `OrigemCompetencia`, `StatusCandidato`, `StatusVaga`, `EtapaSelecao`,
  `PesoCompetencia`, `MeioConsentimento`, `OrigemPerfilEnergia`, `MotivoExclusao`), com chaves em
  TitleCase e método `rotulo()` para a UI. Persistidos como string e usados em casts, validação
  (`Rule::enum`) e seeders.
- **Racional**: os conjuntos são fixos por decisão de escopo (princípio II: sem configurações
  genéricas). Enums evitam tabelas de domínio e telas de cadastro.
- **Alternativas consideradas**: tabelas de domínio (áreas, etapas) com CRUD (configuração
  genérica proibida no MVP).

## R20. Armazenamento dos currículos

- **Decisão**: novo disco `curriculos` em `config/filesystems.php`, apontando para
  `storage/app/private/curriculos`, com `visibility` privada e `serve => false`. Os arquivos são
  servidos só por controllers autenticados (`Storage::disk('curriculos')->response(...)`,
  `inline`, `Cache-Control: private, no-store`). Nome físico por UUID; o nome original fica no
  banco.
- **Racional**: o PDF nunca é acessível por URL pública ou assinada; o controle de acesso por
  contexto (R9) fica em um só lugar. No Docker, o volume `app-storage` já persiste o diretório.
- **Alternativas consideradas**: disco `public` (exporia currículos); disco `local` padrão com
  `serve => true` (expõe rota de arquivos que não precisamos).
