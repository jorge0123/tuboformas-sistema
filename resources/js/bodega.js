import Alpine from 'alpinejs';

// Las librerías de QR se cargan solo en las pantallas que las usan (Vite las separa en otro archivo).
const cargarJsQR = () => import('jsqr').then((m) => m.default);
const BORRADOR = 'tf-ingreso-rapido';

/** Color de muestra para las variantes (Gris, Naranja…). */
const COLORES = { gris: '#9a9590', naranja: '#f97316', blanco: '#ffffff', negro: '#1f1d1b', azul: '#2563eb', verde: '#16a34a', rojo: '#d90016', amarillo: '#facc15' };
window.colorMuestra = (nombre) => COLORES[String(nombre || '').trim().toLowerCase()] || '#d6d3d1';

/**
 * Ingreso rápido de producto terminado.
 * Escanear (cámara en vivo o foto) → propuesta "¿Registrar Copla 3/4" gris, 300 pza?" → confirmar o corregir
 * → se acumula en la lista → "Registrar ingreso" hace un solo movimiento.
 */
Alpine.data('ingresoRapido', ({ productos, limpiar = false }) => ({
    productos,
    lineas: [],
    propuesta: null,
    camara: false,
    buscando: false,
    leyendoFoto: false,
    q: '',
    stream: null,

    init() {
        try {
            if (limpiar) localStorage.removeItem(BORRADOR);
            const b = JSON.parse(localStorage.getItem(BORRADOR) || '[]');
            // Solo se recuperan líneas de productos que siguen activos.
            this.lineas = b.filter((l) => this.producto(l.producto_id));
            if (this.lineas.length) avisar(`Recuperamos ${this.lineas.length} ${this.lineas.length === 1 ? 'línea' : 'líneas'} que no se habían registrado.`, 'info');
        } catch { /* sin almacenamiento: se trabaja en memoria */ }
        this.$watch('lineas', (v) => { try { localStorage.setItem(BORRADOR, JSON.stringify(v)); } catch { /* */ } });
    },
    destroy() { this.cerrarCamara(); },

    // ── Datos ────────────────────────────────────────────────────────
    producto(id) { return this.productos.find((p) => p.id == id); },
    presentacion(p, id) { return p?.presentaciones.find((x) => x.id == id) || null; },
    variantes(p) { return p ? this.productos.filter((x) => x.familia === p.familia) : []; },
    nombre(p) { return p ? p.nombre + (p.color ? ' · ' + p.color : '') : ''; },
    describir(l) {
        const p = this.producto(l.producto_id);
        const pres = this.presentacion(p, l.presentacion_id);
        return { p, pres, base: (Number(l.cantidad) || 0) * (pres?.factor || 1) };
    },
    fmt(n) { return Number(n || 0).toLocaleString('es-GT', { maximumFractionDigits: 3 }); },
    get resultados() {
        const t = this.q.trim().toLowerCase();
        const lista = t ? this.productos.filter((p) => `${p.codigo} ${p.nombre} ${p.color || ''} ${p.medida || ''}`.toLowerCase().includes(t)) : this.productos;
        return lista.slice(0, 40);
    },
    get totalBase() { return this.lineas.reduce((s, l) => s + this.describir(l).base, 0); },
    get resumen() {
        return this.lineas.map((l) => {
            const { p, pres, base } = this.describir(l);
            return `• ${this.nombre(p)}: ${this.fmt(l.cantidad)} ${pres ? pres.nombre : p.unidad} = ${this.fmt(base)} ${p.unidad}`;
        }).join('\n');
    },

    // ── Lectura del QR ───────────────────────────────────────────────
    async abrirCamara() {
        // getUserMedia solo existe en https o localhost; en http (celular en la red local) se usa la foto.
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            avisar('La cámara en vivo necesita https. Tomemos una foto del QR.', 'info');
            this.$refs.foto.click();
            return;
        }
        try {
            const jsQR = await cargarJsQR();
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            this.camara = true;
            await this.$nextTick();
            const video = this.$refs.video;
            video.srcObject = this.stream;
            await video.play();
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const cuadro = () => {
                if (!this.camara) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    const esc = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
                    canvas.width = Math.round(video.videoWidth * esc);
                    canvas.height = Math.round(video.videoHeight * esc);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const qr = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                    if (qr?.data) { this.cerrarCamara(); this.procesar(qr.data); return; }
                }
                requestAnimationFrame(cuadro);
            };
            requestAnimationFrame(cuadro);
        } catch {
            this.cerrarCamara();
            avisar('No se pudo abrir la cámara. Revisa el permiso del navegador o usa "Tomar foto".', 'error');
        }
    },
    cerrarCamara() {
        this.camara = false;
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
    },
    async leerFoto(e) {
        const archivo = e.target.files[0];
        e.target.value = '';
        if (!archivo) return;
        this.leyendoFoto = true;
        try {
            const jsQR = await cargarJsQR();
            const bmp = await createImageBitmap(archivo);
            let texto = null;
            // Las fotos de celular son enormes: se prueba a varias escalas hasta encontrar el código.
            for (const max of [1200, 800, 1800, 500]) {
                const esc = Math.min(1, max / Math.max(bmp.width, bmp.height));
                const c = document.createElement('canvas');
                c.width = Math.round(bmp.width * esc);
                c.height = Math.round(bmp.height * esc);
                const ctx = c.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(bmp, 0, 0, c.width, c.height);
                const img = ctx.getImageData(0, 0, c.width, c.height);
                texto = jsQR(img.data, img.width, img.height)?.data;
                if (texto) break;
            }
            if (texto) this.procesar(texto);
            else avisar('No encontré un QR en la foto. Acércate a la etiqueta y que salga completa y enfocada.', 'error');
        } catch {
            avisar('No se pudo leer la foto.', 'error');
        } finally {
            this.leyendoFoto = false;
        }
    },
    /** Texto del QR: TF:<código>:<presentación> (o solo el código del producto). */
    procesar(texto) {
        const partes = String(texto).trim().split(':');
        const esNuestro = partes[0] === 'TF';
        const codigo = (esNuestro ? partes[1] : partes[0] || '').toLowerCase();
        const p = this.productos.find((x) => x.codigo.toLowerCase() === codigo);
        if (!p) {
            navigator.vibrate?.([70, 50, 70]);
            avisar(`El código "${texto}" no es de un producto terminado activo.`, 'error');
            return;
        }
        navigator.vibrate?.(60);
        const unidades = esNuestro ? Number(partes[3]) : 0;
        // Etiqueta personalizada (TF:código:0:4): propone esas unidades sueltas.
        if (unidades > 0) { this.proponer(p, null); this.propuesta.cantidad = unidades; return; }
        this.proponer(p, esNuestro ? Number(partes[2]) || null : undefined);
    },

    // ── Propuesta y lista ────────────────────────────────────────────
    /** presId: null = unidad base; undefined = la primera presentación del producto. */
    proponer(p, presId) {
        const pres = presId === undefined ? p.presentaciones[0] : this.presentacion(p, presId);
        this.propuesta = { producto_id: p.id, presentacion_id: pres?.id ?? null, cantidad: 1 };
        this.buscando = false;
        this.q = '';
    },
    cambiarVariante(v) {
        const actual = this.presentacion(this.producto(this.propuesta.producto_id), this.propuesta.presentacion_id);
        const igual = actual ? v.presentaciones.find((x) => x.nombre === actual.nombre) || v.presentaciones[0] : null;
        this.propuesta.producto_id = v.id;
        this.propuesta.presentacion_id = igual?.id ?? null;
    },
    sumar(obj, n) { obj.cantidad = Math.max(1, (Number(obj.cantidad) || 0) + n); },
    aceptar() {
        const c = Number(this.propuesta.cantidad);
        if (!(c > 0)) { avisar('La cantidad debe ser mayor a cero.', 'error'); return; }
        const { p, base } = this.describir({ ...this.propuesta, cantidad: c });
        const ya = this.lineas.find((l) => l.producto_id === this.propuesta.producto_id && l.presentacion_id === this.propuesta.presentacion_id);
        if (ya) { ya.cantidad = Number(ya.cantidad) + c; ya.marca = Date.now(); } else this.lineas.unshift({ ...this.propuesta, cantidad: c, marca: Date.now() });
        avisar(`${this.nombre(p)} · ${this.fmt(base)} ${p.unidad} agregado.`);
        this.propuesta = null;
    },
    quitar(i) { this.lineas.splice(i, 1); },
    abrirBusqueda() {
        this.propuesta = null;
        this.buscando = true;
        this.$nextTick(() => this.$refs.buscar?.focus());
    },
}));

