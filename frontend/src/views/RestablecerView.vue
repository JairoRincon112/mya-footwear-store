<script setup>
// Página del enlace que llega por correo: crea la nueva contraseña con el token.
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import authService from '@/services/authService'

const route = useRoute()
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const tokenConFormato = computed(() => /^[0-9a-f]{64}$/.test(token.value))

const claves = reactive({ nueva: '', confirmacion: '' })
const errores = ref({})
const errorGeneral = ref('')
const listo = ref('')
const enviando = ref(false)

async function guardar() {
  errorGeneral.value = ''
  const nuevos = {}
  if (claves.nueva.length < 8) nuevos.nueva = 'Debe tener al menos 8 caracteres.'
  if (claves.confirmacion !== claves.nueva) nuevos.confirmacion = 'Las contraseñas no coinciden.'
  errores.value = nuevos
  if (Object.keys(nuevos).length) return

  enviando.value = true
  try {
    const respuesta = await authService.restablecer(token.value, claves.nueva)
    listo.value = respuesta.mensaje
  } catch (e) {
    errorGeneral.value = e.errores?.token ?? e.errores?.password ?? e.message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <main class="pagina-auth">
    <div class="tarjeta">
      <h2>Crear nueva contraseña</h2>

      <template v-if="listo">
        <p class="alerta alerta-exito" role="status">{{ listo }}</p>
        <RouterLink :to="{ name: 'login' }" class="boton">Iniciar sesión</RouterLink>
      </template>

      <template v-else-if="!tokenConFormato">
        <p class="alerta alerta-error">El enlace no es válido. Revisa que lo hayas copiado completo o solicita uno nuevo.</p>
        <RouterLink :to="{ name: 'recuperar' }" class="boton">Solicitar un enlace nuevo</RouterLink>
      </template>

      <form v-else class="formulario" novalidate @submit.prevent="guardar">
        <label class="campo" :class="{ 'con-error': errores.nueva }">
          Nueva contraseña (mínimo 8 caracteres)
          <input v-model="claves.nueva" type="password" autocomplete="new-password" maxlength="72">
          <span v-if="errores.nueva" class="error-campo">{{ errores.nueva }}</span>
        </label>
        <label class="campo" :class="{ 'con-error': errores.confirmacion }">
          Confirmar contraseña
          <input v-model="claves.confirmacion" type="password" autocomplete="new-password" maxlength="72">
          <span v-if="errores.confirmacion" class="error-campo">{{ errores.confirmacion }}</span>
        </label>
        <button type="submit" class="boton" :disabled="enviando">{{ enviando ? 'Guardando...' : 'Guardar contraseña' }}</button>
        <p v-if="errorGeneral" class="alerta alerta-error" role="alert">
          {{ errorGeneral }}
          <RouterLink :to="{ name: 'recuperar' }">Solicitar otro enlace</RouterLink>
        </p>
      </form>
    </div>
  </main>
</template>

<style scoped>
.pagina-auth {
  min-height: 100vh;
  padding: calc(var(--alto-header) + 40px) 16px 48px;
}

.tarjeta {
  display: grid;
  gap: 16px;
  max-width: 420px;
  margin: 0 auto;
  padding: 28px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .12);
}

.tarjeta h2 {
  font-size: 1.3rem;
}

.alerta a {
  margin-left: 4px;
  font-weight: 600;
  color: inherit;
  text-decoration: underline;
}
</style>
