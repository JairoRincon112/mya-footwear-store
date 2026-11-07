// Endpoints de autenticación: /api/auth/*
import api from './api'

export default {
  /** rol (opcional): 'admin' | 'vendedor' | 'cliente'; la API rechaza si la cuenta no lo tiene. */
  async login(email, password, rol = null) {
    return (await api.post('/auth/login', rol ? { email, password, rol } : { email, password })).data
  },

  async registro(datos) {
    return (await api.post('/auth/registro', datos)).data
  },

  async yo() {
    return (await api.get('/auth/yo')).data
  },

  /** datos: { nombre, email, telefono } */
  async actualizarPerfil(datos) {
    return api.put('/auth/perfil', datos)
  },

  async cambiarPassword(passwordActual, passwordNueva) {
    return api.put('/auth/password', { password_actual: passwordActual, password_nueva: passwordNueva })
  },

  async solicitarRecuperacion(email) {
    return api.post('/auth/recuperar', { email })
  },

  async restablecer(token, password) {
    return api.post('/auth/restablecer', { token, password })
  },
}
