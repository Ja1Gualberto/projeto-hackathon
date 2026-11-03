import assert from 'node:assert/strict';
import { test } from 'node:test';
import { configurarFiltroCompetencias } from '../../resources/js/modulos/filtro-competencias.js';

function montar({ aplicadas = ['1'], fixas = [], termoCodigo = '' } = {}) {
    let foco;
    let observar;
    const envios = [];
    function elemento() {
        const eventos = {};
        const atributos = {};
        return {
            hidden: false, dataset: {}, textContent: '',
            addEventListener(nome, fn) { eventos[nome] = fn; },
            emitir(nome, dados = {}) {
                const evento = { preventDefault() { this.impedido = true; }, ...dados };
                eventos[nome]?.(evento);
                return evento;
            },
            setAttribute(nome, valor) { atributos[nome] = valor; },
            getAttribute(nome) { return atributos[nome]; },
            focus() { foco = this; },
            contains(alvo) { return alvo?.pai === this; },
        };
    }
    const painel = elemento();
    painel.hidden = true;
    const abrir = elemento();
    const cancelar = elemento();
    const busca = { ...elemento(), value: '', pai: painel };
    const etiquetas = { ...elemento(), replaceChildren(...filhos) { this.filhos = filhos; } };
    const contagem = elemento();
    const limpar = elemento();
    const vazias = elemento();
    const campos = ['Linux', 'Comunicação', 'Inglês avançado', 'SQL'].map((nome, indice) => {
        const opcao = elemento();
        return {
            value: String(indice + 1), checked: false, disabled: fixas.includes(String(indice + 1)), pai: painel,
            dataset: { nomeCompetencia: nome }, opcao,
            closest: () => opcao,
        };
    });
    const grupos = [campos.slice(0, 2), campos.slice(2)].map((opcoes) => ({
        ...elemento(), querySelectorAll: () => opcoes.map((campo) => campo.opcao),
    }));
    painel.querySelectorAll = (seletor) => seletor === '[data-grupo-competencias]' ? grupos : campos;
    const modelo = { content: { firstElementChild: { cloneNode() {
        const nome = elemento();
        const botao = { ...elemento(), pai: etiquetas, nome, querySelector: () => nome };
        botao.closest = () => botao;
        return botao;
    } } } };
    const modeloFixo = { content: { firstElementChild: { cloneNode() {
        const etiqueta = modelo.content.firstElementChild.cloneNode();
        etiqueta.dataset.competenciaFixa = '';
        return etiqueta;
    } } } };
    const selecao = {
        querySelectorAll: () => aplicadas.map((value) => ({ value })),
        elements: { busca: { value: termoCodigo } },
    };
    const resultados = { querySelector: () => selecao };
    const doc = { getElementById: () => resultados, get activeElement() { return foco; } };
    const formulario = {
        ownerDocument: doc,
        querySelector: (seletor) => ({
            '[data-painel-competencias]': painel, '[data-abrir-competencias]': abrir,
            '[data-buscar-competencia]': busca, '[data-etiquetas-competencias]': etiquetas,
            '[data-modelo-etiqueta]': modelo, '[data-contagem-competencias]': contagem,
            '[data-modelo-etiqueta-fixa]': modeloFixo,
            '[data-limpar-competencias]': limpar, '[data-competencias-vazias]': vazias,
            '[data-fechar-competencias]': cancelar,
        })[seletor],
        requestSubmit() { envios.push(campos.filter((campo) => campo.checked && !campo.disabled).map((campo) => campo.value)); },
    };
    const janela = { MutationObserver: class {
        constructor(fn) { observar = fn; }
        observe(alvo, opcoes) {
            assert.equal(alvo, resultados);
            assert.deepEqual(opcoes, { childList: true });
        }
    } };
    configurarFiltroCompetencias(formulario, janela);
    function confirmar(ids, codigo = '') {
        aplicadas = ids;
        selecao.elements.busca.value = codigo;
        observar();
    }
    return { painel, abrir, cancelar, busca, etiquetas, contagem, limpar, vazias, campos, grupos, envios, confirmar, foco: () => foco };
}

test('inicia fechado com contagem e etiquetas acessíveis dos filtros realmente aplicados', () => {
    const ui = montar({ aplicadas: ['1', '2'] });

    assert.equal(ui.painel.hidden, true);
    assert.equal(ui.abrir.getAttribute('aria-expanded'), 'false');
    assert.equal(ui.contagem.textContent, 'Competências (2)');
    assert.deepEqual(ui.etiquetas.filhos.map((botao) => botao.getAttribute('aria-label')), ['Remover Linux', 'Remover Comunicação']);
    assert.equal(ui.limpar.hidden, false);
    assert.equal(ui.envios.length, 0);
});

test('busca local ignora acentos e caixa, esconde grupos vazios e preserva múltiplas marcações', () => {
    const ui = montar();
    ui.abrir.emitir('click');
    ui.campos[1].checked = true;
    ui.busca.value = '  COMUNICACAO  ';
    ui.busca.emitir('input');

    assert.deepEqual(ui.campos.map((campo) => campo.opcao.hidden), [true, false, true, true]);
    assert.deepEqual(ui.grupos.map((grupo) => grupo.hidden), [false, true]);
    assert.equal(ui.vazias.hidden, true);
    ui.busca.value = 'ingles AVANCADO';
    ui.busca.emitir('input');
    ui.campos[2].checked = true;
    ui.busca.value = 'inexistente';
    ui.busca.emitir('input');
    assert.equal(ui.vazias.hidden, false);
    assert.ok(ui.grupos.every((grupo) => grupo.hidden));
    ui.busca.value = '';
    ui.busca.emitir('input');
    assert.ok(ui.campos.every((campo) => !campo.opcao.hidden));
    assert.deepEqual(ui.campos.map((campo) => campo.checked), [true, true, true, false]);
    assert.equal(ui.envios.length, 0);
    assert.equal(ui.contagem.textContent, 'Competências (1)');
});

