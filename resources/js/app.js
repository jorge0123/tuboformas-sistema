import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

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
window.avisar = (mensaje, tipo = 'exito') => window.dispatchEvent(new CustomEvent('aviso', { detail: { mensaje, tipo } }));

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

/** Campana de notificaciones con consulta periódica. */
Alpine.data('campana', (url) => ({
    abierto: false,
    sinLeer: 0,
    items: [],
    async cargar() {
        try {
            const d = await api(url);
            this.sinLeer = d.sin_leer;
            this.items = d.items;
        } catch (e) { /* sin red: se reintenta en el siguiente ciclo */ }
    },
    init() {
        this.cargar();
        setInterval(() => document.visibilityState === 'visible' && this.cargar(), 60000);
    },
}));

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
