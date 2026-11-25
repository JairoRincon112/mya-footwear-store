// Endpoints de devoluciones.
import api from './api'

const limpiar = (filtros) => Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))

export default {
  // ---- Cliente ----
  /** datos: { detalle_id, tipo, cantidad, descripcion } */
  async solicitar(pedidoId, datos) {
    return api.post(`/pedidos/${pedidoId}/devoluciones`, datos)
  },

  // ---- Personal de la tienda ----
  async listar(filtros = {}) {
    return (await api.get('/devoluciones', { params: limpiar(filtros) })).data
  },

  async cambiarEstado(id, estado, respuesta = null) {
    return api.patch(`/devoluciones/${id}/estado`, { estado, respuesta: respuesta || null })
  },
}
