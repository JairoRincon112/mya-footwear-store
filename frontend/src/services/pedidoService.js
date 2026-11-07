// Endpoints de pedidos e inventario.
import api from './api'

const limpiar = (filtros) => Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))

export default {
  // ---- Cliente ----
  /** items: [{ variante_id, cantidad }] — el precio lo calcula el servidor. */
  async crear(envio, items, cupon = null) {
    return api.post('/pedidos', { ...envio, items, cupon })
  },

  /** Vista previa: precios actuales (con ofertas), stock y descuento del cupón. No guarda nada. */
  async cotizar(items, cupon = null) {
    return (await api.post('/pedidos/cotizar', { items, cupon })).data
  },

  async mios(pagina = 1) {
    return (await api.get('/pedidos/mios', { params: { pagina } })).data
  },

  async obtener(id) {
    return (await api.get(`/pedidos/${id}`)).data
  },

  async cancelarPropio(id) {
    return api.patch(`/pedidos/${id}/cancelar`)
  },

  // ---- Personal de la tienda ----
  /** filtros: { estado, q, desde, hasta, pagina } */
  async listar(filtros = {}) {
    return (await api.get('/pedidos', { params: limpiar(filtros) })).data
  },

  async cambiarEstado(id, estado) {
    return api.patch(`/pedidos/${id}/estado`, { estado })
  },

  /** filtros: { q, marca, bajo_stock, pagina } */
  async inventario(filtros = {}) {
    return (await api.get('/inventario', { params: limpiar(filtros) })).data
  },

  async movimientos(varianteId) {
    return (await api.get(`/variantes/${varianteId}/movimientos`)).data
  },
}
