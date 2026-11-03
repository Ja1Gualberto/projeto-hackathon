import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { test } from 'node:test';
import { aplicarFragmentos, registrarRenderizador, validarEnvelope } from '../../resources/js/modulos/ajax.js';
import { iniciarVagas, montarQuadro, preencherFormulario, quantidade, renderizarLista, validarQuadro } from '../../resources/js/modulos/vagas.js';

function elemento(seletores = []) {
    const filhos = new Map(seletores.map((seletor) => [seletor, elemento()]));
    const classes = new Set();
    return {
        dataset: {}, style: {}, children: [], hidden: false, textContent: '', atributos: {}, removido: false,
        classList: {
            add: (...nomes) => nomes.forEach((nome) => classes.add(nome)),
            remove: (...nomes) => nomes.forEach((nome) => classes.delete(nome)),
            contains: (nome) => classes.has(nome),
            toggle: (nome, ativo) => ativo ? classes.add(nome) : classes.delete(nome),
        },
        querySelector(seletor) { assert.ok(filhos.has(seletor), `Seletor inesperado: ${seletor}`); return filhos.get(seletor); },
        append(...itens) { this.children.push(...itens); },
        replaceChildren(...itens) { this.children = itens; },
        remove() { this.removido = true; },
        setAttribute(nome, valor) { this.atributos[nome] = String(valor); },
    };
}

function documento() {
    const estruturas = {
        'modelo-vaga': ['[data-status]', '[data-tipo]', '[data-area]', '[data-titulo]', '[data-competencias-total]', '[data-elegiveis]', '[data-entrevista]', '[data-aprovado]', '[data-abrir-quadro]'],
        'modelo-quadro': ['[data-status]', '[data-tipo]', '[data-area]', '[data-titulo]', '[data-descricao]', '[data-atualizar-quadro]', '[data-editar-vaga]', '[data-encerrar-vaga]', '[data-vaga-encerrada]', '[data-encerrada-em]', '[data-soma-pesos]', '[data-requisitos]', '[data-elegiveis]', '[data-colunas]'],
        'modelo-coluna': ['[data-coluna-titulo]', '[data-coluna-total]', '[data-aviso-triagem]', '[data-sem-elegiveis]', '[data-coluna-vazia]', '[data-coluna-itens]'],
        'modelo-card-ranking': ['[data-card-titulo]', '[data-link-perfil]', '[data-posicao]', '[data-codigo-revelado]', '[data-tipo-servidor]', '[data-tipo-estagiario]', '[data-area]', '[data-situacao]', '[data-score]', '[data-score-barra]', '[data-score-texto]', '[data-explicacao]', '[data-competencias]', '[data-experiencia]', '[data-energia]', '[data-proximidade-energia]', '[data-energia-ausente]', '[data-mover-etapa]', '[data-card-detalhes]', '[data-expandir-card]', '[data-adicionar-anotacao]'],
        'modelo-tag-competencia': ['[data-tag-nome]', '[data-tag-peso]', '[data-tag-faltante]', '[data-tag-origem]'],
        'modelo-peso-competencia': ['label', 'select', '[data-erro-campo]'],
        'modelo-eixo-energia': ['label', 'select'],
    };
    const raizes = new Map(['[data-lista-vagas]', '[data-lista-vazia]'].map((seletor) => [seletor, elemento()]));
    const form = elemento(['[data-catalogo="hard"]', '[data-catalogo="soft"]', '[data-eixos-energia]']);
    const campos = new Map(['titulo', 'area', 'tipo_candidato', 'descricao'].map((nome) => [nome, elemento()]));
    form.elements = { namedItem: (nome) => campos.get(nome) };
    raizes.set('[data-formulario-vaga]', form);
    const regiao = elemento();
    return {
        baseURI: 'https://banco.test/vagas/3', form, campos, regiao, inicial: null,
        querySelector: (seletor) => raizes.get(seletor) ?? null,
        createElement: () => elemento(),
        getElementById(id) {
            if (id === 'dados-vagas') { return this.inicial; }
            if (id === 'quadro-vaga') { return regiao; }
            return estruturas[id] ? { content: { firstElementChild: { cloneNode: () => elemento(estruturas[id]) } } } : null;
        },
    };
}

