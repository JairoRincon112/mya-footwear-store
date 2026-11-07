// Store del catálogo (Pinia): marcas, tallas y colores. Se cargan una sola vez.
import { defineStore } from 'pinia'
import { ref } from 'vue'
import productoService from '@/services/productoService'

export const useCatalogoStore = defineStore('catalogo', () => {
  const marcas = ref([])
  const tallas = ref([])
  const colores = ref([])
  let carga = null

  function cargar() {
    carga ??= Promise.all([productoService.marcas(), productoService.tallas(), productoService.colores()])
      .then(([listaMarcas, listaTallas, listaColores]) => {
        marcas.value = listaMarcas
        tallas.value = listaTallas
        colores.value = listaColores
      })
      .catch((error) => {
        carga = null // Permite reintentar más tarde.
        throw error
      })
    return carga
  }

  const marcaPorSlug = (slug) => marcas.value.find((marca) => marca.slug === slug) ?? null

  return { marcas, tallas, colores, cargar, marcaPorSlug }
})
