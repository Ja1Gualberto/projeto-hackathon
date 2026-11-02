import { anunciar, ErroAjax, mesmaOrigem, mostrarErros, registrarRenderizador } from './ajax.js';

const ETAPAS = {
    triagem: { rotulo: 'Triagem', borda: 'border-t-slate-400', vazio: 'Todos os elegíveis já avançaram nesta vaga.', acoes: [['entrevista', 'Mover para Entrevista', 'primario']] },
    entrevista: { rotulo: 'Entrevista', borda: 'border-t-primaria-600', vazio: 'Ninguém em entrevista ainda.', acoes: [['aprovado', 'Aprovar', 'primario'], ['triagem', 'Voltar para Triagem', 'secundario']] },
    aprovado: { rotulo: 'Aprovado', borda: 'border-t-score-alto', vazio: 'Nenhum candidato aprovado ainda.', acoes: [['entrevista', 'Voltar para Entrevista', 'secundario']] },
};
const ORIGENS = {
    curriculo: ['Declarada no currículo', 'declarada'],
    revisao: ['Adicionada na revisão', 'revisada'],
    entrevista: ['Observada em entrevista', 'observada'],
};
const EIXOS = {
    dinamico_analitico: 'Dinâmico ↔ Analítico',
    individual_equipe: 'Individual ↔ Equipe',
    execucao_planejamento: 'Execução ↔ Planejamento',
};
const CORES_STATUS = {
    aberta: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    encerrada: 'bg-slate-100 text-slate-700 ring-slate-300',
};

function modelo(doc, id) {
    const template = doc.getElementById(id);
    if (!template?.content) {
        throw new ErroAjax('A estrutura da tela de vagas não está disponível. Atualize a página.');
    }
    return template.content.firstElementChild.cloneNode(true);
}

function texto(raiz, seletor, valor) {
    raiz.querySelector(seletor).textContent = valor ?? '';
}

function link(raiz, seletor, destino, doc) {
    raiz.querySelector(seletor).href = mesmaOrigem(destino, doc.baseURI).href;
}

export function quantidade(total, singular, plural) {
    return `${total} ${total === 1 ? singular : plural}`;
}

function status(raiz, vaga) {
    const badge = raiz.querySelector('[data-status]');
    badge.classList.remove(...CORES_STATUS.encerrada.split(' '));
    badge.classList.add(...CORES_STATUS[vaga.aberta ? 'aberta' : 'encerrada'].split(' '));
    badge.textContent = vaga.status;
    texto(raiz, '[data-tipo]', vaga.tipo);
    texto(raiz, '[data-area]', vaga.area);
}

export function renderizarLista(dados, doc = document) {
    const itens = dados.vagas.map((vaga) => {
        const card = modelo(doc, 'modelo-vaga');
        card.classList.add(...(vaga.aberta ? 'bg-superficie' : 'border-dashed bg-fundo').split(' '));
        status(card, vaga);
        texto(card, '[data-titulo]', vaga.titulo);
        link(card, '[data-titulo]', vaga.url, doc);
        texto(card, '[data-competencias-total]', quantidade(vaga.competencias_count, 'competência', 'competências'));
        texto(card, '[data-elegiveis]', quantidade(vaga.totalElegiveis, 'candidato elegível', 'candidatos elegíveis'));
        texto(card, '[data-entrevista]', vaga.entrevista_count);
        texto(card, '[data-aprovado]', vaga.aprovado_count);
        const abrir = card.querySelector('[data-abrir-quadro]');
        abrir.textContent = vaga.aberta ? 'Abrir quadro' : 'Consultar quadro';
        const descricao = doc.createElement('span');
        descricao.className = 'sr-only';
        descricao.textContent = ` de ${vaga.titulo}`;
        abrir.append(descricao);
        link(card, '[data-abrir-quadro]', vaga.url, doc);
        return card;
    });
    doc.querySelector('[data-lista-vagas]').replaceChildren(...itens);
    doc.querySelector('[data-lista-vazia]').hidden = itens.length !== 0;
}

function opcoes(select, itens, doc) {
    for (const { valor, rotulo } of itens) {
        const option = doc.createElement('option');
        option.value = valor;
        option.textContent = rotulo;
        select.append(option);
    }
}