function dadosQuadro() {
    return {
        vaga: { titulo: 'Desenvolvimento', descricao: null, area: 'Dados', tipo: 'Estagiários', status: 'Aberta', aberta: true, encerradaEm: null, temEnergia: true,
            url: '/vagas/3', editarUrl: '/vagas/3/editar', statusUrl: '/vagas/3/status' },
        totalElegiveis: 1,
        requisitos: [{ nome: 'Python', peso: 3 }, { nome: 'SQL', peso: 1 }],
        colunas: { triagem: [{ codigo: 'CAND-0042', nome: null, tipo: 'estagiario', area: 'Dados', etapa: 'triagem', elegivel: true, desativado: false,
            experienciaNaArea: true, proximidadeEnergia: 83, percentual: 75, explicacao: '75% — tem 1 de 2 competências',
            atendidas: [{ nome: 'Python', peso: 3, origem: 'entrevista' }], naoIdentificadas: [{ nome: 'SQL', peso: 1 }],
            perfilUrl: '/vagas/3/candidatos/CAND-0042', etapaUrl: '/vagas/3/candidatos/CAND-0042/etapa', anotacaoUrl: '/vagas/3/candidatos/CAND-0042/anotacoes' }], entrevista: [], aprovado: [], reprovado: [] },
    };
}

const colunas = (quadro) => quadro.querySelector('[data-colunas]').children;
const cards = (coluna) => coluna.querySelector('[data-coluna-itens]').children.map((li) => li.children[0]);

test('lista mantém estrutura, contagens, estado vazio e diferencia vaga encerrada', () => {
    const doc = documento();
    renderizarLista({ vagas: [] }, doc);
    assert.equal(doc.querySelector('[data-lista-vazia]').hidden, false);
    const vaga = { ...dadosQuadro().vaga, aberta: false, status: 'Encerrada', competencias_count: 1, totalElegiveis: 2, entrevista_count: 1, aprovado_count: 0 };
    renderizarLista({ vagas: [vaga] }, doc);
    const card = doc.querySelector('[data-lista-vagas]').children[0];
    assert.equal(doc.querySelector('[data-lista-vazia]').hidden, true);
    assert.equal(card.querySelector('[data-competencias-total]').textContent, '1 competência');
    assert.equal(card.querySelector('[data-elegiveis]').textContent, '2 candidatos elegíveis');
    assert.equal(card.querySelector('[data-abrir-quadro]').textContent, 'Consultar quadro');
    assert.equal(card.querySelector('[data-abrir-quadro]').href, 'https://banco.test/vagas/3');
    assert.ok(card.classList.contains('border-dashed'));
    assert.equal(quantidade(1, 'candidato elegível', 'candidatos elegíveis'), '1 candidato elegível');
});

test('formulário preenche dados, catálogo, pesos e energia sem lógica Blade', () => {
    const doc = documento();
    preencherFormulario({ valores: { titulo: 'Analista', area: 'dados', tipo_candidato: 'ambos', descricao: 'Descrição', competencias: { 7: { peso: 3 } }, energia: { individual_equipe: 4 } },
        oldInput: null, areas: [{ valor: 'dados', rotulo: 'Dados' }], tipos: [{ valor: 'ambos', rotulo: 'Servidores e estagiários' }], catalogo: { hard: [{ id: 7, nome: 'Python' }], soft: [] } }, doc);
    assert.equal(doc.campos.get('titulo').value, 'Analista');
    assert.equal(doc.campos.get('area').value, 'dados');
    assert.equal(doc.campos.get('tipo_candidato').children[0].textContent, 'Servidores e estagiários');
    const linha = doc.form.querySelector('[data-catalogo="hard"]').children[0];
    assert.equal(linha.querySelector('select').name, 'competencias[7][peso]');
    assert.equal(linha.querySelector('select').value, 3);
    assert.equal(linha.querySelector('label').htmlFor, linha.querySelector('select').id);
    assert.equal(doc.form.querySelector('[data-catalogo="soft"]').children[0].textContent, 'Nenhuma competência nesta categoria.');
    assert.equal(doc.form.querySelector('[data-eixos-energia]').children[1].querySelector('select').value, 4);
    assert.equal(doc.form.hidden, false);
});

