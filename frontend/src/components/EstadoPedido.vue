<script setup>
// Etiqueta del estado de un pedido: siempre ícono + texto (el color nunca va solo).
import { computed } from 'vue'
import { ESTADOS_PEDIDO } from '@/utils/formato'

const props = defineProps({
  estado: { type: String, required: true },
})

const info = computed(() => ESTADOS_PEDIDO[props.estado] ?? { texto: props.estado, clase: '', icono: 'fa-circle' })
</script>

<template>
  <span class="estado" :class="info.clase">
    <i class="fa-solid" :class="info.icono" aria-hidden="true"></i>
    {{ info.texto }}
  </span>
</template>

<style scoped>
.estado {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  font-size: .8rem;
  font-weight: 600;
  white-space: nowrap;
  border-radius: 20px;
}

.estado-pendiente {
  color: #7a4b00;
  background: #fff1d6;
}

.estado-pagado {
  color: #0b4f8a;
  background: #e2effb;
}

.estado-enviado {
  color: #5a2d91;
  background: #efe6fa;
}

.estado-entregado {
  color: #1b6e2c;
  background: #e7f4ea;
}

.estado-cancelado {
  color: #5f5f5f;
  background: #ececec;
}
</style>
