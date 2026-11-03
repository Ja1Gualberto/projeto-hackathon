import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { enviarFormulario, registrarFormularios } from '../../resources/js/modulos/formularios.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
const locationOriginal = globalThis.location;
afterEach(() => {
    globalThis.fetch = fetchOriginal;
    globalThis.FormData = formDataOriginal;
    globalThis.location = locationOriginal;
});

function resposta(dados, status = 200) {
    return new Response(JSON.stringify(dados), { status, headers: { 'Content-Type': 'application/json' } });
}

/**
 * Formulário de anotação: fica fora das regiões atualizadas e tem data-limpar-apos-sucesso.
 */
function montarAnotacao() {
    const aviso = { textContent: '' };
    const regioes = {
        'historico-anotacoes': { innerHTML: 'histórico anterior', querySelector: () => null },
        'competencias-candidato': { innerHTML: 'competências anteriores', querySelector: () => null },
    };
    const eventos = [];
    const atributos = new Map();
    const texto = {
        name: 'texto', id: 'campo-texto', value: 'Rascunho da entrevista', disabled: false, matches: () => false, childNodes: null,
        focado: false, focus() { this.focado = true; },
        setAttribute: (chave, valor) => atributos.set(chave, valor), getAttribute: (chave) => atributos.get(chave),
        insertAdjacentElement() {},
    };
    const botao = { disabled: false, textContent: 'Registrar anotação', matches: () => true, childNodes: [], replaceChildren() {} };
    const doc = {
        baseURI: 'https://banco.test/candidatos/7',
        getElementById: (id) => regioes[id] ?? null,
        querySelector: (seletor) => seletor === '[data-ajax-anuncios]' ? aviso : null,
        createElement: () => ({ dataset: {}, setAttribute() {}, focus() {} }),
    };
    const form = {
        method: 'post', action: 'https://banco.test/candidatos/7/anotacoes', dataset: {},
        elements: [texto, botao], ownerDocument: doc, isConnected: true, resetado: 0,
        hasAttribute: (nome) => nome === 'data-limpar-apos-sucesso',
        reset() { this.resetado++; texto.value = ''; },
        querySelector: (seletor) => seletor === '[data-foco-apos-limpar]' ? texto : null,
        querySelectorAll: () => [], setAttribute() {}, removeAttribute() {}, prepend() {},
        dispatchEvent: (evento) => { eventos.push(evento.type); return true; },
    };
    globalThis.FormData = class { constructor() { return new formDataOriginal(); } };
    return { form, texto, regioes, aviso, eventos };
}

test('anotação registrada atualiza histórico e competências e só então limpa o formulário', async () => {
    const anotacao = montarAnotacao();
    globalThis.fetch = async () => resposta({
        message: 'Anotação registrada.',
        fragments: { 'historico-anotacoes': 'histórico novo', 'competencias-candidato': 'competências novas' },
        redirect: null,
    });

    await enviarFormulario(anotacao.form);

    assert.equal(anotacao.regioes['historico-anotacoes'].innerHTML, 'histórico novo');
    assert.equal(anotacao.regioes['competencias-candidato'].innerHTML, 'competências novas');
    assert.equal(anotacao.form.resetado, 1);
    assert.equal(anotacao.texto.value, '');
    assert.equal(anotacao.texto.disabled, false);
    assert.equal(anotacao.texto.focado, true);
    assert.equal(anotacao.aviso.textContent, 'Anotação registrada.');
    assert.deepEqual(anotacao.eventos, ['ajax:sucesso']);
});

test('erro de validação ou de rede mantém o rascunho e o histórico', async () => {
    const anotacao = montarAnotacao();
    globalThis.fetch = async () => resposta({ message: 'Revise.', errors: { texto: ['A anotação pode ter até 5000 caracteres.'] } }, 422);

    await enviarFormulario(anotacao.form);

    assert.equal(anotacao.form.resetado, 0);
    assert.equal(anotacao.texto.value, 'Rascunho da entrevista');
    assert.equal(anotacao.regioes['historico-anotacoes'].innerHTML, 'histórico anterior');

    globalThis.fetch = async () => { throw new TypeError('Failed to fetch'); };
    await enviarFormulario(anotacao.form);

    assert.equal(anotacao.form.resetado, 0);
    assert.equal(anotacao.texto.value, 'Rascunho da entrevista');
    assert.match(anotacao.aviso.textContent, /Não foi possível confirmar a operação/);
});

