<script setup>
// Botón "Pagar con Wompi": pide los datos firmados al servidor, abre el widget
// oficial de Wompi y, al terminar, lleva a la página que verifica el resultado.
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import pagoService from '@/services/pagoService'
import { formatearPrecio } from '@/utils/formato'

const props = defineProps({
  pedidoId: { type: Number, required: true },
  total: { type: Number, required: true },
})

const router = useRouter()
const abriendo = ref(false)
const error = ref('')

async function pagar() {
  error.value = ''
  abriendo.value = true
  try {
    const datos = await pagoService.iniciar(props.pedidoId)
    const transaccion = await pagoService.abrirWidget(datos)
    if (transaccion?.id) {
      router.push({ name: 'pago-resultado', query: { id: transaccion.id, pedido: props.pedidoId } })
    }
  } catch (e) {
    error.value = e.message
  } finally {
    abriendo.value = false
  }
}
</script>

<template>
  <div class="pago-wompi">
    <div class="encabezado">
      <span class="icono"><i class="fa-solid fa-shield-halved"></i></span>
      <div>
        <strong>Paga ahora de forma segura</strong>
        <p>Tarjeta, PSE, Nequi o Bancolombia con Wompi. Tu pedido queda pagado al instante.</p>
      </div>
    </div>
    <button type="button" class="pagar" :disabled="abriendo" @click="pagar">
      <template v-if="abriendo"><i class="fa-solid fa-spinner fa-spin"></i> Abriendo Wompi...</template>
      <template v-else><i class="fa-solid fa-lock"></i> Pagar {{ formatearPrecio(total) }} con Wompi</template>
    </button>
    <p v-if="error" class="error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ error }}</p>
    <p class="medios" aria-label="Medios de pago">
      <i class="fa-brands fa-cc-visa" title="Visa"></i>
      <i class="fa-brands fa-cc-mastercard" title="Mastercard"></i>
      <i class="fa-brands fa-cc-amex" title="American Express"></i>
      <span>PSE · Nequi · Bancolombia</span>
    </p>
  </div>
</template>

<style scoped>
.pago-wompi {
  display: grid;
  gap: 14px;
  padding: 20px;
  color: #fff;
  background: radial-gradient(120% 120% at 100% 0%, #3a1a05 0%, #161210 60%);
  border-radius: 18px;
}

.encabezado {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.icono {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  font-size: 1.2rem;
  color: #000;
  background: var(--naranja);
  border-radius: 14px;
}

.encabezado strong {
  font-size: 1.05rem;
}

.encabezado p {
  margin-top: 2px;
  font-size: 0.88rem;
  color: rgba(255, 255, 255, 0.7);
}

.pagar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 14px;
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

.pagar:hover:not(:disabled) {
  background: #ff8533;
  transform: translateY(-2px);
}

.pagar:disabled {
  opacity: 0.7;
  cursor: wait;
}

.error {
  padding: 10px 12px;
  font-size: 0.88rem;
  color: #ffb4a8;
  background: rgba(244, 67, 54, 0.15);
  border-radius: 10px;
}

.medios {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 10px;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.55);
}

.medios i {
  font-size: 1.6rem;
}
</style>
