# Implementation Plan: Importação múltipla, comparação com o PDF e leitura blindada

**Branch**: `002-importacao-multipla` | **Date**: 2026-11-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-importacao-multipla/spec.md`

## Summary

Três entregas sobre a importação existente: (1) reconstruir a ordem visual do PDF a partir das
posições do texto e endurecer as regras de extração; (2) mostrar o PDF ao lado do formulário na
revisão, com "Abrir PDF" sempre disponível; (3) aceitar até 10 PDFs por envio, com uma modal que
oferece revisão um por um ou cadastro sem revisão, mantendo o registro de consentimento obrigatório.
Sem migrations nem dependências novas: o lote vive na sessão, como a importação já vive hoje.

## Technical Context

**Language/Version**: PHP 8.5, JavaScript (módulos ES, Vite 8)

**Primary Dependencies**: Laravel 13, `smalot/pdfparser` 2.13 (já instalado), Tailwind CSS v4

**Storage**: sessão (driver `database`) para importações e lotes; disco privado `curriculos` para os PDFs

**Testing**: PHPUnit 13 (`php artisan test`), `node --test` para os módulos JS

**Target Platform**: aplicação web em Docker (Apache + mod_php), navegadores de desktop

**Project Type**: aplicação web monolítica (Blade + AJAX progressivo)

**Performance Goals**: lote de 10 currículos típicos lido em menos de 15 s (medido: 0,04 s por PDF real)

**Constraints**: offline; sem IA nem OCR; `.env` intocado; PDFs só em área privada; itens de até 300 caracteres

**Scale/Scope**: até 10 arquivos de até 10 MB por envio

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio | Avaliação | Situação |
|---|---|---|
| I. Demo-First | Fortalece a etapa "PDF → leitura automática"; o fluxo de 1 PDF continua igual | ✅ |
| II. Simplicidade | Sem dependência, tabela ou pasta-base novas. Importação em lote era "fora, salvo decisão explícita": o usuário decidiu | ✅ ver *Complexity Tracking* |
| III. Design | Reutiliza `x-modal`, `x-campo`, `x-botao`, `x-badge`; estados de vazio/erro cobertos | ✅ |
| IV. LGPD (inegociável) | "Sem revisão" ainda exige consentimento; PDF temporário restrito à sessão autenticada, sem cache | ✅ |
| V. IA como diferencial | Extração segue determinística e offline | ✅ |
| VI. Decisão humana | Duplicidades e leituras incertas nunca são salvas sozinhas; dados seguem editáveis no perfil | ✅ |

**Pós-design**: reavaliado, sem violações novas.

## Project Structure

### Documentation (this feature)

```text
specs/002-importacao-multipla/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/importacao-lote.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── DTOs/ResultadoLeituraDTO.php                 # ganha `destaque` (maior título da página)
├── Http/Controllers/
│   ├── ImportacaoCurriculoController.php        # envio de 1 a 10 PDFs, PDF temporário, descarte
│   ├── ConfirmacaoImportacaoController.php      # segue para o próximo do lote
│   └── LoteImportacaoController.php             # novo: resumo e cadastro sem revisão
├── Http/Requests/
│   ├── ImportarCurriculoRequest.php             # `arquivos[]`
│   └── CadastrarLoteRequest.php                 # novo: consentimento do lote
└── Services/Curriculo/
    ├── LeitorLayoutPdf.php                      # novo: posições → ordem de leitura
    ├── LeitorPdf.php                            # usa o layout, com recurso à ordem do arquivo
    ├── ExtratorCurriculo.php                    # nome, telefone, seções, itens
    ├── ImportacaoCurriculoService.php           # vínculo com o lote
    └── LoteImportacaoService.php                # novo: fila e cadastro sem revisão
resources/js/modulos/importacao.js               # lista de arquivos selecionados
resources/views/importacoes/
├── create.blade.php                             # seleção múltipla
├── show.blade.php                               # formulário + pré-visualização
├── lote.blade.php                               # novo: resumo do lote e modal
└── partials/_escolha-lote.blade.php             # novo: opções da modal
routes/candidatos.php
config/banco_talentos.php                        # `importacao_max_arquivos`
Dockerfile                                       # `post_max_size` para o lote
database/seeders/Support/GeradorPdfCurriculo.php # PDF com texto posicionado
app/Console/Commands/GerarCurriculosDemoCommand.php # `demo-colunas.pdf`
tests/Unit/{LeitorPdfTest,ExtratorCurriculoTest}.php
tests/Feature/{ImportacaoCurriculoTest,ImportacaoLoteTest}.php
tests/js/importacao.test.js
```

**Structure Decision**: mantém o fluxo Rota → Form Request → Controller → Service do projeto. O lote
é um Service sobre a sessão; não há Repository porque nada é persistido antes da confirmação.

## Complexity Tracking

| Item | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Importação em lote (fora do MVP original) | Pedido explícito do usuário; ganho visível no pitch | Manter 1 PDF por vez não atende ao pedido |
| Leitura por posição (`LeitorLayoutPdf`) | A ordem gravada em PDFs de ferramentas de design é agrupada por estilo: títulos vêm antes de todo o conteúdo, e um contato chegou a sumir | Regras só sobre o texto na ordem do arquivo não recuperam o que não vem nele nem separam colunas |
