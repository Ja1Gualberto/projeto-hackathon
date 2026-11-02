import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mostrarAlerta, registrarAlertas } from '../../resources/js/modulos/alertas.js';
import { anunciar, mostrarErros } from '../../resources/js/modulos/ajax.js';

function montar(t, { inicial = false, modal = false, hidden = false, movimentoReduzido = false } = {}) {
    t.mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 0 });
    const doc = { hidden, activeElement: null };
    function elemento(dataset = {}) {
        const ouvintes = new Map();
        const atributos = new Map();
        return {
            dataset, children: [], parent: null, isConnected: true, textContent: '',
            addEventListener(nome, fn) {
                if (!ouvintes.has(nome)) { ouvintes.set(nome, new Set()); }
                ouvintes.get(nome).add(fn);
            },
            removeEventListener: (nome, fn) => ouvintes.get(nome)?.delete(fn),
            emitir(nome, evento = {}) { ouvintes.get(nome)?.forEach((fn) => fn(evento)); },
            setAttribute: (nome, valor) => atributos.set(nome, valor),
            getAttribute: (nome) => atributos.get(nome),
            removeAttribute: (nome) => atributos.delete(nome),
            focus() { doc.activeElement = this; this.parent?.emitir('focusin'); },
            append(filho) {
                filho.remove();
                this.children.push(filho);
                filho.parent = this;
                filho.isConnected = true;
            },
            remove() {
                if (this.parent) {
                    this.parent.children = this.parent.children.filter((filho) => filho !== this);
                }
                this.parent = null;
                this.isConnected = false;
            },
            contains(alvo) { return alvo === this || this.children.some((filho) => filho.contains(alvo)); },
            matches: (seletor) => seletor === '[data-alerta]' && 'alerta' in dataset,
            querySelectorAll() { return []; },
            querySelector(seletor) {
                return this.children.find((filho) => `[data-${Object.keys(filho.dataset)[0]?.replace(/[A-Z]/g, (letra) => `-${letra.toLowerCase()}`)}]` === seletor) ?? null;
            },
        };
    }
    function criarAlerta(tipo) {
        const alerta = elemento({ alerta: '', alertaTipo: tipo, alertaDuracao: '4500' });
        alerta.append(elemento({ alertaMensagem: '' }));
        alerta.append(elemento({ alertaFechar: '' }));
        alerta.cloneNode = () => criarAlerta(tipo);
        return alerta;
    }
    const central = elemento();
    const anuncios = elemento();
    const origem = elemento();
    doc.activeElement = origem;
    const dialogo = modal ? elemento() : null;
    const modelos = Object.fromEntries(['sucesso', 'erro', 'aviso', 'info'].map((tipo) => [tipo, { content: { firstElementChild: criarAlerta(tipo) } }]));
    const iniciais = inicial ? [criarAlerta('aviso')] : [];
    const eventos = elemento();
    doc.addEventListener = eventos.addEventListener;
    doc.removeEventListener = eventos.removeEventListener;
    doc.emitir = eventos.emitir;
    doc.createElement = () => elemento();
    doc.getElementById = () => origem;
    doc.querySelectorAll = () => iniciais;
    doc.querySelector = (seletor) => {
        const fixos = { '[data-alertas]': central, '[data-ajax-anuncios]': anuncios, 'dialog[open]': dialogo };
        return fixos[seletor] ?? modelos[seletor.match(/^\[data-alerta-modelo="(.+)"\]$/)?.[1]] ?? null;
    };
    let observar;
    doc.defaultView = {
        matchMedia: () => ({ matches: movimentoReduzido }),
        MutationObserver: class {
            constructor(fn) { observar = fn; }
            observe() {}
        },
    };

    return { doc, central, anuncios, origem, dialogo, modelos, iniciais, criarAlerta, elemento, inserir: (no) => observar([{ addedNodes: [no] }]) };
}

