<script setup>
// Detalle de un pedido en el panel: el personal lo avanza de estado o lo cancela.
import { ref, watch } from 'vue'
import DetallePedido from '@/components/DetallePedido.vue'
import pedidoService from '@/services/pedidoService'
import { ESTADOS_PEDIDO } from '@/utils/formato'

const props = defineProps({
  id: { type: String, required: true },
})

const pedido = ref(null)
const cargando = ref(true)
const error = ref('')
const aviso = ref({ tipo: '', texto: '' })
const guardando = ref(false)

const ACCIONES = {
  pagado: 'Confirmar pago',
  enviado: 'Marcar como enviado',
  entregado: 'Marcar como entregado',
  cancelado: 'Cancelar pedido',
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    pedido.value = await pedidoService.obtener(props.id)
  } catch (e) {
    error.value = e.estado === 404 ? 'El pedido no existe.' : e.message
  } finally {
    cargando.value = false
  }
}

watch(() => props.id, cargar, { immediate: true })

async function cambiarEstado(estado) {
  if (estado === 'cancelado' && !confirm('¿Cancelar el pedido? El stock volverá al inventario.')) return
  guardando.value = true
  try {
    const respuesta = await pedidoService.cambiarEstado(pedido.value.id, estado)
    pedido.value = respuesta.data
    aviso.value = { tipo: 'exito', texto: respuesta.mensaje }
  } catch (e) {
    aviso.value = { tipo: 'error', texto: e.errores?.estado ?? e.message }
  } finally {
    guardando.value = false
  }
}
</script>

<template>
  <section>
    <div class="panel-encabezado">
      <div>
        <RouterLink :to="{ name: 'panel-pedidos' }" class="texto-secundario">← Volver a pedidos</RouterLink>
        <h2>Pedido #{{ id }}</h2>
      </div>
    </div>

    <p v-if="cargando" class="cargando"><i class="fa-solid fa-spinner fa-spin"></i>Cargando pedido...</p>
    <p v-else-if="error" class="alerta alerta-error">{{ error }}</p>

    <template v-else-if="pedido">
      <p v-if="aviso.texto" class="alerta aviso" :class="`alerta-${aviso.tipo}`" role="status">{{ aviso.texto }}</p>

      <div class="panel-tarjeta">
        <h3>Gestionar</h3>
        <div v-if="pedido.estados_siguientes.length" class="acciones">
          <button
            v-for="estado in pedido.estados_siguientes"
            :key="estado"
            type="button"
            class="boton"
            :class="{ 'boton-secundario': estado === 'cancelado' }"
            :disabled="guardando"
            @click="cambiarEstado(estado)"
          >
            <i class="fa-solid" :class="ESTADOS_PEDIDO[estado].icono"></i> {{ ACCIONES[estado] }}
          </button>
        </div>
        <p v-else class="texto-secundario">Este pedido está {{ ESTADOS_PEDIDO[pedido.estado].texto.toLowerCase() }} y ya no cambia de estado.</p>
        <p class="texto-secundario flujo">Flujo: Pendiente → Pagado → Enviado → Entregado. Solo se cancela antes de enviarse.</p>
      </div>

      <div class="panel-tarjeta">
        <DetallePedido :pedido="pedido" mostrar-cliente />
        <!-- Pago en línea con Wompi (si el cliente lo intentó) -->
        <p v-if="pedido.pago" class="pago-linea" :class="`pago-${pedido.pago.estado}`">
          <i class="fa-solid fa-credit-card"></i>
          Wompi: <strong>{{ pedido.pago.estado }}</strong>
          <template v-if="pedido.pago.metodo"> · {{ pedido.pago.metodo }}</template>
          · Ref. {{ pedido.pago.referencia }}
          <template v-if="pedido.pago.transaccion_id"> · Transacción {{ pedido.pago.transaccion_id }}</template>
        </p>
      </div>
    </template>
  </section>
</template>

<style scoped>
.acciones {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.flujo {
  margin-top: 12px;
}

.aviso {
  margin-bottom: 16px;
}
.pago-linea {
  margin-top: 16px;
  padding: 10px 14px;
  font-size: .88rem;
  background: #f4f1f1;
  border-radius: 10px;
}

.pago-aprobado { color: #1b6e2c; background: #e7f4ea; }
.pago-pendiente { color: #8a6100; background: #fff6e0; }
.pago-rechazado,
.pago-anulado,
.pago-error { color: var(--error); background: #fdecea; }
</style>
