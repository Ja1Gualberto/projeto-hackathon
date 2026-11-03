# Data Model: Importação múltipla

Sem migrations. Tudo abaixo é temporário e fica na sessão, até a confirmação ou o descarte.

## Importação (`importacoes.{uuid}`), já existente

Ganha um campo:

| Campo | Tipo | Observação |
|---|---|---|
| `lote` | `?string` (UUID) | Lote a que pertence; `null` no envio de um único PDF |

## Lote (`importacoes_lotes.{uuid}`)

| Campo | Tipo | Observação |
|---|---|---|
| `itens` | `list<Item>` | Na ordem de envio |

### Item

| Campo | Tipo | Observação |
|---|---|---|
| `uuid` | `string` | Importação correspondente |
| `nome_original` | `string` | Nome do arquivo enviado |
| `situacao` | `pendente` \| `cadastrado` \| `descartado` | Estado atual |
| `motivo` | `?string` | Por que não foi cadastrado sem revisão |
| `candidato_id` | `?int` | Preenchido ao cadastrar |
| `candidato_nome` | `?string` | Preenchido ao cadastrar |

### Transições

```text
pendente --confirmação manual ou cadastro sem revisão--> cadastrado
pendente --descarte--> descartado (PDF temporário apagado)
pendente --cadastro sem revisão recusado--> pendente (com `motivo`)
```

`cadastrado` e `descartado` são finais. O lote deixa de existir quando a sessão expira; PDFs temporários
seguem a política já existente.

### Aptidão ao cadastro sem revisão

Um item pendente é apto quando, nesta ordem, nenhuma condição abaixo se aplica:

1. PDF sem texto legível ou corrompido.
2. Nome não identificado.
3. Área de atuação não identificada.
4. E-mail ou CPF já cadastrados (inclusive por outro item do mesmo lote, já salvo).
5. Dados recusados pelas mesmas validações da revisão manual.

## Resultado da leitura

`ResultadoLeituraDTO` ganha `destaque: ?string`: o texto de maior fonte da primeira página. É só um
insumo do extrator e não é guardado na sessão. Os campos de `DadosExtraidosDTO` não mudam.
