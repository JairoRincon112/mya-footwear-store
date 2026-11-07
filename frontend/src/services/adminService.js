// Endpoints del panel: usuarios (admin), mensajes de contacto y reportes.
import api from './api'

const limpiar = (filtros) => Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))

export default {
  // ---- Usuarios (solo admin) ----
  async usuarios(filtros = {}) {
    return (await api.get('/usuarios', { params: limpiar(filtros) })).data
  },

  async crearUsuario(datos) {
    return api.post('/usuarios', datos)
  },

  async actualizarUsuario(id, datos) {
    return api.put(`/usuarios/${id}`, datos)
  },

  async cambiarActivo(id, activo) {
    return api.patch(`/usuarios/${id}/activo`, { activo })
  },

  // ---- Mensajes de contacto ----
  async mensajes(filtros = {}) {
    return (await api.get('/contacto', { params: limpiar(filtros) })).data
  },

  async marcarLeido(id, leido) {
    return api.patch(`/contacto/${id}/leido`, { leido })
  },

  // ---- Cupones (solo admin) ----
  async cupones() {
    return (await api.get('/cupones')).data
  },

  async crearCupon(datos) {
    return api.post('/cupones', datos)
  },

  async actualizarCupon(id, datos) {
    return api.put(`/cupones/${id}`, datos)
  },

  async cambiarActivoCupon(id, activo) {
    return api.patch(`/cupones/${id}/activo`, { activo })
  },

  // ---- Reportes ----
  async resumen() {
    return (await api.get('/reportes/resumen')).data
  },
}
