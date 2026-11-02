import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { aplicarFragmentos, solicitar } from '../../resources/js/modulos/ajax.js';
import { enviarFormulario, registrarFormularios } from '../../resources/js/modulos/formularios.js';

const envelope = (fragments = {}) => ({ message: null, fragments, redirect: null });
const originalFetch = globalThis.fetch;
const originalFormData = globalThis.FormData;
afterEach(() => {
    globalThis.fetch = originalFetch;
    globalThis.FormData = originalFormData;
});

function resposta(dados, status = 200) {
    return new Response(JSON.stringify(dados), { status, headers: { 'Content-Type': 'application/json' } });
}

test('JSON válido usa mesma origem, credenciais e não define multipart manualmente', async () => {
    const body = new FormData();
    body.set('_token', 'teste');
    body.set('_method', 'PATCH');
    globalThis.fetch = async (url, options) => {
        assert.equal(url.href, 'https://banco.test/candidatos');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.mode, 'same-origin');
        assert.equal(options.redirect, 'error');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.headers['Content-Type'], undefined);
        assert.equal(options.body.get('_method'), 'PATCH');
        return resposta(envelope());
    };
    assert.deepEqual(await solicitar('/candidatos', { method: 'POST', body, base: 'https://banco.test/' }), envelope());
});

test('422 mantém os erros por campo', async () => {
    globalThis.fetch = async () => resposta({ message: 'Revise os campos.', errors: { nome: ['Informe o nome.'] } }, 422);
    await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }), (erro) => {
        assert.equal(erro.status, 422);
        assert.deepEqual(erro.errors, { nome: ['Informe o nome.'] });
        return true;
    });
});

for (const status of [401, 419, 403, 404, 413, 503, 500]) {
    test(`erro ${status} é amigável e não expõe detalhes do servidor`, async () => {
        globalThis.fetch = async () => resposta({ message: 'SEGREDO', trace: 'stack' }, status);
        await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }), (erro) => {
            assert.equal(erro.status, status);
            assert.ok(erro.message.length > 10);
            assert.doesNotMatch(erro.message, /SEGREDO|stack/);
            return true;
        });
    });
}

test('HTML inesperado inclusive login não é aplicado; 413 HTML continua amigável', async () => {
    for (const status of [200, 413]) {
        globalThis.fetch = async () => new Response('<html>login</html>', { status });
        await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }), status === 413 ? /10 MB/ : /resposta/i);
    }
    globalThis.fetch = async () => new Response('{interrompido', { status: 413, headers: { 'Content-Type': 'application/json' } });
    await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }), /10 MB/);
});

test('recusa navegação externa e envelope inválido inteiro antes de alterar DOM', async () => {
    const regiao = { innerHTML: 'anterior' };
    const doc = { getElementById: () => regiao };
    for (const dados of [
        { ...envelope(), redirect: 'https://outro.test/' },
        { ...envelope(), redirect: 'javascript:alert(1)' },
        { ...envelope(), message: 1 },
        { ...envelope(), fragments: [] },
        envelope({ 'resultados-candidatos': 'novo', '#arbitrario': 'ruim' }),
        envelope({ 'resultados-candidatos': 123 }),
    ]) {
        globalThis.fetch = async () => resposta(dados);
        await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }));
        assert.throws(() => aplicarFragmentos(dados, doc, 'https://banco.test/'));
        assert.equal(regiao.innerHTML, 'anterior');
    }
});

test('não aplica nenhum fragmento se falta uma das regiões', () => {
    const regiao = { innerHTML: 'anterior' };
    assert.throws(() => aplicarFragmentos(envelope({ 'resultados-candidatos': 'novo', 'secao-lgpd': 'outro' }), {
        getElementById: (id) => id === 'resultados-candidatos' ? regiao : null,
    }, 'https://banco.test/'));
    assert.equal(regiao.innerHTML, 'anterior');
});

function formularioFalso() {
    const atributos = new Map();
    const botao = { disabled: false, textContent: 'Salvar', matches: () => true };
    const campo = { disabled: false, matches: () => false };
    const aviso = { textContent: '', hidden: true, replaceChildren() {} };
    const doc = {
        baseURI: 'https://banco.test/', querySelector: () => aviso, getElementById: () => null,
    };
    return {
        method: 'post', action: 'https://banco.test/salvar', dataset: {}, elements: [campo, botao], ownerDocument: doc,
        getAttribute: (nome) => atributos.get(nome),
        setAttribute: (nome, valor) => atributos.set(nome, valor),
        removeAttribute: (nome) => atributos.delete(nome),
        querySelectorAll: () => [], querySelector: () => aviso,
        campo, botao, aviso,
    };
}