/**
 * Hoja de etiquetas QR: se agregan productos con su presentación y número de copias
 * ("20 × Copla 3/4" gris, bolsa x 300"), se ve la hoja tal cual y se imprime.
 * La hoja se recuerda en el navegador por si se cierra la pestaña.
 */
const HOJA = 'tf-hoja-etiquetas';
const MAX_COPIAS = 500;
Alpine.data('hojaEtiquetas', ({ catalogo, inicial = null }) => ({
    catalogo,
    cola: [],
    q: '',
    abierto: false,
    sel: { producto_id: null, presentacion_id: null, copias: 20, cantidad: null, empaque: 'Paquete' },
    tamano: 'mediana',
    qrs: {},
    QRCode: null,

    async init() {
        try {
            this.cola = JSON.parse(localStorage.getItem(HOJA) || '[]').filter((i) => this.producto(i.producto_id));
            this.tamano = localStorage.getItem(HOJA + '-tamano') || 'mediana';
        } catch { /* */ }
        this.$watch('cola', (v) => { try { localStorage.setItem(HOJA, JSON.stringify(v)); } catch { /* */ } });
        this.$watch('tamano', (v) => { try { localStorage.setItem(HOJA + '-tamano', v); } catch { /* */ } });
        this.QRCode = (await import('qrcode')).default;
        if (inicial) this.elegir(this.producto(inicial.producto_id), inicial.presentacion_id, inicial.copias);
        await Promise.all(this.cola.map((i) => this.generarQr(i)));
    },

    producto(id) { return this.catalogo.find((p) => p.id == id); },
    presentacion(p, id) { return p?.presentaciones.find((x) => x.id == id) || null; },
    nombre(p) { return p ? p.nombre + (p.color ? ' · ' + p.color : '') : ''; },
    /** QR: TF:<código>:<presentación>[:<unidades>] — la 4a parte es para etiquetas personalizadas. */
    texto(i) { return `TF:${this.producto(i.producto_id).codigo}:${i.presentacion_id || 0}` + (i.cantidad ? `:${i.cantidad}` : ''); },
    mismo(a, b) { return a.producto_id === b.producto_id && (a.presentacion_id ?? null) === (b.presentacion_id ?? null) && (a.cantidad ?? null) === (b.cantidad ?? null) && (a.empaque ?? '') === (b.empaque ?? ''); },
    etiquetaPres(i) {
        const p = this.producto(i.producto_id);
        if (i.cantidad) return `${i.empaque || 'Paquete'} x ${this.fmt(i.cantidad)}`;
        const pres = this.presentacion(p, i.presentacion_id);
        return pres ? `${pres.nombre}` : `Unidad (${p.unidad})`;
    },
    fmt(n) { return Number(n || 0).toLocaleString('es-GT', { maximumFractionDigits: 3 }); },
    contenido(i) {
        const p = this.producto(i.producto_id);
        if (i.cantidad) return `${this.fmt(i.cantidad)} ${p.unidad}`;
        const pres = this.presentacion(p, i.presentacion_id);
        return pres ? `${Number(pres.factor).toLocaleString('es-GT')} ${p.unidad}` : `1 ${p.unidad}`;
    },
    /** Texto de la banda negra: "Bolsa x 300 pza" (sin repetir el número si ya viene en el nombre). */
    banda(i) {
        const p = this.producto(i.producto_id);
        if (i.cantidad) return `${i.empaque || 'Paquete'} x ${this.fmt(i.cantidad)} ${p.unidad}`;
        const pres = this.presentacion(p, i.presentacion_id);
        if (!pres) return `Unidad · 1 ${p.unidad}`;
        return pres.nombre.includes(String(pres.factor)) ? `${pres.nombre} ${p.unidad}` : `${pres.nombre} · ${this.contenido(i)}`;
    },
    get seleccionado() { return this.producto(this.sel.producto_id); },
    get sugerencias() {
        const t = this.q.trim().toLowerCase();
        if (!t) return this.catalogo.filter((p) => p.tipo === 'producto_terminado').slice(0, 12);
        // Cada palabra debe aparecer: "copla 34 naranja" o "cop 3/4 gris".
        const palabras = t.split(/\s+/);
        return this.catalogo.filter((p) => {
            const h = `${p.codigo} ${p.nombre} ${p.color || ''} ${p.medida || ''}`.toLowerCase();
            return palabras.every((w) => h.includes(w));
        }).slice(0, 12);
    },
    get total() { return this.cola.reduce((s, i) => s + Number(i.copias || 0), 0); },
    /** Una entrada por etiqueta impresa. */
    get hoja() {
        return this.cola.flatMap((i, n) => Array.from({ length: Math.min(MAX_COPIAS, Number(i.copias) || 0) }, (_, k) => ({ key: `${n}-${k}`, i })));
    },
    get columnas() { return { pequena: 'grid-cols-4', mediana: 'grid-cols-3', grande: 'grid-cols-2' }[this.tamano]; },

    elegir(p, presId, copias) {
        if (!p) return;
        const pres = this.presentacion(p, presId) || p.presentaciones[0] || null;
        this.sel = { producto_id: p.id, presentacion_id: pres?.id ?? null, copias: copias || this.sel.copias || 20, cantidad: null, empaque: this.sel.empaque || 'Paquete' };
        this.q = '';
        this.abierto = false;
        this.$nextTick(() => this.$refs.copias?.select());
    },
    async agregar() {
        if (!this.seleccionado) { avisar('Primero elige un producto.', 'error'); this.$refs.buscar.focus(); return; }
        const copias = Math.min(MAX_COPIAS, Math.max(1, Math.round(Number(this.sel.copias) || 0)));
        const personalizada = this.sel.presentacion_id === 'otra';
        const cantidad = personalizada ? Number(this.sel.cantidad) : null;
        if (personalizada && !(cantidad > 0)) { avisar('Indica cuántas unidades lleva cada empaque.', 'error'); this.$refs.cantidad?.focus(); return; }
        const item = {
            producto_id: this.sel.producto_id, presentacion_id: personalizada ? null : this.sel.presentacion_id, copias,
            ...(personalizada ? { cantidad, empaque: (this.sel.empaque || 'Paquete').trim() } : {}),
        };
        const ya = this.cola.find((i) => this.mismo(i, item));
        if (ya) ya.copias = Math.min(MAX_COPIAS, Number(ya.copias) + copias);
        else this.cola.push(item);
        await this.generarQr(item);
        avisar(`${copias} ${copias === 1 ? 'etiqueta' : 'etiquetas'} de ${this.nombre(this.seleccionado)} agregadas.`);
        this.sel = { producto_id: null, presentacion_id: null, copias: 20, cantidad: null, empaque: this.sel.empaque };
        this.$nextTick(() => this.$refs.buscar.focus());
    },
    async generarQr(i) {
        const t = this.texto(i);
        if (this.qrs[t] || !this.QRCode) return;
        this.qrs[t] = await this.QRCode.toDataURL(t, { margin: 1, width: 300, errorCorrectionLevel: 'M' });
    },
    quitar(n) { this.cola.splice(n, 1); },
    async vaciar() {
        if (await confirmar({ titulo: 'Vaciar la hoja', mensaje: 'Se quitan todas las etiquetas de la hoja.', boton: 'Vaciar', peligro: true })) this.cola = [];
    },
    imprimir() {
        if (!this.total) { avisar('Agrega al menos una etiqueta.', 'error'); return; }
        window.print();
    },
}));
