# Contrato: Importação em lote e PDF temporário

**Feature**: `002-importacao-multipla`. Complementa
[`rotas-web.md`](../../001-banco-de-talentos-mvp/contracts/rotas-web.md) e
[`extracao-curriculo.md`](../../001-banco-de-talentos-mvp/contracts/extracao-curriculo.md).
Todas as rotas exigem autenticação. Respostas JSON seguem o envelope `{message, fragments, redirect}`.

## Rotas

| Método | URI | Nome | Entrada | Resultado |
|---|---|---|---|---|
| POST | `/candidatos/importar` | `importacoes.store` | `arquivos[]` (1 a 10), `tipo` | 1 arquivo → revisão; 2 ou mais → lote, com a modal aberta |
| GET | `/candidatos/importar/{importacao}/pdf` | `importacoes.pdf` | — | PDF `inline`, `private, no-store`; 404 fora da sessão |
| DELETE | `/candidatos/importar/{importacao}` | `importacoes.destroy` | — | Apaga o PDF temporário → lote ou tela de envio |
| POST | `/candidatos/importar/{importacao}/confirmar` | `importacoes.confirmar` | como antes | Próximo pendente → resumo do lote; fora de lote → perfil |
| GET | `/candidatos/importar/lotes/{lote}` | `importacoes.lotes.show` | — | Resumo do lote; 404 com a tela de importação expirada |
| POST | `/candidatos/importar/lotes/{lote}/cadastrar` | `importacoes.lotes.cadastrar` | `consentimento_meio`, `consentimento_em` | Cadastra os aptos → resumo do lote |

## Validação do envio

| Regra | Mensagem |
|---|---|
| Sem arquivos | "Selecione ao menos um currículo em PDF." |
| Mais de 10 | "Envie no máximo 10 currículos por vez." |
| Arquivo inválido, envio de 1 | "Envie um arquivo PDF de até 10 MB." |
| Arquivo inválido, lote | "{nome do arquivo}: envie um arquivo PDF de até 10 MB." |

Qualquer erro recusa o envio inteiro, sem guardar arquivos.

## Modal do lote

- Abre ao chegar do envio e quando o cadastro sem revisão volta com erro; pode ser reaberta pelo botão do resumo.
- Informa quantos currículos foram lidos e quantos estão prontos ou precisam de revisão.
- "Revisar um por um" leva ao primeiro pendente.
- "Cadastrar sem revisão" pede o consentimento. Sem currículos aptos, a opção fica indisponível, com a explicação.

## Revisão dentro de um lote

- Indica "Currículo N de T", com "Pular" (quando há outro pendente) e "Voltar ao lote".
- O botão de confirmação vira "Salvar e seguir para o próximo" quando há outro pendente.

## Regras do cadastro sem revisão

- Usa os dados extraídos e as competências lidas, com origem `curriculo`, no modo `novo`. Nunca atualiza
  um cadastro existente.
- O consentimento vale para todos os itens salvos, com `consentimento_registrado_por` = usuário atual.
- Falha em um item não interrompe os demais: ele fica pendente, com o motivo.
- Mensagem: "{N} candidato(s) cadastrado(s). {M} currículo(s) precisa(m) de revisão manual."

## Extração: casos de referência (dados fictícios)

| Entrada | Esperado |
|---|---|
| `Maria` / `Clara Souza` no topo | nome `Maria Clara Souza` |
| `Técnico em Informática` antes do nome | não é nome |
| `(69) 9 9988-7766` | telefone `(69) 99988-7766` |
| Seção `Educação` | vai para `formacoes` |
| `Experiência auxiliando alunos` | não é título de seção |
| `Projeto: “Sistema X”` dentro de Experiência, seguido de nova experiência | item em `projetos`; a experiência seguinte fica em `experiencias` |
| Seção `Certificados` | vai para `experiencias` |
| Parágrafo em 3 linhas | 1 item |
| Item acima de 297 caracteres | dividido em frases, sem perda |
| PDF em duas colunas, texto fora de ordem no arquivo | seções corretas |
| Datas alinhadas à direita | permanecem na linha da entrada |
