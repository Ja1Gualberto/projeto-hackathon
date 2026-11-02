# Quickstart: validar o Banco de Talentos SETIC — MVP

**Feature**: `001-banco-de-talentos-mvp` | Guia de execução e validação de ponta a ponta.
Detalhes de rotas e regras: [contracts/](./contracts/) e [data-model.md](./data-model.md).

## Pré-requisitos

- Docker Desktop (Windows, backend WSL 2) ou Docker Engine + Compose (Linux); **ou** PHP 8.5,
  Composer 2, `pdo_sqlite` e Node.js/npm para rodar sem Docker.
- Navegador Chrome ou Edge; para o pitch, resolução de no mínimo 1280×720.
- Opcional (História 7): chave da API da Anthropic.

## 1. Variáveis de ambiente (feito pelo usuário)

O `.env` não é lido nem alterado por agentes de IA. Num clone novo, o `setup` copia o
`.env.example`, que já traz as chaves abaixo. Num `.env` existente, ajuste manualmente:

```dotenv
APP_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

# Diferencial de IA (opcional; com false o sistema mostra "análise indisponível")
ENERGIA_IA_HABILITADA=false
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-5-5
```

## 2. Preparar o ambiente de demonstração (um comando — SC-006)

**Windows (PowerShell):**

```powershell
.\hackaton.ps1 demo
```

**Linux:**

```bash
./hackaton demo
```

O comando `demo` faz o `setup` (build, serviços e APP_KEY, se faltar) e
`migrate:fresh --seed --force`, carregando o recrutador, o catálogo, 24 candidatos fictícios com
PDF e 3 vagas. A aplicação fica em `http://localhost:8000` (`status` mostra as portas).

**Rode com internet:** quando o código muda, o `demo` reconstrói a imagem, e `composer install`,
`npm ci` e o download da fonte precisam de rede. Depois de pronto, o sistema funciona sem internet e
nenhuma tela faz pedido externo.

**Mantenha o container `vite` no ar:** no Docker, é ele que serve CSS, JavaScript e fonte. Parado,
as telas ficam sem estilo ou respondem erro 500. Antes do pitch, confira com `status`. Se ele subir
do zero sem internet, tudo funciona, mas com a fonte do sistema.

**Sem Docker** (com `.env` e APP_KEY já configurados):

```bash
composer install
npm install --ignore-scripts && npm run build
php artisan migrate:fresh --seed
php artisan serve
```

Para enviar PDFs de até 10 MB sem Docker, ajuste o `php.ini` usado pelo PHP
(`php --ini`): `upload_max_filesize = 12M` e `post_max_size = 13M`.

**Acesso:** `recrutador@setic.demo` / `demo1234` (somente dados fictícios).

**Antes do pitch, com internet (opcional):** `php artisan energia:analisar --todos`
(ou `.\hackaton.ps1 artisan energia:analisar --todos`) grava os perfis de energia no banco. A
demonstração segue offline depois disso.

## 3. Testes automatizados e formatação

```bash
php artisan test                         # ou: ./hackaton artisan test
vendor/bin/pint --dirty --format agent   # ou: ./hackaton check
node --test tests/js/*.test.js           # sem pacotes adicionais
```

Esperado: todos os testes passando, em especial `CalculadoraAderenciaTest`,
`RankingVagaTest`, `TriagemAnonimaTest` e `CurriculoProtegidoTest` (gates da constituição).

As suítes de cada história também verificam o modo JSON interno, 422 de validação e HTML de
fallback; `AjaxSegurancaTest` cobre as proteções transversais com a proteção contra forja ligada,
e `tests/js/contraste.test.js` trava os pares de cor. O resultado da execução dos cenários abaixo
está no fim da seção 4.

## 4. Cenários de validação manual (roteiro do pitch)

Execute na ordem, com o banco recém-semeado. Cada bloco valida uma história de forma isolada.

### A. Painel e busca — História 1 (SC-001, SC-009)

