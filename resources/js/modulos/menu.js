const chavePreferencia = 'banco-talentos:menu-recolhido';

export function registrarMenu(doc = document, janela = window) {
    const raiz = doc.querySelector('[data-menu]');
    if (!raiz || raiz.hasAttribute('data-menu-pronto')) {
        return;
    }

    const lateral = doc.getElementById('menu-lateral');
    const conteudo = raiz.querySelector('[data-menu-conteudo]');
    const identidade = raiz.querySelector('[data-menu-identidade]');
    const abrir = raiz.querySelector('[data-menu-abrir]');
    const fundo = raiz.querySelector('[data-menu-fundo]');
    const desktop = janela.matchMedia('(min-width: 64rem)');
    let recolhido = true;
    let aberto = false;
    let overflowAnterior;

    try {
        recolhido = janela.localStorage.getItem(chavePreferencia) !== 'false';
    } catch {
        // A navegação continua funcionando quando o navegador bloqueia o armazenamento.
    }

    function atualizar() {
        const modal = !desktop.matches && aberto;
        raiz.dataset.menuRecolhido = String(recolhido);
        raiz.dataset.menuAberto = String(modal);
        lateral.inert = !desktop.matches && !aberto;
        conteudo.inert = modal;
        fundo.hidden = !modal;
        identidade.setAttribute('aria-expanded', String(desktop.matches ? !recolhido : aberto));
        const acao = desktop.matches ? (recolhido ? 'Expandir' : 'Recolher') : 'Fechar';
        // O nome acessível começa pelo texto visível do logo (WCAG 2.5.3, rótulo no nome).
        const rotulo = `BT Banco de Talentos SETIC — ${acao} navegação`;
        identidade.setAttribute('aria-label', rotulo);
        identidade.title = rotulo;
        abrir.setAttribute('aria-expanded', String(modal));

        if (modal) {
            lateral.setAttribute('role', 'dialog');
            lateral.setAttribute('aria-modal', 'true');
            if (overflowAnterior === undefined) {
                overflowAnterior = doc.body.style.overflow;
                doc.body.style.overflow = 'hidden';
            }
        } else {
            lateral.removeAttribute('role');
            lateral.removeAttribute('aria-modal');
            if (overflowAnterior !== undefined) {
                doc.body.style.overflow = overflowAnterior;
                overflowAnterior = undefined;
            }
        }
    }

    function salvarPreferencia() {
        try {
            janela.localStorage.setItem(chavePreferencia, String(recolhido));
        } catch {
            // Persistir a preferência é opcional; interagir com o menu não é.
        }
    }

    function abrirMenu() {
        if (desktop.matches) {
            recolhido = false;
            salvarPreferencia();
        } else {
            aberto = true;
        }
        atualizar();
        if (!desktop.matches) {
            identidade.focus();
        }
    }

    function fecharMenu(devolverFoco = false) {
        if (desktop.matches ? recolhido : !aberto) {
            return;
        }
        if (desktop.matches) {
            recolhido = true;
            salvarPreferencia();
        } else {
            aberto = false;
        }
        atualizar();
        if (!desktop.matches) {
            abrir.focus();
        } else if (devolverFoco) {
            identidade.focus();
        }
    }

    abrir.addEventListener('click', (evento) => {
        evento.preventDefault();
        abrirMenu();
    });

    lateral.addEventListener('click', (evento) => {
        if (evento.button > 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey || evento.defaultPrevented) {
            return;
        }

        if (desktop.matches && recolhido) {
            evento.preventDefault();
            evento.stopPropagation();
            abrirMenu();
        } else if (identidade.contains(evento.target)) {
            evento.preventDefault();
            fecharMenu(true);
        } else if (!desktop.matches && evento.target.closest?.('a[href]')) {
            fecharMenu();
        }
    }, true);

    doc.addEventListener('click', (evento) => {
        if (!lateral.contains(evento.target) && !abrir.contains(evento.target)) {
            fecharMenu();
        }
    });

    doc.addEventListener('keydown', (evento) => {
        if (evento.key === ' ' && (evento.target === identidade || evento.target === abrir)) {
            evento.preventDefault();
            evento.target.click();
            return;
        }
        if (evento.key === 'Escape' && (desktop.matches ? !recolhido : aberto)) {
            evento.preventDefault();
            fecharMenu(true);
            return;
        }
        if (desktop.matches || !aberto) {
            return;
        }
        if (evento.key === 'Tab') {
            const focaveis = [...lateral.querySelectorAll('a[href], button:not([disabled])')].filter((elemento) => !elemento.hidden);
            const primeiro = focaveis[0];
            const ultimo = focaveis.at(-1);
            if (evento.shiftKey && doc.activeElement === primeiro) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && doc.activeElement === ultimo) {
                evento.preventDefault();
                primeiro.focus();
            }
        }
    });

    desktop.addEventListener('change', () => {
        const focoNoMenu = lateral.contains(doc.activeElement);
        aberto = false;
        atualizar();
        if (!desktop.matches && focoNoMenu) {
            abrir.focus();
        } else if (desktop.matches && doc.activeElement === abrir) {
            identidade.focus();
        }
    });

    atualizar();
    raiz.setAttribute('data-menu-pronto', '');
    identidade.setAttribute('role', 'button');
    identidade.setAttribute('aria-controls', 'menu-lateral');
    abrir.setAttribute('role', 'button');
    abrir.hidden = false;
}

if (typeof document !== 'undefined') {
    registrarMenu();
}
