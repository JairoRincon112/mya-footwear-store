<script setup>
// Página de contacto.
// Al enviar el formulario se hacen dos cosas:
//   1. Se guarda en la base de datos de la tienda (POST /api/contacto), para verlo en el panel.
//   2. Se envía al correo de la tienda con la API de Web3Forms (clave en frontend/.env).
// Diseño: tarjetas de contacto accionables, motivo en fichas, etiquetas flotantes,
// validación en vivo, contador de caracteres y preguntas frecuentes.
import { computed, nextTick, reactive, ref } from 'vue'
import contactoService from '@/services/contactoService'
import web3formsService from '@/services/web3formsService'
import { REGEX_EMAIL, REGEX_TELEFONO } from '@/utils/formato'

const CORREO = 'jr8488157@gmail.com'
const TELEFONO = '+57 3243884407'
const DIRECCION = 'Calle 24 #29-26'
const MAX_MENSAJE = 2000

const MOTIVOS = [
  { valor: 'Pedido', icono: 'fa-box' },
  { valor: 'Tallas', icono: 'fa-ruler' },
  { valor: 'Cambios', icono: 'fa-rotate' },
  { valor: 'Otro', icono: 'fa-comment-dots' },
]

const VACIO = { nombre: '', email: '', telefono: '', motivo: 'Pedido', mensaje: '' }
const formulario = reactive({ ...VACIO })
const botcheck = ref(false) // campo trampa (oculto) contra bots
const tocados = reactive({})
const erroresServidor = ref({})
const enviando = ref(false)
const enviado = ref(null) // { correo: bool } cuando el envío termina bien
const errorGeneral = ref('')
const copiado = ref(false)

/** Errores calculados en vivo; solo se muestran en campos ya tocados. */
const errores = computed(() => {
  const e = {}
  if (formulario.nombre.trim().length < 2) e.nombre = 'Escribe tu nombre y apellido.'
  if (!REGEX_EMAIL.test(formulario.email.trim())) e.email = 'Escribe un correo válido (ej: nombre@correo.com).'
  if (formulario.telefono.trim() && !REGEX_TELEFONO.test(formulario.telefono.trim())) e.telefono = 'Solo números, espacios, +, ( ) o -.'
  if (formulario.mensaje.trim().length < 10) e.mensaje = `Escribe al menos 10 caracteres (llevas ${formulario.mensaje.trim().length}).`
  return { ...e, ...erroresServidor.value }
})

const errorDe = (campo) => (tocados[campo] ? errores.value[campo] : '')
const valido = (campo) => tocados[campo] && !errores.value[campo] && formulario[campo].trim() !== ''

function tocar(campo) {
  tocados[campo] = true
  delete erroresServidor.value[campo]
}

async function enviar() {
  errorGeneral.value = ''
  for (const campo of ['nombre', 'email', 'telefono', 'mensaje']) tocados[campo] = true
  erroresServidor.value = {}
  if (Object.keys(errores.value).length) {
    enfocarPrimerError()
    return
  }

  const datos = {
    nombre: formulario.nombre.trim(),
    email: formulario.email.trim(),
    telefono: formulario.telefono.trim(),
    motivo: formulario.motivo,
    mensaje: formulario.mensaje.trim(),
  }

  enviando.value = true
  let guardadoEnBd = false
  try {
    // 1) Base de datos de la tienda (también valida en el servidor)
    try {
      await contactoService.enviar({ ...datos, mensaje: `[${datos.motivo}] ${datos.mensaje}` })
      guardadoEnBd = true
    } catch (e) {
      if (e.errores && Object.keys(e.errores).length) {
        erroresServidor.value = e.errores // datos inválidos: no se envía el correo
        errorGeneral.value = e.message
        return
      }
      // Si la API está caída se intenta igual el envío por correo
    }

    // 2) Correo con Web3Forms
    let correo = false
    if (web3formsService.configurado) {
      try {
        await web3formsService.enviar({ ...datos, botcheck: botcheck.value })
        correo = true
      } catch (e) {
        console.warn('Web3Forms:', e.message)
      }
    }

    if (!guardadoEnBd && !correo) {
      errorGeneral.value = 'No pudimos enviar tu mensaje. Revisa tu conexión e inténtalo de nuevo.'
      return
    }
    enviado.value = { correo, nombre: datos.nombre.split(/\s+/)[0] }
    Object.assign(formulario, VACIO)
    Object.keys(tocados).forEach((k) => delete tocados[k])
  } finally {
    enviando.value = false
  }
}

