<script setup>
// "¿Olvidaste tu contraseña?": pide el correo y la API envía un enlace que caduca.
import { ref } from 'vue'
import authService from '@/services/authService'
import { REGEX_EMAIL } from '@/utils/formato'

const email = ref('')
const error = ref('')
const enviado = ref('')
const enviando = ref(false)

async function enviar() {
  error.value = ''
  if (!REGEX_EMAIL.test(email.value.trim())) {
    error.value = 'Escribe un correo electrónico válido.'
    return
  }
  enviando.value = true
  try {
    const respuesta = await authService.solicitarRecuperacion(email.value.trim())
    enviado.value = respuesta.mensaje
  } catch (e) {
    error.value = e.errores?.email ?? e.message
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <main class="pagina-auth">
    <div class="tarjeta">
      <h2>Recuperar contraseña</h2>

      <template v-if="enviado">
        <p class="alerta alerta-exito" role="status">{{ enviado }}</p>
        <p class="texto-secundario">El enlace vence en 60 minutos y solo funciona una vez.</p>
      </template>

      <form v-else class="formulario" novalidate @submit.prevent="enviar">
        <p class="texto-secundario">Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.</p>
        <label class="campo" :class="{ 'con-error': error }">
          Correo electrónico
          <input v-model="email" type="email" autocomplete="email" maxlength="150">
          <span v-if="error" class="error-campo">{{ error }}</span>
        </label>
        <button type="submit" class="boton" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Enviar enlace' }}</button>
      </form>

      <RouterLink :to="{ name: 'login' }" class="volver">← Volver a iniciar sesión</RouterLink>
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

.texto-secundario {
  font-size: .9rem;
  color: var(--texto-secundario);
}

.volver {
  font-size: .9rem;
  color: #c24e00;
}
</style>
