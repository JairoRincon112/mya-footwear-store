<script setup>
// Detalle de un pedido del cliente; puede cancelarlo mientras esté pendiente.
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import BotonPagoWompi from '@/components/BotonPagoWompi.vue'
import DetallePedido from '@/components/DetallePedido.vue'
import pedidoService from '@/services/pedidoService'

const props = defineProps({
  id: { type: String, required: true },
})

const route = useRoute()
const pedido = ref(null)
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })

const esNuevo = computed(() => route.query.nuevo === '1')

const TEXTO_PAGO = { pendiente: 'en proceso', aprobado: 'aprobado', rechazado: 'rechazado', anulado: 'anulado', error: 'con error' }
const ICONO_PAGO = { pendiente: 'fa-hourglass-half', aprobado: 'fa-circle-check', rechazado: 'fa-circle-xmark', anulado: 'fa-ban', error: 'fa-triangle-exclamation' }

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    pedido.value = await pedidoService.obtener(props.id)
    if (esNuevo.value) {
      aviso.value = { tipo: 'exito', texto: `¡Gracias por tu compra! Tu pedido #${pedido.value.id} fue registrado. Págalo ahora con Wompi para despacharlo más rápido.` }
    }
  } catch (e) {
    error.value = e.estado === 404 ? 'Este pedido no existe.' : e.message
  } finally {
    cargando.value = false
  }
}

watch(() => props.id, cargar, { immediate: true })

async function cancelar() {
  if (!confirm('¿Cancelar este pedido?')) return
  try {
    const respuesta = await pedidoService.cancelarPropio(pedido.value.id)
    pedido.value = respuesta.data
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
  } catch (e) {
    aviso.value = { tipo: 'error', texto: e.errores?.estado ?? e.message }
  }
}
</script>

<template>
  <main class="mi-pedido">
    <RouterLink :to="{ name: 'mis-pedidos' }" class="volver">← Mis pedidos</RouterLink>

    <p v-if="cargando" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando pedido...</p>
    <p v-else-if="error" class="alerta alerta-error">{{ error }}</p>

    <template v-else-if="pedido">
      <p v-if="aviso.texto" class="alerta" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>
      <div class="tarjeta">
        <DetallePedido :pedido="pedido" />

        <!-- Estado del último intento de pago en línea -->
        <p v-if="pedido.pago" class="estado-pago" :class="`pago-${pedido.pago.estado}`">
          <i class="fa-solid" :class="ICONO_PAGO[pedido.pago.estado]"></i>
          Pago en línea: <strong>{{ TEXTO_PAGO[pedido.pago.estado] }}</strong>
          <span class="referencia">Ref. {{ pedido.pago.referencia }}</span>
        </p>

        <template v-if="pedido.estado === 'pendiente'">
          <BotonPagoWompi :pedido-id="pedido.id" :total="pedido.total" />
          <div class="acciones">
            <p class="texto-secundario">¿Prefieres pagar de otra forma? Un vendedor te contactará. Puedes cancelar mientras esté pendiente.</p>
            <button type="button" class="boton boton-secundario" @click="cancelar">Cancelar pedido</button>
          </div>
        </template>
      </div>
    </template>
  </main>
</template>

<style scoped>
.mi-pedido {
  display: grid;
  gap: 16px;
  max-width: 860px;
  min-height: 70vh;
  margin: 0 auto;
  padding: calc(var(--alto-header) + 30px) 16px 40px;
  align-content: start;
}

.volver {
  font-size: .9rem;
  color: var(--texto-secundario);
}

.tarjeta {
  display: grid;
  gap: 18px;
  padding: 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.acciones {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid #e6e6e6;
}

.estado-pago {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  font-size: .9rem;
  background: #f4f1f1;
  border-radius: 10px;
}

.pago-aprobado { color: #1b6e2c; background: #e7f4ea; }
.pago-pendiente { color: #8a6100; background: #fff6e0; }
.pago-rechazado,
.pago-anulado,
.pago-error { color: var(--error); background: #fdecea; }

.referencia {
  margin-left: auto;
  font-family: ui-monospace, Consolas, monospace;
  font-size: .78rem;
  opacity: .75;
}

.texto-secundario {
  font-size: .88rem;
  color: var(--texto-secundario);
}
</style>
