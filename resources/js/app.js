import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import './vivo';
import './movil';

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

/** fetch con CSRF y JSON. Lanza Error con el mensaje del servidor si falla. */
async function api(url, { method = 'GET', body = null } = {}) {
    const opts = { method, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    if (body instanceof FormData) opts.body = body;
    else if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
    const r = await fetch(url, opts);
    const data = await r.json().catch(() => ({}));
    if (!r.ok) {
        const primero = data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(primero || data.message || 'No se pudo completar la acción.');
    }
    return data;
}
window.api = api;

/** Aviso flotante (toast). tipo: exito | error | info */
window.avisar = (mensaje, tipo = 'exito', extra = {}) => window.dispatchEvent(new CustomEvent('aviso', { detail: { mensaje, tipo, ...extra } }));

/**
 * Reduce una foto de celular (4–8 MB) a ~300 KB antes de subirla.
 * Devuelve un File JPEG de máximo 1600 px por lado. Los PDF y otros archivos pasan igual.
 */
async function comprimirImagen(file, max = 1600, calidad = 0.82) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif' || file.type === 'image/svg+xml') return file;
    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) return file;
    const escala = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * escala);
    canvas.height = Math.round(bitmap.height * escala);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise((res) => canvas.toBlob(res, 'image/jpeg', calidad));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
}
window.comprimirImagen = comprimirImagen;

/** Subida de archivos adjuntos: <div x-data="subidor({tipo, id, categoria})"> */
Alpine.data('subidor', (cfg) => ({
    subiendo: false,
    progreso: '',
    async subir(event) {
        const archivos = [...event.target.files];
        if (!archivos.length) return;
        this.subiendo = true;
        try {
            for (const [i, original] of archivos.entries()) {
                this.progreso = `${i + 1} de ${archivos.length}`;
                const fd = new FormData();
                fd.append('archivo', await comprimirImagen(original));
                fd.append('tipo', cfg.tipo);
                fd.append('id', cfg.id);
                fd.append('categoria', this.$refs.categoria?.value || cfg.categoria || 'foto');
                await api(cfg.url, { method: 'POST', body: fd });
            }
            window.location.reload();
        } catch (e) {
            avisar(e.message, 'error');
        } finally {
            this.subiendo = false;
            event.target.value = '';
        }
    },
}));

/**
 * Campana de notificaciones: consulta cada 30 s (y al volver a la pestaña). Si llega algo
 * nuevo lo avisa con un toast, mueve la campana y pone el número en el título de la pestaña.
 */
Alpine.data('campana', (url) => ({
    abierto: false,
    sinLeer: 0,
    items: [],
    primera: true,
    sacudir: false,
    async cargar() {
        try {
            const d = await api(url);
            const nuevas = d.items.filter((n) => !n.leida && !this.items.some((v) => v.id === n.id));
            if (!this.primera && nuevas.length) {
                const n = nuevas[0];
                avisar(n.mensaje, 'info', { titulo: nuevas.length > 1 ? `${n.titulo} (+${nuevas.length - 1} más)` : n.titulo, url: n.url });
                this.sacudir = true;
                setTimeout(() => (this.sacudir = false), 900);
            }
            this.primera = false;
            this.sinLeer = d.sin_leer;
            this.items = d.items;
            document.title = (d.sin_leer ? `(${d.sin_leer > 9 ? '9+' : d.sin_leer}) ` : '') + document.title.replace(/^\(\d+\+?\)\s/, '');
        } catch (e) { /* sin red: se reintenta en el siguiente ciclo */ }
    },
    init() {
        this.cargar();
        setInterval(() => document.visibilityState === 'visible' && this.cargar(), 30000);
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.cargar());
        // Después de una acción propia (asignar, aprobar…) la campana se pone al día sin esperar.
        window.addEventListener('vivo:actualizado', () => this.cargar());
    },
}));

/**
 * Menú lateral: contraer a íconos (Ctrl/⌘ + B), secciones plegables y etiqueta flotante
 * cuando está contraído. Todo se recuerda en el navegador.
 */
Alpine.data('menuLateral', () => {
    const leer = (k, def) => { try { return JSON.parse(localStorage.getItem(k)) ?? def; } catch { return def; } };
    const guardar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* */ } };
    return {
        colapsado: document.documentElement.classList.contains('menu-colapsado'),
        grupos: leer('tf-menu-grupos', {}),
        tip: { visible: false, texto: '', ayuda: '', top: 0 },
        init() {
            // Las transiciones se activan después de pintar: al cargar la página no hay animación.
            requestAnimationFrame(() => requestAnimationFrame(() => document.documentElement.classList.add('menu-animado')));
            // Alpine ya aplicó las secciones plegadas: se quita el estilo previo que evitaba el salto al cargar.
            this.$nextTick(() => document.getElementById('tf-grupos-previo')?.remove());
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'b') { e.preventDefault(); this.alternar(); }
            });
        },
        alternar() {
            this.colapsado = !this.colapsado;
            document.documentElement.classList.toggle('menu-colapsado', this.colapsado);
            guardar('tf-menu-colapsado', this.colapsado ? 1 : 0);
            this.tip.visible = false;
        },
        grupoAbierto(g) { return this.grupos[g] !== false; },
        alternarGrupo(g) { this.grupos[g] = !this.grupoAbierto(g); guardar('tf-menu-grupos', this.grupos); },
        mostrarTip(e, texto, ayuda = null) {
            if (!this.colapsado || window.innerWidth < 1024) return;
            const r = e.currentTarget.getBoundingClientRect();
            this.tip = { visible: true, texto, ayuda: ayuda || '', top: r.top + r.height / 2 };
        },
        ocultarTip() { this.tip.visible = false; },
    };
});

/** Filas dinámicas en formularios (especificaciones, líneas de movimiento, checklist). */
Alpine.data('filas', (iniciales = [], plantilla = {}) => ({
    filas: iniciales.length ? iniciales : [{ ...plantilla }],
    agregar() { this.filas.push({ ...plantilla }); },
    quitar(i) { this.filas.splice(i, 1); if (!this.filas.length) this.agregar(); },
}));

/**
 * Confirmación con diseño propio. Uso en Blade:
 *   <form method="POST" data-confirmar="¿Anular este movimiento?" data-titulo="Anular" data-boton="Sí, anular" data-peligro>
 * o desde JS: if (await confirmar({ mensaje: '…' })) { … }
 */
let resolverConfirmacion = null;
Alpine.data('confirmacion', () => ({
    abierto: false, titulo: '', mensaje: '', boton: 'Confirmar', peligro: false,
    init() {
        window.confirmar = (o = {}) => new Promise((res) => {
            Object.assign(this, { titulo: o.titulo || '¿Continuar?', mensaje: o.mensaje || '', boton: o.boton || 'Confirmar', peligro: !!o.peligro });
            resolverConfirmacion = res;
            this.abierto = true;
            this.$nextTick(() => this.$refs.aceptar.focus());
        });
    },
    aceptar() { this.abierto = false; resolverConfirmacion?.(true); },
    cancelar() { if (!this.abierto) return; this.abierto = false; resolverConfirmacion?.(false); },
}));

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.dataset.confirmar || form.dataset.confirmado) return;
    e.preventDefault();
    const ok = await window.confirmar({
        titulo: form.dataset.titulo, mensaje: form.dataset.confirmar,
        boton: form.dataset.boton, peligro: form.hasAttribute('data-peligro'),
    });
    if (ok) { form.dataset.confirmado = '1'; form.requestSubmit(e.submitter); }
});

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();
