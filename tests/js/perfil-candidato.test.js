import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registrarPerfilCandidato } from '../../resources/js/modulos/perfil-candidato.js';

function montarPerfil({ hash = '', erro = null, comPdf = true } = {}) {
    let foco;
    function elemento(id, dataset = {}) {
        const atributos = new Map();
        const ouvintes = {};
        return {
            id, dataset, hidden: false,
            setAttribute: (nome, valor) => atributos.set(nome, valor),
            getAttribute: (nome) => atributos.get(nome),
            hasAttribute: (nome) => atributos.has(nome),
            contains: (alvo) => alvo?.painel === id,
            addEventListener: (nome, callback) => { ouvintes[nome] = callback; },
            emitir(nome, dados = {}) {
                const evento = { button: 0, preventDefault() { this.impedido = true; }, ...dados };
                ouvintes[nome]?.(evento);
                return evento;
            },
            focus() { foco = this; },
        };
    }
    const ids = ['visao-geral', 'curriculo', 'entrevistas', 'historico'];
    const abas = ids.map((id) => elemento(`aba-${id}`, { abaPerfil: id }));
    const paineis = ids.map((id) => elemento(id));
    paineis.find((painel) => painel.id === erro)?.setAttribute('data-painel-com-erros', '');
    const navegacao = elemento('navegacao');
    navegacao.querySelectorAll = () => abas;
    const pdf = comPdf ? elemento('pdf', { src: '/candidatos/1/curriculo' }) : null;
    const classesPdf = new Set(['hidden']);
    if (pdf) {
        pdf.classList = { remove: (classe) => classesPdf.delete(classe) };
    }
    const perfil = elemento('perfil');
    const cabecalhoCopia = { id: 'secao-competencias' };
    const copia = elemento('copia');
    copia.querySelector = () => cabecalhoCopia;
    const origem = { cloneNode: () => copia };
    const espelho = { replaceChildren: (conteudo) => { espelho.conteudo = conteudo; } };
    perfil.querySelector = (seletor) => ({
        '[data-abas-perfil]': navegacao,
        '[data-pdf-perfil]': pdf,
        '#competencias-candidato section': origem,
        '[data-espelho-competencias]': espelho,
    })[seletor];
    perfil.querySelectorAll = () => paineis;
    const janela = elemento('janela');
    janela.location = { hash };
    janela.history = { replaceState: (_estado, _titulo, endereco) => { janela.location.hash = endereco; } };
    const campo = { id: 'campo-texto', painel: 'entrevistas', value: 'Rascunho preservado' };
    const doc = {
        querySelector: () => perfil,
        getElementById: (id) => [...paineis, campo].find((item) => item.id === id) ?? null,
    };

    registrarPerfilCandidato(doc, janela);

    return { abas, paineis, navegacao, pdf, classesPdf, janela, perfil, espelho, copia, cabecalhoCopia, campo, foco: () => foco };
}

test('inicializa abas acessíveis na visão geral sem carregar o PDF', () => {
    const ui = montarPerfil();

    assert.equal(ui.navegacao.getAttribute('role'), 'tablist');
    assert.equal(ui.abas[0].getAttribute('role'), 'tab');
    assert.equal(ui.abas[0].getAttribute('aria-selected'), 'true');
    assert.equal(ui.abas[0].getAttribute('aria-controls'), 'visao-geral');
    assert.deepEqual(ui.abas.map((aba) => aba.tabIndex), [0, -1, -1, -1]);
    assert.deepEqual(ui.paineis.map((painel) => painel.hidden), [false, true, true, true]);
    assert.ok(ui.paineis.every((painel) => painel.getAttribute('role') === 'tabpanel' && painel.tabIndex === 0));
    assert.equal(ui.pdf.getAttribute('src'), undefined);
});

test('troca painéis sem descartar formulários e mantém um único painel visível', () => {
    const ui = montarPerfil({ comPdf: false });

    const evento = ui.abas[2].emitir('click');
    assert.equal(evento.impedido, true);
    assert.equal(ui.janela.location.hash, '#entrevistas');
    assert.deepEqual(ui.paineis.map((painel) => painel.hidden), [true, true, false, true]);
    assert.equal(ui.abas[2].getAttribute('aria-selected'), 'true');
    ui.abas[0].emitir('click');
    ui.abas[2].emitir('click');
    assert.equal(ui.campo.value, 'Rascunho preservado');
});

