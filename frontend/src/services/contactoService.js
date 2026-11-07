// Endpoint del formulario de contacto: /api/contacto
import api from './api'

export default {
  async enviar(datos) {
    return api.post('/contacto', datos)
  },
}
