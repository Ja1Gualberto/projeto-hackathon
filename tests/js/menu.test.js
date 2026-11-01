import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registrarMenu } from '../../resources/js/modulos/menu.js';

function montarMenu({ desktop = true, preferencia = null, armazenamentoBloqueado = false } = {}) {
    const doc = { body: { style: { overflow: 'auto' } }, activeElement: null };
    function elemento(hidden = false) {
        const atributos = new Map();
        const ouvintes = {};
        return {
            hidden, inert: false, dataset: {}, pai: null, link: false,
            setAttribute: (nome, valor) => atributos.set(nome, valor),
            getAttribute: (nome) => atributos.get(nome),
            hasAttribute: (nome) => atributos.has(nome),
            removeAttribute: (nome) => atributos.delete(nome),
            addEventListener: (nome, ouvinte) => { (ouvintes[nome] ??= []).push(ouvinte); },
            emitir: (nome, evento = {}) => { ouvintes[nome]?.forEach((ouvinte) => ouvinte(evento)); },
            focus() { doc.activeElement = this; },
            contains(alvo) {
                for (let atual = alvo; atual; atual = atual.pai) {
                    if (atual === this) {
                        return true;
                    }
                }
                return false;
            },
            closest() { return this.link ? this : this.pai?.closest() ?? null; },
            click() { return clicar(this); },
        };
    }

    const raiz = elemento();
    const lateral = elemento();
    const conteudo = elemento();
    const identidade = elemento();
    const abrir = elemento(true);
    const fundo = elemento(true);
    const inicio = elemento();
    const sair = elemento();
    const focaveis = [identidade, inicio, sair];
    for (const item of focaveis) {
        item.pai = lateral;
    }
    identidade.link = true;
    inicio.link = true;
    const icone = elemento();
    icone.pai = inicio;
    const elementos = {
        '[data-menu-conteudo]': conteudo,
        '[data-menu-identidade]': identidade,
        '[data-menu-abrir]': abrir,
        '[data-menu-fundo]': fundo,
    };
    raiz.querySelector = (seletor) => elementos[seletor];
    lateral.querySelectorAll = () => focaveis;
    doc.querySelector = () => raiz;
    doc.getElementById = () => lateral;
    const eventosDocumento = elemento();
    doc.addEventListener = eventosDocumento.addEventListener;
    const tela = elemento();
    tela.matches = desktop;
    const gravacoes = [];
    const janela = {
        matchMedia: () => tela,
        get localStorage() {
            if (armazenamentoBloqueado) {
                throw new Error('Armazenamento bloqueado');
            }
            return {
                getItem: () => preferencia,
                setItem: (chave, valor) => gravacoes.push([chave, valor]),
            };
        },
    };

    registrarMenu(doc, janela);
    function tecla(key, shiftKey = false) {
        const evento = { key, shiftKey, target: doc.activeElement, impedido: false, preventDefault() { this.impedido = true; } };
        eventosDocumento.emitir('keydown', evento);
        return evento;
    }

    function clicar(alvo, opcoes = {}) {
        const evento = {
            target: alvo, button: 0, defaultPrevented: false, propagacaoInterrompida: false, ...opcoes,
            preventDefault() { this.defaultPrevented = true; },
            stopPropagation() { this.propagacaoInterrompida = true; },
        };
        if (lateral.contains(alvo)) {
            lateral.emitir('click', evento);
        } else if (abrir.contains(alvo)) {
            abrir.emitir('click', evento);
        }
        if (!evento.propagacaoInterrompida) {
            eventosDocumento.emitir('click', evento);
        }
        return evento;
    }

    return { doc, janela, raiz, lateral, conteudo, identidade, abrir, fundo, inicio, icone, sair, tela, gravacoes, tecla, clicar };
}

