const centrais = new WeakMap();

export function registrarAlertas(doc = document) {
    if (centrais.has(doc)) {
        return centrais.get(doc);
    }
    const central = doc.querySelector('[data-alertas]');
    if (!central) {
        return null;
    }
    const ativos = new WeakSet();

    function ativar(alerta) {
        if (ativos.has(alerta)) {
            return alerta;
        }
        ativos.add(alerta);
        const focoAnterior = doc.activeElement;
        const dialogo = doc.querySelector('dialog[open]');
        let destino = central;
        if (dialogo) {
            destino = dialogo.querySelector('[data-alertas-dialogo]');
            if (!destino) {
                destino = doc.createElement('section');
                destino.dataset.alertasDialogo = '';
                destino.className = 'central-alertas';
                destino.setAttribute('aria-label', 'Alertas do sistema');
                dialogo.append(destino);
            }
        }
        destino.append(alerta);
        alerta.dataset.alertaAtivo = '';
        let restante = Number(alerta.dataset.alertaDuracao) || 7000;
        let inicio;
        let temporizador;
        let fechado = false;
        const pausas = new Set();

        function fechar() {
            if (fechado) {
                return;
            }
            fechado = true;
            clearTimeout(temporizador);
            doc.removeEventListener('visibilitychange', visibilidade);
            if (alerta.contains(doc.activeElement)) {
                const retorno = focoAnterior?.isConnected && !focoAnterior.disabled
                    ? focoAnterior : doc.getElementById('conteudo');
                retorno?.focus();
            }
            alerta.remove();
        }

        function continuar() {
            if (!fechado && pausas.size === 0) {
                inicio = Date.now();
                temporizador = setTimeout(fechar, restante);
            }
        }

        function pausar(motivo) {
            if (pausas.size === 0) {
                clearTimeout(temporizador);
                restante = Math.max(0, restante - (Date.now() - inicio));
            }
            pausas.add(motivo);
        }

        function retomar(motivo) {
            if (!pausas.delete(motivo)) {
                return;
            }
            continuar();
        }

        function visibilidade() {
            if (doc.hidden) {
                pausar('aba');
            } else {
                retomar('aba');
            }
        }

        alerta.querySelector('[data-alerta-fechar]').addEventListener('click', fechar);
        alerta.addEventListener('mouseenter', () => pausar('mouse'));
        alerta.addEventListener('mouseleave', () => retomar('mouse'));
        alerta.addEventListener('focusin', () => pausar('foco'));
        alerta.addEventListener('focusout', (evento) => {
            if (!alerta.contains(evento.relatedTarget)) {
                retomar('foco');
            }
        });
        doc.addEventListener('visibilitychange', visibilidade);
        continuar();
        if (doc.hidden) {
            pausar('aba');
        }
        return alerta;
    }

    function procurar(raiz) {
        if (raiz.matches?.('[data-alerta]')) {
            ativar(raiz);
        }
        raiz.querySelectorAll?.('[data-alerta]').forEach(ativar);
    }

    centrais.set(doc, ativar);
    procurar(doc);
    const Observador = doc.defaultView?.MutationObserver;
    if (Observador) {
        new Observador((registros) => {
            for (const registro of registros) {
                registro.addedNodes.forEach(procurar);
            }
        }).observe(doc.body, { childList: true, subtree: true });
    }
    return ativar;
}

export function mostrarAlerta(mensagem, doc = document, tipo = 'info') {
    if (!mensagem) {
        return null;
    }
    tipo = ['sucesso', 'erro', 'aviso', 'info'].includes(tipo) ? tipo : 'info';
    const modelo = doc.querySelector(`[data-alerta-modelo="${tipo}"]`);
    if (!modelo?.content?.firstElementChild) {
        return null;
    }
    const ativar = registrarAlertas(doc);
    if (!ativar) {
        return null;
    }
    const alerta = modelo.content.firstElementChild.cloneNode(true);
    alerta.querySelector('[data-alerta-mensagem]').textContent = mensagem;
    return ativar(alerta);
}

if (typeof document !== 'undefined') {
    registrarAlertas();
}
