import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

test('Blades da listagem são passivos, sem decisões, consultas, componentes dinâmicos ou código embutido', () => {
    const arquivos = [
        'index.blade.php', 'partials/_resultados.blade.php', 'partials/_card.blade.php',
        'partials/_filtros.blade.php', 'partials/_chip-competencia.blade.php',
    ];

    for (const arquivo of arquivos) {
        const conteudo = readFileSync(new URL(`../../resources/views/candidatos/${arquivo}`, import.meta.url), 'utf8');
        assert.doesNotMatch(conteudo, /<\?php|@(if|unless|foreach|forelse|for|while|php|checked|selected|include)\b|<x-|\{!!|\b(?:route|collect|old|match)\s*\(|\$\w+->|=>/, arquivo);
        assert.doesNotMatch(conteudo, /\bon\w+\s*=|<script\b(?![^>]*\bsrc=)/i, arquivo);
    }
});