test('desktop expande ao clicar na própria lateral e recolhe ao clicar fora, salvando a preferência', () => {
    const ui = montarMenu();
    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    assert.equal(ui.identidade.getAttribute('aria-expanded'), 'false');

    ui.clicar(ui.lateral);

    assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
    assert.equal(ui.identidade.getAttribute('aria-expanded'), 'true');
    assert.match(ui.identidade.getAttribute('aria-label'), /Recolher navegação/);
    assert.equal(ui.lateral.inert, false);
    assert.equal(ui.conteudo.inert, false);
    assert.equal(ui.fundo.hidden, true);
    assert.deepEqual(ui.gravacoes, [['banco-talentos:menu-recolhido', 'false']]);

    ui.conteudo.focus();
    ui.clicar(ui.conteudo);

    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    assert.match(ui.identidade.getAttribute('aria-label'), /Expandir navegação/);
    assert.equal(ui.doc.activeElement, ui.conteudo);
    assert.deepEqual(ui.gravacoes.at(-1), ['banco-talentos:menu-recolhido', 'true']);
});

test('restaura preferência válida e ignora valores desconhecidos', () => {
    const recolhido = montarMenu({ preferencia: 'true' });
    const expandido = montarMenu({ preferencia: 'false' });
    const desconhecido = montarMenu({ preferencia: 'qualquer valor' });

    assert.equal(recolhido.raiz.dataset.menuRecolhido, 'true');
    assert.equal(recolhido.identidade.getAttribute('aria-expanded'), 'false');
    assert.equal(expandido.raiz.dataset.menuRecolhido, 'false');
    assert.equal(desconhecido.raiz.dataset.menuRecolhido, 'true');
});

test('menu funciona mesmo com acesso ao armazenamento bloqueado', () => {
    const ui = montarMenu({ armazenamentoBloqueado: true });

    ui.clicar(ui.lateral);

    assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
    assert.equal(ui.identidade.getAttribute('aria-expanded'), 'true');
});

for (const alvo of ['icone', 'sair']) {
    test(`primeiro clique em ${alvo} apenas expande, sem navegar ou encerrar a sessão`, () => {
        const ui = montarMenu();

        const primeiroClique = ui.clicar(ui[alvo]);

        assert.equal(primeiroClique.defaultPrevented, true);
        assert.equal(primeiroClique.propagacaoInterrompida, true);
        assert.equal(ui.raiz.dataset.menuRecolhido, 'false');

        const segundoClique = ui.clicar(ui[alvo]);

        assert.equal(segundoClique.defaultPrevented, false);
        assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
    });
}

test('cliques com modificadores nos links preservam a navegação nativa sem expandir', () => {
    const ui = montarMenu();

    for (const opcoes of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
        assert.equal(ui.clicar(ui.icone, opcoes).defaultPrevented, false);
    }

    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    assert.deepEqual(ui.gravacoes, []);
});

test('a marca alterna o menu também pela tecla Espaço e Escape devolve o foco', () => {
    const ui = montarMenu();
    ui.identidade.focus();

    assert.equal(ui.tecla(' ').impedido, true);
    assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
    ui.tecla(' ');
    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    ui.clicar(ui.lateral);
    ui.inicio.focus();
    assert.equal(ui.tecla('Escape').impedido, true);
    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    assert.equal(ui.doc.activeElement, ui.identidade);
});

test('passar o mouse ou clicar dentro do menu já aberto não alterna seu estado', () => {
    const ui = montarMenu();

    ui.lateral.emitir('mouseenter');
    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    ui.clicar(ui.lateral);
    ui.clicar(ui.lateral);
    ui.lateral.emitir('mouseleave');

    assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
});

