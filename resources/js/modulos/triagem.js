function atualizarSelecao(formulario) {
    const todos = formulario.elements.todos_filtrados.value === '1';
    const campos = Array.from(formulario.querySelectorAll('[data-candidato-triagem]'));
    const marcados = campos.filter((campo) => campo.checked).length;

    campos.forEach((campo) => {
        formulario.querySelector(`[data-excluir-candidato="${campo.value}"]`).disabled = !todos || campo.checked;
    });

    const total = todos ? Number(formulario.dataset.totalCandidatos) - (campos.length - marcados) : marcados;
    formulario.querySelector('[data-resumo-selecao]').textContent = total === 1
        ? '1 candidato selecionado.'
        : `${total} candidatos selecionados.`;
    formulario.querySelector('[data-selecionar-todos-triagem]').textContent = todos ? 'Limpar seleção' : 'Selecionar todos';
}

export function registrarTriagem(doc = document) {
    const inicializados = new WeakSet();

    function sincronizar() {
        doc.querySelectorAll('[data-selecao-triagem]').forEach((formulario) => {
            if (inicializados.has(formulario)) {
                return;
            }
            inicializados.add(formulario);
            atualizarSelecao(formulario);

            formulario.querySelector('[data-selecionar-todos-triagem]').addEventListener('click', () => {
                const selecionar = formulario.elements.todos_filtrados.value !== '1';
                formulario.elements.todos_filtrados.value = selecionar ? '1' : '0';
                formulario.querySelectorAll('[data-candidato-triagem]').forEach((campo) => {
                    campo.checked = selecionar;
                });
                atualizarSelecao(formulario);
            });

            formulario.addEventListener('change', (evento) => {
                if (evento.target.matches('[data-candidato-triagem]')) {
                    atualizarSelecao(formulario);
                }
            });
        });
    }

    sincronizar();
    const regiao = doc.getElementById('resultados-candidatos');
    if (regiao && doc.defaultView?.MutationObserver) {
        const observador = new doc.defaultView.MutationObserver(sincronizar);
        observador.observe(regiao, { childList: true });
    }
}

if (typeof document !== 'undefined') {
    registrarTriagem();
}