test('serializa antes de bloquear, impede envio duplicado e libera controles após rede falhar sem replay', async () => {
    const form = formularioFalso();
    let chamadas = 0;
    let falhar;
    globalThis.FormData = class {
        constructor(formulario) {
            assert.equal(formulario.campo.disabled, false);
            return new originalFormData();
        }
    };
    globalThis.fetch = () => {
        chamadas++;
        return new Promise((resolve, reject) => { falhar = reject; });
    };
    const envio = enviarFormulario(form);
    assert.equal(form.campo.disabled, true);
    assert.equal(form.botao.textContent, 'Processando…');
    await enviarFormulario(form);
    assert.equal(chamadas, 1);
    falhar(new TypeError('offline'));
    await envio;
    assert.equal(chamadas, 1);
    assert.equal(form.campo.disabled, false);
    assert.equal(form.botao.disabled, false);
    assert.equal(form.botao.textContent, 'Salvar');
    assert.match(form.aviso.textContent, /conferir|confira/i);
});

test('confirmação recusada não envia', async () => {
    const eventos = {};
    const doc = { addEventListener: (nome, callback) => { eventos[nome] = callback; } };
    registrarFormularios(doc);
    const form = formularioFalso();
    form.matches = (seletor) => seletor === 'form[data-ajax]';
    form.dataset.confirmar = 'Excluir?';
    let impedido = false;
    await eventos.submit({ target: form, preventDefault: () => { impedido = true; }, submitter: null });
    assert.equal(impedido, true);
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta(envelope()); };
    await enviarFormulario(form, null, () => false);
    assert.equal(chamadas, 0);
});

test('delegação atende formulários inseridos depois do registro e preserva campos no sucesso', async () => {
    const eventos = {};
    registrarFormularios({ addEventListener: (nome, callback) => { eventos[nome] = callback; } });
    const form = formularioFalso();
    form.campo.value = 'Rascunho preservado';
    form.matches = (seletor) => seletor === 'form[data-ajax]';
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta({ ...envelope(), message: 'Salvo.' }); };

    await eventos.submit({ target: form, preventDefault() {}, submitter: null });
    assert.equal(chamadas, 1);
    assert.equal(form.campo.value, 'Rascunho preservado');
    assert.equal(form.campo.disabled, false);
    assert.equal(form.aviso.textContent, 'Salvo.');
});

test('422 mantém valores, mostra resumo/erro local e foca campo somente após liberá-lo', async () => {
    const form = formularioFalso();
    const attrs = new Map([['aria-describedby', 'nome-ajuda']]);
    const avisos = [];
    form.campo.name = 'nome';
    form.campo.value = 'Rascunho';
    form.campo.id = 'nome';
    form.campo.setAttribute = (k, v) => attrs.set(k, v);
    form.campo.getAttribute = (k) => attrs.get(k);
    form.campo.insertAdjacentElement = (posicao, elemento) => avisos.push(elemento);
    let focado = false;
    form.campo.focus = () => { assert.equal(form.campo.disabled, false); focado = true; };
    form.ownerDocument.createElement = () => ({ dataset: {} });
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    globalThis.fetch = async () => resposta({ message: 'Inválido', errors: { nome: ['Nome obrigatório.'] } }, 422);

    await enviarFormulario(form);
    assert.equal(form.campo.value, 'Rascunho');
    assert.equal(attrs.get('aria-invalid'), 'true');
    assert.match(attrs.get('aria-describedby'), /nome-ajuda ajax-erro-/);
    assert.equal(avisos[0].textContent, 'Nome obrigatório.');
    assert.match(form.aviso.textContent, /Nome obrigatório/);
    assert.equal(focado, true);
});

test('autoenvio não duplica change de texto, envio explícito ou formulário de busca', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const eventos = {};
    registrarFormularios({ addEventListener: (nome, fn) => { eventos[nome] = fn; } });
    let envios = 0;
    const form = formularioFalso();
    form.requestSubmit = () => { envios++; };
    form.hasAttribute = () => false;
    const campo = {
        matches: () => true,
        closest: (seletor) => seletor === 'form[data-autosubmit]' ? form : null,
    };
    eventos.input({ target: campo, type: 'input' });
    eventos.change({ target: campo, type: 'change' });
    t.mock.timers.tick(400);
    assert.equal(envios, 1);
    form.hasAttribute = () => true;
    eventos.input({ target: campo, type: 'input' });
    t.mock.timers.tick(400);
    assert.equal(envios, 1);
});

test('regressão: limite de tentativas tem mensagem própria e nunca é repetido', async () => {
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta({ message: 'Too Many Attempts.' }, 429); };
    await assert.rejects(solicitar('https://banco.test/', { method: 'POST', base: 'https://banco.test/' }), (erro) => {
        assert.equal(erro.status, 429);
        assert.match(erro.message, /Muitas tentativas/);
        return true;
    });
    assert.equal(chamadas, 1);
});

test('regressão: resposta redirecionada (página de login) é falha mesmo com JSON válido', async () => {
    globalThis.fetch = async () => {
        const redirecionada = resposta(envelope());
        Object.defineProperty(redirecionada, 'redirected', { value: true });
        return redirecionada;
    };
    await assert.rejects(solicitar('https://banco.test/', { base: 'https://banco.test/' }), /inesperada/);
});