test('celular inicia fechado e abre um diálogo com foco e bloqueio do conteúdo e da rolagem', () => {
    const ui = montarMenu({ desktop: false, preferencia: 'true' });
    assert.equal(ui.lateral.inert, true);
    assert.equal(ui.abrir.hidden, false);
    assert.equal(ui.abrir.getAttribute('aria-expanded'), 'false');

    ui.clicar(ui.abrir);

    assert.equal(ui.raiz.dataset.menuAberto, 'true');
    assert.equal(ui.lateral.inert, false);
    assert.equal(ui.lateral.getAttribute('role'), 'dialog');
    assert.equal(ui.lateral.getAttribute('aria-modal'), 'true');
    assert.equal(ui.conteudo.inert, true);
    assert.equal(ui.fundo.hidden, false);
    assert.equal(ui.doc.body.style.overflow, 'hidden');
    assert.equal(ui.doc.activeElement, ui.identidade);
    assert.equal(ui.abrir.getAttribute('aria-expanded'), 'true');
    assert.deepEqual(ui.gravacoes, []);
});

for (const acao of ['identidade', 'fundo', 'Escape', 'link']) {
    test(`celular fecha pelo ${acao} e devolve foco e rolagem`, () => {
        const ui = montarMenu({ desktop: false });
        ui.clicar(ui.abrir);

        if (acao === 'identidade') {
            ui.clicar(ui.identidade);
        } else if (acao === 'fundo') {
            ui.clicar(ui.fundo);
        } else if (acao === 'Escape') {
            assert.equal(ui.tecla('Escape').impedido, true);
        } else {
            assert.equal(ui.clicar(ui.inicio).defaultPrevented, false);
        }

        assert.equal(ui.raiz.dataset.menuAberto, 'false');
        assert.equal(ui.lateral.inert, true);
        assert.equal(ui.lateral.hasAttribute('aria-modal'), false);
        assert.equal(ui.lateral.hasAttribute('role'), false);
        assert.equal(ui.conteudo.inert, false);
        assert.equal(ui.fundo.hidden, true);
        assert.equal(ui.doc.body.style.overflow, 'auto');
        assert.equal(ui.doc.activeElement, ui.abrir);
        assert.equal(ui.abrir.getAttribute('aria-expanded'), 'false');
    });
}

test('Tab e Shift+Tab mantêm a navegação dentro do menu aberto no celular', () => {
    const ui = montarMenu({ desktop: false });
    ui.clicar(ui.abrir);

    ui.sair.focus();
    assert.equal(ui.tecla('Tab').impedido, true);
    assert.equal(ui.doc.activeElement, ui.identidade);
    assert.equal(ui.tecla('Tab', true).impedido, true);
    assert.equal(ui.doc.activeElement, ui.sair);

    ui.inicio.focus();
    assert.equal(ui.tecla('Tab').impedido, false);
    assert.equal(ui.tecla('Enter').impedido, false);
});

test('redimensionar libera o diálogo e mantém a preferência independente da abertura no celular', () => {
    const ui = montarMenu({ desktop: false, preferencia: 'true' });
    ui.clicar(ui.abrir);

    ui.tela.matches = true;
    ui.tela.emitir('change');

    assert.equal(ui.raiz.dataset.menuAberto, 'false');
    assert.equal(ui.raiz.dataset.menuRecolhido, 'true');
    assert.equal(ui.conteudo.inert, false);
    assert.equal(ui.lateral.inert, false);
    assert.equal(ui.doc.body.style.overflow, 'auto');
    assert.equal(ui.doc.activeElement, ui.identidade);

    ui.inicio.focus();
    ui.tela.matches = false;
    ui.tela.emitir('change');

    assert.equal(ui.lateral.inert, true);
    assert.equal(ui.doc.activeElement, ui.abrir);
    assert.equal(ui.abrir.getAttribute('aria-expanded'), 'false');
});

test('Escape fora do diálogo não interfere com a página e inicializar novamente não duplica ações', () => {
    const ui = montarMenu();
    registrarMenu(ui.doc, ui.janela);

    assert.equal(ui.tecla('Escape').impedido, false);
    ui.clicar(ui.lateral);

    assert.equal(ui.raiz.dataset.menuRecolhido, 'false');
    assert.equal(ui.gravacoes.length, 1);
});

test('páginas sem menu não recebem alterações nem ouvintes', () => {
    registrarMenu({ querySelector: () => null }, {});
});
