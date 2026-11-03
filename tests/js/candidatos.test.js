import assert from 'node:assert/strict';
import { test } from 'node:test';
import { configurarCompetenciasCard, registrarCardsCandidatos } from '../../resources/js/modulos/candidatos.js';

function criarCard(larguras, largura = 200, espaco = 6) {
    const competencias = larguras.map((valor) => ({
        hidden: false,
        getBoundingClientRect: () => ({ width: valor }),
    }));
    const lista = {
        dataset: { expandido: 'true' },
        largura,
        querySelectorAll: () => competencias,
        getBoundingClientRect() { return { width: this.largura }; },
    };
    const eventos = {};
    const atributos = {};
    const botao = {
        hidden: true,
        addEventListener: (nome, executar) => { eventos[nome] = executar; },
        setAttribute: (nome, valor) => { atributos[nome] = valor; },
    };
    const card = { querySelector: (seletor) => seletor === '[data-competencias-card]' ? lista : botao };
    const observadores = [];
    const janela = {
        getComputedStyle: () => ({ columnGap: `${espaco}px` }),
        ResizeObserver: class {
            constructor(callback) { this.callback = callback; observadores.push(this); }
            observe() {}
            disconnect() { this.desconectado = true; }
        },
        removeEventListener() {},
    };
    return { card, lista, botao, competencias, eventos, atributos, janela, observadores };
}

test('mostra somente as competências inteiras que cabem na linha, incluindo o espaçamento', () => {
    const contexto = criarCard([80, 80, 40]);

    configurarCompetenciasCard(contexto.card, contexto.janela);

    assert.deepEqual(contexto.competencias.map((item) => item.hidden), [false, false, true]);
    assert.equal(contexto.botao.hidden, false);
    assert.equal(contexto.botao.textContent, 'Ver todas (+1)');
    assert.equal(contexto.atributos['aria-expanded'], 'false');
});

test('expande todas as competências e permite recolher sem navegar para o perfil', () => {
    const contexto = criarCard([80, 80, 80, 80, 80, 80]);
    configurarCompetenciasCard(contexto.card, contexto.janela);

    contexto.eventos.click();

    assert.ok(contexto.competencias.every((item) => !item.hidden));
    assert.equal(contexto.lista.dataset.expandido, 'true');
    assert.equal(contexto.atributos['aria-expanded'], 'true');
    assert.equal(contexto.botao.textContent, 'Recolher');

    contexto.eventos.click();

    assert.deepEqual(contexto.competencias.map((item) => item.hidden), [false, false, true, true, true, true]);
    assert.equal(contexto.lista.dataset.expandido, 'false');
    assert.equal(contexto.botao.textContent, 'Ver todas (+4)');
});

test('não oferece expansão quando todas cabem exatamente ou não existem competências', () => {
    for (const larguras of [[80, 114], []]) {
        const contexto = criarCard(larguras);

        configurarCompetenciasCard(contexto.card, contexto.janela);

        assert.equal(contexto.botao.hidden, true);
        assert.ok(contexto.competencias.every((item) => !item.hidden));
    }
});

test('competência maior que o card fica disponível por expansão sem corte parcial', () => {
    const contexto = criarCard([350, 50]);
    configurarCompetenciasCard(contexto.card, contexto.janela);

    assert.ok(contexto.competencias.every((item) => item.hidden));
    assert.equal(contexto.botao.textContent, 'Ver todas (+2)');

    contexto.eventos.click();

    assert.ok(contexto.competencias.every((item) => !item.hidden));
    assert.equal(contexto.lista.dataset.expandido, 'true');
});

test('recalcula a linha conforme a largura muda e preserva o estado expandido enquanto necessário', () => {
    const contexto = criarCard([80, 80, 80]);
    configurarCompetenciasCard(contexto.card, contexto.janela);
    contexto.lista.largura = 100;

    contexto.observadores[0].callback();

    assert.deepEqual(contexto.competencias.map((item) => item.hidden), [false, true, true]);
    contexto.eventos.click();
    contexto.lista.largura = 200;
    contexto.observadores[0].callback();
    assert.equal(contexto.atributos['aria-expanded'], 'true');
    assert.ok(contexto.competencias.every((item) => !item.hidden));

    contexto.lista.largura = 300;
    contexto.observadores[0].callback();

    assert.equal(contexto.botao.hidden, true);
    assert.equal(contexto.atributos['aria-expanded'], 'false');
    assert.ok(contexto.competencias.every((item) => !item.hidden));
});

test('inicializa novos resultados AJAX e desconecta observadores dos cards substituídos', () => {
    const anterior = criarCard([80, 80, 80]);
    const novo = criarCard([100, 120]);
    let cards = [anterior.card];
    let sincronizar;
    const regiao = {
        querySelectorAll: () => cards,
        contains: (card) => cards.includes(card),
    };
    const janela = {
        ...anterior.janela,
        MutationObserver: class {
            constructor(callback) { sincronizar = callback; }
            observe(elemento, opcoes) {
                assert.equal(elemento, regiao);
                assert.deepEqual(opcoes, { childList: true });
            }
        },
    };
    registrarCardsCandidatos({ getElementById: () => regiao }, janela);
    assert.equal(anterior.botao.textContent, 'Ver todas (+1)');

    cards = [novo.card];
    sincronizar();

    assert.equal(anterior.observadores[0].desconectado, true);
    assert.equal(novo.botao.textContent, 'Ver todas (+1)');
    assert.deepEqual(novo.competencias.map((item) => item.hidden), [false, true]);
});