/** Lleva al cliente al primer campo con error y lo enfoca. */
function enfocarPrimerError() {
  const ids = { nombre: 'c-nombre', email: 'c-email', telefono: 'c-tel', mensaje: 'c-mensaje' }
  const primero = Object.keys(ids).find((campo) => errores.value[campo])
  if (primero) nextTick(() => document.getElementById(ids[primero])?.focus())
}

function otroMensaje() {
  enviado.value = null
}

async function copiarCorreo() {
  try {
    await navigator.clipboard.writeText(CORREO)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 1800)
  } catch {
    /* el navegador no permite copiar: no pasa nada */
  }
}
</script>

<template>
  <main class="contacto">
    <!-- Encabezado -->
    <section class="intro">
      <span class="eco" aria-hidden="true">HOLA</span>
      <p class="antetitulo"><i class="fa-solid fa-headset"></i> Contacto</p>
      <h1>Hablemos<span class="punto">.</span></h1>
      <p class="bajada">¿Dudas con una talla, un pedido o un cambio? Escríbenos y te respondemos a tu correo.</p>
    </section>

    <section class="cuerpo">
      <!-- Canales de contacto -->
      <aside class="canales">
        <a class="canal" :href="`https://wa.me/${TELEFONO.replace(/\D/g, '')}`" target="_blank" rel="noopener">
          <span class="canal-icono whatsapp"><i class="fa-brands fa-whatsapp"></i></span>
          <span class="canal-texto"><strong>WhatsApp</strong>{{ TELEFONO }}</span>
          <i class="fa-solid fa-arrow-right flecha"></i>
        </a>

        <div class="canal">
          <a :href="`mailto:${CORREO}`" class="canal-enlace">
            <span class="canal-icono"><i class="fa-solid fa-envelope"></i></span>
            <span class="canal-texto"><strong>Correo</strong>{{ CORREO }}</span>
          </a>
          <button type="button" class="copiar" :aria-label="copiado ? 'Correo copiado' : 'Copiar correo'" @click="copiarCorreo">
            <i :class="copiado ? 'fa-solid fa-check' : 'fa-regular fa-copy'"></i>
            <span class="copiar-texto">{{ copiado ? 'Copiado' : 'Copiar' }}</span>
          </button>
        </div>

        <a class="canal" :href="`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(DIRECCION)}`" target="_blank" rel="noopener">
          <span class="canal-icono"><i class="fa-solid fa-location-dot"></i></span>
          <span class="canal-texto"><strong>Visítanos</strong>{{ DIRECCION }}</span>
          <i class="fa-solid fa-arrow-right flecha"></i>
        </a>

        <a class="canal" :href="`tel:${TELEFONO.replace(/\s/g, '')}`">
          <span class="canal-icono"><i class="fa-solid fa-phone"></i></span>
          <span class="canal-texto"><strong>Llámanos</strong>{{ TELEFONO }}</span>
          <i class="fa-solid fa-arrow-right flecha"></i>
        </a>

        <div class="redes">
          <span>Síguenos</span>
          <a href="https://www.instagram.com/mya112102/" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="https://x.com/footwear36903/likes" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
          <a href="https://www.facebook.com/share/1AiU6GN67E/" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://www.linkedin.com" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        </div>
      </aside>

      <!-- Formulario -->
      <div class="tarjeta">
        <Transition name="cambio" mode="out-in">
          <!-- Estado: enviado -->
          <div v-if="enviado" key="ok" class="exito" role="status">
            <span class="check"><i class="fa-solid fa-check"></i></span>
            <h2>¡Gracias, {{ enviado.nombre }}!</h2>
            <p>
              Recibimos tu mensaje<template v-if="enviado.correo"> y ya llegó al correo de la tienda</template>.
              Te responderemos al correo que nos dejaste.
            </p>
            <button type="button" class="enviar" @click="otroMensaje">Enviar otro mensaje</button>
          </div>

          <!-- Estado: formulario -->
          <form v-else key="form" novalidate @submit.prevent="enviar">
            <h2>Envíanos un mensaje</h2>

            <fieldset class="motivos">
              <legend>¿Sobre qué nos escribes?</legend>
              <label v-for="m in MOTIVOS" :key="m.valor" class="motivo" :class="{ activo: formulario.motivo === m.valor }">
                <input v-model="formulario.motivo" type="radio" name="motivo" :value="m.valor">
                <i class="fa-solid" :class="m.icono"></i> {{ m.valor }}
              </label>
            </fieldset>

            <div class="fila">
              <div class="campo" :class="{ error: errorDe('nombre'), ok: valido('nombre') }">
                <input id="c-nombre" v-model="formulario.nombre" type="text" placeholder=" " maxlength="120" autocomplete="name" :aria-invalid="!!errorDe('nombre')" aria-describedby="e-nombre" @blur="tocar('nombre')">
                <label for="c-nombre">Nombre y apellido</label>
                <i class="fa-solid fa-user icono"></i>
                <p id="e-nombre" class="mensaje-error">{{ errorDe('nombre') }}</p>
              </div>

              <div class="campo" :class="{ error: errorDe('email'), ok: valido('email') }">
                <input id="c-email" v-model="formulario.email" type="email" placeholder=" " maxlength="150" autocomplete="email" :aria-invalid="!!errorDe('email')" aria-describedby="e-email" @blur="tocar('email')">
                <label for="c-email">Correo electrónico</label>
                <i class="fa-solid fa-envelope icono"></i>
                <p id="e-email" class="mensaje-error">{{ errorDe('email') }}</p>
              </div>
            </div>

            <div class="campo" :class="{ error: errorDe('telefono'), ok: valido('telefono') }">
              <input id="c-tel" v-model="formulario.telefono" type="tel" placeholder=" " maxlength="20" autocomplete="tel" :aria-invalid="!!errorDe('telefono')" aria-describedby="e-tel" @blur="tocar('telefono')">
              <label for="c-tel">Teléfono (opcional)</label>
              <i class="fa-solid fa-phone icono"></i>
              <p id="e-tel" class="mensaje-error">{{ errorDe('telefono') }}</p>
            </div>

            <div class="campo area" :class="{ error: errorDe('mensaje'), ok: valido('mensaje') }">
              <textarea id="c-mensaje" v-model="formulario.mensaje" placeholder=" " :maxlength="MAX_MENSAJE" :aria-invalid="!!errorDe('mensaje')" aria-describedby="e-mensaje" @blur="tocar('mensaje')"></textarea>
              <label for="c-mensaje">Tu mensaje</label>
              <span class="contador" :class="{ casi: formulario.mensaje.length > MAX_MENSAJE * 0.9 }">{{ formulario.mensaje.length }}/{{ MAX_MENSAJE }}</span>
              <p id="e-mensaje" class="mensaje-error">{{ errorDe('mensaje') }}</p>
            </div>

            <!-- Campo trampa: invisible para personas, los bots lo marcan -->
            <input v-model="botcheck" type="checkbox" class="trampa" tabindex="-1" autocomplete="off" aria-hidden="true">

            <p v-if="errorGeneral" class="aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ errorGeneral }}</p>

            <button type="submit" class="enviar" :disabled="enviando">
              <template v-if="enviando"><i class="fa-solid fa-spinner fa-spin"></i> Enviando...</template>
              <template v-else>Enviar mensaje <i class="fa-solid fa-paper-plane"></i></template>
            </button>
            <p class="nota"><i class="fa-solid fa-lock"></i> Usamos tus datos solo para responderte.</p>
          </form>
        </Transition>
      </div>
    </section>

    <!-- Preguntas frecuentes: respuestas que la tienda ya resuelve sola -->
    <section class="faq">
      <h2>Antes de escribir, quizá esto te ayude<span class="punto">.</span></h2>
      <details>
        <summary>¿Cómo veo el estado de mi pedido?</summary>
        <p>Inicia sesión y entra a <RouterLink :to="{ name: 'mis-pedidos' }">Mis pedidos</RouterLink>: ahí ves cada pedido con su estado (pendiente, pagado, enviado o entregado).</p>
      </details>
      <details>
        <summary>Olvidé mi contraseña, ¿qué hago?</summary>
        <p>En <RouterLink :to="{ name: 'recuperar' }">Recuperar contraseña</RouterLink> escribe tu correo y te enviamos un enlace para crear una nueva (vale por 60 minutos).</p>
      </details>
      <details>
        <summary>¿Cómo uso un cupón de descuento?</summary>
        <p>Agrega tus productos al carrito y en <strong>Finalizar compra</strong> escribe el código del cupón: verás el descuento antes de confirmar.</p>
      </details>
      <details>
        <summary>¿Cómo sé si hay mi talla?</summary>
        <p>En la página de cada producto elige color y talla: te mostramos cuántas unidades quedan. En el catálogo también puedes buscar "negro 42" para filtrar por color y talla.</p>
      </details>
    </section>
  </main>
