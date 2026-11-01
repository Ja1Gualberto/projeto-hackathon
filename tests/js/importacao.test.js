import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { mesclarFragmento, mostrarErros } from '../../resources/js/modulos/ajax.js';
import { enviarFormulario } from '../../resources/js/modulos/formularios.js';
import { registrarImportacao } from '../../resources/js/modulos/importacao.js';

const envelope = (fragments = {}, message = null) => ({ message, fragments, redirect: null });
const originalFetch = globalThis.fetch;
const originalFormData = globalThis.FormData;
afterEach(() => {
    globalThis.fetch = originalFetch;
    globalThis.FormData = originalFormData;
});

function resposta(dados, status = 200) {
    return new Response(JSON.stringify(dados), { status, headers: { 'Content-Type': 'application/json' } });
}

function chip(chave, grupo) {
    return { dataset: { chave }, closest: (seletor) => seletor === '[data-grupo]' ? { dataset: { grupo } } : null };
}

function catalogoFalso(existentes = ['1']) {
    const itens = existentes.map((chave) => chip(chave, 'hard'));
    const recebidos = { hard: [], soft: [] };
    const vazios = { hard: { removido: false }, soft: { removido: false } };
    const grupo = (nome) => ({
        dataset: { grupo: nome },
        querySelector: (seletor) => {
            if (seletor === '[data-grupo-itens]') {
                return { append: (no) => { recebidos[nome].push(no); itens.push(no); } };
            }
            return seletor === '[data-grupo-vazio]' ? { remove: () => { vazios[nome].removido = true; } } : null;
        },
    });
    const regiao = {
        innerHTML: 'catálogo com seleções do usuário',
        querySelectorAll: (seletor) => seletor === '[data-chave]' ? [...itens] : [grupo('hard'), grupo('soft')],
    };
    return { regiao, recebidos, vazios, itens };
}

function documentoCom(regiao, novos) {
    return {
        baseURI: 'https://banco.test/candidatos/importar/abc',
        getElementById: (id) => id === 'catalogo-competencias' ? regiao : null,
        createElement: (nome) => {
            assert.equal(nome, 'template');
            return { content: { querySelectorAll: () => novos } };
        },
        importNode: (no) => no,
        querySelector: () => null,
    };
}

test('mescla somente opções novas no grupo certo, sem substituir seleções existentes', () => {
    const { regiao, recebidos, vazios } = catalogoFalso(['1', '2']);
    const doc = documentoCom(regiao, [chip('1', 'hard'), chip('9', 'soft'), chip('2', 'hard')]);

    const novos = mesclarFragmento(envelope({ 'catalogo-competencias': '<fieldset>…</fieldset>' }), 'catalogo-competencias', doc);

    assert.deepEqual(novos, ['9']);
    assert.equal(regiao.innerHTML, 'catálogo com seleções do usuário');
    assert.deepEqual(recebidos.hard, []);
    assert.equal(recebidos.soft[0].dataset.chave, '9');
    assert.equal(vazios.soft.removido, true);
    assert.equal(vazios.hard.removido, false);
});

test('mesclagem recusa regiões inesperadas ou ausentes antes de alterar o DOM', () => {
    const { regiao, itens } = catalogoFalso();
    const doc = documentoCom(regiao, [chip('5', 'hard')]);

    assert.throws(() => mesclarFragmento(envelope({ 'catalogo-competencias': 'a', 'secao-lgpd': 'b' }), 'catalogo-competencias', doc));
    assert.throws(() => mesclarFragmento(envelope({ 'resultados-candidatos': 'a' }), 'catalogo-competencias', doc));
    assert.throws(() => mesclarFragmento(envelope({ 'catalogo-competencias': 'a' }), 'catalogo-competencias', { ...doc, getElementById: () => null }));
    assert.equal(itens.length, 1);
});