test('setas, Home e End movem o foco; Enter e espaço ativam a aba sem carregar prévias involuntariamente', () => {
    const ui = montarPerfil();

    assert.equal(ui.abas[0].emitir('keydown', { key: 'ArrowRight' }).impedido, true);
    assert.equal(ui.foco(), ui.abas[1]);
    assert.equal(ui.paineis[0].hidden, false);
    assert.equal(ui.pdf.getAttribute('src'), undefined);
    ui.abas[0].emitir('keydown', { key: 'ArrowLeft' });
    assert.equal(ui.foco(), ui.abas[3]);
    ui.abas[0].emitir('keydown', { key: 'End' });
    assert.equal(ui.foco(), ui.abas[3]);
    ui.abas[3].emitir('keydown', { key: 'Home' });
    assert.equal(ui.foco(), ui.abas[0]);
    ui.abas[2].emitir('keydown', { key: ' ' });
    assert.equal(ui.paineis[2].hidden, false);
    ui.abas[3].emitir('keydown', { key: 'Enter' });
    assert.equal(ui.paineis[3].hidden, false);
    assert.equal(ui.abas[3].emitir('keydown', { key: 'ArrowDown' }).impedido, undefined);
});

test('links diretos e mudanças de hash revelam a aba correta e a prévia do PDF não recarrega ao retornar', () => {
    const ui = montarPerfil({ hash: '#curriculo' });
    assert.equal(ui.paineis[1].hidden, false);
    assert.equal(ui.pdf.getAttribute('src'), '/candidatos/1/curriculo');
    assert.equal(ui.classesPdf.has('hidden'), false);
    ui.pdf.setAttribute('src', '/arquivo-ja-carregado');

    ui.janela.location.hash = '#campo-texto';
    ui.janela.emitir('hashchange');
    assert.equal(ui.paineis[2].hidden, false);
    ui.abas[1].emitir('click');
    assert.equal(ui.pdf.getAttribute('src'), '/arquivo-ja-carregado');
    ui.janela.location.hash = '#inexistente';
    ui.janela.emitir('hashchange');
    assert.equal(ui.paineis[0].hidden, false);
});

test('erros de validação têm prioridade sobre a aba da URL', () => {
    for (const erro of ['entrevistas', 'historico']) {
        const ui = montarPerfil({ hash: '#curriculo', erro });
        assert.equal(ui.paineis.find((painel) => !painel.hidden).id, erro);
        assert.equal(ui.pdf.getAttribute('src'), undefined);
    }
});

test('não intercepta cliques modificados nem ativa abas ausentes da página', () => {
    const ui = montarPerfil();
    assert.equal(ui.abas[1].emitir('click', { ctrlKey: true }).impedido, undefined);
    assert.equal(ui.abas[1].emitir('click', { button: 1 }).impedido, undefined);
    ui.abas[1].dataset.abaPerfil = 'inexistente';
    ui.abas[1].emitir('click');
    assert.equal(ui.paineis[0].hidden, false);
});

test('competências observadas por AJAX também atualizam a visão geral sem duplicar os IDs', () => {
    const ui = montarPerfil();
    ui.perfil.emitir('ajax:sucesso', { detail: { resposta: { fragments: { 'secao-energia': '<section>Energia</section>' } } } });
    assert.equal(ui.espelho.conteudo, undefined);

    ui.perfil.emitir('ajax:sucesso', { detail: { resposta: { fragments: { 'competencias-candidato': '<section>Atualizado</section>' } } } });

    assert.equal(ui.espelho.conteudo, ui.copia);
    assert.equal(ui.copia.getAttribute('aria-labelledby'), 'resumo-competencias');
    assert.equal(ui.cabecalhoCopia.id, 'resumo-competencias');
});

test('não interfere em páginas que não são um perfil do candidato', () => {
    assert.doesNotThrow(() => registrarPerfilCandidato({ querySelector: () => null }, {}));
});
