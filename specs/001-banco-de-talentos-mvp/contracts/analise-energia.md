# Contrato: Análise do Padrão de Energia (IA)

**Feature**: `001-banco-de-talentos-mvp` | História 7 (P4, diferencial) | FR-033 a FR-036 |
Decisão em [research R13](../research.md#r13-padrão-de-energia-com-ia-história-7).

## Interface interna

```text
interface AnalisadorEnergia
    analisar(string $textoAnonimizado): ?SugestaoEnergia   // null = análise indisponível

final readonly class SugestaoEnergia
    int $dinamicoAnalitico       // 1 = dinâmico … 5 = analítico
    int $individualEquipe        // 1 = individual … 5 = equipe
    int $execucaoPlanejamento    // 1 = execução … 5 = planejamento
    string $justificativa        // até 500 caracteres, citando evidências do currículo
```

| Implementação | Quando é vinculada |
|---|---|
| `ClaudeAnalisadorEnergia` | `banco_talentos.energia.habilitada = true` **e** `services.anthropic.key` preenchida |
| `AnalisadorEnergiaIndisponivel` | Qualquer outro caso (sempre devolve `null`) |
| Falso de teste | Vinculado nos testes via `$this->app->instance(...)` |

## Configuração

| Chave de configuração | Variável de ambiente | Padrão |
|---|---|---|
| `banco_talentos.energia.habilitada` | `ENERGIA_IA_HABILITADA` | `false` |
| `services.anthropic.key` | `ANTHROPIC_API_KEY` | — |
| `services.anthropic.model` | `ANTHROPIC_MODEL` | `claude-opus-5-5` |
| `banco_talentos.energia.timeout` | `ENERGIA_IA_TIMEOUT` | `20` (segundos) |

As variáveis entram no `.env.example`. O usuário as copia para o próprio `.env`.

## Entrada enviada à IA

Montada por `PerfilEnergiaService::textoAnonimizado(Candidato)` a partir de **formação,
experiências, projetos e nomes das competências**, com remoção de e-mails, telefones, CPF, URLs
e de qualquer ocorrência dos termos do nome do candidato. Nunca inclui nome, contatos, data de
nascimento, links ou o PDF.

## Requisição (Messages API via SDK oficial `anthropic-ai/sdk`)

- **Modelo**: `config('services.anthropic.model')`.
- **Esforço**: baixo (`outputConfig` com `effort: low`), por ser uma classificação curta.
- **`maxTokens`**: folgado para a justificativa (ex.: 2000), sem cortar a resposta.
- **System** (pt-BR, fixo): descreve os três eixos e a escala 1–5, pede justificativa curta
  baseada **apenas** em evidências do texto e orienta a usar 3 (neutro) quando não houver
  evidência.
- **User**: o texto anonimizado.
- **Saída estruturada**: `outputConfig.format` com `json_schema`:

```json
{
  "type": "object",
  "properties": {
    "dinamico_analitico":    { "type": "integer", "enum": [1, 2, 3, 4, 5] },
    "individual_equipe":     { "type": "integer", "enum": [1, 2, 3, 4, 5] },
    "execucao_planejamento": { "type": "integer", "enum": [1, 2, 3, 4, 5] },
    "justificativa":         { "type": "string" }
  },
  "required": ["dinamico_analitico", "individual_equipe", "execucao_planejamento", "justificativa"],
  "additionalProperties": false
}
```

## Mapeamento de falhas → `null` ("análise indisponível")

| Situação | Tratamento |
|---|---|
| IA desligada ou sem chave | `AnalisadorEnergiaIndisponivel` |
| Erro de conexão / tempo limite | captura a exceção do SDK, registra em log (sem conteúdo do currículo) e devolve `null` |
| Erro HTTP da API (4xx/5xx, *rate limit*, sobrecarga) | idem; no máximo 1 nova tentativa |
| `stop_reason = refusal` | `null` |
| JSON fora do esquema ou eixos fora de 1–5 | `null` |
| Justificativa com mais de 500 caracteres | truncada |

## Persistência e exibição

- Sucesso → `perfis_energia` (upsert por candidato) com `origem = ia` e `analisado_em = agora`.
- Ajuste manual → mesmos campos, `origem = humano`, `revisado_por = usuário` (US7-5).
- No ranking e no perfil: componente `eixos-energia` (três réguas) e selo
  "Energia: 83% de proximidade", **separado** da barra de aderência e fora da ordenação
  (FR-035). Sem perfil na vaga → nada é exibido; candidato sem perfil → "Não analisado";
  falha → "Análise indisponível".
- Comando `php artisan energia:analisar {--todos}` processa candidatos sem perfil, com barra de
  progresso e resumo (analisados / indisponíveis).

## Proximidade

`proximidade = round((1 − (|Δ1| + |Δ2| + |Δ3|) ÷ 12) × 100)`, em que Δ é a diferença por eixo
entre a vaga e o candidato (máximo 4 por eixo). Exemplo: vaga (2, 4, 2) × candidato (3, 4, 1) →
Δ = 1 + 0 + 1 = 2 → 83%.
