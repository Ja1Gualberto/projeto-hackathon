# Contrato: Leitura e Extração de Currículo

**Feature**: `001-banco-de-talentos-mvp` | FR-008 a FR-014 | Algoritmo em
[research R4](../research.md#r4-extração-estruturada-regras--catálogo).

## Componentes

```text
LeitorPdf::extrairTexto(string $caminhoAbsoluto): ResultadoLeitura
ExtratorCurriculo::extrair(string $texto): DadosExtraidos
ImportacaoCurriculoService::importar(UploadedFile $arquivo, TipoCandidato $tipo): string   // UUID da importação
ImportacaoCurriculoService::obter(string $uuid): ?Importacao
CadastroCandidatoService::confirmar(Importacao $importacao, array $dadosRevisados, User $recrutador): Candidato
```

## `ResultadoLeitura`

| Campo | Tipo | Descrição |
|---|---|---|
| `situacao` | `lido` \| `sem_texto` \| `corrompido` | `sem_texto`: menos de 80 caracteres não brancos |
| `texto` | `?string` | texto bruto quando `lido` |

## `DadosExtraidos` (serializável em array para a sessão)

| Campo | Tipo | Observação |
|---|---|---|
| `nome` | `?string` | heurística da primeira linha |
| `email` | `?string` | primeiro e-mail válido |
| `telefone` | `?string` | formato brasileiro normalizado |
| `cpf` | `?string` | 11 dígitos, só se os dígitos verificadores forem válidos |
| `data_nascimento` | `?string` (Y-m-d) | |
| `formacoes` | `list<string>` | linhas da seção de formação |
| `experiencias` | `list<string>` | linhas da seção de experiência |
| `projetos` | `list<string>` | linhas da seção de projetos |
| `links` | `list<string>` | URLs únicas |
| `competencias` | `list<{id:int, nome:string, categoria:string, trecho:string}>` | sempre origem `curriculo`; soft skills exibidas como "declaradas no currículo" (FR-012) |
| `sugestoes_competencias` | `list<string>` | termos fora do catálogo; nunca cadastrados sozinhos |
| `area_sugerida` | `?string` | valor de `Area` (R5) |
| `experiencia_na_area` | `bool` | `false` significa "não informado" |

## Estados da tela de revisão

| Situação | Comportamento |
|---|---|
| `lido` | Formulário pré-preenchido; competências marcadas com o trecho de origem; sugestões como chips "Adicionar ao catálogo" / "Ignorar" |
| `sem_texto` | Alerta "Não conseguimos ler o texto deste PDF (parece digitalizado). Preencha os dados manualmente."; formulário vazio; PDF mantido |
| `corrompido` | Mesmo comportamento de `sem_texto`, com a mensagem "O arquivo parece corrompido." |
| Duplicidade (e-mail ou CPF iguais a um cadastro existente) | Alerta com o nome do cadastro existente e a escolha "Atualizar cadastro existente" (padrão) ou "Criar novo mesmo assim" |
| Sem consentimento | Validação bloqueia: "Registre como e quando o candidato consentiu com o uso dos dados." (FR-011) |

## Regras de confirmação

- Modo `novo`: gera o `codigo`, move o PDF de `tmp/` para `curriculos/{uuid}.pdf`, grava as
  competências marcadas (origem `curriculo` se vieram da extração, `revisao` se adicionadas pelo
  recrutador), o consentimento (`consentimento_registrado_por` = usuário atual) e
  `dados_atualizados_em = agora`.
- Modo `atualizar`: substitui o PDF (apaga o anterior depois do commit), sobrescreve os campos
  revisados, mescla competências respeitando a precedência de origem (uma competência observada
  em entrevista não é rebaixada) e renova `dados_atualizados_em`. Mantém `codigo`,
  participações e anotações.
- Tudo em transação; a sessão `importacoes.{uuid}` é limpa ao final.

## Critério de qualidade (SC-002)

Nos PDFs de exemplo, ≥ 80% das competências do catálogo presentes no texto devem ser
identificadas. O teste unitário do extrator usa textos de referência com a lista esperada.

## Casos de teste de referência (unidade, sobre texto)

| Entrada (trecho) | Esperado |
|---|---|
| `"Conhecimentos: Python, SQL, Git e Docker"` | Python, SQL, Git, Docker |
| `"Experiência com C#, .NET e Node.js"` | C#, .NET, Node.js (fronteiras com símbolos) |
| `"Banco de dados PostgreSQL"` | PostgreSQL **e** SQL |
| `"Inglês: intermediário"` | Inglês intermediário |
| `"Inglês"` (sem nível) | Inglês básico |
| `"Trabalho em equipe e comunicação"` | soft skills com origem `curriculo` |
| `"Kubernetes, Terraform"` (fora do catálogo) | `sugestoes_competencias` |
| `"CPF 123.456.789-00"` (dígitos inválidos) | `cpf = null` |
| Texto com 30 caracteres | `situacao = sem_texto` |
