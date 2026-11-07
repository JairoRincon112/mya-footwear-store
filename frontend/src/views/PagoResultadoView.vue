<script setup>
// Resultado del pago con Wompi. Recibe ?id=<transacción> y le pide al servidor
// que la verifique con Wompi. Si sigue en proceso (PSE, Nequi...), vuelve a
// consultar cada pocos segundos.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import pagoService from '@/services/pagoService'

const route = useRoute()
const estado = ref('verificando') // verificando | aprobado | pendiente | rechazado | anulado | error
const mensaje = ref('')
const pedidoId = ref(Number(route.query.pedido) || null)
const metodo = ref('')

const MAX_INTENTOS = 10
const ESPERA_MS = 4000
let intentos = 0
let temporizador = null

const VISTAS = {
  verificando: { icono: 'fa-spinner fa-spin', clase: 'neutro', titulo: 'Verificando tu pago...' },
  aprobado: { icono: 'fa-check', clase: 'exito', titulo: '¡Pago aprobado!' },
  pendiente: { icono: 'fa-hourglass-half', clase: 'espera', titulo: 'Pago en proceso' },
  rechazado: { icono: 'fa-xmark', clase: 'fallo', titulo: 'Pago rechazado' },
  anulado: { icono: 'fa-ban', clase: 'fallo', titulo: 'Pago anulado' },
  error: { icono: 'fa-triangle-exclamation', clase: 'fallo', titulo: 'No pudimos confirmar el pago' },
}
const vista = computed(() => VISTAS[estado.value] ?? VISTAS.error)

const NOMBRE_METODO = { CARD: 'Tarjeta', PSE: 'PSE', NEQUI: 'Nequi', BANCOLOMBIA_TRANSFER: 'Bancolombia', BANCOLOMBIA_QR: 'QR Bancolombia' }

async function verificar() {
  const id = route.query.id
  if (typeof id !== 'string' || !id) {
    estado.value = 'error'
    mensaje.value = 'Falta el identificador de la transacción.'
    return
  }
  try {
    const respuesta = await pagoService.verificar(id)
    const datos = respuesta.data
    estado.value = datos.pago.estado
    mensaje.value = respuesta.mensaje
    pedidoId.value = datos.pedido_id
    metodo.value = NOMBRE_METODO[datos.pago.metodo] ?? datos.pago.metodo ?? ''
    if (estado.value === 'pendiente' && ++intentos < MAX_INTENTOS) {
      temporizador = setTimeout(verificar, ESPERA_MS)
    }
  } catch (e) {
    estado.value = 'error'
    mensaje.value = e.message
  }
}

onMounted(verificar)
onBeforeUnmount(() => clearTimeout(temporizador))
</script>

<template>
  <main class="pago-resultado">
    <section class="tarjeta" :class="vista.clase" role="status" aria-live="polite">
      <span class="icono"><i class="fa-solid" :class="vista.icono"></i></span>
      <h1>{{ vista.titulo }}</h1>
      <p v-if="mensaje" class="mensaje">{{ mensaje }}</p>
      <p v-if="pedidoId" class="dato">Pedido <strong>#{{ pedidoId }}</strong><template v-if="metodo"> · {{ metodo }}</template></p>
      <p v-if="estado === 'pendiente'" class="nota">Algunos medios (PSE, Nequi) tardan unos segundos en confirmar. Seguimos revisando...</p>

      <div class="acciones">
        <RouterLink v-if="pedidoId" :to="{ name: 'mi-pedido', params: { id: String(pedidoId) } }" class="boton-principal">
          {{ ['rechazado', 'anulado', 'error'].includes(estado) ? 'Intentar de nuevo' : 'Ver mi pedido' }}
        </RouterLink>
        <RouterLink to="/" class="boton-secundario">Seguir comprando</RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.pago-resultado {
  display: grid;
  place-items: center;
  min-height: 80vh;
  padding: calc(var(--alto-header) + 30px) 16px 50px;
  background: radial-gradient(110% 80% at 50% 0%, #3a1a05 0%, #161210 55%, #0d0b0a 100%);
}

.tarjeta {
  display: grid;
  justify-items: center;
  gap: 12px;
  width: min(480px, 100%);
  padding: 40px 28px;
  text-align: center;
  background: #fff;
  border-radius: 26px;
  box-shadow: 0 40px 80px -24px rgba(0, 0, 0, 0.8);
}

.icono {
  display: grid;
  place-items: center;
  width: 88px;
  height: 88px;
  margin-bottom: 6px;
  font-size: 2.3rem;
  color: #fff;
  background: #777;
  border-radius: 50%;
  animation: rebote 0.6s cubic-bezier(0.2, 1.4, 0.4, 1) both;
}

.exito .icono { background: #1f9d55; }
.espera .icono { background: #d48806; }
.fallo .icono { background: #c62828; }
.neutro .icono { color: #000; background: var(--naranja); animation: none; }

@keyframes rebote {
  from { transform: scale(0); }
}

h1 {
  font-size: 1.9rem;
  line-height: 1.1;
}

.mensaje {
  max-width: 360px;
  line-height: 1.6;
  color: #555;
}

.dato {
  font-size: 0.95rem;
}

.nota {
  font-size: 0.85rem;
  color: #8a6100;
}

.acciones {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
  margin-top: 10px;
}

.boton-principal,
.boton-secundario {
  padding: 13px 24px;
  font-weight: 700;
  border-radius: 999px;
}

.boton-principal {
  color: #000;
  background: var(--naranja);
}

.boton-secundario {
  color: #111;
  background: #eee;
}

@media (prefers-reduced-motion: reduce) {
  .icono { animation: none; }
}
</style>