export function preencherFormulario(dados, doc = document) {
    const form = doc.querySelector('[data-formulario-vaga]');
    const valores = dados.oldInput ?? dados.valores;
    opcoes(form.elements.namedItem('area'), dados.areas, doc);
    opcoes(form.elements.namedItem('tipo_candidato'), dados.tipos, doc);
    for (const nome of ['titulo', 'area', 'tipo_candidato', 'descricao']) {
        form.elements.namedItem(nome).value = valores[nome] ?? '';
    }
    for (const categoria of ['hard', 'soft']) {
        const grupo = form.querySelector(`[data-catalogo="${categoria}"]`);
        const itens = dados.catalogo[categoria].map((competencia) => {
            const linha = modelo(doc, 'modelo-peso-competencia');
            const id = `peso-competencia-${competencia.id}`;
            linha.dataset.chipTexto = competencia.nome;
            const label = linha.querySelector('label');
            label.htmlFor = id;
            label.textContent = competencia.nome;
            const select = linha.querySelector('select');
            select.id = id;
            select.name = `competencias[${competencia.id}][peso]`;
            select.value = valores.competencias?.[competencia.id]?.peso ?? '';
            const erro = linha.querySelector('[data-erro-campo]');
            erro.id = `${id}-erro`;
            erro.dataset.erroCampo = `competencias.${competencia.id}.peso`;
            return linha;
        });
        if (itens.length === 0) {
            const vazio = doc.createElement('p');
            vazio.className = 'text-sm text-texto-suave';
            vazio.textContent = 'Nenhuma competência nesta categoria.';
            itens.push(vazio);
        }
        grupo.replaceChildren(...itens);
    }
    const eixos = Object.entries(EIXOS).map(([eixo, rotulo]) => {
        const campo = modelo(doc, 'modelo-eixo-energia');
        const id = `campo-energia-${eixo}`;
        campo.querySelector('label').htmlFor = id;
        texto(campo, 'label', rotulo);
        const select = campo.querySelector('select');
        select.id = id;
        select.name = `energia[${eixo}]`;
        select.value = valores.energia?.[eixo] ?? '';
        return campo;
    });
    form.querySelector('[data-eixos-energia]').replaceChildren(...eixos);
    form.hidden = false;
    if (Object.keys(dados.errors ?? {}).length) {
        mostrarErros(form, new ErroAjax('Revise os campos indicados para salvar a vaga.', 422, dados.errors));
    }
}

