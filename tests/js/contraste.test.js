import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

/**
 * Pares de cor do design system (T143, WCAG 2.1 AA): 4,5:1 para texto (1.4.3) e 3:1 para
 * contorno de campo, indicador de foco e barras de score (1.4.11). Os valores vêm de app.css:
 * mudar um token e quebrar um par faz este teste falhar.
 */
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

function token(nome) {
    const encontrado = css.match(new RegExp(`--color-${nome}:\\s*(#[0-9a-f]{6})`, 'i'));
    assert.ok(encontrado, `token --color-${nome} não encontrado em app.css`);
    return encontrado[1];
}

function declaracaoDoAlerta(tipo) {
    const bloco = css.match(new RegExp(`\\.alerta--${tipo}\\s*\\{([^}]*)\\}`))?.[1] ?? '';
    return {
        fundo: bloco.match(/--alerta-fundo:\s*(#[0-9a-f]{6})/i)?.[1],
        texto: bloco.match(/[^-]color:\s*(#[0-9a-f]{3,6})/i)?.[1],
    };
}

function luminancia(hex) {
    const completo = hex.length === 4 ? `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}` : hex;
    const [r, g, b] = [1, 3, 5].map((inicio) => {
        const canal = parseInt(completo.slice(inicio, inicio + 2), 16) / 255;
        return canal <= 0.03928 ? canal / 12.92 : ((canal + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contraste(a, b) {
    const [claro, escuro] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
    return (claro + 0.05) / (escuro + 0.05);
}

const BRANCO = '#ffffff';
const PARES_DE_TEXTO = [
    ['texto', 'superficie'], ['texto', 'fundo'],
    ['texto-suave', 'superficie'], ['texto-suave', 'fundo'], ['texto-suave', 'primaria-50'],
    ['servidor-800', 'servidor-100'], ['estagiario-800', 'estagiario-100'],
    ['primaria-700', 'superficie'], ['primaria-700', 'primaria-50'], ['primaria-800', 'primaria-50'],
    ['primaria-100', 'primaria-900'], ['primaria-200', 'primaria-900'],
    ['score-alto', 'superficie'], ['score-medio', 'superficie'], ['score-baixo', 'superficie'],
];

for (const [texto, fundo] of PARES_DE_TEXTO) {
    test(`texto ${texto} sobre ${fundo} atinge 4,5:1`, () => {
        assert.ok(contraste(token(texto), token(fundo)) >= 4.5, `${contraste(token(texto), token(fundo)).toFixed(2)}:1`);
    });
}

for (const fundo of ['primaria-600', 'primaria-700', 'primaria-900']) {
    test(`texto branco sobre ${fundo} atinge 4,5:1`, () => {
        assert.ok(contraste(BRANCO, token(fundo)) >= 4.5);
    });
}

for (const tipo of ['sucesso', 'erro', 'aviso']) {
    test(`alerta de ${tipo} atinge 4,5:1`, () => {
        const { fundo, texto } = declaracaoDoAlerta(tipo);
        assert.ok(fundo && texto, `cores de .alerta--${tipo} não encontradas`);
        assert.ok(contraste(texto, fundo) >= 4.5, `${contraste(texto, fundo).toFixed(2)}:1`);
    });
}

test('alerta informativo atinge 4,5:1', () => {
    const base = css.match(/\.alerta\s*\{[^}]*--alerta-fundo:\s*(#[0-9a-f]{6})[^}]*?color:\s*(#[0-9a-f]{6})/i);
    assert.ok(base, 'cores de .alerta não encontradas');
    assert.ok(contraste(base[2], base[1]) >= 4.5);
});

test('placeholder atinge 4,5:1 sobre o fundo dos campos', () => {
    const placeholder = css.match(/::placeholder\s*\{\s*color:\s*(#[0-9a-f]{6})/i)?.[1];
    assert.ok(placeholder, 'cor de ::placeholder não encontrada');
    assert.ok(contraste(placeholder, token('superficie')) >= 4.5);
});

for (const [elemento, fundo] of [['borda-campo', 'superficie'], ['borda-campo', 'fundo'], ['primaria-400', 'superficie'], ['primaria-400', 'fundo'], ['primaria-400', 'primaria-900']]) {
    test(`${elemento} sobre ${fundo} atinge 3:1 (componente de interface)`, () => {
        assert.ok(contraste(token(elemento), token(fundo)) >= 3, `${contraste(token(elemento), token(fundo)).toFixed(2)}:1`);
    });
}

for (const barra of ['score-alto', 'score-medio', 'score-baixo']) {
    test(`barra ${barra} atinge 3:1 sobre o trilho`, () => {
        // Trilho da barra: bg-slate-200 de x-barra-score.
        assert.ok(contraste(token(barra), '#e2e8f0') >= 3);
    });
}
