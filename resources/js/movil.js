/**
 * Ajustes para celular que dependen del contenido:
 * - Etiqueta cada celda de .tabla con su encabezado (data-label) para mostrarla como tarjeta.
 * - Marca las celdas vacías para ocultarlas y las pestañas que ya llegaron al final.
 * Se vuelve a aplicar cuando la pantalla cambia (búsqueda en vivo, refresco, Alpine).
 */
function etiquetarTablas(raiz = document) {
    raiz.querySelectorAll('table.tabla').forEach((t) => {
        const ths = [...t.querySelectorAll('thead th')];
        const enc = ths.map((th) => th.textContent.trim());
        // <th data-movil="ocultar">: columna que en el celular no aporta (ej. existencia por bodega; ya está el total).
        const ocultas = ths.map((th) => th.dataset.movil === 'ocultar');
        let visibles = 0;
        t.querySelectorAll('tbody tr').forEach((tr) => {
            let i = 0;
            [...tr.children].forEach((td) => {
                const etiqueta = enc[i] ?? '';
                if (etiqueta && td.dataset.label !== etiqueta) td.dataset.label = etiqueta;
                const vacio = !td.textContent.trim() && !td.querySelector('img, svg, input, select, button, a, form');
                td.toggleAttribute('data-vacio', vacio);
                // Botones/acciones sin encabezado ocupan todo el ancho de la tarjeta.
                td.toggleAttribute('data-ancho', !etiqueta && i > 0);
                td.toggleAttribute('data-movil-oculto', !!ocultas[i]);
                i += Number(td.getAttribute('colspan') || 1);
            });
            if (tr === t.tBodies[0]?.rows[0]) {
                visibles = [...tr.children].filter((td, k) => k > 0 && td.dataset.label && !td.hasAttribute('data-movil-oculto') && !td.classList.contains('hidden')).length;
            }
        });
        // Con 5 datos o más la tarjeta usa 3 columnas: la mitad de alto.
        t.dataset.columnas = visibles >= 5 ? '3' : '2';
    });
}

function pestanas(raiz = document) {
    raiz.querySelectorAll('.pestanas').forEach((p) => {
        const revisar = () => p.classList.toggle('al-final', p.scrollLeft + p.clientWidth >= p.scrollWidth - 4);
        if (!p.dataset.observada) {
            p.dataset.observada = '1';
            p.addEventListener('scroll', revisar, { passive: true });
            p.addEventListener('click', (e) => e.target.closest('.pestana')?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }));
        }
        revisar();
    });
}

function aplicar() { etiquetarTablas(); pestanas(); }

let pendiente = false;
new MutationObserver(() => {
    if (pendiente) return;
    pendiente = true;
    requestAnimationFrame(() => { pendiente = false; aplicar(); });
}).observe(document.documentElement, { childList: true, subtree: true });
document.addEventListener('DOMContentLoaded', aplicar);
