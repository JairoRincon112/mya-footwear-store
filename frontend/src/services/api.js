// Cliente HTTP (Axios) para consumir la API PHP.
// Agrega el token JWT a cada petición y convierte los errores en mensajes legibles.
import axios from 'axios'

export const API_URL = import.meta.env.VITE_API_URL.replace(/\/+$/, '')

const api = axios.create({
  baseURL: `${API_URL}/api`,
  headers: { Accept: 'application/json' },
  timeout: 20000,
})

let tokenActual = null
let alExpirarSesion = null

/** El store de autenticación informa aquí el token vigente. */
export function establecerToken(token) {
  tokenActual = token || null
}

/** Qué hacer cuando la API responde 401 con una sesión abierta (token vencido o cuenta desactivada). */
export function alExpirar(callback) {
  alExpirarSesion = callback
}

/** Error de la API con su código HTTP y los errores por campo (validación 422). */
export class ApiError extends Error {
  constructor(mensaje, estado = 0, errores = {}) {
    super(mensaje)
    this.estado = estado
    this.errores = errores
  }
}

api.interceptors.request.use((config) => {
  if (tokenActual) {
    config.headers.Authorization = `Bearer ${tokenActual}`
  }
  return config
})

api.interceptors.response.use(
  // Todas las respuestas exitosas tienen la forma { ok, data, mensaje }.
  (respuesta) => respuesta.data,
  (error) => {
    if (!error.response) {
      return Promise.reject(
        new ApiError('No se pudo conectar con el servidor. Verifica que Apache y MySQL estén encendidos en XAMPP.'),
      )
    }

    const { status, data } = error.response
    if (status === 401 && tokenActual && alExpirarSesion) {
      alExpirarSesion()
    }

    const mensaje = data?.mensaje || 'Ocurrió un error inesperado. Intenta de nuevo.'
    return Promise.reject(new ApiError(mensaje, status, data?.errores || {}))
  },
)

/** URL completa de una imagen guardada en el backend (ej. "img/productos/superstar.jpg"). */
export function urlImagen(ruta) {
  if (!ruta) return ''
  if (/^https?:\/\//.test(ruta)) return ruta
  return `${API_URL}/${ruta.split('/').map(encodeURIComponent).join('/')}`
}

export default api
