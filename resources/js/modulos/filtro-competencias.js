import { normalizar } from './chips.js';

export function configurarFiltroCompetencias(formulario, janela = window) {
    const doc = formulario.ownerDocument;
    const resultados = doc.getElementById('resultados-candidatos');
    const painel = formulario.querySelector('[data-painel-competencias]');
    const abrir = formulario.querySelector('[data-abrir-competencias]');
    const busca = formulario.querySelector('[data-buscar-competencia]');
    const etiquetas = formulario.querySelector('[data-etiquetas-competencias]');
    const modelo = formulario.querySelector('[data-modelo-etiqueta]');
    const modeloFixo = formulario.querySelector('[data-modelo-etiqueta-fixa]');
    const campos = [...painel.querySelectorAll('[name="competencias[]"]')];
    const fixas = new Set(campos.filter((campo) => campo.disabled).map((campo) => campo.value));
    const grupos = [...painel.querySelectorAll('[data-grupo-competencias]')];
    let aplicadas = new Set();

    function filtrarOpcoes() {
        const termo = normalizar(busca.value);
        let visiveis = 0;
        for (const campo of campos) {
            const opcao = campo.closest('[data-opcao-competencia]');
            opcao.hidden = !normalizar(campo.dataset.nomeCompetencia).includes(termo);
            visiveis += Number(!opcao.hidden);
        }
        grupos.forEach((grupo) => {
            grupo.hidden = ![...grupo.querySelectorAll('[data-opcao-competencia]')].some((opcao) => !opcao.hidden);
        });
        formulario.querySelector('[data-competencias-vazias]').hidden = visiveis !== 0;
    }

    function restaurarMarcacoes() {
        campos.forEach((campo) => { campo.checked = fixas.has(campo.value) || aplicadas.has(campo.value); });
        busca.value = '';
        filtrarOpcoes();
    }

    function fechar(focar = true) {
        painel.hidden = true;
        abrir.setAttribute('aria-expanded', 'false');
        restaurarMarcacoes();
        if (focar) {
            abrir.focus();
        }
    }

    function sincronizar() {
        const selecao = resultados.querySelector('[data-selecao-triagem]');
        aplicadas = new Set([...selecao.querySelectorAll('[name="competencias[]"]')].map((campo) => campo.value));
        const focar = painel.contains(doc.activeElement) || etiquetas.contains(doc.activeElement);
        fechar(false);
        formulario.querySelector('[data-contagem-competencias]').textContent = aplicadas.size
            ? `Competências (${aplicadas.size})` : 'Competências';
        formulario.querySelector('[data-limpar-competencias]').hidden = [...aplicadas].every((id) => fixas.has(id)) && !selecao.elements.busca.value;
        const botoes = campos.filter((campo) => aplicadas.has(campo.value)).map((campo) => {
            const fixa = fixas.has(campo.value);
            const botao = (fixa ? modeloFixo : modelo).content.firstElementChild.cloneNode(true);
            if (!fixa) {
                botao.dataset.removerCompetencia = campo.value;
                botao.setAttribute('aria-label', `Remover ${campo.dataset.nomeCompetencia}`);
            }
            botao.querySelector('[data-nome-etiqueta]').textContent = campo.dataset.nomeCompetencia;
            return botao;
        });
        etiquetas.replaceChildren(...botoes);
        if (focar) {
            abrir.focus();
        }
    }

    abrir.addEventListener('click', () => {
        if (!painel.hidden) {
            fechar();
            return;
        }
        restaurarMarcacoes();
        painel.hidden = false;
        abrir.setAttribute('aria-expanded', 'true');
        busca.focus();
    });
    formulario.querySelector('[data-fechar-competencias]').addEventListener('click', () => fechar());
    painel.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            evento.preventDefault();
            fechar();
        } else if (evento.key === 'Enter' && evento.target === busca) {
            // A busca é local: Enter não deve aplicar um rascunho por acidente.
            evento.preventDefault();
        }
    });
    busca.addEventListener('input', filtrarOpcoes);
    etiquetas.addEventListener('click', (evento) => {
        const botao = evento.target.closest('[data-remover-competencia]');
        if (!botao || fixas.has(botao.dataset.removerCompetencia)) {
            return;
        }
        restaurarMarcacoes();
        campos.forEach((campo) => { campo.checked = campo.checked && campo.value !== botao.dataset.removerCompetencia; });
        formulario.requestSubmit();
    });

    sincronizar();
    const observador = new janela.MutationObserver(sincronizar);
    observador.observe(resultados, { childList: true });
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-filtro-competencias]').forEach((formulario) => configurarFiltroCompetencias(formulario));
}