1. Entre com o recrutador → **Painel** mostra o total de talentos (24), servidores × estagiários
   (12 × 12), vagas abertas (3), top competências e o contador de retenção pendente.
2. **Candidatos** → filtre `Python` + `SQL` → apenas quem tem **as duas** aparece, com
   "N talentos encontrados".
3. Adicione `Estagiário` e área `Desenvolvimento` → a lista e a contagem diminuem (≥ 7
   estagiários com Python e SQL no total, antes do filtro de área).
4. Os cards mostram selo de tipo com cor **e** texto, área, experiência ("sim" ou
   "não informado") e tags.
5. Combine filtros sem resultado → estado vazio com "Remover filtros".
6. Abra um card → perfil completo com formação, experiências, competências com origem,
    links, PDF, consentimento, retenção e anotações.

**Gate AJAX da Phase 3 (SC-010):** com JavaScript, etapas 2–5 e a paginação atualizam apenas
contagem/cards/paginação, sem nova navegação de documento nem perda de foco/cursor. Digite
um nome continuamente e confirme o debounce de 400 ms. A URL acompanha a consulta confirmada;
voltar/avançar, abrir a URL diretamente e recarregar reproduzem filtros/página. Sem JS, o
botão de aplicar filtros, remover filtros e os links de paginação continuam funcionando.

### B. Importação de PDF — História 2 (SC-002)

1. **Importar currículo** → envie `database/seeders/curriculos/demo-curriculo.pdf` como
   Estagiário → revisão pré-preenchida (nome, contato, formação, experiências, competências com
   o trecho de origem, área sugerida e links). Soft skills aparecem como "declaradas no currículo".
2. Tente confirmar sem consentimento → bloqueado, com a mensagem explicando o motivo.
3. Preencha meio e data, remova/adicione uma competência e confirme → perfil salvo e PDF
   disponível; o candidato aparece na busca da etapa A. Tempo total < 2 minutos.
4. Envie `demo-escaneado.pdf` → aviso de que não foi possível ler, formulário vazio para
   preenchimento manual e PDF mantido.
5. Envie `demo-duplicado.pdf` → alerta de possível duplicidade com a opção de atualizar o
   cadastro existente.
6. Envie um `.docx` ou um arquivo acima de 10 MB → rejeição com mensagem amigável.

**Gate AJAX da Phase 4:** envio mostra “Processando…” e navega à revisão só no sucesso;
erros aparecem na tela de origem. Na revisão, altere campos, selecione competências e inclua
uma sugestão no catálogo: nenhum dado editado/selecionado se perde e o candidato não é salvo
por engano. Confirmar sem consentimento retorna 422 sem apagar campos; sucesso navega ao perfil.

### C. Vaga com pesos e ranking — História 3 (SC-003)

1. **Vagas → Nova vaga** "Estagiário de Desenvolvimento", área Desenvolvimento, tipo Estagiário:
   Python (3), SQL (3), Git (2), Inglês básico (1), Trabalho em equipe (1).
