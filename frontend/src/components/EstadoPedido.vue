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

/* Colores de semáforo, iguales a los filtros del panel:
   amarillo = pendiente, azul = pagado, naranja = en tránsito, verde = entregado, rojo = cancelado. */
.estado {
  border: 1.5px solid currentColor;
}

.estado-pendiente {
  color: #7a5600;
  background: #fff6d6;
}

.estado-pagado {
  color: #0d47a1;
  background: #e3effb;
}

.estado-enviado {
  color: #a84300;
  background: #fdeedd;
}

.estado-entregado {
  color: #17692e;
  background: #e6f4ea;
}

.estado-cancelado {
  color: #b71c1c;
  background: #fdecea;
}
</style>