test('old input substitui todos os valores, inclusive pesos e eixos limpos', () => {
    const doc = documento();
    preencherFormulario({ valores: { titulo: 'Anterior', competencias: { 7: { peso: 3 } }, energia: { individual_equipe: 4 } },
        oldInput: { titulo: 'Tentativa', competencias: { 7: { peso: '' } } }, areas: [], tipos: [], catalogo: { hard: [{ id: 7, nome: 'Python' }], soft: [] } }, doc);
    assert.equal(doc.campos.get('titulo').value, 'Tentativa');
    assert.equal(doc.form.querySelector('[data-catalogo="hard"]').children[0].querySelector('select').value, '');
    assert.equal(doc.form.querySelector('[data-eixos-energia]').children[1].querySelector('select').value, '');
});

test('quadro monta ranking anônimo, ações, tags, score e energia apenas em JavaScript', () => {
    const quadro = montarQuadro(dadosQuadro(), documento());
    assert.equal(quadro.querySelector('[data-soma-pesos]').textContent, 4);
    assert.equal(quadro.querySelector('[data-elegiveis]').textContent, '· 1 candidato elegível');
    const [triagem, entrevista, aprovado] = colunas(quadro);
    assert.deepEqual(colunas(quadro).map((coluna) => coluna.dataset.etapa), ['triagem', 'entrevista', 'aprovado', 'reprovado']);
    assert.equal(triagem.querySelector('[data-coluna-total]').atributos['aria-label'], '1 candidato');
    assert.equal(entrevista.querySelector('[data-coluna-vazia]').hidden, false);
    assert.equal(aprovado.querySelector('[data-coluna-vazia]').textContent, 'Nenhum candidato aprovado ainda.');
    const card = cards(triagem)[0];
    assert.equal(card.draggable, true);
    assert.equal(card.querySelector('[data-link-perfil]').textContent, 'CAND-0042');
    assert.equal(card.querySelector('[data-codigo-revelado]').hidden, true);
    assert.equal(card.querySelector('[data-tipo-servidor]').hidden, true);
    assert.equal(card.querySelector('[data-score]').atributos['aria-label'], 'Aderência de 75%');
    assert.equal(card.querySelector('[data-score-barra]').style.width, '75%');
    assert.ok(card.querySelector('[data-score-barra]').classList.contains('bg-score-alto'));
    assert.equal(card.querySelector('[data-proximidade-energia]').textContent, 'Energia: 83% de proximidade');
    assert.equal(card.querySelector('[data-energia-ausente]').hidden, true);
    const [atendida, faltante] = card.querySelector('[data-competencias]').children;
    assert.equal(atendida.querySelector('[data-tag-origem]').textContent, '· observada');
    assert.equal(faltante.title, 'Não identificado no currículo');
    const acao = card.querySelector('[data-mover-etapa]').children[0];
    assert.equal(acao.value, 'entrevista');
    assert.equal(acao.textContent, 'Mover para Entrevista');
    assert.equal(card.querySelector('[data-mover-etapa]').dataset.etapaAtual, 'triagem');
    assert.equal(card.querySelector('[data-mover-etapa]').dataset.candidatoCodigo, 'CAND-0042');
    assert.equal(card.querySelector('[data-adicionar-anotacao]').hidden, true);
});

test('etapas reveladas oferecem ações corretas e campos de texto nunca usam innerHTML', () => {
    const dados = dadosQuadro();
    const item = dados.colunas.triagem.pop();
    dados.vaga.titulo = '<img src=x onerror=alert(1)>';
    dados.colunas.entrevista = [{ ...item, etapa: 'entrevista', nome: '<script>nome</script>', percentual: 50, proximidadeEnergia: null }];
    dados.colunas.aprovado = [{ ...item, codigo: 'CAND-0043', etapa: 'aprovado', nome: 'Aprovado', percentual: 0 }];
    const quadro = montarQuadro(dados, documento());
    assert.equal(quadro.querySelector('[data-titulo]').textContent, dados.vaga.titulo);
    const card = cards(colunas(quadro)[1])[0];
    assert.equal(card.querySelector('[data-link-perfil]').textContent, '<script>nome</script>');
    assert.equal(card.querySelector('[data-link-perfil]').innerHTML, undefined);
    assert.deepEqual(card.querySelector('[data-mover-etapa]').children.map((botao) => botao.value), ['aprovado', 'reprovado']);
    assert.equal(card.querySelector('[data-adicionar-anotacao]').hidden, false);
    assert.equal(card.querySelector('[data-adicionar-anotacao]').dataset.anotacaoUrl, 'https://banco.test/vagas/3/candidatos/CAND-0042/anotacoes');
    assert.equal(card.querySelector('[data-energia-ausente]').hidden, false);
    assert.ok(card.querySelector('[data-score-texto]').classList.contains('text-score-medio'));
    const aprovado = cards(colunas(quadro)[2])[0];
    assert.deepEqual(aprovado.querySelector('[data-mover-etapa]').children.map((botao) => botao.value), ['entrevista']);
    assert.ok(aprovado.querySelector('[data-score-texto]').classList.contains('text-score-baixo'));
    assert.equal(aprovado.querySelector('[data-adicionar-anotacao]').hidden, true);
});

