// Store de autenticación (Pinia): usuario actual, token JWT y rol.
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import authService from '@/services/authService'
import { establecerToken } from '@/services/api'
import { almacenamiento } from '@/utils/formato'

const CLAVE_TOKEN = 'mya_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(almacenamiento.leer(CLAVE_TOKEN))
  const usuario = ref(null)
  let inicializacion = null

  establecerToken(token.value)

  const autenticado = computed(() => usuario.value !== null)
  const rol = computed(() => usuario.value?.rol ?? null)
  const esAdmin = computed(() => rol.value === 'admin')
  const esPersonal = computed(() => rol.value === 'admin' || rol.value === 'vendedor')

  /** Página a la que va cada rol después de iniciar sesión. */
  const rutaInicio = computed(() => (esPersonal.value ? { name: 'panel' } : { name: 'inicio' }))

  /** Al abrir la app, si hay un token guardado se valida con la API (GET /auth/yo). */
  function inicializar() {
    inicializacion ??= (async () => {
      if (!token.value) return
      try {
        usuario.value = await authService.yo()
      } catch {
        cerrarSesion()
      }
    })()
    return inicializacion
  }

  function guardarSesion(sesion) {
    token.value = sesion.token
    usuario.value = sesion.usuario
    almacenamiento.guardar(CLAVE_TOKEN, sesion.token)
    establecerToken(sesion.token)
  }

  async function login(email, password, rolElegido = null) {
    guardarSesion(await authService.login(email, password, rolElegido))
    return usuario.value
  }

  async function registro(datos) {
    guardarSesion(await authService.registro(datos))
    return usuario.value
  }

  /** Refresca los datos del usuario después de editar su perfil. */
  function actualizarUsuario(datos) {
    usuario.value = datos
  }

  function cerrarSesion() {
    token.value = null
    usuario.value = null
    almacenamiento.borrar(CLAVE_TOKEN)
    establecerToken(null)
  }

  return { token, usuario, autenticado, rol, esAdmin, esPersonal, rutaInicio, inicializar, login, registro, actualizarUsuario, cerrarSesion }
})