</template>

<style scoped>
.contacto {
  padding-top: var(--alto-header);
  color: #fff;
  background: radial-gradient(120% 70% at 85% 0%, #3a1a05 0%, #161210 50%, #0d0b0a 100%);
}

.punto {
  color: var(--naranja);
}

/* ---------- Encabezado ---------- */
.intro {
  position: relative;
  max-width: 1100px;
  margin: 0 auto;
  padding: 56px 20px 36px;
  overflow: hidden;
}

.eco {
  position: absolute;
  right: 0;
  top: 10px;
  font-size: clamp(6rem, 18vw, 14rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.04em;
  color: transparent;
  -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.07);
  pointer-events: none;
  user-select: none;
}

.antetitulo {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--naranja);
  background: rgba(255, 102, 0, 0.08);
  border: 1px solid rgba(255, 102, 0, 0.45);
  border-radius: 999px;
}

.intro h1 {
  font-size: clamp(2.8rem, 7vw, 5rem);
  line-height: 1;
  letter-spacing: -0.03em;
  color: #fff;
}

.bajada {
  max-width: 520px;
  margin-top: 16px;
  font-size: 1.05rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.7);
}

/* ---------- Cuerpo: canales + formulario ---------- */
.cuerpo {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
  gap: 28px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 20px 56px;
}

