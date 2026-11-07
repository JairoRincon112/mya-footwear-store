<script setup>
// Mi cuenta: el usuario edita sus datos y cambia su contraseña.
import { reactive, ref } from 'vue'
import authService from '@/services/authService'
import { useAuthStore } from '@/stores/auth'
import { REGEX_EMAIL, REGEX_TELEFONO } from '@/utils/formato'

const auth = useAuthStore()

const perfil = reactive({
  nombre: auth.usuario?.nombre ?? '',
  email: auth.usuario?.email ?? '',
  telefono: auth.usuario?.telefono ?? '',
})
const erroresPerfil = ref({})
const avisoPerfil = ref({ tipo: '', texto: '' })
const guardandoPerfil = ref(false)

const claves = reactive({ actual: '', nueva: '', confirmacion: '' })
const erroresClave = ref({})
const avisoClave = ref({ tipo: '', texto: '' })
const guardandoClave = ref(false)

async function guardarPerfil() {
  avisoPerfil.value = { tipo: '', texto: '' }
  const errores = {}
  if (perfil.nombre.trim().length < 2) errores.nombre = 'Escribe tu nombre.'
  if (!REGEX_EMAIL.test(perfil.email.trim())) errores.email = 'Escribe un correo válido.'
  if (perfil.telefono.trim() && !REGEX_TELEFONO.test(perfil.telefono.trim())) errores.telefono = 'Teléfono no válido.'
  erroresPerfil.value = errores
  if (Object.keys(errores).length) return

  guardandoPerfil.value = true
  try {
    const respuesta = await authService.actualizarPerfil({
      nombre: perfil.nombre.trim(),
      email: perfil.email.trim(),
      telefono: perfil.telefono.trim() || null,
    })
    auth.actualizarUsuario(respuesta.data)
    avisoPerfil.value = { tipo: 'exito', texto: respuesta.mensaje }
  } catch (e) {
    erroresPerfil.value = e.errores
    avisoPerfil.value = { tipo: 'error', texto: e.message }
  } finally {
    guardandoPerfil.value = false
  }
}

async function cambiarClave() {
  avisoClave.value = { tipo: '', texto: '' }
  const errores = {}
  if (!claves.actual) errores.password_actual = 'Escribe tu contraseña actual.'
  if (claves.nueva.length < 8) errores.password_nueva = 'La nueva contraseña debe tener al menos 8 caracteres.'
  if (claves.confirmacion !== claves.nueva) errores.confirmacion = 'Las contraseñas no coinciden.'
  erroresClave.value = errores
  if (Object.keys(errores).length) return

  guardandoClave.value = true
  try {
    const respuesta = await authService.cambiarPassword(claves.actual, claves.nueva)
    Object.assign(claves, { actual: '', nueva: '', confirmacion: '' })
    avisoClave.value = { tipo: 'exito', texto: respuesta.mensaje }
  } catch (e) {
    erroresClave.value = e.errores
    avisoClave.value = { tipo: 'error', texto: e.message }
  } finally {
    guardandoClave.value = false
  }
}
</script>

<template>
  <main class="mi-cuenta">
    <h2 class="titulo_descuento">MI CUENTA</h2>

    <div class="columnas">
      <form class="tarjeta formulario" novalidate @submit.prevent="guardarPerfil">
        <h3>Mis datos</h3>
        <label class="campo" :class="{ 'con-error': erroresPerfil.nombre }">
          Nombre
          <input v-model="perfil.nombre" type="text" autocomplete="name" maxlength="120">
          <span v-if="erroresPerfil.nombre" class="error-campo">{{ erroresPerfil.nombre }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': erroresPerfil.email }">
          Correo electrónico
          <input v-model="perfil.email" type="email" autocomplete="email" maxlength="150">
          <span v-if="erroresPerfil.email" class="error-campo">{{ erroresPerfil.email }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': erroresPerfil.telefono }">
          Teléfono (opcional)
          <input v-model="perfil.telefono" type="tel" autocomplete="tel" maxlength="20">
          <span v-if="erroresPerfil.telefono" class="error-campo">{{ erroresPerfil.telefono }}</span>
        </label>
        <button type="submit" class="boton" :disabled="guardandoPerfil">{{ guardandoPerfil ? 'Guardando...' : 'Guardar cambios' }}</button>
        <p v-if="avisoPerfil.texto" class="alerta" :class="`alerta-${avisoPerfil.tipo}`" role="status">{{ avisoPerfil.texto }}</p>
      </form>

      <form class="tarjeta formulario" novalidate @submit.prevent="cambiarClave">
        <h3>Cambiar contraseña</h3>
        <input type="text" autocomplete="username" :value="auth.usuario?.email" hidden>
        <label class="campo" :class="{ 'con-error': erroresClave.password_actual }">
          Contraseña actual
          <input v-model="claves.actual" type="password" autocomplete="current-password" maxlength="72">
          <span v-if="erroresClave.password_actual" class="error-campo">{{ erroresClave.password_actual }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': erroresClave.password_nueva }">
          Nueva contraseña (mínimo 8 caracteres)
          <input v-model="claves.nueva" type="password" autocomplete="new-password" maxlength="72">
          <span v-if="erroresClave.password_nueva" class="error-campo">{{ erroresClave.password_nueva }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': erroresClave.confirmacion }">
          Confirmar nueva contraseña
          <input v-model="claves.confirmacion" type="password" autocomplete="new-password" maxlength="72">
          <span v-if="erroresClave.confirmacion" class="error-campo">{{ erroresClave.confirmacion }}</span>
        </label>
        <button type="submit" class="boton" :disabled="guardandoClave">{{ guardandoClave ? 'Cambiando...' : 'Cambiar contraseña' }}</button>
        <p v-if="avisoClave.texto" class="alerta" :class="`alerta-${avisoClave.tipo}`" role="status">{{ avisoClave.texto }}</p>
      </form>
    </div>

    <p v-if="auth.rol === 'cliente'" class="enlace-pedidos">
      <RouterLink :to="{ name: 'mis-pedidos' }"><i class="fa-solid fa-receipt"></i> Ver mis pedidos</RouterLink>
    </p>
  </main>
</template>

<style scoped>
.mi-cuenta {
  max-width: 1000px;
  min-height: 70vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 30px) 16px 40px;
}

.columnas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  align-items: start;
}

.tarjeta {
  padding: 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.tarjeta h3 {
  font-size: 1.1rem;
}

.enlace-pedidos {
  margin-top: 20px;
  text-align: center;
}

.enlace-pedidos a {
  font-weight: 600;
  color: #c24e00;
}
</style>
