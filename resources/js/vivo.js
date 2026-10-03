import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';

/**
 * Pantallas "en vivo".
 * - Buscadores (<x-filtros>): buscan mientras se escribe, sin Enter ni recargar.
 * - <main data-vivo>: se vuelve a pedir cada 30 s y se actualiza en su lugar (morph), sin perder
 *   el scroll ni lo que se está escribiendo. No refresca si el usuario está escribiendo, tiene un
 *   formulario a medio llenar, un diálogo abierto o está arrastrando algo.
 * - El menú lateral también actualiza sus contadores.
 */
Alpine.plugin(morph);

const INTERVALO = 30_000;
let ultimo = Date.now();
let enCurso = null;
let presionando = false;

const main = () => document.querySelector('main[data-vivo]');

function barra(activa) {
    let b = document.getElementById('barra-carga');
    if (!b) {
        b = Object.assign(document.createElement('div'), { id: 'barra-carga' });
        document.body.appendChild(b);
    }
    b.classList.toggle('activa', activa);
}

function ocupado() {
    const m = main();
    if (!m || document.visibilityState !== 'visible' || presionando) return true;
    const a = document.activeElement;
    if (a && m.contains(a) && a.matches('input:not([type=checkbox]):not([type=radio]), textarea, select, [contenteditable]')) return true;
    if (m.querySelector('form[data-sucio]')) return true;
    const visible = (e) => (e.checkVisibility ? e.checkVisibility() : e.offsetParent !== null);
    if ([...document.querySelectorAll('[aria-modal="true"], [role="alertdialog"]')].some(visible)) return true;
    if (document.querySelector('.arrastrando')) return true;
    return false;
}

/** Trae una URL y actualiza <main> y los contadores del menú. */
async function actualizar(url = location.href, { silencioso = false } = {}) {
    const m = main();
    if (!m) return false;
    enCurso?.abort();
    const control = (enCurso = new AbortController());
    if (!silencioso) barra(true);
    try {
        const r = await fetch(url, { headers: { 'X-Vivo': '1', Accept: 'text/html' }, signal: control.signal, credentials: 'same-origin' });
        // Sesión vencida o sin permiso: que el navegador haga lo normal en la próxima navegación.
        if (r.redirected && new URL(r.url).pathname === '/login') { location.href = r.url; return false; }
        if (!r.ok) return false;
        const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
        const nuevo = doc.querySelector('main[data-vivo]');
        if (!nuevo) return false;
        if (silencioso && ocupado()) return false; // el usuario empezó a escribir mientras llegaba
        Alpine.morph(m, nuevo.outerHTML, {
            // Un componente cuyo x-data cambió (datos del servidor) se reconstruye; si no, conserva su estado.
            key: (el) => el.id || (el.getAttribute?.('x-data')?.length > 2 ? 'xd:' + el.getAttribute('x-data') : undefined),
            updating(el, _a, _b, skip) { if (el === document.activeElement || el.hasAttribute?.('data-vivo-fijo')) skip(); },
        });
        // Contadores del menú lateral (OT atrasadas, por aprobar, bajo mínimo).
        document.querySelectorAll('aside a.nav-link[href]').forEach((a) => {
            const n = doc.querySelector(`aside a.nav-link[href="${a.getAttribute('href')}"]`);
            if (n && n.innerHTML !== a.innerHTML) Alpine.morph(a, n.outerHTML);
        });
        const titulo = doc.querySelector('header h1');
        if (titulo) document.querySelector('header h1').textContent = titulo.textContent;
        ultimo = Date.now();
        window.dispatchEvent(new CustomEvent('vivo:actualizado'));
        return true;
    } catch (e) {
        if (e.name !== 'AbortError') console.warn('No se pudo actualizar la pantalla', e);
        return false;
    } finally {
        if (enCurso === control) enCurso = null;
        barra(false);
    }
}
window.actualizarPantalla = actualizar;

// ── Refresco automático ────────────────────────────────────────────────
setInterval(() => { if (!ocupado() && Date.now() - ultimo >= INTERVALO - 500) actualizar(location.href, { silencioso: true }); }, 5_000);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() - ultimo > INTERVALO && !ocupado()) actualizar(location.href, { silencioso: true });
});
window.addEventListener('online', () => !ocupado() && actualizar(location.href, { silencioso: true }));
document.addEventListener('pointerdown', () => { presionando = true; });
document.addEventListener('pointerup', () => { presionando = false; });
document.addEventListener('pointercancel', () => { presionando = false; });

// Un formulario con cambios sin guardar no se pisa.
document.addEventListener('input', (e) => {
    const f = e.target.closest('main[data-vivo] form');
    if (f && !f.hasAttribute('data-filtro-vivo') && f.method.toLowerCase() === 'post') f.dataset.sucio = '1';
});
document.addEventListener('submit', (e) => { e.target.removeAttribute?.('data-sucio'); }, true);

// ── Búsqueda mientras se escribe ───────────────────────────────────────
function urlDe(form) {
    const url = new URL(form.action || location.href, location.origin);
    url.search = '';
    new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') url.searchParams.append(k, v); });
    return url;
}
async function filtrar(form) {
    const url = urlDe(form);
    // Si la búsqueda es de otra página (o no hay <main data-vivo>), navegación normal.
    if (!main() || url.pathname !== location.pathname) { location.href = url; return; }
    form.setAttribute('aria-busy', 'true');
    const ok = await actualizar(url.href);
    form.removeAttribute('aria-busy');
    if (ok) history.replaceState(history.state, '', url);
    else location.href = url;
}
let espera;
document.addEventListener('input', (e) => {
    const form = e.target.closest('form[data-filtro-vivo]');
    if (!form || e.target.tagName === 'SELECT') return;
    clearTimeout(espera);
    espera = setTimeout(() => filtrar(form), 300);
});
document.addEventListener('change', (e) => {
    const form = e.target.closest('form[data-filtro-vivo]');
    if (form && (e.target.tagName === 'SELECT' || e.target.type === 'date')) { clearTimeout(espera); filtrar(form); }
});
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-filtro-vivo]');
    if (!form) return;
    e.preventDefault();
    clearTimeout(espera);
    filtrar(form);
});
// Esc limpia el buscador.
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || !e.target.matches('form[data-filtro-vivo] input[name=q]') || !e.target.value) return;
    e.target.value = '';
    filtrar(e.target.form);
});
