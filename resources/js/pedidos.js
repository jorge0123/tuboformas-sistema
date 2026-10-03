import Alpine from 'alpinejs';

const fmt = (n) => Number(n || 0).toLocaleString('es-GT', { maximumFractionDigits: 3 });

/**
 * Formulario de pedido: cliente (con alta rápida), datos de entrega que se llenan solos al elegir
 * el cliente, y líneas de producto con la existencia disponible a la vista.
 */
Alpine.data('formPedido', ({ clientes, productos, clienteInicial = null, lineas = [], bodega, urlCliente }) => ({
    clientes,
    productos,
    clienteId: clienteInicial,
    buscarCliente: '',
    abiertoCliente: false,
    nuevo: null,
    bodega,
    lineas: lineas.length ? lineas.map((l, i) => ({ ...l, clave: i, busqueda: '', abierto: false })) : [],
    contador: 100,
    fmt,

    init() {
        if (!this.lineas.length) this.agregar(false);
    },
    get cliente() { return this.clientes.find((c) => c.id == this.clienteId); },
    get clientesFiltrados() {
        const t = this.buscarCliente.trim().toLowerCase();
        const lista = t ? this.clientes.filter((c) => `${c.nombre} ${c.nit || ''} ${c.contacto || ''} ${c.municipio || ''}`.toLowerCase().includes(t)) : this.clientes;
        return lista.slice(0, 30);
    },
    elegirCliente(c) {
        this.clienteId = c.id;
        this.abiertoCliente = false;
        this.buscarCliente = '';
        // Los datos de entrega salen del cliente; se pueden cambiar para este pedido.
        const f = this.$root.closest('form');
        const poner = (n, v) => { const el = f.querySelector(`[name=${n}]`); if (el && v && !el.dataset.tocado) el.value = v; };
        poner('direccion_entrega', [c.direccion, c.municipio].filter(Boolean).join(', '));
        poner('contacto_nombre', c.contacto);
        poner('contacto_telefono', c.telefono);
    },
    async guardarCliente() {
        try {
            const c = await api(urlCliente, { method: 'POST', body: this.nuevo });
            this.clientes.push(c);
            this.clientes.sort((a, b) => a.nombre.localeCompare(b.nombre));
            this.elegirCliente(c);
            this.nuevo = null;
            avisar(`Cliente ${c.nombre} registrado.`);
        } catch (e) { avisar(e.message, 'error'); }
    },

    producto(id) { return this.productos.find((p) => p.id == id); },
    filtrar(t) {
        t = (t || '').trim().toLowerCase();
        const palabras = t.split(/\s+/).filter(Boolean);
        return this.productos.filter((p) => palabras.every((w) => `${p.codigo} ${p.nombre}`.toLowerCase().includes(w))).slice(0, 40);
    },
    agregar(enfocar = true) {
        this.lineas.push({ clave: this.contador++, producto_id: '', presentacion_id: '', cantidad: '', notas: '', busqueda: '', abierto: enfocar });
        if (enfocar) this.$nextTick(() => this.$root.querySelectorAll('[data-buscar-linea]').item(this.lineas.length - 1)?.focus());
    },
    quitar(i) { this.lineas.splice(i, 1); if (!this.lineas.length) this.agregar(false); },
    elegir(l, p) {
        l.producto_id = p.id;
        l.presentacion_id = p.presentaciones[0]?.id ?? '';
        l.abierto = false;
        l.busqueda = '';
        this.$nextTick(() => this.$root.querySelector(`[data-cantidad="${l.clave}"]`)?.focus());
    },
    factor(l) { return this.producto(l.producto_id)?.presentaciones.find((x) => x.id == l.presentacion_id)?.factor || 1; },
    base(l) { return (Number(l.cantidad) || 0) * this.factor(l); },
    stock(l) { return this.producto(l.producto_id)?.stock?.[this.bodega] || 0; },
    excede(l) { return l.producto_id && this.base(l) > this.stock(l); },
    get totalUnidades() { return this.lineas.reduce((s, l) => s + this.base(l), 0); },
}));

