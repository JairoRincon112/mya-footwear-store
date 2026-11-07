<script setup>
// Controles "Anterior / Siguiente" para listados paginados.
defineProps({
  pagina: { type: Number, required: true },
  paginas: { type: Number, required: true },
  total: { type: Number, default: null },
  etiqueta: { type: String, default: 'registros' },
})
defineEmits(['cambiar'])
</script>

<template>
  <div v-if="paginas > 1" class="paginacion">
    <button type="button" class="boton boton-secundario boton-pequeno" :disabled="pagina <= 1" @click="$emit('cambiar', pagina - 1)">Anterior</button>
    <span>Página {{ pagina }} de {{ paginas }}<template v-if="total !== null"> · {{ total }} {{ etiqueta }}</template></span>
    <button type="button" class="boton boton-secundario boton-pequeno" :disabled="pagina >= paginas" @click="$emit('cambiar', pagina + 1)">Siguiente</button>
  </div>
</template>

<style scoped>
.paginacion {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 16px;
  font-size: .9rem;
}
</style>
