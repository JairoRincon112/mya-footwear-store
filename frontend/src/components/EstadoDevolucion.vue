<script setup>
// Etiqueta del estado de una devolución: ícono + texto con los colores del semáforo.
import { computed } from 'vue'
import { ESTADOS_DEVOLUCION } from '@/utils/formato'

const props = defineProps({
  estado: { type: String, required: true },
})

const info = computed(() => ESTADOS_DEVOLUCION[props.estado] ?? { texto: props.estado, clase: '', icono: 'fa-circle' })
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
  border: 1.5px solid currentColor;
  border-radius: 20px;
}

.estado-pendiente { color: #7a5600; background: #fff6d6; }
.estado-pagado { color: #0d47a1; background: #e3effb; }
.estado-entregado { color: #17692e; background: #e6f4ea; }
.estado-cancelado { color: #b71c1c; background: #fdecea; }
</style>