.canales {
  display: grid;
  align-content: start;
  gap: 12px;
}

.canal {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 18px;
  transition: background-color 0.2s, border-color 0.2s, transform 0.2s;
}

a.canal:hover,
.canal:has(.canal-enlace:hover) {
  background: rgba(255, 102, 0, 0.1);
  border-color: rgba(255, 102, 0, 0.5);
  transform: translateX(4px);
}

.canal-enlace {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 14px;
  min-width: 0;
  color: #fff;
}

.canal-icono {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 46px;
  height: 46px;
  font-size: 1.15rem;
  color: #000;
  background: var(--naranja);
  border-radius: 14px;
}

.canal-icono.whatsapp {
  font-size: 1.4rem;
  color: #fff;
  background: #1f9d55;
}

.canal-texto {
  display: grid;
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
  font-size: 0.92rem;
  color: rgba(255, 255, 255, 0.7);
}

.canal-texto strong {
  font-size: 1rem;
  color: #fff;
}

.flecha {
  color: rgba(255, 255, 255, 0.35);
  transition: color 0.2s, transform 0.2s;
}

a.canal:hover .flecha {
  color: var(--naranja);
  transform: translateX(3px);
}

.copiar {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 999px;
  cursor: pointer;
}

.copiar:hover {
  color: #000;
  background: #fff;
}

.redes {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  padding-left: 4px;
}

.redes span {
  margin-right: 4px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
}

.redes a {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 50%;
  transition: background-color 0.2s, color 0.2s, transform 0.2s;
}

.redes a:hover {
  color: #000;
  background: var(--naranja);
  transform: translateY(-3px);
}

/* ---------- Tarjeta del formulario ---------- */
.tarjeta {
  padding: 28px;
  color: #111;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.7);
}

.tarjeta h2 {
  margin-bottom: 18px;
  font-size: 1.6rem;
  color: #111;
}

form {
  display: grid;
  gap: 14px;
}

.motivos {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  border: 0;
}

.motivos legend {
  margin-bottom: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #555;
}

.motivo {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  font-size: 0.88rem;
  background: #f4f1f1;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
  transition: background-color 0.15s, border-color 0.15s;
}

.motivo:hover {
  border-color: #ccc;
}

.motivo input {
  position: absolute;
  opacity: 0;
}

.motivo.activo {
  font-weight: 600;
  color: #fff;
  background: #111;
}

.motivo.activo i {
  color: var(--naranja);
}

.motivo:focus-within {
  outline: 2px solid var(--naranja);
  outline-offset: 2px;
}

.fila {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

/* Campo con etiqueta flotante: la etiqueta sube al escribir o enfocar */
.campo {
  position: relative;
}

.campo input,
.campo textarea {
  width: 100%;
  padding: 22px 40px 8px 14px;
  font-size: 0.98rem;
  background: #f7f5f5;
  border: 1.5px solid #e2dede;
  border-radius: 14px;
  outline: none;
  transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
}

.campo textarea {
  min-height: 140px;
  padding-right: 14px;
  padding-bottom: 26px;
  resize: vertical;
}

.campo label {
  position: absolute;
  left: 15px;
  top: 16px;
  font-size: 0.98rem;
  color: #777;
  pointer-events: none;
  transition: top 0.18s, font-size 0.18s, color 0.18s;
}

.campo input:focus,
.campo textarea:focus {
  background: #fff;
  border-color: var(--naranja);
  box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.15);
}

