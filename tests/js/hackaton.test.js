import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const wrapper = fileURLToPath(new URL('../../hackaton', import.meta.url));
const powershell = readFileSync(new URL('../../hackaton.ps1', import.meta.url), 'utf8');
const scriptPowerShell = powershell.match(/\$installModel = @'\r?\n([\s\S]*?)\r?\n'@/)[1];

function ambiente(t, opcoes = {}) {
    const diretorio = mkdtempSync(join(tmpdir(), 'hackaton-ia-'));
    t.after(() => rmSync(diretorio, { recursive: true, force: true }));

    const executavel = (nome, conteudo) => writeFileSync(join(diretorio, nome), `#!/bin/sh\n${conteudo}\n`, { mode: 0o755 });
    executavel('docker', `
printf '%s\n' "$*" >> "$MOCK_COMPOSE"
[ "$1" = compose ] || exit 90
shift
if [ "$1" = version ]; then exit 0; fi
[ "$1" = --project-directory ] || exit 91
shift 2
[ "$1" = --file ] || exit 92
shift 2
case "$1" in
    up) exit "$MOCK_UP_EXIT" ;;
    exec)
        shift
        [ "$1" = -T ] || exit 93
        shift
        [ "$1" = ollama ] || exit 94
        shift
        exec "$@"
        ;;
    *) exit 95 ;;
esac
`);
    executavel('ollama', `
printf '%s\n' "$*" >> "$MOCK_OLLAMA"
case "$1" in
    list)
        if [ "$MOCK_READY" = never ]; then exit 1; fi
        if [ "$MOCK_READY" = delayed ] && [ ! -f "$MOCK_STARTED" ]; then
            touch "$MOCK_STARTED"
            exit 1
        fi
        ;;
    show) exit "$MOCK_INSTALLED" ;;
    pull) exit "$MOCK_PULL_EXIT" ;;
    *) exit 96 ;;
esac
`);
    executavel('sleep', 'exit 0');

    const env = {
        PATH: `${diretorio}:${process.env.PATH}`,
        OLLAMA_MODEL: 'llama3.2:3b',
        MOCK_COMPOSE: join(diretorio, 'compose'),
        MOCK_OLLAMA: join(diretorio, 'ollama-calls'),
        MOCK_STARTED: join(diretorio, 'started'),
        MOCK_READY: 'yes',
        MOCK_INSTALLED: '1',
        MOCK_PULL_EXIT: '0',
        MOCK_UP_EXIT: '0',
        ...opcoes,
    };

    return {
        executar: (tipo, argumentos = ['ia-setup']) => tipo === 'Bash'
            ? spawnSync('bash', [wrapper, ...argumentos], { env, encoding: 'utf8' })
            : spawnSync('sh', ['-eu', '-s'], { env, input: scriptPowerShell, encoding: 'utf8' }),
        chamadas: () => readFileSync(env.MOCK_OLLAMA, 'utf8').trim().split('\n'),
        compose: () => readFileSync(env.MOCK_COMPOSE, 'utf8'),
    };
}

for (const tipo of ['Bash', 'script de instalação do PowerShell']) {
    test(`${tipo}: instala o modelo configurado após aguardar o serviço`, (t) => {
        const mock = ambiente(t, { OLLAMA_MODEL: 'outro-modelo:7b', MOCK_READY: 'delayed' });
        const resultado = mock.executar(tipo);

        assert.equal(resultado.status, 0, resultado.stderr);
        assert.deepEqual(mock.chamadas(), ['list', 'list', 'show outro-modelo:7b', 'pull outro-modelo:7b']);
        if (tipo === 'Bash') {
            assert.match(mock.compose(), /up -d ollama\n/);
            assert.match(mock.compose(), /exec -T ollama sh -eu -c/);
            assert.doesNotMatch(mock.compose(), /key:generate|\bapp\b/);
        }
    });

    test(`${tipo}: reutiliza o modelo instalado sem novo download`, (t) => {
        const mock = ambiente(t, { MOCK_INSTALLED: '0' });
        const resultado = mock.executar(tipo);

        assert.equal(resultado.status, 0, resultado.stderr);
        assert.deepEqual(mock.chamadas(), ['list', 'show llama3.2:3b']);
        assert.match(resultado.stdout, /llama3\.2:3b/);
    });

    test(`${tipo}: falha se o serviço não ficar disponível`, (t) => {
        const mock = ambiente(t, { MOCK_READY: 'never' });
        const resultado = mock.executar(tipo);

        assert.equal(resultado.status, 1);
        assert.equal(mock.chamadas().length, 30);
        assert.ok(mock.chamadas().every((comando) => comando === 'list'));
        assert.match(resultado.stderr, /logs ollama/);
        assert.doesNotMatch(resultado.stderr, /IA local pronta/);
    });

    test(`${tipo}: propaga a falha no download sem anunciar sucesso`, (t) => {
        const mock = ambiente(t, { MOCK_PULL_EXIT: '17' });
        const resultado = mock.executar(tipo);

        assert.equal(resultado.status, 17);
        assert.doesNotMatch(resultado.stderr, /IA local pronta/);
    });
}

test('Bash: propaga falha ao iniciar o container', (t) => {
    const mock = ambiente(t, { MOCK_UP_EXIT: '18' });
    const resultado = mock.executar('Bash');

    assert.equal(resultado.status, 18);
    assert.doesNotMatch(mock.compose(), /exec -T/);
    assert.doesNotMatch(resultado.stderr, /IA local pronta/);
});

test('Bash: rejeita argumentos inesperados antes de iniciar o serviço', (t) => {
    const mock = ambiente(t);
    const resultado = mock.executar('Bash', ['ia-setup', 'modelo-inesperado']);

    assert.equal(resultado.status, 1);
    assert.match(resultado.stderr, /OLLAMA_MODEL/);
    assert.doesNotMatch(mock.compose(), /up -d/);
});

test('setup prepara a IA local nos dois wrappers e Compose compartilha o modelo configurado', () => {
    const bash = readFileSync(wrapper, 'utf8');
    const compose = readFileSync(new URL('../../compose.yaml', import.meta.url), 'utf8');

    assert.match(bash, /cmd_setup\(\) \{[\s\S]*?\n    cmd_ia_setup\n/);
    assert.match(powershell, /function Invoke-Setup \{[\s\S]*?\n    Initialize-LocalAi @\(\)/);
    assert.match(powershell, /'ia-setup' \{ Initialize-LocalAi \$commandArguments \}/);
    assert.match(powershell, /Invoke-Compose @\('exec', '-T', 'ollama', 'sh', '-eu', '-s'\) -StandardInput/);
    assert.match(compose, /OLLAMA_MODEL: \$\{OLLAMA_MODEL:-llama3\.2:3b\}/);
});
