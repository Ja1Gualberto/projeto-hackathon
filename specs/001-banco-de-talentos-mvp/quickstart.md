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
```

Esperado: todos os testes passando, em especial `CalculadoraAderenciaTest`,
`RankingVagaTest`, `TriagemAnonimaTest` e `CurriculoProtegidoTest` (gates da constituição).

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

### C. Vaga com pesos e ranking — História 3 (SC-003)

1. **Vagas → Nova vaga** "Estagiário de Desenvolvimento", área Desenvolvimento, tipo Estagiário:
   Python (3), SQL (3), Git (2), Inglês básico (1), Trabalho em equipe (1).
2. Salvar → quadro aberto em < 3 s; a Triagem lista **apenas estagiários**, do maior para o menor
   percentual, cada card com barra e explicação (ex.: "80% — tem 4 de 5 competências; não
   identificado: Git (peso 2)").
3. Confira um card na mão: Σ pesos atendidos ÷ 10 × 100.
4. Tente salvar uma vaga sem competências → bloqueado.
5. Edite o peso de Git para 3 → o ranking muda na próxima abertura do quadro.

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

### E. Anotações de entrevista — História 5 (SC-008)

1. No perfil de um candidato em Entrevista, registre uma anotação marcando a soft skill
   "Comunicação" como observada.
2. A anotação aparece no topo do histórico com autor, data e vaga; a competência passa a ter
   origem "observada em entrevista".
3. Pela gestão do banco, registre uma anotação **sem vaga** → fica associada só ao candidato.
4. Painel → Candidatos → card → anotação visível: no máximo 3 cliques.

### F. LGPD — História 6

1. No perfil: meio e data do consentimento e "Retenção até dd/mm/aaaa".
2. Filtre **Retenção pendente** → candidatos semeados com dados antigos aparecem sinalizados.
3. **Desativar** um candidato → some da busca e das triagens; reative → volta.
4. **Excluir definitivamente** (com confirmação) → some de buscas, rankings e quadros; a URL
   antiga do PDF responde 404; um registro de exclusão sem dados pessoais é gravado.

### G. Padrão de energia — História 7 (diferencial)

1. Edite uma vaga e posicione os três eixos.
2. Com `ENERGIA_IA_HABILITADA=true` e chave válida: "Analisar com IA" no perfil → eixos
   sugeridos + justificativa curta; no ranking, o selo de proximidade aparece **separado** da
   aderência e a ordem não muda.
3. Ajuste os eixos manualmente → selo "Revisado por humano".
4. Com a IA desligada ou sem internet: "Análise indisponível" e todo o resto funciona (SC-007).

## 5. Ensaio cronometrado (SC-005)

Painel → envio de PDF → busca → vaga → triagem anônima → entrevista → anotação, sem erros, em
até 5 minutos. Repita com o Wi-Fi desligado para confirmar o funcionamento offline.
