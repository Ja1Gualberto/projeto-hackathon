import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { enviarFormulario } from '../../resources/js/modulos/formularios.js';

const fetchOriginal = globalThis.fetch;
const formDataOriginal = globalThis.FormData;
afterEach(() => {
    globalThis.fetch = fetchOriginal;
    globalThis.FormData = formDataOriginal;
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