2. Salvar → quadro aberto em < 3 s; a Triagem lista **apenas estagiários**, do maior para o menor
   percentual, cada card com barra e explicação (ex.: "80% — tem 4 de 5 competências; não
   identificado: Git (peso 2)").
3. Confira um card na mão: Σ pesos atendidos ÷ 10 × 100.
4. Tente salvar uma vaga sem competências → bloqueado.
5. Edite o peso de Git para 3 → o ranking muda na próxima abertura do quadro.

**Gate AJAX da Phase 5:** criar/editar com dados inválidos mostra erros sem navegação/perda
de campos; sucesso navega ao quadro. “Atualizar quadro” refaz a leitura sem recarregar o
documento; “Encerrar” atualiza status/ações e mantém somente consulta. Percentuais, explicações
e ordem são iguais aos da página tradicional; não há atualização periódica em segundo plano.

### D. Triagem anônima e kanban — História 4 (SC-004)

1. Na Triagem, nenhum card mostra nome, idade, contato ou link do perfil do banco; só o código
   `CAND-NNNN`.
2. Abra o perfil de um card em triagem → sem identidade, links, anotações ou PDF. Copie a URL
   `…/curriculo` e acesse direto → **403**.
3. Mova os 3 primeiros para **Entrevista** → os nomes aparecem nessa vaga; o histórico mostra
   quem moveu e quando; o PDF passa a abrir.
4. Abra outra vaga em que um desses candidatos seja elegível → ele continua anônimo lá.
5. Volte um candidato para Triagem → anônimo de novo. Encerre a vaga → o quadro fica somente
    leitura.

**Gate AJAX da Phase 6:** mover por botão atualiza colunas, contagens e ações sem recarregar;
o perfil aberto atualiza identidade e histórico quando a ação parte dele. Voltar à triagem
remove também nome/contato/links/anotações do DOM e das mensagens do candidato afetado.
Inspecione o JSON/HTML decodificado na aba Network: não basta ocultar visualmente. A vaga B
continua anônima; em falha, não há movimentação visual otimista. Drag-and-drop é opcional e
reutiliza o mesmo envio, não um segundo caminho de persistência.

### E. Anotações de entrevista — História 5 (SC-008)

1. No perfil de um candidato em Entrevista, registre uma anotação marcando a soft skill
   "Comunicação" como observada.
2. A anotação aparece no topo do histórico com autor, data e vaga; a competência passa a ter
   origem "observada em entrevista".
3. Pela gestão do banco, registre uma anotação **sem vaga** → fica associada só ao candidato.
4. Painel → Candidatos → card → anotação visível: no máximo 3 cliques.

**Gate AJAX da Phase 7:** anotar atualiza histórico e competências sem recarregar; limpar
texto/seleções apenas no sucesso. Com texto inválido/rede falha, o rascunho permanece. No
contexto da vaga, triagem continua negando anotação com 403 mesmo por requisição manual.

### F. LGPD — História 6

1. No perfil: meio e data do consentimento e "Retenção até dd/mm/aaaa".
2. Filtre **Retenção pendente** → candidatos semeados com dados antigos aparecem sinalizados.
3. **Desativar** um candidato → some da busca e das triagens; reative → volta.
4. **Excluir definitivamente** (com confirmação) → some de buscas, rankings e quadros; a URL
    antiga do PDF responde 404; um registro de exclusão sem dados pessoais é gravado.

**Gate AJAX da Phase 8:** situação/retenção entram na mesma busca parcial da US1. No perfil,
desativar/reativar atualiza situação e seção LGPD sem recarregar. Corrigir dados mostra 422
local em erro e navega ao perfil no sucesso; excluir exige motivo/confirmação e só navega à
lista após resposta de sucesso. Não repetir a exclusão automaticamente após falha de rede.
Outras abas/quadros refletem alterações na próxima leitura; não há sincronização em tempo real.

### G. Padrão de energia — História 7 (diferencial)

1. Edite uma vaga e posicione os três eixos.
2. Com `ENERGIA_IA_HABILITADA=true` e chave válida: "Analisar com IA" no perfil → eixos
   sugeridos + justificativa curta; no ranking, o selo de proximidade aparece **separado** da
   aderência e a ordem não muda.
3. Ajuste os eixos manualmente → selo "Revisado por humano".
4. Com a IA desligada ou sem internet: "Análise indisponível" e todo o resto funciona (SC-007).

**Gate AJAX da Phase 9 (se US7 incluída):** no perfil do banco, analisar/ajustar atualiza
somente `secao-energia`, com loading e bloqueio de duplo envio. Indisponibilidade retorna
503, mantém perfil anterior e permite ajuste manual. Os selos da vaga mudam na próxima
atualização explícita do quadro; a ordenação principal nunca muda por energia.

### H. Robustez AJAX, acessibilidade e fallback — Phases 2–10 (SC-010, SC-011)

Executar em cada história entregue e repetir integralmente antes de T148:

1. **Rede lenta / ordem:** na aba Network, limite a velocidade e altere rapidamente texto,
   selects e página. Só a última intenção de busca pode atualizar resultados/URL, inclusive
   quando a edição acontece durante o debounce e uma resposta anterior chega nesse intervalo.
2. **Duplo envio:** dê dois cliques em Confirmar, Mover ou Registrar anotação enquanto a primeira
   resposta está pendente. Há só uma submissão; controles voltam a funcionar após a resposta.
3. **Validação / foco:** provoque 422; mensagens em português junto aos campos e resumo
   anunciado, foco no primeiro erro, valores/rascunho preservados. Após atualização parcial,
   modais, chips, botões e filtros continuam funcionando sem listeners duplicados.
4. **Falha ambígua:** interrompa a conexão durante uma escrita. Não há retry ou submit
   tradicional automático. O usuário é orientado a conferir estado por GET antes de repetir;
   a tela não anuncia sucesso nem duplica anotação/movimentação/confirmação.
5. **Sessão / segurança:** simule sessão expirada, token inválido e recurso inexistente.
   Tratar 401/419/403/404 sem inserir uma página de login/erro dentro dos resultados nem
   navegar automaticamente perdendo o formulário. Para arquivo grande, 413 continua amigável.
   Não desabilitar proteção para testar a integração.
6. **Teclado / anúncios:** usar só teclado para filtrar, paginar, mover e confirmar; foco
   continua previsível, loading é anunciado por `aria-live`/`aria-busy`, sem depender só de cor.
7. **Sem JavaScript:** desative JS, recarregue e execute A–F (e G se incluída) por links e
   formulários tradicionais; confirmações continuam explícitas. Não confundir sem JS com
   falha de rede depois que uma escrita já começou.
8. **Sem internet:** desligue o acesso externo mantendo o servidor local acessível; A–F
   continuam funcionando também via AJAX. Falha da IA não bloqueia o restante do sistema.

**Registro da execução em 2026-11-01:** Chromium headless 156 (Playwright fora do projeto), em
1280×720 e 390×780, com a imagem do `Dockerfile` e seus assets compilados, MySQL 8.4, seed novo e
rede sem internet. A pilha com o container `vite` foi conferida só no carregamento do login. Na
sessão expirada, a escrita em navegador responde 401; 419 foi conferido em envio sem prova de origem.

| Item | Resultado |
|---|---|
| A–G | 39/39 passos aprovados; quadro aberto dentro da meta de 3 s; IA desligada em G4 |
| H1–H6, celular | Aprovados: só a última busca vale, um único envio no duplo clique, sem retry após queda, foco previsível, Tab preso em modal e menu |
| H7 — sem JavaScript | Aprovado: importação, filtros, paginação, vaga, mover, anotação, encerrar, status, ajuste manual e exclusão pelo bloco `noscript` |
| H8 — sem internet | Aprovado: nenhum pedido externo; com IA ligada, 503 amigável em 147 ms e perfil preservado |
| Acessibilidade | axe-core sem violações WCAG 2.0/2.1 A e AA; foco visível em 186 elementos percorridos com Tab |

**Fora desta execução, a cargo do usuário (T148):** `demo` real, API real da Anthropic, Chrome ou
Edge com interface, Firefox, leitor de tela real e revisão visual em projetor.

## 5. Ensaio cronometrado (SC-005)

Painel → envio de PDF → busca → vaga → triagem anônima → entrevista → anotação, sem erros, em
até 5 minutos. Repita com o Wi-Fi desligado para confirmar o funcionamento offline.

Quando o navegador acessar um servidor em outra máquina, manter a rede local disponível;
o requisito offline é ausência de internet, não ausência de comunicação com o servidor.

**Dica para o pitch:** deixe o menu lateral expandido antes de começar (a preferência fica salva).
Recolhido, o primeiro clique só expande o menu. Os botões da página, como "Explorar talentos",
navegam direto.

O roteiro executado sem pausas consome 2,8 s de sistema; o ensaio humano continua necessário para
o SC-005.
