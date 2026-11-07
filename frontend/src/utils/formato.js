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
  pendiente: { texto: 'Pendiente', clase: 'estado-pendiente', icono: 'fa-clock' },
  pagado: { texto: 'Pagado', clase: 'estado-pagado', icono: 'fa-credit-card' },
  enviado: { texto: 'Enviado', clase: 'estado-enviado', icono: 'fa-truck' },
  entregado: { texto: 'Entregado', clase: 'estado-entregado', icono: 'fa-circle-check' },
  cancelado: { texto: 'Cancelado', clase: 'estado-cancelado', icono: 'fa-ban' },
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