function tagCompetencia(competencia, faltante, doc) {
    const tag = modelo(doc, 'modelo-tag-competencia');
    texto(tag, '[data-tag-nome]', competencia.nome);
    texto(tag, '[data-tag-peso]', `· peso ${competencia.peso}`);
    tag.querySelector('[data-tag-faltante]').hidden = !faltante;
    tag.querySelector('[data-tag-origem]').hidden = faltante || !competencia.origem;
    tag.querySelector('[data-tag-peso]').classList.toggle('font-semibold', faltante);
    if (faltante) {
        tag.classList.add(...'border border-dashed border-slate-400 bg-superficie font-medium text-texto-suave'.split(' '));
        tag.title = 'Não identificado no currículo';
    } else {
        tag.classList.add(...'font-semibold ring-1 ring-inset'.split(' '));
        tag.classList.add(...(competencia.origem === 'entrevista'
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-primaria-50 text-primaria-800 ring-primaria-200').split(' '));
        if (competencia.origem) {
            const [titulo, rotulo] = ORIGENS[competencia.origem];
            tag.title = titulo;
            texto(tag, '[data-tag-origem]', `· ${rotulo}`);
        }
    }
    return tag;
}

function cardRanking(item, posicao, vaga, doc) {
    const card = modelo(doc, 'modelo-card-ranking');
    const identificacao = item.nome ?? item.codigo;
    const podeMover = vaga.aberta && item.elegivel;
    card.dataset.codigo = item.codigo;
    card.draggable = podeMover;
    card.setAttribute('aria-labelledby', `card-${item.codigo}`);
    card.classList.add(...(item.desativado ? 'border-dashed border-slate-400 bg-slate-50' : 'bg-superficie').split(' '));
    if (podeMover) {
        card.classList.add('cursor-grab', 'active:cursor-grabbing');
    }
    texto(card, '[data-posicao]', `${posicao}º`);
    const titulo = card.querySelector('[data-card-titulo]');
    titulo.id = `card-${item.codigo}`;
    titulo.classList.toggle('font-mono', item.nome === null);
    texto(card, '[data-link-perfil]', identificacao);
    link(card, '[data-link-perfil]', item.perfilUrl, doc);
    texto(card, '[data-codigo-revelado]', item.codigo);
    card.querySelector('[data-codigo-revelado]').hidden = item.nome === null;
    card.querySelector('[data-tipo-servidor]').hidden = item.tipo !== 'servidor';
    card.querySelector('[data-tipo-estagiario]').hidden = item.tipo !== 'estagiario';
    texto(card, '[data-area]', item.area);
    const situacao = card.querySelector('[data-situacao]');
    situacao.hidden = !item.desativado && item.elegivel;
    situacao.textContent = item.desativado ? 'Desativado' : 'Fora do tipo aceito pela vaga';
    const score = card.querySelector('[data-score]');
    score.setAttribute('aria-valuenow', item.percentual);
    score.setAttribute('aria-label', `Aderência de ${item.percentual}%`);
    const barra = card.querySelector('[data-score-barra]');
    const numero = card.querySelector('[data-score-texto]');
    const cores = item.percentual >= 70 ? ['bg-score-alto', 'text-score-alto']
        : item.percentual >= 40 ? ['bg-score-medio', 'text-score-medio'] : ['bg-score-baixo', 'text-score-baixo'];
    barra.classList.add(cores[0]);
    numero.classList.add(cores[1]);
    barra.style.width = `${item.percentual}%`;
    numero.textContent = `${item.percentual}%`;
    texto(card, '[data-explicacao]', item.explicacao);
    card.querySelector('[data-competencias]').replaceChildren(
        ...item.atendidas.map((competencia) => tagCompetencia(competencia, false, doc)),
        ...item.naoIdentificadas.map((competencia) => tagCompetencia(competencia, true, doc)),
    );
    texto(card, '[data-experiencia]', `Experiência na área: ${item.experienciaNaArea ? 'sim' : 'não informado'}`);
    card.querySelector('[data-energia]').hidden = !vaga.temEnergia;
    texto(card, '[data-proximidade-energia]', `Energia: ${item.proximidadeEnergia}% de proximidade`);
    card.querySelector('[data-proximidade-energia]').hidden = item.proximidadeEnergia === null;
    card.querySelector('[data-energia-ausente]').hidden = item.proximidadeEnergia !== null;
    const form = card.querySelector('[data-mover-etapa]');
    if (!podeMover) {
        form.remove();
    } else {
        form.action = mesmaOrigem(item.etapaUrl, doc.baseURI).href;
        for (const [destino, rotulo, variante] of ETAPAS[item.etapa].acoes) {
            const botao = doc.createElement('button');
            botao.type = 'submit';
            botao.name = 'etapa';
            botao.value = destino;
            botao.className = 'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors duration-150 motion-reduce:transition-none disabled:cursor-not-allowed disabled:opacity-60 '
                + (variante === 'primario' ? 'bg-primaria-600 text-white shadow-sm hover:bg-primaria-700' : 'bg-superficie text-primaria-700 ring-1 ring-borda ring-inset hover:bg-primaria-50');
            botao.textContent = rotulo;
            const descricao = doc.createElement('span');
            descricao.className = 'sr-only';
            descricao.textContent = `: ${identificacao}`;
            botao.append(descricao);
            form.append(botao);
        }
    }
    return card;
}

export function validarQuadro(dados, base = globalThis.location?.href) {
    const vaga = dados?.vaga;
    const string = (valor) => typeof valor === 'string';
    const boolean = (valor) => typeof valor === 'boolean';
    const competencias = (itens) => Array.isArray(itens) && itens.every((item) => string(item.nome) && [1, 2, 3].includes(item.peso)
        && (item.origem === undefined || Object.hasOwn(ORIGENS, item.origem)));
    if (!vaga || !['titulo', 'area', 'tipo', 'status', 'url', 'statusUrl'].every((campo) => string(vaga[campo]))
        || !boolean(vaga.aberta) || !boolean(vaga.temEnergia) || !(vaga.descricao === null || string(vaga.descricao))
        || !(vaga.encerradaEm === null || string(vaga.encerradaEm))
        || !(vaga.aberta ? string(vaga.editarUrl) : vaga.editarUrl === null)
        || !Number.isInteger(dados.totalElegiveis) || dados.totalElegiveis < 0 || !competencias(dados.requisitos)
        || !dados.colunas || Object.keys(dados.colunas).length !== 3) {
        throw new ErroAjax('O servidor enviou dados inesperados para o quadro.');
    }
    mesmaOrigem(vaga.url, base);
    mesmaOrigem(vaga.statusUrl, base);
    if (vaga.aberta) {
        mesmaOrigem(vaga.editarUrl, base);
    }
    for (const etapa of Object.keys(ETAPAS)) {
        const itens = dados.colunas[etapa];
        if (!Array.isArray(itens) || !itens.every((item) => /^CAND-[0-9]{4}$/.test(item.codigo)
            && item.etapa === etapa && ['servidor', 'estagiario'].includes(item.tipo) && string(item.area)
            && string(item.perfilUrl) && string(item.etapaUrl)
            && (item.nome === null || (etapa !== 'triagem' && string(item.nome)))
            && ['elegivel', 'desativado', 'experienciaNaArea'].every((campo) => boolean(item[campo]))
            && Number.isInteger(item.percentual) && item.percentual >= 0 && item.percentual <= 100
            && (item.proximidadeEnergia === null || (Number.isInteger(item.proximidadeEnergia) && item.proximidadeEnergia >= 0 && item.proximidadeEnergia <= 100))
            && string(item.explicacao) && competencias(item.atendidas) && competencias(item.naoIdentificadas))) {
            throw new ErroAjax('O servidor enviou dados inesperados para o quadro.');
        }
        for (const item of itens) {
            mesmaOrigem(item.perfilUrl, base);
            mesmaOrigem(item.etapaUrl, base);
        }
    }
    return dados;
}

export function montarQuadro(dados, doc = document) {
    validarQuadro(dados, doc.baseURI);
    const quadro = modelo(doc, 'modelo-quadro');
    const { vaga } = dados;
    status(quadro, vaga);
    texto(quadro, '[data-titulo]', vaga.titulo);
    texto(quadro, '[data-descricao]', vaga.descricao);
    quadro.querySelector('[data-descricao]').hidden = !vaga.descricao;
    link(quadro, '[data-atualizar-quadro]', vaga.url, doc);
    if (vaga.aberta) {
        link(quadro, '[data-editar-vaga]', vaga.editarUrl, doc);
        quadro.querySelector('[data-encerrar-vaga]').action = mesmaOrigem(vaga.statusUrl, doc.baseURI).href;
        quadro.querySelector('[data-vaga-encerrada]').remove();
    } else {
        quadro.querySelector('[data-editar-vaga]').remove();
        quadro.querySelector('[data-encerrar-vaga]').remove();
        texto(quadro, '[data-encerrada-em]', vaga.encerradaEm);
    }
    texto(quadro, '[data-soma-pesos]', dados.requisitos.reduce((soma, item) => soma + item.peso, 0));
    quadro.querySelector('[data-requisitos]').replaceChildren(...dados.requisitos.map((item) => {
        const li = doc.createElement('li');
        li.append(tagCompetencia(item, false, doc));
        return li;
    }));
    texto(quadro, '[data-elegiveis]', `· ${quantidade(dados.totalElegiveis, 'candidato elegível', 'candidatos elegíveis')}`);
    const colunas = Object.entries(ETAPAS).map(([etapa, configuracao]) => {
        const coluna = modelo(doc, 'modelo-coluna');
        const itens = dados.colunas[etapa];
        coluna.dataset.etapa = etapa;
        coluna.classList.add(configuracao.borda);
        coluna.setAttribute('aria-labelledby', `coluna-${etapa}`);
        coluna.querySelector('[data-coluna-titulo]').id = `coluna-${etapa}`;
        texto(coluna, '[data-coluna-titulo]', configuracao.rotulo);
        texto(coluna, '[data-coluna-total]', itens.length);
        coluna.querySelector('[data-coluna-total]').setAttribute('aria-label', quantidade(itens.length, 'candidato', 'candidatos'));
        coluna.querySelector('[data-aviso-triagem]').hidden = etapa !== 'triagem';
        const semElegiveis = etapa === 'triagem' && dados.totalElegiveis === 0;
        coluna.querySelector('[data-sem-elegiveis]').hidden = itens.length !== 0 || !semElegiveis;
        coluna.querySelector('[data-coluna-vazia]').hidden = itens.length !== 0 || semElegiveis;
        texto(coluna, '[data-coluna-vazia]', configuracao.vazio);
        const lista = coluna.querySelector('[data-coluna-itens]');
        lista.hidden = itens.length === 0;
        lista.setAttribute('aria-label', `Candidatos em ${configuracao.rotulo}, por aderência`);
        lista.replaceChildren(...itens.map((item, indice) => {
            const li = doc.createElement('li');
            li.append(cardRanking(item, indice + 1, vaga, doc));
            return li;
        }));
        return coluna;
    });
    quadro.querySelector('[data-colunas]').replaceChildren(...colunas);
    return quadro;
}

export function iniciarVagas(doc = document) {
    const inicial = doc.getElementById('dados-vagas');
    if (!inicial) {
        return;
    }
    registrarRenderizador('quadro-vaga', montarQuadro);
    try {
        const dados = JSON.parse(inicial.textContent);
        if (inicial.dataset.tela === 'lista') {
            renderizarLista(dados, doc);
        } else if (inicial.dataset.tela === 'formulario') {
            preencherFormulario(dados, doc);
        } else {
            doc.getElementById('quadro-vaga').replaceChildren(montarQuadro(dados, doc));
            if (Object.keys(dados.errors ?? {}).length) {
                anunciar(Object.values(dados.errors).flat().join(' '), doc, 'erro');
            }
        }
        // Não mantenha uma segunda cópia dos nomes revelados depois de voltar à triagem.
        inicial.remove();
    } catch {
        anunciar('Não foi possível carregar a tela de vagas. Atualize a página para tentar novamente.', doc, 'erro');
    }
}

if (typeof document !== 'undefined') {
    iniciarVagas();
}
