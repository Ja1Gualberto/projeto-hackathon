# Quickstart: validar a importação múltipla

## Preparação

```powershell
.\hackaton.ps1 dev                                 # reconstrói a imagem (limite de upload do lote)
.\hackaton.ps1 artisan test --filter=Importacao    # usa SQLite em memória; não toca no banco de desenvolvimento
```

Os PDFs fictícios estão em `database/seeders/curriculos`; `.\hackaton.ps1 artisan demo:curriculos` os gera de novo.

Se uma mudança em Blade, CSS ou JS não aparecer, reinicie o Vite com `.\hackaton.ps1 restart vite`: no Docker Desktop
com Windows, ele não recebe os avisos de alteração de arquivo.

## Cenário A: currículo em colunas (US1)

1. Em **Candidatos → Importar**, envie só `demo-colunas.pdf`.
2. Confira na revisão: nome completo em uma linha, telefone normalizado, Formação vinda de "Educação",
   Projetos vindos de "Projeto: ..." e Experiências com o estágio e os certificados.

## Cenário B: comparação com o PDF (US2)

1. Na mesma revisão, em uma janela de 1280 px ou mais, o PDF aparece à direita e continua visível ao rolar.
2. "Abrir PDF" abre o documento em nova aba.
3. Reduza a janela abaixo de 1280 px: a pré-visualização some, o botão permanece e não há rolagem horizontal.

## Cenário C: lote, um por um (US3)

1. Selecione `demo-curriculo.pdf`, `demo-colunas.pdf` e `demo-escaneado.pdf`, e escolha o tipo.
2. Antes de enviar, os três nomes aparecem listados. Clique em **Ler currículo**: a modal informa 3 currículos lidos.
3. Escolha **Revisar um por um**: a tela indica "Currículo 1 de 3". Preencha o consentimento e confirme.
4. A tela segue para o próximo. Ao terminar, aparece o resumo do lote.

## Cenário D: lote, sem revisão (US3)

1. Envie `demo-curriculo.pdf`, `demo-colunas.pdf`, `demo-escaneado.pdf` e `demo-duplicado.pdf` em um banco com o seed.
2. Na modal, tente cadastrar sem preencher o consentimento: o sistema recusa e nada é salvo.
3. Preencha o consentimento e confirme. Os dois primeiros ficam **Cadastrado**, com link para o perfil. O digitalizado
   e o duplicado ficam pendentes, com o motivo.
4. Descarte um pendente: ele deixa de ter ações e o PDF temporário é apagado.

## Resultado esperado

Nenhum candidato é salvo sem consentimento, e nenhum cadastro existente é alterado pelo cadastro sem revisão.