for (const modo of ['cancelar', 'alternar', 'Escape']) {
    test(`fechar por ${modo} descarta apenas o rascunho e restaura foco e filtros aplicados`, () => {
        const ui = montar();
        ui.abrir.emitir('click');
        assert.equal(ui.foco(), ui.busca);
        ui.campos[0].checked = false;
        ui.campos[2].checked = true;
        ui.busca.value = 'SQL';
        if (modo === 'Escape') {
            assert.equal(ui.painel.emitir('keydown', { key: 'Escape' }).impedido, true);
        } else {
            ui[modo === 'cancelar' ? 'cancelar' : 'abrir'].emitir('click');
        }

        assert.equal(ui.painel.hidden, true);
        assert.equal(ui.foco(), ui.abrir);
        assert.equal(ui.busca.value, '');
        assert.deepEqual(ui.campos.map((campo) => campo.checked), [true, false, false, false]);
        assert.equal(ui.etiquetas.filhos[0].nome.textContent, 'Linux');
        assert.equal(ui.envios.length, 0);
        ui.abrir.emitir('click');
        assert.equal(ui.campos[0].checked, true);
    });
}

test('confirmação dos resultados aplica o conjunto inteiro, fecha o painel e atualiza etiquetas', () => {
    const ui = montar();
    ui.abrir.emitir('click');
    ui.campos[1].checked = true;
    ui.campos[2].checked = true;
    ui.busca.focus();

    ui.confirmar(['1', '2', '3']);

    assert.equal(ui.contagem.textContent, 'Competências (3)');
    assert.equal(ui.painel.hidden, true);
    assert.equal(ui.foco(), ui.abrir);
    assert.deepEqual(ui.etiquetas.filhos.map((botao) => botao.nome.textContent), ['Linux', 'Comunicação', 'Inglês avançado']);
});

test('remover etiqueta usa o envio existente sem incluir rascunhos e espera confirmação antes de mudar a barra', () => {
    const ui = montar({ aplicadas: ['1', '2'] });
    ui.abrir.emitir('click');
    ui.campos[3].checked = true;
    const remover = ui.etiquetas.filhos[0];
    remover.focus();

    ui.etiquetas.emitir('click', { target: remover });

    assert.deepEqual(ui.envios, [['2']]);
    assert.equal(ui.contagem.textContent, 'Competências (2)');
    ui.confirmar(['2']);
    assert.equal(ui.contagem.textContent, 'Competências (1)');
    assert.equal(ui.foco(), ui.abrir);
    assert.equal(ui.etiquetas.filhos[0].getAttribute('aria-label'), 'Remover Comunicação');
});

test('limpar e navegar pelo histórico sincronizam o painel com os filtros confirmados pelo servidor', () => {
    const ui = montar();
    ui.abrir.emitir('click');
    ui.campos[1].checked = true;

    ui.confirmar([]);

    assert.equal(ui.contagem.textContent, 'Competências');
    assert.equal(ui.limpar.hidden, true);
    assert.deepEqual(ui.etiquetas.filhos, []);
    assert.ok(ui.campos.every((campo) => !campo.checked));
    ui.confirmar(['3']);
    ui.abrir.emitir('click');
    assert.deepEqual(ui.campos.map((campo) => campo.checked), [false, false, true, false]);
    assert.equal(ui.etiquetas.filhos[0].nome.textContent, 'Inglês avançado');
});

test('Enter na busca não aplica filtros, enquanto as teclas dos checkboxes mantêm comportamento nativo', () => {
    const ui = montar();

    assert.equal(ui.painel.emitir('keydown', { key: 'Enter', target: ui.busca }).impedido, true);
    assert.equal(ui.painel.emitir('keydown', { key: ' ', target: ui.campos[0] }).impedido, undefined);
    assert.equal(ui.envios.length, 0);
});

test('competências da vaga ficam fixas, sem botão de remoção, e não oferecem limpeza sem adicionais', () => {
    const ui = montar({ aplicadas: ['1'], fixas: ['1'] });

    assert.equal(ui.campos[0].checked, true);
    assert.equal(ui.campos[0].disabled, true);
    assert.equal(ui.etiquetas.filhos[0].dataset.competenciaFixa, '');
    assert.equal(ui.etiquetas.filhos[0].dataset.removerCompetencia, undefined);
    assert.equal(ui.etiquetas.filhos[0].getAttribute('aria-label'), undefined);
    assert.equal(ui.limpar.hidden, true);
    ui.etiquetas.emitir('click', { target: { closest: () => ({ dataset: { removerCompetencia: '1' } }) } });
    assert.equal(ui.envios.length, 0);
});

test('remover adicionais e limpar mantêm competências fixas e distinguem as etiquetas', () => {
    const ui = montar({ aplicadas: ['1', '2'], fixas: ['1'] });
    assert.equal(ui.limpar.hidden, false);
    assert.equal(ui.etiquetas.filhos[1].getAttribute('aria-label'), 'Remover Comunicação');
    ui.etiquetas.emitir('click', { target: ui.etiquetas.filhos[1] });

    assert.deepEqual(ui.envios, [[]]);
    ui.confirmar(['1']);
    assert.equal(ui.limpar.hidden, true);
    assert.equal(ui.contagem.textContent, 'Competências (1)');
    assert.equal(ui.etiquetas.filhos[0].nome.textContent, 'Linux');
    ui.abrir.emitir('click');
    assert.deepEqual(ui.campos.map((campo) => campo.checked), [true, false, false, false]);
    assert.equal(ui.campos[0].disabled, true);
});