.campo input:focus + label,
.campo input:not(:placeholder-shown) + label,
.campo textarea:focus + label,
.campo textarea:not(:placeholder-shown) + label {
  top: 6px;
  font-size: 0.74rem;
  font-weight: 600;
  color: #c24e00;
}

.campo .icono {
  position: absolute;
  right: 15px;
  top: 19px;
  color: #b5b0b0;
  transition: color 0.2s;
}

.campo input:focus ~ .icono {
  color: var(--naranja);
}

.campo.ok input,
.campo.ok textarea {
  border-color: #3c9a5f;
}

.campo.ok .icono {
  color: #3c9a5f;
}

.campo.error input,
.campo.error textarea {
  border-color: var(--error);
  background: #fff8f8;
}

.campo.error .icono {
  color: var(--error);
}

.mensaje-error {
  min-height: 0;
  margin-top: 4px;
  font-size: 0.8rem;
  color: var(--error);
}

.mensaje-error:empty {
  display: none;
}

.contador {
  position: absolute;
  right: 14px;
  bottom: 10px;
  font-size: 0.75rem;
  color: #888;
  font-variant-numeric: tabular-nums;
}

.campo.error .contador,
.campo:has(.mensaje-error:not(:empty)) .contador {
  bottom: 32px;
}

.contador.casi {
  color: #c24e00;
}

.trampa {
  position: absolute;
  left: -9999px;
}

.aviso-error {
  padding: 10px 14px;
  font-size: 0.9rem;
  color: var(--error);
  background: #fdecea;
  border-radius: 12px;
}

.enviar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 15px;
  font-size: 1rem;
  font-weight: 700;
  color: #000;
  background: var(--naranja);
  border: 0;
  border-radius: 999px;
  box-shadow: 0 12px 26px -10px rgba(255, 102, 0, 0.8);
  cursor: pointer;
  transition: transform 0.2s, background-color 0.2s;
}

.enviar:hover:not(:disabled) {
  background: #ff8533;
  transform: translateY(-2px);
}

.enviar:hover:not(:disabled) .fa-paper-plane {
  transform: translate(3px, -3px);
}

.enviar i {
  transition: transform 0.2s;
}

.enviar:disabled {
  opacity: 0.7;
  cursor: wait;
}

.nota {
  font-size: 0.8rem;
  text-align: center;
  color: #777;
}

/* Estado enviado */
.exito {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 40px 10px;
  text-align: center;
}

.exito p {
  max-width: 380px;
  line-height: 1.6;
  color: #555;
}

.exito .enviar {
  width: auto;
  margin-top: 8px;
  padding: 13px 26px;
}

.check {
  display: grid;
  place-items: center;
  width: 84px;
  height: 84px;
  font-size: 2.2rem;
  color: #fff;
  background: #1f9d55;
  border-radius: 50%;
  animation: rebote 0.6s cubic-bezier(0.2, 1.4, 0.4, 1) both;
}

@keyframes rebote {
  from { transform: scale(0); }
}

.cambio-enter-active,
.cambio-leave-active {
  transition: opacity 0.25s, transform 0.25s;
}

.cambio-enter-from,
.cambio-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

/* ---------- Preguntas frecuentes ---------- */
.faq {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 20px 70px;
}

.faq h2 {
  margin-bottom: 18px;
  font-size: clamp(1.5rem, 3vw, 2rem);
  color: #fff;
}

details {
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 4px;
  font-weight: 600;
  list-style: none;
  cursor: pointer;
}

summary::-webkit-details-marker {
  display: none;
}

summary::after {
  content: "+";
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 30px;
  height: 30px;
  font-size: 1.2rem;
  color: var(--naranja);
  border: 1px solid rgba(255, 102, 0, 0.5);
  border-radius: 50%;
  transition: transform 0.25s;
}

details[open] summary::after {
  transform: rotate(45deg);
}

summary:hover {
  color: var(--naranja);
}

details p {
  padding: 0 4px 18px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.7);
}

details a {
  font-weight: 600;
  color: var(--naranja);
  text-decoration: underline;
}

/* ---------- Celulares y tabletas ---------- */
@media (max-width: 860px) {
  .cuerpo {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }

  .canales {
    order: 2;
  }
}

@media (max-width: 520px) {
  .fila {
    grid-template-columns: minmax(0, 1fr); /* minmax: el contenido ancho (tablas) no estira la página */
  }

  .tarjeta {
    padding: 22px 18px;
  }

  .copiar-texto {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .contacto * {
    animation: none !important;
    transition: none !important;
  }
}
</style>