for (const tipo of ['sucesso', 'info', 'erro', 'aviso']) {
    test(`${tipo} aparece sem roubar foco e inicia o fade após 4,5 segundos`, (t) => {
        const ui = montar(t);

        const alerta = mostrarAlerta('Mensagem da operação.', ui.doc, tipo);

        assert.equal(ui.central.children[0], alerta);
        assert.equal(alerta.dataset.alertaTipo, tipo);
        assert.equal(ui.doc.activeElement, ui.origem);
        t.mock.timers.tick(4499);
        assert.equal(ui.central.children.length, 1);
        assert.equal(alerta.dataset.alertaSaindo, undefined);
        t.mock.timers.tick(1);
        assert.equal(alerta.dataset.alertaSaindo, '');
        assert.equal(alerta.getAttribute('inert'), '');
        assert.equal(alerta.isConnected, true);
        t.mock.timers.tick(299);
        assert.equal(alerta.isConnected, true);
        t.mock.timers.tick(1);
        assert.equal(ui.central.children.length, 0);
    });
}

test('alerta sem duração explícita também inicia o fade após 4,5 segundos', (t) => {
    const ui = montar(t);
    const alerta = ui.criarAlerta('info');
    delete alerta.dataset.alertaDuracao;
    registrarAlertas(ui.doc);

    ui.inserir(alerta);

    t.mock.timers.tick(4499);
    assert.equal(alerta.dataset.alertaSaindo, undefined);
    t.mock.timers.tick(1);
    assert.equal(alerta.dataset.alertaSaindo, '');
    t.mock.timers.tick(300);
    assert.equal(alerta.isConnected, false);
});

test('preferência por movimento reduzido fecha sem aguardar animação', (t) => {
    const ui = montar(t, { movimentoReduzido: true });
    const alerta = mostrarAlerta('Informação.', ui.doc);

    t.mock.timers.tick(4499);

    assert.equal(alerta.isConnected, true);
    t.mock.timers.tick(1);
    assert.equal(alerta.isConnected, false);
});

test('X fecha somente o alerta escolhido e devolve o foco ao controle anterior', (t) => {
    const ui = montar(t);
    const primeiro = mostrarAlerta('Primeiro.', ui.doc);
    const segundo = mostrarAlerta('Segundo.', ui.doc);
    const fechar = primeiro.querySelector('[data-alerta-fechar]');
    fechar.focus();

    fechar.emitir('click');

    assert.equal(primeiro.dataset.alertaSaindo, '');
    assert.equal(segundo.dataset.alertaSaindo, undefined);
    assert.equal(ui.doc.activeElement, ui.origem);
    t.mock.timers.tick(300);
    assert.deepEqual(ui.central.children, [segundo]);
    t.mock.timers.tick(4200);
    t.mock.timers.tick(300);
    assert.equal(ui.central.children.length, 0);
});

test('mouse e foco pausam a contagem independentemente e retomam somente o tempo restante', (t) => {
    const ui = montar(t);
    const alerta = mostrarAlerta('Leia com calma.', ui.doc);
    t.mock.timers.tick(2000);
    alerta.emitir('mouseenter');
    alerta.emitir('focusin');
    t.mock.timers.tick(10000);
    alerta.emitir('mouseleave');
    t.mock.timers.tick(10000);
    assert.equal(alerta.isConnected, true);

    alerta.emitir('focusout', { relatedTarget: ui.origem });

    t.mock.timers.tick(2499);
    assert.equal(alerta.isConnected, true);
    assert.equal(alerta.dataset.alertaSaindo, undefined);
    t.mock.timers.tick(1);
    assert.equal(alerta.dataset.alertaSaindo, '');
    t.mock.timers.tick(300);
    assert.equal(alerta.isConnected, false);
});

test('aba oculta não consome o período de leitura do alerta', (t) => {
    const ui = montar(t, { hidden: true });
    const alerta = mostrarAlerta('Informação.', ui.doc);
    t.mock.timers.tick(30000);
    assert.equal(alerta.isConnected, true);

    ui.doc.hidden = false;
    ui.doc.emitir('visibilitychange');

    t.mock.timers.tick(4499);
    assert.equal(alerta.isConnected, true);
    assert.equal(alerta.dataset.alertaSaindo, undefined);
    t.mock.timers.tick(1);
    assert.equal(alerta.dataset.alertaSaindo, '');
    t.mock.timers.tick(300);
    assert.equal(alerta.isConnected, false);
});

