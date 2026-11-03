export function registrarPerfilCandidato(doc = document, janela = window) {
    const perfil = doc.querySelector('[data-perfil-candidato]');
    if (!perfil) {
        return;
    }

    const navegacao = perfil.querySelector('[data-abas-perfil]');
    const abas = [...navegacao.querySelectorAll('[data-aba-perfil]')];
    const paineis = [...perfil.querySelectorAll('[data-painel-perfil]')];
    const pdf = perfil.querySelector('[data-pdf-perfil]');

    function painelDoHash() {
        const alvo = doc.getElementById(janela.location.hash.slice(1));
        return paineis.find((painel) => painel === alvo || painel.contains(alvo));
    }

    function ativar(id, atualizarEndereco = false) {
        if (!paineis.some((painel) => painel.id === id)) {
            return;
        }
        abas.forEach((aba) => {
            const selecionada = aba.dataset.abaPerfil === id;
            aba.setAttribute('aria-selected', String(selecionada));
            aba.tabIndex = selecionada ? 0 : -1;
        });
        paineis.forEach((painel) => { painel.hidden = painel.id !== id; });

        if (id === 'curriculo' && pdf && !pdf.getAttribute('src')) {
            pdf.setAttribute('src', pdf.dataset.src);
            pdf.classList.remove('hidden');
        }
        if (atualizarEndereco) {
            janela.history.replaceState(null, '', `#${id}`);
        }
    }

    navegacao.setAttribute('role', 'tablist');
    abas.forEach((aba) => {
        aba.setAttribute('role', 'tab');
        aba.setAttribute('aria-controls', aba.dataset.abaPerfil);
        aba.addEventListener('click', (evento) => {
            if (evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey) {
                return;
            }
            evento.preventDefault();
            ativar(aba.dataset.abaPerfil, true);
        });
        aba.addEventListener('keydown', (evento) => {
            const indice = abas.indexOf(aba);
            const destinos = {
                ArrowRight: (indice + 1) % abas.length,
                ArrowLeft: (indice - 1 + abas.length) % abas.length,
                Home: 0,
                End: abas.length - 1,
            };
            if (Object.hasOwn(destinos, evento.key)) {
                evento.preventDefault();
                abas[destinos[evento.key]].focus();
            } else if (evento.key === ' ' || evento.key === 'Enter') {
                evento.preventDefault();
                ativar(aba.dataset.abaPerfil, true);
            }
        });
    });
    paineis.forEach((painel) => {
        painel.setAttribute('role', 'tabpanel');
        painel.tabIndex = 0;
    });

    const painelComErros = paineis.find((painel) => painel.hasAttribute('data-painel-com-erros'));
    ativar(painelComErros?.id ?? painelDoHash()?.id ?? 'visao-geral');
    navegacao.setAttribute('data-abas-prontas', '');
    janela.addEventListener('hashchange', () => {
        ativar(painelDoHash()?.id ?? 'visao-geral');
    });

    perfil.addEventListener('ajax:sucesso', (evento) => {
        if (!evento.detail?.resposta?.fragments?.['competencias-candidato']) {
            return;
        }
        const origem = perfil.querySelector('#competencias-candidato section');
        const resumo = perfil.querySelector('[data-espelho-competencias]');
        if (origem && resumo) {
            const copia = origem.cloneNode(true);
            copia.setAttribute('aria-labelledby', 'resumo-competencias');
            copia.querySelector('h2').id = 'resumo-competencias';
            resumo.replaceChildren(copia);
        }
    });
}

if (typeof document !== 'undefined') {
    registrarPerfilCandidato();
}