test('anotação salva recolhe o formulário e devolve foco a adicionar sem fechar a modal do histórico', async () => {
    const ui = montarAnotacao();
    let focado = false;
    const regiao = { id: 'nova-anotacao', hidden: false };
    const atributos = new Map();
    const controle = { focus: () => { focado = true; }, setAttribute: (nome, valor) => atributos.set(nome, valor) };
    const consultar = ui.form.ownerDocument.querySelector;
    ui.form.ownerDocument.querySelector = (seletor) => seletor === '[data-alternar-regiao="nova-anotacao"]' ? controle : consultar(seletor);
    ui.form.hasAttribute = (nome) => ['data-limpar-apos-sucesso', 'data-recolher-apos-sucesso'].includes(nome);
    ui.form.closest = (seletor) => seletor === '[data-regiao-recolhivel]' ? regiao : null;
    globalThis.fetch = async () => resposta({
        message: 'Anotação registrada.', fragments: { 'historico-anotacoes': 'Nova anotação' }, redirect: null,
    });

    await enviarFormulario(ui.form);

    assert.equal(ui.regioes['historico-anotacoes'].innerHTML, 'Nova anotação');
    assert.equal(ui.form.resetado, 1);
    assert.equal(regiao.hidden, true);
    assert.equal(atributos.get('aria-expanded'), 'false');
    assert.equal(focado, true);
    assert.equal(ui.texto.focado, false);
});

test('anotação salva na modal de criação atualiza a lista e limpa o formulário sem tentar recolher uma região inexistente', async () => {
    const ui = montarAnotacao();
    ui.form.hasAttribute = (nome) => ['data-limpar-apos-sucesso', 'data-recolher-apos-sucesso'].includes(nome);
    ui.form.closest = () => null;
    globalThis.fetch = async () => resposta({
        message: 'Anotação registrada.',
        fragments: { 'historico-anotacoes': 'Três anotações recentes', 'competencias-candidato': 'Competências atualizadas' },
        redirect: null,
    });

    await enviarFormulario(ui.form);

    assert.equal(ui.regioes['historico-anotacoes'].innerHTML, 'Três anotações recentes');
    assert.equal(ui.regioes['competencias-candidato'].innerHTML, 'Competências atualizadas');
    assert.equal(ui.form.resetado, 1);
    assert.equal(ui.texto.value, '');
    assert.equal(ui.texto.focado, true);
    assert.equal(ui.aviso.textContent, 'Anotação registrada.');
});

test('anotação inválida mantém formulário aberto e rascunho na modal', async () => {
    const ui = montarAnotacao();
    const regiao = { hidden: false };
    ui.form.hasAttribute = () => true;
    ui.form.closest = () => regiao;
    globalThis.fetch = async () => resposta({ message: 'Revise.', errors: { texto: ['Texto inválido.'] } }, 422);

    await enviarFormulario(ui.form);

    assert.equal(regiao.hidden, false);
    assert.equal(ui.form.resetado, 0);
    assert.equal(ui.texto.value, 'Rascunho da entrevista');
});

test('modal de anotação com erros abre ao carregar e pode ser aberta e fechada pelos controles', () => {
    const eventos = {};
    let aberturas = 0;
    let fechamentos = 0;
    const dialogo = { showModal: () => { aberturas++; }, close: () => { fechamentos++; } };
    registrarFormularios({
        querySelectorAll: (seletor) => seletor === 'dialog[data-abrir-ao-carregar]' ? [dialogo] : [],
        getElementById: (id) => id === 'anotacoes-entrevista' ? dialogo : null,
        addEventListener: (nome, callback) => { eventos[nome] = callback; },
    });

    assert.equal(aberturas, 1);
    eventos.click({
        target: { closest: () => ({ dataset: { abrirModal: 'anotacoes-entrevista' } }) }, preventDefault() {},
    });
    assert.equal(aberturas, 2);
    eventos.click({
        target: { closest: (seletor) => ['[data-fechar-modal]', 'dialog'].includes(seletor) ? dialogo : null }, preventDefault() {},
    });
    assert.equal(fechamentos, 1);
});

test('botão no cabeçalho alterna o formulário de anotação e foca o campo ao abrir', () => {
    const eventos = {};
    let focos = 0;
    const atributos = new Map();
    const regiao = { hidden: true, querySelector: () => ({ focus: () => { focos++; } }) };
    const botao = { dataset: { alternarRegiao: 'nova-anotacao' }, setAttribute: (nome, valor) => atributos.set(nome, valor) };
    registrarFormularios({
        getElementById: (id) => id === 'nova-anotacao' ? regiao : null,
        addEventListener: (nome, callback) => { eventos[nome] = callback; },
    });
    const evento = {
        target: { closest: (seletor) => seletor === '[data-alternar-regiao]' ? botao : null }, preventDefault() {},
    };

    eventos.click(evento);

    assert.equal(regiao.hidden, false);
    assert.equal(atributos.get('aria-expanded'), 'true');
    assert.equal(focos, 1);

    eventos.click(evento);

    assert.equal(regiao.hidden, true);
    assert.equal(atributos.get('aria-expanded'), 'false');
    assert.equal(focos, 1);
});

