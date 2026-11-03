export function configurarCompetenciasCard(card, janela = window) {
    const lista = card.querySelector('[data-competencias-card]');
    const botao = card.querySelector('[data-expandir-competencias]');
    const competencias = [...lista.querySelectorAll('[data-competencia-card]')];
    let expandido = false;
    let ultimaLargura;

    function atualizar() {
        lista.dataset.expandido = 'false';
        competencias.forEach((competencia) => { competencia.hidden = false; });

        const largura = lista.getBoundingClientRect().width;
        ultimaLargura = largura;
        const espaco = parseFloat(janela.getComputedStyle(lista).columnGap) || 0;
        let ocupado = 0;
        let visiveis = 0;

        for (const competencia of competencias) {
            const necessario = competencia.getBoundingClientRect().width + (visiveis > 0 ? espaco : 0);
            if (ocupado + necessario > largura) {
                break;
            }
            ocupado += necessario;
            visiveis++;
        }

        const excedentes = competencias.length - visiveis;
        if (excedentes === 0) {
            expandido = false;
        }
        competencias.forEach((competencia, indice) => { competencia.hidden = !expandido && indice >= visiveis; });
        lista.dataset.expandido = String(expandido);
        botao.hidden = excedentes === 0;
        botao.setAttribute('aria-expanded', String(expandido));
        botao.textContent = expandido ? 'Recolher' : `Ver todas (+${excedentes})`;
        botao.setAttribute('aria-label', `${botao.textContent} competências`);
    }

    botao.addEventListener('click', () => {
        expandido = !expandido;
        atualizar();
    });

    const observador = janela.ResizeObserver ? new janela.ResizeObserver(() => {
        if (lista.getBoundingClientRect().width !== ultimaLargura) {
            atualizar();
        }
    }) : null;
    observador?.observe(lista);
    if (!observador) {
        janela.addEventListener('resize', atualizar);
    }
    atualizar();

    return {
        atualizar,
        desconectar() {
            observador?.disconnect();
            janela.removeEventListener('resize', atualizar);
        },
    };
}

export function registrarCardsCandidatos(doc = document, janela = window) {
    const regiao = doc.getElementById('resultados-candidatos');
    if (!regiao) {
        return;
    }
    const cards = new Map();

    function sincronizar() {
        for (const [card, controle] of cards) {
            if (!regiao.contains(card)) {
                controle.desconectar();
                cards.delete(card);
            }
        }
        regiao.querySelectorAll('[data-card-candidato]').forEach((card) => {
            if (!cards.has(card)) {
                cards.set(card, configurarCompetenciasCard(card, janela));
            }
        });
    }

    sincronizar();
    const observador = new janela.MutationObserver(sincronizar);
    observador.observe(regiao, { childList: true });
    doc.fonts?.ready.then(() => {
        cards.forEach((controle) => controle.atualizar());
    });
}

if (typeof document !== 'undefined') {
    registrarCardsCandidatos();
}