/**
 * Armar el pedido en bodega: cada línea se marca al tocarla (se guarda al instante).
 * Si no alcanzó, se indica cuánto se armó. El botón "Pedido listo" se habilita al completar todas.
 */
Alpine.data('armarPedido', ({ url, lineas }) => ({
    lineas,
    guardando: null,
    fmt,
    get armadas() { return this.lineas.filter((l) => l.preparada).length; },
    get completo() { return this.lineas.length && this.armadas === this.lineas.length; },
    async marcar(l, preparada, cantidad = null) {
        const antes = { preparada: l.preparada, cantidad_preparada: l.cantidad_preparada };
        l.preparada = preparada;
        l.cantidad_preparada = preparada ? (cantidad ?? l.cantidad_base) : null;
        this.guardando = l.id;
        try {
            const r = await api(url.replace('__LINEA__', l.id), { method: 'POST', body: { preparada, cantidad } });
            navigator.vibrate?.(30);
            // Si era el primero, el pedido pasó a "en preparación": se refleja en la pantalla.
            if (r.estado === 'preparando' && document.body.dataset.estadoPedido === 'nuevo') window.actualizarPantalla?.();
        } catch (e) {
            Object.assign(l, antes);
            avisar(e.message, 'error');
        } finally {
            this.guardando = null;
        }
    },
    /** "No alcanzó": se escribe cuánto se armó, en la misma línea. */
    editar(l) {
        l.editando = true;
        l.tmp = l.cantidad_preparada ?? l.cantidad_base;
        this.$nextTick(() => document.getElementById('parcial-' + l.id)?.select());
    },
    async guardarParcial(l) {
        const n = Number(String(l.tmp).replace(',', '.'));
        if (!(n >= 0) || n > l.cantidad_base) { avisar(`Debe ser un número entre 0 y ${fmt(l.cantidad_base)}.`, 'error'); return; }
        l.editando = false;
        await this.marcar(l, true, n);
    },
}));

/**
 * Armar un viaje: elegir camión, piloto y los pedidos listos (agrupados por municipio para
 * ver qué va de camino), y ordenar las paradas.
 */
Alpine.data('armarViaje', ({ pedidos, vehiculos, pilotos, preseleccion = [] }) => ({
    pedidos,
    vehiculos,
    pilotos,
    paradas: preseleccion.filter((id) => pedidos.some((p) => p.id === id)),
    vehiculo: vehiculos.filter((v) => !v.viaje).length === 1 ? vehiculos.find((v) => !v.viaje).id : null,
    piloto: pilotos.filter((p) => !p.viaje).length === 1 ? pilotos.find((p) => !p.viaje).id : null,

    get grupos() {
        const g = {};
        this.pedidos.forEach((p) => { (g[p.municipio] ||= []).push(p); });
        return Object.entries(g).sort((a, b) => b[1].length - a[1].length);
    },
    pedido(id) { return this.pedidos.find((p) => p.id === id); },
    elegido(id) { return this.paradas.includes(id); },
    alternar(id) {
        const i = this.paradas.indexOf(id);
        if (i >= 0) this.paradas.splice(i, 1); else this.paradas.push(id);
        navigator.vibrate?.(15);
    },
    todoElGrupo(lista) {
        const faltan = lista.filter((p) => !this.elegido(p.id));
        if (faltan.length) faltan.forEach((p) => this.paradas.push(p.id));
        else this.paradas = this.paradas.filter((id) => !lista.some((p) => p.id === id));
    },
    mover(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.paradas.length) return;
        [this.paradas[i], this.paradas[j]] = [this.paradas[j], this.paradas[i]];
    },
    get listo() { return this.vehiculo && this.piloto && this.paradas.length; },
    get resumen() {
        const v = this.vehiculos.find((x) => x.id === this.vehiculo);
        const p = this.pilotos.find((x) => x.id === this.piloto);
        return `${v?.codigo ?? ''} con ${p?.nombre ?? ''}:\n` + this.paradas.map((id, i) => `${i + 1}. ${this.pedido(id).cliente} (${this.pedido(id).municipio})`).join('\n');
    },
    get municipios() { return [...new Set(this.paradas.map((id) => this.pedido(id).municipio))].join(' → '); },
}));