test('alertas iniciais e de fragmentos AJAX vão para a central sem duplicar inicialização', (t) => {
    const ui = montar(t, { inicial: true });
    registrarAlertas(ui.doc);
    registrarAlertas(ui.doc);
    const dinamico = ui.criarAlerta('sucesso');

    ui.inserir(dinamico);
    ui.inserir(dinamico);

    assert.deepEqual(ui.central.children, [ui.iniciais[0], dinamico]);
    t.mock.timers.tick(4500);
    assert.equal(ui.iniciais[0].dataset.alertaSaindo, '');
    assert.equal(dinamico.dataset.alertaSaindo, '');
    t.mock.timers.tick(300);
    assert.equal(ui.central.children.length, 0);
});

test('alerta de formulário modal fica dentro do diálogo para continuar acessível', (t) => {
    const ui = montar(t, { modal: true });

    const alerta = mostrarAlerta('Revise os campos.', ui.doc, 'erro');

    assert.equal(ui.central.children.length, 0);
    assert.equal(ui.dialogo.contains(alerta), true);
    t.mock.timers.tick(4500);
    assert.equal(alerta.dataset.alertaSaindo, '');
    t.mock.timers.tick(300);
    assert.equal(alerta.isConnected, false);
});

test('conteúdo dinâmico é texto e tipo desconhecido usa informação', (t) => {
    const ui = montar(t);
    const mensagem = '<img src=x onerror=alert(1)>';

    const alerta = mostrarAlerta(mensagem, ui.doc, 'desconhecido');

    assert.equal(alerta.dataset.alertaTipo, 'info');
    assert.equal(alerta.querySelector('[data-alerta-mensagem]').textContent, mensagem);
    assert.equal(alerta.querySelector('[data-alerta-mensagem]').children.length, 0);
});

test('progresso continua só no leitor de tela e resultado vira alerta flutuante', (t) => {
    const ui = montar(t);
    anunciar('Processando…', ui.doc);
    assert.equal(ui.anuncios.textContent, 'Processando…');
    assert.equal(ui.central.children.length, 0);

    anunciar('Renata Barros está agora em Entrevista.', ui.doc, 'sucesso');

    assert.equal(ui.anuncios.textContent, '');
    assert.equal(ui.central.children[0].dataset.alertaTipo, 'sucesso');
    assert.equal(ui.central.children[0].querySelector('[data-alerta-mensagem]').textContent, 'Renata Barros está agora em Entrevista.');
});

test('erro AJAX usa alerta sem faixa estática e mantém erro e foco no campo inválido', (t) => {
    const ui = montar(t);
    const resumo = ui.elemento();
    const campo = ui.elemento();
    campo.name = 'nome';
    campo.id = 'nome';
    campo.insertAdjacentElement = (posicao, aviso) => campo.append(aviso);
    const formulario = {
        ownerDocument: ui.doc, elements: [campo], querySelector: () => resumo, querySelectorAll: () => [],
    };

    mostrarErros(formulario, { message: 'Revise os campos.', status: 422, errors: { nome: ['Informe o nome.'] } });

    assert.equal(resumo.className, 'sr-only');
    assert.equal(ui.central.children[0].dataset.alertaTipo, 'erro');
    assert.equal(campo.getAttribute('aria-invalid'), 'true');
    assert.equal(campo.children[0].textContent, 'Informe o nome.');
    assert.equal(ui.doc.activeElement, campo);
});

test('páginas sem central e mensagens vazias não provocam erros', (t) => {
    const ui = montar(t);
    ui.doc.querySelector = () => null;

    assert.equal(registrarAlertas(ui.doc), null);
    assert.equal(mostrarAlerta('Mensagem.', ui.doc), null);
    assert.equal(mostrarAlerta('', ui.doc), null);
});
