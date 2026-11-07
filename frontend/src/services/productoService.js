// Endpoints del catálogo, productos, imágenes y variantes.
import api from './api'

export default {
  // ---- Catálogo público ----
  async marcas() {
    return (await api.get('/marcas')).data
  },

  async tallas() {
    return (await api.get('/tallas')).data
  },

  async colores() {
    return (await api.get('/colores')).data
  },

  /** filtros: { marca, q, talla, color, orden, pagina, por_pagina, estado } */
  async listar(filtros = {}) {
    const params = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor !== '' && valor != null))
    return (await api.get('/productos', { params })).data
  },

  /** Acepta el slug o el id del producto. */
  async obtener(idOSlug) {
    return (await api.get(`/productos/${encodeURIComponent(idOSlug)}`)).data
  },

  // ---- Administración (admin) ----
  async crear(datos) {
    return api.post('/productos', datos)
  },

  async actualizar(id, datos) {
    return api.put(`/productos/${id}`, datos)
  },

  async desactivar(id) {
    return api.delete(`/productos/${id}`)
  },

  async subirImagen(productoId, archivo, colorId = null) {
    const formulario = new FormData()
    formulario.append('imagen', archivo)
    if (colorId) formulario.append('color_id', colorId)
    return api.post(`/productos/${productoId}/imagenes`, formulario)
  },

  async eliminarImagen(imagenId) {
    return api.delete(`/imagenes/${imagenId}`)
  },

  // ---- Variantes e inventario ----
  async crearVariantes(productoId, { color_id, talla_ids, stock }) {
    return api.post(`/productos/${productoId}/variantes`, { color_id, talla_ids, stock })
  },

  async actualizarVariante(id, { sku, activo }) {
    return api.put(`/variantes/${id}`, { sku, activo })
  },

  async ajustarStock(id, { tipo, cantidad, motivo }) {
    return api.patch(`/variantes/${id}/stock`, { tipo, cantidad, motivo })
  },
}
