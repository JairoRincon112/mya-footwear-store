// Utilidades de formato y validación compartidas por las vistas.

const formatoPesos = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 })

/** 399950 -> "$399.950" (mismo formato que el catálogo original). */
export function formatearPrecio(valor) {
  return `$${formatoPesos.format(Number(valor) || 0)}`
}

const formatoFecha = new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' })

/** "2026-11-05 14:30:00" -> "5 nov 2026, 2:30 p. m." */
export function formatearFecha(valor) {
  if (!valor) return ''
  const fecha = new Date(String(valor).replace(' ', 'T'))
  return Number.isNaN(fecha.getTime()) ? valor : formatoFecha.format(fecha)
}

/** Nombre y estilo de cada estado de pedido. */
export const ESTADOS_PEDIDO = {
  pendiente: { texto: 'Pendiente', clase: 'estado-pendiente', icono: 'fa-clock', semaforo: 'sem-amarillo' },
  pagado: { texto: 'Pagado', clase: 'estado-pagado', icono: 'fa-credit-card', semaforo: 'sem-azul' },
  enviado: { texto: 'En tránsito', clase: 'estado-enviado', icono: 'fa-truck-fast', semaforo: 'sem-naranja' },
  entregado: { texto: 'Entregado', clase: 'estado-entregado', icono: 'fa-circle-check', semaforo: 'sem-verde' },
  cancelado: { texto: 'Cancelado', clase: 'estado-cancelado', icono: 'fa-ban', semaforo: 'sem-rojo' },
}

/** Estados de una devolución con su color de semáforo. */
export const ESTADOS_DEVOLUCION = {
  solicitada: { texto: 'Solicitada', clase: 'estado-pendiente', icono: 'fa-clock', semaforo: 'sem-amarillo' },
  aprobada: { texto: 'Aprobada', clase: 'estado-pagado', icono: 'fa-thumbs-up', semaforo: 'sem-azul' },
  recibida: { texto: 'Recibida', clase: 'estado-entregado', icono: 'fa-box-open', semaforo: 'sem-verde' },
  rechazada: { texto: 'Rechazada', clase: 'estado-cancelado', icono: 'fa-ban', semaforo: 'sem-rojo' },
}

/** Tipos de devolución que puede elegir el cliente. */
export const TIPOS_DEVOLUCION = {
  garantia: { texto: 'Garantía', icono: 'fa-shield-halved', ayuda: 'El producto presenta una falla de fabricación con el uso normal.' },
  talla_incorrecta: { texto: 'Talla incorrecta', icono: 'fa-ruler', ayuda: 'La talla no te quedó y quieres cambiarla.' },
  defectuoso: { texto: 'Llegó defectuoso', icono: 'fa-triangle-exclamation', ayuda: 'El producto llegó dañado, sucio o con una falla.' },
  producto_equivocado: { texto: 'Llegó otro producto', icono: 'fa-right-left', ayuda: 'Recibiste un modelo, color o talla diferente al que pediste.' },
  no_satisface: { texto: 'No me gustó', icono: 'fa-face-frown', ayuda: 'El producto no es lo que esperabas.' },
}

export const REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
export const REGEX_TELEFONO = /^[0-9+()\s-]{7,20}$/

/** Lee y escribe en localStorage sin romper la app si el navegador lo bloquea. */
export const almacenamiento = {
  leer(clave, porDefecto = null) {
    try {
      const valor = localStorage.getItem(clave)
      return valor === null ? porDefecto : JSON.parse(valor)
    } catch {
      return porDefecto
    }
  },
  guardar(clave, valor) {
    try {
      localStorage.setItem(clave, JSON.stringify(valor))
    } catch {
      /* almacenamiento no disponible: se ignora */
    }
  },
  borrar(clave) {
    try {
      localStorage.removeItem(clave)
    } catch {
      /* almacenamiento no disponible: se ignora */
    }
  },
}
