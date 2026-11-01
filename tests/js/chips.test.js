import assert from 'node:assert/strict';
import { test } from 'node:test';

test('filtra sem acentos, preserva as seleções e restaura os chips ao limpar', async () => {
    const eventos = {};
    const chips = [
        { textContent: 'Comunicação', hidden: false, checked: true },
        { textContent: 'Python', hidden: false, checked: false },
    ];
    const input = {
        value: '',
        dataset: { filtrarChips: '#catalogo' },
        addEventListener: (nome, executar) => { eventos[nome] = executar; },
    };
    globalThis.document = {
        querySelectorAll: () => [input],
        querySelector: (seletor) => {
            assert.equal(seletor, '#catalogo');
            return { querySelectorAll: () => chips };
        },
    };

    try {
        await import('../../resources/js/modulos/chips.js');
        input.value = '  COMUNICACAO  ';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [false, true]);

        input.value = 'python';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [true, false]);
        assert.equal(chips[0].checked, true);

        input.value = 'inexistente';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [true, true]);

        input.value = '';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [false, false]);

        // Linha de peso da vaga: o filtro olha só o nome (data-chip-texto), não as opções do select.
        chips.push({ textContent: 'Git Não usar 1 · Desejável 2 · Importante', dataset: { chipTexto: 'Git' }, hidden: false });
        input.value = 'desejavel';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [true, true, true]);

        input.value = 'GIT';
        eventos.input();
        assert.deepEqual(chips.map((chip) => chip.hidden), [true, true, false]);
    } finally {
        delete globalThis.document;
    }
});
