<script setup>
// Precio de un producto: si está en oferta muestra el precio anterior tachado y el % de descuento.
import { computed } from 'vue'
import { formatearPrecio } from '@/utils/formato'

const props = defineProps({
  precio: { type: Number, required: true },
  precioRegular: { type: Number, default: null },
  enOferta: { type: Boolean, default: false },
  tamano: { type: String, default: 'normal' }, // 'normal' | 'grande'
})

const porcentaje = computed(() =>
  props.enOferta && props.precioRegular ? Math.round((1 - props.precio / props.precioRegular) * 100) : 0,
)
</script>

<template>
  <span class="precio-producto" :class="tamano">
    <span class="actual">{{ formatearPrecio(precio) }}</span>
    <template v-if="enOferta && precioRegular">
      <s class="anterior"><span class="solo-lectores">Antes </span>{{ formatearPrecio(precioRegular) }}</s>
      <span class="descuento">-{{ porcentaje }}%</span>
    </template>
  </span>
</template>

<style scoped>
.precio-producto {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 10px;
}

.anterior {
  font-size: .8em;
  font-weight: 400;
  color: #777;
}

.descuento {
  padding: 1px 8px;
  font-size: .7em;
  font-weight: 700;
  color: #fff;
  background: #c24e00;
  border-radius: 20px;
  align-self: center;
}

.grande .actual {
  font-size: 1.8rem;
  font-weight: 600;
}
</style>