test('status confirmado substitui as duas regiões LGPD e restaura foco sem limpar outros formulários', async () => {
    const ui = montarAnotacao();
    const foco = { focado: false, focus() { this.focado = true; } };
    ui.form.action = 'https://banco.test/candidatos/7/status';
    ui.form.hasAttribute = () => false;
    ui.regioes['situacao-candidato'] = { innerHTML: 'Ativo', querySelector: () => foco };
    ui.regioes['secao-lgpd'] = {
        set innerHTML(html) { this.html = html; ui.form.isConnected = false; },
        querySelector: () => null,
    };
    globalThis.fetch = async () => resposta({
        message: 'Candidato desativado.', redirect: null,
        fragments: { 'situacao-candidato': 'Desativado', 'secao-lgpd': 'Reativar' },
    });

    await enviarFormulario(ui.form);

    assert.equal(ui.regioes['situacao-candidato'].innerHTML, 'Desativado');
    assert.equal(ui.regioes['secao-lgpd'].html, 'Reativar');
    assert.equal(foco.focado, true);
    assert.equal(ui.form.resetado, 0);
    assert.equal(ui.texto.value, 'Rascunho da entrevista');
});

test('exclusão não repete envio ambíguo nem navega em falha; só navega após confirmação do servidor', async () => {
    const ui = montarAnotacao();
    ui.form.action = 'https://banco.test/candidatos/7';
    ui.form.hasAttribute = () => false;
    const navegacoes = [];
    globalThis.location = { assign: (url) => navegacoes.push(url) };
    globalThis.FormData = class {
        constructor() {
            const dados = new formDataOriginal();
            dados.append('_method', 'DELETE');
            dados.append('motivo', 'pedido_titular');
            dados.append('confirmacao', '1');
            return dados;
        }
    };
    let chamadas = 0;
    let falhar;
    globalThis.fetch = (url, options) => {
        chamadas++;
        assert.equal(options.body.get('_method'), 'DELETE');
        assert.equal(options.body.get('confirmacao'), '1');
        return new Promise((resolve, reject) => { falhar = reject; });
    };

    const envio = enviarFormulario(ui.form);
    await enviarFormulario(ui.form);
    falhar(new TypeError('Failed to fetch'));
    await envio;

    assert.equal(chamadas, 1);
    assert.deepEqual(navegacoes, []);
    assert.equal(ui.texto.disabled, false);
    assert.match(ui.aviso.textContent, /Não foi possível confirmar a operação/);

    globalThis.fetch = async () => resposta({
        message: 'Dados do candidato excluídos definitivamente.', fragments: {}, redirect: 'https://banco.test/candidatos',
    });
    await enviarFormulario(ui.form);

    assert.deepEqual(navegacoes, ['https://banco.test/candidatos']);
    assert.equal(ui.form.resetado, 0);
});

test('energia indisponível preserva perfil e rascunho, bloqueia duplo envio e libera controles sem retry', async () => {
    const ui = montarAnotacao();
    ui.form.action = 'https://banco.test/candidatos/7/energia';
    ui.form.hasAttribute = () => false;
    ui.regioes['secao-energia'] = { innerHTML: 'Perfil revisado por humano' };
    let concluir;
    let chamadas = 0;
    globalThis.fetch = () => {
        chamadas++;
        return new Promise((resolve) => { concluir = resolve; });
    };

    const envio = enviarFormulario(ui.form);
    await enviarFormulario(ui.form);
    assert.equal(ui.texto.disabled, true);
    assert.equal(ui.aviso.textContent, 'Processando…');
    concluir(resposta({ message: 'Análise indisponível no momento.' }, 503));
    await envio;

    assert.equal(chamadas, 1);
    assert.equal(ui.texto.disabled, false);
    assert.equal(ui.form.resetado, 0);
    assert.equal(ui.texto.value, 'Rascunho da entrevista');
    assert.equal(ui.regioes['secao-energia'].innerHTML, 'Perfil revisado por humano');
    assert.equal(ui.aviso.textContent, 'Análise indisponível no momento.');
});

test('ajuste de energia confirmado substitui apenas a seção e restaura foco após remover o formulário modal', async () => {
    const ui = montarAnotacao();
    ui.form.action = 'https://banco.test/candidatos/7/energia';
    ui.form.hasAttribute = () => false;
    const foco = { focado: false, focus() { this.focado = true; } };
    ui.regioes['secao-energia'] = {
        set innerHTML(html) { this.html = html; ui.form.isConnected = false; },
        querySelector: () => foco,
    };
    globalThis.fetch = async () => resposta({
        message: 'Padrão de energia revisado por humano.', redirect: null,
        fragments: { 'secao-energia': 'Eixos e justificativa revisados' },
    });

    await enviarFormulario(ui.form);

    assert.equal(ui.regioes['secao-energia'].html, 'Eixos e justificativa revisados');
    assert.equal(ui.regioes['historico-anotacoes'].innerHTML, 'histórico anterior');
    assert.equal(ui.regioes['competencias-candidato'].innerHTML, 'competências anteriores');
    assert.equal(foco.focado, true);
    assert.equal(ui.form.resetado, 0);
});
