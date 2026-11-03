import assert from 'node:assert/strict';
import { test } from 'node:test';
import { registrarTriagem } from '../../resources/js/modulos/triagem.js';

function montarSelecao({ total = 25, todos = false, marcados = [false, false, false], inicializar = true } = {}) {
    const eventos = {};
    const registros = {};
    const registrarEvento = (nome, executar) => {
        eventos[nome] = executar;
        registros[nome] = (registros[nome] ?? 0) + 1;
    };
    const botao = { textContent: '', addEventListener: registrarEvento };
    const resumo = { textContent: '' };
    const campos = marcados.map((checked, indice) => ({
        value: String(indice + 1), checked,
        matches: (seletor) => seletor === '[data-candidato-triagem]',
    }));
    const excluidos = campos.map(() => ({ disabled: true }));
    const formulario = {
        dataset: { totalCandidatos: String(total) },
        elements: { todos_filtrados: { value: todos ? '1' : '0' } },
        querySelectorAll: () => campos,
        querySelector: (seletor) => {
            if (seletor === '[data-selecionar-todos-triagem]') {
                return botao;
            }
            if (seletor === '[data-resumo-selecao]') {
                return resumo;
            }
            return excluidos[Number(seletor.match(/"(\d+)"/)[1]) - 1];
        },
        addEventListener: registrarEvento,
    };
    const doc = { querySelectorAll: () => [formulario], getElementById: () => null };
    if (inicializar) {
        registrarTriagem(doc);
    }

    return { formulario, campos, excluidos, botao, resumo, eventos, registros, doc };
}

test('selecionar todos marca os cards e inclui o total de todas as páginas sem enviar o formulário', () => {
    const ui = montarSelecao({ marcados: [true, false, false] });

    ui.eventos.click();

    assert.equal(ui.formulario.elements.todos_filtrados.value, '1');
    assert.deepEqual(ui.campos.map((campo) => campo.checked), [true, true, true]);
    assert.deepEqual(ui.excluidos.map((campo) => campo.disabled), [true, true, true]);
    assert.equal(ui.resumo.textContent, '25 candidatos selecionados.');
    assert.equal(ui.botao.textContent, 'Limpar seleção');
});

test('desmarcar um candidato exclui apenas ele da seleção global e remarcar remove a exclusão', () => {
    const ui = montarSelecao();
    ui.eventos.click();

    ui.campos[1].checked = false;
    ui.eventos.change({ target: ui.campos[1] });

    assert.deepEqual(ui.excluidos.map((campo) => campo.disabled), [true, false, true]);
    assert.equal(ui.resumo.textContent, '24 candidatos selecionados.');

    ui.campos[1].checked = true;
    ui.eventos.change({ target: ui.campos[1] });

    assert.equal(ui.excluidos[1].disabled, true);
    assert.equal(ui.resumo.textContent, '25 candidatos selecionados.');
});

test('limpar seleção desmarca os cards e volta à seleção manual', () => {
    const ui = montarSelecao();
    ui.eventos.click();
    ui.campos[0].checked = false;
    ui.eventos.change({ target: ui.campos[0] });

    ui.eventos.click();

    assert.equal(ui.formulario.elements.todos_filtrados.value, '0');
    assert.deepEqual(ui.campos.map((campo) => campo.checked), [false, false, false]);
    assert.deepEqual(ui.excluidos.map((campo) => campo.disabled), [true, true, true]);
    assert.equal(ui.resumo.textContent, '0 candidatos selecionados.');
    assert.equal(ui.botao.textContent, 'Selecionar todos');

    ui.campos[0].checked = true;
    ui.eventos.change({ target: ui.campos[0] });

    assert.equal(ui.resumo.textContent, '1 candidato selecionado.');
    assert.equal(ui.formulario.elements.todos_filtrados.value, '0');
});

test('restaura o resumo e as exclusões após um erro de validação e aceita resultados vazios', () => {
    const restaurada = montarSelecao({ todos: true, marcados: [true, false, true] });

    assert.equal(restaurada.resumo.textContent, '24 candidatos selecionados.');
    assert.equal(restaurada.excluidos[1].disabled, false);
    assert.equal(restaurada.botao.textContent, 'Limpar seleção');

    const vazia = montarSelecao({ total: 0, marcados: [] });

    assert.equal(vazia.resumo.textContent, '0 candidatos selecionados.');
});

test('selecionar todos e exclusões continuam funcionando nos resultados substituídos por AJAX sem duplicar eventos', () => {
    const inicial = montarSelecao({ inicializar: false });
    let atual = inicial;
    let sincronizar;
    const regiao = {};
    inicial.doc.querySelectorAll = () => [atual.formulario];
    inicial.doc.getElementById = () => regiao;
    inicial.doc.defaultView = {
        MutationObserver: class {
            constructor(executar) { sincronizar = executar; }
            observe(elemento, opcoes) {
                assert.equal(elemento, regiao);
                assert.deepEqual(opcoes, { childList: true });
            }
        },
    };
    registrarTriagem(inicial.doc);
    inicial.eventos.click();

    atual = montarSelecao({ total: 9, inicializar: false });
    sincronizar();

    assert.equal(atual.formulario.elements.todos_filtrados.value, '0');
    assert.equal(atual.resumo.textContent, '0 candidatos selecionados.');
    atual.eventos.click();
    assert.equal(atual.resumo.textContent, '9 candidatos selecionados.');
    assert.deepEqual(atual.campos.map((campo) => campo.checked), [true, true, true]);

    atual.campos[0].checked = false;
    atual.eventos.change({ target: atual.campos[0] });
    sincronizar();

    assert.equal(atual.resumo.textContent, '8 candidatos selecionados.');
    assert.equal(atual.excluidos[0].disabled, false);
    assert.deepEqual(atual.registros, { click: 1, change: 1 });
});
