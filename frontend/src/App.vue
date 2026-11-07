<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import CarritoFlotante from '@/components/CarritoFlotante.vue'

const route = useRoute()

// El footer y el carrito solo se muestran en las páginas de la tienda.
const enTienda = computed(() => !route.path.startsWith('/panel') && route.name !== 'login')
</script>

<template>
  <AppHeader />
  <RouterView />
  <AppFooter v-if="enTienda" />
  <CarritoFlotante v-if="enTienda" />
</template>