test('formulário com data-ajax-mesclar mescla o catálogo e avisa o sucesso com as opções novas', async () => {
    const { regiao, recebidos } = catalogoFalso(['1']);
    const doc = documentoCom(regiao, [chip('1', 'hard'), chip('7', 'hard')]);
    const aviso = { textContent: '' };
    doc.querySelector = () => aviso;
    const eventos = [];
    const botao = { disabled: false, textContent: 'Adicionar ao catálogo', matches: () => true, childNodes: [], replaceChildren() {} };
    const form = {
        method: 'post', action: 'https://banco.test/competencias', dataset: { ajaxMesclar: 'catalogo-competencias' },
        elements: [botao], ownerDocument: doc,
        setAttribute() {}, removeAttribute() {}, querySelectorAll: () => [], querySelector: () => null,
        dispatchEvent: (evento) => { eventos.push(evento); return true; },
    };
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    globalThis.fetch = async () => resposta(envelope({ 'catalogo-competencias': '<div></div>' }, 'Competência adicionada ao catálogo.'));

    await enviarFormulario(form);

    assert.equal(regiao.innerHTML, 'catálogo com seleções do usuário');
    assert.equal(recebidos.hard[0].dataset.chave, '7');
    assert.equal(aviso.textContent, 'Competência adicionada ao catálogo.');
    assert.equal(eventos.length, 1);
    assert.equal(eventos[0].type, 'ajax:sucesso');
    assert.deepEqual(eventos[0].detail.novos, ['7']);
});

test('erro de grupo de rádios aparece uma única vez e marca todas as opções', () => {
    const criados = [];
    const radio = (id) => {
        const atributos = new Map();
        return {
            id, name: 'tipo', type: 'radio', atributos,
            setAttribute: (k, v) => atributos.set(k, v), getAttribute: (k) => atributos.get(k),
            insertAdjacentElement: (posicao, elemento) => criados.push(elemento),
        };
    };
    const servidor = radio('tipo-servidor');
    const estagiario = radio('tipo-estagiario');
    const resumo = { textContent: '', hidden: true, focus() {} };
    const form = {
        elements: [servidor, estagiario],
        ownerDocument: { createElement: () => ({ dataset: {} }), querySelector: () => null },
        querySelector: () => resumo, querySelectorAll: () => [],
    };

    mostrarErros(form, { message: 'Revise.', status: 422, errors: { tipo: ['Selecione o tipo de candidato.'] } }, { focar: false });

    assert.equal(criados.length, 1);
    assert.equal(criados[0].textContent, 'Selecione o tipo de candidato.');
    assert.equal(servidor.atributos.get('aria-invalid'), 'true');
    assert.equal(estagiario.atributos.get('aria-invalid'), 'true');
    assert.equal(servidor.atributos.get('aria-describedby'), estagiario.atributos.get('aria-describedby'));
});

function registrar() {
    const eventos = {};
    const focados = [];
    const input = { focus: () => focados.push('chip-9') };
    const regiao = { querySelectorAll: () => [{ dataset: { chave: '3' }, querySelector: () => null }, { dataset: { chave: '9' }, querySelector: () => input }] };
    const doc = {
        addEventListener: (nome, fn) => { eventos[nome] = fn; },
        getElementById: (id) => id === 'catalogo-competencias' ? regiao : null,
    };
    registrarImportacao(doc);
    return { eventos, focados };
}

test('ignorar remove a sugestão sem chamar o servidor', () => {
    const { eventos } = registrar();
    let removida = false;
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; };
    const sugestao = { remove: () => { removida = true; }, nextElementSibling: null };
    const botao = { closest: (seletor) => seletor === '[data-sugestao]' ? sugestao : null };

    eventos.click({ target: { closest: (seletor) => seletor === '[data-ignorar-sugestao]' ? botao : null }, preventDefault() {} });

    assert.equal(removida, true);
    assert.equal(chamadas, 0);
});

test('sugestão incluída no catálogo some da lista e o foco vai para a nova opção marcada', () => {
    const { eventos, focados } = registrar();
    let removida = false;
    const sugestao = { remove: () => { removida = true; } };
    const form = {
        matches: (seletor) => seletor === '[data-sugestao-form]',
        closest: (seletor) => seletor === '[data-sugestao]' ? sugestao : null,
    };

    eventos['ajax:sucesso']({ target: form, detail: { novos: ['9'] } });

    assert.equal(removida, true);
    assert.deepEqual(focados, ['chip-9']);
});

test('sucesso de outros formulários não remove sugestões', () => {
    const { eventos, focados } = registrar();
    eventos['ajax:sucesso']({ target: { matches: () => false }, detail: { novos: ['9'] } });
    assert.deepEqual(focados, []);
});