test('coluna de reprovados mostra contagem, identidade e retorno à entrevista sem anotação no card', () => {
    const dados = dadosQuadro();
    const item = dados.colunas.triagem.pop();
    dados.colunas.reprovado.push({ ...item, etapa: 'reprovado', nome: 'Ana Silva' });

    const coluna = colunas(montarQuadro(dados, documento()))[3];

    assert.equal(coluna.querySelector('[data-coluna-titulo]').textContent, 'Reprovados');
    assert.equal(coluna.querySelector('[data-coluna-total]').textContent, 1);
    const card = cards(coluna)[0];
    assert.equal(card.querySelector('[data-link-perfil]').textContent, 'Ana Silva');
    assert.deepEqual(card.querySelector('[data-mover-etapa]').children.map((botao) => botao.value), ['entrevista']);
    assert.equal(card.querySelector('[data-adicionar-anotacao]').hidden, true);
});

test('cards iniciam compactos com controles de expansão independentes e identificação acessível', () => {
    const dados = dadosQuadro();
    dados.colunas.entrevista.push({ ...dados.colunas.triagem[0], codigo: 'CAND-0043', etapa: 'entrevista', nome: 'Ana Silva' });

    const quadro = montarQuadro(dados, documento());

    const anonimo = cards(colunas(quadro)[0])[0];
    const revelado = cards(colunas(quadro)[1])[0];
    assert.equal(anonimo.querySelector('[data-card-detalhes]').hidden, true);
    assert.equal(anonimo.querySelector('[data-card-detalhes]').id, 'card-detalhes-CAND-0042');
    assert.deepEqual(anonimo.querySelector('[data-expandir-card]').atributos, {
        'aria-controls': 'card-detalhes-CAND-0042',
        'aria-expanded': 'false',
        'aria-label': 'Mostrar competências de CAND-0042',
    });
    assert.equal(revelado.querySelector('[data-card-detalhes]').hidden, true);
    assert.equal(revelado.querySelector('[data-card-detalhes]').id, 'card-detalhes-CAND-0043');
    assert.equal(revelado.querySelector('[data-expandir-card]').atributos['aria-controls'], 'card-detalhes-CAND-0043');
    assert.equal(revelado.querySelector('[data-expandir-card]').atributos['aria-label'], 'Mostrar competências de Ana Silva');
});

test('vaga encerrada e candidatos inelegíveis removem ações e arraste', () => {
    const dados = dadosQuadro();
    dados.vaga.aberta = false;
    dados.vaga.temEnergia = false;
    dados.vaga.editarUrl = null;
    dados.vaga.encerradaEm = '02/11/2026';
    let quadro = montarQuadro(dados, documento());
    assert.equal(quadro.querySelector('[data-editar-vaga]').removido, true);
    assert.equal(quadro.querySelector('[data-encerrar-vaga]').removido, true);
    assert.equal(quadro.querySelector('[data-encerrada-em]').textContent, '02/11/2026');
    let card = cards(colunas(quadro)[0])[0];
    assert.equal(card.draggable, false);
    assert.equal(card.querySelector('[data-mover-etapa]').removido, true);
    assert.equal(card.querySelector('[data-energia]').hidden, true);
    assert.equal(card.querySelector('[data-adicionar-anotacao]').hidden, true);
    dados.vaga.aberta = true;
    dados.vaga.editarUrl = '/vagas/3/editar';
    dados.colunas.triagem[0].elegivel = false;
    dados.colunas.triagem[0].desativado = true;
    quadro = montarQuadro(dados, documento());
    card = cards(colunas(quadro)[0])[0];
    assert.equal(card.querySelector('[data-mover-etapa]').removido, true);
    assert.ok(card.classList.contains('border-dashed'));
    assert.equal(card.querySelector('[data-situacao]').textContent, 'Desativado');
});

