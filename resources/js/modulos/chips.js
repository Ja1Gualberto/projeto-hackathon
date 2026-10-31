const normalizar = (texto) => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();

document.querySelectorAll('[data-filtrar-chips]').forEach((input) => {
    const alvo = document.querySelector(input.dataset.filtrarChips);

    if (!alvo) {
        return;
    }

    const filtrar = () => {
        const termo = normalizar(input.value);

        alvo.querySelectorAll('[data-chip]').forEach((chip) => {
            chip.hidden = !normalizar(chip.textContent).includes(termo);
        });
    };

    input.addEventListener('input', filtrar);
    filtrar();
});