test('regressão: falha ambígua de escrita não faz segunda chamada nem anuncia sucesso', async () => {
    const form = formularioFalso();
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    const metodos = [];
    globalThis.fetch = async (url, opcoes) => { metodos.push(opcoes.method); throw new TypeError('conexão interrompida'); };

    await enviarFormulario(form);

    assert.deepEqual(metodos, ['POST']);
    assert.match(form.aviso.textContent, /confira o estado/i);
    assert.doesNotMatch(form.aviso.textContent, /concluída/i);
    assert.equal(form.botao.disabled, false);
});

test('regressão: 5xx e HTML de erro não alteram a região confirmada', async () => {
    const form = formularioFalso();
    const regiao = { innerHTML: 'estado confirmado' };
    form.ownerDocument.getElementById = () => regiao;
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    for (const falha of [
        new Response('<html>Erro interno</html>', { status: 500, headers: { 'Content-Type': 'text/html' } }),
        new Response('<html>login</html>', { status: 200, headers: { 'Content-Type': 'text/html' } }),
    ]) {
        globalThis.fetch = async () => falha;
        await enviarFormulario(form);
        assert.equal(regiao.innerHTML, 'estado confirmado');
    }
});

test('regressão: nova tentativa só parte de nova ação do usuário e reaproveita o formulário liberado', async () => {
    const form = formularioFalso();
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    let chamadas = 0;
    globalThis.fetch = async () => {
        chamadas++;
        if (chamadas === 1) {
            throw new TypeError('offline');
        }
        return resposta({ ...envelope(), message: 'Salvo.' });
    };

    await enviarFormulario(form);
    assert.equal(chamadas, 1);
    await enviarFormulario(form);
    assert.equal(chamadas, 2);
    assert.equal(form.aviso.textContent, 'Salvo.');
});

test('regressão: substituir o HTML não duplica listeners; o envio segue delegado no documento', async () => {
    const eventos = {};
    let registros = 0;
    registrarFormularios({ addEventListener: (nome, callback) => { registros++; eventos[nome] = callback; } });
    const registrosIniciais = registros;
    globalThis.FormData = class { constructor() { return new originalFormData(); } };
    let chamadas = 0;
    globalThis.fetch = async () => { chamadas++; return resposta(envelope()); };

    for (let substituicao = 0; substituicao < 3; substituicao++) {
        const novoFormulario = formularioFalso();
        novoFormulario.matches = (seletor) => seletor === 'form[data-ajax]';
        await eventos.submit({ target: novoFormulario, preventDefault() {}, submitter: null });
    }

    assert.equal(chamadas, 3);
    assert.equal(registros, registrosIniciais);
});

test('formulário tradicional com data-carregando mostra Processando… só depois do evento e libera ao voltar', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const eventos = {};
    const janela = {};
    const botao = { disabled: false, textContent: 'Entrar', childNodes: ['Entrar'], replaceChildren(...filhos) { this.textContent = filhos.join(''); } };
    const form = {
        dataset: {}, matches: () => false,
        querySelector: (seletor) => seletor === '[data-carregando]' ? botao : null,
    };
    registrarFormularios({
        addEventListener: (nome, callback) => { eventos[nome] = callback; },
        defaultView: { addEventListener: (nome, callback) => { janela[nome] = callback; } },
        querySelectorAll: () => [botao],
    });

    let impedido = false;
    await eventos.submit({ target: form, preventDefault: () => { impedido = true; }, submitter: botao });
    assert.equal(impedido, false);
    // Bloquear antes de o navegador serializar tiraria o name/value do botão enviado.
    assert.equal(botao.disabled, false);
    t.mock.timers.tick(0);
    assert.equal(botao.disabled, true);
    assert.equal(botao.textContent, 'Processando…');

    janela.pageshow();
    assert.equal(botao.disabled, false);
    assert.equal(botao.textContent, 'Entrar');
});

test('formulário tradicional sem data-carregando ou com confirmação recusada segue sem indicar envio', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const eventos = {};
    registrarFormularios({ addEventListener: (nome, callback) => { eventos[nome] = callback; } });
    const botao = { disabled: false, textContent: 'Excluir', childNodes: [] };
    const recusado = {
        dataset: { confirmar: 'Excluir?' }, matches: () => false,
        querySelector: () => botao,
    };
    const confirmOriginal = globalThis.confirm;
    globalThis.confirm = () => false;
    try {
        let impedido = false;
        await eventos.submit({ target: recusado, preventDefault: () => { impedido = true; }, submitter: botao });
        assert.equal(impedido, true);
    } finally {
        globalThis.confirm = confirmOriginal;
    }
    t.mock.timers.tick(0);
    assert.equal(botao.disabled, false);
    assert.equal(botao.textContent, 'Excluir');

    await eventos.submit({ target: { dataset: {}, matches: () => false, querySelector: () => null }, preventDefault() {}, submitter: null });
});