test('sem elegíveis mostra estado vazio específico na triagem', () => {
    const dados = dadosQuadro();
    dados.colunas.triagem = [];
    dados.totalElegiveis = 0;
    const triagem = colunas(montarQuadro(dados, documento()))[0];
    assert.equal(triagem.querySelector('[data-sem-elegiveis]').hidden, false);
    assert.equal(triagem.querySelector('[data-coluna-vazia]').hidden, true);
    assert.equal(triagem.querySelector('[data-coluna-itens]').hidden, true);
});

test('JSON substitui o quadro inteiro e remove identidade ao voltar à triagem', () => {
    const doc = documento();
    const dados = dadosQuadro();
    const item = dados.colunas.triagem.pop();
    dados.colunas.entrevista.push({ ...item, etapa: 'entrevista', nome: 'Nome revelado' });
    doc.inicial = { textContent: JSON.stringify(dados), dataset: { tela: 'quadro' }, remove() { doc.inicial = null; } };
    iniciarVagas(doc);
    assert.equal(doc.inicial, null);
    assert.equal(cards(colunas(doc.regiao.children[0])[1])[0].querySelector('[data-link-perfil]').textContent, 'Nome revelado');
    aplicarFragmentos({ message: null, fragments: {}, redirect: null, data: { 'quadro-vaga': dadosQuadro() } }, doc);
    assert.equal(cards(colunas(doc.regiao.children[0])[0])[0].querySelector('[data-link-perfil]').textContent, 'CAND-0042');
    assert.equal(cards(colunas(doc.regiao.children[0])[1]).length, 0);
});

test('dados inválidos e links externos são recusados antes de alterar a região confirmada', () => {
    const doc = documento();
    registrarRenderizador('quadro-vaga', montarQuadro);
    doc.regiao.children = ['anterior'];
    for (const alterar of [
        (dados) => { dados.colunas.triagem[0].nome = 'Identidade em triagem'; },
        (dados) => { dados.colunas.triagem[0].percentual = 101; },
        (dados) => { dados.colunas.triagem[0].perfilUrl = 'https://outro.test/'; },
        (dados) => { dados.vaga.url = 'javascript:alert(1)'; },
        (dados) => { dados.colunas.triagem[0].anotacaoUrl = 'https://outro.test/anotacoes'; },
        (dados) => { delete dados.colunas.triagem[0].perfilUrl; },
        (dados) => { delete dados.colunas.aprovado; },
    ]) {
        const dados = dadosQuadro();
        alterar(dados);
        assert.throws(() => validarQuadro(dados, doc.baseURI));
        assert.throws(() => aplicarFragmentos({ message: null, fragments: {}, redirect: null, data: { 'quadro-vaga': dados } }, doc));
        assert.deepEqual(doc.regiao.children, ['anterior']);
    }
    assert.throws(() => validarEnvelope({ message: null, fragments: {}, redirect: null, data: { 'secao-lgpd': {} } }));
    assert.throws(() => validarEnvelope({ message: null, fragments: { 'quadro-vaga': 'html' }, redirect: null, data: { 'quadro-vaga': dadosQuadro() } }));
});

test('Blades de vagas não decidem estado, iteram dados, consultam modelos ou executam interações', () => {
    const pasta = new URL('../../resources/views/vagas/', import.meta.url);
    const arquivos = [...readdirSync(pasta).filter((nome) => nome.endsWith('.blade.php')), ...readdirSync(new URL('partials/', pasta)).map((nome) => `partials/${nome}`)];
    for (const arquivo of arquivos) {
        const conteudo = readFileSync(new URL(arquivo, pasta), 'utf8');
        assert.doesNotMatch(conteudo, /<\?php|@(if|unless|foreach|forelse|for|while|php|checked|selected|error|class|isset)\b|\b(?:collect|old|session|match)\s*\(|\$\w+->(?!titulo)|=>|\bon\w+\s*=/, arquivo);
        assert.doesNotMatch(conteudo, /<script\b(?![^>]*type="application\/json")/, arquivo);
    }
});
